# NotesAO-ALPHA

## Table of Contents
- [Platform overview](#platform-overview)
- [Repository layout](#repository-layout)
- [Technology stack](#technology-stack)
- [Setting up a development environment](#setting-up-a-development-environment)
- [Operational notes](#operational-notes)
- [Audit summary](#audit-summary)
  - [Overview](#overview)
  - [Public Site and Authentication](#public-site-and-authentication)
  - [Clinic Deployments](#clinic-deployments)
  - [Supporting properties](#supporting-properties)
  - [NotePro Report Generator](#notepro-report-generator)
  - [Key Observations](#key-observations)
- [Further reading](#further-reading)
- [Contributing](#contributing)
- [Security & Privacy](#security--privacy)
- [License](#license)
- [Repository file tree](#repository-file-tree)

## Platform overview
NotesAO is a multi-tenant case management platform used by partner clinics to run client intake, attendance, document workflows, and automated reporting from a single shared codebase.
The public marketing site in `public_html/` funnels users into the universal login portal, which dispatches every request to the correct clinic-specific PHP application based on a short clinic code and then hands off authentication to that clinic’s `auth.php` configuration.【F:public_html/index.html†L1-L200】【F:public_html/login.php†L11-L200】
Each clinic directory (for example `ffltest/`, `dwag/`, `transform/`, `safatherhood/`, `lakeview/`, and others) contains the shared CRUD modules plus custom features such as intake pipelines, document templates, portal tooling, and reporting tweaks that match that clinic’s programs.【F:AUDIT_SUMMARY.md†L10-L25】 All clinics connect to the shared NotePro report generator, which packages program-specific Word/PDF documents and CSVs through a Flask + Celery backend that orchestrates dozens of Python scripts.【F:AUDIT_SUMMARY.md†L31-L35】【F:NotePro-Report-Generator/Reporting_GUI.py†L1-L178】

## Repository layout
| Path | Purpose |
| --- | --- |
| `public_html/` | Marketing landing page, legal pages, and `login.php`, which maps short codes to clinic folders/domains and bootstraps authentication for each tenant.【F:public_html/login.php†L11-L200】 |
| `adminclinic/` | Administrative console for onboarding clinics, handling its own authentication guard and management UI for central staff.【F:AUDIT_SUMMARY.md†L26-L27】【F:adminclinic/public_html/index.php†L1-L116】 |
| `enroll/` | Self-service enrollment microsite that hosts sign-up, plan selection, and payment onboarding flows.【F:AUDIT_SUMMARY.md†L26-L28】 |
| `clinicpublic/` | Public-facing microsite templates used to showcase clinic offerings with embedded authentication hooks.【F:AUDIT_SUMMARY.md†L26-L29】 |
| `ffltest/`, `dwag/`, `ctc/`, `transform/`, `safatherhood/`, `lakeview/`, etc. | Individual clinic apps built on the shared PHP modules, with local `public_html/`, `config/`, and `report/` assets tailored to the clinic’s programs.【F:AUDIT_SUMMARY.md†L10-L25】 |
| `NotePro-Report-Generator/` | Central reporting engine containing the Flask web UI, Celery tasks, PHP data harvesting bridge, and the library of document scripts grouped by program.【F:NotePro-Report-Generator/Reporting_GUI.py†L1-L178】【F:NotePro-Report-Generator/fetch_data.php†L1-L188】 |
| `global_config.php` | Global SMTP configuration shared by PHP sites (replace repository values with secure, environment-specific credentials before deploying).【F:global_config.php†L1-L17】 |
| `composer.json` | PHP dependency manifest, currently pulling PHPMailer for transactional mail delivery.【F:composer.json†L1-L5】 |
| `AUDIT_SUMMARY.md` | Detailed audit of clinic-specific customizations and cross-cutting services to use as a companion reference for this README.【F:AUDIT_SUMMARY.md†L1-L39】 |

## Technology stack
- **Web tier:** PHP applications served per clinic, using `mysqli` for database access and PHPMailer for outbound notifications.【F:public_html/login.php†L127-L195】【F:composer.json†L1-L5】
- **Databases:** Each clinic points to its own MySQL schema defined in its `config/` directory (samples include database, Twilio, and mail constants that must be customized per deployment).【F:NotePro-Report-Generator/fetch_data.php†L132-L156】【F:ffltest/config/config_sample.php†L1-L36】
- **Report generator:** Python 3 application powered by Flask, Celery, and Redis/Gunicorn workers to produce program documents from CSV exports.【F:NotePro-Report-Generator/Reporting_GUI.py†L1-L178】【F:NotePro-Report-Generator/manage_services.sh†L1-L93】
- **Dependencies:** All Python requirements are enumerated in `NotePro-Report-Generator/requirements.txt`, covering report-generation libraries (docxtpl, python-docx, pandas), async tooling (Celery, Redis), and operational utilities (Flower, Gunicorn).【F:NotePro-Report-Generator/requirements.txt†L1-L147】

## Setting up a development environment
1. **Clone the repository and install PHP dependencies.** Run `composer install` from the repository root to fetch PHPMailer, then configure your web server (Apache/Nginx) so the appropriate virtual hosts point to `public_html/` for the marketing site and to each clinic’s `public_html/` for tenant-specific portals.【F:composer.json†L1-L5】【F:public_html/login.php†L11-L200】
2. **Configure clinic credentials.** Copy each clinic’s `config/config_sample.php` (or local template) to `config.php`, updating MySQL, Twilio, and mail credentials; never commit production secrets. The report generator expects those configs to expose valid `mysqli` handles when invoked.【F:ffltest/config/config_sample.php†L1-L36】【F:NotePro-Report-Generator/fetch_data.php†L132-L156】
3. **Provision databases.** Create the MySQL schemas referenced in clinic configs and grant accounts with `SELECT/INSERT/UPDATE/DELETE` privileges; the generator will dynamically add extra columns defined in `fetch_data.php` when new report fields are required.【F:NotePro-Report-Generator/fetch_data.php†L46-L125】
4. **Prepare the Python environment.** Create a virtualenv inside `NotePro-Report-Generator/`, install dependencies with `pip install -r requirements.txt`, and ensure Redis is available locally for Celery task coordination.【F:NotePro-Report-Generator/requirements.txt†L1-L147】【F:NotePro-Report-Generator/Reporting_GUI.py†L25-L94】
5. **Run the report services.** Start Redis, Celery workers, and the Gunicorn-hosted Flask app; the provided `manage_services.sh` script shows the expected process lineup (adjust paths before using it in your environment).【F:NotePro-Report-Generator/manage_services.sh†L3-L93】
6. **Connect clinics to the generator.** Each clinic’s `public_html/reportgen.php` proxies AJAX requests to the shared Flask/PHP data pipeline; verify the clinic URL and permissions allow the generator to create ZIP archives in both the clinic and shared directories.【F:ffltest/public_html/reportgen.php†L1-L143】
7. **Configure global mail.** Update `global_config.php` with environment-specific SMTP credentials (or refactor into environment variables) before sending mail from production systems.【F:global_config.php†L1-L17】

## Operational notes
- The universal login remembers the last clinic selection via secure cookies and will redirect to the clinic’s `home.php` after authentication, so HTTPS and shared domain cookies must be enabled in every deployment.【F:public_html/login.php†L112-L195】
- The report generator can be triggered from the CLI (`php fetch_data.php <clinic_folder>`) or via HTTP; it validates allowed clinic folders and hydrates extra per-clinic fields before populating reporting tables.【F:NotePro-Report-Generator/fetch_data.php†L11-L188】
- Document scripts in `NotePro-Report-Generator/` are organized per program (BIPP, Anger Control, Parenting, SAE, etc.), enabling reuse across clinics; reference `AUDIT_SUMMARY.md` when mapping program coverage to clinic offerings.【F:AUDIT_SUMMARY.md†L31-L35】
- Secrets in sample configs and scripts are placeholders and should be rotated immediately in any live deployment. Treat `AUDIT_SUMMARY.md` and this README as living documents and update them whenever new clinics or programs are added.【F:ffltest/config/config_sample.php†L1-L36】【F:AUDIT_SUMMARY.md†L31-L39】

---

## Audit summary

### Overview
This document captures the repository-wide audit requested for the NotesAO environment. It consolidates findings about the public-facing site, shared authentication, individual clinic deployments, and the shared NotePro report generator engine.

### Public Site and Authentication
- `public_html/index.html` delivers the marketing landing page with fixed navigation, multiple feature sections, and a rich client-side layout that links to the login portal and hosted legal pages.【F:public_html/index.html†L1-L200】
- `public_html/login.php` centralizes authentication by mapping short clinic codes (e.g., `ffl`, `dwag`, `ctc`, `tbo`) to clinic-specific folders and domains, dynamically loading each clinic’s `auth.php`, and routing users accordingly.【F:public_html/login.php†L1-L150】

### Clinic Deployments
Each clinic folder contains a full PHP application with shared CRUD modules (clients, facilitators, therapy sessions, attendance) plus clinic-specific extensions:

- **Sandbox**: Includes reset tooling (`sandbox_reset.php`) and group stubs to support demos alongside the standard report and attendance suite.【b451e9†L1-L34】
- **FFL (ffltest)**: Adds client portal assets, reminders, messaging CSV workflows, and MAR report pages supporting extensive automation hooks.【9dccc8†L1-L71】
- **CTC**: Extends the base platform with dedicated instructor management (`instructors-*` pages) while retaining the shared intake and reporting flows.【e1a58c†L1-L40】
- **DWAG**: Offers import tooling (`client-create-import.php`), intake pipelines, office document downloads, and a clinic-specific logo, reflecting a heavy use of document templates.【dcc330†L1-L68】
- **Transform**: Standard toolkit plus transform-specific logo and aligns with the report generator’s extra identification and hours tracking fields for their program metrics.【a403a3†L1-L37】【F:NotePro-Report-Generator/fetch_data.php†L66-L111】
- **SA Fatherhood**: Adds consent/document template management, payment link administration, and client portal link configuration tailored to program-specific paperwork requirements.【bdc5f7†L1-L66】
- **Safer Path & Sage**: Share intake copy workflows and custom logos; both maintain the full reporting suite but without the additional client portal admin tooling seen in SA Fatherhood.【59862f†L1-L60】【fe0455†L1-L60】
- **Lakeview**: Mirrors the common modules but introduces unsubscribe support for messaging campaigns and custom branding assets.【9473ce†L1-L58】
- **Best Option**: Uses the generic framework yet pairs with extra contact fields supplied by the report generator (street, city, facilitator contact data).【7db916†L1-L58】【F:NotePro-Report-Generator/fetch_data.php†L76-L121】
- **Lankford**: Aligns closely with the Best Option footprint while supplying a separate clinic logo package.【794fb5†L1-L58】
- **Denton**: Provides a slimmer deployment—no client portal modules, unique dual authentication handler (`authenticate2.php`), and only the core attendance/reporting features plus MAR outputs.【2d7364†L1-L62】
- **DWAG, FFL, Lakeview, SA Fatherhood**: All expose `officedocuments.php` and CSV messaging utilities, indicating more advanced document automation compared to minimal clinics.【dcc330†L1-L68】【9dccc8†L1-L71】【9473ce†L1-L58】【bdc5f7†L1-L66】

### Supporting properties
- **Admin Clinic**: Focused management console for onboarding clinics, including logo uploads, profile management, and helper utilities.
- **Enrollment Portal (`enroll`)**: Dedicated onboarding flow with payment, agreements, and plan selection pages.【d7a1aa†L1-L2】
- **Clinic Public Microsite (`clinicpublic`)**: Marketing pages plus embedded auth to surface examples such as Denton’s public view.【d62623†L1-L4】

### NotePro Report Generator
- The shared generator hosts dozens of Python document scripts grouped by program (BIPP, Anger Control, Parenting, MRT, SAE, etc.), enabling consistent zip-package report production across clinics.【695c06†L1-L112】
- `Reporting_GUI.py` exposes a Flask + Celery web interface, launching PHP data-fetch routines, orchestrating asynchronous script execution, and distributing generated archives to users.【F:NotePro-Report-Generator/Reporting_GUI.py†L1-L180】
- `fetch_data.php` tailors data extraction per clinic, validating allowed clinic folders and defining additional schema/select clauses to pull custom fields (e.g., SA Fatherhood progress flags, Transform identification data, Best Option contact info).【F:NotePro-Report-Generator/fetch_data.php†L46-L129】
- Clinic-side `reportgen.php` (example: FFL) wires authentication to the generator, embeds cleanup flows, and proxies AJAX-driven fetch requests into the central `fetch_data.php`, ensuring generated documents and CSV tables stay synchronized between clinic directories and the shared engine.【F:ffltest/public_html/reportgen.php†L1-L143】

### Key Observations
- All clinics share a broad PHP base but rely on selective enablement of optional modules (client portal, intake copy, document templates) to match program needs.
- The report generator is the unifying layer: clinics connect through standardized PHP endpoints while extra fields are injected through configurable column maps per clinic, allowing dynamic template population without duplicating scripts.

## Further reading
- `AUDIT_SUMMARY.md` — in-depth notes on per-clinic differences and report generator behaviors to guide onboarding of new developers and operators.【F:AUDIT_SUMMARY.md†L1-L39】

## Contributing
Pull requests are welcome for bug fixes, documentation, and non-breaking refactors. Coordinate schema changes and cross-clinic behavior in advance to avoid tenant regressions. Follow existing code style and add tests where practical.

## Security & Privacy
Do not commit secrets or real client data. Generated artifacts containing PII must be excluded via `.gitignore` and operational filters. Review access logs and SMTP settings per clinic. Enable HTTPS and HSTS on all public endpoints.

## License
Proprietary. All rights reserved. Usage requires explicit permission from the NotesAO maintainers.

## Repository file tree
Intro: the filtered repository file tree appears below and intentionally omits logs, caches, archives, PII artifacts, mail payloads, and large vendor/runtime directories while preserving structural context.

├── adminclinic/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   └── public_html/
│       ├── admin/
│       │   ├── account.php*
│       │   ├── accounts.php*
│       │   ├── activity-reporting-common.php*
│       │   ├── activity-reporting-dumpcsv.php*
│       │   ├── activity-reporting.php*
│       │   ├── admin.css*
│       │   ├── admin.js*
│       │   ├── admin_navbar.php*
│       │   ├── admin.scss*
│       │   ├── client_file_update.php*
│       │   ├── emailtemplate.php*
│       │   ├── index.php*
│       │   ├── main.php*
│       │   ├── roles.php*
│       │   ├── settings.php*
│       │   └── user_accounts.php*
│       ├── authenticate.php*
│       ├── auth.php*
│       ├── clinic-create.php
│       ├── clinic-index.php
│       ├── clinic-read.php
│       ├── clinic_review_panel.php
│       ├── clinic-review.php
│       ├── clinic-update.php
│       ├── favicons/
│       │   ├── apple-touch-icon.png
│       │   ├── favicon-96x96.png
│       │   ├── favicon.ico
│       │   ├── favicon.svg
│       │   ├── site.webmanifest
│       │   ├── web-app-manifest-192x192.png
│       │   └── web-app-manifest-512x512.png
│       ├── helpers.php*
│       ├── home.php*
│       ├── index.php*
│       ├── logout.php*
│       ├── navbar.php*
│       ├── notesao.png
│       ├── profile.php*
│       ├── sql_functions.php*
│       └── upload_logo.php
├── adminclinic_structure.txt
├── .bash_history
├── .bash_logout
├── .bash_profile
├── .bashrc
├── bestoption/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── bestoptionlogo.png
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── bestoption_brand_issues.txt
├── bestoption_public_diff.txt
├── bestoption_saf_public_diff.txt
├── clinicpublic/
│   ├── auth.php*
│   ├── Background.png*
│   ├── denton.css*
│   ├── denton.html*
│   ├── denton.js*
│   ├── favicon.ico*
│   ├── .htaccess*
│   ├── .htaccess.backup*
│   ├── index.html*
│   ├── index.php*
│   ├── login.php*
│   ├── logo.png*
│   ├── NoteProLogo2.png*
│   ├── NoteProLogoFinal.ico*
│   ├── php.ini*
│   └── .user.ini*
├── composer.json
├── composer.lock
├── .config/
│   ├── composer/
│   │   └── .htaccess
│   └── libreoffice/
│       └── 4/
│           ├── crash/
│           │   └── dump.ini
│           └── user/
│               ├── autotext/
│               │   └── mytexts.bau
│               ├── basic/
│               │   ├── dialog.xlc
│               │   ├── script.xlc
│               │   └── Standard/
│               │       ├── dialog.xlb
│               │       ├── Module1.xba
│               │       └── script.xlb
│               ├── config/
│               │   ├── autotbl.fmt
│               │   └── javasettings_Linux_X86_64.xml
│               ├── database/
│               │   ├── biblio/
│               │   │   ├── biblio.dbf
│               │   │   └── biblio.dbt
│               │   ├── biblio.odb
│               │   └── evolocal.odb
│               ├── extensions/
│               │   ├── buildid
│               │   ├── bundled/
│               │   │   ├── extensions.pmap
│               │   │   ├── lastsynchronized
│               │   │   └── registry/
│               │   │       ├── com.sun.star.comp.deployment.bundle.PackageRegistryBackend/
│               │   │       │   └── backenddb.xml
│               │   │       ├── com.sun.star.comp.deployment.component.PackageRegistryBackend/
│               │   │       │   └── unorc
│               │   │       ├── com.sun.star.comp.deployment.configuration.PackageRegistryBackend/
│               │   │       │   ├── backenddb.xml
│               │   │       │   └── configmgr.ini
│               │   │       ├── com.sun.star.comp.deployment.help.PackageRegistryBackend/
│               │   │       │   └── backenddb.xml
│               │   │       └── com.sun.star.comp.deployment.script.PackageRegistryBackend/
│               │   │           └── backenddb.xml
│               │   └── shared/
│               │       ├── lastsynchronized
│               │       └── registry/
│               │           ├── com.sun.star.comp.deployment.configuration.PackageRegistryBackend/
│               │           │   └── backenddb.xml
│               │           └── com.sun.star.comp.deployment.help.PackageRegistryBackend/
│               │               └── backenddb.xml
│               ├── gallery/
│               │   ├── sg30.sdv
│               │   └── sg30.thm
│               └── registrymodifications.xcu
├── ctc/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── ctclogo.png*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── instructors-create.php*
│   │   ├── instructors-delete.php*
│   │   ├── instructors-index.php*
│   │   ├── instructors-read.php*
│   │   ├── instructors-update.php*
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── denton/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate2.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php*
│   │   ├── mar.php -> mar2.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   └── truant_client.php*
│   └── report/
│       ├── mar/
│       │   └── CJAD BIPP MAR sample.pdf*
│       └── merge/
│           ├── connection_setup.docx*
│           └── samples/
│               ├── BIPP Progress Note.27.C.dotm*
│               ├── BIPP Progress Note.27.denton.dotm*
│               ├── report2_progress_note.docx*
│               ├── report2_progress_note_pic.docx*
│               ├── report3_progress_note_pic.docx*
│               ├── report3_progress_note_pic_vert.docx*
│               ├── T4C_EntranceNotification.docx*
│               ├── t4c_merge_results.docx*
│               └── T4C_ProgressNoteSample.docx*
├── directory_overview_20250717.txt
├── Downloads/
│   ├── =4.2.5*
│   ├── AC_Completion_Documents_Script.py*
│   ├── AC_Entrance_Notifications_Script.py*
│   ├── AC_Exit_Notices_Script.py*
│   ├── AC_Progress_Reports_Script.py*
│   ├── AC_Unexcused_Absences_Script.py*
│   ├── BIPP_Behavior_Contracts_Script.py
│   ├── BIPP_Completion_Documents_Script.py*
│   ├── BIPP_CUR_Progress_Reports_Script.py*
│   ├── BIPP_Entrance_Notifications_Script.py*
│   ├── BIPP_Exit_Notices_Script.py*
│   ├── BIPP_SOC_Progress_Reports_Script.py*
│   ├── BIPP_Unexcused_Absences_Script.py*
│   ├── BIPP_Victim_Letters_Script.py
│   ├── celery.out*
│   ├── check_absences.py*
│   ├── csv/
│   │   ├── ctc/
│   │   │   ├── report5_dump_20250612.csv
│   │   │   └── updated_report5_dump_20250612.csv
│   │   ├── ffltest/
│   │   │   └── report5_dump_20250612.csv
│   │   ├── safatherhood/
│   │   │   └── report5_dump_20250610.csv
│   │   ├── sandbox/
│   │   │   └── report5_dump_20250610.csv
│   │   └── transform/
│   │       └── report5_dump_20250507.csv
│   ├── dump.rdb*
│   ├── fetch_data.php*
│   ├── generator/
│   │   ├── =4.2.5*
│   │   ├── AC_Completion_Documents_Script.py*
│   │   ├── AC_Entrance_Notifications_Script.py*
│   │   ├── AC_Exit_Notices_Script.py*
│   │   ├── AC_Progress_Reports_Script.py*
│   │   ├── AC_Unexcused_Absences_Script.py*
│   │   ├── BIPP_Behavior_Contracts_Script.py
│   │   ├── BIPP_Completion_Documents_Script.py*
│   │   ├── BIPP_CUR_Progress_Reports_Script.py*
│   │   ├── BIPP_Entrance_Notifications_Script.py*
│   │   ├── BIPP_Exit_Notices_Script.py*
│   │   ├── BIPP_SOC_Progress_Reports_Script.py*
│   │   ├── BIPP_Unexcused_Absences_Script.py*
│   │   ├── BIPP_Victim_Letters_Script.py
│   │   ├── celery.out*
│   │   ├── check_absences.py*
│   │   ├── csv/
│   │   │   ├── ctc/
│   │   │   │   ├── report5_dump_20250612.csv
│   │   │   │   └── updated_report5_dump_20250612.csv
│   │   │   ├── ffltest/
│   │   │   │   └── report5_dump_20250612.csv
│   │   │   ├── safatherhood/
│   │   │   │   └── report5_dump_20250610.csv
│   │   │   ├── sandbox/
│   │   │   │   └── report5_dump_20250610.csv
│   │   │   └── transform/
│   │   │       └── report5_dump_20250507.csv
│   │   ├── dump.rdb*
│   │   ├── fetch_data.php*
│   │   ├── .github/
│   │   │   └── workflows/
│   │   │       └── create-diagram.yml*
│   │   ├── .gitignore*
│   │   ├── gunicorn_config.py*
│   │   ├── gunicorn.conf.py*
│   │   ├── gunicorn.out*
│   │   ├── .htaccess*
│   │   ├── logs/
│   │   │   ├── sedckeqKw
│   │   │   └── sedlWMJsy
│   │   ├── manage_services_denton.sh*
│   │   ├── manage_services.sh*
│   │   ├── nohup.out*
│   │   ├── README.md*
│   │   ├── redis.conf*
│   │   ├── redis.out*
│   │   ├── Reporting_GUI.py*
│   │   ├── requirements.txt*
│   │   ├── static/
│   │   │   ├── Background.png*
│   │   │   ├── Free_for_Life_Logo.png*
│   │   │   ├── GCheck.png*
│   │   │   ├── jquery-3.7.1.min.js*
│   │   │   ├── loading-spinner.gif*
│   │   │   ├── NoteProLogo2.png*
│   │   │   ├── NoteProLogoFinal.ico*
│   │   │   └── REx.png*
│   │   ├── stop_and_restart_nginx.sh*
│   │   ├── T4C_Completion_Documents_Script.py*
│   │   ├── T4C_Entrance_Notifications_Script.py*
│   │   ├── T4C_Exit_Notices_Script.py*
│   │   ├── T4C_Progress_Reports_Script.py*
│   │   ├── T4C_Unexcused_Absences_Script.py*
│   │   ├── tasks/
│   │   │   └── status_task_6750cc934b0f24.41758572.txt*
│   │   ├── templates/
│   │   │   ├── ctc/
│   │   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   │   ├── Template.AC Progress Report.docx
│   │   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   │   ├── Template.BIPP Progress Report.docx
│   │   │   │   ├── Template.TIPS Completion Certificate.docx*
│   │   │   │   └── Template.TIPS Progress Report.docx
│   │   │   ├── ffltest/
│   │   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   │   ├── BIPP.Template Victim Mailing Labels.docx
│   │   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   │   ├── greencheck.png*
│   │   │   │   ├── greencheck.svg*
│   │   │   │   ├── index.html*
│   │   │   │   ├── purplecross.png*
│   │   │   │   ├── purplecross.svg*
│   │   │   │   ├── redcross.png*
│   │   │   │   ├── redcross.svg*
│   │   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   │   ├── Template.AC Completion Letter.docx*
│   │   │   │   ├── Template.AC Completion Progress Report.docx*
│   │   │   │   ├── Template.AC Entrance Notification.docx*
│   │   │   │   ├── Template.AC Exit Notice.docx*
│   │   │   │   ├── Template.AC Exit Progress Report.docx*
│   │   │   │   ├── Template.AC Progress Report.docx*
│   │   │   │   ├── Template.AC Unexcused Absence.docx*
│   │   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   │   ├── Template.BIPP Completion Letter.docx*
│   │   │   │   ├── Template.BIPP Completion Progress Report.docx*
│   │   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │   │   │   ├── Template.BIPP CUR Progress Report.docx
│   │   │   │   ├── Template.BIPP CUR Progress Report Virtual.docx
│   │   │   │   ├── Template.BIPP Entrance Notification.docx*
│   │   │   │   ├── Template.BIPP Exit Notice.docx*
│   │   │   │   ├── Template.BIPP Exit Progress Report.docx*
│   │   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │   │   │   ├── Template.BIPP Progress Report.docx*
│   │   │   │   ├── Template.BIPP Progress Report Virtual.docx*
│   │   │   │   ├── Template.BIPP SOC Progress Report.docx*
│   │   │   │   ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │   │   │   ├── Template.BIPP Unexcused Absence.docx*
│   │   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   │   ├── Template.BIPP Victim Exit.docx
│   │   │   │   ├── Template.T4C Completion Certificate.docx*
│   │   │   │   ├── Template.T4C Completion Letter.docx*
│   │   │   │   ├── Template.T4C Completion Progress Report.docx*
│   │   │   │   ├── Template.T4C Entrance Notification.docx*
│   │   │   │   ├── Template.T4C Exit Notice.docx*
│   │   │   │   ├── Template.T4C Exit Progress Report.docx*
│   │   │   │   ├── Template.T4C Progress Report.docx
│   │   │   │   └── Template.T4C Unexcused Absence.docx*
│   │   │   ├── .htaccess*
│   │   │   ├── safatherhood/
│   │   │   │   ├── Blank Progress Notes.xlsx
│   │   │   │   ├── Template.BIPP Completion Certificate.docx
│   │   │   │   └── Template.BIPP Entrance Notification.docx
│   │   │   └── sandbox/
│   │   │       ├── BIPP Curriculum Blank Notes copy.xlsx*
│   │   │       ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │       ├── BIPP.Template Victim Mailing Labels.docx
│   │   │       ├── Blank Progress Notes copy.xlsx*
│   │   │       ├── Blank Progress Notes.xlsx*
│   │   │       ├── greencheck copy.png*
│   │   │       ├── greencheck copy.svg*
│   │   │       ├── greencheck.png*
│   │   │       ├── greencheck.svg*
│   │   │       ├── index copy.html*
│   │   │       ├── index.html*
│   │   │       ├── purplecross copy.png*
│   │   │       ├── purplecross copy.svg*
│   │   │       ├── purplecross.png*
│   │   │       ├── purplecross.svg*
│   │   │       ├── redcross copy.png*
│   │   │       ├── redcross copy.svg*
│   │   │       ├── redcross.png*
│   │   │       ├── redcross.svg*
│   │   │       ├── Template.AC Completion Certificate copy.docx*
│   │   │       ├── Template.AC Completion Certificate.docx*
│   │   │       ├── Template.AC Completion Letter copy.docx*
│   │   │       ├── Template.AC Completion Letter.docx*
│   │   │       ├── Template.AC Completion Progress Report copy.docx*
│   │   │       ├── Template.AC Completion Progress Report.docx*
│   │   │       ├── Template.AC Entrance Notification copy.docx*
│   │   │       ├── Template.AC Entrance Notification.docx*
│   │   │       ├── Template.AC Exit Notice copy.docx*
│   │   │       ├── Template.AC Exit Notice.docx*
│   │   │       ├── Template.AC Exit Progress Report copy.docx*
│   │   │       ├── Template.AC Exit Progress Report.docx*
│   │   │       ├── Template.AC Progress Report copy.docx*
│   │   │       ├── Template.AC Progress Report.docx*
│   │   │       ├── Template.AC Unexcused Absence copy.docx*
│   │   │       ├── Template.AC Unexcused Absence.docx*
│   │   │       ├── Template.BIPP Behavior Contract.docx
│   │   │       ├── Template.BIPP Completion Certificate copy.docx*
│   │   │       ├── Template.BIPP Completion Certificate.docx*
│   │   │       ├── Template.BIPP Completion Letter copy.docx*
│   │   │       ├── Template.BIPP Completion Letter.docx*
│   │   │       ├── Template.BIPP Completion Progress Report copy.docx*
│   │   │       ├── Template.BIPP Completion Progress Report.docx*
│   │   │       ├── Template.BIPP Completion Progress Report Virtual copy.docx*
│   │   │       ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │   │       ├── Template.BIPP CUR Progress Report copy.docx
│   │   │       ├── Template.BIPP CUR Progress Report.docx*
│   │   │       ├── Template.BIPP CUR Progress Report Virtual copy.docx
│   │   │       ├── Template.BIPP CUR Progress Report Virtual.docx*
│   │   │       ├── Template.BIPP Entrance Notification copy.docx*
│   │   │       ├── Template.BIPP Entrance Notification.docx*
│   │   │       ├── Template.BIPP Exit Notice copy.docx*
│   │   │       ├── Template.BIPP Exit Notice.docx*
│   │   │       ├── Template.BIPP Exit Progress Report copy.docx*
│   │   │       ├── Template.BIPP Exit Progress Report.docx*
│   │   │       ├── Template.BIPP Exit Progress Report Virtual copy.docx*
│   │   │       ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │   │       ├── Template.BIPP Progress Report copy.docx*
│   │   │       ├── Template.BIPP Progress Report.docx*
│   │   │       ├── Template.BIPP Progress Report Virtual copy.docx*
│   │   │       ├── Template.BIPP Progress Report Virtual.docx*
│   │   │       ├── Template.BIPP SOC Progress Report copy.docx*
│   │   │       ├── Template.BIPP SOC Progress Report.docx*
│   │   │       ├── Template.BIPP SOC Progress Report Virtual copy.docx*
│   │   │       ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │   │       ├── Template.BIPP Unexcused Absence copy.docx*
│   │   │       ├── Template.BIPP Unexcused Absence.docx*
│   │   │       ├── Template.BIPP Victim Completion.docx
│   │   │       ├── Template.BIPP Victim Entrance.docx
│   │   │       ├── Template.BIPP Victim Exit.docx
│   │   │       ├── Template.T4C Completion Certificate copy.docx*
│   │   │       ├── Template.T4C Completion Certificate.docx*
│   │   │       ├── Template.T4C Completion Letter copy.docx*
│   │   │       ├── Template.T4C Completion Letter.docx*
│   │   │       ├── Template.T4C Completion Progress Report copy.docx*
│   │   │       ├── Template.T4C Completion Progress Report.docx*
│   │   │       ├── Template.T4C Entrance Notification copy.docx*
│   │   │       ├── Template.T4C Entrance Notification.docx*
│   │   │       ├── Template.T4C Exit Notice copy.docx*
│   │   │       ├── Template.T4C Exit Notice.docx*
│   │   │       ├── Template.T4C Exit Progress Report copy.docx*
│   │   │       ├── Template.T4C Exit Progress Report.docx*
│   │   │       ├── Template.T4C Progress Report copy.docx
│   │   │       ├── Template.T4C Progress Report.docx*
│   │   │       ├── Template.T4C Unexcused Absence copy.docx*
│   │   │       └── Template.T4C Unexcused Absence.docx*
│   │   ├── TIPS_Completion_Documents_Script.py*
│   │   ├── TIPS_Entrance_Notifications_Script.py*
│   │   ├── TIPS_Exit_Notices_Script.py*
│   │   ├── TIPS_Progress_Reports_Script.py*
│   │   ├── TIPS_Unexcused_Absences_Script.py*
│   │   └── Update_Clients.py*
│   ├── .github/
│   │   └── workflows/
│   │       └── create-diagram.yml*
│   ├── .gitignore*
│   ├── gunicorn_config.py*
│   ├── gunicorn.conf.py*
│   ├── gunicorn.out*
│   ├── .htaccess*
│   ├── logs/
│   │   ├── sedckeqKw
│   │   └── sedlWMJsy
│   ├── manage_services_denton.sh*
│   ├── manage_services.sh*
│   ├── nohup.out*
│   ├── README.md*
│   ├── redis.conf*
│   ├── redis.out*
│   ├── Reporting_GUI.py*
│   ├── requirements.txt*
│   ├── static/
│   │   ├── Background.png*
│   │   ├── Free_for_Life_Logo.png*
│   │   ├── GCheck.png*
│   │   ├── jquery-3.7.1.min.js*
│   │   ├── loading-spinner.gif*
│   │   ├── NoteProLogo2.png*
│   │   ├── NoteProLogoFinal.ico*
│   │   └── REx.png*
│   ├── stop_and_restart_nginx.sh*
│   ├── T4C_Completion_Documents_Script.py*
│   ├── T4C_Entrance_Notifications_Script.py*
│   ├── T4C_Exit_Notices_Script.py*
│   ├── T4C_Progress_Reports_Script.py*
│   ├── T4C_Unexcused_Absences_Script.py*
│   ├── tasks/
│   │   └── status_task_6750cc934b0f24.41758572.txt*
│   ├── templates/
│   │   ├── ctc/
│   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   ├── Template.AC Progress Report.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   ├── Template.BIPP Progress Report.docx
│   │   │   ├── Template.TIPS Completion Certificate.docx*
│   │   │   └── Template.TIPS Progress Report.docx
│   │   ├── ffltest/
│   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   ├── BIPP.Template Victim Mailing Labels.docx
│   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   ├── greencheck.png*
│   │   │   ├── greencheck.svg*
│   │   │   ├── index.html*
│   │   │   ├── purplecross.png*
│   │   │   ├── purplecross.svg*
│   │   │   ├── redcross.png*
│   │   │   ├── redcross.svg*
│   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   ├── Template.AC Completion Letter.docx*
│   │   │   ├── Template.AC Completion Progress Report.docx*
│   │   │   ├── Template.AC Entrance Notification.docx*
│   │   │   ├── Template.AC Exit Notice.docx*
│   │   │   ├── Template.AC Exit Progress Report.docx*
│   │   │   ├── Template.AC Progress Report.docx*
│   │   │   ├── Template.AC Unexcused Absence.docx*
│   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   ├── Template.BIPP Completion Letter.docx*
│   │   │   ├── Template.BIPP Completion Progress Report.docx*
│   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP CUR Progress Report.docx
│   │   │   ├── Template.BIPP CUR Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Entrance Notification.docx*
│   │   │   ├── Template.BIPP Exit Notice.docx*
│   │   │   ├── Template.BIPP Exit Progress Report.docx*
│   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP Progress Report.docx*
│   │   │   ├── Template.BIPP Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP SOC Progress Report.docx*
│   │   │   ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP Unexcused Absence.docx*
│   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   ├── Template.BIPP Victim Exit.docx
│   │   │   ├── Template.T4C Completion Certificate.docx*
│   │   │   ├── Template.T4C Completion Letter.docx*
│   │   │   ├── Template.T4C Completion Progress Report.docx*
│   │   │   ├── Template.T4C Entrance Notification.docx*
│   │   │   ├── Template.T4C Exit Notice.docx*
│   │   │   ├── Template.T4C Exit Progress Report.docx*
│   │   │   ├── Template.T4C Progress Report.docx
│   │   │   └── Template.T4C Unexcused Absence.docx*
│   │   ├── .htaccess*
│   │   ├── safatherhood/
│   │   │   ├── Blank Progress Notes.xlsx
│   │   │   ├── Template.BIPP Completion Certificate.docx
│   │   │   └── Template.BIPP Entrance Notification.docx
│   │   └── sandbox/
│   │       ├── BIPP Curriculum Blank Notes copy.xlsx*
│   │       ├── BIPP Curriculum Blank Notes.xlsx*
│   │       ├── BIPP.Template Victim Mailing Labels.docx
│   │       ├── Blank Progress Notes copy.xlsx*
│   │       ├── Blank Progress Notes.xlsx*
│   │       ├── greencheck copy.png*
│   │       ├── greencheck copy.svg*
│   │       ├── greencheck.png*
│   │       ├── greencheck.svg*
│   │       ├── index copy.html*
│   │       ├── index.html*
│   │       ├── purplecross copy.png*
│   │       ├── purplecross copy.svg*
│   │       ├── purplecross.png*
│   │       ├── purplecross.svg*
│   │       ├── redcross copy.png*
│   │       ├── redcross copy.svg*
│   │       ├── redcross.png*
│   │       ├── redcross.svg*
│   │       ├── Template.AC Completion Certificate copy.docx*
│   │       ├── Template.AC Completion Certificate.docx*
│   │       ├── Template.AC Completion Letter copy.docx*
│   │       ├── Template.AC Completion Letter.docx*
│   │       ├── Template.AC Completion Progress Report copy.docx*
│   │       ├── Template.AC Completion Progress Report.docx*
│   │       ├── Template.AC Entrance Notification copy.docx*
│   │       ├── Template.AC Entrance Notification.docx*
│   │       ├── Template.AC Exit Notice copy.docx*
│   │       ├── Template.AC Exit Notice.docx*
│   │       ├── Template.AC Exit Progress Report copy.docx*
│   │       ├── Template.AC Exit Progress Report.docx*
│   │       ├── Template.AC Progress Report copy.docx*
│   │       ├── Template.AC Progress Report.docx*
│   │       ├── Template.AC Unexcused Absence copy.docx*
│   │       ├── Template.AC Unexcused Absence.docx*
│   │       ├── Template.BIPP Behavior Contract.docx
│   │       ├── Template.BIPP Completion Certificate copy.docx*
│   │       ├── Template.BIPP Completion Certificate.docx*
│   │       ├── Template.BIPP Completion Letter copy.docx*
│   │       ├── Template.BIPP Completion Letter.docx*
│   │       ├── Template.BIPP Completion Progress Report copy.docx*
│   │       ├── Template.BIPP Completion Progress Report.docx*
│   │       ├── Template.BIPP Completion Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │       ├── Template.BIPP CUR Progress Report copy.docx
│   │       ├── Template.BIPP CUR Progress Report.docx*
│   │       ├── Template.BIPP CUR Progress Report Virtual copy.docx
│   │       ├── Template.BIPP CUR Progress Report Virtual.docx*
│   │       ├── Template.BIPP Entrance Notification copy.docx*
│   │       ├── Template.BIPP Entrance Notification.docx*
│   │       ├── Template.BIPP Exit Notice copy.docx*
│   │       ├── Template.BIPP Exit Notice.docx*
│   │       ├── Template.BIPP Exit Progress Report copy.docx*
│   │       ├── Template.BIPP Exit Progress Report.docx*
│   │       ├── Template.BIPP Exit Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │       ├── Template.BIPP Progress Report copy.docx*
│   │       ├── Template.BIPP Progress Report.docx*
│   │       ├── Template.BIPP Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Progress Report Virtual.docx*
│   │       ├── Template.BIPP SOC Progress Report copy.docx*
│   │       ├── Template.BIPP SOC Progress Report.docx*
│   │       ├── Template.BIPP SOC Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │       ├── Template.BIPP Unexcused Absence copy.docx*
│   │       ├── Template.BIPP Unexcused Absence.docx*
│   │       ├── Template.BIPP Victim Completion.docx
│   │       ├── Template.BIPP Victim Entrance.docx
│   │       ├── Template.BIPP Victim Exit.docx
│   │       ├── Template.T4C Completion Certificate copy.docx*
│   │       ├── Template.T4C Completion Certificate.docx*
│   │       ├── Template.T4C Completion Letter copy.docx*
│   │       ├── Template.T4C Completion Letter.docx*
│   │       ├── Template.T4C Completion Progress Report copy.docx*
│   │       ├── Template.T4C Completion Progress Report.docx*
│   │       ├── Template.T4C Entrance Notification copy.docx*
│   │       ├── Template.T4C Entrance Notification.docx*
│   │       ├── Template.T4C Exit Notice copy.docx*
│   │       ├── Template.T4C Exit Notice.docx*
│   │       ├── Template.T4C Exit Progress Report copy.docx*
│   │       ├── Template.T4C Exit Progress Report.docx*
│   │       ├── Template.T4C Progress Report copy.docx
│   │       ├── Template.T4C Progress Report.docx*
│   │       ├── Template.T4C Unexcused Absence copy.docx*
│   │       └── Template.T4C Unexcused Absence.docx*
│   ├── TIPS_Completion_Documents_Script.py*
│   ├── TIPS_Entrance_Notifications_Script.py*
│   ├── TIPS_Exit_Notices_Script.py*
│   ├── TIPS_Progress_Reports_Script.py*
│   ├── TIPS_Unexcused_Absences_Script.py*
│   └── Update_Clients.py*
├── dwag/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create-import.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal_lib.php
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client-reminders.php
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── dwaglogo.png*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── info.php
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── unsubscribe.php
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── enroll/
│   ├── config/
│   │   └── config.php*
│   └── public_html/
│       ├── agreement.php
│       ├── _agreements/
│       │   ├── eua-2025-08-29.html
│       │   ├── pdf/
│       │   │   ├── eua_20250829_124927.html
│       │   │   ├── eua_20250829_125456.html
│       │   │   ├── eua_20250829_125725.html
│       │   │   ├── eua_20250829_130257.html
│       │   │   ├── eua_20250829_144608.html
│       │   │   ├── eua_20250829_182530.html
│       │   │   ├── eua_20250901_083825.html
│       │   │   ├── eua_20250901_102550.html
│       │   │   ├── eua_20250903_152650.html
│       │   │   └── eua.pdf
│       │   └── sig/
│       │       ├── cardauth_20250829_144632.png
│       │       ├── cardauth_20250829_144902.png
│       │       ├── cardauth_20250829_145022.png
│       │       ├── cardauth_20250901_083854.png
│       │       ├── cardauth_20250901_102624.png
│       │       ├── cardauth_20250903_152724.png
│       │       ├── sig_20250829_124927.png
│       │       ├── sig_20250829_125456.png
│       │       ├── sig_20250829_125725.png
│       │       ├── sig_20250829_130257.png
│       │       ├── sig_20250829_144608.png
│       │       ├── sig_20250829_182530.png
│       │       ├── sig_20250901_083825.png
│       │       ├── sig_20250901_102550.png
│       │       └── sig_20250903_152650.png
│       ├── api/
│       │   ├── charge_onboarding_and_subscribe.php
│       │   └── save_card_only.php
│       ├── card-auth.php
│       ├── list_plans.php
│       ├── onboarding-pay.php
│       ├── print_eua.php
│       ├── seed_eua.php
│       ├── start.php
│       ├── thank-you.php
│       └── tools/
│           └── check_square.php
├── etc/
│   ├── ffl.notesao.com/
│   │   ├── angie.rcube.db
│   │   ├── angie.rcube.db.1748581832
│   │   ├── angie.rcube.db.1748927425
│   │   ├── angie.rcube.db.latest -> angie.rcube.db.1748927425
│   │   ├── passwd
│   │   ├── _privs.json
│   │   ├── @pwcache/
│   │   │   ├── angie
│   │   │   ├── angie@ffl.notesao.com
│   │   │   ├── no-reply
│   │   │   └── reporting
│   │   ├── quota
│   │   └── shadow
│   ├── ftpquota*
│   ├── notesao.com/
│   │   ├── admin.rcube.db
│   │   ├── admin.rcube.db.1748581832
│   │   ├── admin.rcube.db.1748927425
│   │   ├── admin.rcube.db.1757481053
│   │   ├── admin.rcube.db.latest -> admin.rcube.db.1757481053
│   │   ├── ceo.rcube.db
│   │   ├── ceo.rcube.db.1757481053
│   │   ├── ceo.rcube.db.latest -> ceo.rcube.db.1757481053
│   │   ├── david.rcube.db
│   │   ├── david.rcube.db.1757481053
│   │   ├── david.rcube.db.latest -> david.rcube.db.1757481053
│   │   ├── gabriel.rcube.db
│   │   ├── gabriel.rcube.db.1757481053
│   │   ├── gabriel.rcube.db.latest -> gabriel.rcube.db.1757481053
│   │   ├── lwtest.rcube.db
│   │   ├── no-preply.rcube.db
│   │   ├── no-preply.rcube.db.1748581832
│   │   ├── no-preply.rcube.db.1748927425
│   │   ├── no-preply.rcube.db.latest -> no-preply.rcube.db.1748927425
│   │   ├── passwd
│   │   ├── privacy.rcube.db
│   │   ├── privacy.rcube.db.1757481053
│   │   ├── privacy.rcube.db.latest -> privacy.rcube.db.1757481053
│   │   ├── _privs.json
│   │   ├── @pwcache/
│   │   ├── quota
│   │   ├── sales.rcube.db
│   │   ├── sales.rcube.db.1748581832
│   │   ├── sales.rcube.db.1748927425
│   │   ├── sales.rcube.db.1757481053
│   │   ├── sales.rcube.db.latest -> sales.rcube.db.1757481053
│   │   ├── shadow
│   │   ├── test.rcube.db
│   │   ├── test.rcube.db.1757481053
│   │   ├── test.rcube.db.latest -> test.rcube.db.1757481053
│   │   ├── vanmartin.rcube.db
│   │   ├── vanmartin.rcube.db.1757481053
│   │   └── vanmartin.rcube.db.latest -> vanmartin.rcube.db.1757481053
│   ├── notesao.rcube.db
│   ├── notesao.rcube.db.1748581832
│   ├── notesao.rcube.db.1748927425
│   ├── notesao.rcube.db.1757481053
│   ├── notesao.rcube.db.latest -> notesao.rcube.db.1757481053
│   ├── safatherhood.notesao.com/
│   │   ├── passwd
│   │   ├── @pwcache/
│   │   │   ├── no-reply
│   │   │   └── reporting
│   │   ├── quota
│   │   └── shadow
│   └── tbo.notesao.com/
│       ├── passwd
│       ├── @pwcache/
│       │   └── reporting
│       ├── quota
│       └── shadow
├── ffltest/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal_lib.php
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client-reminders.php
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── evaluation-index.php
│   │   ├── evaluation-review.php
│   │   ├── evaluations-pai.php
│   │   ├── evaluations-vtc.php
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-copy.php
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── unsubscribe.php
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── .gitconfig
├── .gitignore
├── global_config.php
├── .imunify_patch_id
├── lakeview/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal_lib.php
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client-reminders.php
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── lakeviewlogo.png
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── unsubscribe.php
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── lankford/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── lankfordlogo.png
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       └── update_clients.sh*
├── lankford_ffl_filtered.txt
├── lankford_search_results.txt
├── .lastlogin
├── lib/
│   └── mailer.php
├── .myimunify_id
├── .mysql_history
├── NotePro-Report-Generator/
│   ├── =4.2.5*
│   ├── AC_Behavior_Contracts_Script.py
│   ├── AC_Completion_Documents_Script.py*
│   ├── AC_Enrollment_Letters_Script.py*
│   ├── AC_Entrance_Notifications_Script.py*
│   ├── AC_Exit_Notices_Script.py*
│   ├── AC_Progress_Reports_Script.py*
│   ├── AC_Unexcused_Absences_Script.py*
│   ├── AC_Victim_Letters_Script.py
│   ├── ADVANCE_PARENTING_Behavior_Contracts_Script.py
│   ├── ADVANCE_PARENTING_Completion_Documents_Script.py
│   ├── ADVANCE_PARENTING_Enrollment_Letters_Script.py
│   ├── ADVANCE_PARENTING_Entrance_Notifications_Script.py
│   ├── ADVANCE_PARENTING_Exit_Notices_Script.py
│   ├── ADVANCE_PARENTING_Progress_Reports_Script.py
│   ├── ADVANCE_PARENTING_Unexcused_Absences_Script.py
│   ├── ADVANCE_PARENTING_Victim_Letters_Script.py
│   ├── AEMP_Behavior_Contracts_Script.py
│   ├── AEMP_Completion_Documents_Script.py
│   ├── AEMP_Enrollment_Letters_Script.py
│   ├── AEMP_Entrance_Notifications_Script.py
│   ├── AEMP_Exit_Notices_Script.py
│   ├── AEMP_Progress_Reports_Script.py
│   ├── AEMP_Unexcused_Absences_Script.py
│   ├── AEMP_Victim_Letters_Script.py
│   ├── BIPP_Behavior_Contracts_Script.py
│   ├── BIPP_Completion_Documents_Script.py*
│   ├── BIPP_CUR_Progress_Reports_Script.py*
│   ├── BIPP_Enrollment_Letters_Script.py*
│   ├── BIPP_Entrance_Notifications_Script.py*
│   ├── BIPP_Exit_Notices_Script.py*
│   ├── BIPP_SOC_Progress_Reports_Script.py*
│   ├── BIPP_Unexcused_Absences_Script.py*
│   ├── BIPP_Victim_Letters_Script.py
│   ├── celery.out*
│   ├── check_absences.py*
│   ├── csv/
│   │   ├── bestoption/
│   │   │   └── report5_dump_20251008.csv
│   │   ├── ctc/
│   │   │   └── report5_dump_20250613.csv
│   │   ├── dwag/
│   │   │   └── report5_dump_20251016.csv
│   │   ├── ffltest/
│   │   │   └── report5_dump_20251016.csv
│   │   ├── safatherhood/
│   │   │   └── report5_dump_20251003.csv
│   │   ├── sandbox/
│   │   │   └── report5_dump_20250911.csv
│   │   └── transform/
│   │       └── report5_dump_20250507.csv
│   ├── DOEP_Behavior_Contracts_Script.py
│   ├── DOEP_Completion_Documents_Script.py
│   ├── DOEP_Enrollment_Letters_Script.py
│   ├── DOEP_Entrance_Notifications_Script.py
│   ├── DOEP_Exit_Notices_Script.py
│   ├── DOEP_Progress_Reports_Script.py
│   ├── DOEP_Unexcused_Absences_Script.py
│   ├── DOEP_Victim_Letters_Script.py
│   ├── dump.rdb*
│   ├── DWI_Behavior_Contracts_Script.py
│   ├── DWI_Completion_Documents_Script.py
│   ├── DWIE_Behavior_Contracts_Script.py
│   ├── DWIE_Completion_Documents_Script.py
│   ├── DWIE_Enrollment_Letters_Script.py
│   ├── DWIE_Entrance_Notifications_Script.py
│   ├── DWIE_Exit_Notices_Script.py
│   ├── DWI_Enrollment_Letters_Script.py
│   ├── DWI_Entrance_Notifications_Script.py
│   ├── DWIE_Progress_Reports_Script.py
│   ├── DWIE_Unexcused_Absences_Script.py
│   ├── DWIE_Victim_Letters_Script.py
│   ├── DWI_Exit_Notices_Script.py
│   ├── DWII_Behavior_Contracts_Script.py
│   ├── DWII_Completion_Documents_Script.py
│   ├── DWII_Enrollment_Letters_Script.py
│   ├── DWII_Entrance_Notifications_Script.py
│   ├── DWII_Exit_Notices_Script.py
│   ├── DWII_Progress_Reports_Script.py
│   ├── DWII_Unexcused_Absences_Script.py
│   ├── DWII_Victim_Letters_Script.py
│   ├── DWI_Progress_Reports_Script.py
│   ├── DWI_Unexcused_Absences_Script.py
│   ├── DWI_Victim_Letters_Script.py
│   ├── fetch_data.php*
│   ├── .github/
│   │   └── workflows/
│   │       └── create-diagram.yml*
│   ├── .gitignore*
│   ├── gunicorn_config.py*
│   ├── gunicorn.conf.py*
│   ├── gunicorn.out*
│   ├── .htaccess*
│   ├── inventory.txt
│   ├── IOP_Behavior_Contracts_Script.py
│   ├── IOP_Completion_Documents_Script.py
│   ├── IOP_Enrollment_Letters_Script.py
│   ├── IOP_Entrance_Notifications_Script.py
│   ├── IOP_Exit_Notices_Script.py
│   ├── IOP_Progress_Reports_Script.py
│   ├── IOP_Unexcused_Absences_Script.py
│   ├── IOP_Victim_Letters_Script.py
│   ├── LIFE_SKILLS_Behavior_Contracts_Script.py
│   ├── LIFE_SKILLS_Completion_Documents_Script.py
│   ├── LIFE_SKILLS_Enrollment_Letters_Script.py
│   ├── LIFE_SKILLS_Entrance_Notifications_Script.py
│   ├── LIFE_SKILLS_Exit_Notices_Script.py
│   ├── LIFE_SKILLS_Progress_Reports_Script.py
│   ├── LIFE_SKILLS_Unexcused_Absences_Script.py
│   ├── LIFE_SKILLS_Victim_Letters_Script.py
│   ├── logs/
│   │   ├── sedckeqKw
│   │   └── sedlWMJsy
│   ├── manage_services_denton.sh*
│   ├── manage_services.sh*
│   ├── MARIJUANA_EDU_Behavior_Contracts_Script.py
│   ├── MARIJUANA_EDU_Completion_Documents_Script.py
│   ├── MARIJUANA_EDU_Enrollment_Letters_Script.py
│   ├── MARIJUANA_EDU_Entrance_Notifications_Script.py
│   ├── MARIJUANA_EDU_Exit_Notices_Script.py
│   ├── MARIJUANA_EDU_Progress_Reports_Script.py
│   ├── MARIJUANA_EDU_Unexcused_Absences_Script.py
│   ├── MARIJUANA_EDU_Victim_Letters_Script.py
│   ├── MARIJUANA_INT_Behavior_Contracts_Script.py
│   ├── MARIJUANA_INT_Completion_Documents_Script.py
│   ├── MARIJUANA_INT_Enrollment_Letters_Script.py
│   ├── MARIJUANA_INT_Entrance_Notifications_Script.py
│   ├── MARIJUANA_INT_Exit_Notices_Script.py
│   ├── MARIJUANA_INT_Progress_Reports_Script.py
│   ├── MARIJUANA_INT_Unexcused_Absences_Script.py
│   ├── MARIJUANA_INT_Victim_Letters_Script.py
│   ├── MRT_Behavior_Contracts_Script.py
│   ├── MRT_Completion_Documents_Script.py
│   ├── MRT_Enrollment_Letters_Script.py*
│   ├── MRT_Entrance_Notifications_Script.py
│   ├── MRT_Exit_Notices_Script.py
│   ├── MRT_Progress_Reports_Script.py
│   ├── MRT_Unexcused_Absences_Script.py
│   ├── MRT_Victim_Letters_Script.py
│   ├── nohup.out*
│   ├── OBSTRUCTION_Behavior_Contracts_Script.py
│   ├── OBSTRUCTION_Completion_Documents_Script.py
│   ├── OBSTRUCTION_Enrollment_Letters_Script.py
│   ├── OBSTRUCTION_Entrance_Notifications_Script.py
│   ├── OBSTRUCTION_Exit_Notices_Script.py
│   ├── OBSTRUCTION_Progress_Reports_Script.py
│   ├── OBSTRUCTION_Unexcused_Absences_Script.py
│   ├── OBSTRUCTION_Victim_Letters_Script.py
│   ├── PARENTING_Behavior_Contracts_Script.py
│   ├── PARENTING_Completion_Documents_Script.py
│   ├── PARENTING_Enrollment_Letters_Script.py
│   ├── PARENTING_Entrance_Notifications_Script.py
│   ├── PARENTING_Exit_Notices_Script.py
│   ├── PARENTING_Progress_Reports_Script.py
│   ├── PARENTING_Unexcused_Absences_Script.py
│   ├── PARENTING_Victim_Letters_Script.py
│   ├── README.md*
│   ├── redis.conf*
│   ├── redis.out*
│   ├── Reporting_GUI.py*
│   ├── requirements.txt*
│   ├── SAE_Behavior_Contracts_Script.py
│   ├── SAE_Completion_Documents_Script.py
│   ├── SAE_Enrollment_Letters_Script.py
│   ├── SAE_Entrance_Notifications_Script.py
│   ├── SAE_Exit_Notices_Script.py
│   ├── SAE_Progress_Reports_Script.py
│   ├── SAE_Unexcused_Absences_Script.py
│   ├── SAE_Victim_Letters_Script.py
│   ├── scriptbackuppre/
│   │   ├── AC_Behavior_Contracts_Script.py
│   │   ├── AC_Completion_Documents_Script.py*
│   │   ├── AC_Enrollment_Letters_Script.py*
│   │   ├── AC_Entrance_Notifications_Script.py*
│   │   ├── AC_Exit_Notices_Script.py*
│   │   ├── AC_Progress_Reports_Script.py*
│   │   ├── AC_Unexcused_Absences_Script.py*
│   │   ├── AC_Victim_Letters_Script.py
│   │   ├── ADVANCE_PARENTING_Behavior_Contracts_Script.py
│   │   ├── ADVANCE_PARENTING_Completion_Documents_Script.py
│   │   ├── ADVANCE_PARENTING_Enrollment_Letters_Script.py
│   │   ├── ADVANCE_PARENTING_Entrance_Notifications_Script.py
│   │   ├── ADVANCE_PARENTING_Exit_Notices_Script.py
│   │   ├── ADVANCE_PARENTING_Progress_Reports_Script.py
│   │   ├── ADVANCE_PARENTING_Unexcused_Absences_Script.py
│   │   ├── ADVANCE_PARENTING_Victim_Letters_Script.py
│   │   ├── AEMP_Behavior_Contracts_Script.py
│   │   ├── AEMP_Completion_Documents_Script.py
│   │   ├── AEMP_Enrollment_Letters_Script.py
│   │   ├── AEMP_Entrance_Notifications_Script.py
│   │   ├── AEMP_Exit_Notices_Script.py
│   │   ├── AEMP_Progress_Reports_Script.py
│   │   ├── AEMP_Unexcused_Absences_Script.py
│   │   ├── AEMP_Victim_Letters_Script.py
│   │   ├── BIPP_Behavior_Contracts_Script.py
│   │   ├── BIPP_Completion_Documents_Script.py*
│   │   ├── BIPP_CUR_Progress_Reports_Script.py*
│   │   ├── BIPP_Enrollment_Letters_Script.py*
│   │   ├── BIPP_Entrance_Notifications_Script.py*
│   │   ├── BIPP_Exit_Notices_Script.py*
│   │   ├── BIPP_SOC_Progress_Reports_Script.py*
│   │   ├── BIPP_Unexcused_Absences_Script.py*
│   │   ├── BIPP_Victim_Letters_Script.py
│   │   ├── check_absences.py*
│   │   ├── DOEP_Behavior_Contracts_Script.py
│   │   ├── DOEP_Completion_Documents_Script.py
│   │   ├── DOEP_Enrollment_Letters_Script.py
│   │   ├── DOEP_Entrance_Notifications_Script.py
│   │   ├── DOEP_Exit_Notices_Script.py
│   │   ├── DOEP_Progress_Reports_Script.py
│   │   ├── DOEP_Unexcused_Absences_Script.py
│   │   ├── DOEP_Victim_Letters_Script.py
│   │   ├── DWI_Behavior_Contracts_Script.py
│   │   ├── DWI_Completion_Documents_Script.py
│   │   ├── DWIE_Behavior_Contracts_Script.py
│   │   ├── DWIE_Completion_Documents_Script.py
│   │   ├── DWIE_Enrollment_Letters_Script.py
│   │   ├── DWIE_Entrance_Notifications_Script.py
│   │   ├── DWIE_Exit_Notices_Script.py
│   │   ├── DWI_Enrollment_Letters_Script.py
│   │   ├── DWI_Entrance_Notifications_Script.py
│   │   ├── DWIE_Progress_Reports_Script.py
│   │   ├── DWIE_Unexcused_Absences_Script.py
│   │   ├── DWIE_Victim_Letters_Script.py
│   │   ├── DWI_Exit_Notices_Script.py
│   │   ├── DWII_Behavior_Contracts_Script.py
│   │   ├── DWII_Completion_Documents_Script.py
│   │   ├── DWII_Enrollment_Letters_Script.py
│   │   ├── DWII_Entrance_Notifications_Script.py
│   │   ├── DWII_Exit_Notices_Script.py
│   │   ├── DWII_Progress_Reports_Script.py
│   │   ├── DWII_Unexcused_Absences_Script.py
│   │   ├── DWII_Victim_Letters_Script.py
│   │   ├── DWI_Progress_Reports_Script.py
│   │   ├── DWI_Unexcused_Absences_Script.py
│   │   ├── DWI_Victim_Letters_Script.py
│   │   ├── IOP_Behavior_Contracts_Script.py
│   │   ├── IOP_Completion_Documents_Script.py
│   │   ├── IOP_Enrollment_Letters_Script.py
│   │   ├── IOP_Entrance_Notifications_Script.py
│   │   ├── IOP_Exit_Notices_Script.py
│   │   ├── IOP_Progress_Reports_Script.py
│   │   ├── IOP_Unexcused_Absences_Script.py
│   │   ├── IOP_Victim_Letters_Script.py
│   │   ├── LIFE_SKILLS_Behavior_Contracts_Script.py
│   │   ├── LIFE_SKILLS_Completion_Documents_Script.py
│   │   ├── LIFE_SKILLS_Enrollment_Letters_Script.py
│   │   ├── LIFE_SKILLS_Entrance_Notifications_Script.py
│   │   ├── LIFE_SKILLS_Exit_Notices_Script.py
│   │   ├── LIFE_SKILLS_Progress_Reports_Script.py
│   │   ├── LIFE_SKILLS_Unexcused_Absences_Script.py
│   │   ├── LIFE_SKILLS_Victim_Letters_Script.py
│   │   ├── manage_services_denton.sh*
│   │   ├── MARIJUANA_EDU_Behavior_Contracts_Script.py
│   │   ├── MARIJUANA_EDU_Completion_Documents_Script.py
│   │   ├── MARIJUANA_EDU_Enrollment_Letters_Script.py
│   │   ├── MARIJUANA_EDU_Entrance_Notifications_Script.py
│   │   ├── MARIJUANA_EDU_Exit_Notices_Script.py
│   │   ├── MARIJUANA_EDU_Progress_Reports_Script.py
│   │   ├── MARIJUANA_EDU_Unexcused_Absences_Script.py
│   │   ├── MARIJUANA_EDU_Victim_Letters_Script.py
│   │   ├── MARIJUANA_INT_Behavior_Contracts_Script.py
│   │   ├── MARIJUANA_INT_Completion_Documents_Script.py
│   │   ├── MARIJUANA_INT_Enrollment_Letters_Script.py
│   │   ├── MARIJUANA_INT_Entrance_Notifications_Script.py
│   │   ├── MARIJUANA_INT_Exit_Notices_Script.py
│   │   ├── MARIJUANA_INT_Progress_Reports_Script.py
│   │   ├── MARIJUANA_INT_Unexcused_Absences_Script.py
│   │   ├── MARIJUANA_INT_Victim_Letters_Script.py
│   │   ├── MRT_Behavior_Contracts_Script.py
│   │   ├── MRT_Completion_Documents_Script.py
│   │   ├── MRT_Enrollment_Letters_Script.py*
│   │   ├── MRT_Entrance_Notifications_Script.py
│   │   ├── MRT_Exit_Notices_Script.py
│   │   ├── MRT_Progress_Reports_Script.py
│   │   ├── MRT_Unexcused_Absences_Script.py
│   │   ├── MRT_Victim_Letters_Script.py
│   │   ├── OBSTRUCTION_Behavior_Contracts_Script.py
│   │   ├── OBSTRUCTION_Completion_Documents_Script.py
│   │   ├── OBSTRUCTION_Enrollment_Letters_Script.py
│   │   ├── OBSTRUCTION_Entrance_Notifications_Script.py
│   │   ├── OBSTRUCTION_Exit_Notices_Script.py
│   │   ├── OBSTRUCTION_Progress_Reports_Script.py
│   │   ├── OBSTRUCTION_Unexcused_Absences_Script.py
│   │   ├── OBSTRUCTION_Victim_Letters_Script.py
│   │   ├── PARENTING_Behavior_Contracts_Script.py
│   │   ├── PARENTING_Completion_Documents_Script.py
│   │   ├── PARENTING_Enrollment_Letters_Script.py
│   │   ├── PARENTING_Entrance_Notifications_Script.py
│   │   ├── PARENTING_Exit_Notices_Script.py
│   │   ├── PARENTING_Progress_Reports_Script.py
│   │   ├── PARENTING_Unexcused_Absences_Script.py
│   │   ├── PARENTING_Victim_Letters_Script.py
│   │   ├── SAE_Behavior_Contracts_Script.py
│   │   ├── SAE_Completion_Documents_Script.py
│   │   ├── SAE_Enrollment_Letters_Script.py
│   │   ├── SAE_Entrance_Notifications_Script.py
│   │   ├── SAE_Exit_Notices_Script.py
│   │   ├── SAE_Progress_Reports_Script.py
│   │   ├── SAE_Unexcused_Absences_Script.py
│   │   ├── SAE_Victim_Letters_Script.py
│   │   ├── SOP_Behavior_Contracts_Script.py
│   │   ├── SOP_Completion_Documents_Script.py
│   │   ├── SOP_Enrollment_Letters_Script.py
│   │   ├── SOP_Entrance_Notifications_Script.py
│   │   ├── SOP_Exit_Notices_Script.py
│   │   ├── SOP_Progress_Reports_Script.py
│   │   ├── SOP_Unexcused_Absences_Script.py
│   │   ├── SOP_Victim_Letters_Script.py
│   │   ├── T4C_Behavior_Contracts_Script.py
│   │   ├── T4C_Completion_Documents_Script.py*
│   │   ├── T4C_Enrollment_Letters_Script.py*
│   │   ├── T4C_Entrance_Notifications_Script.py*
│   │   ├── T4C_Exit_Notices_Script.py*
│   │   ├── T4C_Progress_Reports_Script.py*
│   │   ├── T4C_Unexcused_Absences_Script.py*
│   │   ├── T4C_Victim_Letters_Script.py
│   │   ├── TIPS_Completion_Documents_Script.py*
│   │   ├── TIPS_Entrance_Notifications_Script.py*
│   │   ├── TIPS_Exit_Notices_Script.py*
│   │   ├── TIPS_Progress_Reports_Script.py*
│   │   ├── TIPS_Unexcused_Absences_Script.py*
│   │   ├── WEST_Behavior_Contracts_Script.py
│   │   ├── WEST_Completion_Documents_Script.py
│   │   ├── WEST_Enrollment_Letters_Script.py
│   │   ├── WEST_Entrance_Notifications_Script.py
│   │   ├── WEST_Exit_Notices_Script.py
│   │   ├── WEST_Progress_Reports_Script.py
│   │   ├── WEST_Unexcused_Absences_Script.py
│   │   └── WEST_Victim_Letters_Script.py
│   ├── SOP_Behavior_Contracts_Script.py
│   ├── SOP_Completion_Documents_Script.py
│   ├── SOP_Enrollment_Letters_Script.py
│   ├── SOP_Entrance_Notifications_Script.py
│   ├── SOP_Exit_Notices_Script.py
│   ├── SOP_Progress_Reports_Script.py
│   ├── SOP_Unexcused_Absences_Script.py
│   ├── SOP_Victim_Letters_Script.py
│   ├── static/
│   │   ├── Background.png*
│   │   ├── Free_for_Life_Logo.png*
│   │   ├── GCheck.png*
│   │   ├── jquery-3.7.1.min.js*
│   │   ├── loading-spinner.gif*
│   │   ├── NoteProLogo2.png*
│   │   ├── NoteProLogoFinal.ico*
│   │   └── REx.png*
│   ├── stop_and_restart_nginx.sh*
│   ├── T4C_Behavior_Contracts_Script.py
│   ├── T4C_Completion_Documents_Script.py*
│   ├── T4C_Enrollment_Letters_Script.py*
│   ├── T4C_Entrance_Notifications_Script.py*
│   ├── T4C_Exit_Notices_Script.py*
│   ├── T4C_Progress_Reports_Script.py*
│   ├── T4C_Unexcused_Absences_Script.py*
│   ├── T4C_Victim_Letters_Script.py
│   ├── tasks/
│   │   └── status_task_6750cc934b0f24.41758572.txt*
│   ├── templates/
│   │   ├── bestoption/
│   │   │   ├── Blank Progress Notes.xlsx
│   │   │   ├── Template.BIPP Behavior Contract.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Behavior Contract.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Completion Certificate.docx
│   │   │   ├── Template.BIPP Completion Letter.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Completion Letter.docx
│   │   │   ├── Template.BIPP Completion Progress Report.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Completion Progress Report.docx
│   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Completion Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Entrance Notification.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Entrance Notification.docx
│   │   │   ├── Template.BIPP Exit Notice.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Exit Notice.docx
│   │   │   ├── Template.BIPP Exit Progress Report.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Exit Progress Report.docx
│   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Exit Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Progress Report.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Progress Report.docx
│   │   │   ├── Template.BIPP Progress Report Virtual.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Unexcused Absence.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Unexcused Absence.docx
│   │   │   ├── Template.BIPP Victim Completion.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Victim Completion.docx
│   │   │   ├── Template.BIPP Victim Entrance.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Victim Entrance.docx
│   │   │   └── Template.BIPP Victim Exit.docx -> /home/notesao/NotePro-Report-Generator/templates/default/BIPP/Template.BIPP Victim Exit.docx
│   │   ├── ctc/
│   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   ├── Template.AC Progress Report.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   ├── Template.BIPP Progress Report.docx
│   │   │   ├── Template.TIPS Completion Certificate.docx*
│   │   │   └── Template.TIPS Progress Report.docx
│   │   ├── default/
│   │   │   ├── AC/
│   │   │   │   ├── Template.AC Behavior Contract.docx
│   │   │   │   ├── Template.AC Completion Certificate.docx
│   │   │   │   ├── Template.AC Completion Letter.docx
│   │   │   │   ├── Template.AC Completion Progress Report.docx
│   │   │   │   ├── Template.AC Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.AC Entrance Notification.docx
│   │   │   │   ├── Template.AC Exit Notice.docx
│   │   │   │   ├── Template.AC Exit Progress Report.docx
│   │   │   │   ├── Template.AC Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.AC Progress Report.docx
│   │   │   │   ├── Template.AC Progress Report Virtual.docx
│   │   │   │   ├── Template.AC Unexcused Absence.docx
│   │   │   │   ├── Template.AC Victim Completion.docx
│   │   │   │   ├── Template.AC Victim Entrance.docx
│   │   │   │   └── Template.AC Victim Exit.docx
│   │   │   ├── ADVANCE PARENTING/
│   │   │   │   ├── Template.ADVANCE PARENTING Behavior Contract.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Completion Certificate.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Completion Letter.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Completion Progress Report.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Entrance Notification.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Exit Notice.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Exit Progress Report.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Progress Report.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Progress Report Virtual.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Unexcused Absence.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Victim Completion.docx
│   │   │   │   ├── Template.ADVANCE PARENTING Victim Entrance.docx
│   │   │   │   └── Template.ADVANCE PARENTING Victim Exit.docx
│   │   │   ├── AEMP/
│   │   │   │   ├── Template.AEMP Behavior Contract.docx
│   │   │   │   ├── Template.AEMP Completion Certificate.docx
│   │   │   │   ├── Template.AEMP Completion Letter.docx
│   │   │   │   ├── Template.AEMP Completion Progress Report.docx
│   │   │   │   ├── Template.AEMP Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.AEMP Entrance Notification.docx
│   │   │   │   ├── Template.AEMP Exit Notice.docx
│   │   │   │   ├── Template.AEMP Exit Progress Report.docx
│   │   │   │   ├── Template.AEMP Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.AEMP Progress Report.docx
│   │   │   │   ├── Template.AEMP Progress Report Virtual.docx
│   │   │   │   ├── Template.AEMP Unexcused Absence.docx
│   │   │   │   ├── Template.AEMP Victim Completion.docx
│   │   │   │   ├── Template.AEMP Victim Entrance.docx
│   │   │   │   └── Template.AEMP Victim Exit.docx
│   │   │   ├── BIPP/
│   │   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   │   ├── Template.BIPP Completion Certificate.docx
│   │   │   │   ├── Template.BIPP Completion Letter.docx
│   │   │   │   ├── Template.BIPP Completion Progress Report.docx
│   │   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.BIPP Entrance Notification.docx
│   │   │   │   ├── Template.BIPP Exit Notice.docx
│   │   │   │   ├── Template.BIPP Exit Progress Report.docx
│   │   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.BIPP Progress Report.docx
│   │   │   │   ├── Template.BIPP Progress Report Virtual.docx
│   │   │   │   ├── Template.BIPP Unexcused Absence.docx
│   │   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   │   └── Template.BIPP Victim Exit.docx
│   │   │   ├── DOEP/
│   │   │   │   ├── Template.DOEP Behavior Contract.docx
│   │   │   │   ├── Template.DOEP Completion Certificate.docx
│   │   │   │   ├── Template.DOEP Completion Letter.docx
│   │   │   │   ├── Template.DOEP Completion Progress Report.docx
│   │   │   │   ├── Template.DOEP Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.DOEP Entrance Notification.docx
│   │   │   │   ├── Template.DOEP Exit Notice.docx
│   │   │   │   ├── Template.DOEP Exit Progress Report.docx
│   │   │   │   ├── Template.DOEP Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.DOEP Progress Report.docx
│   │   │   │   ├── Template.DOEP Progress Report Virtual.docx
│   │   │   │   ├── Template.DOEP Unexcused Absence.docx
│   │   │   │   ├── Template.DOEP Victim Completion.docx
│   │   │   │   ├── Template.DOEP Victim Entrance.docx
│   │   │   │   └── Template.DOEP Victim Exit.docx
│   │   │   ├── DWI/
│   │   │   │   ├── Template.DWI Behavior Contract.docx
│   │   │   │   ├── Template.DWI Completion Certificate.docx
│   │   │   │   ├── Template.DWI Completion Letter.docx
│   │   │   │   ├── Template.DWI Completion Progress Report.docx
│   │   │   │   ├── Template.DWI Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.DWI Entrance Notification.docx
│   │   │   │   ├── Template.DWI Exit Notice.docx
│   │   │   │   ├── Template.DWI Exit Progress Report.docx
│   │   │   │   ├── Template.DWI Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.DWI Progress Report.docx
│   │   │   │   ├── Template.DWI Progress Report Virtual.docx
│   │   │   │   ├── Template.DWI Unexcused Absence.docx
│   │   │   │   ├── Template.DWI Victim Completion.docx
│   │   │   │   ├── Template.DWI Victim Entrance.docx
│   │   │   │   └── Template.DWI Victim Exit.docx
│   │   │   ├── DWIE/
│   │   │   │   ├── Template.DWIE Behavior Contract.docx
│   │   │   │   ├── Template.DWIE Completion Certificate.docx
│   │   │   │   ├── Template.DWIE Completion Letter.docx
│   │   │   │   ├── Template.DWIE Completion Progress Report.docx
│   │   │   │   ├── Template.DWIE Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.DWIE Entrance Notification.docx
│   │   │   │   ├── Template.DWIE Exit Notice.docx
│   │   │   │   ├── Template.DWIE Exit Progress Report.docx
│   │   │   │   ├── Template.DWIE Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.DWIE Progress Report.docx
│   │   │   │   ├── Template.DWIE Progress Report Virtual.docx
│   │   │   │   ├── Template.DWIE Unexcused Absence.docx
│   │   │   │   ├── Template.DWIE Victim Completion.docx
│   │   │   │   ├── Template.DWIE Victim Entrance.docx
│   │   │   │   └── Template.DWIE Victim Exit.docx
│   │   │   ├── DWII/
│   │   │   │   ├── Template.DWII Behavior Contract.docx
│   │   │   │   ├── Template.DWII Completion Certificate.docx
│   │   │   │   ├── Template.DWII Completion Letter.docx
│   │   │   │   ├── Template.DWII Completion Progress Report.docx
│   │   │   │   ├── Template.DWII Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.DWII Entrance Notification.docx
│   │   │   │   ├── Template.DWII Exit Notice.docx
│   │   │   │   ├── Template.DWII Exit Progress Report.docx
│   │   │   │   ├── Template.DWII Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.DWII Progress Report.docx
│   │   │   │   ├── Template.DWII Progress Report Virtual.docx
│   │   │   │   ├── Template.DWII Unexcused Absence.docx
│   │   │   │   ├── Template.DWII Victim Completion.docx
│   │   │   │   ├── Template.DWII Victim Entrance.docx
│   │   │   │   └── Template.DWII Victim Exit.docx
│   │   │   ├── IOP/
│   │   │   │   ├── Template.IOP Behavior Contract.docx
│   │   │   │   ├── Template.IOP Completion Certificate.docx
│   │   │   │   ├── Template.IOP Completion Letter.docx
│   │   │   │   ├── Template.IOP Completion Progress Report.docx
│   │   │   │   ├── Template.IOP Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.IOP Entrance Notification.docx
│   │   │   │   ├── Template.IOP Exit Notice.docx
│   │   │   │   ├── Template.IOP Exit Progress Report.docx
│   │   │   │   ├── Template.IOP Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.IOP Progress Report.docx
│   │   │   │   ├── Template.IOP Progress Report Virtual.docx
│   │   │   │   ├── Template.IOP Unexcused Absence.docx
│   │   │   │   ├── Template.IOP Victim Completion.docx
│   │   │   │   ├── Template.IOP Victim Entrance.docx
│   │   │   │   └── Template.IOP Victim Exit.docx
│   │   │   ├── LIFE SKILLS/
│   │   │   │   ├── Template.LIFE SKILLS Behavior Contract.docx
│   │   │   │   ├── Template.LIFE SKILLS Completion Certificate.docx
│   │   │   │   ├── Template.LIFE SKILLS Completion Letter.docx
│   │   │   │   ├── Template.LIFE SKILLS Completion Progress Report.docx
│   │   │   │   ├── Template.LIFE SKILLS Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.LIFE SKILLS Entrance Notification.docx
│   │   │   │   ├── Template.LIFE SKILLS Exit Notice.docx
│   │   │   │   ├── Template.LIFE SKILLS Exit Progress Report.docx
│   │   │   │   ├── Template.LIFE SKILLS Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.LIFE SKILLS Progress Report.docx
│   │   │   │   ├── Template.LIFE SKILLS Progress Report Virtual.docx
│   │   │   │   ├── Template.LIFE SKILLS Unexcused Absence.docx
│   │   │   │   ├── Template.LIFE SKILLS Victim Completion.docx
│   │   │   │   ├── Template.LIFE SKILLS Victim Entrance.docx
│   │   │   │   └── Template.LIFE SKILLS Victim Exit.docx
│   │   │   ├── MARIJUANA EDU/
│   │   │   │   ├── Template.MARIAUNA EDU Victim Completion.docx
│   │   │   │   ├── Template.MARIJUANA EDU Behavior Contract.docx
│   │   │   │   ├── Template.MARIJUANA EDU Completion Certificate.docx
│   │   │   │   ├── Template.MARIJUANA EDU Completion Letter.docx
│   │   │   │   ├── Template.MARIJUANA EDU Completion Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA EDU Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA EDU Entrance Notification.docx
│   │   │   │   ├── Template.MARIJUANA EDU Exit Notice.docx
│   │   │   │   ├── Template.MARIJUANA EDU Exit Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA EDU Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA EDU Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA EDU Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA EDU Unexcused Absence.docx
│   │   │   │   ├── Template.MARIJUANA EDU Victim Entrance.docx
│   │   │   │   └── Template.MARIJUANA EDU Victim Exit.docx
│   │   │   ├── MARIJUANA INT/
│   │   │   │   ├── Template.MARIJUANA INT Behavior Contract.docx
│   │   │   │   ├── Template.MARIJUANA INT Completion Certificate.docx
│   │   │   │   ├── Template.MARIJUANA INT Completion Letter.docx
│   │   │   │   ├── Template.MARIJUANA INT Completion Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA INT Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA INT Entrance Notification.docx
│   │   │   │   ├── Template.MARIJUANA INT Exit Notice.docx
│   │   │   │   ├── Template.MARIJUANA INT Exit Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA INT Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA INT Progress Report.docx
│   │   │   │   ├── Template.MARIJUANA INT Progress Report Virtual.docx
│   │   │   │   ├── Template.MARIJUANA INT Unexcused Absence.docx
│   │   │   │   ├── Template.MARIJUANA INT Victim Completion.docx
│   │   │   │   ├── Template.MARIJUANA INT Victim Entrance.docx
│   │   │   │   └── Template.MARIJUANA INT Victim Exit.docx
│   │   │   ├── MRT/
│   │   │   │   ├── Template.MRT Behavior Contract.docx
│   │   │   │   ├── Template.MRT Completion Certificate.docx
│   │   │   │   ├── Template.MRT Completion Letter.docx
│   │   │   │   ├── Template.MRT Completion Progress Report.docx
│   │   │   │   ├── Template.MRT Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.MRT Entrance Notification.docx
│   │   │   │   ├── Template.MRT Exit Notice.docx
│   │   │   │   ├── Template.MRT Exit Progress Report.docx
│   │   │   │   ├── Template.MRT Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.MRT Progress Report.docx
│   │   │   │   ├── Template.MRT Progress Report Virtual.docx
│   │   │   │   ├── Template.MRT Unexcused Absence.docx
│   │   │   │   ├── Template.MRT Victim Completion.docx
│   │   │   │   ├── Template.MRT Victim Entrance.docx
│   │   │   │   └── Template.MRT Victim Exit.docx
│   │   │   ├── OBSTRUCTION/
│   │   │   │   ├── Template.OBSTRUCTION Behavior Contract.docx
│   │   │   │   ├── Template.OBSTRUCTION Completion Certificate.docx
│   │   │   │   ├── Template.OBSTRUCTION Completion Letter.docx
│   │   │   │   ├── Template.OBSTRUCTION Completion Progress Report.docx
│   │   │   │   ├── Template.OBSTRUCTION Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.OBSTRUCTION Entrance Notification.docx
│   │   │   │   ├── Template.OBSTRUCTION Exit Notice.docx
│   │   │   │   ├── Template.OBSTRUCTION Exit Progress Report.docx
│   │   │   │   ├── Template.OBSTRUCTION Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.OBSTRUCTION Progress Report.docx
│   │   │   │   ├── Template.OBSTRUCTION Progress Report Virtual.docx
│   │   │   │   ├── Template.OBSTRUCTION Unexcused Absence.docx
│   │   │   │   ├── Template.OBSTRUCTION Victim Completion.docx
│   │   │   │   ├── Template.OBSTRUCTION Victim Entrance.docx
│   │   │   │   └── Template.OBSTRUCTION Victim Exit.docx
│   │   │   ├── PARENTING/
│   │   │   │   ├── Template.PARENTING Behavior Contract.docx
│   │   │   │   ├── Template.PARENTING Completion Certificate.docx
│   │   │   │   ├── Template.PARENTING Completion Letter.docx
│   │   │   │   ├── Template.PARENTING Completion Progress Report.docx
│   │   │   │   ├── Template.PARENTING Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.PARENTING Entrance Notification.docx
│   │   │   │   ├── Template.PARENTING Exit Notice.docx
│   │   │   │   ├── Template.PARENTING Exit Progress Report.docx
│   │   │   │   ├── Template.PARENTING Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.PARENTING Progress Report.docx
│   │   │   │   ├── Template.PARENTING Progress Report Virtual.docx
│   │   │   │   ├── Template.PARENTING Unexcused Absence.docx
│   │   │   │   ├── Template.PARENTING Victim Completion.docx
│   │   │   │   ├── Template.PARENTING Victim Entrance.docx
│   │   │   │   └── Template.PARENTING Victim Exit.docx
│   │   │   ├── SAE/
│   │   │   │   ├── Template.SAE Behavior Contract.docx
│   │   │   │   ├── Template.SAE Completion Certificate.docx
│   │   │   │   ├── Template.SAE Completion Letter.docx
│   │   │   │   ├── Template.SAE Completion Progress Report.docx
│   │   │   │   ├── Template.SAE Completion Progress Report Virtual.docx
│   │   │   │   ├── Template.SAE Entrance Notification.docx
│   │   │   │   ├── Template.SAE Exit Notice.docx
│   │   │   │   ├── Template.SAE Exit Progress Report.docx
│   │   │   │   ├── Template.SAE Exit Progress Report Virtual.docx
│   │   │   │   ├── Template.SAE Progress Report.docx
│   │   │   │   ├── Template.SAE Progress Report Virtual.docx
│   │   │   │   ├── Template.SAE Unexcused Absence.docx
│   │   │   │   ├── Template.SAE Victim Completion.docx
│   │   │   │   ├── Template.SAE Victim Entrance.docx
│   │   │   │   └── Template.SAE Victim Exit.docx
│   │   │   └── SOP/
│   │   │       ├── Template.SOP Behavior Contract.docx
│   │   │       ├── Template.SOP Completion Certificate.docx
│   │   │       ├── Template.SOP Completion Letter.docx
│   │   │       ├── Template.SOP Completion Progress Report.docx
│   │   │       ├── Template.SOP Completion Progress Report Virtual.docx
│   │   │       ├── Template.SOP Entrance Notification.docx
│   │   │       ├── Template.SOP Exit Notice.docx
│   │   │       ├── Template.SOP Exit Progress Report.docx
│   │   │       ├── Template.SOP Exit Progress Report Virtual.docx
│   │   │       ├── Template.SOP Progress Report.docx
│   │   │       ├── Template.SOP Progress Report Virtual.docx
│   │   │       ├── Template.SOP Unexcused Absence.docx
│   │   │       ├── Template.SOP Victim Completion.docx
│   │   │       ├── Template.SOP Victim Entrance.docx
│   │   │       └── Template.SOP Victim Exit.docx
│   │   ├── dwag/
│   │   │   ├── BIPP Curriculum Blank Notes.xlsx
│   │   │   ├── Blank Progress Notes.xlsx
│   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx
│   │   │   ├── Template.BIPP Completion Letter.docx
│   │   │   ├── Template.BIPP Completion Progress Report.docx
│   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx
│   │   │   ├── Template.BIPP CUR Progress Report.docx
│   │   │   ├── Template.BIPP CUR Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Entrance Notification.docx
│   │   │   ├── Template.BIPP Exit Notice.docx
│   │   │   ├── Template.BIPP Exit Progress Report.docx
│   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx
│   │   │   ├── Template.BIPP SOC Progress Report.docx
│   │   │   ├── Template.BIPP SOC Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Unexcused Absence.docx
│   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   ├── Template.BIPP Victim Exit.docx
│   │   │   ├── Template.MRT Completion Certificate.docx
│   │   │   ├── Template.MRT Completion Letter.docx
│   │   │   ├── Template.MRT Completion Progress Report.docx
│   │   │   ├── Template.MRT Completion Progress Report Virtual.docx
│   │   │   ├── Template.MRT Entrance Notification.docx
│   │   │   ├── Template.MRT Exit Notice.docx
│   │   │   ├── Template.MRT Exit Progress Report.docx
│   │   │   ├── Template.MRT Exit Progress Report Virtual.docx
│   │   │   ├── Template.MRT Progress Report.docx
│   │   │   ├── Template.MRT Progress Report Virtual.docx
│   │   │   └── Template.MRT Unexcused Absence.docx
│   │   ├── ffltest/
│   │   │   ├── BIPP Curriculum Blank Notes.xlsx*
│   │   │   ├── BIPP.Template Victim Mailing Labels.docx
│   │   │   ├── Blank Progress Notes.xlsx*
│   │   │   ├── greencheck.png*
│   │   │   ├── greencheck.svg*
│   │   │   ├── index.html*
│   │   │   ├── purplecross.png*
│   │   │   ├── purplecross.svg*
│   │   │   ├── redcross.png*
│   │   │   ├── redcross.svg*
│   │   │   ├── Template.AC Completion Certificate.docx*
│   │   │   ├── Template.AC Completion Letter.docx*
│   │   │   ├── Template.AC Completion Progress Report.docx*
│   │   │   ├── Template.AC Entrance Notification.docx*
│   │   │   ├── Template.AC Exit Notice.docx*
│   │   │   ├── Template.AC Exit Progress Report.docx*
│   │   │   ├── Template.AC Progress Report.docx*
│   │   │   ├── Template.AC Unexcused Absence.docx*
│   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx*
│   │   │   ├── Template.BIPP Completion Letter.docx*
│   │   │   ├── Template.BIPP Completion Progress Report.docx*
│   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP CUR Progress Report.docx
│   │   │   ├── Template.BIPP CUR Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Entrance Notification.docx*
│   │   │   ├── Template.BIPP Exit Notice.docx*
│   │   │   ├── Template.BIPP Exit Progress Report.docx*
│   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP Progress Report.docx*
│   │   │   ├── Template.BIPP Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP SOC Progress Report.docx*
│   │   │   ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │   │   ├── Template.BIPP Unexcused Absence.docx*
│   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   ├── Template.BIPP Victim Exit.docx
│   │   │   ├── Template.T4C Completion Certificate.docx*
│   │   │   ├── Template.T4C Completion Letter.docx*
│   │   │   ├── Template.T4C Completion Progress Report.docx*
│   │   │   ├── Template.T4C Entrance Notification.docx*
│   │   │   ├── Template.T4C Exit Notice.docx*
│   │   │   ├── Template.T4C Exit Progress Report.docx*
│   │   │   ├── Template.T4C Progress Report.docx
│   │   │   └── Template.T4C Unexcused Absence.docx*
│   │   ├── .htaccess*
│   │   ├── safatherhood/
│   │   │   ├── Blank Progress Notes.xlsx
│   │   │   ├── Template.BIPP Behavior Contract.docx
│   │   │   ├── Template.BIPP Completion Certificate.docx
│   │   │   ├── Template.BIPP Completion Letter.docx
│   │   │   ├── Template.BIPP Completion Progress Report Virtual.docx
│   │   │   ├── Template.BIPP CUR Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Entrance Notification.docx
│   │   │   ├── Template.BIPP Exit Notice.docx
│   │   │   ├── Template.BIPP Exit Progress Report Virtual.docx
│   │   │   ├── Template.BIPP SOC Progress Report Virtual.docx
│   │   │   ├── Template.BIPP Unexcused Absence.docx
│   │   │   ├── Template.BIPP Victim Completion.docx
│   │   │   ├── Template.BIPP Victim Entrance.docx
│   │   │   └── Template.BIPP Victim Exit.docx
│   │   └── sandbox/
│   │       ├── BIPP Curriculum Blank Notes copy.xlsx*
│   │       ├── BIPP Curriculum Blank Notes.xlsx*
│   │       ├── BIPP.Template Victim Mailing Labels.docx
│   │       ├── Blank Progress Notes copy.xlsx*
│   │       ├── Blank Progress Notes.xlsx*
│   │       ├── greencheck copy.png*
│   │       ├── greencheck copy.svg*
│   │       ├── greencheck.png*
│   │       ├── greencheck.svg*
│   │       ├── index copy.html*
│   │       ├── index.html*
│   │       ├── purplecross copy.png*
│   │       ├── purplecross copy.svg*
│   │       ├── purplecross.png*
│   │       ├── purplecross.svg*
│   │       ├── redcross copy.png*
│   │       ├── redcross copy.svg*
│   │       ├── redcross.png*
│   │       ├── redcross.svg*
│   │       ├── Template.AC Completion Certificate copy.docx*
│   │       ├── Template.AC Completion Certificate.docx*
│   │       ├── Template.AC Completion Letter copy.docx*
│   │       ├── Template.AC Completion Letter.docx*
│   │       ├── Template.AC Completion Progress Report copy.docx*
│   │       ├── Template.AC Completion Progress Report.docx*
│   │       ├── Template.AC Entrance Notification copy.docx*
│   │       ├── Template.AC Entrance Notification.docx*
│   │       ├── Template.AC Exit Notice copy.docx*
│   │       ├── Template.AC Exit Notice.docx*
│   │       ├── Template.AC Exit Progress Report copy.docx*
│   │       ├── Template.AC Exit Progress Report.docx*
│   │       ├── Template.AC Progress Report copy.docx*
│   │       ├── Template.AC Progress Report.docx*
│   │       ├── Template.AC Unexcused Absence copy.docx*
│   │       ├── Template.AC Unexcused Absence.docx*
│   │       ├── Template.BIPP Behavior Contract.docx
│   │       ├── Template.BIPP Completion Certificate copy.docx*
│   │       ├── Template.BIPP Completion Certificate.docx*
│   │       ├── Template.BIPP Completion Letter copy.docx*
│   │       ├── Template.BIPP Completion Letter.docx*
│   │       ├── Template.BIPP Completion Progress Report copy.docx*
│   │       ├── Template.BIPP Completion Progress Report.docx*
│   │       ├── Template.BIPP Completion Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Completion Progress Report Virtual.docx*
│   │       ├── Template.BIPP CUR Progress Report copy.docx
│   │       ├── Template.BIPP CUR Progress Report.docx*
│   │       ├── Template.BIPP CUR Progress Report Virtual copy.docx
│   │       ├── Template.BIPP CUR Progress Report Virtual.docx*
│   │       ├── Template.BIPP Entrance Notification copy.docx*
│   │       ├── Template.BIPP Entrance Notification.docx*
│   │       ├── Template.BIPP Exit Notice copy.docx*
│   │       ├── Template.BIPP Exit Notice.docx*
│   │       ├── Template.BIPP Exit Progress Report copy.docx*
│   │       ├── Template.BIPP Exit Progress Report.docx*
│   │       ├── Template.BIPP Exit Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Exit Progress Report Virtual.docx*
│   │       ├── Template.BIPP Progress Report copy.docx*
│   │       ├── Template.BIPP Progress Report.docx*
│   │       ├── Template.BIPP Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP Progress Report Virtual.docx*
│   │       ├── Template.BIPP SOC Progress Report copy.docx*
│   │       ├── Template.BIPP SOC Progress Report.docx*
│   │       ├── Template.BIPP SOC Progress Report Virtual copy.docx*
│   │       ├── Template.BIPP SOC Progress Report Virtual.docx*
│   │       ├── Template.BIPP Unexcused Absence copy.docx*
│   │       ├── Template.BIPP Unexcused Absence.docx*
│   │       ├── Template.BIPP Victim Completion.docx
│   │       ├── Template.BIPP Victim Entrance.docx
│   │       ├── Template.BIPP Victim Exit.docx
│   │       ├── Template.T4C Completion Certificate copy.docx*
│   │       ├── Template.T4C Completion Certificate.docx*
│   │       ├── Template.T4C Completion Letter copy.docx*
│   │       ├── Template.T4C Completion Letter.docx*
│   │       ├── Template.T4C Completion Progress Report copy.docx*
│   │       ├── Template.T4C Completion Progress Report.docx*
│   │       ├── Template.T4C Entrance Notification copy.docx*
│   │       ├── Template.T4C Entrance Notification.docx*
│   │       ├── Template.T4C Exit Notice copy.docx*
│   │       ├── Template.T4C Exit Notice.docx*
│   │       ├── Template.T4C Exit Progress Report copy.docx*
│   │       ├── Template.T4C Exit Progress Report.docx*
│   │       ├── Template.T4C Progress Report copy.docx
│   │       ├── Template.T4C Progress Report.docx*
│   │       ├── Template.T4C Unexcused Absence copy.docx*
│   │       └── Template.T4C Unexcused Absence.docx*
│   ├── TIPS_Completion_Documents_Script.py*
│   ├── TIPS_Entrance_Notifications_Script.py*
│   ├── TIPS_Exit_Notices_Script.py*
│   ├── TIPS_Progress_Reports_Script.py*
│   ├── TIPS_Unexcused_Absences_Script.py*
│   ├── Update_Clients.py*
│   ├── WEST_Behavior_Contracts_Script.py
│   ├── WEST_Completion_Documents_Script.py
│   ├── WEST_Enrollment_Letters_Script.py
│   ├── WEST_Entrance_Notifications_Script.py
│   ├── WEST_Exit_Notices_Script.py
│   ├── WEST_Progress_Reports_Script.py
│   ├── WEST_Unexcused_Absences_Script.py
│   └── WEST_Victim_Letters_Script.py
├── notesao_structure_clean.txt
├── notesao_tree_20251016.txt
├── patch_work/
│   ├── core_deltas.patch
│   └── full_sandbox_vs_ffltest.patch
├── php_admin_value\[?disable_functions\]? = .*exec|shell_exec
├── public_html/
│   ├── activate.php
│   ├── apple-touch-icon.png
│   ├── assets/
│   │   ├── css/
│   │   │   └── main.css*
│   │   ├── images/
│   │   │   ├── favicon.ico*
│   │   │   ├── hero-illustration.jpg*
│   │   │   ├── hero-illustration.png*
│   │   │   ├── logo-placeholder1.png*
│   │   │   ├── logo-placeholder2.jpg*
│   │   │   ├── logo-placeholder2.png*
│   │   │   ├── logo-placeholder3.png*
│   │   │   ├── logo-placeholder4.png*
│   │   │   ├── logo.png*
│   │   │   ├── NotesAO Logo.png*
│   │   │   ├── testimonial-author1.png*
│   │   │   ├── testimonial-author2.png*
│   │   │   └── testimonial-author3.png*
│   │   └── js/
│   │       ├── main.js*
│   │       └── nav.js
│   ├── auth.php*
│   ├── clientinfo.php*
│   ├── config.php*
│   ├── favicon-16x16.png
│   ├── favicon-192x192.png
│   ├── favicon-32x32.png
│   ├── favicon-512x512.png
│   ├── favicon-96x96.png
│   ├── favicons/
│   │   ├── apple-touch-icon.png
│   │   ├── favicon-96x96.png
│   │   ├── favicon.ico
│   │   ├── favicon.svg
│   │   ├── site.webmanifest
│   │   ├── web-app-manifest-192x192.png
│   │   └── web-app-manifest-512x512.png
│   ├── favicon.svg
│   ├── forgot_password.php
│   ├── .htaccess*
│   ├── img/
│   │   └── email/
│   │       └── logo-notesao.png
│   ├── index.html*
│   ├── legal/
│   │   ├── accessibility.html
│   │   ├── privacy.html
│   │   ├── security.html
│   │   └── terms.html
│   ├── login.php
│   ├── logo.png*
│   ├── NAOflyer.png
│   ├── NotesAO.ico*
│   ├── NotesAO Logo.png*
│   ├── originalfavicon.ico
│   ├── partials/
│   │   └── header.php
│   ├── phpinfo.php
│   ├── reset_password.php
│   ├── signup.php
│   ├── site.webmanifest
│   ├── web-app-manifest-192x192.png
│   └── web-app-manifest-512x512.png
├── safatherhood/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create-import.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal_lib.php
│   │   ├── clientportal_links_admin.php
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── consent_texts.php
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── document-templates.php
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── payment-link-admin.php
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── safatherhoodlogo.png*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── saferpath/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-copy.php
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── saferpathlogo.png
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── sage/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── db/
│   │   │   ├── find_users_attends_days.sql*
│   │   │   ├── handy.sql*
│   │   │   ├── set_attends.sql*
│   │   │   ├── table_structure.docx*
│   │   │   ├── therapy_track.sql*
│   │   │   └── unexcused_absence_count.sql*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-copy.php
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── sandbox/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── ffllogo.png*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── group_stub.php
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── intake-index.php
│   │   ├── intake.php
│   │   ├── intake-review.php
│   │   ├── intake-update.php
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── notesao.png
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── sandbox_reset.php
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── sandbox_update.sh*
│       └── update_clients.sh*
├── search_audit.txt
├── secure/
│   └── notesao_secrets.php
├── .spamassassinboxenable
├── .spamassassinenable
├── ssl/
│   ├── certs/
│   │   ├── autodiscover_notesao_com_d056d_1fd65_1750254530_8a0ad8398775474a0e08a16ce14b2810.crt
│   │   ├── autodiscover_notesao_com_d056d_1fd65_1750254530_8a0ad8398775474a0e08a16ce14b2810.crt.cache
│   │   ├── ctc_notesao_com_c8c2a_013ab_1773948969_6dbc3cce9be1e8a4a2d3918ac85ff58c.crt
│   │   ├── ctc_notesao_com_c8c2a_013ab_1773948969_6dbc3cce9be1e8a4a2d3918ac85ff58c.crt.cache
│   │   ├── dwag_notesao_com_c1cc0_1eba7_1773949003_6d28ca4ec5c13d05c92c0e9ed2a5b521.crt
│   │   ├── dwag_notesao_com_c1cc0_1eba7_1773949003_6d28ca4ec5c13d05c92c0e9ed2a5b521.crt.cache
│   │   ├── ffltest_notesao_com_c2d36_68b19_1773949026_f5241938cf0874bafc1b458dc78d3a35.crt
│   │   ├── ffltest_notesao_com_c2d36_68b19_1773949026_f5241938cf0874bafc1b458dc78d3a35.crt.cache
│   │   ├── notesao_com_9be00_b2df9_1773934948_30d9d945d2bf3759df38de28f13c94dd.crt
│   │   ├── notesao_com_9be00_b2df9_1773934948_30d9d945d2bf3759df38de28f13c94dd.crt.cache
│   │   ├── safathernood_notesao_com_b8fe5_1e6cb_1773949050_eb9dc2cb497670f5ae829eb4f750f31a.crt
│   │   ├── safathernood_notesao_com_b8fe5_1e6cb_1773949050_eb9dc2cb497670f5ae829eb4f750f31a.crt.cache
│   │   ├── sandbox_notesao_com_e67df_805eb_1773949070_21fd788858e6af9a6cb1620e68ff6ced.crt
│   │   ├── sandbox_notesao_com_e67df_805eb_1773949070_21fd788858e6af9a6cb1620e68ff6ced.crt.cache
│   │   ├── transform_notesao_com_d2560_22373_1773949087_9607f05fd92738937059c226ddddb5a9.crt
│   │   ├── transform_notesao_com_d2560_22373_1773949087_9607f05fd92738937059c226ddddb5a9.crt.cache
│   │   ├── _wildcard__notesao_com_da653_4bbf9_1773951799_f7bcdd00e475f7301645ace194738c17.crt
│   │   ├── _wildcard__notesao_com_da653_4bbf9_1773951799_f7bcdd00e475f7301645ace194738c17.crt.cache
│   │   ├── _wildcard__notesao_com_da653_4bbf9_1776716832_dcdceabcb013676214b89a9fda4837b5.crt
│   │   └── _wildcard__notesao_com_da653_4bbf9_1776716832_dcdceabcb013676214b89a9fda4837b5.crt.cache
│   ├── keys/
│   │   ├── 9be00_b2df9_53bfb0ffb37f6b1bedf1fa570c8def2e.key
│   │   ├── b8fe5_1e6cb_d59e743808fff33a3052f136df62a534.key
│   │   ├── c1cc0_1eba7_55e9133aa960ec767acf7f7b0f45c086.key
│   │   ├── c2d36_68b19_de2484c8614aeb6a2bfe77b414fd129e.key
│   │   ├── c8c2a_013ab_6c5432ed0b60c23bd8bd98c647bde612.key
│   │   ├── d056d_1fd65_0cd0100cdc84f51c7b624364b2330d35.key
│   │   ├── d2560_22373_6793ad68e79405e2601338f01143e191.key
│   │   ├── da653_4bbf9_479e5770890432ca6bc7133be156ddc3.key
│   │   └── e67df_805eb_ec3280ff371512fafadc5aa7dafe40f0.key
│   ├── ssl.db
│   └── ssl.db.cache
├── .subaccounts/
│   └── storage.sqlite
├── transform/
│   ├── config/
│   │   ├── config.php*
│   │   ├── config_sample.php*
│   │   ├── .gitignore*
│   │   └── users.txt*
│   ├── db/
│   │   ├── find_users_attends_days.sql*
│   │   ├── handy.sql*
│   │   ├── set_attends.sql*
│   │   ├── table_structure.docx*
│   │   ├── therapy_track.sql*
│   │   └── unexcused_absence_count.sql*
│   ├── documents/
│   │   ├── BIPP_Accreditation_Guidelines.pdf*
│   │   ├── import_sample.xlsx*
│   │   └── Onboarding.docx*
│   ├── .gitignore*
│   ├── import/
│   │   ├── conversion_addicare.sql*
│   │   ├── conversion.sql*
│   │   ├── project/
│   │   │   └── bippImport/
│   │   │       ├── lib/
│   │   │       │   ├── .gitignore*
│   │   │       │   └── poi-bin-5.2.3/
│   │   │       │       ├── LICENSE*
│   │   │       │       └── NOTICE*
│   │   │       ├── README.md*
│   │   │       └── src/
│   │   │           ├── bippImportAddicare.java*
│   │   │           ├── bippImport.java*
│   │   │           └── MissingKeyException.java*
│   │   └── temp.sql*
│   ├── messaging/
│   │   ├── recepient.csv*
│   │   ├── sample1.txt*
│   │   └── sample_van.txt*
│   ├── public_html/
│   │   ├── absence-create.php*
│   │   ├── absence-delete.php*
│   │   ├── absence_logic_daily.php*
│   │   ├── absence_logic.php*
│   │   ├── absence_logic_weekly.php*
│   │   ├── absence-read.php*
│   │   ├── absence-update.php*
│   │   ├── admin/
│   │   │   ├── account.php*
│   │   │   ├── accounts.php*
│   │   │   ├── activity-reporting-common.php*
│   │   │   ├── activity-reporting-dumpcsv.php*
│   │   │   ├── activity-reporting.php*
│   │   │   ├── admin.css*
│   │   │   ├── admin.js*
│   │   │   ├── admin_navbar.php*
│   │   │   ├── admin.scss*
│   │   │   ├── client_file_update.php*
│   │   │   ├── emailtemplate.php*
│   │   │   ├── index.php*
│   │   │   ├── main.php*
│   │   │   ├── roles.php*
│   │   │   ├── settings.php*
│   │   │   └── user_accounts.php*
│   │   ├── attendance_record-create.php*
│   │   ├── attendance_record-delete.php*
│   │   ├── attendance_record-index.php*
│   │   ├── attendance_record-read.php*
│   │   ├── attendance_record-update.php*
│   │   ├── authenticate.php*
│   │   ├── auth.php*
│   │   ├── build_report2_table_gen.php*
│   │   ├── build_report2_table.php*
│   │   ├── build_report3_table.php*
│   │   ├── build_report3_table.php.orig*
│   │   ├── build_report4_table.php*
│   │   ├── build_report_table.php*
│   │   ├── case_manager-create.php*
│   │   ├── case_manager-delete.php*
│   │   ├── case_manager-index.php*
│   │   ├── case_manager-read.php*
│   │   ├── case_manager-update.php*
│   │   ├── check_absences.php*
│   │   ├── check_in_step1.php*
│   │   ├── check_in_step2.php*
│   │   ├── check_in_step3.php*
│   │   ├── check_in_step4.php*
│   │   ├── check_in_step5.php*
│   │   ├── client-attendance.php*
│   │   ├── client-contract-upload.php
│   │   ├── client-create.php*
│   │   ├── client-delete.php*
│   │   ├── client-event-add.php*
│   │   ├── client-event-delete.php*
│   │   ├── client-event.php*
│   │   ├── client-event-update.php*
│   │   ├── client-image-upload.php*
│   │   ├── client-index.php*
│   │   ├── client-ledger.php*
│   │   ├── clientportal.php
│   │   ├── client-read.php*
│   │   ├── client_review_panel.php*
│   │   ├── client-review.php*
│   │   ├── client-update.php*
│   │   ├── client-victim-create.php
│   │   ├── client-victim-delete.php
│   │   ├── client-victim-index.php
│   │   ├── client-victim.php
│   │   ├── client-victim-update.php
│   │   ├── completion_logic.php*
│   │   ├── composer.json*
│   │   ├── composer.lock*
│   │   ├── curriculum-create.php*
│   │   ├── curriculum-delete.php*
│   │   ├── curriculum-index.php*
│   │   ├── curriculum-read.php*
│   │   ├── curriculum-update.php*
│   │   ├── download2.php*
│   │   ├── download.php*
│   │   ├── dump_report5_csv_gen.php*
│   │   ├── dump_report5_csv.php*
│   │   ├── dump_report_csv.php*
│   │   ├── error.php*
│   │   ├── ethnicity-create.php*
│   │   ├── ethnicity-delete.php*
│   │   ├── ethnicity-index.php*
│   │   ├── ethnicity-read.php*
│   │   ├── ethnicity-update.php*
│   │   ├── facilitator-create.php*
│   │   ├── facilitator-delete.php*
│   │   ├── facilitator-index.php*
│   │   ├── facilitator-read.php*
│   │   ├── facilitator-update.php*
│   │   ├── favicon.ico -> /home/clinicnotepro/public_html/favicon.ico
│   │   ├── favicons/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon-96x96.png
│   │   │   ├── favicon.ico
│   │   │   ├── favicon.svg
│   │   │   ├── site.webmanifest
│   │   │   ├── web-app-manifest-192x192.png
│   │   │   └── web-app-manifest-512x512.png
│   │   ├── fetch_data_auto.php*
│   │   ├── fetch_data.php*
│   │   ├── generate_reports.php*
│   │   ├── getImageKey.php*
│   │   ├── getImage.php*
│   │   ├── get_task_status.php*
│   │   ├── helpers.php*
│   │   ├── home_auth.php*
│   │   ├── home.php*
│   │   ├── .htaccess*
│   │   ├── image-create.php*
│   │   ├── image-delete.php*
│   │   ├── image-index.php*
│   │   ├── image-read.php*
│   │   ├── image-update.php*
│   │   ├── img/
│   │   │   ├── female-placeholder.jpg*
│   │   │   └── male-placeholder.jpg*
│   │   ├── index.php*
│   │   ├── ledger-create.php*
│   │   ├── ledger-delete.php*
│   │   ├── ledger-read.php*
│   │   ├── ledger-update.php*
│   │   ├── location-create.php*
│   │   ├── location-delete.php*
│   │   ├── location-index.php*
│   │   ├── location-read.php*
│   │   ├── location-update.php*
│   │   ├── logout.php*
│   │   ├── mar2.php -> mar.php*
│   │   ├── mar.php*
│   │   ├── message_csv_buildform.php*
│   │   ├── message_csv.php*
│   │   ├── message_results.php*
│   │   ├── navbar.php*
│   │   ├── NoteProLogoFinal.ico*
│   │   ├── officedocuments.php*
│   │   ├── phpinfo.php*
│   │   ├── php.ini*
│   │   ├── profile.php*
│   │   ├── program-select.php*
│   │   ├── referral_type-create.php*
│   │   ├── referral_type-delete.php*
│   │   ├── referral_type-index.php*
│   │   ├── referral_type-read.php*
│   │   ├── referral_type-update.php*
│   │   ├── reportgen.php*
│   │   ├── reporting.php*
│   │   ├── send_sms.php*
│   │   ├── sql_functions.php*
│   │   ├── style.css*
│   │   ├── style.scss*
│   │   ├── test.php*
│   │   ├── therapy_group-create.php*
│   │   ├── therapy_group-delete.php*
│   │   ├── therapy_group-index.php*
│   │   ├── therapy_group-read.php*
│   │   ├── therapy_group-update.php*
│   │   ├── therapy_session-attendance.php*
│   │   ├── therapy_session-create.php*
│   │   ├── therapy_session-delete.php*
│   │   ├── therapy_session-index.php*
│   │   ├── therapy_session-read.php*
│   │   ├── therapy_session-update.php*
│   │   ├── transformlogo.png*
│   │   ├── truant_client.php*
│   │   ├── update_clients.php*
│   │   └── .user.ini*
│   ├── report/
│   │   ├── mar/
│   │   │   └── CJAD BIPP MAR sample.pdf*
│   │   └── merge/
│   │       ├── connection_setup.docx*
│   │       └── samples/
│   │           ├── BIPP Progress Note.27.C.dotm*
│   │           ├── BIPP Progress Note.27.denton.dotm*
│   │           ├── report2_progress_note.docx*
│   │           ├── report2_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic.docx*
│   │           ├── report3_progress_note_pic_vert.docx*
│   │           ├── T4C_EntranceNotification.docx*
│   │           ├── t4c_merge_results.docx*
│   │           └── T4C_ProgressNoteSample.docx*
│   └── scripts/
│       ├── auto_complete.sh*
│       ├── create_absence.sh*
│       ├── fetch_data.sh*
│       ├── logs/
│       │   └── fetch_data_20250306.html*
│       └── update_clients.sh*
├── .viminfo
├── .wget-hsts
└── .wp-toolkit-identifier

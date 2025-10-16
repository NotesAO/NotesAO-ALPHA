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

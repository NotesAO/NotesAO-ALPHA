import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime
import re
import argparse
import shutil
import subprocess
import time
import sys
from datetime import timedelta
import math

# ------------------------------------------------------------------------------
# Logging
# ------------------------------------------------------------------------------
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s [%(levelname)s] %(message)s')

# ------------------------------------------------------------------------------
# CLI args
# ------------------------------------------------------------------------------
parser = argparse.ArgumentParser(description='Generate BIPP Entrance Notifications')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory (clinic folder)')
args = parser.parse_args()

# ------------------------------------------------------------------------------
# Base vars
# ------------------------------------------------------------------------------
csv_file_path = args.csv_file
start_date = datetime.strptime(args.start_date, '%Y-%m-%d')
end_date = datetime.strptime(args.end_date, '%Y-%m-%d')
clinic_folder = args.clinic_folder
output_dir = args.output_dir

base_dir = os.path.dirname(os.path.abspath(__file__))
templates_dir = args.templates_dir  # clinic-specific templates folder
today = datetime.now().strftime('%m.%d.%y')

# ------------------------------------------------------------------------------
# Output folders
# ------------------------------------------------------------------------------
if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today)
os.makedirs(generated_documents_dir, exist_ok=True)

public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)

central_location_path = os.path.join(public_generated_documents_dir, today)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory created: {central_location_path}")

# Error sink (per run)
errors_file_path = os.path.join(central_location_path, 'errors.txt')

def record_error(row_dict, reasons):
    """
    Append a human-readable line to errors.txt with client + reasons list.
    row_dict can be None or empty for global errors.
    """
    try:
        if row_dict:
            cid   = row_dict.get('client_id', '')
            fname = (row_dict.get('first_name') or '').strip()
            lname = (row_dict.get('last_name') or '').strip()
            line  = f"client_id={cid} name={lname}, {fname} :: " + "; ".join(reasons)
        else:
            line  = "GLOBAL :: " + "; ".join(reasons)
        with open(errors_file_path, 'a', encoding='utf-8') as f:
            f.write(line + "\n")
        logging.warning(line)
    except Exception as e:
        logging.error(f"Failed writing to errors.txt: {e}")

# ------------------------------------------------------------------------------
# Template resolver (clinic → default/BIPP)
# ------------------------------------------------------------------------------
PROGRAM_DEFAULT_CODE = 'BIPP'  # this script is for BIPP

templates_root = os.environ.get('NP_TEMPLATES_DIR') or os.path.join(base_dir, 'templates')
program_code   = os.environ.get('NP_PROGRAM_CODE')  or PROGRAM_DEFAULT_CODE
clinic_folder  = os.environ.get('NP_CLINIC_FOLDER') or clinic_folder  # prefer env if present

clinic_templates_dir = templates_dir  # args.templates_dir: .../templates/<clinic>

def resolve_template(filename: str) -> str:
    """
    Prefer clinic template .../templates/{clinic}/{filename};
    if missing, fall back to .../templates/default/{program_code}/{filename};
    final safety: default BIPP for this script.
    """
    clinic_path  = os.path.join(clinic_templates_dir, filename)
    default_path = os.path.join(templates_root, 'default', program_code, filename)

    if os.path.isfile(clinic_path) and os.path.getsize(clinic_path) > 0:
        logging.debug(f"Using clinic template: {clinic_path}")
        return clinic_path

    if os.path.isfile(default_path) and os.path.getsize(default_path) > 0:
        logging.info(f"Using default {program_code} template: {default_path}")
        return default_path

    bipp_default = os.path.join(templates_root, 'default', 'BIPP', filename)
    if program_code != 'BIPP' and os.path.isfile(bipp_default) and os.path.getsize(bipp_default) > 0:
        logging.warning(f"Falling back to default BIPP template: {bipp_default}")
        return bipp_default

    raise FileNotFoundError(
        f"Template not found. Tried:\n - {clinic_path}\n - {default_path}"
    )

TEMPLATE_FILE = 'Template.BIPP Entrance Notification.docx'
try:
    template_path = resolve_template(TEMPLATE_FILE)
except Exception as e:
    # Do not break PHP; log and exit gracefully
    record_error(None, [f"template missing: {e}"])
    sys.exit(0)

# ------------------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------------------
def _is_missing(x):
    return x is None or (isinstance(x, float) and math.isnan(x)) or (isinstance(x, str) and x.strip() == "")

def format_date(value, formats=None, out_fmt='%m/%d/%Y'):
    """Return '' on missing/unparseable; accept str, Timestamp, date/datetime, or NaN/float."""
    if formats is None:
        formats = ['%m/%d/%Y', '%Y-%m-%d', '%m-%d-%Y', '%Y/%m/%d']
    if _is_missing(value):
        return ''
    # Import here to avoid top-level circulars in some environments
    import pandas as pd  # noqa: WPS433
    from datetime import date as _date  # noqa: WPS433

    if isinstance(value, (pd.Timestamp, datetime, _date)):
        try:
            return pd.to_datetime(value).strftime(out_fmt)
        except Exception:
            return ''
    s = str(value).strip()
    for fmt in formats:
        try:
            return datetime.strptime(s, fmt).strftime(out_fmt)
        except Exception:
            pass
    try:
        ts = pd.to_datetime(s, errors='coerce')
        return '' if pd.isna(ts) else ts.strftime(out_fmt)
    except Exception:
        return ''

def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

def normalize_balance(raw_balance):
    try:
        val = float(raw_balance)
        return abs(val) if val < 0 else 0
    except (TypeError, ValueError):
        return ''

# --- Helpers for weekday parsing / date math (additive) ---
DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
_DOW_ALIASES = {
    'monday': 0, 'mon': 0,
    'tuesday': 1, 'tue': 1, 'tues': 1,
    'wednesday': 2, 'wed': 2,
    'thursday': 3, 'thu': 3, 'thur': 3, 'thurs': 3,
    'friday': 4, 'fri': 4,
    'saturday': 5, 'sat': 5,
    'sunday': 6, 'sun': 6,
}

def _extract_weekday_from_group(group_name: str):
    """Return (FullDayName, weekday_index) if we can infer a day from group_name, else (None, None)."""
    if not isinstance(group_name, str):
        return (None, None)
    s = group_name.strip().lower()
    tokens = re.split(r'[\s\-\(\)]+', s)
    for tok in tokens:
        if tok in _DOW_ALIASES:
            idx = _DOW_ALIASES[tok]
            return (DAY_NAMES[idx], idx)
    for alias, idx in _DOW_ALIASES.items():
        if f' {alias} ' in f' {s} ':
            return (DAY_NAMES[idx], idx)
    return (None, None)

def _next_weekday(d: datetime, target_idx: int) -> datetime:
    """Given a date d and target weekday index (Mon=0..Sun=6), return the date of the next occurrence (strictly after d)."""
    days_ahead = (target_idx - d.weekday() + 7) % 7
    if days_ahead == 0:
        days_ahead = 7
    return d + timedelta(days=days_ahead)

def _parse_dt_any(value):
    """Quick parser for dates -> datetime or None."""
    if value is None:
        return None
    if not isinstance(value, str):
        value = str(value)
    for fmt in ('%m/%d/%Y', '%Y-%m-%d', '%m-%d-%Y', '%Y/%m/%d', '%Y-%m-%d %H:%M:%S'):
        try:
            return datetime.strptime(value.strip(), fmt)
        except ValueError:
            continue
    return None

def normalize_gender(raw):
    if _is_missing(raw):
        return 'unspecified'
    s = str(raw).strip().lower()
    if s in ('m', 'male', 'man', 'boy'):
        return 'male'
    if s in ('f', 'female', 'woman', 'girl'):
        return 'female'
    if s in ('not specified', 'unspecified', 'unknown', 'n/a', 'na', 'other'):
        return 'unspecified'
    return s

# ------------------------------------------------------------------------------
# Row filler (kept from original, hardened)
# ------------------------------------------------------------------------------
def fill_placeholders(row):
    gender_mapping = {
        'male':   {'gender1': 'his', 'gender2': 'he', 'gender3': "Men's",   'gender4': "Mr.", 'gender5': 'him'},
        'female': {'gender1': 'her', 'gender2': 'she', 'gender3': "Women's", 'gender4': "Ms.", 'gender5': 'her'},
    }

    gender = normalize_gender(row.get('gender'))
    logging.debug(f"Gender: {gender}")
    if gender in gender_mapping:
        row.update(gender_mapping[gender])
        logging.debug(f"Updated row with gender mapping keys for: {gender}")
    else:
        logging.debug(f"Gender '{gender}' not mapped; leaving placeholders for later.")

    # normalize *_date fields
    for placeholder, value in list(row.items()):
        if isinstance(placeholder, str) and placeholder.endswith('_date') and not _is_missing(value):
            row[placeholder] = format_date(value)

    # Replace '&' with 'and' in client_note if needed
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    # Safe DOB
    row['dob'] = format_date(row.get('dob'), formats=('%m/%d/%Y', '%Y-%m-%d'))

    # orientation date
    if 'orientation_date_str' in row:
        row['orientation_date'] = format_date(row.get('orientation_date_str'), formats=('%m/%d/%Y', '%Y-%m-%d'))

    # normalize balance
    if 'balance' in row:
        row['balance'] = normalize_balance(row['balance'])

    # map bool-like fields
    def map_bool(val):
        return {0: 'No', 1: 'Yes', '0': 'No', '1': 'Yes'}.get(val, val)

    row['restarted'] = map_bool(row.get('restarted', ''))
    row['completed'] = map_bool(row.get('completed', ''))

    # progress_ok normalization & explanation
    _raw_prog = str(row.get('progress_ok', '')).strip()
    _yn_map = {'1': 'Yes', '0': 'No', 'Y': 'Yes', 'N': 'No', 'y': 'Yes', 'n': 'No', 'Yes': 'Yes', 'No': 'No'}
    row['progress_ok'] = _yn_map.get(_raw_prog, _raw_prog.lower() if _raw_prog else '')

    if row['progress_ok'] == 'no':
        expected = {
            'respectful_to_group': 'Y',
            'takes_responsibility_for_past': 'Y',
            'disruptive_argumentitive': 'N',
            'humor_inappropriate': 'N',
            'blames_victim': 'N',
            'appears_drug_alcohol': 'N',
            'inappropriate_to_staff': 'N',
        }
        labels = {
            'respectful_to_group': 'Not respectful to group',
            'takes_responsibility_for_past': 'Does not take responsibility for past behavior',
            'disruptive_argumentitive': 'Disruptive/argumentative in group',
            'humor_inappropriate': 'Uses inappropriate humor',
            'blames_victim': 'Blames victim',
            'appears_drug_alcohol': 'Appeared under the influence of drugs/alcohol',
            'inappropriate_to_staff': 'Inappropriate toward staff',
        }
        issues = []
        for col, good in expected.items():
            val = row.get(col, '')
            val_up = str(val).strip().upper()
            if val_up and val_up != good:
                issues.append(labels[col])
        row['progress_expl'] = '; '.join(issues) if issues else 'Overall progress below expectations; see facilitator notes.'
    else:
        row['progress_expl'] = ''

    # class_day, start_date, class_time
    group_name = row.get('group_name', '')
    day_name, day_idx = _extract_weekday_from_group(group_name)
    row['class_day'] = day_name or row.get('class_day', '')

    o_str = row.get('orientation_date_str') or row.get('orientation_date')
    o_dt = _parse_dt_any(o_str)
    if o_dt and day_idx is not None:
        _start_dt = _next_weekday(o_dt, day_idx)
        row['start_date'] = _start_dt.strftime('%m/%d/%Y')
    else:
        row['start_date'] = row.get('start_date', '')

    row['class_time'] = group_name or row.get('class_time', '')

    return row

# ------------------------------------------------------------------------------
# DOCX → PDF
# ------------------------------------------------------------------------------
def docx_to_pdf(docx_path, pdf_dir, retries=3):
    """Convert DOCX to PDF using LibreOffice with retry logic."""
    attempt = 0
    while attempt < retries:
        try:
            output_file = os.path.join(pdf_dir, os.path.basename(docx_path).replace('.docx', '.pdf'))
            subprocess.run(['libreoffice', '--headless', '--convert-to', 'pdf', '--outdir', pdf_dir, docx_path], check=True)
            if os.path.exists(output_file):
                logging.info(f"Converted {docx_path} to PDF successfully on attempt {attempt + 1}.")
                return output_file
            else:
                raise FileNotFoundError(f"PDF not found at expected path: {output_file}")
        except subprocess.CalledProcessError as e:
            logging.error(f"Error converting {docx_path} to PDF on attempt {attempt + 1}: {e}")
            attempt += 1
            time.sleep(1)  # retry after 1 second
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

# ------------------------------------------------------------------------------
# CSV load
# ------------------------------------------------------------------------------
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    # Graceful: log error and exit 0 (avoid bubbling to PHP)
    record_error(None, [f"failed to load CSV: {e}"])
    sys.exit(0)

# ------------------------------------------------------------------------------
# Filter for BIPP entries
# ------------------------------------------------------------------------------
df = df[df['program_name'].isin(['BIPP (male)', 'BIPP (female)'])]
logging.debug(f"Filtered DataFrame by program name. Number of records: {len(df)}")

df['orientation_date_str'] = df['orientation_date']

def try_parsing_date(text):
    for fmt in ('%m/%d/%Y', '%Y-%m-%d'):
        try:
            return datetime.strptime(text, fmt)
        except (ValueError, TypeError):
            continue
    return None

df['orientation_date'] = df['orientation_date'].apply(lambda x: try_parsing_date(x) if pd.notnull(x) else None)
logging.debug(f"orientation_date after parsing:\n{df['orientation_date'].head()}")

df = df.sort_values(by='orientation_date', ascending=False)
logging.debug(f"DataFrame sorted by orientation_date:\n{df.head()}")

# Filter by date range
df_filtered = df[(df['orientation_date'] >= start_date) & (df['orientation_date'] <= end_date)]
logging.debug(f"Filtered DataFrame by date range. Number of records: {len(df_filtered)}")

if df_filtered.empty:
    logging.warning("DataFrame is empty after filtering. No documents will be generated.")
    sys.exit(0)

# ------------------------------------------------------------------------------
# Validation for Entrance Notifications (skip & record issues)
# ------------------------------------------------------------------------------
def validate_row_for_entrance(row_dict):
    """
    Return (ok, reasons).
    REQUIRE:
      - gender male/female (unspecified => skip)
      - first_name, last_name present
      - orientation_date present/parseable (from *_str or orientation_date)
    """
    reasons = []

    g = normalize_gender(row_dict.get('gender'))
    if g not in ('male', 'female'):
        reasons.append('gender not male/female (value is unspecified)')

    if not (row_dict.get('first_name') and row_dict.get('last_name')):
        reasons.append('missing first_name/last_name')

    od_str = row_dict.get('orientation_date_str') or row_dict.get('orientation_date')
    if not format_date(od_str):
        reasons.append('orientation_date missing or invalid')

    return (len(reasons) == 0, reasons)

# ------------------------------------------------------------------------------
# Row-wise processing
# ------------------------------------------------------------------------------
df_filtered = df_filtered.drop(columns=['orientation_date'], errors='ignore').copy()

generated_docx_files = []
processed = 0
skipped = 0

for index, row in df_filtered.iterrows():
    row_dict = row.to_dict()

    ok, reasons = validate_row_for_entrance(row_dict)
    if not ok:
        record_error(row_dict, reasons)
        skipped += 1
        continue

    try:
        context = fill_placeholders(row_dict)

        # Ensure orientation_date formatting for template
        context['orientation_date'] = format_date(
            context.get('orientation_date') or context.get('orientation_date_str'),
            formats=('%m/%d/%Y', '%Y-%m-%d')
        )

        # Gender placeholders (post-normalize)
        g = normalize_gender(context.get('gender'))
        if g == 'male':
            context.update({'gender1': 'his','gender2': 'he','gender3': "Men's",'gender4': "Mr.",'gender5': 'him'})
        elif g == 'female':
            context.update({'gender1': 'her','gender2': 'she','gender3': "Women's",'gender4': "Ms.",'gender5': 'her'})
        else:
            # Shouldn't happen due to validator; still guard
            record_error(row_dict, ['gender unresolved after normalization'])
            skipped += 1
            continue

        doc = DocxTemplate(template_path)
        doc.render(context)

        first_name = sanitize_filename(context.get('first_name', ''))
        last_name  = sanitize_filename(context.get('last_name', ''))
        if first_name or last_name:
            docx_filename = f"{last_name} {first_name} BIPP Entrance.docx".strip()
        else:
            docx_filename = f"client_{context.get('client_id','unknown')}_BIPP_Entrance.docx"

        docx_path = os.path.join(generated_documents_dir, docx_filename)
        doc.save(docx_path)
        logging.info(f"Generated DOCX: {docx_path}")
        generated_docx_files.append((docx_path, context))
        processed += 1

    except FileNotFoundError as e:
        record_error(row_dict, [f"template missing at render: {e}"])
        skipped += 1
    except Exception as e:
        record_error(row_dict, [f"render exception: {e}"])
        skipped += 1

# ------------------------------------------------------------------------------
# Convert to PDF & file placement
# ------------------------------------------------------------------------------
for docx_path, context in generated_docx_files:
    try:
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            # Remove DOCX after conversion
            try:
                os.remove(docx_path)
            except Exception:
                pass

            # If office is present, nest under it; otherwise put manager directly under the date folder.
            office_raw = context.get('case_manager_office')
            # Treat None/NaN/empty and strings like "nan"/"null"/"none" as missing
            office_missing = (
                _is_missing(office_raw) or
                (isinstance(office_raw, str) and office_raw.strip().lower() in ('nan', 'none', 'null', ''))
            )
            office = 'Unknown Office' if office_missing else sanitize_filename(str(office_raw))


            manager_name = sanitize_filename(
                (f"{context.get('case_manager_first_name','')} {context.get('case_manager_last_name','')}".strip())
            ) or 'Unknown Manager'

            if office:
                parent_dir = os.path.join(central_location_path, office)
            else:
                # No office → manager folder sits at the same level as office folders
                parent_dir = central_location_path

            manager_dir = os.path.join(parent_dir, manager_name)
            os.makedirs(manager_dir, exist_ok=True)

            final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
            shutil.move(pdf_path, final_pdf_path)
            logging.info(f"Moved PDF to: {final_pdf_path}")
        else:
            record_error(context, ['PDF conversion failed (no output file)'])
    except Exception as e:
        record_error(context, [f'PDF conversion exception: {e}'])

# ------------------------------------------------------------------------------
# Final verification
# ------------------------------------------------------------------------------
remaining_docx_files = [f for f in os.listdir(generated_documents_dir) if f.endswith('.docx')]
if remaining_docx_files:
    logging.error(f"The following DOCX files were not converted to PDF: {remaining_docx_files}")
else:
    logging.info("All DOCX files successfully converted to PDF and organized.")

logging.info(f"Row processing complete. processed={processed}, skipped={skipped}")

# Always exit 0 so PHP UI doesn't show an error panel for per-row issues
sys.exit(0)

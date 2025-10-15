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

# Configure logging
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s [%(levelname)s] %(message)s')

# Parse command-line arguments
parser = argparse.ArgumentParser(description='Generate DWIE Entrance Notifications')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

# Assign arguments to variables
csv_file_path = args.csv_file
start_date = datetime.strptime(args.start_date, '%Y-%m-%d')
end_date = datetime.strptime(args.end_date, '%Y-%m-%d')
clinic_folder = args.clinic_folder
output_dir = args.output_dir

# Define base directories and today's date for directory naming
base_dir = os.path.dirname(os.path.abspath(__file__))
templates_dir = args.templates_dir

# --- env overrides + templates root ---
program_code  = os.environ.get('NP_PROGRAM_CODE', 'DWIE')
templates_dir = os.environ.get('NP_TEMPLATES_DIR', templates_dir)
clinic_folder = os.environ.get('NP_CLINIC_FOLDER', clinic_folder)

if os.path.basename(templates_dir) == clinic_folder:
    _TEMPLATES_ROOT = os.path.dirname(templates_dir)
else:
    _TEMPLATES_ROOT = templates_dir

today = datetime.now().strftime('%m.%d.%y')

# Determine the generated documents directory (for initial DOCX and PDF generation)
if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today)
os.makedirs(generated_documents_dir, exist_ok=True)

# The public directory where final PDFs should be placed
public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)

# Define a central location path for final sorted PDFs, similar to the completion script
central_location_path = os.path.join(public_generated_documents_dir, today)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory created: {central_location_path}")

# --- per-run errors sink + resolver ---
errors_txt = os.path.join(generated_documents_dir, 'errors.txt')
def record_error(msg: str):
    try:
        with open(errors_txt, 'a', encoding='utf-8') as f:
            f.write(str(msg).rstrip() + '\n')
    except Exception:
        pass
    logging.error(msg)

def resolve_template(templates_root: str, clinic: str, program: str, filename: str):
    for p in (
        os.path.join(templates_root, clinic, filename),
        os.path.join(templates_root, 'default', program, filename),
        os.path.join(templates_root, 'default', 'BIPP', filename),
    ):
        if os.path.isfile(p):
            return p
    return None

def need_template(filename: str):
    p = resolve_template(_TEMPLATES_ROOT, clinic_folder, program_code, filename)
    if not p:
        record_error(f"[FATAL] Missing template {filename} under clinic/default fallbacks in {_TEMPLATES_ROOT}.")
    return p


# Template path
template_path = need_template('Template.DWIE Entrance Notification.docx')

def format_date(value, formats=None):
    if formats is None:
        formats = ['%m/%d/%Y', '%Y-%m-%d', '%d-%m-%Y', '%Y/%m/%d']
    for fmt in formats:
        try:
            date = datetime.strptime(value, fmt)
            return date.strftime('%m/%d/%Y')
        except ValueError:
            continue
    logging.warning(f"Unrecognized date format for value: {value}")
    return value

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
# Accept full names + common short forms appearing in group names
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
    """
    Return (FullDayName, weekday_index) if we can infer a day from group_name, else (None, None).
    We look anywhere in the string so "Retired Sat ..." still resolves to Saturday.
    """
    if not isinstance(group_name, str):
        return (None, None)
    s = group_name.strip().lower()
    # tokenize on whitespace/()/- to catch day tokens
    tokens = re.split(r'[\s\-\(\)]+', s)
    for tok in tokens:
        if tok in _DOW_ALIASES:
            idx = _DOW_ALIASES[tok]
            return (DAY_NAMES[idx], idx)
    # fallback: scan substring-wise if tokenization missed
    for alias, idx in _DOW_ALIASES.items():
        if f' {alias} ' in f' {s} ':
            return (DAY_NAMES[idx], idx)
    return (None, None)

def _next_weekday(d: datetime, target_idx: int) -> datetime:
    """
    Given a date d and target weekday index (Mon=0..Sun=6),
    return the date of the *next* occurrence (strictly after d).
    """
    days_ahead = (target_idx - d.weekday() + 7) % 7
    if days_ahead == 0:
        days_ahead = 7
    return d + timedelta(days=days_ahead)

def _parse_dt_any(value):
    """Local quick parser for dates -> datetime or None (kept local to avoid reordering existing defs)."""
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


def fill_placeholders(row):
    gender_mapping = {
        'male': {'gender1': 'his', 'gender2': 'he', 'gender3': "Men's", 'gender4': "Mr.", 'gender5': 'him'},
        'female': {'gender1': 'her', 'gender2': 'she', 'gender3': "Women's", 'gender4': "Ms.", 'gender5': 'her'},
    }

    gender = row.get('gender', '').lower()
    logging.debug(f"Gender: {gender}")
    if gender in gender_mapping:
        row.update(gender_mapping[gender])
        logging.debug(f"Updated row with gender mapping: {row}")
    else:
        logging.error(f"Gender '{gender}' not found in gender_mapping.")

    for placeholder, value in row.items():
        if placeholder.endswith('_date') and pd.notnull(value):
            # Attempt various date formats
            try:
                date = datetime.strptime(value, '%m/%d/%Y')
            except ValueError:
                try:
                    date = datetime.strptime(value, '%Y-%m-%d')
                except ValueError:
                    continue
            row[placeholder] = date.strftime('%m/%d/%Y')

    # Replace '&' with 'and' in client_note if needed
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    # Format dob as m/d/yyyy
    if 'dob' in row:
        row['dob'] = format_date(row['dob'], formats=('%m/%d/%Y', '%Y-%m-%d'))

    # Format orientation_date from orientation_date_str if present
    if 'orientation_date_str' in row:
        row['orientation_date'] = format_date(row['orientation_date_str'], formats=('%m/%d/%Y', '%Y-%m-%d'))

    if 'balance' in row: 
        row['balance'] = normalize_balance(row['balance'])

    def map_bool(val):
        return {0: 'No', 1: 'Yes', '0': 'No', '1': 'Yes'}.get(val, val)

    row['restarted'] = map_bool(row.get('restarted', ''))
    row['completed'] = map_bool(row.get('completed', ''))

    if row.get('progress_ok') == '0':
        row['progress_expl'] = "Please contact us for a closer look."
    else:
        row['progress_expl'] = ''

    # --- NEW: normalize progress_ok (1/0 -> yes/no) & construct progress_expl when not OK ---
    # Accept a few common variants to be robust across CSVs
    _raw_prog = str(row.get('progress_ok', '')).strip()
    _yn_map = {'1': 'Yes', '0': 'No', 'Y': 'Yes', 'N': 'No', 'y': 'Yes', 'n': 'No', 'Yes': 'Yes', 'No': 'No'}
    row['progress_ok'] = _yn_map.get(_raw_prog, _raw_prog.lower() if _raw_prog else '')

    if row['progress_ok'] == 'no':
        # Define expected "good" values. If the row deviates, we add a plain-English issue.
        expected = {
            'respectful_to_group': 'Y',            # expected respectful
            'takes_responsibility_for_past': 'Y',  # expected responsibility
            'disruptive_argumentitive': 'N',       # expected NOT disruptive
            'humor_inappropriate': 'N',            # expected NOT inappropriate humor
            'blames_victim': 'N',                  # expected NOT blaming victim
            'appears_drug_alcohol': 'N',           # expected NOT under influence
            'inappropriate_to_staff': 'N',         # expected NOT inappropriate to staff
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
            if isinstance(val, str):
                val_up = val.strip().upper()
            else:
                val_up = str(val).strip().upper()
            if val_up and val_up != good:
                issues.append(labels[col])

        # If earlier stub set something, we’ll replace it with the concrete list.
        if issues:
            row['progress_expl'] = '; '.join(issues)
        else:
            # Still not OK but no granular flags—leave a neutral note
            row['progress_expl'] = 'Overall progress below expectations; see facilitator notes.'
    else:
        # If OK, ensure explanation is blank
        row['progress_expl'] = ''

    # --- NEW: class_day, start_date, class_time ---
    group_name = row.get('group_name', '')
    day_name, day_idx = _extract_weekday_from_group(group_name)

    # class_day → always the full day name if we can infer it
    row['class_day'] = day_name or row.get('class_day', '')

    # orientation_date for math (use the raw string column the script already carries)
    o_str = row.get('orientation_date_str') or row.get('orientation_date')  # either one
    o_dt = _parse_dt_any(o_str)

    # start_date → next occurrence of class day after orientation_date
    if o_dt and day_idx is not None:
        _start_dt = _next_weekday(o_dt, day_idx)
        row['start_date'] = _start_dt.strftime('%m/%d/%Y')
    else:
        # fallback: keep whatever may already be present/formatted upstream
        row['start_date'] = row.get('start_date', '')

    # class_time → per spec, just print the full group_name string
    row['class_time'] = group_name or row.get('class_time', '')


    return row

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

# Load the CSV file
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

# Filter by DWIE (male/female)
df = df[df['program_name'].isin(['DWIE'])]
logging.debug(f"Filtered DataFrame by program name. Number of records: {len(df)}")

df['orientation_date_str'] = df['orientation_date']

def try_parsing_date(text):
    for fmt in ('%m/%d/%Y', '%Y-%m-%d'):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
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
else:
    df_filtered.drop(columns=['orientation_date'], inplace=True)
    df_filtered = df_filtered.apply(fill_placeholders, axis=1)
    df_filtered.fillna('', inplace=True)

    # Generate DOCX files
    generated_docx_files = []
    for index, row in df_filtered.iterrows():
        try:
            if not template_path:
                return
            doc = DocxTemplate(template_path)
            context = row.to_dict()

            # Ensure orientation_date formatting
            if 'orientation_date' in context and pd.notnull(context['orientation_date']):
                orientation_date = pd.to_datetime(context['orientation_date'])
                context['orientation_date'] = orientation_date.strftime('%m/%d/%Y')
            else:
                context['orientation_date'] = ''

            # Set gender placeholders if needed
            gender = context.get('gender', '').lower()
            if gender == 'male':
                context['gender1'] = 'his'
                context['gender2'] = 'he'
                context['gender3'] = "Men's"
                context['gender4'] = "Mr."
                context['gender5'] = 'him'  
            elif gender == 'female':
                context['gender1'] = 'her'
                context['gender2'] = 'she'
                context['gender3'] = "Women's"
                context['gender4'] = "Ms."
                context['gender5'] = 'her'
            else:
                logging.error(f"Gender '{gender}' not recognized.")

            doc.render(context)
            first_name = sanitize_filename(context.get('first_name', ''))
            last_name = sanitize_filename(context.get('last_name', ''))
            docx_filename = f"{last_name} {first_name} DWIE Entrance.docx"
            docx_path = os.path.join(generated_documents_dir, docx_filename)
            doc.save(docx_path)
            logging.info(f"Generated DOCX: {docx_path}")
            generated_docx_files.append((docx_path, row))  # Store row too for later reference

        except Exception as e:
            logging.error(f"Error processing row {index}: {e}")

    # Convert DOCX to PDFs and move them
    for docx_path, row in generated_docx_files:
        try:
            pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
            if pdf_path:
                # Remove DOCX after conversion
                os.remove(docx_path)

                # Organize PDFs by case manager office/name under today's date folder
                case_manager_office = sanitize_filename(row['case_manager_office'])
                case_manager_name = sanitize_filename(f"{row['case_manager_first_name']} {row['case_manager_last_name']}")

                office_dir = os.path.join(central_location_path, case_manager_office)
                manager_dir = os.path.join(office_dir, case_manager_name)
                os.makedirs(manager_dir, exist_ok=True)

                final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
                shutil.move(pdf_path, final_pdf_path)
                logging.info(f"Moved PDF to: {final_pdf_path}")
            else:
                logging.error(f"PDF conversion failed for DOCX: {docx_path}")
        except Exception as e:
            logging.error(f"Error converting DOCX to PDF for {docx_path}: {e}")

    # Final verification
    remaining_docx_files = [f for f in os.listdir(generated_documents_dir) if f.endswith('.docx')]
    if remaining_docx_files:
        logging.error(f"The following DOCX files were not converted to PDF: {remaining_docx_files}")
    else:
        logging.info("All DOCX files successfully converted to PDF and organized.")

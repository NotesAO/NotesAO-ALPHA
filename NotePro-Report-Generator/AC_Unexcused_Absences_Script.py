import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime
import subprocess
import time
import argparse
import shutil
import sys

# ------------------------------
# 1) Parse command-line arguments (including --templates_dir)
# ------------------------------
parser = argparse.ArgumentParser(description='Generate Anger Control Unexcused Absence Documents')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')

# NEW: templates_dir argument
parser.add_argument('--templates_dir', required=True, help='Path to the clinic-specific templates folder')

parser.add_argument('--output_dir', required=False, help='Optional path to the output directory')
args = parser.parse_args()

csv_file_path   = args.csv_file
start_date_str  = args.start_date
end_date_str    = args.end_date
clinic_folder   = args.clinic_folder
templates_dir   = args.templates_dir  # <-- new

# --- env overrides + templates root ---
program_code  = os.environ.get('NP_PROGRAM_CODE', 'AC')
templates_dir = os.environ.get('NP_TEMPLATES_DIR', templates_dir)
clinic_folder = os.environ.get('NP_CLINIC_FOLDER', clinic_folder)
if os.path.basename(templates_dir) == clinic_folder:
    _TEMPLATES_ROOT = os.path.dirname(templates_dir)
else:
    _TEMPLATES_ROOT = templates_dir

output_dir      = args.output_dir

# ------------------------------
# 2) Logging Setup
# ------------------------------
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'AC_Unexcused_Absences_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("AC Unexcused Absences Script started.")

# ------------------------------
# 3) Convert start/end dates
# ------------------------------
try:
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
    end_date   = datetime.strptime(end_date_str, '%Y-%m-%d')
except ValueError as e:
    logging.error(f"Invalid date format for start_date or end_date: {e}")
    sys.exit(1)

# ------------------------------
# 4) Define directories
# ------------------------------
base_dir = os.path.dirname(os.path.abspath(__file__))
today_str = datetime.now().strftime('%m.%d.%y')

if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today_str)
os.makedirs(generated_documents_dir, exist_ok=True)

public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)

central_location_path = os.path.join(public_generated_documents_dir, today_str)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory created: {central_location_path}")

errors_txt = os.path.join(generated_documents_dir, 'errors.txt')
def record_error(msg: str):
    try:
        with open(errors_txt, 'a', encoding='utf-8') as f:
            f.write(str(msg).rstrip() + '\n')
    except Exception:
        pass
    logging.error(msg)

def resolve_template(templates_root, clinic, program, filename):
    for p in (
        os.path.join(templates_root, clinic, filename),
        os.path.join(templates_root, 'default', program, filename),
        os.path.join(templates_root, 'default', 'BIPP', filename),
    ):
        if os.path.isfile(p):
            return p
    return None


# ------------------------------
# 5) Template Path from --templates_dir
# ------------------------------
template_file_path = resolve_template(_TEMPLATES_ROOT, clinic_folder, program_code, 'Template.AC Unexcused Absence.docx')
if not template_file_path:
    record_error(f"[FATAL] Missing template Template.AC Unexcused Absence.docx under clinic/default fallbacks in {_TEMPLATES_ROOT}.")


# ------------------------------
# Helper functions
# ------------------------------
def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

def docx_to_pdf(docx_path, pdf_dir, retries=3):
    """
    Convert DOCX to PDF using LibreOffice with up to 3 retries.
    """
    attempt = 0
    while attempt < retries:
        try:
            output_file = os.path.join(pdf_dir, os.path.basename(docx_path).replace('.docx', '.pdf'))
            subprocess.run(['libreoffice', '--headless', '--convert-to', 'pdf', '--outdir', pdf_dir, docx_path],
                           check=True)
            if os.path.exists(output_file):
                logging.info(f"Converted {docx_path} to PDF successfully on attempt {attempt + 1}.")
                return output_file
            else:
                raise FileNotFoundError(f"PDF not found at expected path: {output_file}")
        except subprocess.CalledProcessError as e:
            logging.error(f"Error converting {docx_path} to PDF on attempt {attempt + 1}: {e}")
            attempt += 1
            time.sleep(2)
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

def parse_date_formats(value):
    """
    Try multiple date formats (YYYY-MM-DD or MM/DD/YYYY or mm/dd/yy).
    Return a datetime object or None.
    """
    if not value or not isinstance(value, str):
        return None
    value = value.strip()
    possible_formats = ['%Y-%m-%d','%m/%d/%Y','%m/%d/%y']
    for fmt in possible_formats:
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            continue
    return None

def format_mmddyyyy(dt_obj):
    """Return dt_obj as 'MM/DD/YYYY'."""
    return dt_obj.strftime('%m/%d/%Y')

def format_mmddyy(dt_obj):
    """Return dt_obj as 'MM/DD/YY' (short year)."""
    return dt_obj.strftime('%m/%d/%y')

def fill_gender(row):
    """
    Fill gender1..gender3 for male/female. If unrecognized, log an error and set placeholders to ''.
    """
    gender = row.get('gender', '').lower()
    if gender == 'male':
        row['gender1'] = 'his'
        row['gender2'] = 'he'
        row['gender3'] = "Men's"
    elif gender == 'female':
        row['gender1'] = 'her'
        row['gender2'] = 'she'
        row['gender3'] = "Women's"
    else:
        logging.error(f"Unrecognized gender: {gender}. Setting placeholders to empty.")
        row['gender1'] = ''
        row['gender2'] = ''
        row['gender3'] = ''

def fill_placeholders(row):
    """
    Fills placeholders for AC Unexcused Absences:
    - Replace 'nan' with ''
    - gender placeholders
    - last_absence => mm/dd/yyyy
    - A1..A28 => mm/dd/yy
    - Combine them into 'absences'
    - dob => mm/dd/yyyy if present
    - client_note => replace & with 'and'
    - absence_label => 'absence' or 'absences'
    """
    # 1) Replace any 'nan' with empty strings
    for col, val in row.items():
        if isinstance(val, str) and val.strip().lower() == 'nan':
            row[col] = ''

    # 2) Fill gender placeholders
    fill_gender(row)

    # 3) last_absence => mm/dd/yyyy
    val = row.get('last_absence','').strip()
    if val:
        dt_obj = parse_date_formats(val)
        row['last_absence'] = format_mmddyyyy(dt_obj) if dt_obj else ''
    else:
        row['last_absence'] = ''

    # 4) dob => mm/dd/yyyy if it exists
    if 'dob' in row:
        raw_dob = row['dob'].strip()
        if raw_dob:
            dt_obj = parse_date_formats(raw_dob)
            row['dob'] = format_mmddyyyy(dt_obj) if dt_obj else ''
        else:
            row['dob'] = ''

    # 5) A1..A28 => mm/dd/yy or blank
    abs_list = []   # We'll collect each valid date in short format here
    for i in range(1, 29):
        a_col = f'A{i}'
        raw_val = row.get(a_col, '').strip()
        if raw_val:
            dt_obj = parse_date_formats(raw_val)
            if dt_obj:
                short_date = format_mmddyy(dt_obj)
                row[a_col] = short_date
                abs_list.append(short_date)
            else:
                row[a_col] = ''
        else:
            row[a_col] = ''

    # For 2 or more dates => use commas and '&amp;'
    # For exactly 2 => "Date1 &amp; Date2"
    # For 3+ => "Date1, Date2, ..., &amp; DateN"
    if len(abs_list) == 0:
        row['absences'] = ''
    elif len(abs_list) == 1:
        row['absences'] = abs_list[0]
    elif len(abs_list) == 2:
        row['absences'] = f"{abs_list[0]} &amp; {abs_list[1]}"
    else:
        row['absences'] = ', '.join(abs_list[:-1]) + f", &amp; {abs_list[-1]}"

    # 7) client_note => remove '&'
    if 'client_note' in row:
        row['client_note'] = row['client_note'].replace('&','and')

    # 8) absence_label => singular vs plural
    try:
        unexcused_count = int(float(row.get('absence_unexcused', '0')))
    except ValueError:
        unexcused_count = 0
    row['absence_label'] = 'absence' if unexcused_count == 1 else 'absences'

    return row

# ------------------------------
# Load CSV
# ------------------------------
try:
    df = pd.read_csv(csv_file_path, dtype=str, low_memory=False)
    logging.info(f"Loaded {len(df)} records from CSV: {csv_file_path}")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

df.fillna('', inplace=True)

# Filter for Anger Control + exit_reason == 'Not Exited'
df = df[(df['program_name'] == 'Anger Control') & (df['exit_reason'] == 'Not Exited')]

# If there's no 'last_absence' column, we can't proceed
if 'last_absence' not in df.columns:
    logging.error("'last_absence' column not found in CSV.")
    sys.exit(1)

# Convert 'last_absence' to datetime for filtering
df['last_absence_dt'] = pd.to_datetime(df['last_absence'], errors='coerce')
df = df.dropna(subset=['last_absence_dt'])

df = df[(df['last_absence_dt'] >= start_date) & (df['last_absence_dt'] <= end_date)]
if df.empty:
    logging.warning("No records found after date filtering. No documents will be generated.")
    sys.exit(0)

# Drop the helper column
df.drop(columns=['last_absence_dt'], inplace=True)

# Apply fill_placeholders for date formatting, etc.
df = df.apply(fill_placeholders, axis=1)

def generate_documents_for_row(row):
    if not template_file_path:
        return  # soft-skip if there’s no template
    """
    Renders the doc, sets up 'report_date' as mm/dd/yyyy,
    uses no-'nan' logic for office/manager subfolder, etc.
    """
    context = row.to_dict()

    # If no unexcused absences, skip
    try:
        absence_unexcused = int(float(context.get('absence_unexcused', '0')))
    except ValueError:
        absence_unexcused = 0
    if absence_unexcused == 0:
        return

    # Add a 'report_date' field => mm/dd/yyyy
    now = datetime.now()
    context['report_date'] = now.strftime('%m/%d/%Y')

    try:
        doc = DocxTemplate(template_file_path)
        doc.render(context)

        first_name = sanitize_filename(context.get('first_name', ''))
        last_name  = sanitize_filename(context.get('last_name',  ''))
        docx_filename = f"{last_name} {first_name} AC Unexcused Absence.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)
        logging.info(f"Saved docx: {docx_path}")

        # Convert to PDF
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)

            # Office/manager subfolder logic
            case_manager_office = str(context.get('case_manager_office', '')).strip().lower()
            cm_first = str(context.get('case_manager_first_name', '')).strip()
            cm_last  = str(context.get('case_manager_last_name', '')).strip()

            # If office is missing or 'nan'
            if not case_manager_office or case_manager_office == 'nan':
                office_dir = central_location_path
            else:
                office_dir = os.path.join(
                    central_location_path,
                    sanitize_filename(case_manager_office)
                )

            # If first name is missing or 'nan'
            if not cm_first or cm_first.lower() == 'nan':
                manager_subfolder_name = sanitize_filename(cm_last)
            else:
                manager_subfolder_name = f"{sanitize_filename(cm_first)} {sanitize_filename(cm_last)}"

            manager_subfolder_name = manager_subfolder_name.strip()
            manager_dir = os.path.join(office_dir, manager_subfolder_name)
            os.makedirs(manager_dir, exist_ok=True)

            final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
            shutil.move(pdf_path, final_pdf_path)
            logging.info(f"Moved {pdf_path} to {final_pdf_path}")
        else:
            logging.error("PDF conversion failed; no document moved.")

    except Exception as e:
        logging.error(f"Error processing row for {context.get('first_name','')} {context.get('last_name','')}: {e}")

try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("AC Unexcused Absence documents have been generated and organized.")
print("AC Unexcused Absence documents have been generated and organized.")

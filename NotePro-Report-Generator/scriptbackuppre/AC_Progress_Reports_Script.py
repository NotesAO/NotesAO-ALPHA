import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime
from PIL import Image
import subprocess
import time
import argparse
import shutil
import sys

# ------------------------------
# 1) Parse command-line arguments (including --templates_dir)
# ------------------------------
parser = argparse.ArgumentParser(description='Generate Anger Control Progress Reports')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='Optional start date (YYYY-MM-DD)')
parser.add_argument('--end_date', required=False, help='Optional end date (YYYY-MM-DD)')
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
output_dir      = args.output_dir

# ------------------------------
# 2) Logging Setup
# ------------------------------
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'AC_Progress_Reports_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("AC Progress Reports Script started.")

# ------------------------------
# 3) Define directories
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

# ------------------------------
# 4) Template path from --templates_dir
# ------------------------------
template_file_path = os.path.join(templates_dir, 'Template.AC Progress Report.docx')

# ------------------------------
# Helper Functions
# ------------------------------
def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

def docx_to_pdf(docx_path, pdf_dir, retries=3):
    """Convert DOCX to PDF using LibreOffice with retry logic."""
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
                raise FileNotFoundError(f"PDF not found at {output_file}")
        except subprocess.CalledProcessError as e:
            logging.error(f"Error converting {docx_path} to PDF on attempt {attempt + 1}: {e}")
            attempt += 1
            time.sleep(2)
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

def parse_multiple_formats(value, formats=(' %m/%d/%Y','%Y-%m-%d','%m/%d/%y')):
    """Try multiple date formats and return a datetime object or None."""
    if not value or not isinstance(value, str):
        return None
    value = value.strip()
    for fmt in formats:
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            continue
    return None

def fill_placeholders(row):
    """
    Fill placeholders for Anger Control progress notes:
      - Replace 'nan' with ''
      - D1..D30 attendance checkmarks
      - P1..P27, A1..A18 short-date formatting
      - feeproblems if balance != 0
      - fallback last_attended
      - gender placeholders
      - also add today's date as report_date
    """
    # 1) Replace any 'nan' with empty strings
    for col, val in row.items():
        if isinstance(val, str) and val.strip().lower() == 'nan':
            row[col] = ''

    # Convert row to a local context dict
    context = dict(row)

    # Format DOB
    if 'dob' in context and context['dob'].strip():
        date_obj = parse_multiple_formats(context['dob'])
        if date_obj:
            context['dob'] = date_obj.strftime('%m/%d/%Y')

    # Attendance checkmarks R1–R3, C1–C3, S1–S3, O1–O3
    try:
        attended = float(context.get('attended', '0'))
        required = float(context.get('required_sessions', '0'))
        if required > 0:
            # Clear all checkboxes initially
            for section in ['R', 'C', 'S', 'O']:
                for i in range(1, 4):
                    context[f'{section}{i}'] = ''

            # Determine which boxes to check
            if 1 <= attended <= 4:
                context['R1'] = context['C1'] = context['O1'] = context['S1'] = '\u2714'
            elif 5 <= attended <= 13:
                context['R2'] = context['C2'] = context['O2'] = context['S2'] = '\u2714'
            elif 14 <= attended <= 15:
                context['R3'] = context['C3'] = context['O3'] = '\u2714'
                # intentionally mark S2 as well:
                context['S2'] = '\u2714'
            elif 16 <= attended <= 18:
                context['R3'] = context['C3'] = context['O3'] = context['S3'] = '\u2714'
    except ValueError:
        # If invalid attended/required, just skip
        for section in ['R', 'C', 'S', 'O']:
            for i in range(1, 4):
                context[f'{section}{i}'] = ''

    # Attendance checkmarks D1..D30
    attended_str = context.get('attended', '0')
    required_str = context.get('required_sessions', '0')
    try:
        attended_f = float(attended_str)
        required_f = float(required_str)
    except ValueError:
        attended_f, required_f = 0, 0

    # Clear/initialize all Dn fields
    for i in range(1, 31):
        context[f'D{i}'] = ''

    if required_f > 0:
        filled = int((attended_f / required_f) * 30)
        for i in range(1, filled + 1):
            context[f'D{i}'] = '\u2714'

    # Format P1..P27 / A1..A18 as mm/dd/yy
    for i in range(1, 28):
        p_field = f'P{i}'
        if p_field in context and context[p_field].strip():
            date_obj = parse_multiple_formats(context[p_field])
            if date_obj:
                context[p_field] = date_obj.strftime('%m/%d/%y')

    for i in range(1, 19):
        a_field = f'A{i}'
        if a_field in context and context[a_field].strip():
            date_obj = parse_multiple_formats(context[a_field])
            if date_obj:
                context[a_field] = date_obj.strftime('%m/%d/%y')

    # Replace '&' with 'and' in client_note
    if 'client_note' in context and isinstance(context['client_note'], str):
        context['client_note'] = context['client_note'].replace('&', 'and')

    # Fee problems
    if 'balance' in context:
        try:
            balance_f = float(context['balance'])
            context['feeproblems'] = "No" if balance_f == 0 else "Yes"
        except ValueError:
            context['feeproblems'] = "Yes"

    # Fallback last_attended if blank
    if 'orientation_date' in context and 'last_attended' in context:
        if not context['last_attended'].strip():
            context['last_attended'] = context['orientation_date']

    # Gender placeholders
    gender = context.get('gender', '').lower()
    if gender == 'male':
        context.update({
            'gender1': 'his',
            'gender2': 'he',
            'gender3': "Men's",
            'gender4': 'him',
            'gender5': 'He',
            'gender6': 'himself'
        })
    elif gender == 'female':
        context.update({
            'gender1': 'her',
            'gender2': 'she',
            'gender3': "Women's",
            'gender4': 'her',
            'gender5': 'She',
            'gender6': 'herself'
        })
    else:
        logging.error(f"Unrecognized gender: {gender}. Setting placeholders empty.")
        for g in ['gender1','gender2','gender3','gender4','gender5','gender6']:
            context[g] = ''

    # Add today's date as report_date
    now = datetime.now()
    context['report_date'] = f"{now.month}/{now.day}/{now.year}"

    return context

# ------------------------------
# Load & Filter CSV
# ------------------------------
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV: {csv_file_path}")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

# Filter for Anger Control + 'Not Exited'
df = df[(df['program_name'] == 'Anger Control') & (df['exit_reason'] == 'Not Exited')]
if df.empty:
    logging.warning("No records found after filtering for Anger Control Not Exited. No documents will be generated.")
    sys.exit(0)

# Convert all to string
df = df.astype(str)
df.fillna('', inplace=True)

# ------------------------------
# Generating Documents
# ------------------------------
def generate_documents_for_row(row):
    # Fill placeholders
    context = fill_placeholders(row)

    # Load the docx template from the user-defined templates_dir
    doc = DocxTemplate(template_file_path)
    doc.render(context)

    # Build the docx filename
    first_name = sanitize_filename(context.get('first_name', ''))
    last_name = sanitize_filename(context.get('last_name', ''))
    docx_filename = f"{last_name} {first_name} AC PReport.docx"
    docx_path = os.path.join(generated_documents_dir, docx_filename)

    # Save the .docx file
    doc.save(docx_path)
    logging.info(f"Saved docx: {docx_path}")

    # Convert to PDF
    pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
    if pdf_path:
        os.remove(docx_path)  # Remove the .docx after successful conversion

        # Organize by case manager
        case_manager_office = str(context.get('case_manager_office', '')).strip()
        case_manager_first = str(context.get('case_manager_first_name', '')).strip()
        case_manager_last  = str(context.get('case_manager_last_name', '')).strip()

        # If office is missing or "nan", place directly in central_location_path
        if not case_manager_office or case_manager_office.lower() == 'nan':
            office_dir = central_location_path
        else:
            office_dir = os.path.join(
                central_location_path,
                sanitize_filename(case_manager_office)
            )

        # If first name is missing or "nan", omit it from the subfolder name
        if not case_manager_first or case_manager_first.lower() == 'nan':
            manager_subfolder_name = sanitize_filename(case_manager_last)
        else:
            manager_subfolder_name = f"{sanitize_filename(case_manager_first)} {sanitize_filename(case_manager_last)}"

        manager_subfolder_name = manager_subfolder_name.strip()
        manager_dir = os.path.join(office_dir, manager_subfolder_name)
        os.makedirs(manager_dir, exist_ok=True)

        # Move the PDF to the correct directory
        final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, final_pdf_path)
        logging.info(f"Moved {pdf_path} to {final_pdf_path}")
    else:
        logging.error("PDF conversion failed; no document moved.")

# Main loop
try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("AC Progress Reports have been generated and organized.")
print("AC Progress Reports have been generated and organized.")

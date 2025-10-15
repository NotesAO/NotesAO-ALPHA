import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
import sys
from datetime import datetime
import argparse
import shutil
import time
import subprocess

# Parse command-line arguments
parser = argparse.ArgumentParser(description='Generate SOP Unexcused Absences Documents')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

# Assign arguments to variables
csv_file_path = args.csv_file
start_date_str = args.start_date
end_date_str = args.end_date
output_dir = args.output_dir
clinic_folder = args.clinic_folder

# Set up logging
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'SOP_Unexcused_Absences_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("SOP Unexcused Absences Script started.")

base_dir = os.path.dirname(os.path.abspath(__file__))
templates_dir = args.templates_dir

# Determine output directories
today = datetime.now().strftime('%m.%d.%y')
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

# Template path
template_file_path = os.path.join(templates_dir, 'Template.SOP Unexcused Absence.docx')

# Convert start_date and end_date to datetime
try:
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
    end_date = datetime.strptime(end_date_str, '%Y-%m-%d')
except ValueError as e:
    logging.error(f"Invalid date format for start_date or end_date: {e}")
    sys.exit(1)

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
            time.sleep(2)
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

def format_date(value, formats=('%m/%d/%Y', '%Y-%m-%d')):
    if not isinstance(value, str) or not value.strip():
        return value
    for fmt in formats:
        try:
            date_obj = datetime.strptime(value.strip(), fmt)
            return date_obj.strftime('%m/%d/%Y')
        except ValueError:
            continue
    return value

def get_absence_label(absence_unexcused):
    try:
        absence_unexcused = int(float(absence_unexcused))
    except (ValueError, TypeError):
        logging.warning(f"Invalid absence_unexcused value: {absence_unexcused}")
        return "absences"
    return "absence" if absence_unexcused == 1 else "absences"

def fill_placeholders(row):
    gender_mapping = {
        'male': {'gender1': 'his', 'gender2': 'he', 'gender3': "Men's", 'gender4': "Mr."},
        'female': {'gender1': 'her', 'gender2': 'she', 'gender3': "Women's", 'gender4': "Ms."},
    }

    gender = row.get('gender', '').lower()
    if gender in gender_mapping:
        row.update(gender_mapping[gender])
    else:
        logging.error(f"Gender '{gender}' not found in gender_mapping.")

    # Format any _date fields
    for placeholder, value in row.items():
        if placeholder.endswith('_date') and isinstance(value, str) and value.strip():
            row[placeholder] = format_date(value)

    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    if 'dob' in row and isinstance(row['dob'], str) and row['dob'].strip():
        row['dob'] = format_date(row['dob'])

    if 'absence_unexcused' in row and row['absence_unexcused'].strip():
        row['absence_label'] = get_absence_label(row['absence_unexcused'])

    return row

def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

def format_absences(row):
    absences = []
    for i in range(1, 29):
        col = f'A{i}'
        if col in row and isinstance(row[col], str) and row[col].strip():
            val = row[col].strip()
            parsed_date = None
            for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
                try:
                    parsed_date = datetime.strptime(val, fmt)
                    break
                except ValueError:
                    continue
            if parsed_date:
                formatted_date = parsed_date.strftime('%m/%d/%y').lstrip('0').replace('/0', '/')
                absences.append(formatted_date)
            else:
                logging.debug(f"Unrecognized date format for {col}: {val}")

    if len(absences) > 2:
        return ', '.join(absences[:-1]) + ', &amp; ' + absences[-1]
    elif len(absences) == 2:
        return absences[0] + ' &amp; ' + absences[1]
    elif len(absences) == 1:
        return absences[0]
    else:
        return ''

# Load CSV file
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

# Filter by program and exit reason
df = df[df['program_name'].isin(['SOP'])]
df = df[df['exit_reason'] == 'Not Exited']

if 'last_absence' not in df.columns:
    logging.error("'last_absence' column not found in the DataFrame.")
    sys.exit(1)

df['last_absence'] = pd.to_datetime(df['last_absence'], errors='coerce')
df = df.dropna(subset=['last_absence'])

# Filter by date range
df = df[(df['last_absence'] >= start_date) & (df['last_absence'] <= end_date)]
logging.debug(f"Filtered DataFrame by date range. Number of records: {len(df)}")

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

df = df.sort_values(by='last_absence', ascending=False)
df['last_absence_str'] = df['last_absence'].dt.strftime('%m/%d/%Y')

df.fillna('', inplace=True)
df = df.astype(str)

# Apply placeholders
try:
    df = df.apply(fill_placeholders, axis=1)
except Exception as e:
    logging.error(f"Error during DataFrame processing: {e}")
    sys.exit(1)

df['absences'] = df.apply(format_absences, axis=1)

def generate_documents(row, output_dir_path):
    context = row.to_dict()
    context['last_absence'] = context.get('last_absence_str', '')
    context.pop('last_absence_str', None)

    gender = context.get('gender', '').lower()
    if gender == 'male':
        context['gender1'] = 'his'
        context['gender2'] = 'he'
        context['gender3'] = "Men's"
        context['gender4'] = "Mr."
    elif gender == 'female':
        context['gender1'] = 'her'
        context['gender2'] = 'she'
        context['gender3'] = "Women's"
        context['gender4'] = "Ms."
    else:
        logging.error(f"Gender '{gender}' not recognized.")

    try:
        doc = DocxTemplate(template_file_path)
        doc.render(context)

        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} SOP Unexcused Absence.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)
        time.sleep(1)
        logging.info(f"Document saved successfully at: {docx_path}")

        # Convert DOCX to PDF
        pdf_converted_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_converted_path:
            os.remove(docx_path)
        else:
            logging.error(f"PDF conversion failed for: {docx_path}")

        # Organize PDFs by case manager
        case_manager_office = sanitize_filename(row.get('case_manager_office', 'Unknown Office'))
        case_manager_first_name = sanitize_filename(row.get('case_manager_first_name', ''))
        case_manager_last_name = sanitize_filename(row.get('case_manager_last_name', ''))

        office_dir = os.path.join(central_location_path, case_manager_office)
        manager_dir = os.path.join(office_dir, f"{case_manager_first_name} {case_manager_last_name}")
        os.makedirs(manager_dir, exist_ok=True)

        if pdf_converted_path and os.path.exists(pdf_converted_path):
            shutil.move(pdf_converted_path, os.path.join(manager_dir, os.path.basename(pdf_converted_path)))
            logging.info(f"Moved {pdf_converted_path} to {manager_dir}")
        else:
            logging.warning("PDF file not found or not generated.")
    except Exception as e:
        logging.error(f"Error processing document for {context.get('first_name', '')} {context.get('last_name', '')}: {e}")

try:
    for _, row in df.iterrows():
        generate_documents(row, central_location_path)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("SOP Unexcused Absences Script completed successfully.")

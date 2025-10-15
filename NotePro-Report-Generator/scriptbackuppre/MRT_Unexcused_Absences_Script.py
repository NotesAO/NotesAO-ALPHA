#!/usr/bin/env python3
# MRT_Unexcused_Absences_Script.py

import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime
import time
import argparse
import shutil
import subprocess
from io import BytesIO

##############################################################################
# 1) Parse command-line arguments
##############################################################################
parser = argparse.ArgumentParser(description='Generate MRT Unexcused Absences Notifications')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--output_dir', required=False, help='Optional path to the output directory')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

csv_file_path = args.csv_file
start_date_str = args.start_date
end_date_str = args.end_date
clinic_folder = args.clinic_folder
output_dir = args.output_dir
templates_dir = args.templates_dir

##############################################################################
# 2) Set up logging
##############################################################################
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'MRT_Unexcused_Absences_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("MRT Unexcused Absences Script started.")

##############################################################################
# 3) Convert start and end dates
##############################################################################
try:
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
    end_date = datetime.strptime(end_date_str, '%Y-%m-%d')
except ValueError as e:
    logging.error(f"Invalid date format for start_date or end_date: {e}")
    exit(1)

##############################################################################
# 4) Define directories
##############################################################################
base_dir = os.path.dirname(os.path.abspath(__file__))
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

##############################################################################
# 5) Template path (using templates_dir)
##############################################################################
template_file_path = os.path.join(templates_dir, 'Template.MRT Unexcused Absence.docx')

##############################################################################
# 6) Helper Functions
##############################################################################
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
            subprocess.run(['libreoffice', '--headless', '--convert-to', 'pdf',
                            '--outdir', pdf_dir, docx_path], check=True)
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

def parse_date_mdy(date_str):
    """
    Attempt to parse date_str in formats like 'YYYY-MM-DD' or 'mm/dd/yyyy'
    returning a zero-padded mm/dd/yyyy. If it fails, returns original.
    """
    if not date_str or not isinstance(date_str, str):
        return date_str
    date_str = date_str.strip()
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y'):
        try:
            dt = datetime.strptime(date_str, fmt)
            return dt.strftime('%m/%d/%Y')  # e.g. "02/14/2025"
        except ValueError:
            continue
    return date_str  # fallback if parsing fails

def fill_placeholders(row):
    """
    Handle gender placeholders, date fields, remove 'nan', etc.
    """
    # Clean up 'nan' or 'NaN' text
    for k, v in row.items():
        if isinstance(v, str) and v.strip().lower() == 'nan':
            row[k] = ''

    # Gender placeholders
    gender_map = {
        'male':   {'gender1': 'his', 'gender2': 'he',   'gender3': "Men's"},
        'female': {'gender1': 'her', 'gender2': 'she',  'gender3': "Women's"}
    }
    gender = row.get('gender', '').lower()
    if gender in gender_map:
        row.update(gender_map[gender])
    else:
        logging.error(f"Gender '{gender}' not recognized; placeholders set to blank.")
        row['gender1'] = ''
        row['gender2'] = ''
        row['gender3'] = ''

    # Reformat any fields named something_date as mm/dd/yyyy
    for placeholder, value in row.items():
        if placeholder.endswith('_date'):
            row[placeholder] = parse_date_mdy(value)

    # Also convert last_absence if present
    if 'last_absence' in row:
        row['last_absence'] = parse_date_mdy(row['last_absence'])

    # Optionally add 'report_date' (today) for consistency
    row['report_date'] = datetime.now().strftime('%m/%d/%Y')

    if 'dob' in row: 
        row['dob'] = parse_date_mdy(row['dob'])

    return row

def format_absences(row):
    """
    Combine unexcused absence columns (A1..A28) into a single string,
    each date parsed as mm/dd/yyyy, with '&' before the last one.
    """
    absences = []
    for i in range(1, 29):
        col = f'A{i}'
        if col in row and isinstance(row[col], str) and row[col].strip():
            date_str = parse_date_mdy(row[col].strip())
            if date_str:
                absences.append(date_str)

    if len(absences) > 2:
        return ', '.join(absences[:-1]) + ', &amp; ' + absences[-1]
    elif len(absences) == 2:
        return absences[0] + ' &amp; ' + absences[1]
    elif len(absences) == 1:
        return absences[0]
    else:
        return ''

##############################################################################
# 7) Load CSV
##############################################################################
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV: {csv_file_path}")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

df.columns = df.columns.str.strip()

##############################################################################
# 8) Filter data
##############################################################################
# Must be MRT + Not Exited
df = df[(df['program_name'] == 'MRT') & (df['exit_reason'] == 'Not Exited')]

# Convert last_absence to datetime; filter by date range
df['last_absence'] = pd.to_datetime(df['last_absence'], errors='coerce')
df = df.dropna(subset=['last_absence'])
df = df[(df['last_absence'] >= start_date) & (df['last_absence'] <= end_date)]

if df.empty:
    message = "MRT Unexcused Absences Script completed: No absences found within the given date range."
    logging.info(message)
    print(message)
    sys.exit(0)


# Sort by last_absence
df = df.sort_values(by='last_absence')

##############################################################################
# 9) Apply placeholders and format absences
##############################################################################
df = df.astype(str)
df = df.apply(fill_placeholders, axis=1)
df.fillna('', inplace=True)

df['absences_formatted'] = df.apply(format_absences, axis=1)

##############################################################################
# 10) Generate documents
##############################################################################
def generate_documents_for_row(row):
    context = row.to_dict()

    # absence_label = "absence" or "absences"
    try:
        absence_count = int(context.get('absence_unexcused', '0'))
    except ValueError:
        absence_count = 0

    context['absence_label'] = "absence" if absence_count == 1 else "absences"

    if context['absences_formatted']:
        context['absences'] = context['absences_formatted']
    else:
        context['absences'] = "No absences recorded"

    # Render doc
    doc = DocxTemplate(template_file_path)
    try:
        doc.render(context)
    except Exception as e:
        logging.error(f"Error rendering doc for {context.get('first_name', '')} {context.get('last_name', '')}: {e}")
        return

    first_name = sanitize_filename(context.get('first_name', ''))
    last_name = sanitize_filename(context.get('last_name', ''))
    docx_filename = f"{last_name} {first_name} MRT Unexcused Absence.docx"
    docx_path = os.path.join(generated_documents_dir, docx_filename)

    try:
        doc.save(docx_path)
        logging.info(f"Document saved: {docx_path}")

        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)
            # Organize by case manager
            office = sanitize_filename(context.get('case_manager_office', 'Unknown Office'))
            mgr_first = sanitize_filename(context.get('case_manager_first_name', ''))
            mgr_last  = sanitize_filename(context.get('case_manager_last_name', ''))

            office_dir = os.path.join(central_location_path, office)
            os.makedirs(office_dir, exist_ok=True)

            manager_dir = os.path.join(office_dir, f"{mgr_first} {mgr_last}".strip())
            os.makedirs(manager_dir, exist_ok=True)

            final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
            shutil.move(pdf_path, final_pdf_path)
            logging.info(f"Moved {pdf_path} → {final_pdf_path}")
        else:
            logging.error(f"PDF conversion failed for {docx_path}, not moved.")
    except Exception as e:
        logging.error(f"Error processing doc for {context.get('first_name', '')} {context.get('last_name', '')}: {e}")

try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("MRT Unexcused Absence documents have been generated and organized.")
print("MRT Unexcused Absence documents have been generated and organized.")

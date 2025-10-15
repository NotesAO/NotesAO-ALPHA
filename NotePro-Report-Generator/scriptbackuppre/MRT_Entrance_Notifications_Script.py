#!/usr/bin/env python3
# MRT_Entrance_Notifications_Script.py

import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime
import time
import argparse
import shutil
import subprocess
import sys

##############################################################################
# 1) Parse command-line arguments
##############################################################################
parser = argparse.ArgumentParser(description='Generate MRT Entrance Notifications')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date (YYYY-MM-DD)')
parser.add_argument('--end_date', required=True, help='End date (YYYY-MM-DD)')
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
    filename=os.path.join(log_dir, f'MRT_Entrance_Notifications_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("MRT Entrance Notifications Script started.")

##############################################################################
# 3) Convert start/end dates
##############################################################################
try:
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
    end_date = datetime.strptime(end_date_str, '%Y-%m-%d')
except ValueError as e:
    logging.error(f"Invalid date format for start_date or end_date: {e}")
    sys.exit(1)

##############################################################################
# 4) Define directories
##############################################################################
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

##############################################################################
# 5) Template path (now uses templates_dir)
##############################################################################
template_path = os.path.join(templates_dir, 'Template.MRT Entrance Notification.docx')

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
            subprocess.run(
                ['libreoffice', '--headless', '--convert-to', 'pdf', '--outdir', pdf_dir, docx_path],
                check=True
            )
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
    Attempt to parse a date string in formats like 'YYYY-MM-DD' or 'mm/dd/yyyy'
    returning a zero-padded mm/dd/yyyy string. If it fails, returns original.
    """
    if not date_str or not isinstance(date_str, str):
        return date_str
    date_str = date_str.strip()
    # Try common patterns
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y'):
        try:
            dt = datetime.strptime(date_str, fmt)
            return dt.strftime('%m/%d/%Y')  # e.g. "02/14/2025"
        except ValueError:
            continue
    return date_str  # fallback if not parsed

def fill_placeholders(row):
    """
    Fill placeholders (gender, date fields, remove 'nan').
    This script does not handle progress because it's just an entrance notice.
    """
    # Clean up 'nan' or 'NaN'
    for k, v in row.items():
        if isinstance(v, str) and v.strip().lower() == 'nan':
            row[k] = ''

    # Gender placeholders
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
        logging.warning(f"Unrecognized gender '{gender}', placeholders set to blank.")
        row['gender1'] = ''
        row['gender2'] = ''
        row['gender3'] = ''

    # Reformat date fields
    for date_field in ['orientation_date', 'dob']:
        if date_field in row:
            row[date_field] = parse_date_mdy(row[date_field])

    # If you want an official "report_date" in mm/dd/yyyy
    # (this is optional, but consistent with your other scripts):
    row['report_date'] = datetime.now().strftime('%m/%d/%Y')

    # If you have a client_note or other text fields with ampersands
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    return row

##############################################################################
# 7) Load & Filter CSV
##############################################################################
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV: {csv_file_path}")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

# Convert orientation_date to datetime for filtering
df['orientation_date'] = pd.to_datetime(df['orientation_date'], errors='coerce')

# Filter: MRT + 'Not Exited' + orientation_date in [start_date, end_date]
df = df[(df['program_name'] == 'MRT') & (df['exit_reason'] == 'Not Exited')]
df = df[(df['orientation_date'] >= start_date) & (df['orientation_date'] <= end_date)]

# Sort by orientation_date ascending (optional)
df = df.sort_values(by='orientation_date', ascending=True)

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

# Convert everything to string, fill placeholders
df = df.astype(str)
df = df.apply(fill_placeholders, axis=1)
df.fillna('', inplace=True)

##############################################################################
# 8) Document generation per row
##############################################################################
def generate_documents_for_row(row):
    """
    Generate a single PDF per row: MRT Entrance Notification.
    Organize the PDF in the standard case_manager folder structure.
    """
    context = row.to_dict()

    # Render the doc
    try:
        doc = DocxTemplate(template_path)
        doc.render(context)
    except Exception as e:
        first_name = context.get('first_name', '')
        last_name = context.get('last_name', '')
        logging.error(f"Error rendering doc for {first_name} {last_name}: {e}")
        return

    # Save .docx, convert to PDF
    first_name = sanitize_filename(context.get('first_name', ''))
    last_name = sanitize_filename(context.get('last_name', ''))
    docx_filename = f"{last_name} {first_name} MRT Entrance.docx"
    docx_path = os.path.join(generated_documents_dir, docx_filename)

    try:
        doc.save(docx_path)
        logging.info(f"Saved docx: {docx_path}")

        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            # Remove DOCX if PDF conversion was successful
            os.remove(docx_path)

            # Organize by case manager
            office = sanitize_filename(context.get('case_manager_office', '').strip())
            if not office:
                # If no office specified, place in the top-level folder for today's date
                office_dir = central_location_path
            else:
                office_dir = os.path.join(central_location_path, office)

            mgr_first = sanitize_filename(context.get('case_manager_first_name', '').strip())
            mgr_last = sanitize_filename(context.get('case_manager_last_name', '').strip())

            # If manager first is missing, just use last
            if not mgr_first:
                manager_subfolder = mgr_last
            else:
                manager_subfolder = f"{mgr_first} {mgr_last}".strip()

            manager_dir = os.path.join(office_dir, manager_subfolder)
            os.makedirs(manager_dir, exist_ok=True)

            final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
            shutil.move(pdf_path, final_pdf_path)
            logging.info(f"Moved PDF to: {final_pdf_path}")
        else:
            logging.error(f"PDF conversion failed for {docx_path}; no document moved.")
    except Exception as e:
        logging.error(f"Error creating or moving MRT Entrance doc for row: {e}")

##############################################################################
# 9) Main loop
##############################################################################
try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("MRT Entrance Notifications have been generated and organized.")
print("MRT Entrance Notifications have been generated and organized.")

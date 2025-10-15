# DWIE_Enrollment_Letters_Script.py
import pandas as pd
from docxtpl import DocxTemplate
import logging
import os
from datetime import datetime, timedelta
import argparse
import shutil
import subprocess
import time
import sys
import re

# Configure logging
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s [%(levelname)s] %(message)s')

# -------- Args (kept identical shape for generator compatibility) --------
parser = argparse.ArgumentParser(description='Generate Enrollment Letters (program-agnostic)')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=True, help='Start date in YYYY-MM-DD format')
parser.add_argument('--end_date', required=True, help='End date in YYYY-MM-DD format')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

# -------- Assign / paths --------
csv_file_path = args.csv_file
start_date = datetime.strptime(args.start_date, '%Y-%m-%d')
end_date   = datetime.strptime(args.end_date,   '%Y-%m-%d')
clinic_folder = args.clinic_folder
output_dir = args.output_dir

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

today_folder = datetime.now().strftime('%m.%d.%y')

# Work dir for generation
if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today_folder)
os.makedirs(generated_documents_dir, exist_ok=True)

# Public “central” location (PDFs sorted by case manager)
public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)
central_location_path = os.path.join(public_generated_documents_dir, today_folder)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory: {central_location_path}")

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


# -------- Template resolution (first existing wins) --------
CANDIDATE_TEMPLATES = [
    'Template.Enrollment Letter.docx',
    'Template.DWIE Enrollment Letter.docx',
    'Template.DWIE Enroll.docx',
    'Template.DWIE Entrance Notification.docx',  # last-resort fallback
]
# --- template resolution (clinic/default fallbacks) ---
template_path = None
for name in ('Template.DWIE Enrollment Letter.docx', 'Template.Enrollment Letter.docx'):
    p = resolve_template(_TEMPLATES_ROOT, clinic_folder, program_code, name)
    if p:
        template_path = p
        break
if not template_path:
    # soft-fail: keep running but log an error so errors.txt is populated
    record_error(f"[FATAL] Missing any DWIE enrollment template under clinic/default fallbacks in {_TEMPLATES_ROOT}. Tried: Template.DWIE Enrollment Letter.docx, Template.Enrollment Letter.docx")

logging.info(f"Using template: {template_path}")

# -------- Small helpers --------
def format_date_any(value, formats=None, out='%m/%d/%Y'):
    if value is None or value == '':
        return ''
    if isinstance(value, (datetime, )):
        return value.strftime(out)
    if formats is None:
        formats = ['%Y-%m-%d', '%m/%d/%Y', '%m-%d-%Y', '%Y/%m/%d', '%Y-%m-%d %H:%M:%S']
    s = str(value).strip()
    for fmt in formats:
        try:
            return datetime.strptime(s, fmt).strftime(out)
        except ValueError:
            continue
    return s  # leave as-is if unrecognized

def parse_dt_any(value):
    if value is None or value == '':
        return None
    if isinstance(value, datetime):
        return value
    s = str(value).strip()
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m-%d-%Y', '%Y/%m/%d', '%Y-%m-%d %H:%M:%S'):
        try:
            return datetime.strptime(s, fmt)
        except ValueError:
            continue
    return None

def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for c in invalid_chars:
        filename = filename.replace(c, '')
    return filename.strip()

def docx_to_pdf(docx_path, pdf_dir, retries=3):
    """Convert DOCX to PDF using LibreOffice with retry logic."""
    attempt = 0
    while attempt < retries:
        try:
            out_pdf = os.path.join(pdf_dir, os.path.basename(docx_path).replace('.docx', '.pdf'))
            subprocess.run(['libreoffice', '--headless', '--convert-to', 'pdf', '--outdir', pdf_dir, docx_path], check=True)
            if os.path.exists(out_pdf):
                logging.info(f"Converted to PDF: {out_pdf} (attempt {attempt+1})")
                return out_pdf
            else:
                raise FileNotFoundError(f"Expected PDF not found at: {out_pdf}")
        except subprocess.CalledProcessError as e:
            logging.error(f"LibreOffice conversion error (attempt {attempt+1}): {e}")
            attempt += 1
            time.sleep(1)
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

# -------- Load CSV --------
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} rows from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

# ---- Column normalization / presence checks ----
# We expect: first_name, last_name, date_of_birth (or dob), enrollment_date,
#            case_manager_office, case_manager_first_name, case_manager_last_name
missing = []

if 'enrollment_date' not in df.columns:
    missing.append('enrollment_date')
if 'first_name' not in df.columns:
    missing.append('first_name')
if 'last_name' not in df.columns:
    missing.append('last_name')
if ('date_of_birth' not in df.columns) and ('dob' not in df.columns):
    missing.append('date_of_birth (or dob)')

for cm_col in ['case_manager_office', 'case_manager_first_name', 'case_manager_last_name']:
    if cm_col not in df.columns:
        missing.append(cm_col)

if missing:
    logging.error(f"CSV is missing required columns for Enrollment Letters: {missing}")
    sys.exit(1)

# Normalize DOB column to a single 'dob'
if 'dob' not in df.columns and 'date_of_birth' in df.columns:
    df['dob'] = df['date_of_birth']

# Parse enrollment_date into datetime for filtering
df['enrollment_date_dt'] = df['enrollment_date'].apply(lambda x: parse_dt_any(x) if pd.notnull(x) else None)

# Drop rows with no enrollment_date
before = len(df)
df = df[df['enrollment_date_dt'].notnull()]
logging.debug(f"Rows without enrollment_date removed: {before - len(df)} (remaining {len(df)})")

# ---- Program-agnostic: NO filtering by program_name ----

# Filter by date range on enrollment_date_dt
df_filtered = df[(df['enrollment_date_dt'] >= start_date) & (df['enrollment_date_dt'] <= end_date)]
logging.info(f"Filtered to date range [{start_date:%Y-%m-%d} .. {end_date:%Y-%m-%d}]. Rows: {len(df_filtered)}")

if df_filtered.empty:
    logging.warning("No rows to generate after date filtering on enrollment_date. Exiting.")
    sys.exit(0)

# Sort newest first (enrollment_date descending)
df_filtered = df_filtered.sort_values(by='enrollment_date_dt', ascending=False).copy()

# -------- Render letters --------
generated_docx_files = []
today_mmddyyyy = datetime.now().strftime('%m/%d/%Y')

for idx, row in df_filtered.iterrows():
    try:
        if not template_path:
            continue
        # Build context for template (keep names used across your other scripts)
        context = {}

        # Required fields
        context['first_name']  = str(row.get('first_name', '') or '').strip()
        context['last_name']   = str(row.get('last_name',  '') or '').strip()
        context['dob']         = format_date_any(row.get('dob', ''))
        # Provide both today_date and enrollment_date for template flexibility
        context['today_date']        = today_mmddyyyy
        context['enrollment_date']   = format_date_any(row.get('enrollment_date', ''))
        # Case manager identity / office
        cm_first = str(row.get('case_manager_first_name', '') or '').strip()
        cm_last  = str(row.get('case_manager_last_name',  '') or '').strip()
        context['case_manager_first_name'] = cm_first
        context['case_manager_last_name']  = cm_last
        context['case_manager_full']       = f"{cm_first} {cm_last}".strip()
        context['case_manager_office']     = str(row.get('case_manager_office', '') or '').strip()

        # Optional carry-through fields if your template wants them
        context['program_name'] = str(row.get('program_name', '') or '').strip()

        # Render
        doc = DocxTemplate(template_path)
        doc.render(context)

        # Filename
        first_name = sanitize_filename(context['first_name'])
        last_name  = sanitize_filename(context['last_name'])
        # Keep neutral (program-agnostic) filename
        docx_filename = f"{last_name} {first_name} Enrollment Letter.docx".strip()
        docx_path = os.path.join(generated_documents_dir, docx_filename)
        doc.save(docx_path)
        logging.info(f"Generated DOCX: {docx_path}")

        generated_docx_files.append((docx_path, row))
    except Exception as e:
        logging.error(f"Error rendering row index {idx}: {e}")

# -------- Convert to PDF and file into central location --------
for docx_path, row in generated_docx_files:
    try:
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        # Remove DOCX after successful conversion
        if pdf_path:
            try:
                os.remove(docx_path)
            except Exception:
                pass

            cm_office = sanitize_filename(str(row.get('case_manager_office', '') or ''))
            cm_first  = sanitize_filename(str(row.get('case_manager_first_name', '') or ''))
            cm_last   = sanitize_filename(str(row.get('case_manager_last_name',  '') or ''))
            cm_full   = (cm_first + ' ' + cm_last).strip() or 'Unknown_Manager'

            office_dir  = os.path.join(central_location_path, cm_office or 'Unknown_Office')
            manager_dir = os.path.join(office_dir, cm_full)
            os.makedirs(manager_dir, exist_ok=True)

            final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
            shutil.move(pdf_path, final_pdf_path)
            logging.info(f"Moved PDF → {final_pdf_path}")
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
    except Exception as e:
        logging.error(f"Error converting/moving output for {docx_path}: {e}")

# -------- Final sanity --------
leftover_docx = [f for f in os.listdir(generated_documents_dir) if f.lower().endswith('.docx')]
if leftover_docx:
    logging.error(f"Unconverted DOCX files remain: {leftover_docx}")
else:
    logging.info("All enrollment letters generated, converted, and organized successfully.")

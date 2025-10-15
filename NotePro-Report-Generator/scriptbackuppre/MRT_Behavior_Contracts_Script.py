import pandas as pd
from docxtpl import DocxTemplate, InlineImage
from docx.shared import Inches
import logging
import requests
from io import BytesIO
import os
import sys
from datetime import datetime
from PIL import Image
import argparse
import shutil
import subprocess
from docx.enum.table import WD_TABLE_ALIGNMENT
import time

# ------------------------------------------------------------------------------
# 1) Parse CLI arguments
# ------------------------------------------------------------------------------
parser = argparse.ArgumentParser(description='Generate Behavior Contracts for MRT Clients')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='(Optional) Start date for the reports')
parser.add_argument('--end_date', required=False, help='(Optional) End date for the reports')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

csv_file_path  = args.csv_file
output_dir     = args.output_dir
clinic_folder  = args.clinic_folder
templates_dir  = args.templates_dir

# ------------------------------------------------------------------------------
# 2) Set up Logging
# ------------------------------------------------------------------------------
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'MRT_Behavior_Contract_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("MRT Behavior Contract Script started.")

# ------------------------------------------------------------------------------
# 3) Determine Output Directories
# ------------------------------------------------------------------------------
base_dir = os.path.dirname(os.path.abspath(__file__))

today = datetime.now().strftime('%m.%d.%y')
if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today)

os.makedirs(generated_documents_dir, exist_ok=True)

# Also create a "public" location so you can easily retrieve the generated docs
public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)

central_location_path = os.path.join(public_generated_documents_dir, today)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory created: {central_location_path}")

# ------------------------------------------------------------------------------
# 4) Paths to Behavior Contract Template
# ------------------------------------------------------------------------------
behavior_contract_template_path = os.path.join(templates_dir, 'Template.MRT Behavior Contract.docx')

# ------------------------------------------------------------------------------
# 5) Helper Functions
# ------------------------------------------------------------------------------
def docx_to_pdf(docx_path, pdf_dir, retries=3):
    """
    Convert the given .docx to .pdf using LibreOffice in headless mode.
    Retries a few times if needed.
    """
    attempt = 0
    while attempt < retries:
        try:
            output_file = os.path.join(pdf_dir, os.path.basename(docx_path).replace('.docx', '.pdf'))
            subprocess.run(
                ['libreoffice', '--headless', '--convert-to', 'pdf', '--outdir', pdf_dir, docx_path],
                check=True
            )
            if os.path.exists(output_file):
                logging.info(f"Converted {docx_path} to PDF successfully (attempt {attempt+1}).")
                return output_file
            else:
                raise FileNotFoundError(f"PDF not found at path: {output_file}")
        except subprocess.CalledProcessError as e:
            logging.error(f"Error converting {docx_path} to PDF (attempt {attempt+1}): {e}")
            attempt += 1
            time.sleep(2)
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

def align_tables_left(doc):
    """Align all tables in the Word doc to the left."""
    for table in doc.tables:
        table.alignment = WD_TABLE_ALIGNMENT.LEFT

def fetch_image(url, doc, max_width=3.45, max_height=4.71, margin=0.2):
    """
    Download an image from `url`, resize to fit the doc if needed, return InlineImage.
    """
    headers = {
        'User-Agent': 'Mozilla/5.0',
        'Accept': 'image/*'
    }
    try:
        response = requests.get(url, headers=headers, timeout=30)
        if response.status_code == 200 and 'image' in response.headers.get('Content-Type', ''):
            img = Image.open(BytesIO(response.content))

            img_width, img_height = img.size
            aspect_ratio = img_width / img_height

            adjusted_max_width = max_width - margin
            adjusted_max_height = max_height - margin

            if aspect_ratio > 1:
                new_width = min(adjusted_max_width, img_width)
                new_height = new_width / aspect_ratio
                if new_height > adjusted_max_height:
                    new_height = adjusted_max_height
                    new_width = new_height * aspect_ratio
            else:
                new_height = min(adjusted_max_height, img_height)
                new_width = new_height * aspect_ratio
                if new_width > adjusted_max_width:
                    new_width = adjusted_max_width
                    new_height = new_width / aspect_ratio

            # Convert inches to "pixels" at 300 dpi
            img = img.resize((int(new_width * 300), int(new_height * 300)), Image.LANCZOS)
            img_byte_arr = BytesIO()
            img.save(img_byte_arr, format='JPEG')

            return InlineImage(doc, BytesIO(img_byte_arr.getvalue()), width=Inches(new_width), height=Inches(new_height))
        else:
            logging.warning(f"Failed to fetch image or non-image content returned: {url}")
            return None
    except requests.RequestException as e:
        logging.warning(f"Request failed for URL {url} with error: {e}")
        return None
    except Exception as e:
        logging.error(f"Unexpected error fetching image: {e}")
        return None

def sanitize_filename(filename):
    """Remove invalid filename characters."""
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

def convert_yn_to_yes_no(value):
    """Optional: if your CSV has Y/N fields you want to interpret as Yes/No."""
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

def parse_and_format_date(date_str):
    """
    Attempt to parse date_str in known formats and return 'm/d/yyyy'.
    If invalid, return ''.
    """
    if not date_str or not isinstance(date_str, str):
        return ''
    date_str = date_str.strip()
    if not date_str:
        return ''
    for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
        try:
            d = datetime.strptime(date_str, fmt)
            return f"{d.month}/{d.day}/{d.year}"
        except ValueError:
            continue
    return date_str  # fallback unparsed

# ------------------------------------------------------------------------------
# 6) Read the CSV
# ------------------------------------------------------------------------------
try:
    # Force all columns to string
    df = pd.read_csv(csv_file_path, dtype=str)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

df.fillna('', inplace=True)

# If you have any Y/N fields to convert:
for col in df.columns:
    df[col] = df[col].apply(convert_yn_to_yes_no)

# ------------------------------------------------------------------------------
# 7) Filter to MRT + Behavior Contract Status
# ------------------------------------------------------------------------------
# Must be MRT (male or female). Adjust if your CSV uses different strings.
mrt_filter = df['program_name'].isin(['MRT'])

# behavior_contract_status must be "Needed" or "Signed"
bc_filter = df['behavior_contract_status'].isin(['Needed', 'Signed'])

df = df[mrt_filter & bc_filter]

if df.empty:
    logging.warning("No records found matching MRT + Behavior Contract 'Needed' or 'Signed'. Exiting.")
    sys.exit(0)

logging.info(f"Filtered down to {len(df)} records needing Behavior Contracts.")

# ------------------------------------------------------------------------------
# 8) Prepare Data / Placeholders
# ------------------------------------------------------------------------------
def fill_placeholders(row):
    """
    If you need to fill additional placeholders, handle them here.
    For instance, parse and format date_of_birth or orientation_date, etc.
    """
    row_dict = row.to_dict()

    # Format 'report_date' as today's date if needed
    now = datetime.now()
    row_dict['report_date'] = f"{now.month}/{now.day}/{now.year}"

    # Possibly parse and format these fields if present
    for date_field in ['dob', 'orientation_date']:
        if date_field in row_dict:
            row_dict[date_field] = parse_and_format_date(row_dict[date_field])

    # If you have a 'fee' that you'd like to format with a dollar sign
    if 'fee' in row_dict and row_dict['fee']:
        try:
            row_dict['fee'] = f"${float(row_dict['fee']):.2f}"
        except ValueError:
            row_dict['fee'] = '$0.00'

    return row_dict

# ------------------------------------------------------------------------------
# 9) Render & Save Doc
# ------------------------------------------------------------------------------
def render_and_save_behavior_contract(row_data, output_dir_path):
    """
    1. Load the docx template
    2. Insert image if 'image_url' is present
    3. Render placeholders
    4. Save docx
    5. Convert to PDF
    6. Move to the appropriate subfolder (case manager, etc.)
    """
    try:
        doc = DocxTemplate(behavior_contract_template_path)
        context = fill_placeholders(row_data)  # build placeholders

        # Insert image
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc)
            context['image'] = image if image else ''

        doc.render(context)
        align_tables_left(doc)

        # Build docx filename
        first_name = sanitize_filename(context.get('first_name', ''))
        last_name  = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} Behavior Contract.docx"
        docx_path = os.path.join(output_dir_path, docx_filename)

        doc.save(docx_path)
        time.sleep(1)

        # Convert to PDF
        pdf_path = docx_to_pdf(docx_path, output_dir_path)
        if pdf_path:
            os.remove(docx_path)  # remove the docx once PDF is done
            return pdf_path
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
            return None

    except Exception as e:
        logging.error(f"Error rendering Behavior Contract: {e}")
        raise

# ------------------------------------------------------------------------------
# 10) Generate Contracts & Organize
# ------------------------------------------------------------------------------
def generate_behavior_contracts(row, output_dir_path):
    """
    Actually generate the Word/PDF, then move into subfolders if needed.
    """
    pdf_path = render_and_save_behavior_contract(row, output_dir_path)

    # You might want to sort them by case manager office
    case_manager_office = sanitize_filename(row.get('case_manager_office', 'UnknownOffice'))
    cm_first = sanitize_filename(row.get('case_manager_first_name', ''))
    cm_last  = sanitize_filename(row.get('case_manager_last_name', ''))
    office_dir = os.path.join(output_dir_path, case_manager_office)
    manager_dir = os.path.join(office_dir, f"{cm_first} {cm_last}")
    os.makedirs(manager_dir, exist_ok=True)

    if pdf_path and os.path.exists(pdf_path):
        new_location = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, new_location)
        logging.info(f"Moved {pdf_path} to {new_location}")
    else:
        logging.warning("PDF file not found or not generated for row. Possibly conversion failed.")

# ------------------------------------------------------------------------------
# 11) Main Loop
# ------------------------------------------------------------------------------
try:
    for idx, row in df.iterrows():
        generate_behavior_contracts(row, central_location_path)
except Exception as e:
    logging.error(f"Error during Behavior Contract generation loop: {e}")
    sys.exit(1)

logging.info("MRT Behavior Contract Script completed successfully.")

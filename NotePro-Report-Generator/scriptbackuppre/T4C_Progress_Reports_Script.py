#!/usr/bin/env python3
# T4C_Progress_Reports_Script.py

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
import time
import argparse
import shutil
import subprocess
import re

# Parse command-line arguments
parser = argparse.ArgumentParser(description='Generate T4C Progress Reports')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='Optional start date (YYYY-MM-DD)')
parser.add_argument('--end_date', required=False, help='Optional end date (YYYY-MM-DD)')
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

# Setup logging
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'T4C_Progress_Reports_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("T4C Progress Reports Script started.")

# Base directories
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


template_file_path = os.path.join(templates_dir, 'Template.T4C Progress Report.docx')

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

def fetch_image(url, doc, max_width=4.79, max_height=3.92, margin=0.2):
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

            img = img.resize((int(new_width * 300), int(new_height * 300)), Image.LANCZOS)

            img_byte_arr = BytesIO()
            img.save(img_byte_arr, format='JPEG')

            return InlineImage(doc, BytesIO(img_byte_arr.getvalue()), width=Inches(new_width), height=Inches(new_height))
        else:
            logging.warning("Failed to fetch image or non-image content returned: %s", url)
            return None
    except requests.RequestException as e:
        logging.warning("Request failed for URL %s with error: %s", url, e)
        return None
    except Exception as e:
        logging.error("Unexpected error fetching image: %s", e)
        return None

def parse_multiple_formats(value, formats=['%m/%d/%Y', '%Y-%m-%d', '%m/%d/%y', '%b-%Y', '%B %Y']):
    if not value or not isinstance(value, str):
        return None
    for fmt in formats:
        try:
            return datetime.strptime(value.strip(), fmt)
        except ValueError:
            continue
    return None

def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

##############################################################################
# Optional: decode weird sequences and replace with letters for c1_, c2_, ...
##############################################################################
#!/usr/bin/env python3
# T4C_Progress_Reports_Script.py

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
import time
import argparse
import shutil
import subprocess
import re

# Parse command-line arguments
parser = argparse.ArgumentParser(description='Generate T4C Progress Reports')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='Optional start date (YYYY-MM-DD)')
parser.add_argument('--end_date', required=False, help='Optional end date (YYYY-MM-DD)')
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

# Setup logging
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'T4C_Progress_Reports_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("T4C Progress Reports Script started.")

# Base directories
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


template_file_path = os.path.join(templates_dir, 'Template.T4C Progress Report.docx')

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

def fetch_image(url, doc, max_width=4.79, max_height=3.92, margin=0.2):
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

            img = img.resize((int(new_width * 300), int(new_height * 300)), Image.LANCZOS)

            img_byte_arr = BytesIO()
            img.save(img_byte_arr, format='JPEG')

            return InlineImage(doc, BytesIO(img_byte_arr.getvalue()), width=Inches(new_width), height=Inches(new_height))
        else:
            logging.warning("Failed to fetch image or non-image content returned: %s", url)
            return None
    except requests.RequestException as e:
        logging.warning("Request failed for URL %s with error: %s", url, e)
        return None
    except Exception as e:
        logging.error("Unexpected error fetching image: %s", e)
        return None

def parse_multiple_formats(value, formats=['%m/%d/%Y', '%Y-%m-%d', '%m/%d/%y', '%b-%Y', '%B %Y']):
    if not value or not isinstance(value, str):
        return None
    for fmt in formats:
        try:
            return datetime.strptime(value.strip(), fmt)
        except ValueError:
            continue
    return None

def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

##############################################################################
# Optional: decode weird sequences and replace with letters for c1_, c2_, ...
##############################################################################
def transform_calendar_cell(cell_value):
    """
    Transform c#_## columns to preserve multiple marks in a single calendar day.
      P -> ✅ (Present)
      X -> ❌ (Unexcused Absence)
      E -> ✖️ (Excused Absence)

    Handles the exact encodings we see in report5_dump (including orphan trailing
    bytes like 'â' after the first symbol). Order is preserved.
    """
    if not isinstance(cell_value, str) or not cell_value.strip():
        return cell_value  # leave non-strings / blanks unchanged

    raw = cell_value.strip()

    import re
    # --- 0) Optional: map letter codes if they appear ---
    raw = re.sub(r'(?<!\w)P(?!\w)', '✅', raw)
    raw = re.sub(r'(?<!\w)X(?!\w)', '❌', raw)
    raw = re.sub(r'(?<!\w)E(?!\w)', '✖️', raw)

    # ----------------------------------------------------------------------
    # 1) TWO-MARK COMBINATIONS FIRST (most specific → least specific)
    #    Orphan-tail cases: first symbol complete, second truncated to just 'â'
    # ----------------------------------------------------------------------

    # Present + orphan  → Present + Unexcused  (your "20 âœ… â ")
    raw = raw.replace("âœ… â ", "✅❌")
    raw = raw.replace("âœ… â",  "✅❌")
    raw = raw.replace("â\x9c\x85 â", "✅❌")  # other check mojibake

    # Excused + orphan  → Excused + Excused   (your "28 âœ– â ")
    raw = raw.replace("âœ– â ", "✖️✖️")
    raw = raw.replace("âœ– â",  "✖️✖️")
    raw = raw.replace("â\x9d– â", "✖️✖️")     # variant bytes for excused

    # Unexcused + orphan → Unexcused + Excused (seen in some dumps)
    raw = raw.replace("â\x9d\x8c â ", "❌✖️")
    raw = raw.replace("â\x9d\x8c â",  "❌✖️")
    raw = raw.replace("âŒ â ",       "❌✖️")
    raw = raw.replace("âŒ â",        "❌✖️")

    # --- Known explicit pairs you already encounter ---

    # Present + Unexcused (your July 20, 27)
    raw = raw.replace("âœ… â\x9d", "✅❌")
    raw = raw.replace("âœ… â",  "✅❌")
    raw = raw.replace("â\x9c\x85 â\x9d", "✅❌")

    # Double Present (your Aug 3) – include compact/split variants
    raw = raw.replace("âœ… âœ ", "✅✅")
    raw = raw.replace("âœ… âœ",  "✅✅")
    raw = raw.replace("â\x9c\x85 âœ", "✅✅")
    raw = raw.replace("âœ…âœ…",   "✅✅")
    raw = raw.replace("â\x9c\x85 â\x9c", "✅✅")

    # Double Unexcused (your Aug 17)
    raw = raw.replace("â\x9d\x8c â\x9d", "❌❌")
    raw = raw.replace("âŒ â",        "❌❌")
    raw = raw.replace("â\x9d\x8c â ",   "❌❌")
    raw = raw.replace("â\x9d\x8c â",    "❌❌")
    raw = raw.replace("âŒ â ",         "❌❌")
    raw = raw.replace("âŒ â",          "❌❌")

    # Excused + Unexcused (your Sept 7) + compact variants
    raw = raw.replace("âœ– â\x9d",      "✖️❌")
    raw = raw.replace("âœ– â",         "✖️❌")
    raw = raw.replace("â\x9d– â\x9d",   "✖️❌")
    raw = raw.replace("âœ–â\x9d\x8c",   "✖️❌")
    raw = raw.replace("â\x9d–â\x9d\x8c","✖️❌")

    # Attendance + Excused (and reverse) – cover remaining pairings
    raw = raw.replace("âœ… âœ–", "✅✖️")
    raw = raw.replace("âœ… â\x9d–", "✅✖️")
    raw = raw.replace("â\x9c\x85 âœ–", "✅✖️")
    raw = raw.replace("â\x9c\x85 â\x9d–", "✅✖️")
    raw = raw.replace("âœ– âœ…", "✖️✅")
    raw = raw.replace("â\x9d– âœ…", "✖️✅")
    raw = raw.replace("âœ– â\x9c\x85", "✖️✅")
    raw = raw.replace("â\x9d– â\x9c\x85", "✖️✅")
    raw = raw.replace("âœ–âœ…", "✖️✅")
    raw = raw.replace("â\x9d–âœ…", "✖️✅")

    # Unexcused + Excused (and reverse) – additional explicit forms
    raw = raw.replace("â\x9d\x8c âœ–", "❌✖️")
    raw = raw.replace("â\x9d\x8c â\x9d–", "❌✖️")
    raw = raw.replace("âŒ âœ–", "❌✖️")
    raw = raw.replace("âŒ â\x9d–", "❌✖️")

    # Double Excused (if compact)
    raw = raw.replace("âœ–âœ–", "✖️✖️")
    raw = raw.replace("â\x9d–â\x9d–", "✖️✖️")

    # ----------------------------------------------------------------------
    # 2) SINGLE-SYMBOL NORMALIZATION (exact sequences only; ABSENCES FIRST)
    # ----------------------------------------------------------------------

    # Unexcused ❌ (U+274C)
    raw = raw.replace("â\x9d\x8c", "❌")   # full mojibake
    raw = raw.replace("âŒ",       "❌")   # display variant

    # Excused ✖️ (U+2716 + VS16)
    raw = raw.replace("âœ–",       "✖️")
    raw = raw.replace("â\x9d–",     "✖️")
    raw = raw.replace("✖",         "✖️")  # normalize to VS16 form

    # Present ✅ (U+2705)
    raw = raw.replace("âœ…",       "✅")
    raw = raw.replace("â\x9c\x85", "✅")

    # ----------------------------------------------------------------------
    # 3) CLEANUP: orphan mojibake only when adjacent to a real symbol
    # ----------------------------------------------------------------------
    raw = re.sub(r'[Ââ]\s*(?=(✅|❌|✖️))', '', raw)     # before symbol
    raw = re.sub(r'(✅|❌|✖️)\s*[Ââ]+', r'\1', raw)     # after symbol
    raw = re.sub(r'\s*(✅|❌|✖️)\s*', r'\1', raw)       # tighten spaces

    # ----------------------------------------------------------------------
    # 4) Extract: first day number + all symbols in encounter order
    # ----------------------------------------------------------------------
    m = re.search(r'\b(\d{1,2})\b', raw)
    day = m.group(1) if m else None
    marks = ''.join(re.findall(r'✅|❌|✖️', raw))

    # Cap rare triple checks to two (visual clarity in a tiny box)
    if marks.count('✅') >= 3:
        marks = marks.replace('✅✅✅', '✅✅')

    out = (f"{day}{marks}" if day else marks).strip()

    # Final micro-cleanups of lingering bytes some fonts render oddly
    out = out.replace("Â", "").replace("â", "").replace("œ", "").strip()
    return out



def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

def fill_placeholders(row):
    """
    Process each row by converting date formats, handling attendance,
    and formatting placeholders.
    """
    # Convert fields as needed
    for i in range(1, 31):
        row[f'D{i}'] = ''

    # Handle attendance
    attended = row.get('attended', '0')
    required = row.get('required_sessions', '0')
    try:
        attended_f = float(attended)
        required_f = float(required)
    except ValueError:
        attended_f, required_f = 0, 0

    if required_f > 0:
        filled = int((attended_f / required_f) * 30)
        for i in range(1, filled + 1):
            row[f'D{i}'] = '\u2714'

    # Format DOB and Orientation Date
    date_fields = ['dob', 'orientation_date']
    for field in date_fields:
        if field in row and isinstance(row[field], str) and row[field].strip():
            date_obj = parse_multiple_formats(row[field])
            if date_obj:
                row[field] = date_obj.strftime('%m/%d/%Y')

    # Format P and A fields
    for i in range(1, 28):
        p_field = f'P{i}'
        if p_field in row and isinstance(row[p_field], str) and row[p_field].strip():
            date_obj = parse_multiple_formats(row[p_field])
            if date_obj:
                row[p_field] = date_obj.strftime('%m/%d/%y')

    for i in range(1, 19):
        a_field = f'A{i}'
        if a_field in row and isinstance(row[a_field], str) and row[a_field].strip():
            date_obj = parse_multiple_formats(row[a_field])
            if date_obj:
                row[a_field] = date_obj.strftime('%m/%d/%y')

    # Convert Calendar Headers (c#_header) to Month Year (e.g., "December 2024")
    header_keys = ['c1_header', 'c2_header', 'c3_header', 'c4_header']
    
    for key in header_keys:
        if key in row and isinstance(row[key], str) and row[key].strip():
            value = row[key].strip()
            date_obj = None
            original_value = value  # Store the original value for logging

            # 1. Try standard formats (e.g., "Jun-23", "Jun 23", "06/23")
            for fmt in ['%b-%y', '%b %y', '%b/%y']:
                try:
                    date_obj = datetime.strptime(value, fmt)
                    break  # Exit loop if successfully parsed
                except ValueError:
                    continue

            # 2. Try detecting "MonthYear" format (e.g., "December2024" → "December 2024")
            if not date_obj:
                match = re.match(r"([A-Za-z]+)(\d{4})", value)
                if match:
                    month_name, year = match.groups()
                    try:
                        date_obj = datetime.strptime(f"{month_name} {year}", "%B %Y")
                    except ValueError:
                        logging.error(f"Failed to parse MonthYear format for {key}: {value}")

            # 3. Ensure date_obj is properly formatted
            if date_obj:
                formatted_date = date_obj.strftime('%B %Y')  # Convert to "December 2024"
                row[key] = formatted_date
                logging.info(f"Transformed {key}: {original_value} -> {formatted_date}")
            else:
                logging.error(f"Date parsing error for {key}: {value}, leaving as is.")

    # Convert Y/N fields to Yes/No
    for col in row.keys():
        row[col] = convert_yn_to_yes_no(row[col])

    # Replace '&' in client_note
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    # Determine fee problems
    if 'balance' in row:
        try:
            balance = float(row['balance'])
            row['feeproblems'] = "No" if balance == 0 else "Yes"
        except ValueError:
            row['feeproblems'] = "Yes"

    # Gender placeholders
    gender = row.get('gender', '').lower()
    if gender == 'male':
        row['gender1'] = 'his'
        row['gender2'] = 'he'
        row['gender3'] = "Men's"
        row['gender4'] = 'him'
        row['gender5'] = "He"
        row['gender6'] = 'himself'
    elif gender == 'female':
        row['gender1'] = 'her'
        row['gender2'] = 'she'
        row['gender3'] = "Women's"
        row['gender4'] = 'her'
        row['gender5'] = "She"
        row['gender6'] = 'herself'
    else:
        logging.error(f"Unrecognized gender: {gender}")
        for g in ['gender1','gender2','gender3','gender4','gender5','gender6']:
            row[g] = ''

    # If last_attended is missing, fallback to orientation_date if available
    if 'orientation_date' in row and 'last_attended' in row:
        if not row['last_attended'].strip() or row['last_attended'].lower() == 'nan':
            row['last_attended'] = row['orientation_date']

    if 'age' in row:
        try:
            age_value = float(row['age'])
            if age_value.is_integer():
                row['age'] = str(int(age_value))
            else:
                row['age'] = str(age_value)
        except ValueError:
            pass  # Leave it as is if it's not a valid number

    return row

def render_and_save(template_path, doc_type, context):
    try:
        doc = DocxTemplate(template_path)
        
        # Insert image if available
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc)
            context['image'] = image if image else ''

        # Debug: Log the full context before rendering
        logging.info(f"Context before rendering: {context}")

        # **Remove "œ" from all context values before rendering**
        for key in context:
            if isinstance(context[key], str):  # Ensure value is a string
                context[key] = context[key].replace("oe", "").strip()

        doc.render(context)
        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} {doc_type}.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)
            return pdf_path
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
            return None
    except Exception as e:
        logging.error(f"Error rendering {doc_type}: {e}")
        return None


# Load CSV
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

# Filter for T4C Not Exited clients
df = df[(df['program_name'] == 'Thinking for a Change') & (df['exit_reason'] == 'Not Exited')]

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

# Convert columns to string
df = df.astype(str)

# 1) Apply your existing fill_placeholders
df = df.apply(fill_placeholders, axis=1)
df.fillna('', inplace=True)

df.replace(['nan', 'NaN'], '', inplace=True)

calendar_cols = []
for c in df.columns:
    if c.startswith(('c1_', 'c2_', 'c3_', 'c4_')):
        if not c.endswith('_header'):
            calendar_cols.append(c)


# Apply the transform_calendar_cell function to each of those columns
for col in calendar_cols:
    df[col] = df[col].apply(transform_calendar_cell)


def generate_documents_for_row(row):
    # Convert all header values to string to prevent truncation
    for key in ['c1_header', 'c2_header', 'c3_header', 'c4_header']:
        if key in row:
            row[key] = str(row[key]).strip()  # Ensure proper conversion

    context = row.to_dict()

    # Log final context to verify correctness before rendering
    logging.info(f"Final context before rendering: {context}")

    # Add report_date as today's date m/d/yyyy
    now = datetime.now()
    context['report_date'] = f"{now.month}/{now.day}/{now.year}"

    pdf_path = render_and_save(template_file_path, "T4C Progress Report", context)

    if pdf_path and os.path.exists(pdf_path):
        # Organize PDFs by case manager
        case_manager_office = sanitize_filename(context.get('case_manager_office', 'Unknown Office'))
        case_manager_first = sanitize_filename(context.get('case_manager_first_name', ''))
        case_manager_last = sanitize_filename(context.get('case_manager_last_name', ''))

        office_dir = os.path.join(central_location_path, case_manager_office)
        manager_dir = os.path.join(office_dir, f"{case_manager_first} {case_manager_last}")
        os.makedirs(manager_dir, exist_ok=True)

        new_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, new_pdf_path)
        logging.info(f"Moved {pdf_path} to {new_pdf_path}")
    else:
        logging.warning("PDF not generated or missing for this row.")

try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("T4C Progress Reports have been generated and organized.")
print("T4C Progress Reports have been generated and organized.")




def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

def fill_placeholders(row):
    """
    Process each row by converting date formats, handling attendance,
    and formatting placeholders.
    """
    # Convert fields as needed
    for i in range(1, 31):
        row[f'D{i}'] = ''

    # Handle attendance
    attended = row.get('attended', '0')
    required = row.get('required_sessions', '0')
    try:
        attended_f = float(attended)
        required_f = float(required)
    except ValueError:
        attended_f, required_f = 0, 0

    if required_f > 0:
        filled = int((attended_f / required_f) * 30)
        for i in range(1, filled + 1):
            row[f'D{i}'] = '\u2714'

    # Format DOB and Orientation Date
    date_fields = ['dob', 'orientation_date']
    for field in date_fields:
        if field in row and isinstance(row[field], str) and row[field].strip():
            date_obj = parse_multiple_formats(row[field])
            if date_obj:
                row[field] = date_obj.strftime('%m/%d/%Y')

    # Format P and A fields
    for i in range(1, 28):
        p_field = f'P{i}'
        if p_field in row and isinstance(row[p_field], str) and row[p_field].strip():
            date_obj = parse_multiple_formats(row[p_field])
            if date_obj:
                row[p_field] = date_obj.strftime('%m/%d/%y')

    for i in range(1, 19):
        a_field = f'A{i}'
        if a_field in row and isinstance(row[a_field], str) and row[a_field].strip():
            date_obj = parse_multiple_formats(row[a_field])
            if date_obj:
                row[a_field] = date_obj.strftime('%m/%d/%y')

    # Convert Calendar Headers (c#_header) to Month Year (e.g., "December 2024")
    header_keys = ['c1_header', 'c2_header', 'c3_header', 'c4_header']
    
    for key in header_keys:
        if key in row and isinstance(row[key], str) and row[key].strip():
            value = row[key].strip()
            date_obj = None
            original_value = value  # Store the original value for logging

            # 1. Try standard formats (e.g., "Jun-23", "Jun 23", "06/23")
            for fmt in ['%b-%y', '%b %y', '%b/%y']:
                try:
                    date_obj = datetime.strptime(value, fmt)
                    break  # Exit loop if successfully parsed
                except ValueError:
                    continue

            # 2. Try detecting "MonthYear" format (e.g., "December2024" → "December 2024")
            if not date_obj:
                match = re.match(r"([A-Za-z]+)(\d{4})", value)
                if match:
                    month_name, year = match.groups()
                    try:
                        date_obj = datetime.strptime(f"{month_name} {year}", "%B %Y")
                    except ValueError:
                        logging.error(f"Failed to parse MonthYear format for {key}: {value}")

            # 3. Ensure date_obj is properly formatted
            if date_obj:
                formatted_date = date_obj.strftime('%B %Y')  # Convert to "December 2024"
                row[key] = formatted_date
                logging.info(f"Transformed {key}: {original_value} -> {formatted_date}")
            else:
                logging.error(f"Date parsing error for {key}: {value}, leaving as is.")

    # Convert Y/N fields to Yes/No
    for col in row.keys():
        row[col] = convert_yn_to_yes_no(row[col])

    # Replace '&' in client_note
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    # Determine fee problems
    if 'balance' in row:
        try:
            balance = float(row['balance'])
            row['feeproblems'] = "No" if balance == 0 else "Yes"
        except ValueError:
            row['feeproblems'] = "Yes"

    # Gender placeholders
    gender = row.get('gender', '').lower()
    if gender == 'male':
        row['gender1'] = 'his'
        row['gender2'] = 'he'
        row['gender3'] = "Men's"
        row['gender4'] = 'him'
        row['gender5'] = "He"
        row['gender6'] = 'himself'
    elif gender == 'female':
        row['gender1'] = 'her'
        row['gender2'] = 'she'
        row['gender3'] = "Women's"
        row['gender4'] = 'her'
        row['gender5'] = "She"
        row['gender6'] = 'herself'
    else:
        logging.error(f"Unrecognized gender: {gender}")
        for g in ['gender1','gender2','gender3','gender4','gender5','gender6']:
            row[g] = ''

    # If last_attended is missing, fallback to orientation_date if available
    if 'orientation_date' in row and 'last_attended' in row:
        if not row['last_attended'].strip() or row['last_attended'].lower() == 'nan':
            row['last_attended'] = row['orientation_date']

    if 'age' in row:
        try:
            age_value = float(row['age'])
            if age_value.is_integer():
                row['age'] = str(int(age_value))
            else:
                row['age'] = str(age_value)
        except ValueError:
            pass  # Leave it as is if it's not a valid number

    return row

def render_and_save(template_path, doc_type, context):
    try:
        doc = DocxTemplate(template_path)
        
        # Insert image if available
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc)
            context['image'] = image if image else ''

        # Debug: Log the full context before rendering
        logging.info(f"Context before rendering: {context}")

        doc.render(context)
        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} {doc_type}.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)
            return pdf_path
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
            return None
    except Exception as e:
        logging.error(f"Error rendering {doc_type}: {e}")
        return None


# Load CSV
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

# Filter for T4C Not Exited clients
df = df[(df['program_name'] == 'Thinking for a Change') & (df['exit_reason'] == 'Not Exited')]

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

# Convert columns to string
df = df.astype(str)

# 1) Apply your existing fill_placeholders
df = df.apply(fill_placeholders, axis=1)
df.fillna('', inplace=True)

df.replace(['nan', 'NaN'], '', inplace=True)

calendar_cols = []
for c in df.columns:
    if c.startswith(('c1_', 'c2_', 'c3_', 'c4_')):
        if not c.endswith('_header'):
            calendar_cols.append(c)


# Apply the transform_calendar_cell function to each of those columns
for col in calendar_cols:
    df[col] = df[col].apply(transform_calendar_cell)


def generate_documents_for_row(row):
    # Convert all header values to string to prevent truncation
    for key in ['c1_header', 'c2_header', 'c3_header', 'c4_header']:
        if key in row:
            row[key] = str(row[key]).strip()  # Ensure proper conversion

    context = row.to_dict()

    # Log final context to verify correctness before rendering
    logging.info(f"Final context before rendering: {context}")

    # Add report_date as today's date m/d/yyyy
    now = datetime.now()
    context['report_date'] = f"{now.month}/{now.day}/{now.year}"

    pdf_path = render_and_save(template_file_path, "T4C Progress Report", context)

    if pdf_path and os.path.exists(pdf_path):
        # Organize PDFs by case manager
        case_manager_office = sanitize_filename(context.get('case_manager_office', 'Unknown Office'))
        case_manager_first = sanitize_filename(context.get('case_manager_first_name', ''))
        case_manager_last = sanitize_filename(context.get('case_manager_last_name', ''))

        office_dir = os.path.join(central_location_path, case_manager_office)
        manager_dir = os.path.join(office_dir, f"{case_manager_first} {case_manager_last}")
        os.makedirs(manager_dir, exist_ok=True)

        new_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, new_pdf_path)
        logging.info(f"Moved {pdf_path} to {new_pdf_path}")
    else:
        logging.warning("PDF not generated or missing for this row.")

try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("T4C Progress Reports have been generated and organized.")
print("T4C Progress Reports have been generated and organized.")

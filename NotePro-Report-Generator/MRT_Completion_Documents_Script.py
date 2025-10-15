#!/usr/bin/env python3
# MRT_Completion_Documents_Script.py

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

##############################################################################
# 1) Parse arguments
##############################################################################
parser = argparse.ArgumentParser(description='Generate MRT Completion Documents (Letter, Certificate, Completion Progress Report)')
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
    filename=os.path.join(log_dir, f'MRT_Completion_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("MRT Completion Documents Script started.")

##############################################################################
# 3) Convert date arguments
##############################################################################
try:
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
    end_date = datetime.strptime(end_date_str, '%Y-%m-%d')
except ValueError as e:
    logging.error(f"Invalid date format for start_date or end_date: {e}")
    sys.exit(1)

##############################################################################
# 4) Set up directories
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
# 5) Template paths
##############################################################################
completion_letter_template = os.path.join(templates_dir, 'Template.MRT Completion Letter.docx')
completion_certificate_template = os.path.join(templates_dir, 'Template.MRT Completion Certificate.docx')
completion_report_template = os.path.join(templates_dir, 'Template.MRT Completion Progress Report.docx')

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
            subprocess.run([
                'libreoffice', '--headless',
                '--convert-to', 'pdf',
                '--outdir', pdf_dir,
                docx_path
            ], check=True)
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
    """Fetch and resize an image to insert into the DOCX template."""
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

            # Keep aspect ratio, but limit to max_width / max_height
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

            # Convert from inches to pixels at ~300 DPI
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

def parse_multiple_formats(value, formats=None):
    """Try multiple date formats for a single string value (expanded for MRT logic)."""
    if formats is None:
        formats = [
            '%m/%d/%Y', '%Y-%m-%d', '%m/%d/%y',
            '%b-%Y', '%B %Y',
            '%Y-%m-%d %H:%M:%S'
        ]
    if not value or not isinstance(value, str):
        return None
    for fmt in formats:
        try:
            return datetime.strptime(value.strip(), fmt)
        except ValueError:
            continue
    return None

def convert_yn_to_yes_no(value):
    """Converts 'y' to 'Yes', 'n' to 'No', otherwise returns original."""
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

def transform_calendar_cell(cell_value):
    """
    Transform c#_ columns so 'P' -> ✅, 'X' -> ❌, 'E' -> ✖️.
    Also decode older or garbled checkmark encodings.
    """
    if not isinstance(cell_value, str) or not cell_value.strip():
        return cell_value  # return as-is if empty

    raw = cell_value.strip()

    # Decode weird sequences or older checkmarks
    raw = raw.replace("âœ…", "P")
    raw = raw.replace("✅", "P")
    raw = raw.replace("âŒ", "X")
    raw = raw.replace("❌", "X")
    raw = raw.replace("âœ–", "X")
    raw = raw.replace("â–", "E")
    raw = raw.replace("✖", "E")

    parts = raw.split()
    day_number = None
    symbol_string = ""
    for part in parts:
        if part.isdigit():
            day_number = part
        elif part.upper() in ['P','X','E']:
            symbol_string += part.upper()

    symbol_map = {'P': '✅', 'X': '❌', 'E': '✖️'}
    replaced_symbols = "".join(symbol_map.get(ch, ch) for ch in symbol_string)

    if day_number:
        return f"{day_number} {replaced_symbols}"
    else:
        return replaced_symbols

def parse_date_mdy(s):
    if not isinstance(s, str) or not s.strip():
        return ''
    for fmt in ('%Y-%m-%d','%m/%d/%Y','%m/%d/%y','%Y-%m-%d %H:%M:%S'):
        try:
            return datetime.strptime(s.strip(), fmt).strftime('%m/%d/%Y')
        except ValueError:
            pass
    return s.strip()

def checkbox(ok: bool) -> str:
    return '[x]' if ok else '[ ]'

def fmt_currency(v) -> str:
    try:
        x = float(str(v).strip() or 0)
    except ValueError:
        return str(v)
    if abs(x) < 0.005:
        return ''          # suppress 0.0
    return f"${x:,.2f}" if x >= 0 else f"-${abs(x):,.2f}"

def map_stage_of_change(raw) -> tuple[str, str]:
    s = str(raw or '').strip().lower()
    m = {
        '1': ('PC','Precontemplation'), 'pc': ('PC','Precontemplation'), 'precontemplation': ('PC','Precontemplation'),
        '2': ('C','Contemplation'),     'c':  ('C','Contemplation'),     'contemplation':    ('C','Contemplation'),
        '3': ('P','Preparation'),       'p':  ('P','Preparation'),       'preparation':      ('P','Preparation'),
        '4': ('A','Action'),            'a':  ('A','Action'),            'action':           ('A','Action'),
        '5': ('M','Maintenance'),       'm':  ('M','Maintenance'),       'maintenance':      ('M','Maintenance'),
    }
    return m.get(s, ('', s.title() if s else ''))


def ensure_columns(df, cols, default=''):
    for c in cols:
        if c not in df.columns:
            df[c] = default
    return df

def _derive_attended_from_P(row):
    # if 'attended' is 0/blank, count non-empty P1..P27
    try:
        a = int(float(row.get('attended','') or 0))
    except Exception:
        a = 0
    if a == 0:
        a = sum(1 for i in range(1, 28) if str(row.get(f'P{i}','')).strip())
        row['attended'] = str(a)
    return row


def _is_yes(v):
    return str(v).strip().lower() in ('yes','y','1','true','✓','check','checked')

def _chk(ok):  # ✓ or ✖ to match progress reports
    return '\u2714' if ok else '\u2716'


def determine_stage_of_change(attended_str, required_str):
    try:
        a = float(attended_str or 0); r = float(required_str or 0)
    except ValueError:
        return 'Alert'
    if r == 15:
        return ('Precontemplation' if 0<=a<=3 else
                'Contemplation'    if 4<=a<=7 else
                'Preparation'      if 8<=a<=11 else
                'Action'           if 12<=a<=14 else
                'Maintenance'      if 15<=a<=18 else 'Alert')
    if r == 27:
        return ('Precontemplation' if 1<=a<=7 else
                'Contemplation'    if 8<=a<=14 else
                'Preparation'      if 15<=a<=18 else
                'Action'           if 19<=a<=23 else
                'Maintenance'      if 24<=a<=27 else 'Alert')
    ratio = a / r if r else 0
    return ('Precontemplation' if 0<ratio<=3/15  else
            'Contemplation'    if ratio<=7/15   else
            'Preparation'      if ratio<=11/15  else
            'Action'           if ratio<=14/15  else
            'Maintenance'      if ratio<=1.0    else 'Alert')

def determine_fee_problems(balance):
    try:
        return "No" if float(balance or 0) == 0 else "Yes"
    except Exception:
        return "Yes"

def format_balance(balance):
    try:
        v = float(balance or 0)
    except Exception:
        return str(balance)
    return '' if abs(v) < 0.005 else f"${v:,.2f}"


ABSENCE_LIMIT = 3

def _mrt_mid_derived(row):
    # --- attendance / consistency ---
    try:
        required = float(row.get('required_sessions', 0) or 0)
    except ValueError:
        required = 0.0
    try:
        unexcused = float(row.get('absence_unexcused', 0) or 0)
    except ValueError:
        unexcused = 0.0

    has_orientation = bool(str(row.get('orientation_date','')).strip())
    row['attended_initial_assessment']   = _chk(has_orientation)
    row['attended_initial_assessessment'] = row['attended_initial_assessment']  # template typo support

    # consistent attendance = within 10% unexcused
    if required > 0:
        ratio_ok = (required - unexcused) / required >= 0.90
    else:
        ratio_ok = False
    row['consistent_attendance'] = _chk(ratio_ok)

    # Three or more absences text
    try:
        absences = int(unexcused)
    except ValueError:
        absences = 0
    if absences >= 3:
        row['three_or_more_absences'] = f"{absences} absences"
    elif absences == 0:
        row['three_or_more_absences'] = "No"
    else:
        row['three_or_more_absences'] = "Within 3"

    # --- group rules / participant agreement / dialogue / accountability ---
    def _is_yes(v):
        return str(v).strip().lower() in ('y','yes','true','1')

    respectful = _is_yes(row.get('respectful_to_group',''))
    disruptive = _is_yes(row.get('disruptive_argumentitive', row.get('disruptive_arumentitive','')))
    humor_bad  = _is_yes(row.get('humor_inappropriate',''))
    blames     = _is_yes(row.get('blames_victim',''))
    appears    = _is_yes(row.get('appears_drug_alcohol',''))
    staff_bad  = _is_yes(row.get('inappropriate_to_staff',''))
    dialogue   = _is_yes(row.get('speaks_significantly_in_group',''))
    takes_resp = _is_yes(row.get('takes_responsibility_for_past',''))

    row['followed_group_rules']             = _chk(respectful and not disruptive and not humor_bad and not staff_bad)
    row['adhered_to_participant_agreement'] = _chk(not appears and not blames and not staff_bad)
    row['participated_in_dialogue']         = _chk(dialogue or (respectful and not disruptive))
    row['participated_in_dialoge']          = row['participated_in_dialogue']   # template typo support
    row['participated_in_dialog']           = row['participated_in_dialogue']   # template typo support
    row['accountability_consistent']        = _chk(takes_resp)
    return row


##############################################################################
# fill_placeholders for MRT completion progress logic
##############################################################################
def fill_placeholders(row):
    row = row.copy()

    # normalize 'nan'
    for k, v in list(row.items()):
        if isinstance(v, str) and v.strip().lower() == 'nan':
            row[k] = ''

    # ----- gender detection like Unexcused script -----
    raw_gender = (row.get('gender','') or row.get('gender_name','') or row.get('sex','')).strip().lower()
    gid = str(row.get('gender_id','')).strip()
    if raw_gender in ('male','m') or gid == '2':
        g = 'male'
    elif raw_gender in ('female','f') or gid == '3':
        g = 'female'
    else:
        g = 'neutral'

    if g == 'male':
        row['gender1']='his'; row['gender2']='he'; row['gender3']="Men's"; row['gender4']='him'; row['gender5']='He'; row['gender6']='himself'; row['gender7']='Mr.'
    elif g == 'female':
        row['gender1']='her'; row['gender2']='she'; row['gender3']="Women's"; row['gender4']='her'; row['gender5']='She'; row['gender6']='herself'; row['gender7']='Ms.'
    else:
        row['gender1']='their'; row['gender2']='they'; row['gender3']='Participants'; row['gender4']='them'; row['gender5']='They'; row['gender6']='themselves'; row['gender7']=''

    # ----- 30 squares (completion progress) -----
    for i in range(1,31):
        row[f'D{i}'] = ''
    try:
        attended_f = float(row.get('attended','0') or 0)
        required_f = float(row.get('required_sessions','0') or 0)
    except ValueError:
        attended_f = required_f = 0.0
    if required_f > 0:
        filled = max(0, min(30, int((attended_f/required_f)*30)))
        for i in range(1, filled+1):
            row[f'D{i}'] = '✔'

    # ----- P# and A# → mm/dd/yy -----
    for i in range(1,28):
        k = f'P{i}'
        if isinstance(row.get(k,''), str) and row[k].strip():
            d = parse_multiple_formats(row[k]);  row[k] = d.strftime('%m/%d/%y') if d else row[k]
    for i in range(1,19):
        k = f'A{i}'
        if isinstance(row.get(k,''), str) and row[k].strip():
            d = parse_multiple_formats(row[k]);  row[k] = d.strftime('%m/%d/%y') if d else row[k]

    # ----- headers c#_header → "Month Year" -----
    for key in ('c1_header','c2_header','c3_header','c4_header'):
        val = str(row.get(key,'')).strip()
        if not val:
            continue
        date_obj = None
        for fmt in ('%b-%y','%b %y','%b/%y'):
            try:
                date_obj = datetime.strptime(val, fmt); break
            except ValueError:
                pass
        if not date_obj:
            m = re.match(r'([A-Za-z]+)\s*(\d{4})$', val)
            if m:
                try:
                    date_obj = datetime.strptime(f"{m.group(1)} {m.group(2)}","%B %Y")
                except ValueError:
                    pass
        if date_obj:
            row[key] = date_obj.strftime('%B %Y')

    # ----- Yes/No normalization (y/n -> Yes/No) -----
    for k, v in list(row.items()):
        row[k] = convert_yn_to_yes_no(v)

        # ----- Balance formatting + fee status (overwrite template's {{balance}}) -----
    bal_raw = row.get('balance', '')
    try:
        bal_val = float(str(bal_raw).strip() or 0)
    except ValueError:
        bal_val = 0.0
    row['balance'] = '' if abs(bal_val) < 0.005 else f"${bal_val:,.2f}"  # template uses {{balance}}
    row['feeproblems'] = "No" if abs(bal_val) < 0.005 else "Yes"

    # ----- Stage of Change for template's {{client_stagechange}} -----
    # prefer existing client_stagechange if non-empty; otherwise map from alt fields
    existing_soc = str(row.get('client_stagechange', '')).strip()
    if not existing_soc:
        soc_src = row.get('stage_of_change', row.get('stage_change', row.get('soc', '')))
        soc_map = {
            '1':'Precontemplation','pc':'Precontemplation','precontemplation':'Precontemplation',
            '2':'Contemplation','c':'Contemplation','contemplation':'Contemplation',
            '3':'Preparation','p':'Preparation','preparation':'Preparation',
            '4':'Action','a':'Action','action':'Action',
            '5':'Maintenance','m':'Maintenance','maintenance':'Maintenance',
        }
        key = str(soc_src or '').strip().lower()
        row['client_stagechange'] = soc_map.get(key, str(soc_src or '').strip())
    else:
        row['client_stagechange'] = existing_soc

    # ----- Absence tally + attendance rate + checkbox benchmarks (exact token names in template) -----
    # Count A1..A18 non-empty as unexcused absences
    unexcused = sum(1 for i in range(1, 19) if str(row.get(f'A{i}','')).strip())
    row['absence_unexcused'] = str(unexcused)  # template shows {{absence_unexcused}}

    try:
        attended_f = float(row.get('attended','0') or 0)
        required_f = float(row.get('required_sessions','0') or 0)
    except ValueError:
        attended_f = required_f = 0.0
    rate = (attended_f/required_f) if required_f > 0 else 0.0


    # ----- fallbacks and dates -----
    if not str(row.get('last_attended','')).strip() and str(row.get('orientation_date','')).strip():
        row['last_attended'] = row['orientation_date']

    for dfld in ('dob','orientation_date','last_attended','exit_date','report_date'):
        row[dfld] = parse_date_mdy(row.get(dfld,''))

    # client note cleanup
    if isinstance(row.get('client_note',''), str):
        row['client_note'] = row['client_note'].replace('&','and')

    return row


def render_and_save(template_path, doc_type, context):
    """Render a single DOCX -> PDF, then remove the DOCX, returning the PDF path."""
    try:
        doc = DocxTemplate(template_path)

        # Insert image if available
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc)
            context['image'] = image if image else ''
        else:
            context['image'] = ''

        logging.info(f"Context before rendering {doc_type}: {context}")
        doc.render(context)

        # Construct DOCX path
        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} {doc_type}.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)

        # Convert to PDF
        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)  # Remove .docx after successful PDF
            return pdf_path
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
            return None
    except Exception as e:
        logging.error(f"Error rendering {doc_type}: {e}")
        return None

##############################################################################
# 7) Load & Filter CSV
##############################################################################
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV: {e}")
    sys.exit(1)

# Filter only MRT, exit_reason == 'Completion of Program', exit_date in range
df['exit_date'] = pd.to_datetime(df['exit_date'], errors='coerce')
df = df.dropna(subset=['exit_date'])

df = df[
    (df['program_name'] == 'MRT') &
    (df['exit_reason'] == 'Completion of Program') &
    (df['exit_date'] >= start_date) & (df['exit_date'] <= end_date)
]

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

# 8) Prepare placeholders
df = df.astype(str)

df = ensure_columns(df, [
    'balance','attended','required_sessions','absence_unexcused','orientation_date',
    'respectful_to_group','disruptive_argumentitive','disruptive_arumentitive',
    'humor_inappropriate','blames_victim','appears_drug_alcohol',
    'inappropriate_to_staff','speaks_significantly_in_group',
    'takes_responsibility_for_past'
], '')

df = df.apply(_derive_attended_from_P, axis=1)
# derive mid-section flags to match regular Progress Reports
df = df.apply(_mrt_mid_derived, axis=1)

# your placeholders after derivations
df = df.apply(fill_placeholders, axis=1)
df.fillna('', inplace=True)
df.replace(['nan','NaN'], '', inplace=True)

# stage of change + fees (same as regular Progress Reports)
df['client_stagechange'] = df.apply(
    lambda r: determine_stage_of_change(r.get('attended','0'), r.get('required_sessions','0')),
    axis=1
)
df['feeproblems'] = df['balance'].apply(determine_fee_problems)
df['balance']     = df['balance'].apply(format_balance)
df['stagechange'] = df['client_stagechange']

# calendar cell transforms stay the same below

##############################################################################
# 9) Generate 3 Documents (Letter, Certificate, Completion Progress Report)
##############################################################################
def generate_documents_for_row(row):
    # Make sure 'report_date' is mm/dd/yyyy today
    now = datetime.now()
    row['report_date'] = now.strftime('%m/%d/%Y')

    context = row.to_dict()

    # 1) MRT Completion Progress Report
    pdf_report = render_and_save(completion_report_template, "MRT Completion Progress Report", context)

    # 2) MRT Completion Letter
    pdf_letter = render_and_save(completion_letter_template, "MRT Completion Letter", context)

    # 3) MRT Completion Certificate
    pdf_certificate = render_and_save(completion_certificate_template, "MRT Completion Certificate", context)

    # Organize PDFs by case manager
    case_manager_office = sanitize_filename(context.get('case_manager_office', 'Unknown Office'))
    cm_first = sanitize_filename(context.get('case_manager_first_name', ''))
    cm_last  = sanitize_filename(context.get('case_manager_last_name', ''))

    office_dir = os.path.join(central_location_path, case_manager_office)
    manager_dir = os.path.join(office_dir, f"{cm_first} {cm_last}".strip())
    os.makedirs(manager_dir, exist_ok=True)

    for pdf_file in [pdf_report, pdf_letter, pdf_certificate]:
        if pdf_file and os.path.exists(pdf_file):
            new_path = os.path.join(manager_dir, os.path.basename(pdf_file))
            shutil.move(pdf_file, new_path)
            logging.info(f"Moved {pdf_file} → {new_path}")
        else:
            if pdf_file:
                logging.warning(f"PDF file {pdf_file} not found or not generated.")

##############################################################################
# 10) Main loop
##############################################################################
try:
    for _, row in df.iterrows():
        generate_documents_for_row(row)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("MRT Completion Documents have been generated and organized.")
print("MRT Completion Documents have been generated and organized.")

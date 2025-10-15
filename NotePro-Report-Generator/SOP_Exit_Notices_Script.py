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
import re

parser = argparse.ArgumentParser(description='Generate SOP Exit Notices')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='Start date for the reports (YYYY-MM-DD)')
parser.add_argument('--end_date', required=False, help='End date for the reports (YYYY-MM-DD)')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

csv_file_path = args.csv_file
start_date_str = args.start_date
end_date_str = args.end_date
output_dir = args.output_dir
clinic_folder = args.clinic_folder

log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'SOP_Exit_Notices_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("SOP Exit Notices Script started.")

base_dir = os.path.dirname(os.path.abspath(__file__))
templates_dir = args.templates_dir

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

exit_notice_template_path = os.path.join(templates_dir, 'Template.SOP Exit Notice.docx')
progress_report_template_path = os.path.join(templates_dir, 'Template.SOP Exit Progress Report.docx')
virtual_progress_report_template_path = os.path.join(templates_dir, 'Template.SOP Exit Progress Report Virtual.docx')

def docx_to_pdf(docx_path, pdf_dir, retries=3):
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

def align_tables_left(doc):
    for table in doc.tables:
        table.alignment = WD_TABLE_ALIGNMENT.LEFT

def fetch_image(url, doc, max_width=3.45, max_height=4.71, margin=0.2):
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

def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        if value.lower() == 'y':
            return 'Yes'
        elif value.lower() == 'n':
            return 'No'
    return value

def parse_and_format_date(date_str):
    """
    For regular dates (dob, exit_date, orientation_date, last_attended)
    -> format as m/d/yyyy (no leading zeros)
    """
    s = _normalize_date_string(date_str)
    if not s:
        return ''
    # try a couple explicit formats first, then fall back to pandas
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y'):
        try:
            d = datetime.strptime(s, fmt)
            return f"{d.month}/{d.day}/{d.year}"
        except ValueError:
            pass
    try:
        d = pd.to_datetime(s, errors='raise', dayfirst=False)
        return f"{d.month}/{d.day}/{d.year}"
    except Exception:
        return ''


def parse_and_format_p_a_date(date_str):
    """
    For P1..P27 and A1..A18 -> format as mm/dd/yy (with leading zeros)
    """
    s = _normalize_date_string(date_str)
    if not s:
        return ''
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y'):
        try:
            d = datetime.strptime(s, fmt)
            return d.strftime('%m/%d/%y')
        except ValueError:
            pass
    try:
        d = pd.to_datetime(s, errors='raise', dayfirst=False)
        return d.strftime('%m/%d/%y')
    except Exception:
        return ''

import re

def _normalize_date_string(s: str) -> str:
    if not isinstance(s, str):
        return ''
    # normalize dashes, strip ALL internal whitespace/newlines, trim commas
    s = s.replace('\u2013', '-').replace('\u2014', '-')
    s = re.sub(r'\s+', '', s)
    return s.strip().strip(',')


# Define function to determine fee problems
def determine_fee_problems(balance):
    try:
        balance = float(balance)
        return "No" if balance == 0 else "Yes"
    except ValueError:
        logging.error(f"Invalid balance value: {balance}")
        return "Unknown"

# Define function to format balance
def format_balance(balance):
    try:
        balance = float(balance)
        return f"${int(balance)}"  # Convert to whole number and format as currency
    except ValueError:
        return "$0"  # Default to $0 if invalid

def determine_stage_of_change(attended_str, required_str):
    try:
        attended = float(attended_str)
        required = float(required_str)
    except ValueError:
        logging.error(f"Invalid attended/required values: attended={attended_str}, required={required_str}")
        return 'Alert'

    if required == 0:
        return 'Alert'

    # Exact logic for 18-week program
    if required == 18:
        if 1 <= attended <= 4:
            return 'Precontemplation'
        elif 5 <= attended <= 8:
            return 'Contemplation'
        elif 9 <= attended <= 11:
            return 'Preparation'
        elif 12 <= attended <= 15:
            return 'Action'
        elif 16 <= attended <= 18:
            return 'Maintenance'
        else:
            logging.warning(f"Unexpected attendance value for SOP 18-week: attended={attended}")
            return 'Alert'

    # Exact logic for 27-week program
    if required == 27:
        if 1 <= attended <= 7:
            return 'Precontemplation'
        elif 8 <= attended <= 14:
            return 'Contemplation'
        elif 15 <= attended <= 18:
            return 'Preparation'
        elif 19 <= attended <= 23:
            return 'Action'
        elif 24 <= attended <= 27:
            return 'Maintenance'
        else:
            logging.warning(f"Unexpected attendance value for SOP 27-week: attended={attended}")
            return 'Alert'

    # Adaptive logic for other durations using 18-week proportions
    ratio = attended / required
    # Using the 18-week thresholds proportionally:
    # Precontemplation: ≤ 4/18 ≈ 0.2222
    # Contemplation: ≤ 8/18 ≈ 0.4444
    # Preparation: ≤ 11/18 ≈ 0.6111
    # Action: ≤ 15/18 ≈ 0.8333
    # Maintenance: > 0.8333

    if ratio > 0 and ratio <= (4/18):
        return 'Precontemplation'
    elif ratio <= (8/18):
        return 'Contemplation'
    elif ratio <= (11/18):
        return 'Preparation'
    elif ratio <= (15/18):
        return 'Action'
    elif ratio <= 1.0:
        return 'Maintenance'
    else:
        logging.warning(f"Ratio out of expected range for required={required}, attended={attended}")
        return 'Alert'

def fill_placeholders(row):
    # Set gender placeholders
    gender = row.get('gender', '').lower()
    if gender == 'male':
        row['gender1'] = 'his'
        row['gender2'] = 'he'
        row['gender3'] = "Men's"
        row['gender4'] = 'him'
        row['gender5'] = "He"
        row['gender6'] = 'himself'
        row['gender7'] = "Mr."
    elif gender == 'female':
        row['gender1'] = 'her'
        row['gender2'] = 'she'
        row['gender3'] = "Women's"
        row['gender4'] = 'her'
        row['gender5'] = "She"
        row['gender6'] = 'herself'
        row['gender7'] = "Ms."
    else:
        logging.error(f"Unrecognized gender: {gender}")
        for g in ['gender1','gender2','gender3','gender4','gender5','gender6','gender7']:
            row[g] = ''

    # Parse and format key dates
    for date_field in ['dob', 'exit_date', 'orientation_date', 'last_attended']:
        if date_field in row:
            row[date_field] = parse_and_format_date(row[date_field])

    # Inside fill_placeholders or wherever you handle attendance placeholders:
    # Clear any old values first:
    for i in range(1, 31):
        row[f'D{i}'] = ''

    # Fill placeholders up to the correct count:
    attended_str = row.get('attended', '0')
    required_str = row.get('required_sessions', '0')

    try:
        attended = float(attended_str)
        required = float(required_str)
    except ValueError:
        attended = 0
        required = 0

    if required > 0:
        # Determine how many of the D1..D30 placeholders should get the checkmark
        placeholders_to_fill = int((attended / required) * 30)
        for i in range(1, placeholders_to_fill + 1):
            row[f'D{i}'] = '\u2714'   # Heavy checkmark: '✔'


    # Replace '&' with 'and'
    if 'client_note' in row:
        row['client_note'] = row['client_note'].replace('&', 'and')

    # Format P1-P27 and A1-A18 as mm/dd/yy (format both cases so template gets the right one)
    for i in range(1, 28):
        for key in (f'p{i}', f'P{i}'):
            if key in row:
                row[key] = parse_and_format_p_a_date(row[key])

    for i in range(1, 19):
        for key in (f'a{i}', f'A{i}'):
            if key in row:
                row[key] = parse_and_format_p_a_date(row[key])


    # Determine client stage of change
    row['client_stagechange'] = determine_stage_of_change(attended, required)

    return row

def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

# Load CSV
try:
    df = pd.read_csv(csv_file_path)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

# Don’t assign '' into numeric dtypes; replace NaNs first, then cast
df = df.where(pd.notna(df), '')     # no dtype clash
df = df.astype(str)                 # now everything is string for templating


# Convert exit_date to datetime, filter, then convert back to string
df['exit_date'] = pd.to_datetime(df['exit_date'], errors='coerce')
df = df.dropna(subset=['exit_date'])

df = df[df['program_name'].isin(['SOP'])]
df = df[df['exit_reason'].isin(['Violation of Requirements', 'Unable to Participate', 'Death', 'Moved'])]
df = df.sort_values(by='exit_date', ascending=False)

if start_date_str and end_date_str:
    try:
        start_date = datetime.strptime(start_date_str, '%Y-%m-%d')
        end_date = datetime.strptime(end_date_str, '%Y-%m-%d')
        df = df[(df['exit_date'] >= start_date) & (df['exit_date'] <= end_date)]
    except Exception as e:
        logging.error(f"Error during date filtering: {e}")
        sys.exit(1)

if df.empty:
    logging.warning("No records found after filtering. No documents will be generated.")
    sys.exit(0)

# Convert exit_date back to string for parsing
df['exit_date'] = df['exit_date'].dt.strftime('%Y-%m-%d')

# Handle last_attended
def fix_last_attended(row):
    if not row['last_attended'].strip():
        return row['orientation_date']
    return row['last_attended']

df['last_attended'] = df.apply(fix_last_attended, axis=1)

# Apply determine_fee_problems and format_balance
df['feeproblems'] = df['balance'].apply(determine_fee_problems)
df['balance'] = df['balance'].apply(format_balance)

# -------------------------------------------------------------------
# Derive extra compliance / benchmark fields for DWAG template
# -------------------------------------------------------------------
# ---------------------------------------------------------------------------
# 1)  NORMALISE BOOLEAN / ENUM COLUMNS  (run once, right after df.astype(str))
# ---------------------------------------------------------------------------
# camelCase → snake_case expected by the template helpers
df = df.rename(columns={
    'speaksSignificantlyInGroup'        : 'speaks_significantly_in_group',
    'respectfulTowardsGroup'            : 'respectful_to_group',
    'takesResponsibilityForPastBehavior': 'takes_responsibility_for_past',
    'disruptiveOrArgumentitive'         : 'disruptive_argumentitive',
    'inappropriateHumor'                : 'humor_inappropriate',
    'blamesVictim'                      : 'blames_victim',
    'drug_alcohol'                      : 'appears_drug_alcohol',
    'inappropriate_behavior_to_staff'   : 'inappropriate_to_staff'
})

# convert 1/0 (or y/n) to the “Yes”/“No” strings used everywhere else
BOOL_COLS = [
    'speaks_significantly_in_group', 'respectful_to_group',
    'takes_responsibility_for_past', 'disruptive_argumentitive',
    'humor_inappropriate', 'blames_victim',
    'appears_drug_alcohol', 'inappropriate_to_staff'
]
for col in BOOL_COLS:
    df[col] = df[col].map({'1': 'Yes', '0': 'No', 'y': 'Yes', 'n': 'No'}).fillna('No')

# ---------------------------------------------------------------------------
# 2)  BENCHMARKS & FLAGS FOR PROGRESS-REPORT + EXIT NOTICE
# ---------------------------------------------------------------------------
ABSENCE_THRESHOLD = 0.10       # < 10 % un-excused absences = “consistent”
ABSENCE_LIMIT     = 3          # termination if ≥ 3 un-excused

def derive_benchmark_flags(row):
    # ------- attendance -------
    row['attended_initial_assessment'] = 'Yes' if row['orientation_date'] else 'No'

    try:
        attended   = float(row.get('attended', 0))
        unexcused  = float(row.get('absence_unexcused', 0))
        required   = float(row.get('required_sessions', 0))
    except ValueError:
        attended = unexcused = required = 0

    if required:
        ratio = (required - unexcused) / required
        row['consistent_attendance'] = 'Yes' if ratio >= (1 - ABSENCE_THRESHOLD) else 'No'
    else:
        row['consistent_attendance'] = 'No'

    row['three_or_more_absences'] = 'Yes' if unexcused >= ABSENCE_LIMIT else 'No'

    # ------- conduct / rule-compliance -------
    good_flags = all([
        row['speaks_significantly_in_group'] == 'Yes',
        row['respectful_to_group']           == 'Yes',
        row['takes_responsibility_for_past'] == 'Yes',
        row['disruptive_argumentitive']      == 'No',
        row['humor_inappropriate']           == 'No',
        row['blames_victim']                 == 'No',
    ])
    row['followed_group_rules'] = 'Yes' if good_flags else 'No'

    agreement_flags = all([
        row['appears_drug_alcohol'] == 'No',
        row['inappropriate_to_staff'] == 'No',
    ])
    row['adhered_to_participant_agreement'] = 'Yes' if agreement_flags else 'No'

    # ------- shortcuts reused in template -------
    row['participated_in_dialogue'] = row['speaks_significantly_in_group']
    row['accountability_consistent'] = row['takes_responsibility_for_past']
    return row

df = df.apply(derive_benchmark_flags, axis=1)

# ---------------------------------------------------------------------------
# 3)  BUILD ONE MERGED “EXIT REASONS” STRING
# ---------------------------------------------------------------------------
def build_exit_reasons(row):
    reasons = []

    # database-supplied exit_reason
    if row.get('exit_reason'):
        reasons.append(row['exit_reason'])

    # ≥ 3 un-excused absences
    try:
        unexcused = int(float(row.get('absence_unexcused', 0)))
    except ValueError:
        unexcused = 0
    if unexcused >= ABSENCE_LIMIT:
        reasons.append(f"{unexcused} un-excused absences")

    # conduct problems
    bad_map = {
        'disruptive_argumentitive'      : "Disruptive / argumentative in group",
        'humor_inappropriate'           : "Inappropriate humor",
        'blames_victim'                 : "Blamed victim for abuse",
        'respectful_to_group'           : "Disrespectful toward group members",
        'speaks_significantly_in_group' : "Did not participate in dialogue",
        'takes_responsibility_for_past' : "Refused accountability for past abuse",
        'appears_drug_alcohol'          : "Appeared under influence in group",
        'inappropriate_to_staff'        : "Inappropriate behavior toward staff",
    }
    for col, msg in bad_map.items():
        val = row[col]
        trigger = (val == 'Yes') if col not in ['respectful_to_group',
                                                'speaks_significantly_in_group',
                                                'takes_responsibility_for_past'] else (val == 'No')
        if trigger:
            reasons.append(msg)

    row['exit_reasons_block'] = "; ".join(reasons) if reasons else "See case notes"
    return row

df = df.apply(build_exit_reasons, axis=1)

# ---------------------------------------------------------------------------
# 4)  REMAINING PLACEHOLDERS
# ---------------------------------------------------------------------------
df = df.apply(fill_placeholders, axis=1)



# Convert Y/N to Yes/No
columns_to_convert = [
    "speaks_significantly_in_group", "respectful_to_group",
    "takes_responsibility_for_past", "disruptive_argumentitive",
    "humor_inappropriate", "blames_victim",
    "appears_drug_alcohol", "inappropriate_to_staff"
]
for col in columns_to_convert:
    df[col] = df[col].apply(convert_yn_to_yes_no)

df = df.apply(fill_placeholders, axis=1)

def render_and_save(template_path, doc_type, context, output_dir):
    try:
        doc = DocxTemplate(template_path)

        # Insert image if available
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc=doc)
            context['image'] = image if image else ''

        doc.render(context)
        align_tables_left(doc)

        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} {doc_type}.docx"
        docx_path = os.path.join(generated_documents_dir, docx_filename)

        doc.save(docx_path)
        time.sleep(1)

        pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
        if pdf_path:
            os.remove(docx_path)
            return pdf_path
        else:
            logging.error(f"PDF conversion failed for {docx_path}")
            return None
    except Exception as e:
        logging.error(f"Error rendering {doc_type}: {e}")
        raise

def generate_documents(row, output_dir_path):
    context = row.to_dict()

    # Add report_date (today's date) in m/d/yyyy format no leading zeros
    now = datetime.now()
    context['report_date'] = f"{now.month}/{now.day}/{now.year}"

    group_name = context.get("group_name", "")
    report_template = progress_report_template_path
    report_suffix = "Exit PReport"
    if "Virtual" in group_name or "Women" in group_name:
        report_template = virtual_progress_report_template_path
        report_suffix = "Exit PReport Vir"

    # Generate Exit Notice
    exit_pdf = render_and_save(exit_notice_template_path, "SOP Exit Notice", context, output_dir_path)
    # Generate Exit Progress Report
    progress_pdf = render_and_save(report_template, f"SOP {report_suffix}", context, output_dir_path)

    # Organize PDFs by case manager
    try:
        case_manager_office = sanitize_filename(row.get('case_manager_office', 'Unknown Office'))
        case_manager_first_name = sanitize_filename(row.get('case_manager_first_name', ''))
        case_manager_last_name = sanitize_filename(row.get('case_manager_last_name', ''))

        office_dir = os.path.join(output_dir_path, case_manager_office)
        manager_dir = os.path.join(office_dir, f"{case_manager_first_name} {case_manager_last_name}")
        os.makedirs(manager_dir, exist_ok=True)

        for pdf_file_path in [exit_pdf, progress_pdf]:
            if pdf_file_path and os.path.exists(pdf_file_path):
                shutil.move(pdf_file_path, os.path.join(manager_dir, os.path.basename(pdf_file_path)))
                logging.info(f"Moved {pdf_file_path} to {manager_dir}")
            else:
                logging.warning("PDF file not found or not generated.")
    except Exception as e:
        logging.error(f"Error organizing generated PDFs: {e}")

try:
    for _, row in df.iterrows():
        generate_documents(row, central_location_path)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)

logging.info("SOP Exit Notices Script completed successfully.")

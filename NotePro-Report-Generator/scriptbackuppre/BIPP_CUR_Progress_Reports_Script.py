#!/usr/bin/env python3
import pandas as pd
from docxtpl import DocxTemplate, InlineImage
from docx.shared import Inches
import logging
import requests
from io import BytesIO
import os
import sys
import mysql.connector
from datetime import datetime, timedelta
from PIL import Image
import argparse
import shutil
import subprocess
from docx.enum.table import WD_TABLE_ALIGNMENT
import time

################################################################################
# ARGUMENT PARSING
################################################################################

parser = argparse.ArgumentParser(description='Generate BIPP Curriculum Progress Reports')

parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='(Ignored) Start date')
parser.add_argument('--end_date', required=False, help='(Ignored) End date')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
# DB credentials
parser.add_argument('--db_host', required=True, help='Database hostname')
parser.add_argument('--db_user', required=True, help='Database username')
parser.add_argument('--db_pass', required=True, help='Database password')
parser.add_argument('--db_name', required=True, help='Database name')

args = parser.parse_args()

csv_file_path   = args.csv_file
output_dir      = args.output_dir
clinic_folder   = args.clinic_folder
templates_dir   = args.templates_dir
db_host         = args.db_host
db_user         = args.db_user
db_pass         = args.db_pass
db_name         = args.db_name

################################################################################
# LOGGING SETUP
################################################################################

log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'BIPP_CUR_Progress_Reports_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("BIPP Curriculum Progress Reports Script started.")

################################################################################
# FOLDER SETUP
################################################################################

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

################################################################################
# TEMPLATES
################################################################################

progress_report_template_path = os.path.join(templates_dir, 'Template.BIPP CUR Progress Report.docx')
virtual_progress_report_template_path = os.path.join(templates_dir, 'Template.BIPP CUR Progress Report Virtual.docx')

################################################################################
# DB CONNECTION
################################################################################

try:
    db_conn = mysql.connector.connect(
        host=db_host,
        user=db_user,
        password=db_pass,
        database=db_name
    )
    db_cursor = db_conn.cursor(dictionary=True)
    logging.info("Database connection established successfully.")
except Exception as e:
    logging.error(f"Failed to connect to DB: {e}")
    sys.exit(1)

################################################################################
# LOAD CURRICULUM BLANK NOTES (Excel)
################################################################################

curriculum_notes_path = os.path.join(templates_dir, "BIPP Curriculum Blank Notes.xlsx")
try:
    curriculum_df = pd.read_excel(curriculum_notes_path)
    logging.info(f"Loaded BIPP Curriculum Blank Notes from {curriculum_notes_path}, rows={len(curriculum_df)}.")
except Exception as e:
    logging.error(f"Could not read BIPP Curriculum Blank Notes.xlsx: {e}")
    sys.exit(1)

################################################################################
# HELPER FUNCTIONS
################################################################################

def docx_to_pdf(docx_path, pdf_dir, retries=3):
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
                raise FileNotFoundError(f"PDF not found at {output_file}")
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
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0',
            'Accept': 'image/*'
        }
        response = requests.get(url, headers=headers, timeout=30)
        if response.status_code == 200 and 'image' in response.headers.get('Content-Type', ''):
            img = Image.open(BytesIO(response.content))

            img_width, img_height = img.size
            aspect_ratio = img_width / img_height

            adjusted_max_width  = max_width - margin
            adjusted_max_height = max_height - margin

            # Resize to fit
            if aspect_ratio > 1:
                new_width  = min(adjusted_max_width, img_width)
                new_height = new_width / aspect_ratio
                if new_height > adjusted_max_height:
                    new_height = adjusted_max_height
                    new_width  = new_height * aspect_ratio
            else:
                new_height = min(adjusted_max_height, img_height)
                new_width  = new_height * aspect_ratio
                if new_width > adjusted_max_width:
                    new_width  = adjusted_max_width
                    new_height = new_width / aspect_ratio

            img = img.resize((int(new_width * 300), int(new_height * 300)), Image.LANCZOS)
            img_byte_arr = BytesIO()
            img.save(img_byte_arr, format='JPEG')
            return InlineImage(doc, BytesIO(img_byte_arr.getvalue()), width=Inches(new_width), height=Inches(new_height))
        else:
            logging.warning(f"Failed to fetch image or non-image content returned: {url}")
            return None
    except Exception as e:
        logging.error(f"Unexpected error fetching image: {e}")
        return None

def convert_yn_to_yes_no(value):
    if isinstance(value, str):
        val = value.strip().lower()
        if val == 'y':
            return 'Yes'
        elif val == 'n':
            return 'No'
    return value

def parse_and_format_date(date_str):
    """ Format date_str as m/d/yyyy if parseable. """
    if not date_str or not isinstance(date_str, str):
        return ''
    date_str = date_str.strip()
    if not date_str:
        return ''
    for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
        try:
            d = datetime.strptime(date_str, fmt)
            return f"{d.month}/{d.day}/{d.year}"  # m/d/yyyy
        except ValueError:
            continue
    return date_str

def parse_and_format_p_a_date(date_str):
    """ Format P# / A# date as mm/dd/yy if parseable. """
    if not date_str or not isinstance(date_str, str):
        return ''
    date_str = date_str.strip()
    if not date_str:
        return ''
    for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
        try:
            d = datetime.strptime(date_str, fmt)
            return d.strftime('%m/%d/%y')  # mm/dd/yy
        except ValueError:
            continue
    return ''

def determine_fee_problems(balance):
    """ Return 'No' if balance==0 else 'Yes' """
    try:
        b = float(balance)
        if b == 0:
            return "No"
        return "Yes"
    except:
        return ""

def format_balance(balance):
    """ Format as e.g. $40. """
    try:
        b = float(balance)
        return f"${int(b)}"
    except:
        return "$0"

def determine_stage_of_change(attended_str, required_str):
    """Same logic as your Stage-of-Change script."""
    try:
        attended = float(attended_str)
        required = float(required_str)
    except ValueError:
        logging.error(f"Invalid attended/required: {attended_str}, {required_str}")
        return 'Alert'

    if required == 0:
        return 'Alert'

    # 18-week logic
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
            logging.warning(f"Unexpected attendance for 18-week: {attended}")
            return 'Alert'

    # 27-week logic
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
            logging.warning(f"Unexpected attendance for 27-week: {attended}")
            return 'Alert'

    # Adaptive logic for other durations
    ratio = attended / required
    if ratio > 0   and ratio <= (4/18):
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
    """
    We replicate the SOC logic so that DOB, orientation_date, last_attended,
    P/A fields, D# checkmarks, stage_of_change, fee, etc. all work the same.
    """
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

    # Format key dates
    for date_field in ['dob', 'orientation_date', 'last_attended']:
        if date_field in row:
            row[date_field] = parse_and_format_date(row[date_field])

    # Mark D1..D30
    attended = row.get('attended', '0')
    required = row.get('required_sessions', '0')
    try:
        attended_f = float(attended)
        required_f = float(required)
    except ValueError:
        attended_f, required_f = 0, 0

    for i in range(1, 31):
        row[f'D{i}'] = ''

    # D1 is always a check
    row['D1'] = '\u2714'

    if required_f > 0:
        filled = int((attended_f / required_f) * 30)
        for i in range(2, filled + 1):
            if i <= 30:
                row[f'D{i}'] = '\u2714'

    # If there's a CSV-based client_note, replace & with 'and'
    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    # P1..P27, A1..A18 => mm/dd/yy
    for i in range(1, 28):
        p_field = f'P{i}'
        if p_field in row:
            row[p_field] = parse_and_format_p_a_date(row[p_field])

    for i in range(1, 19):
        a_field = f'A{i}'
        if a_field in row:
            row[a_field] = parse_and_format_p_a_date(row[a_field])

    # Stage of change
    row['client_stagechange'] = determine_stage_of_change(attended, required)

    return row

def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

################################################################################
# CSV LOADING + CLEANUP
################################################################################

try:
    df = pd.read_csv(csv_file_path, dtype=str)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    logging.error(f"Failed to load CSV file: {e}")
    sys.exit(1)

df.fillna('', inplace=True)  # everything is string, so blank is fine

# Convert Y/N to Yes/No
for col in df.columns:
    df[col] = df[col].apply(convert_yn_to_yes_no)

# We only want BIPP Not Exited
df['exit_date'] = pd.to_datetime(df['exit_date'], errors='coerce')
df = df[df['program_name'].isin(['BIPP (male)', 'BIPP (female)'])]
df = df[df['exit_reason'] == 'Not Exited']
if df.empty:
    logging.warning("No BIPP Not Exited clients found. Exiting.")
    sys.exit(0)

# Convert exit_date back to string if needed
df['exit_date'] = df['exit_date'].dt.strftime('%Y-%m-%d').fillna('')

# fix_last_attended if blank => orientation_date
def fix_last_attended(row):
    if not row['last_attended'].strip():
        return row['orientation_date']
    return row['last_attended']

df['last_attended'] = df.apply(fix_last_attended, axis=1)

# fee problems
df['feeproblems'] = df['balance'].apply(determine_fee_problems)
df['balance']     = df['balance'].apply(format_balance)

# Convert to numeric for placeholders
df['attended']           = pd.to_numeric(df['attended'], errors='coerce').fillna(0)
df['required_sessions']  = pd.to_numeric(df['required_sessions'], errors='coerce').fillna(0)

# Apply placeholders (DOB, orientation_date, D# checks, etc.)
df = df.apply(fill_placeholders, axis=1)

################################################################################
# DB + EXCEL => CLIENT_NOTE
################################################################################

def fetch_curriculum_note_for_client(client_id):
    """
    Fetches the curriculum notes based on the earliest curriculum taught in the last 22 days.
    If a single curriculum is found, it infers the next two in sequence.
    """

    today = datetime.now().date()
    start_date = today - timedelta(days=22)
    end_date   = today  # exclusive

    sql = """
        SELECT ts.date AS session_date, ts.curriculum_id AS cid
          FROM attendance_record ar
          JOIN therapy_session ts ON ar.therapy_session_id = ts.id
         WHERE ar.client_id = %s
           AND ts.date >= %s
           AND ts.date < %s
           AND ts.curriculum_id IS NOT NULL
           AND ts.curriculum_id <> ''
         ORDER BY ts.date ASC
    """
    db_cursor.execute(sql, (client_id, start_date, end_date))
    sessions = db_cursor.fetchall()

    if not sessions:
        return ""

    # Earliest session => earliest_cid => earliest long_description
    earliest_cid = sessions[0]['cid']
    db_cursor.execute("SELECT long_description FROM curriculum WHERE id = %s", (earliest_cid,))
    row_first = db_cursor.fetchone()
    if not row_first or not row_first['long_description']:
        return ""
    
    first_sd = row_first['long_description'].strip()

    # Identify curriculum sequence
    curriculum_names = list(curriculum_df['Curriculum Name'])
    try:
        idx = curriculum_names.index(first_sd)
    except ValueError:
        logging.warning(f"Could not find '{first_sd}' in BIPP Curriculum Blank Notes.")
        return ""

    # Fetch the next two sequential curriculum notes (wrapping around if needed)
    num_total = len(curriculum_names)
    wanted_sds = [curriculum_names[(idx + offset) % num_total] for offset in range(3)]

    final_lines = []
    for i, sd in enumerate(wanted_sds, start=1):
        sub_df = curriculum_df[curriculum_df['Curriculum Name'] == sd]
        if sub_df.empty:
            logging.warning(f"Curriculum Name '{sd}' not found in BIPP Curriculum Blank Notes.")
            continue

        for _, subrow in sub_df.iterrows():
            part_name = str(subrow.get('Part Name', '')).strip()
            part_note = str(subrow.get('Part Note', '')).strip()

            if part_name:
                labeled_part_name = f"**{part_name} (Week {i})**"
                final_lines.append(labeled_part_name)

            if part_note:
                final_lines.append(part_note)

            final_lines.append("")

    return "\n".join(final_lines).strip()



def render_and_save(template_path, doc_type, context, output_dir):
    try:
        doc = DocxTemplate(template_path)
        # Insert image if available
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc)
            context['image'] = image if image else ''

        doc.render(context)
        align_tables_left(doc)

        first_name = sanitize_filename(context.get('first_name', ''))
        last_name  = sanitize_filename(context.get('last_name',  ''))
        docx_filename = f"{last_name} {first_name} BIPP Curriculum PReport.docx"
        docx_path = os.path.join(output_dir, docx_filename)

        doc.save(docx_path)
        time.sleep(1)

        pdf_path = docx_to_pdf(docx_path, output_dir)
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
    # We already have row placeholders from fill_placeholders
    context = row.to_dict()

    # ------------------------------------------------------------------
    # 1) Compute friendly date range for {{client_note_dates}} 
    #    e.g. "January 14th, 2025 through February 4th, 2025"
    # ------------------------------------------------------------------
    # Usually you want 22 days ago as the start and "yesterday" as the end
    start_dt = datetime.now().date() - timedelta(days=22)  # e.g. Jan 14
    end_dt   = datetime.now().date() - timedelta(days=1)   # e.g. Feb 4
    
    def format_friendly_date(dt):
        """Optional: add day-number suffix (1st, 2nd, 3rd, etc.)"""
        # Quick suffix logic:
        day = dt.day
        if 11 <= day % 100 <= 13:
            suffix = "th"
        elif day % 10 == 1:
            suffix = "st"
        elif day % 10 == 2:
            suffix = "nd"
        elif day % 10 == 3:
            suffix = "rd"
        else:
            suffix = "th"
        return dt.strftime(f"%B {day}{suffix}, %Y")

    start_str = format_friendly_date(start_dt)
    end_str   = format_friendly_date(end_dt)
    context["client_note_dates"] = f"{start_str} through {end_str}"

    # Overwrite 'client_note' from DB + Excel
    client_id_str = context.get("client_id", "")
    if not client_id_str.isdigit():
        logging.warning(f"Row has invalid client_id={client_id_str}. Skipping DB logic.")
        context["client_note"] = ""
    else:
        client_id = int(client_id_str)
        # Fetch final note from the function below
        final_note = fetch_curriculum_note_for_client(client_id)

        # ---------------------------------------------------------
        # Manual string replacement for the placeholders 
        # ({{first_name}}, {{gender1}}, etc.)
        # ---------------------------------------------------------
        replacements = {
            "{{first_name}}": context.get("first_name", ""),
            "{{gender1}}":    context.get("gender1", ""),
            "{{gender2}}":    context.get("gender2", ""),
            "{{gender3}}":    context.get("gender3", ""),
            "{{gender4}}":    context.get("gender4", ""),
            "{{gender5}}":    context.get("gender5", ""),
            "{{gender6}}":    context.get("gender6", ""),
        }
        for placeholder, value in replacements.items():
            final_note = final_note.replace(placeholder, value)

        context["client_note"] = final_note

    # The doc uses "report_date" for today's date
    now = datetime.now()
    context['report_date'] = f"{now.month}/{now.day}/{now.year}"

    # Pick the appropriate Word template
    group_name = context.get("group_name", "")
    report_template = progress_report_template_path
    if ("Virtual" in group_name) or ("Women" in group_name):
        report_template = virtual_progress_report_template_path

    pdf_path = render_and_save(report_template, "PReport", context, output_dir_path)

    # Move PDF into case manager folder
    case_manager_office = sanitize_filename(row.get('case_manager_office', 'Unknown Office'))
    cm_first = sanitize_filename(row.get('case_manager_first_name', ''))
    cm_last  = sanitize_filename(row.get('case_manager_last_name',  ''))
    office_dir = os.path.join(output_dir_path, case_manager_office)
    manager_dir = os.path.join(office_dir, f"{cm_first} {cm_last}")
    os.makedirs(manager_dir, exist_ok=True)

    if pdf_path and os.path.exists(pdf_path):
        final_path = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, final_path)
        logging.info(f"Moved {pdf_path} to {final_path}")
    else:
        logging.warning("PDF file not found or not generated.")


################################################################################
# MAIN LOOP
################################################################################

try:
    for idx, row in df.iterrows():
        generate_documents(row, central_location_path)
except Exception as e:
    logging.error(f"Error during document generation loop: {e}")
    sys.exit(1)
finally:
    db_cursor.close()
    db_conn.close()

logging.info("BIPP Curriculum Progress Reports Script completed successfully.")

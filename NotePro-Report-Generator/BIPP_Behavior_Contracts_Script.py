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
parser = argparse.ArgumentParser(description='Generate Behavior Contracts for BIPP Clients')
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

# --- Env overrides to match other BIPP scripts ---
program_code = os.environ.get('NP_PROGRAM_CODE', 'BIPP')
templates_dir = os.environ.get('NP_TEMPLATES_DIR', templates_dir)
clinic_folder = os.environ.get('NP_CLINIC_FOLDER', clinic_folder)

# after env overrides
if os.path.basename(templates_dir) == clinic_folder:
    _TEMPLATES_ROOT = os.path.dirname(templates_dir)
else:
    _TEMPLATES_ROOT = templates_dir

# ------------------------------------------------------------------------------
# 2) Set up Logging
# ------------------------------------------------------------------------------
log_dir = '/home/notesao/NotePro-Report-Generator/logs'
os.makedirs(log_dir, exist_ok=True)
logging.basicConfig(
    filename=os.path.join(log_dir, f'BIPP_Behavior_Contract_{datetime.now().strftime("%Y%m%d")}.log'),
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s'
)
logging.info("BIPP Behavior Contract Script started.")

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

# Per-run human-friendly error sink
errors_txt = os.path.join(central_location_path, 'errors.txt')

def record_error(msg: str):
    try:
        with open(errors_txt, 'a', encoding='utf-8') as f:
            f.write(msg.rstrip() + '\n')
    except Exception as e:
        logging.error(f"Failed writing to errors.txt: {e}")
    logging.warning(msg)

# ------------------------------------------------------------------------------
# 4) Resolve template (clinic → default/{program_code} → default/BIPP)
# ------------------------------------------------------------------------------
def resolve_template(templates_root: str, clinic: str, program: str, filename: str):
    candidates = [
        os.path.join(templates_root, clinic, filename),
        os.path.join(templates_root, 'default', program, filename),
        os.path.join(templates_root, 'default', 'BIPP', filename),
    ]
    for p in candidates:
        if os.path.isfile(p):
            return p
    return None

CANDIDATES = [
    'Template.BIPP Behavior Contract.docx',
    'Template.Behavior Contract.docx',
]
behavior_contract_template_path = None
for _name in CANDIDATES:
    p = resolve_template(_TEMPLATES_ROOT, clinic_folder, program_code, _name)
    if p:
        behavior_contract_template_path = p
        break
if not behavior_contract_template_path:
    record_error(f"[FATAL] Missing behavior contract template. Tried: {', '.join(CANDIDATES)} in {_TEMPLATES_ROOT}.")
    sys.exit(0)
logging.info(f"Using template: {behavior_contract_template_path}")


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
        if not isinstance(url, str) or not url.lower().startswith(('http://','https://')):
            logging.warning(f"Skipping non-http image_url: {url}")
            return None

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

# --- Date parsing & optional date-window filter ---
KNOWN_INPUT_FMTS = ['%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y', '%Y/%m/%d']

def coerce_dt(s):
    if not s or not isinstance(s, str):
        return None
    v = s.strip()
    if not v or v.lower() in ('nan', 'none', 'null'):
        return None
    for fmt in KNOWN_INPUT_FMTS:
        try:
            return datetime.strptime(v, fmt)
        except ValueError:
            continue
    return None

def filter_by_date_window(df: pd.DataFrame, start_s: str | None, end_s: str | None) -> pd.DataFrame:
    if not start_s and not end_s:
        return df
    start_dt = coerce_dt(start_s) if start_s else None
    end_dt   = coerce_dt(end_s)   if end_s   else None

    date_cols_priority = ['behavior_contract_date', 'orientation_date', 'enrollment_date', 'first_session_date']
    date_col = next((c for c in date_cols_priority if c in df.columns), None)
    if not date_col:
        record_error("No date column found for filtering (looked for behavior_contract_date/orientation_date/enrollment_date/first_session_date). Skipping date filter.")
        return df

    dts = df[date_col].apply(coerce_dt)
    mask = pd.Series([True]*len(df), index=df.index)

    if start_dt:
        mask &= dts.apply(lambda x: x is not None and x >= start_dt)
    if end_dt:
        mask &= dts.apply(lambda x: x is not None and x <= end_dt)
    out = df[mask]
    if out.empty:
        record_error(f"No rows inside date window using {date_col}.")
    return out


# ------------------------------------------------------------------------------
# 6) Read the CSV
# ------------------------------------------------------------------------------
try:
    # Force all columns to string
    df = pd.read_csv(csv_file_path, dtype=str)
    logging.info(f"Loaded {len(df)} records from CSV.")
except Exception as e:
    record_error(f"Failed to load CSV file: {e}")
    sys.exit(0)

df.fillna('', inplace=True)
df = filter_by_date_window(df, args.start_date, args.end_date)


# If you have any Y/N fields to convert:
for col in df.columns:
    df[col] = df[col].apply(convert_yn_to_yes_no)

# --------------------------------------------------------------------------
# 7) Filter to BIPP + Behavior Contract Status
# --------------------------------------------------------------------------
# Must be BIPP (male or female). Adjust if your CSV uses different strings.
if 'program_name' not in df.columns:
    record_error("Missing 'program_name' column in CSV. Exiting non-fatally.")
    sys.exit(0)

bipp_filter = df['program_name'].isin(['BIPP (male)', 'BIPP (female)'])

# behavior_contract_status must be "Needed" or "Signed"
if 'behavior_contract_status' not in df.columns:
    record_error("Missing 'behavior_contract_status' column in CSV. Exiting non-fatally.")
    sys.exit(0)

bc_filter = df['behavior_contract_status'].isin(['Needed', 'Signed'])

df = df[bipp_filter & bc_filter]

if df.empty:
    logging.warning("No records found matching BIPP + Behavior Contract 'Needed' or 'Signed'. Exiting.")
    sys.exit(0)

logging.info(f"Filtered down to {len(df)} records needing Behavior Contracts.")


def normalize_gender(row: pd.Series) -> str:
    pn = (row.get('program_name') or '').lower()
    g  = (row.get('gender') or row.get('gender_text') or '').strip().lower()
    gid = (row.get('gender_id') or '').strip()
    if 'male' in pn:   return 'male'
    if 'female' in pn: return 'female'
    if g in ('m','male','man','men'): return 'male'
    if g in ('f','female','woman','women'): return 'female'
    if gid == '2': return 'male'
    if gid == '3': return 'female'
    return 'unknown'

def attach_pronouns(ctx: dict, norm_gender: str):
    if norm_gender == 'male':
        ctx.update({'gender1':'he','gender2':'him','gender3':'his','gender4':'himself','gender5':'Mr.'})
    elif norm_gender == 'female':
        ctx.update({'gender1':'she','gender2':'her','gender3':'her','gender4':'herself','gender5':'Ms.'})
    else:
        ctx.update({'gender1':'they','gender2':'them','gender3':'their','gender4':'themself','gender5':''})


# ------------------------------------------------------------------------------
# 8) Prepare Data / Placeholders
# ------------------------------------------------------------------------------
def fill_placeholders(row):
    row_dict = row.to_dict()

    # Normalize NaN-ish
    for k, v in list(row_dict.items()):
        if isinstance(v, str) and v.strip().lower() in ('nan', 'none', 'null'):
            row_dict[k] = ''

    # Report date (today)
    now = datetime.now()
    row_dict['report_date'] = f"{now.month}/{now.day}/{now.year}"

    # Common date fields
    for date_field in ['dob', 'orientation_date', 'enrollment_date', 'behavior_contract_date']:
        if date_field in row_dict:
            row_dict[date_field] = parse_and_format_date(row_dict[date_field])

    # Fee formatting
    if 'fee' in row_dict and row_dict['fee']:
        try:
            row_dict['fee'] = f"${float(str(row_dict['fee']).replace('$','')):.2f}"
        except ValueError:
            row_dict['fee'] = '$0.00'

    # Pronouns / honorific
    ng = normalize_gender(row)
    attach_pronouns(row_dict, ng)

    # Name cleanup
    for nm in ('first_name', 'last_name'):
        if nm in row_dict and isinstance(row_dict[nm], str):
            row_dict[nm] = row_dict[nm].strip()

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
        safe_base  = (f"{last_name} {first_name}".strip() or f"client_{row_data.get('client_id','unknown')}")
        docx_filename = f"{safe_base} BIPP Behavior Contract.docx"
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
        record_error(f"Render failed for '{row_data.get('first_name','')}' '{row_data.get('last_name','')}': {e}")
        return None


# ------------------------------------------------------------------------------
# 10) Generate Contracts & Organize
# ------------------------------------------------------------------------------
def generate_behavior_contracts(row, output_dir_path):
    """
    Actually generate the Word/PDF, then move into subfolders if needed.
    """
    pdf_path = render_and_save_behavior_contract(row, output_dir_path)

    # You might want to sort them by case manager office
    def pick(row, *keys, default=''):
        for k in keys:
            if k in row and str(row[k]).strip():
                return str(row[k]).strip()
        return default

    case_manager_office = sanitize_filename(
        pick(row, 'case_manager_office', 'facilitator_office', 'instructor_office', default='Unknown Office')
    )
    cm_first = sanitize_filename(
        pick(row, 'case_manager_first_name', 'facilitator_first_name', 'instructor_first_name', default='')
    )
    cm_last  = sanitize_filename(
        pick(row, 'case_manager_last_name',  'facilitator_last_name',  'instructor_last_name',  default='')
    )

    office_dir  = os.path.join(output_dir_path, case_manager_office or 'Unknown Office')
    manager_dir = os.path.join(office_dir, f"{cm_first} {cm_last}".strip() or 'Unknown Manager')
    os.makedirs(manager_dir, exist_ok=True)


    if pdf_path and os.path.exists(pdf_path):
        new_location = os.path.join(manager_dir, os.path.basename(pdf_path))
        shutil.move(pdf_path, new_location)
        logging.info(f"Moved {pdf_path} to {new_location}")
        return new_location  # <-- return truthy path so caller counts 'processed'
    else:
        logging.warning("PDF file not found or not generated for row. Possibly conversion failed.")
        return None


# ------------------------------------------------------------------------------
# 11) Main Loop (per-row isolation so one bad row never sinks the run)
# ------------------------------------------------------------------------------
processed = 0
skipped   = 0

try:
    for idx, row in df.iterrows():
        try:
            fn = (row.get('first_name') or '').strip()
            ln = (row.get('last_name') or '').strip()
            if not fn or not ln:
                skipped += 1
                record_error(f"[row {idx}] Missing first/last name → skipped.")
                continue

            prog = (row.get('program_name') or '').strip()
            if prog not in ('BIPP (male)', 'BIPP (female)'):
                skipped += 1
                record_error(f"[row {idx}] Non-BIPP program '{prog}' → skipped.")
                continue

            pdf_path = generate_behavior_contracts(row, central_location_path)
            if pdf_path:
                processed += 1
            else:
                skipped += 1
        except Exception as row_e:
            skipped += 1
            record_error(f"[row {idx}] Unexpected error: {row_e}")
            continue
except Exception as e:
    logging.error(f"Fatal loop error: {e}")
    record_error(f"[FATAL] Loop crashed: {e}")
    sys.exit(0)  # keep UI responsive

logging.info(f"BIPP Behavior Contract Script completed. Processed={processed}, Skipped={skipped}")
print(f"Behavior Contracts complete. Processed={processed}, Skipped={skipped}")
sys.exit(0)


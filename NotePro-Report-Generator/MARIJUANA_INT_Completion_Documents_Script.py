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
import zipfile
import time

# Configure logging
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s [%(levelname)s] %(message)s')

# Parse command-line arguments
parser = argparse.ArgumentParser(description='Generate Marijuana Intervention Completion Documents')
parser.add_argument('--csv_file', required=True, help='Path to the CSV file')
parser.add_argument('--start_date', required=False, help='Start date for the reports')
parser.add_argument('--end_date', required=False, help='End date for the reports')
parser.add_argument('--output_dir', required=False, help='Path to the output directory')  # Added argument for output_dir
parser.add_argument('--clinic_folder', required=True, help='Clinic folder identifier')
parser.add_argument('--templates_dir', required=True, help='Path to the templates directory')
args = parser.parse_args()

csv_file_path = args.csv_file
start_date = args.start_date
end_date = args.end_date
output_dir = args.output_dir  # Capture the output_dir argument

# Define clinic folder
clinic_folder = args.clinic_folder

# Define base directories
base_dir = os.path.dirname(os.path.abspath(__file__))
templates_dir = args.templates_dir
today = datetime.now().strftime('%m.%d.%y')

# Determine the generated documents directory (for initial DOCX and PDF generation)
if output_dir:
    generated_documents_dir = output_dir
else:
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today)
os.makedirs(generated_documents_dir, exist_ok=True)

# Public folder for downloads
public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
os.makedirs(public_generated_documents_dir, exist_ok=True)

# Define a central location path for final sorted PDFs, similar to the completion script
central_location_path = os.path.join(public_generated_documents_dir, today)
os.makedirs(central_location_path, exist_ok=True)
logging.info(f"Central location directory created: {central_location_path}")

# Define template file paths
completion_certificate_file_path = os.path.join(templates_dir, 'Template.MARIJUANA INT Completion Certificate.docx')
completion_letter_file_path = os.path.join(templates_dir, 'Template.MARIJUANA INT Completion Letter.docx')
completion_report_file_path = os.path.join(templates_dir, 'Template.MARIJUANA INT Completion Progress Report.docx')
completion_virtual_report_file_path = os.path.join(templates_dir, 'Template.MARIJUANA INT Completion Progress Report Virtual.docx')

# Define a function to convert DOCX to PDF using LibreOffice with retry logic
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
            time.sleep(2)  # Wait for 2 seconds before retrying
    logging.error(f"Failed to convert {docx_path} to PDF after {retries} attempts.")
    return None

# Function to align all tables in a document to the left
def align_tables_left(doc):
    for table in doc.tables:
        table.alignment = WD_TABLE_ALIGNMENT.LEFT

# Function to fetch and prepare images
def fetch_image(url, doc, max_width=3.45, max_height=4.71, margin=0.2):
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
        'Accept': 'image/*'  # Accept any image type
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
            logging.warning("Failed to fetch image from URL or non-image content returned: %s", url)
            return None
    except requests.RequestException as e:
        logging.warning("Request failed for URL %s with error: %s", url, e)
        return None
    except Exception as e:
        logging.error("Unexpected error: %s", e)
        return None

def format_date(value, formats=None):
    if formats is None:
        formats = ['%m/%d/%Y', '%Y-%m-%d', '%d-%m-%Y', '%Y/%m/%d']
    for fmt in formats:
        try:
            date = datetime.strptime(value, fmt)
            return date.strftime('%m/%d/%Y')
        except ValueError:
            continue
    logging.warning(f"Unrecognized date format for value: {value}")
    return value

# Function to parse dates with multiple formats
def parse_date(date_str):
    date_formats = ['%m/%d/%Y', '%Y-%m-%d', '%m/%d/%y']  # Add more formats if needed
    for fmt in date_formats:
        try:
            return datetime.strptime(date_str, fmt)
        except ValueError:
            continue
    raise ValueError(f"Date {date_str} does not match any of the expected formats.")

# Function to fill placeholders in the template
def fill_placeholders(row):
    def parse_multiple_formats(date_str, formats=['%m/%d/%y', '%m/%d/%Y', '%Y-%m-%d', '%Y-%m-%d %H:%M:%S']):
        date_str = str(date_str).strip()
        for fmt in formats:
            try:
                return datetime.strptime(date_str, fmt)
            except ValueError:
                continue
        return None  # If no format matches

    # Replace 'nan' or missing values with empty strings
    for placeholder, value in row.items():
        if pd.isnull(value) or str(value).strip().lower() == 'nan':
            row[placeholder] = ''

    # Specific handling for fields like officer names or locations
    row['case_manager_first_name'] = row.get('case_manager_first_name', '').strip()
    row['case_manager_last_name'] = row.get('case_manager_last_name', '').strip()
    row['case_manager_office'] = row.get('case_manager_office', '').strip()

    if not row['case_manager_first_name']:
        row['case_manager_first_name'] = ''  # Default to blank if missing

    if not row['case_manager_last_name']:
        row['case_manager_last_name'] = ''  # Default to blank if missing

    if not row['case_manager_office']:
        row['case_manager_office'] = ''  # Default to blank if missing

    for placeholder, value in row.items():
        if placeholder.endswith('_date') and pd.notnull(value):
            # If the value is already a datetime or timestamp, just format it directly
            if isinstance(value, (datetime, pd.Timestamp)):
                row[placeholder] = value.strftime('%m/%d/%Y')
            else:
                # Try multiple formats for string values, including a datetime with time component
                date_obj = parse_multiple_formats(value, ['%m/%d/%Y', '%Y-%m-%d', '%Y-%m-%d %H:%M:%S'])
                if date_obj:
                    row[placeholder] = date_obj.strftime('%m/%d/%Y')
                else:
                    logging.error(f"Error formatting date for placeholder {placeholder}: {value} did not match expected formats")

    # For completions, we assume all sessions are fulfilled
    for i in range(1, 31):
        # Use the same Unicode check as your Exit Notices script:
        row[f'D{i}'] = '\u2714'

    # Process 'P1' through 'P27' placeholders
    for i in range(1, 28):
        placeholder = f'P{i}'
        if placeholder in row:
            val = row[placeholder]
            if pd.isnull(val) or str(val).strip().lower() == 'nan':
                row[placeholder] = ''  # Leave blank if no valid value
            else:
                # Attempt to format as a date
                date_obj = parse_multiple_formats(val)
                if date_obj:
                    row[placeholder] = date_obj.strftime('%m/%d/%y')  # Format as m/d/yyyy
                else:
                    row[placeholder] = ''  # Default to blank if not a valid date

    # Process 'A1' through 'A18' placeholders
    for i in range(1, 19):
        placeholder = f'A{i}'
        if placeholder in row:
            val = row[placeholder]
            if pd.isnull(val) or str(val).strip().lower() == 'nan':
                row[placeholder] = ''  # Leave blank if no valid value
            else:
                # Attempt to format as a date
                date_obj = parse_multiple_formats(val)
                if date_obj:
                    row[placeholder] = date_obj.strftime('%m/%d/%y')  # Format as m/d/yyyy
                else:
                    row[placeholder] = ''  # Default to blank if not a valid date

    if 'client_note' in row and isinstance(row['client_note'], str):
        row['client_note'] = row['client_note'].replace('&', 'and')

    if 'dob' in row:
        # If dob might also have time, we can handle that as well:
        if isinstance(row['dob'], (datetime, pd.Timestamp)):
            row['dob'] = row['dob'].strftime('%m/%d/%Y')
        else:
            # Try flexible formatting
            date_obj = parse_multiple_formats(row['dob'], ['%m/%d/%Y','%Y-%m-%d','%Y-%m-%d %H:%M:%S'])
            if date_obj:
                row['dob'] = date_obj.strftime('%m/%d/%Y')

    if 'exit_date' in row and row['exit_date']:
        val = row['exit_date'].strip().lower()
        if val == 'nan' or val == '':
            row['exit_date'] = ''
        else:
            date_obj = parse_multiple_formats(row['exit_date'], ['%m/%d/%Y', '%Y-%m-%d', '%Y-%m-%d %H:%M:%S'])
            if date_obj:
                row['exit_date'] = date_obj.strftime('%m/%d/%Y')
            else:
                # If date not recognized, set blank or log an error
                row['exit_date'] = ''

    return row

# Function to sanitize filenames
def sanitize_filename(filename):
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '')
    return filename.strip()

# Function to convert all DOCX files in a directory to PDF and delete the original DOCX files
def convert_all_to_pdf_and_delete(directory_path):
    for filename in os.listdir(directory_path):
        if filename.endswith('.docx'):
            docx_path = os.path.join(directory_path, filename)
            pdf_path = os.path.join(directory_path, filename[:-5] + '.pdf')
            docx_to_pdf(docx_path, os.path.dirname(pdf_path))  # Convert DOCX to PDF
            if os.path.exists(pdf_path):
                os.remove(docx_path)  # Remove the DOCX file after conversion
                logging.info(f"Deleted the DOCX file: {docx_path}")
            else:
                logging.error(f"PDF conversion failed for: {docx_path}")

# Formatting date without leading zeros
def format_date_without_zeros(date_obj):
    return f"{date_obj.month}/{date_obj.day}/{date_obj.year}"

# Function to determine fee problems based on balance
def determine_fee_problems(balance):
    try:
        balance = float(balance)
        return "No" if balance == 0 else "Yes"
    except ValueError:
        logging.error(f"Invalid balance value: {balance}")
        return "Unknown"

# Function to format balance as whole numbers with a dollar sign
def format_balance(balance):
    try:
        balance = float(balance)
        return f"${int(balance)}"  # Convert to whole number and format as currency
    except ValueError:
        logging.error(f"Invalid balance value: {balance}")
        return "$0"  # Default to $0 if the value is invalid

# Load the CSV file into a DataFrame
try:
    df = pd.read_csv(csv_file_path)
    logging.debug(f"Data loaded from CSV. Number of records: {len(df)}")
except Exception as e:
    error_message = f"Failed to load CSV file: {e}"
    logging.error(f"Failed to load CSV file: {e}")
    # If task ID handling or external notification is relevant:
    # update_status(status_file, 'failed', error_message)
    sys.exit(1)  # Gracefully exit with a logged error if the CSV is critical

# Convert the 'exit_date' column to datetime and filter valid entries
df['exit_date'] = pd.to_datetime(df['exit_date'], errors='coerce')
df = df.dropna(subset=['exit_date'])

# Filter by program names and sort by exit date
df = df[df['program_name'].isin(['Marijuana Intervention'])]
logging.debug(f"DataFrame filtered by program_name:\n{df.head()}")

df = df.sort_values(by='exit_date', ascending=False)
logging.debug(f"DataFrame sorted by exit_date:\n{df.head()}")

# Filter the DataFrame by the provided date range and completion status
try:
    if start_date and end_date:
        start_date_obj = datetime.strptime(start_date, '%Y-%m-%d')
        end_date_obj = datetime.strptime(end_date, '%Y-%m-%d')
        df_filtered = df[
            (df['exit_date'] >= start_date_obj) &
            (df['exit_date'] <= end_date_obj) &
            (df['exit_reason'] == 'Completion of Program')
        ]
        logging.info(f"Number of records after filtering by date range and exit reason: {len(df_filtered)}")
    else:
        df_filtered = df[df['exit_reason'] == 'Completion of Program']
        logging.info(f"Number of records after filtering by exit reason: {len(df_filtered)}")
except Exception as e:
    logging.error(f"Error during DataFrame filtering: {e}")
    sys.exit(1)

# Check if filtered DataFrame is empty
if df_filtered.empty:
    logging.warning("DataFrame is empty after filtering. No documents will be generated.")
    sys.exit(0)
else:
    # Process filtered DataFrame
    df_filtered = df_filtered.copy()

    # Handle missing fields and apply logic for fee problems
    df_filtered['last_attended'] = df_filtered.apply(
        lambda row: row['orientation_date'] if pd.isnull(row['last_attended']) else row['last_attended'], axis=1
    )
    df_filtered = df_filtered.dropna(subset=['exit_date'])
    # Apply determine_fee_problems to calculate fee problems
    df_filtered['feeproblems'] = df_filtered['balance'].apply(determine_fee_problems)

    # Format balance as whole numbers with a dollar sign
    df_filtered['balance'] = df_filtered['balance'].apply(format_balance)

    # Convert entire DataFrame to string type and fill placeholders
    df_filtered = df_filtered.astype(str)
    df_filtered = df_filtered.apply(fill_placeholders, axis=1)
    df_filtered.fillna('', inplace=True)

    # Initialize output directories
    today = datetime.now().strftime('%m.%d.%y')
    generated_documents_dir = output_dir or os.path.join(base_dir, 'GeneratedDocuments', clinic_folder, today)
    os.makedirs(generated_documents_dir, exist_ok=True)

    public_generated_documents_dir = f"/home/notesao/{clinic_folder}/public_html/GeneratedDocuments"
    os.makedirs(public_generated_documents_dir, exist_ok=True)

    central_location_path = os.path.join(public_generated_documents_dir, today)
    os.makedirs(central_location_path, exist_ok=True)
    logging.info(f"Central location directory created: {central_location_path}")

# Function to render and save documents
def render_and_save(template_path, doc_type, context, output_dir):
    try:
        # Load and render the template
        doc = DocxTemplate(template_path)
        
        # Fetch and insert images if applicable
        if 'image_url' in context and context['image_url']:
            image = fetch_image(context['image_url'], doc=doc)
            context['image'] = image if image else ''  # Use fetched image or default to empty
        
        # Fill placeholders and align tables
        doc.render(context)
        align_tables_left(doc)
        
        # Generate file name
        first_name = sanitize_filename(context.get('first_name', ''))
        last_name = sanitize_filename(context.get('last_name', ''))
        docx_filename = f"{last_name} {first_name} {doc_type}.docx"
        docx_path = os.path.join(output_dir, docx_filename)
        
        # Save the rendered DOCX
        doc.save(docx_path)
        logging.info(f"Generated DOCX: {docx_path}")
        return docx_path

    except Exception as e:
        logging.error(f"Error rendering and saving {doc_type}: {e}")
        raise


# Main processing loop for filtered DataFrame
if df_filtered.empty:
    logging.warning("DataFrame is empty after filtering. No documents will be generated.")
    sys.exit(0)
else:
    # Ensure placeholders are filled
    df_filtered = df_filtered.apply(fill_placeholders, axis=1)
    df_filtered.fillna('', inplace=True)

    # Store DOCX paths for conversion
    generated_docx_files = []

    for index, row in df_filtered.iterrows():
        try:
            context = row.to_dict()

            # Format dates as needed
            if 'orientation_date' in context and pd.notnull(context['orientation_date']):
                context['orientation_date'] = pd.to_datetime(context['orientation_date']).strftime('%m/%d/%Y')
            else:
                context['orientation_date'] = ''

            # Set gender placeholders
            gender = context.get('gender', '').lower()
            if gender == 'male':
                context.update({
                    'gender1': 'his', 'gender2': 'he', 'gender3': "Men's",
                    'gender4': 'him', 'gender5': "He", 'gender6': "His", 'gender7': "women", 'gender8': "Mr."
                })
            elif gender == 'female':
                context.update({
                    'gender1': 'her', 'gender2': 'she', 'gender3': "Women's",
                    'gender4': 'her', 'gender5': "She", 'gender6': "Her", 'gender7': "others", 'gender8': "Ms."
                })
            else:
                logging.error(f"Gender '{gender}' not recognized for row {index}.")

            # Render and save documents
            letter_docx = render_and_save(completion_letter_file_path, "MARI INT Comp Letter", context, generated_documents_dir)
            certificate_docx = render_and_save(completion_certificate_file_path, "MARI INT Comp Cert", context, generated_documents_dir)
            progress_template = completion_virtual_report_file_path if "Virtual" in row['group_name'] else completion_report_file_path
            progress_report_docx = render_and_save(progress_template, "MARI INT PReport", context, generated_documents_dir)

            # Add to list for conversion
            generated_docx_files.extend([letter_docx, certificate_docx, progress_report_docx])

        except Exception as e:
            logging.error(f"Error processing row {index}: {e}")

    # Convert DOCX to PDFs and organize them
    for docx_path in generated_docx_files:
        try:
            pdf_path = docx_to_pdf(docx_path, generated_documents_dir)
            if pdf_path:
                os.remove(docx_path)  # Cleanup DOCX after conversion

                # Organize PDFs by case manager
                row = next((r for r in df_filtered.to_dict('records') if os.path.basename(docx_path).startswith(r['last_name'])), None)
                if row:
                    # Handle officer location
                    raw_office = row.get('case_manager_office', '').strip()
                    if not raw_office or raw_office.lower() == 'nan':
                        office_dir = central_location_path  # Place directly in central location if no office
                    else:
                        office_dir = os.path.join(central_location_path, sanitize_filename(raw_office))

                    # Handle officer name
                    raw_first = row.get('case_manager_first_name', '').strip()
                    raw_last = row.get('case_manager_last_name', '').strip()

                    if not raw_first or raw_first.lower() == 'nan':  # Use only last name if first is missing
                        manager_subfolder = sanitize_filename(raw_last)
                    else:
                        manager_subfolder = f"{sanitize_filename(raw_first)} {sanitize_filename(raw_last)}".strip()

                    # Create manager directory
                    manager_dir = os.path.join(office_dir, manager_subfolder)
                    os.makedirs(manager_dir, exist_ok=True)

                    # Move the PDF to the correct folder
                    final_pdf_path = os.path.join(manager_dir, os.path.basename(pdf_path))
                    shutil.move(pdf_path, final_pdf_path)
                    logging.info(f"Moved PDF to: {final_pdf_path}")
            else:
                logging.error(f"PDF conversion failed for DOCX: {docx_path}")
        except Exception as e:
            logging.error(f"Error converting DOCX to PDF for {docx_path}: {e}")

    # Final verification
    remaining_docx_files = [f for f in os.listdir(generated_documents_dir) if f.endswith('.docx')]
    if remaining_docx_files:
        logging.error(f"The following DOCX files were not converted to PDF: {remaining_docx_files}")
    else:
        logging.info("All DOCX files successfully converted to PDF and organized.")

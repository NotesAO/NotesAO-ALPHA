import pandas as pd
from datetime import datetime
import random
import os
import argparse


def log_print(*args, **kwargs):
    print(*args, **kwargs)  # Only prints to terminal (no file logging)

def process_client_notes(csv_file, start_date_str=None, end_date_str=None, output_csv_path=None, templates_dir=None):
    start_date = datetime.strptime(start_date_str, '%Y-%m-%d') if start_date_str else None
    end_date   = datetime.strptime(end_date_str, '%Y-%m-%d') if end_date_str else None

    base_path          = os.path.dirname(__file__)
    client_notes_path  = os.path.join(templates_dir, 'Blank Progress Notes.xlsx')
    client_data_path   = csv_file

    # --- Load Data ---
    try:
        # Load CSV data
        client_data = pd.read_csv(client_data_path, low_memory=False)

        # Load all sheets from the workbook in one go
        all_sheets = pd.read_excel(client_notes_path, sheet_name=None)

        # Pull out each sheet by name if it exists; otherwise None
        bipp_notes          = all_sheets.get('BIPP', None)
        anger_control_notes = all_sheets.get('Anger Control', None)
        t4c_notes           = all_sheets.get('T4C', None)
        tips_notes          = all_sheets.get('TIPS', None)
        mrt_notes          = all_sheets.get('MRT', None)

        # --- DWAG-specific BIPP sheets ------------------------------------
        clinic_name = os.path.basename(os.path.normpath(templates_dir)).lower()
        
        bipp_dwag_male_notes   = None
        bipp_dwag_female_notes = None
        bipp_dwag_male_trackers  = None
        bipp_dwag_female_trackers = None
        
        # REPLACE WITH
        if clinic_name == "dwag":
            # ------- pick the tab names safely -------
            bipp_dwag_male_notes = all_sheets.get('BIPP DWAG Male')
            if bipp_dwag_male_notes is None:
                bipp_dwag_male_notes = all_sheets.get('BIPP (male)')

            bipp_dwag_female_notes = all_sheets.get('BIPP DWAG Female')
            if bipp_dwag_female_notes is None:
                bipp_dwag_female_notes = all_sheets.get('BIPP (female)')

            # ------- build trackers (if a sheet exists) -------
            bipp_dwag_male_trackers   = initialize_note_trackers(bipp_dwag_male_notes)   if bipp_dwag_male_notes   is not None else None
            bipp_dwag_female_trackers = initialize_note_trackers(bipp_dwag_female_notes) if bipp_dwag_female_notes is not None else None


    except FileNotFoundError as e:
        return f"File not found: {e.filename}", 500
    except pd.errors.EmptyDataError:
        return "No data found in the CSV or Excel file.", 500
    except Exception as e:
        return f"An unexpected error occurred while loading files: {e}", 500

    # Retain necessary fields for processing
    processing_fields = [
        'client_id', 'program_name', 'first_name', 'last_name', 'gender',
        'attended', 'required_sessions', 'orientation_date', 'exit_date',
        'exit_reason', 'exit_note', 'last_attended', 'absence_unexcused', 'referral_type'
    ]

    # Make sure the CSV has the columns we need
    missing_fields = [field for field in processing_fields if field not in client_data.columns]
    if missing_fields:
        return f"Missing required fields in input CSV: {', '.join(missing_fields)}", 500

    # Keep only the processing fields
    client_data = client_data[processing_fields]

    # Filter out Veterans Court
    original_len = len(client_data)
    client_data = client_data[client_data['program_name'] != 'Veterans Court']
    skipped_count = original_len - len(client_data)
    if skipped_count > 0:
        print(f"Skipped {skipped_count} rows from Veterans Court; {len(client_data)} remain.")

    # Fix contradictory "exit_date" + "Not Exited"
    mask = (
        client_data['exit_date'].notnull() &
        (client_data['exit_date'] != '') &
        (client_data['exit_reason'] == 'Not Exited')
    )
    if mask.any():
        print(f"Found {mask.sum()} rows with exit_date but exit_reason == 'Not Exited'. Overriding to 'Other'.")
        client_data.loc[mask, 'exit_reason'] = 'Other'

    # Handle missing or empty values in critical fields
    critical_fields = ['client_id', 'first_name', 'program_name']
    for field in critical_fields:
        if client_data[field].isnull().any():
            print(f"Warning: Missing values detected in critical field: {field}. Filling with defaults.")
            client_data[field] = client_data[field].fillna('Unknown')

    print(f"Processing {len(client_data)} clients...")

    # Generate notes and stages
    client_data['client_note'], client_data['client_stage'] = generate_notes(
        client_data, start_date, end_date, bipp_notes, anger_control_notes, t4c_notes, tips_notes, mrt_notes, clinic_name, bipp_dwag_male_notes, bipp_dwag_female_notes, bipp_dwag_male_trackers, bipp_dwag_female_trackers
    )

    # Trim to allowed fields for uploading
    allowed_fields = [
        'client_id', 'client_stage', 'client_note',
        'orientation_date', 'exit_date', 'exit_reason', 'referral_type'
    ]
    client_data = client_data[allowed_fields]

    print(f"Data to be uploaded:\n{client_data.head()}")

    # Save updated data to CSV
    try:
        client_data.to_csv(output_csv_path, index=False)
        return "Client data saved successfully.", 200
    except Exception as e:
        return f"Error saving client data: {e}", 500

# --- Helper Functions ---

def is_within_date_range(date_str, start_date, end_date):
    """
    Return True if `date_str` falls inside [start_date, end_date] (inclusive).

    If either boundary is None, we treat the range as open-ended and
    always return True so callers don’t crash.
    """
    # no date range filtering requested
    if start_date is None or end_date is None:
        return True

    if pd.isna(date_str) or date_str == '':
        return True   # empty cell → let other rules decide

    # try ISO then US format
    for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
        try:
            date_obj = datetime.strptime(date_str, fmt)
            break
        except ValueError:
            continue
    else:                                   # no break => both failed
        print(f"Unrecognized date format: {date_str}")
        return False

    return start_date <= date_obj <= end_date


def map_gender_pronouns(gender):
    gender = gender.lower()
    if gender == 'male':
        return {
            'gender1': 'his',
            'gender2': 'he',
            'gender3': "Men's",
            'gender4': 'him',
            'gender5': 'He',
            'gender6': 'His',
            'gender7': 'himself'
        }
    elif gender == 'female':
        return {
            'gender1': 'her',
            'gender2': 'she',
            'gender3': "Women's",
            'gender4': 'her',
            'gender5': 'She',
            'gender6': 'Her',
            'gender7': 'herself'
        }
    else:
        print(f"Unexpected gender value: {gender}")
        return {}

def _latin1_to_utf8(text):
    if not isinstance(text, str):
        return text
    try:
        return text.encode('latin1').decode('utf-8')
    except Exception:
        return text


def fix_encoding_issues(text):
    if not isinstance(text, str):
        return text
    # First try to reverse common mojibake at once
    text = _latin1_to_utf8(text)

    # Then normalize smart quotes/dashes if any remain
    replacements = {
        '\u2018': "'",  # ‘
        '\u2019': "'",  # ’
        '\u201c': '"',  # “
        '\u201d': '"',  # ”
        '\u2013': '-',  # –
        '\u2014': '-',  # —
        '\u2026': '...',# …
        '\u2022': '•',
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    return text


def format_date(date_str):
    try:
        date_obj = datetime.strptime(date_str, '%m/%d/%Y')
        return date_obj.strftime('%m/%d/%y')
    except ValueError:
        return date_str

def get_attendance_category(absence_unexcused):
    if absence_unexcused == 0:
        return 'perfect'
    elif 1 <= absence_unexcused <= 2:
        return 'excellent'
    elif 3 <= absence_unexcused <= 4:
        return 'good'
    else:
        return 'decent'

class NoteTracker:
    def __init__(self, notes, stage_name):
        self.original_notes = notes
        self.unused_notes   = notes.copy()
        self.stage_name     = stage_name
        random.shuffle(self.unused_notes)
        log_print(f"NoteTracker initialized for stage: {self.stage_name} with {len(self.original_notes)} notes.")

    def get_note(self):
        if not self.unused_notes:
            log_print(f"All notes used for stage: {self.stage_name}. Reshuffling and reloading notes.")
            self.unused_notes = self.original_notes.copy()
            random.shuffle(self.unused_notes)
        note = self.unused_notes.pop()
        log_print(f"Note provided for stage: {self.stage_name}. {len(self.unused_notes)} notes remaining.")
        return note

def initialize_note_trackers(sheet):
    if sheet is None:
        return None
    stages = ['Precontemplation', 'Contemplation', 'Preparation', 'Action', 'Maintenance']
    note_trackers = {}
    for stage in stages:
        if stage not in sheet.columns:
            note_trackers[stage] = NoteTracker([], stage)
            continue
        notes = sheet[stage].dropna().tolist()
        note_trackers[stage] = NoteTracker(notes, stage)
    return note_trackers

def initialize_client_stage(client):
    stage = client.get('client_stage', '')
    if pd.isna(stage) or stage == '':
        stage = determine_stage(client)
        log_print(f"Stage for client {client['first_name']} was NaN or empty, setting to {stage}.")
    return stage

def check_data_integrity(client):
    required_fields = ['first_name', 'attended', 'required_sessions', 'program_name']
    for field in required_fields:
        if pd.isna(client.get(field)) or client.get(field) == '':
            log_print(f"Warning: {field} is missing or empty for client {client.get('first_name', 'Unknown')}.")

# --- Helper Functions -------------------------------------------------

def get_tracked_note_for_stage(note_trackers, stage):
    """
    Return a fresh note for *stage*.  
    If the tracker dictionary is missing or the stage column does not exist,
    give back a placeholder so the script never crashes.
    """
    if note_trackers is None:                         # ← new guard
        log_print("⚠️  note_trackers is None – sheet/tab probably missing.")
        return "No note available (sheet missing)"

    if stage not in note_trackers:                    # ← extra guard
        log_print(f"⚠️  Stage '{stage}' not found in note_trackers.")
        return "No note available for this stage"

    return note_trackers[stage].get_note()


def get_random_maintenance_note(sheet):
    if sheet is None or 'Maintenance' not in sheet.columns:
        return 'No maintenance note available'
    notes = sheet['Maintenance'].dropna().tolist()

    return random.choice(notes) if notes else 'No maintenance note available'

def get_intake_note(sheet):
    if sheet is None or 'Intake' not in sheet.columns:
        return ""
    vals = sheet['Intake'].dropna().values
    return vals[0] if len(vals) else ""

def get_relapse_note(sheet):
    if sheet is None or 'Relapse' not in sheet.columns:
        return ""
    vals = sheet['Relapse'].dropna().values
    return vals[0] if len(vals) else ""

def get_after_note(sheet):
    if sheet is None or 'After Note' not in sheet.columns:
        return ""
    vals = sheet['After Note'].dropna().values
    return vals[0] if len(vals) else ""



def get_random_note_for_stage(sheet, stage):
    notes = sheet[stage].dropna().tolist()
    return random.choice(notes) if notes else 'No note available for this stage'

def contains_pause(text):
    if pd.isna(text):
        return False
    pause_variations = ['Pause', 'Paused', 'On Pause']
    return any(variation.lower() in text.lower() for variation in pause_variations)

def is_paused(client):
    has_pause_keyword       = (contains_pause(client.get('exit_reason', '')) or
                               contains_pause(client.get('exit_note', '')))
    has_exit_date           = pd.notna(client.get('exit_date', ''))
    attended_equals_required= client.get('attended', 0) == client.get('required_sessions', 0)
    return has_pause_keyword and has_exit_date and not attended_equals_required

def contains_duplicate(text):
    if pd.isna(text):
        return False
    cleaned_text = text.strip().replace("\n", " ").lower()
    log_print(f"Checking for 'duplicate' in cleaned text: '{cleaned_text}'")
    return 'duplicate' in cleaned_text

def _to_int(x, default=0):
    try:
        # handles '', NaN, '27.0'
        v = pd.to_numeric(x, errors='coerce')
        return int(v) if pd.notna(v) else default
    except Exception:
        return default

def determine_stage(client):
    program_name = client['program_name']
    exit_date    = client['exit_date']
    exit_reason  = client['exit_reason']

    attended_i = _to_int(client.get('attended', 0))
    required_i = _to_int(client.get('required_sessions', 0))

    # If no recognized program, set to Alert
    if all(prog not in program_name for prog in
            ['BIPP', 'Anger Control', 'Thinking for a Change', 'TIPS (Theft)', 'MRT']):
        return 'Alert'

    # If there's an exit_date with a violating exit_reason, set to Relapse
    if pd.notnull(exit_date) and exit_date != '' and exit_reason not in ['Not Exited', 'Completion of Program']:
        return 'Relapse'

    # Over-attendance but not exited → treat as Maintenance
    if attended_i > required_i and str(exit_reason).strip() in ('', 'Not Exited'):
        return 'Maintenance'

    # T4C logic
    if 'Thinking for a Change' in program_name:
        if   1 <= attended_i <= 6:   return 'Precontemplation'
        elif 7 <= attended_i <= 12:  return 'Contemplation'
        elif 13 <= attended_i <= 18: return 'Preparation'
        elif 19 <= attended_i <= 29: return 'Action'
        elif attended_i in [30, 31]: return 'Maintenance'
        else:
            print(f"Unexpected attendance value for T4C: {attended_i}")
            return 'Alert'

    # TIPS logic
    if 'TIPS (Theft)' in program_name:
        if   1 <= attended_i <= 6:   return 'Precontemplation'
        elif 7 <= attended_i <= 12:  return 'Contemplation'
        elif 13 <= attended_i <= 18: return 'Preparation'
        elif 19 <= attended_i <= 29: return 'Action'
        elif attended_i in [30, 31]: return 'Maintenance'
        else:
            print(f"Unexpected attendance value for TIPS: {attended_i}")
            return 'Alert'

    if 'MRT' in program_name:
        if   0 <= attended_i <= 3:  return 'Precontemplation'
        elif 4 <= attended_i <= 7:  return 'Contemplation'
        elif 8 <= attended_i <= 11: return 'Preparation'
        elif 12 <= attended_i <= 14:return 'Action'
        elif attended_i >= 15:      return 'Maintenance'

    # BIPP logic for 18 sessions
    if required_i == 18:
        if   1 <= attended_i <= 4:   return 'Precontemplation'
        elif 5 <= attended_i <= 8:   return 'Contemplation'
        elif 9 <= attended_i <= 11:  return 'Preparation'
        elif 12 <= attended_i <= 15: return 'Action'
        elif 16 <= attended_i <= 18: return 'Maintenance'
        else:
            print(f"Unexpected attendance value for BIPP 18: {attended_i}")
            return 'Alert'

    # BIPP logic for 27 sessions
    if required_i == 27:
        if   1 <= attended_i <= 7:   return 'Precontemplation'
        elif 8 <= attended_i <= 14:  return 'Contemplation'
        elif 15 <= attended_i <= 18: return 'Preparation'
        elif 19 <= attended_i <= 23: return 'Action'
        elif 24 <= attended_i <= 27: return 'Maintenance'
        else:
            print(f"Unexpected attendance value for BIPP 27: {attended_i}")
            return 'Alert'

    # Otherwise use dynamic approach
    return determine_dynamic_stage(attended_i, required_i)


def determine_dynamic_stage(attended, required_sessions):
    # Calculate approximate boundaries
    precontemplation_end = int(required_sessions * 0.25)
    contemplation_end    = int(required_sessions * 0.50)
    preparation_end      = int(required_sessions * 0.65)
    action_end           = int(required_sessions * 0.85)
    maintenance_end      = required_sessions

    if attended <= precontemplation_end:
        return 'Precontemplation'
    elif attended <= contemplation_end:
        return 'Contemplation'
    elif attended <= preparation_end:
        return 'Preparation'
    elif attended <= action_end:
        return 'Action'
    elif attended <= maintenance_end:
        return 'Maintenance'
    else:
        return 'Alert'

def replace_placeholders_intake(note, client):
    gender_pronouns = map_gender_pronouns(client['gender'])

    orientation_date = client.get('orientation_date', '')
    if pd.notna(orientation_date) and orientation_date.strip():
        try:
            orientation_date = datetime.strptime(orientation_date, '%Y-%m-%d').strftime('%m/%d/%Y')
        except ValueError:
            try:
                orientation_date = datetime.strptime(orientation_date, '%m/%d/%Y').strftime('%m/%d/%Y')
            except ValueError:
                pass  # leave as-is if all parsing fails

    placeholders = {
        'first_name':    client['first_name'],
        'gender1':       gender_pronouns.get('gender1', ''),
        'gender2':       gender_pronouns.get('gender2', ''),
        'gender5':       gender_pronouns.get('gender5', ''),
        'orientation_date': orientation_date
    }
    for placeholder, value in placeholders.items():
        note = note.replace(f"{{{{{placeholder}}}}}", value)
    return fix_encoding_issues(note)

def replace_placeholders_maintenance(note, client):
    gender_pronouns = map_gender_pronouns(client['gender'])
    placeholders = {
        'first_name': client['first_name'],
        'gender1':    gender_pronouns.get('gender1', ''),
        'gender2':    gender_pronouns.get('gender2', ''),
        'gender5':    gender_pronouns.get('gender5', ''),
        'gender6':    gender_pronouns.get('gender6', '')
    }
    for placeholder, value in placeholders.items():
        note = note.replace(f"{{{{{placeholder}}}}}", value)
    return fix_encoding_issues(note)

def generate_completion_note_with_maintenance(client, sheet):
    maintenance_note = get_random_maintenance_note(sheet)
    maintenance_note = replace_placeholders(maintenance_note, client)

    exit_date_raw     = str(client.get('exit_date', '')).strip()
    actual_exit_date  = ''
    if exit_date_raw and exit_date_raw.lower() != 'nan':
        try:
            exit_date_obj    = datetime.strptime(exit_date_raw, '%Y-%m-%d')
            actual_exit_date = exit_date_obj.strftime('%m/%d/%Y')
        except ValueError:
            try:
                exit_date_obj    = datetime.strptime(exit_date_raw, '%m/%d/%Y')
                actual_exit_date = exit_date_obj.strftime('%m/%d/%Y')
            except ValueError:
                actual_exit_date = ''

    # Choose the right template based on program
    if 'Thinking for a Change' in client['program_name']:
        completion_note_template = (
            "{first_name} has successfully completed the Thinking for a Change program on {actual_exit_date} "
            "according to {referral_type} stipulations with {attendance_category} attendance. Throughout the course, "
            "{gender2} demonstrated consistent engagement and a positive attitude towards change. {gender6} participation "
            "in group discussions and activities was proactive and reflective, showing a deep understanding of the "
            "concepts taught. {maintenance_note} Please see faxed Completion Letter & Certificate."
        )
    elif 'Anger Control' in client['program_name']:
        completion_note_template = (
            "{first_name} successfully completed {gender1} {required_sessions}-week Anger Control Group requirement "
            "on {actual_exit_date} according to {referral_type} stipulations with {attendance_category} attendance. "
            "{maintenance_note} Please see faxed Completion Letter & Certificate."
        )
    elif 'TIPS (Theft)' in client['program_name']:
        completion_note_template = (
            "{first_name} successfully completed {gender1} {required_sessions}-week TIPS program requirement "
            "on {actual_exit_date} according to {referral_type} stipulations with {attendance_category} attendance. "
            "{maintenance_note} Please see faxed Completion Letter & Certificate."
        )
    elif 'MRT' in client['program_name']:
        completion_note_template = (
            "{first_name} successfully completed {gender1} {required_sessions}-step MRT program "
            "on {actual_exit_date} according to {referral_type} stipulations with {attendance_category} attendance. "
            "{maintenance_note} Please see faxed Completion Letter & Certificate."
        )

    else:
        # Default to BIPP style
        completion_note_template = (
            "{first_name} successfully completed {gender1} {required_sessions}-week program requirement "
            "on {actual_exit_date} according to {referral_type} stipulations with {attendance_category} attendance. "
            "{maintenance_note} Please see faxed Completion Letter & Certificate."
        )

    placeholders = {
        'first_name':         client['first_name'],
        'gender1':            map_gender_pronouns(client['gender']).get('gender1', ''),
        'gender2':            map_gender_pronouns(client['gender']).get('gender2', ''),
        'gender6':            map_gender_pronouns(client['gender']).get('gender6', ''),
        'required_sessions':  str(client['required_sessions']),
        'actual_exit_date':   actual_exit_date,
        'referral_type':      client.get('referral_type', ''),
        'attendance_category': get_attendance_category(client.get('absence_unexcused', 0)),
        'maintenance_note':   maintenance_note
    }
    completion_note = completion_note_template.format(**placeholders)
    return fix_encoding_issues(completion_note)

def replace_placeholders_relapse(note, client):
    gender_pronouns   = map_gender_pronouns(client['gender'])
    orientation_date  = format_date(str(client.get('orientation_date', '')))
    exit_date         = format_date(str(client.get('exit_date', '')))
    last_attended     = client.get('last_attended', '')

    if not last_attended or pd.isna(last_attended) or last_attended.lower() == 'nan':
        last_attended_text = f"Orientation on {orientation_date}"
    else:
        last_attended_text = format_date(str(last_attended))

    placeholders = {
        'first_name':       str(client['first_name']),
        'gender1':          str(gender_pronouns.get('gender1', '')),
        'gender2':          str(gender_pronouns.get('gender2', '')),
        'gender4':          str(gender_pronouns.get('gender4', '')),
        'gender5':          str(gender_pronouns.get('gender5', '')),
        'gender7':          str(gender_pronouns.get('gender7', '')),
        'orientation_date': orientation_date,
        'last_attended':    last_attended_text,
        'exit_reason':      str(client.get('exit_reason', '')),
        'exit_date':        exit_date
    }
    for placeholder, value in placeholders.items():
        note = note.replace(f"{{{{{placeholder}}}}}", value)
    return fix_encoding_issues(note)

def replace_placeholders(note, client):
    gender_pronouns = map_gender_pronouns(client['gender'])
    placeholders = {
        'first_name':       str(client.get('first_name', '')),
        'gender1':          str(gender_pronouns.get('gender1', '')),
        'gender2':          str(gender_pronouns.get('gender2', '')),
        'gender3':          str(gender_pronouns.get('gender3', '')),
        'gender4':          str(gender_pronouns.get('gender4', '')),
        'gender5':          str(gender_pronouns.get('gender5', '')),
        'gender6':          str(gender_pronouns.get('gender6', '')),
        'gender7':          str(gender_pronouns.get('gender7', '')),
        'orientation_date': str(format_date(str(client.get('orientation_date', '')))),
        'last_attended':    str(format_date(str(client.get('last_attended', '')))),
        'exit_reason':      str(client.get('exit_reason', '')),
        'required_sessions':str(client.get('required_sessions', '')),
        'exit_date':        str(format_date(str(client.get('exit_date', '')))),
        'referral_type':    str(client.get('referral_type', '')),
        'attendance_category': str(get_attendance_category(client.get('absence_unexcused', 0)))
    }
    for placeholder, value in placeholders.items():
        if f"{{{{{placeholder}}}}}" in note:
            log_print(f"Replacing placeholder {placeholder} with {value} in note.")
            note = note.replace(f"{{{{{placeholder}}}}}", value)
        else:
            log_print(f"Placeholder {placeholder} not found in note template.")
    return fix_encoding_issues(note)

def update_client_stage(client):
    new_stage = determine_stage(client)
    if client.get('client_stage', '') != new_stage:
        log_print(f"Client {client['first_name']}: Stage updated from {client.get('client_stage', '')} to {new_stage}.")
        client['client_stage'] = new_stage
    return client

def generate_notes(client_data, start_date, end_date,
                   bipp_notes, anger_control_notes, t4c_notes,
                   tips_notes, mrt_notes, clinic_name, bipp_dwag_male_notes, bipp_dwag_female_notes, bipp_dwag_male_trackers, bipp_dwag_female_trackers):
    """Create note & stage columns for every client row."""

    notes = []
    stages= []
    alerts= []

    # Initialize trackers if the sheet is not None
    bipp_note_trackers = initialize_note_trackers(bipp_notes)          if bipp_notes          is not None else None
    anger_note_trackers= initialize_note_trackers(anger_control_notes) if anger_control_notes is not None else None
    t4c_note_trackers  = initialize_note_trackers(t4c_notes)           if t4c_notes           is not None else None
    tips_note_trackers = initialize_note_trackers(tips_notes)          if tips_notes          is not None else None
    mrt_note_trackers  = initialize_note_trackers(mrt_notes)           if mrt_notes           is not None else None


    for _, client in client_data.iterrows():
        check_data_integrity(client)
        program_name = client['program_name']
        log_print(f"\nProcessing client: {client['first_name']} in program: {program_name}")

        client = update_client_stage(client)
        stage  = initialize_client_stage(client)
        log_print(f"Initial stage: {stage}")

        # Decide which sheet to use based on program name
        if "BIPP" in program_name:
            if clinic_name == "dwag":
                if client["gender"].lower() == "male":
                    sheet         = bipp_dwag_male_notes   if bipp_dwag_male_notes   is not None else bipp_notes
                    note_trackers = bipp_dwag_male_trackers if bipp_dwag_male_trackers is not None else bipp_note_trackers
                else:
                    sheet         = bipp_dwag_female_notes  if bipp_dwag_female_notes is not None else bipp_notes
                    note_trackers = bipp_dwag_female_trackers if bipp_dwag_female_trackers is not None else bipp_note_trackers
                    intake_sheet = sheet  # ← set here, after sheet is known
            else:
                sheet         = bipp_notes
                note_trackers = bipp_note_trackers
                intake_sheet  = bipp_notes  # legacy


        elif 'Anger Control' in program_name:
            if anger_control_notes is None:
                log_print("Warning: Anger Control row encountered but sheet is missing.")
                notes.append("No Anger Control notes available.")
                stages.append("Alert")
                continue
            sheet         = anger_control_notes
            note_trackers = anger_note_trackers

        elif 'Thinking for a Change' in program_name:
            if t4c_notes is None:
                log_print("Warning: T4C row encountered but 'T4C' sheet is missing.")
                notes.append("No T4C notes available.")
                stages.append("Alert")
                continue
            sheet         = t4c_notes
            note_trackers = t4c_note_trackers

        elif 'TIPS (Theft)' in program_name:
            if tips_notes is None:
                log_print("Warning: TIPS row encountered but 'TIPS' sheet is missing.")
                notes.append("No TIPS notes available.")
                stages.append("Alert")
                continue
            sheet = tips_notes
            note_trackers = tips_note_trackers

        elif 'MRT' in program_name:
            if mrt_notes is None:
                log_print("Warning: MRT row encountered but 'MRT' sheet is missing.")
                notes.append("No MRT notes available.")
                stages.append("Alert")
                continue

            sheet         = mrt_notes
            note_trackers = mrt_note_trackers

            # >>> MRT completion override (must come before intake) <<<
            if str(client.get('exit_reason', '')).strip() == 'Completion of Program' or \
               _to_int(client.get('attended', 0)) == _to_int(client.get('required_sessions', 0)):
                note  = generate_completion_note_with_maintenance(client, sheet)
                stage = 'Maintenance'
                notes.append(fix_encoding_issues(note))
                stages.append(stage)
                log_print(f"MRT completion override applied for {client['first_name']}.")
                continue
            # <<< end override >>>

            # Intake on first session → emit note and continue so nothing overwrites it later
            if _to_int(client.get('attended', 0)) == 0:
                note  = replace_placeholders_intake(get_intake_note(sheet), client)
                stage = 'Precontemplation'
                notes.append(fix_encoding_issues(note))
                stages.append(stage)
                log_print(f"MRT intake override applied for {client['first_name']}.")
                continue

            # Normal tracked note for current stage
            note = replace_placeholders(get_tracked_note_for_stage(note_trackers, stage), client)

        else:
            log_print(f"Unknown program name '{program_name}' for client {client['first_name']}.")
            notes.append("")
            stages.append("Alert")
            continue

        # Check for "duplicate" exit reason
        if str(client.get('exit_reason', '')).strip().lower() == 'other' and contains_duplicate(client.get('exit_note', '')):
            stage = 'Relapse'
            note  = ''  # leave blank
            notes.append(note)
            stages.append(stage)
            log_print(f"Client {client['first_name']} is marked as duplicate with exit_reason='Other' + 'duplicate' in exit_note.")
            continue

        # Check for "Paused"
        if is_paused(client):
            stage = 'Paused'
            note  = 'Paused'
            notes.append(note)
            stages.append(stage)
            log_print(f"Client {client['first_name']} is on pause. Setting stage+note to 'Paused'.")
            continue

        # Check for exit_date & violation reason => relapse
        if (pd.notnull(client.get('exit_date')) and
            client.get('exit_reason') in ['Violation of Requirements','Other','Unable to Participate']):

            exit_date_raw = str(client.get('exit_date', '')).strip()
            formatted_exit_date = ''
            if exit_date_raw and exit_date_raw.lower() != 'nan':
                try:
                    exit_date_obj       = datetime.strptime(exit_date_raw, '%Y-%m-%d')
                    formatted_exit_date = exit_date_obj.strftime('%m/%d/%Y')
                except ValueError:
                    try:
                        exit_date_obj       = datetime.strptime(exit_date_raw, '%m/%d/%Y')
                        formatted_exit_date = exit_date_obj.strftime('%m/%d/%Y')
                    except ValueError as e:
                        import logging
                        logging.error(f"Failed to parse exit_date: {exit_date_raw} - {e}")

            orientation_date_raw = str(client.get('orientation_date', '')).strip()
            formatted_orientation_date = ''
            if orientation_date_raw and orientation_date_raw.lower() != 'nan':
                try:
                    orientation_date_obj = datetime.strptime(orientation_date_raw, '%Y-%m-%d')
                    formatted_orientation_date = orientation_date_obj.strftime('%m/%d/%Y')
                except ValueError:
                    try:
                        orientation_date_obj = datetime.strptime(orientation_date_raw, '%m/%d/%Y')
                        formatted_orientation_date = orientation_date_obj.strftime('%m/%d/%Y')
                    except ValueError as e:
                        import logging
                        logging.error(f"Failed to parse orientation_date: {orientation_date_raw} - {e}")

            last_attended_raw = str(client.get('last_attended', '')).strip()
            formatted_last_attended = ''
            if last_attended_raw and last_attended_raw.lower() != 'nan':
                try:
                    last_attended_obj = datetime.strptime(last_attended_raw, '%Y-%m-%d')
                    formatted_last_attended = last_attended_obj.strftime('%m/%d/%Y')
                except ValueError:
                    try:
                        last_attended_obj = datetime.strptime(last_attended_raw, '%m/%d/%Y')
                        formatted_last_attended = last_attended_obj.strftime('%m/%d/%Y')
                    except ValueError as e:
                        import logging
                        logging.error(f"Failed to parse last_attended: {last_attended_raw} - {e}")

            # Prepare relapse note
            stage               = 'Relapse'
            relapse_note_tmpl   = get_relapse_note(sheet)
            client['exit_date'] = formatted_exit_date
            client['orientation_date'] = formatted_orientation_date
            client['last_attended']    = formatted_last_attended
            note = replace_placeholders_relapse(relapse_note_tmpl, client)
            log_print(f"Client {client['first_name']} has exit reason '{client.get('exit_reason')}'. Stage=Relapse.")
            notes.append(note)
            stages.append(stage)
            continue

        client_note = client.get('client_note', '')
        if not isinstance(client_note, str):
            client_note = ''

        # ---------- DWAG/BIPP unconditional overrides ----------
        gender_l = str(client.get('gender', '')).lower()
        att_i    = _to_int(client.get('attended', 0))
        req_i    = _to_int(client.get('required_sessions', 0))
        exit_date_s   = str(client.get('exit_date', '')).strip()
        exit_reason_s = str(client.get('exit_reason', '')).strip()

        if 'BIPP' in program_name:
            # Intake note whenever attended == 0
            if att_i == 0:
                intake_src = (bipp_notes if clinic_name != 'dwag'
                            else (bipp_dwag_male_notes if gender_l == 'male' else bipp_dwag_female_notes))
                note  = replace_placeholders_intake(get_intake_note(intake_src), client)
                stage = 'Precontemplation'
                notes.append(fix_encoding_issues(note))
                stages.append(stage)
                log_print(f"DWAG/BIPP intake override applied for {client['first_name']}.")
                continue

            # Maintenance note when over required and not exited
            if (clinic_name == 'dwag' and att_i > req_i and (exit_date_s == '' or exit_reason_s in ('', 'Not Exited'))):
                if gender_l == 'male':
                    sheet_sel   = bipp_dwag_male_notes if bipp_dwag_male_notes is not None else bipp_notes
                    trackers_sel= bipp_dwag_male_trackers if bipp_dwag_male_trackers is not None else bipp_note_trackers
                else:
                    sheet_sel   = bipp_dwag_female_notes if bipp_dwag_female_notes is not None else bipp_notes
                    trackers_sel= bipp_dwag_female_trackers if bipp_dwag_female_trackers is not None else bipp_note_trackers

                note = replace_placeholders(get_tracked_note_for_stage(trackers_sel, 'Maintenance'), client)
                # keep existing stage or set to Maintenance; your call. We set to Maintenance to align stage with note.
                stage = 'Maintenance'
                notes.append(fix_encoding_issues(note))
                stages.append(stage)
                log_print(f"DWAG/BIPP over-attendance override applied for {client['first_name']} (attended {att_i} > required {req_i}).")
                continue
        # -------------------------------------------------------


        # Decide if we need to update note based on date range or empty note
        update_needed = (
            not client_note.strip() or
            is_within_date_range(client.get('orientation_date', ''), start_date, end_date) or
            is_within_date_range(client.get('exit_date', ''),        start_date, end_date) or
            is_within_date_range(client.get('last_attended', ''),    start_date, end_date)
        )
        log_print(f"Update needed for client {client['first_name']}: {update_needed}")

        if update_needed:
            log_print(f"Updating note for client {client['first_name']} at stage {stage}")
            # Program-specific logic
            if 'BIPP' in program_name:
                if client['attended'] == 0:
                    # Intake on first session
                    intake_note = get_intake_note(bipp_notes if clinic_name != 'dwag' else (
                        bipp_dwag_male_notes if str(client['gender']).lower() == 'male' else bipp_dwag_female_notes
                    ))
                    note  = replace_placeholders_intake(intake_note, client)
                    stage = 'Precontemplation'
                else:
                    # Choose sheet + trackers by clinic/gender
                    gender_1 = str(client.get('gender', '')).lower()
                    if clinic_name == 'dwag':
                        if gender_1 == 'male':
                            # safe for DataFrames
                            sheet = bipp_dwag_male_notes if bipp_dwag_male_notes is not None else bipp_notes
                            note_trackers = bipp_dwag_male_trackers if bipp_dwag_male_trackers is not None else bipp_note_trackers

                        else:
                            # safe for DataFrames
                            sheet = bipp_dwag_female_notes if bipp_dwag_female_notes is not None else bipp_notes
                            note_trackers = bipp_dwag_female_trackers if bipp_dwag_female_trackers is not None else bipp_note_trackers

                    else:
                        sheet, note_trackers = bipp_notes, bipp_note_trackers

                    # DWAG over-attendance → pull a Maintenance note when not exited
                    stage_used_for_bipp = stage
                    exit_date   = str(client.get('exit_date', '')).strip()
                    exit_reason = str(client.get('exit_reason', '')).strip()
                    if (clinic_name == 'dwag'
                        and int(client.get('attended', 0)) > int(client.get('required_sessions', 0))
                        and (exit_date == '' or exit_reason in ('', 'Not Exited'))):
                        stage_used_for_bipp = 'Maintenance'

                    # Generate the stage-appropriate tracked note now
                    note = replace_placeholders(
                        get_tracked_note_for_stage(note_trackers, stage_used_for_bipp),
                        client
                    )



            elif 'Anger Control' in program_name:
                sheet         = anger_control_notes
                note_trackers = anger_note_trackers

            elif 'Thinking for a Change' in program_name:
                sheet         = t4c_notes
                note_trackers = t4c_note_trackers

            elif 'TIPS (Theft)' in program_name:
                sheet = tips_notes
                # Using T4C trackers for TIPS, or define a separate tips_note_trackers if needed
                note_trackers = tips_note_trackers

            # If client completed the program
            if (client['exit_reason'] == 'Completion of Program'
                or client.get('attended', 0) == client.get('required_sessions', 0)):
                note = generate_completion_note_with_maintenance(client, sheet)
                stage = 'Maintenance'
                log_print(f"Client {client['first_name']} completed the program. Stage=Maintenance.")
            
            elif 'Thinking for a Change' in program_name:
                if clinic_name == 'dwag' and client['attended'] == 0:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'

                if stage == 'Relapse':
                    note = replace_placeholders_relapse(get_relapse_note(sheet), client)
                elif client['attended'] == 1:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'
                elif 2 <= client['attended'] <= 4:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'
                elif 5 <= client['attended'] <= 6:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Precontemplation'
                elif 7 <= client['attended'] <= 12:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Contemplation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Contemplation'
                elif 13 <= client['attended'] <= 18:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Preparation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Preparation'
                elif 19 <= client['attended'] <= 29:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Action'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Action'
                elif client['attended'] == 30:
                    note        = generate_completion_note_with_maintenance(client, sheet)
                    stage       = 'Maintenance'

            

            elif 'TIPS (Theft)' in program_name:
                if clinic_name == 'dwag' and client['attended'] == 0:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'

                if stage == 'Relapse':
                    note  = replace_placeholders_relapse(get_relapse_note(sheet), client)
                elif client['attended'] == 1:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'
                elif 2 <= client['attended'] <= 4:
                    intake_note = replace_placeholders_intake(get_intake_note(sheet), client)
                    pre_note    = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    note        = f"{intake_note} {pre_note}"
                    stage       = 'Precontemplation'
                elif 5 <= client['attended'] <= 6:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Precontemplation'
                elif 7 <= client['attended'] <= 12:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Contemplation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Contemplation'
                elif 13 <= client['attended'] <= 18:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Preparation'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Preparation'
                elif 19 <= client['attended'] <= 29:
                    note        = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Action'), client)
                    after_note  = replace_placeholders(get_after_note(sheet), client)
                    note        = f"{note} {after_note}"
                    stage       = 'Action'
                elif client['attended'] == 30:
                    note        = generate_completion_note_with_maintenance(client, sheet)
                    stage       = 'Maintenance'

            elif 'Anger Control' in program_name:
                if clinic_name == 'dwag' and client['attended'] == 0:
                    note  = replace_placeholders_intake(get_intake_note(sheet), client)
                    stage = 'Precontemplation'
                elif client['attended'] == 1:
                    note  = replace_placeholders_intake(get_intake_note(sheet), client)
                    stage = 'Precontemplation'

                if client['attended'] == 1:
                    note  = replace_placeholders_intake(get_intake_note(sheet), client)
                    stage = 'Precontemplation'
                elif 2 <= client['attended'] <= 4:
                    note  = replace_placeholders(get_tracked_note_for_stage(note_trackers, 'Precontemplation'), client)
                    stage = 'Precontemplation'
                elif (pd.notnull(client['exit_date']) and
                      client['attended'] != client['required_sessions']):
                    note  = replace_placeholders_relapse(get_relapse_note(sheet), client)
                    stage = 'Relapse'
                else:
                    if stage == 'Alert':
                        alerts.append(f"Client {client['first_name']} has unexpected attended={client['attended']} or required_sessions={client['required_sessions']}.")
                        note = 'Alert: Check client details'
                    elif stage == 'Intake':
                        note  = replace_placeholders_intake(get_intake_note(sheet), client)
                        stage = 'Precontemplation'
                    elif stage == 'Completion' or (stage == 'Maintenance' and client['attended'] == client['required_sessions']):
                        note  = generate_completion_note_with_maintenance(client, sheet)
                        stage = 'Maintenance'
                    else:
                        note  = replace_placeholders(get_tracked_note_for_stage(note_trackers, stage), client)

            else:
                # Fallback for e.g. BIPP but not recognized, or any other mismatch
                if stage == 'Precontemplation' and client['attended'] == 0:
                    note  = replace_placeholders_intake(get_intake_note(bipp_notes), client)
                    stage = 'Precontemplation'
                elif (pd.notnull(client['exit_date']) and
                      client['attended'] != client['required_sessions']):
                    note  = replace_placeholders_relapse(get_relapse_note(sheet), client)
                    stage = 'Relapse'
                else:
                    if stage == 'Alert':
                        alerts.append(f"Client {client['first_name']} has unexpected attended={client['attended']} or required_sessions={client['required_sessions']}.")
                        note  = 'Alert: Check client details'
                    elif stage == 'Intake':
                        note  = replace_placeholders_intake(get_intake_note(sheet), client)
                        stage = 'Precontemplation'
                    elif stage == 'Completion' or (stage == 'Maintenance' and client['attended'] == client['required_sessions']):
                        note  = generate_completion_note_with_maintenance(client, sheet)
                        stage = 'Maintenance'
                    else:
                        note  = replace_placeholders(get_tracked_note_for_stage(note_trackers, stage), client)

            notes.append(fix_encoding_issues(note))
            stages.append(stage)
            log_print(f"Generated note for client {client['first_name']}: {note}")

        else:
            # No update needed
            notes.append(client.get('client_note', ''))
            stages.append(client.get('client_stage', ''))
            log_print(f"No update needed for client {client['first_name']}, using existing note.")

    if alerts:
        log_print("Alerts found during processing:")
        for alert in alerts:
            log_print(alert)

    return notes, stages

def main():
    log_print("This will be printed in both the terminal and the log file.")
    parser = argparse.ArgumentParser(description='Process SOG and client notes.')

    parser.add_argument('--csv_file',       required=True, help='The input CSV file path')
    parser.add_argument('--start_date',     required=False, help='The start date for processing')
    parser.add_argument('--end_date',       required=False, help='The end date for processing')
    parser.add_argument('--output_csv_path',required=True, help='The output CSV file path')
    parser.add_argument('--templates_dir',  required=True, help='The directory containing the note templates')

    args = parser.parse_args()

    message, status_code = process_client_notes(
        args.csv_file,
        args.start_date,
        args.end_date,
        args.output_csv_path,
        args.templates_dir
    )

    if status_code == 200:
        print(f"Client data saved successfully to {args.output_csv_path}")
    else:
        print(message)

if __name__ == '__main__':
    main()

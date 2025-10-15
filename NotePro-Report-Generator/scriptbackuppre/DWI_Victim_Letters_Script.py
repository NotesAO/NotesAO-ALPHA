"""
DWI_Victim_Letters_Script.py
--------------------------------
Generate Entrance, Completion, or Exit victim notification letters (DOCX ➜ PDF) for DWI clients.

Usage (CLI)
------------
python DWI_Victim_Letters_Script.py \
  --csv_file /path/to/report5_dump_20250520.csv \
  --clinic_folder ffltest \
  --templates_dir /home/notesao/NotePro-Report-Generator/templates \
  --start_date 2025-05-01 --end_date 2025-05-21

Required Word templates (names are fixed):
  Template.DWI Victim Entrance.docx
  Template.DWI Victim Completion.docx
  Template.DWI Victim Exit.docx

For each eligible client, the script iterates over victim slots 1‑5 and creates one letter per victim.
Output is placed under:
  /GeneratedDocuments/<clinic>/<MM.DD.YY>/<Office>/<Case Manager>/
PDFs are retained, intermediate DOCX files are deleted once converted.
"""

import argparse
import logging
import re
import os
import shutil
import subprocess
import sys
import csv
import time
from datetime import datetime
from typing import Optional, Tuple

import pandas as pd
from docxtpl import DocxTemplate

# ------------------------- CLI PARSING -------------------------
parser = argparse.ArgumentParser(description="Generate DWI Victim Notification Letters")
parser.add_argument("--csv_file", required=True, help="Path to CSV dump (report 5)")
parser.add_argument("--clinic_folder", required=True, help="Clinic folder identifier e.g. 'ffltest'")
parser.add_argument("--templates_dir", required=True, help="Folder containing Word templates")
parser.add_argument("--output_dir", help="Optional override for output root")
parser.add_argument("--start_date", help="Filter start date YYYY-MM-DD (inclusive)")
parser.add_argument("--end_date", help="Filter end date YYYY-MM-DD (inclusive)")
args = parser.parse_args()

# ------------------------- SETUP PATHS -------------------------
CSV_PATH = args.csv_file
CLINIC = args.clinic_folder
TEMPLATES_DIR = args.templates_dir

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
TODAY_TAG = datetime.now().strftime("%m.%d.%y")

GENERATED_DIR = args.output_dir or os.path.join(BASE_DIR, "GeneratedDocuments", CLINIC, TODAY_TAG)
os.makedirs(GENERATED_DIR, exist_ok=True)

PUBLIC_DIR = f"/home/notesao/{CLINIC}/public_html/GeneratedDocuments"
os.makedirs(PUBLIC_DIR, exist_ok=True)
CENTRAL_DIR = os.path.join(PUBLIC_DIR, TODAY_TAG)
os.makedirs(CENTRAL_DIR, exist_ok=True)

LOG_DIR = os.path.join(BASE_DIR, "logs")
os.makedirs(LOG_DIR, exist_ok=True)
LOG_PATH = os.path.join(LOG_DIR, f"DWI_Victim_Letters_{datetime.now().strftime('%Y%m%d')}.log")
logging.basicConfig(filename=LOG_PATH, level=logging.DEBUG, format="%(asctime)s [%(levelname)s] %(message)s")
logging.info("DWI Victim Letters Script started.")

# ------------------------- CONSTANTS -------------------------
LETTER_TYPES = {
    "entrance": {
        "template": "Template.DWI Victim Entrance.docx",
        "criteria": lambda row, sd, ed: (
            row.get("exit_reason", "") == "Not Exited"
            and date_in_range(row.get("orientation_date"), sd, ed)
        ),
    },
    "completion": {
        "template": "Template.DWI Victim Completion.docx",
        "criteria": lambda row, sd, ed: (
            row.get("exit_reason", "") == "Completion of Program"
            and date_in_range(row.get("exit_date"), sd, ed)
        ),
    },
    "exit": {
        "template": "Template.DWI Victim Exit.docx",
        "criteria": lambda row, sd, ed: (
            row.get("exit_reason", "") in [
                "Violation of Requirements",
                "Unable to Participate",
                "Death",
                "Moved",
            ]
            and date_in_range(row.get("exit_date"), sd, ed)
        ),
    },
}

PRONOUNS = {
    "male": {
        "gender1": "his",
        "gender2": "he",
        "gender3": "Men's",
        "gender4": "him",
        "gender5": "He",
        "gender6": "His",
        "gender7": "women",
    },
    "female": {
        "gender1": "her",
        "gender2": "she",
        "gender3": "Women's",
        "gender4": "her",
        "gender5": "She",
        "gender6": "Her",
        "gender7": "others",
    },
}

DATE_FMTS = ["%Y-%m-%d", "%m/%d/%Y", "%m/%d/%y", "%Y-%m-%d %H:%M:%S"]

# ------------------------- HELPERS -------------------------

def parse_date(val: str) -> Optional[datetime]:
    if not val or not isinstance(val, str):
        return None
    val = val.strip()
    for fmt in DATE_FMTS:
        try:
            return datetime.strptime(val, fmt)
        except ValueError:
            continue
    return None


def date_in_range(val: str, start: Optional[datetime], end: Optional[datetime]) -> bool:
    if not start and not end:
        return True  # no filtering
    dt = parse_date(val)
    if not dt:
        return False
    if start and dt < start:
        return False
    if end and dt > end:
        return False
    return True


def fmt_mmddyyyy(dt_str: str) -> str:
    dt = parse_date(dt_str)
    return dt.strftime("%m/%d/%Y") if dt else ""


def sanitize_filename(name: str) -> str:
    for ch in '<>:"/\\|?*':
        name = name.replace(ch, "")
    return name.strip()


def docx_to_pdf(docx_path: str, pdf_dir: str, retries: int = 3) -> Optional[str]:
    """Convert DOCX ➜ PDF via LibreOffice. Returns PDF path or None."""
    attempt = 0
    while attempt < retries:
        try:
            out_path = os.path.join(pdf_dir, os.path.basename(docx_path).replace(".docx", ".pdf"))
            subprocess.run([
                "libreoffice",
                "--headless",
                "--convert-to",
                "pdf",
                "--outdir",
                pdf_dir,
                docx_path,
            ], check=True)
            if os.path.exists(out_path):
                return out_path
        except subprocess.CalledProcessError as e:
            logging.error("LibreOffice conversion failed (%s): %s", docx_path, e)
            attempt += 1
            time.sleep(1)
    return None


# ------------------------- LOAD CSV -------------------------
try:
    df = pd.read_csv(CSV_PATH).fillna("")

    # clean up any victim_zip floats → strings without “.0”
    for i in range(1,6):
        col = f"victim_zip{i}"
        if col in df.columns:
            # convert everything to str, then remove a trailing “.0”
            df[col] = (
                df[col]
                .astype(str)
                .str.replace(r'\.0+$', '', regex=True)
            )
except Exception as e:
    logging.exception("Failed to read CSV: %s", e)
    sys.exit(1)

# -----------------------------------------------------------------
# NORMALISE COLUMNS + BUILD exit_reason_block  (Victim Exit letters)
# -----------------------------------------------------------------
# 1) camelCase  →  snake_case used elsewhere
df = df.rename(columns={
    'disruptiveOrArgumentitive'        : 'disruptive_argumentitive',
    'inappropriateHumor'               : 'humor_inappropriate',
    'blamesVictim'                     : 'blames_victim',
    'drug_alcohol'                     : 'appears_drug_alcohol',
    'inappropriate_behavior_to_staff'  : 'inappropriate_to_staff',
    'speaksSignificantlyInGroup'       : 'speaks_significantly_in_group',
    'respectfulTowardsGroup'           : 'respectful_to_group',
    'takesResponsibilityForPastBehavior': 'takes_responsibility_for_past'
})

# 2) map 1/0/Y/N → Yes/No once for all clinics
BOOL_COLS = [
    'disruptive_argumentitive','humor_inappropriate','blames_victim',
    'appears_drug_alcohol','inappropriate_to_staff',
    'speaks_significantly_in_group','respectful_to_group',
    'takes_responsibility_for_past'
]
for c in BOOL_COLS:
    if c in df.columns:
        df[c] = df[c].map({'1':'Yes','0':'No','Y':'Yes','N':'No','y':'Yes','n':'No'}).fillna('No')

ABSENCE_LIMIT = 3   # “≥ 3 un-excused absences”

def build_exit_reason_block(r):
    reasons = []
    # DB “exit_reason” always leads
    if r.get('exit_reason'):
        reasons.append(r['exit_reason'])

    # attendance
    try:
        abs_unexcused = int(float(r.get('absence_unexcused', 0)))
    except ValueError:
        abs_unexcused = 0
    if abs_unexcused >= ABSENCE_LIMIT:
        reasons.append(f"{abs_unexcused} un-excused absences")

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
        if col not in r:        # column absent in CSV → ignore
            continue
        val = r[col]
        trigger = (val == 'Yes') if col not in [
            'respectful_to_group','speaks_significantly_in_group',
            'takes_responsibility_for_past'
        ] else (val == 'No')
        if trigger:
            reasons.append(msg)

    return "; ".join(reasons) if reasons else "See case notes"

df['exit_reason_block'] = df.apply(build_exit_reason_block, axis=1)


# Restrict to DWI programs
PROGRAM_FILTER = ["DWI"]
df = df[df["program_name"].isin(PROGRAM_FILTER)]
if df.empty:
    logging.warning("No DWI records found – exiting.")
    sys.exit(0)

# Parse date filters
start_dt = parse_date(args.start_date) if args.start_date else None
end_dt = parse_date(args.end_date) if args.end_date else None

# ------------------------- MAIN PROCESS -------------------------
letters_generated = 0

for idx, row in df.iterrows():
    row_dict = row.to_dict()

    # Determine which letter type applies – exactly one should match
    applicable_type: Optional[str] = None
    for lt_key, lt_cfg in LETTER_TYPES.items():
        if lt_cfg["criteria"](row_dict, start_dt, end_dt):
            applicable_type = lt_key
            break
    if not applicable_type:
        continue  # Skip rows that do not meet criteria

    # Ensure at least one victim exists
    victim_slots = [i for i in range(1, 6) if str(row_dict.get(f"victim_name{i}", "")).strip()]
    if not victim_slots:
        continue

    # Gender placeholders
    gender_key = row_dict.get("gender", "").lower()
    gender_map = PRONOUNS.get(gender_key, {k: "" for k in PRONOUNS["male"].keys()})

    # Prepare common client context
    client_ctx = {
        **row_dict,
        **gender_map,
        "orientation_date": fmt_mmddyyyy(row_dict.get("orientation_date")),
        "exit_date": fmt_mmddyyyy(row_dict.get("exit_date")),
        "last_attended": fmt_mmddyyyy(row_dict.get("last_attended")),
        "required_sessions": row_dict.get("required_sessions", ""),
        "first_group": fmt_mmddyyyy(row_dict.get("first_group")),
    }

    # Template path
    template_file = os.path.join(TEMPLATES_DIR, LETTER_TYPES[applicable_type]["template"])
    if not os.path.exists(template_file):
        logging.error("Template not found: %s", template_file)
        continue

    # Iterate victim slots
    for v_idx in victim_slots:
        victim_ctx = {
            "victim_name": row_dict.get(f"victim_name{v_idx}", ""),
            "victim_address1": row_dict.get(f"victim_address1{v_idx}", ""),
            "victim_address2": row_dict.get(f"victim_address2{v_idx}", ""),
            "victim_city": row_dict.get(f"victim_city{v_idx}", ""),
            "victim_state": row_dict.get(f"victim_state{v_idx}", ""),
            "victim_zip": row_dict.get(f"victim_zip{v_idx}", ""),
        }

        context = {**client_ctx, **victim_ctx}

        # Render DOCX
        try:
            doc = DocxTemplate(template_file)
            doc.render(context)
        except Exception:
            logging.exception("Render failed for client %s (victim slot %d)", idx, v_idx)
            continue

        # Build filename/path
        fn_first = sanitize_filename(context.get("first_name", ""))
        fn_last = sanitize_filename(context.get("last_name", ""))
        fn_victim = sanitize_filename(victim_ctx["victim_name"])
        doc_name = f"{fn_last} {fn_first} Victim {applicable_type.title()} - {fn_victim}.docx"
        doc_path = os.path.join(GENERATED_DIR, doc_name)
        doc.save(doc_path)

        # Convert to PDF
        pdf_path = docx_to_pdf(doc_path, GENERATED_DIR)
        if pdf_path:
            os.remove(doc_path)
        else:
            logging.error("Conversion to PDF failed: %s", doc_path)
            continue

        # Organize into /Office/CaseManager
        victim = "Victim Letters"
        client_first = sanitize_filename(row_dict.get("first_name", ""))
        client_last = sanitize_filename(row_dict.get("last_name", ""))
        client_folder = (client_first + " " + client_last).strip()

        target_dir = os.path.join(CENTRAL_DIR, victim, client_folder)
        os.makedirs(target_dir, exist_ok=True)
        shutil.move(pdf_path, os.path.join(target_dir, os.path.basename(pdf_path)))
        letters_generated += 1
        logging.info("Generated letter ➜ %s", os.path.join(target_dir, os.path.basename(pdf_path)))

# ------------------------- SUMMARY -------------------------
logging.info("Victim letter generation complete – %d PDFs created.", letters_generated)
print(f"Victim letter generation complete – {letters_generated} PDFs created. See logs at {LOG_PATH}")
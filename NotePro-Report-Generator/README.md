# Reporting GUI Application

## Overview
The **Reporting GUI** application is a web-based interface for generating various reports and documents related to multiple programs like BIPP, Anger Control, and Thinking for a Change. The system uses Python, Flask, HTML, and relevant document scripts to automate the process of report generation, handling CSV uploads, and generating ZIP files for user download.

This README outlines the structure and functionality of the key components of the project, including `Reporting_GUI.py`, `index.html`, and all the document scripts.

---

## Table of Contents
- [Requirements](#requirements)
- [File Structure](#file-structure)
- [Main Components](#main-components)
  - [Reporting_GUI.py](#reporting_guipy)
  - [index.html](#indexhtml)
  - [Document Scripts](#document-scripts)
- [Usage](#usage)
  - [How to Run the Application](#how-to-run-the-application)
  - [Generating Reports](#generating-reports)
- [Folder Structure](#folder-structure)
- [Known Issues](#known-issues)
- [Future Features](#future-features)

---

## Requirements
- Python 3.x
- Flask
- Pandas
- Jinja2
- pypandoc (for DOCX to PDF conversion)
- Celery & Redis (for managing asynchronous tasks)
- A MySQL database (connected via phpMyAdmin)

![Visualization of the codebase](./diagram.svg)

### Installation
To install the required Python packages, run the following command:

```bash
pip install -r requirements.txt
.
├── Reporting_GUI.py            # Main application logic for the web interface
├── templates/
│   └── index.html              # HTML file for the user interface
├── static/
│   └── styles.css              # Optional: CSS styling for the web interface
├── scripts/
│   └── bipp_completion.py      # Example document script for BIPP completion
│   └── anger_control.py        # Example document script for Anger Control program
│   └── thinking_for_a_change.py # Example document script for Thinking for a Change program
├── uploads/                    # Folder for CSV uploads
├── GeneratedDocuments/         # Folder for storing generated reports
├── DocumentsCSV/               # Folder for saving CSVs for Generate Reports
└── README.md                   # This file

 ```markdown
 ![Visualization of the codebase](./diagram.svg)
 ```

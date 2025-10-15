import os
import subprocess
from flask import Flask, render_template, request, url_for, send_file, jsonify, send_from_directory, session
import zipfile
from datetime import datetime
from check_absences import process_absences
import pandas as pd
import logging
import shutil
from celery import Celery, group
from flask_cors import CORS
import threading
import time

# Initialize Flask application
app = Flask(__name__, static_folder='static')
app.secret_key = 'beetlejuice'
CORS(app)

# Base directory path
base_dir = os.path.dirname(os.path.abspath(__file__))
last_uploaded_csv = None
download_status = {'completed': False}

# Configure Celery
app.config.update(
    broker_url='redis://localhost:6379/0',  # Correct broker URL
    result_backend='redis://localhost:6379/0',  # Result backend configuration
    broker_connection_retry_on_startup=True  # Added this line
)

# Define the Flask route for the home page
@app.route('/')
def index():
    app.logger.debug("Accessing index route")
    return render_template('index.html'), 200, {'Content-Type': 'text/html; charset=utf-8'}

@app.route('/static/<path:filename>')
def send_static(filename):
    response = send_from_directory('static', filename)
    response.headers['Cache-Control'] = 'public, max-age=31536000, immutable'
    return response

@app.route('/fetch_data', methods=['GET'])
def fetch_data():
    try:
        # Call fetch_data.php using subprocess
        result = subprocess.run(
            ["php", "/home/notesao/NotePro-Report-Generator/fetch_data.php"],
            capture_output=True,
            text=True
        )
        
        # Check for errors
        if result.returncode != 0:
            return jsonify(status="error", message=result.stderr), 500

        # Return success message
        return jsonify(status="success", message="Data fetched successfully.")

    except Exception as e:
        return jsonify(status="error", message=str(e)), 500

# Configure logging
logging.basicConfig(level=logging.DEBUG)

# Adjust Celery time limits based on environment variables or use defaults
CELERY_SOFT_TIME_LIMIT = int(os.getenv('CELERY_SOFT_TIME_LIMIT', '12000'))  # 20 minutes default
CELERY_TIME_LIMIT = int(os.getenv('CELERY_TIME_LIMIT', '24000'))  # 40 minutes default

# Function to initialize Celery with the Flask app
def make_celery(app):
    celery = Celery(
        app.import_name,
        backend=app.config['result_backend'],
        broker=app.config['broker_url']
    )
    celery.conf.update(app.config)
    
    # Set task time limits for Celery
    celery.conf.task_soft_time_limit = CELERY_SOFT_TIME_LIMIT
    celery.conf.task_time_limit = CELERY_TIME_LIMIT

    # ContextTask class to provide Flask's app context to Celery tasks
    class ContextTask(celery.Task):
        def __call__(self, *args, **kwargs):
            with app.app_context():
                return self.run(*args, **kwargs)

    celery.Task = ContextTask
    return celery

# Initialize Celery
celery = make_celery(app)

logging.basicConfig(level=logging.DEBUG)

@celery.task(bind=True)
def run_bipp_progress_report(self, csv_file_path, script_info_list):
    report_result = {"success": [], "errors": []}
    total_scripts = len(script_info_list)

    try:
        for i, script_info in enumerate(script_info_list):
            script_path = script_info['path']
            command = ['python', script_path, '--csv_file', csv_file_path]

            if script_info.get('requires_dates'):
                command.extend(['--start_date', script_info['start_date'], '--end_date', script_info['end_date']])

            result = subprocess.run(command, capture_output=True, text=True, timeout=6000)

            if result.returncode == 0:
                report_result['success'].append(f"Script {script_path} completed successfully.")
            else:
                report_result['errors'].append(f"Error in {script_path}: {result.stderr}")

            # Update the state of the task with progress
            self.update_state(state='PROGRESS', meta={'current': i + 1, 'total': total_scripts})
        
        return report_result
    except Exception as e:
        report_result['errors'].append(f"Task failed: {str(e)}")
        return report_result

@celery.task(bind=True)
def generate_bipp_progress_reports(self, csv_file_path, start_date, end_date, soft_time_limit=30000, time_limit=60000):
    logging.info("Generating BIPP Progress Reports...")
    try:
        script_path = os.path.join(base_dir, 'BIPP_Progress_Reports_Script.py')
        logging.info(f"Running script: {script_path}")
        result = subprocess.run(
            ['python', script_path, '--csv_file', csv_file_path, '--start_date', start_date, '--end_date', end_date],
            capture_output=True, text=True
        )
        logging.info(f"Subprocess result: {result.stdout}")
        
        if result.returncode == 0:
            logging.info("BIPP Progress Reports generated successfully.")
            return {"status": "success"}
        else:
            logging.error(f"Error in BIPP Progress Reports: {result.stderr}")
            return {"status": "error", "error": result.stderr}
    except Exception as e:
        logging.error(f"Exception in progress reports task: {e}")
        return {"status": "error", "error": str(e)}

@celery.task(bind=True)
def generate_ac_progress_reports(self, csv_file_path, start_date, end_date):
    logging.info("Generating Anger Control Progress Reports...")
    script_path = os.path.join(base_dir, 'AC_Progress_Reports_Script.py')
    result = subprocess.run(
        ['python', script_path, '--csv_file', csv_file_path, '--start_date', start_date, '--end_date', end_date],
        capture_output=True, text=True
    )
    
    if result.returncode == 0:
        logging.info("Anger Control Progress Reports generated successfully.")
        return {"status": "success"}
    else:
        logging.error(f"Error in Anger Control Progress Reports: {result.stderr}")
        return {"status": "error", "error": result.stderr}

@celery.task(bind=True)
def generate_t4c_progress_reports(self, csv_file_path, start_date, end_date):
    logging.info("Generating Thinking for a Change Progress Reports...")
    script_path = os.path.join(base_dir, 'T4C_Progress_Reports_Script.py')
    result = subprocess.run(
        ['python', script_path, '--csv_file', csv_file_path, '--start_date', start_date, '--end_date', end_date],
        capture_output=True, text=True
    )
    
    if result.returncode == 0:
        logging.info("T4C Progress Reports generated successfully.")
        return {"status": "success"}
    else:
        logging.error(f"Error in T4C Progress Reports: {result.stderr}")
        return {"status": "error", "error": result.stderr}

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    # Check the file extension to determine directory and MIME type
    if filename.endswith('.pdf'):
        directory = os.path.join(base_dir, 'absence')
        mimetype = 'application/pdf'
    elif filename.endswith('.csv'):
        directory = os.path.join(base_dir, 'uploads')
        mimetype = 'text/csv'
    else:
        directory = os.path.join(base_dir, 'uploads')
        mimetype = 'application/octet-stream'  # Default MIME type for unknown files

    try:
        # Serve the file from the correct directory with the appropriate MIME type
        return send_from_directory(directory, filename, as_attachment=True, mimetype=mimetype)
    except FileNotFoundError:
        return "File not found.", 404

# Helper function to clear all specified directories after download
def clear_all_directories():
    """Clears all specified directories after the download process, and deletes any .zip files in the base directory."""
    allowed_directories = ['__pycache__', 'absence', 'csv', 'uploads', 'GeneratedDocuments', 'DocumentsCSV']
    
    # Clear contents of allowed directories
    for directory in allowed_directories:
        folder_path = os.path.join(base_dir, directory)
        if os.path.exists(folder_path):
            shutil.rmtree(folder_path)  # Remove the directory
            os.makedirs(folder_path)    # Recreate an empty directory
            logging.info(f"Cleared directory: {directory}")

    # Delete all .zip files in the base directory
    for zip_file in os.listdir(base_dir):
        if zip_file.endswith('.zip'):
            zip_file_path = os.path.join(base_dir, zip_file)
            try:
                os.remove(zip_file_path)
                logging.info(f"Deleted ZIP file: {zip_file_path}")
            except Exception as e:
                logging.error(f"Failed to delete ZIP file {zip_file_path}: {e}")


# Clear directories at the start of the script

@app.route('/task_status/<task_id>')
def task_status(task_id):
    task = run_bipp_progress_report.AsyncResult(task_id)

    if task.state == 'PENDING':
        response = {'state': 'PENDING', 'status': 'Pending...'}
    elif task.state == 'SUCCESS':
        response = {
            'state': 'SUCCESS',
            'status': task.info,  # More detailed info from run_bipp_progress_report
            'result': task.result  # Access detailed task results
        }
    elif task.state == 'FAILURE':
        response = {
            'state': 'FAILURE',
            'status': str(task.info),
            'result': task.result  # Include failure details
        }
    else:
        response = {
            'state': task.state,
            'status': task.info
        }

    return jsonify(response)

@app.route('/Check_Absences', methods=['POST'])
def Check_Absences():
    csv_file = request.files['csv_file']
    report_date = request.form.get('report_date', datetime.now().strftime('%m/%d/%Y'))

    # Save the uploaded CSV file to the 'uploads' folder
    csv_path = os.path.join(base_dir, 'uploads', csv_file.filename)
    os.makedirs(os.path.dirname(csv_path), exist_ok=True)
    csv_file.save(csv_path)

    output_directory = os.path.join(base_dir, 'absence')
    os.makedirs(output_directory, exist_ok=True)

    try:
        # Call the process_absences function to generate the PDF
        output_pdf = process_absences(csv_path, report_date, output_directory)

        # Check if the PDF was generated
        if os.path.exists(output_pdf):
            # Return the download URL for the generated PDF
            download_url = url_for('uploaded_file', filename=os.path.basename(output_pdf))  # Correct URL for the PDF
            return jsonify({'status': 'success', 'download_url': download_url})
        else:
            return jsonify({'status': 'error', 'message': 'PDF generation failed'}), 500
        
    except Exception as e:
        return f"An error occurred while generating the absence report: {str(e)}", 500

    finally:
        # Delay the clearing of the directory until after the file is downloaded
        # clear_directory('absence')  # Comment this out for now, and clear after download
        pass

@app.route('/update_clients', methods=['POST'])
def update_clients():
    logging.info("Updating clients route accessed")

    # Get form data
    csv_file = request.files['csv_file']
    start_date_str = request.form['start_date']
    end_date_str = request.form['end_date']

    # Ensure the uploads directory exists
    upload_directory = os.path.join(base_dir, 'uploads')
    if not os.path.exists(upload_directory):
        os.makedirs(upload_directory)

    # Save the uploaded CSV file
    csv_path = os.path.join(upload_directory, csv_file.filename)
    csv_file.save(csv_path)
    logging.info(f"CSV file saved to: {csv_path}")

    # Define the filename for the updated CSV
    updated_filename = f"Updated_{csv_file.filename}"
    updated_csv_path = os.path.join(upload_directory, updated_filename)

    # Define the script path and arguments
    script_path = os.path.join(base_dir, 'Update_Clients.py')

    try:
        # Run the script using subprocess, passing the required arguments
        result = subprocess.run(
            ['python', script_path, '--csv_file', csv_path, '--start_date', start_date_str, '--end_date', end_date_str, '--output_csv_path', updated_csv_path],
            capture_output=True, text=True
        )

        # If the script was successful
        if result.returncode == 0:
            logging.info(f"Client notes updated successfully. Output file: {updated_csv_path}")
            return jsonify({'status': 'success', 'download_url': url_for('uploaded_file', filename=os.path.basename(updated_csv_path))})
        else:
            logging.error(f"Error in script: {result.stderr}")
            return jsonify({'status': 'error', 'message': f"Error in script: {result.stderr}"}), 500

    except Exception as e:
        logging.error(f"An error occurred while updating client notes: {str(e)}")
        return jsonify({'status': 'error', 'message': f"An error occurred: {str(e)}"}), 500

    finally:
        pass

@app.route('/clear_directories', methods=['POST'])
def clear_directories_route():
    try:
        clear_all_directories()
        return jsonify({'status': 'success', 'message': 'Directories cleared successfully.'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': f"An error occurred: {str(e)}"}), 500

@app.route('/generate_reports', methods=['POST'])
def generate_reports():
    logging.info("Generating reports...")

    # Define the upload directory specifically for generate reports in the "DocumentsCSV" folder
    upload_directory = os.path.join(base_dir, 'DocumentsCSV')

    # Check if the DocumentsCSV folder exists, and create it if it doesn't
    if not os.path.exists(upload_directory):
        os.makedirs(upload_directory)
        logging.info(f"Created 'DocumentsCSV' folder in base directory at {upload_directory}")

    # Save the uploaded CSV file to the 'DocumentsCSV' folder
    csv_file = request.files['csv_file']
    csv_path = os.path.join(upload_directory, csv_file.filename)
    csv_file.save(csv_path)

    # Define script_paths locally within the function
    script_paths = {
        "BIPP": {
            "Completion Documents": {
                "path": os.path.join(base_dir, 'BIPP_Completion_Documents_Script.py'),
                "requires_dates": True
            },
            "Entrance Notifications": {
                "path": os.path.join(base_dir, 'BIPP_Entrance_Notifications_Script.py'),
                "requires_dates": True
            },
            "Exit Notices": {
                "path": os.path.join(base_dir, 'BIPP_Exit_Notices_Script.py'),
                "requires_dates": True
            },
            "Unexcused Absences": {
                "path": os.path.join(base_dir, 'BIPP_Unexcused_Absences_Script.py'),
                "requires_dates": True
            }
        },
        "Anger Control": {
            "Completion Documents": {
                "path": os.path.join(base_dir, 'AC_Completion_Documents_Script.py'),
                "requires_dates": True
            },
            "Entrance Notifications": {
                "path": os.path.join(base_dir, 'AC_Entrance_Notifications_Script.py'),
                "requires_dates": True
            },
            "Exit Notices": {
                "path": os.path.join(base_dir, 'AC_Exit_Notices_Script.py'),
                "requires_dates": True
            },
            "Unexcused Absences": {
                "path": os.path.join(base_dir, 'AC_Unexcused_Absences_Script.py'),
                "requires_dates": True
            }
        },
        "Thinking for a Change": {
            "Completion Documents": {
                "path": os.path.join(base_dir, 'T4C_Completion_Documents_Script.py'),
                "requires_dates": True
            },
            "Entrance Notifications": {
                "path": os.path.join(base_dir, 'T4C_Entrance_Notifications_Script.py'),
                "requires_dates": True
            },
            "Exit Notices": {
                "path": os.path.join(base_dir, 'T4C_Exit_Notices_Script.py'),
                "requires_dates": True
            },
            "Unexcused Absences": {
                "path": os.path.join(base_dir, 'T4C_Unexcused_Absences_Script.py'),
                "requires_dates": True
            }
        },
        # Similar for "Anger Control" and "Thinking for a Change"
    }

    synchronous_tasks_completed = False  # Flag to indicate completion of synchronous tasks

    # List to hold Celery tasks for Progress Reports (asynchronous)
    async_tasks = []

    # Handle Progress Reports asynchronously
    if "Progress Reports" in request.form.getlist('reports'):
        logging.info(f"Processing Progress Reports for {request.form['program']}")

        # Use Celery for asynchronous processing based on the program selected
        program = request.form['program']
        start_date = request.form['start_date']
        end_date = request.form['end_date']

        if program == "BIPP":
            task = generate_bipp_progress_reports.apply_async(args=[csv_path, start_date, end_date])
        elif program == "Anger Control":
            task = generate_ac_progress_reports.apply_async(args=[csv_path, start_date, end_date])
        elif program == "Thinking for a Change":
            task = generate_t4c_progress_reports.apply_async(args=[csv_path, start_date, end_date])
        
        async_tasks.append(task)  # Add task to the async task list

    # Handle other reports synchronously (e.g., Entrance Notifications, Completion Documents)
    for report_name in request.form.getlist('reports'):
        if report_name != "Progress Reports":  # Exclude Progress Reports, already handled asynchronously
            script_info = script_paths.get(request.form['program'], {}).get(report_name)

            if script_info:
                script_path = script_info['path']
                command = ['python', script_path, '--csv_file', csv_path]

                # Add start and end dates if required
                if script_info.get('requires_dates'):
                    command.extend(['--start_date', request.form['start_date'], '--end_date', request.form['end_date']])

                # Run the script synchronously
                logging.info(f"Running {report_name} for {request.form['program']}")
                result = subprocess.run(command, capture_output=True, text=True, timeout=3600)

                # Handle script output
                if result.returncode == 0:
                    logging.info(f"{report_name} for {request.form['program']} generated successfully.")
                else:
                    logging.error(f"Error in {report_name}: {result.stderr}")
                    return jsonify({'status': 'error', 'message': f"Error in {report_name}: {result.stderr}"}), 500

    # Mark synchronous tasks as completed
    synchronous_tasks_completed = True

    # After synchronous tasks are completed, wait until progress reports are generated
    if async_tasks:
        # Wait for all async tasks to complete
        while not all(task.ready() for task in async_tasks):
            time.sleep(1)  # Small delay to avoid busy-waiting

        # Now that progress reports are done, they are already sorted by the script itself
        for task in async_tasks:
            if task.status == "SUCCESS":
                logging.info(f"Progress reports task {task.id} completed successfully.")

        task_ids = [task.id for task in async_tasks]
        return jsonify({'status': 'processing', 'task_ids': task_ids}), 202

    return jsonify({'status': 'success', 'message': 'Reports generated successfully!'}), 200

def create_zip():
    # Get today's date to name the ZIP file
    today = datetime.now().strftime('%m.%d.%y')
    zip_filename = f"Generated_Reports_{today}.zip"
    zip_path = os.path.join(base_dir, zip_filename)

    # Directory where generated documents are stored
    generated_documents_dir = os.path.join(base_dir, 'GeneratedDocuments')

    # Remove the old ZIP file if it exists (to avoid conflicts)
    if os.path.exists(zip_path):
        try:
            os.remove(zip_path)
            logging.info(f"Old ZIP file removed: {zip_path}")
        except Exception as e:
            logging.error(f"Error removing old ZIP file: {e}")
            return {'status': 'error', 'message': f"Error removing old ZIP file: {e}"}

    # Create a ZIP file of all contents in 'GeneratedDocuments'
    try:
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for root, dirs, files in os.walk(generated_documents_dir):
                for file in files:
                    file_path = os.path.join(root, file)
                    zipf.write(file_path, os.path.relpath(file_path, generated_documents_dir))
        logging.info(f"Created ZIP file at {zip_path}")
        return {'status': 'success', 'zip_path': zip_path}
    except Exception as e:
        logging.error(f"Failed to create ZIP file: {e}")
        return {'status': 'error', 'message': f"Failed to create zip file: {e}"}   

@app.route('/download_reports')
def download_reports():
    zip_result = create_zip()
    if zip_result['status'] == 'error':
        return jsonify({'status': 'error', 'message': zip_result['message']}), 500
    
    # Get today's date to name the ZIP file
    today = datetime.now().strftime('%m.%d.%y')
    zip_filename = f"Generated_Reports_{today}.zip"
    zip_path = os.path.join(base_dir, zip_filename)

    # Ensure the ZIP file exists before attempting to send it
    if not os.path.exists(zip_path):
        logging.error(f"ZIP file not found: {zip_path}")
        return "Error: ZIP file not found", 404

    # Serve the ZIP file and delete it after sending
    response = send_file(zip_path, as_attachment=True, mimetype='application/zip')

    return response

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
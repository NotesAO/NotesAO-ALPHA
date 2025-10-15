#!/bin/bash

# Full path to the project directory
PROJECT_DIR="/home/notesao/NotePro-Report-Generator"
VENV_DIR="$PROJECT_DIR/venv311"
LOG_FILE="/var/log/manage_services.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

# Activate virtual environment
source "$VENV_DIR/bin/activate"

# Install necessary Python packages
log_message "Installing Flask-CORS..."
$VENV_DIR/bin/pip install flask-cors

# Stop services if they are already running
log_message "Stopping existing processes..."
sudo pkill -f gunicorn
sudo pkill -f 'celery worker'
sudo pkill -f redis-server

# Ensure no processes are running on important ports
log_message "Ensuring no processes are using the necessary ports..."
PORT=8002
if sudo lsof -t -i:$PORT > /dev/null; then
    sudo lsof -t -i:$PORT | sudo xargs kill -9
    log_message "Port $PORT has been freed."
else
    log_message "Port $PORT is not in use."
fi

# Start Redis
log_message "Starting Redis..."
nohup redis-server $PROJECT_DIR/redis.conf > ~/redis.out &
sleep 2
if pgrep -f 'redis-server' > /dev/null; then
    log_message "Redis is running"
else
    log_message "Failed to start Redis"
    exit 1
fi

# Start Celery worker with PYTHONPATH set
log_message "Starting Celery worker..."
nohup env PYTHONPATH=$PROJECT_DIR $VENV_DIR/bin/celery --app=Reporting_GUI.celery worker --loglevel=info --soft-time-limit=12000 --time-limit=18000 > ~/celery.out &
sleep 5
if pgrep -f 'celery worker' > /dev/null; then
    log_message "Celery is running"
else
    log_message "Failed to start Celery"
    exit 1
fi

# Start Gunicorn on port 8002
log_message "Starting Gunicorn on port 8002..."
nohup env PYTHONPATH=$PROJECT_DIR $VENV_DIR/bin/gunicorn -w 4 -b 127.0.0.1:8002 --timeout 2000 Reporting_GUI:app > ~/gunicorn.out &
sleep 5
if pgrep -f 'gunicorn' > /dev/null; then
    log_message "Gunicorn is running on port 8002"
else
    log_message "Failed to start Gunicorn"
    exit 1
fi

# Confirm Gunicorn is listening on port 8002
if sudo lsof -i:8002 > /dev/null; then
    log_message "Gunicorn confirmed on port 8002"
else
    log_message "Gunicorn is not active on port 8002. Attempting restart..."
    sudo pkill -f gunicorn
    nohup env PYTHONPATH=$PROJECT_DIR $VENV_DIR/bin/gunicorn -w 4 -b 127.0.0.1:8002 --timeout 2000 Reporting_GUI:app > ~/gunicorn.out &
    sleep 5
    if sudo lsof -i:8002 > /dev/null; then
        log_message "Gunicorn successfully restarted on port 8002"
    else
        log_message "Failed to restart Gunicorn on port 8002. Manual intervention required."
        exit 1
    fi
fi

log_message "All services are running successfully!"

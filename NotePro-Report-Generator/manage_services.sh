#!/bin/bash

# Full path to the project directory
PROJECT_DIR="/home/gabecthomas/NotePro-Report-Generator"
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
sudo pkill -f nginx
sudo pkill -f 'celery worker'
sudo pkill -f redis-server

# Ensure no processes are running on important ports
log_message "Ensuring no processes are using the necessary ports..."
PORTS_TO_CHECK=(80 8000 8001)
for PORT in "${PORTS_TO_CHECK[@]}"; do
    if sudo lsof -t -i:$PORT > /dev/null; then
        sudo lsof -t -i:$PORT | sudo xargs kill -9
        log_message "Port $PORT has been freed."
    else
        log_message "Port $PORT is not in use."
    fi
done

# Start Redis
log_message "Starting Redis..."
if ! pgrep -f 'redis-server' > /dev/null; then
    redis-server &
    sleep 2
    # Verify Redis is running
    if pgrep -f 'redis-server' > /dev/null; then
        log_message "Redis is running"
    else
        log_message "Failed to start Redis"
        exit 1
    fi
else
    log_message "Redis is already running"
fi

# Start Nginx
log_message "Starting Nginx..."
sudo systemctl start nginx

# Verify Nginx is running
if systemctl status nginx | grep -q "active (running)"; then
    log_message "Nginx is running"
else
    log_message "Failed to start Nginx"
    exit 1
fi

# Start Celery worker
log_message "Starting Celery worker..."
$VENV_DIR/bin/celery -A Reporting_GUI.celery worker --loglevel=info &

# Verify Celery is running
sleep 5
if pgrep -f 'celery worker' > /dev/null; then
    log_message "Celery is running"
else
    log_message "Failed to start Celery"
    exit 1
fi

# Start Gunicorn
log_message "Starting Gunicorn..."
$VENV_DIR/bin/gunicorn -w 4 -b 127.0.0.1:8001 Reporting_GUI:app &

# Verify Gunicorn is running
sleep 5
if pgrep -f 'gunicorn' > /dev/null; then
    log_message "Gunicorn is running"
else
    log_message "Failed to start Gunicorn"
    exit 1
fi

log_message "All services are running successfully!"

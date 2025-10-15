# gunicorn_config.py

# Number of worker processes
workers = 4

# Bind to all network interfaces on port 5000
bind = '0.0.0.0:5000'

# Set the worker timeout (in seconds)
timeout = 6000

# Additional optional settings (if needed)
worker_class = 'sync'  # The worker class, can also use 'gevent' or 'eventlet' for async workers
loglevel = 'info'      # Logging level
accesslog = '-'        # Log access requests to stdout
errorlog = '-'         # Log errors to stdout


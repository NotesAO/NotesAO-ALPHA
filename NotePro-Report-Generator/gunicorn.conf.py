# gunicorn.conf.py

# Bind to a specific address and port
bind = "0.0.0.0:5000"

# Number of worker processes
workers = 4

# Timeout in seconds for each worker. Increase this if the report generation takes a long time.
timeout = 3600  # Set to 300 seconds (5 minutes) to handle long-running tasks

# Optionally, you can specify the worker class if needed
# worker_class = 'sync'  # Default is 'sync', you can also use 'gevent', 'eventlet', etc.

# Logging
accesslog = "-"  # Log access requests to stdout
errorlog = "-"   # Log errors to stdout
loglevel = "info"


#!/bin/bash
export DB_HOST="50.28.37.79"
export DB_NAME="clinicnotepro_sage"
export DB_USER="clinicnotepro_sage_app"
export DB_PASS="PF-m[T-+pF%g"

LOG_FILE="/home/notesao/fetch_data_sage_log_$(date +'%Y%m%d').log"

echo "Running fetch_data.php locally in CLI mode for 'sage'..." | tee -a "$LOG_FILE"
php /home/notesao/NotePro-Report-Generator/fetch_data.php sage >> "$LOG_FILE" 2>&1
echo "Finished fetch_data.php for sage" | tee -a "$LOG_FILE"
echo "Fetch data process completed." | tee -a "$LOG_FILE"

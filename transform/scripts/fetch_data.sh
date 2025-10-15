#!/bin/bash
export DB_HOST="50.28.37.79"
export DB_NAME="clinicnotepro_transform"
export DB_USER="clinicnotepro_transform_app"
export DB_PASS="PF-m[T-+pF%g"

LOG_FILE="/home/notesao/fetch_data_transform_log_$(date +'%Y%m%d').log"

echo "Running fetch_data.php locally in CLI mode for 'transform'..." | tee -a "$LOG_FILE"
php /home/notesao/NotePro-Report-Generator/fetch_data.php transform >> "$LOG_FILE" 2>&1
echo "Finished fetch_data.php for transform" | tee -a "$LOG_FILE"
echo "Fetch data process completed." | tee -a "$LOG_FILE"

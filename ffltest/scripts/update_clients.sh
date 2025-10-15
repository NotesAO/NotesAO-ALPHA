#!/bin/bash

DAY_OF_WEEK=$(date +%u)

if [[ "$DAY_OF_WEEK" -eq 1 ]]; then
    START_DATE=$(date -d '3 days ago' +"%Y-%m-%d")
    END_DATE=$(date -d 'yesterday' +"%Y-%m-%d")
else
    START_DATE=$(date -d 'yesterday' +"%Y-%m-%d")
    END_DATE=$START_DATE
fi

LOG_FILE="/home/notesao/update_clients_ffltest_log_$(date +'%Y%m%d').log"

echo "Running update_clients.php locally for ffltest from $START_DATE to $END_DATE..." | tee -a "$LOG_FILE"

php /home/notesao/ffltest/public_html/update_clients.php start_date=$START_DATE end_date=$END_DATE >> "$LOG_FILE" 2>&1

echo "Completed update_clients.php for ffltest." | tee -a "$LOG_FILE"

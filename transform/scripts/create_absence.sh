#!/bin/bash

export TODAY=`date +"%Y%m%d_%H%M%S"`
cd /home/notesao/transform/scripts
curl https://transform.notesao.com/absence_logic.php -s -o ./logs/absence_${TODAY}.html 
#curl -s https://freeforlifegroup.therapydatasolutions.com/absence_logic.php 
#curl -s https://freeforlifegroup.therapydatasolutions.com/completion_logic.php

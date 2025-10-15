#!/bin/bash

export TODAY=`date +"%Y%m%d"`
cd /home/notesao/bestoption/scripts
curl https://tbo.notesao.com/completion_logic.php -s -o ./logs/completion_${TODAY}.html 
#curl -s https://freeforlifegroup.therapydatasolutions.com/absence_logic.php 
#curl -s https://freeforlifegroup.therapydatasolutions.com/completion_logic.php

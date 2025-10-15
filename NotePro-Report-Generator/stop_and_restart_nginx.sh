#!/bin/bash

# Kill processes using port 80
sudo lsof -i :80 | awk 'NR>1 {print $2}' | sudo xargs kill -9

# Restart Nginx
sudo systemctl restart nginx


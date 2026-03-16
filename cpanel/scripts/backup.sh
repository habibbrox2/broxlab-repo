#!/bin/bash

APP="/home/user/deploy-system/app"
BACKUPS="/home/user/deploy-system/backups"

DATE=$(date +"%Y%m%d_%H%M%S")

tar -czf $BACKUPS/backup_$DATE.tar.gz $APP/current
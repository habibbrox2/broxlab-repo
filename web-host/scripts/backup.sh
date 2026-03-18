#!/bin/bash

APP="/home/tdhuedhn/broxlab/app"
BACKUPS="/home/tdhuedhn/broxlab/backups"

DATE=$(date +"%Y%m%d_%H%M%S")

tar -czf $BACKUPS/backup_$DATE.tar.gz $APP/current
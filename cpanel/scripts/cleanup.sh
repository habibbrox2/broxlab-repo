#!/bin/bash

RELEASES="/home/user/deploy-system/app/releases"

ls -dt $RELEASES/* | tail -n +6 | xargs rm -rf
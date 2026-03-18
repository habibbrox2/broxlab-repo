#!/bin/bash

BASE="/home/tdhuedhn/broxlab/app"

cd $BASE/releases

PREVIOUS=$(ls -dt */ | sed -n '2p')

ln -sfn "$BASE/releases/$PREVIOUS" "$BASE/current"

echo "Rollback successful"
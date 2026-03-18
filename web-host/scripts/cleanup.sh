#!/bin/bash

RELEASES="/home/tdhuedhn/broxlab/app/releases"

ls -dt $RELEASES/* | tail -n +6 | xargs rm -rf
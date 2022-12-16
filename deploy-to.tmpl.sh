#!/bin/sh

# ---------------------------------------------------------
# This file is a temporary bash script for uploading files
# to [[HOST]] ([[SERVER_NAME]])
#
# If you are looking at this file, it probably means the
# deployment failed for some reason. Either the script was
# manually terminated part way through or was never called.
#
# If this is the first time you're calling deploy-to.sh for
# this directory, it could mean that you have not configured
# the deploy-to.json file correctly.
#
# Delete this file and try again
#
# created: [[CREATED]]
# by:      [[USER]]
# ---------------------------------------------------------

# Record when this script was last run
touch [[TIME_CHECK_FILE]];

echo;
echo;
echo 'About to upload [[FILE_C]] file[[FILE_S]] in [[GROUP_C]] group[[GROUP_S]]';
echo [[SINCE]]
echo 'To [[SERVER_NAME]] ([[HOST]])';
echo 'As [[USER]]';
echo;
echo;

[[OUTPUT]]

echo;
echo;
echo;

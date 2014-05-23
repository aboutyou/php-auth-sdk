#!/bin/bash
#
# Simple 'howto' run example/ in php's built in webserver
#
# NOTICE:
# + This example was only tested with linux/osx (for windows refer to doku.pdf)
# + php-cli (command-line-interface) must be installed + php >= 5.4
#
# Its just an example, you always can use 'php -S somehostname.domain:80' directly in the terminal
# You must run that in this directory as root (e.g. sudo) and add '127.0.0.1 somehostname.domain' to your /etc/hosts

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

SDKHOST='mytestserver.local'
php -S ${SDKHOST}:80 || echo "" && echo "POSSIBLE REASON: add '127.0.0.1 $SDKHOST' to /etc/hosts"
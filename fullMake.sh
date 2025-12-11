#!/bin/bash
clear
# nvm use 20.18.0

php translationtool.phar convert-po-files
make

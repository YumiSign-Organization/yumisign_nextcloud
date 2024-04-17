# #!/bin/bash
# make


# # rm -f babel.config.js
# # rm -rf build/
# # rm -f composer.json
# # rm -f composer.lock
# # rm -f .eslintrc.js
# # rm -rf .git/
# # rm -f .gitignore
# # rm -f Makefile
# # find ./node_modules -mindepth 1 ! -regex '^./node_modules/vuejs-paginate\(/.*\)?' -delete
# # rm -f package.json
# # rm -f package-lock.json
# # rm -f phpunit.integration.xml
# # rm -f phpunit.xml
# # rm -f README.md
# # rm -rf src/
# # rm -f stylelint.config.js
# # rm -rf tests/
# # rm -rf translationfiles/
# # rm -f .travis.yml
# # rm -f webpack.js
# # rm -rf node_modules/
# # rm -f translationtool.phar
# # rm -f fullMake.sh
# # rm -f pre-package.sh


# #!/bin/bash
# APP_NAME=yumisign_nextcloud
# VERSION=1.0.2

# OCC=/var/www/sandbox.bayssette.fr/Nextclouds/dev-nextcloud-27.1.x/occ
# PRIV_KEY=/opt/Nextcloud/devKeys/$APP_NAME.key
# CERT_APP=/opt/Nextcloud/devKeys/$APP_NAME.crt
# BUILDS=/var/www/builds

# # Check phase

# if [ ! -f "$OCC" ]; then
#     echo "occ executable does not exist ($OCC)"
#     exit 1
# fi

# if [ ! -f "$PRIV_KEY" ]; then
#     echo "Private key does not exist ($PRIV_KEY)"
#     exit 1
# fi

# if [ ! -f "$CERT_APP" ]; then
#     echo "App certificate does not exist ($CERT_APP)"
#     exit 1
# fi

# if [ `whoami` != "www-data" ] ; then
# 	echo You are not Apache2 user, the process is finished
# 	exit 1
# fi

# echo Let\'s go !

# if [ ! -d "../build" ]; then
#     mkdir -p ../build
# fi

# # Clean Nextcloud
# rm -fr build/$APP_NAME
# rm -fr build/$APP_NAME-$VERSION.tar.gz

# # Copy this version into build folder
# cp -fr $APP_NAME build/
# cd build/$APP_NAME

# echo
# echo -------------------------------------------------------
# # prompting for choice
# read -p "Do you wish to compress this app ? (y)Yes/(n)No:- " choice

# # giving choices there tasks using
# case $choice in
#     [yY]* ) echo "Create Gzip" ;;
#     [nN]* ) exit ;;
#     *) exit ;;
# esac

# rm -rf .git/
# rm -rf build/
# rm -rf tests/

# rm -f .gitignore
# rm -f composer.json
# rm -f composer.lock
# rm -f package.json
# rm -f package-lock.json

# rm -f pre-package.sh

# # Sign App
# php $OCC integrity:sign-app --privateKey=$PRIV_KEY --certificate=$CERT_APP --path=`pwd`

# # Compress to upload to GitHub
# cd ..
# tar -czf $APP_NAME-$VERSION.tar.gz $APP_NAME

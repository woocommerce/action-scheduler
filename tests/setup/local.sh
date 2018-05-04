#!/usr/bin/env bash

# WordPress test setup script for Travis CI
#
# Author: Benjamin J. Balter ( ben@balter.com | ben.balter.com ), Prospress, Jeremy Pry
# License: GPL3

set -ev

# Set necessary variables
export WP_DIR="${WP_DIR:-/tmp/wordpress}"
export WP_CORE_DIR="${WP_CORE_DIR:-${WP_DIR}/src}"
export WP_TESTS_DIR="${WP_TESTS_DIR:-${WP_DIR}/tests/phpunit/}"
WP_VERSION="${WP_VERSION:-master}"

# Composer install
if [[ ! -e "$(pwd)/vendor/autoload.php" ]]
then
	which composer && composer install --optimize-autoloader --no-interaction --quiet
fi


# Set up database
mysql -e 'CREATE DATABASE IF NOT EXISTS wordpress_test;' -uroot

# Maybe install WP?
if [[ ! -d "${WP_CORE_DIR}" ]]
then
	# Grab specified version of WordPress
	git clone --quiet --depth=1 --branch="${WP_VERSION}" git://develop.git.wordpress.org/ ${WP_DIR}
fi

# Ensure action-scheduler is present in wp-content/plugins/ directory
PLUGIN_SLUG="$(basename $(pwd))"
PLUGIN_DIR="${WP_CORE_DIR}/wp-content/plugins/${PLUGIN_SLUG}"

# Put various components in proper folders
cp tests/travis/wp-tests-config.php ${WP_TESTS_DIR}/wp-tests-config.php

cd ..
cp -R ${PLUGIN_SLUG} ${PLUGIN_DIR}

cd ${PLUGIN_DIR}

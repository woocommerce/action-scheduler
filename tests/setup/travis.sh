#!/bin/sh

# WordPress test setup script for Travis CI
#
# Author: Benjamin J. Balter ( ben@balter.com | ben.balter.com ), Prospress, Jeremy Pry
# License: GPL3

set -ev

# Set necessary variables.
export WP_DIR=/tmp/wordpress
export WP_CORE_DIR="${WP_DIR}/src"
export WP_TESTS_DIR="${WP_DIR}/tests/phpunit"

# Ensure we have a WP_VERSION
WP_VERSION="${WP_VERSION:-master}"
PLUGIN_SLUG=$(basename $(pwd))
PLUGIN_DIR=${WP_CORE_DIR}/wp-content/plugins/${PLUGIN_SLUG}

# Install composer dependencies
composer install --ignore-platform-reqs --no-interaction

# Init database
mysql -e 'CREATE DATABASE IF NOT EXISTS wordpress_test;' -uroot

# Grab specified version of WordPress
git clone --quiet --depth=1 --branch="${WP_VERSION}" git://develop.git.wordpress.org/ "${WP_DIR}"

# Put various components in proper folders
cp tests/setup/travis-config.php ${WP_TESTS_DIR}/wp-tests-config.php

cd ..
mv ${PLUGIN_SLUG} ${PLUGIN_DIR}

cd ${PLUGIN_DIR}

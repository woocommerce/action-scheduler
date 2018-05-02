#!/bin/sh

# WordPress test setup script for Travis CI
#
# Author: Benjamin J. Balter ( ben@balter.com | ben.balter.com )
# License: GPL3

set -ev

export WP_DIR=/tmp/wordpress
export WP_CORE_DIR=${WP_DIR}/src
export WP_TESTS_DIR=${WP_DIR}/tests/phpunit

if [[ "$1" = "5.6" || "$1" > "5.6" ]]
then
  wget -c https://phar.phpunit.de/phpunit-5.7.phar
  chmod +x phpunit-5.7.phar
  mv phpunit-5.7.phar `which phpunit`
fi

plugin_slug=$(basename $(pwd))
plugin_dir=${WP_CORE_DIR}/wp-content/plugins/${plugin_slug}

# Init database
mysql -e 'CREATE DATABASE wordpress_test;' -uroot

# Grab specified version of WordPress
git clone --quiet --depth=1 --branch="${WP_VERSION}" git://develop.git.wordpress.org/ ${WP_DIR}

# Put various components in proper folders
cp tests/travis/wp-tests-config.php ${WP_TESTS_DIR}/wp-tests-config.php

cd ..
mv ${plugin_slug} ${plugin_dir}

cd ${plugin_dir}

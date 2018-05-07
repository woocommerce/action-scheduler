#!/usr/bin/env bash

if [[ "$TRAVIS_PHP_VERSION" == "7.1" ]] && [[ "$WP_VERSION" == "4.8" ]] && [[ "$WP_MULTISITE" == "0" ]] && [[ "$TRAVIS_BRANCH" == "master" ]]
then
	vendor/bin/phpunit --configuration tests/phpunit.xml.dist --coverage-clover clover.xml
else
	vendor/bin/phpunit --configuration tests/phpunit.xml.dist
fi

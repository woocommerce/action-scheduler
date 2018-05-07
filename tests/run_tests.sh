#!/usr/bin/env bash

set -ev

# Use composer phpunit for newer PHP versions, or if we're not in Travis.
if [[ "${TRAVIS_PHP_VERSION}" > "5.6" || "${TRAVIS_PHP_VERSION}" = "5.6" || -z "${TRAVIS_PHP_VERSION}" ]]
then
	PHPUNIT="vendor/bin/phpunit"
else
	PHPUNIT="phpunit"
fi

# Maybe run code coverage report.
if [[ "${TRAVIS_PHP_VERSION}" = "7.1" && "${WP_VERSION}" = "${WP_LATEST}" && "${WP_MULTISITE}" = "0" && "${TRAVIS_BRANCH}" = "master" ]]
then
	export AS_CODE_COVERAGE=1
	"${PHPUNIT}" --configuration tests/phpunit.xml.dist --coverage-clover clover.xml
else
	"${PHPUNIT}" --configuration tests/phpunit.xml.dist
fi

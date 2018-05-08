# Action Scheduler Unit Tests

The Action Scheduler codebase utilizes Unit Tests powered by [phpunit](https://phpunit.de).

## Requirements

Unit tests require the following:

* A **Development Version** of the [WordPress Codebase](https://make.wordpress.org/core/handbook/contribute/codebase/).
This means that you will see `src/` and `tests/` directories at the root of the WordPres directory.
* A MySQL or MariaDB database server
* A running database named `wordpress_test` that can be **entirely erased** at will
* [Composer](https://getcomposer.org)
* PHP 7.0+

## Running Unit Tests

Unit tests run automatically using [Travis CI](https://travis-ci.org/Prospress/action-scheduler).

To run unit test locally, you will need a local installation of WordPress, complete with a disposable database.
This can be configured easily using a tool such as Vagrant (and [VVV](https://varyingvagrantvagrants.org)), or using
another solution of your choice for locally running a WordPress site.

The following instructions will allow you to run tests locally using VVV.

### Local Setup for Tests

* Follow [VVV's instructions](https://varyingvagrantvagrants.org/docs/en-US/installation/software-requirements/) to
set up VVV. This includes:
    * Installing VirtualBox
    * Installing Vagrant
    * Installing Vagrant Plugins
* [Install and start](https://varyingvagrantvagrants.org/docs/en-US/installation/) VVV. Make sure to do the
post-installation step of creating a `vvv-custom.yml` file to manage your sites.
* Add a custom site to the `vvv-custom.yml` file, using the [VVV instruction](https://varyingvagrantvagrants.org/docs/en-US/adding-a-new-site/).
This is a recommended configuration to automatically install a new site:
```yaml
sites:

    # The wordpress-develop site is probably already in your file, but is included here for completeness. It is needed.
    wordpress-develop:
        repo: https://github.com/Varying-Vagrant-Vagrants/custom-site-template-develop.git
        hosts:
            - src.wordpress-develop.test
            - build.wordpress-develop.test
            
    action-scheduler:
        repo: https://github.com/JPry/vvv-base.git
        hosts:
            - action-scheduler.test
        custom:
            title: Action Scheduler Testing
```
* Run `vagrant provision --provision-with site-action-scheduler`. This will set up the site in
`<vvv-directory>/www/action-scheduler/`.
* Run the following commands from the VVV root directory to clone the Action Scheduler repository:
```bash
cd www/action-scheduler/htdocs/wp-content
git clone https://github.com/Prospress/action-scheduler.git
```
* Connect via SSH to the virtual machine: `vagrant ssh`
* Switch to the Action Scheduler directory: `cd /srv/www/action-scheduler/htdocs/wp-content/plugins/action-scheduler`
* Run the `tests/setup/local.sh` script, which is used to set up a local environment. By default, it will do 
the following:

1. Determine where the development WordPress directory lives. You can define the `WP_DIR` environment variable
to customize this location. If you don't customize the location, then WordPress will be installed in `/tmp/wordpress`
1. Run `composer install` in the `action-scheduler` directory if you have not done so already.
1. Download the WordPress files if the directory from step 1 doesn't exist.
1. Copy the `tests/setup/local-config.php` file to the WordPress `tests/phpunit/` directory as `wp-tests-config.php`.
1. Copy the `action-scheduler` directory into the WordPress `src/wp-content/plugins/` directory

Now that the setup process is completed, the best way to actually run the tests is to execute the `run_tests.sh` script:

```bash
./tests/run_tests.sh
```

## Known Issues

The Action Scheduler tests run multiple times using multiple timezones.
[#135](https://github.com/Prospress/action-scheduler/issues/135) Describes the fact that PHPUnit over-counts the 
number of tests that are run.

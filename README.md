# ![logo](/public/imgs/favicon-96.png) Phyxo

Simply share your images.

![alt tag](https://www.phyxo.net/demo-home.png 'Phyxo screenshot')

## Requirements

This project use severals librairies that need at least PHP 8.3.0

[![Phyxo](https://github.com/nikrou/phyxo/actions/workflows/phyxo.yml/badge.svg)](https://github.com/nikrou/phyxo/actions/workflows/phyxo.yml)
![PHPSTan level](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat)
![PHP 8](./tools/php-8-ready.svg)

This project uses a database and support all database engines managed by [DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/platforms.html#platforms) like Mysql, PostgreSQL and SQLite engines.

## Installation

### From web browser

[Download](https://download.phyxo.net/?C=M;O=A) the latest stable version and unarchive it.
Transfer the content to your web space with any FTP client.
Open your website (https://example.com/phyxo/public/index.php or your domain name) and install database and first user through web interface.

You can found more informations on [Installation from source page](../../wiki/Installation-from-source#finish-installation-in-your-favorite-browser)

### From source

Clone this repository:

```sh
git clone git@github.com:nikrou/phyxo.git
```

Download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.

```sh
curl -sS https://getcomposer.org/installer | php
mv composer.phar composer
```

Update dependencies via composer:

```sh
composer install
```

Install Phyxo through web interface.

For both methods you can make installation process from command line:

```sh
./bin/console phyxo:install
```

And create first user:

```sh
./bin/console phyxo:user:create
```

## Contributing

If you'd like to contribute, please read the [CONTRIBUTING.md file](CONTRIBUTING.md). You can submit
a pull request, or feel free to use any other way you'd prefer.

## Running tests

You must have install phyxo first, and update base_url in behat.yml.dist

phyxo is tested using a BDD framework - [Behat](http://www.behat.org).
To run test:

```
./bin/behat
```

But there's also unit tests in phyxo runned with [PHPUnit](https://phpunit.de/).
To run tests:

```sh
./bin/simple-phpunit --testdox
```

or

```sh
make unit-test
```

You can also get coverage report for tests by running:

```sh
./bin/simple-phpunit --testdox --coverage-html=coverage
```

or

```sh
make unit-test-coverage
```

## Static code analysis

Analysis is made using [PHPStan](https://github.com/phpstan/phpstan) :

```sh
composer phpstan
```

The analysis is made with level 6 but the idea is to increase that level and fix more and more possible issues.

## Demo

You can find, discover and play with a [demo](https://demo.phyxo.net/)

## Documentation

Documentation is available on the [wiki](../../wiki). Work in progress...

## Todo

- Add more tests
- See [TODO file](TODO.md).

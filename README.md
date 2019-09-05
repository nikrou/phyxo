# Phyxo

Simply share your images.

![alt tag](https://www.phyxo.net/demo-home.png "Phyxo screenshot")

## Requirements

This project use severals librairies that need at least PHP 7.1.0

[![Build Status](https://travis-ci.org/nikrou/phyxo.svg?branch=master)](https://travis-ci.org/nikrou/phyxo)

This project uses a database and support Mysql, PostgreSQL and SQLite engines.

## Installation

### Manual

[Download](https://download.phyxo.net/) the latest stable version and unarchive it.
Transfer the content to your web space with any FTP client.
Open your website and install database and first user through web interface.

### From source

Clone this repository :

```sh
$ git clone git@github.com:nikrou/phyxo.git
```

Download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.

```sh
$ curl -sS https://getcomposer.org/installer | php
```

Update dependencies via composer :
```
$ composer install
```

Install Phyxo through web interface.

For both methods you can make installation process from command line :
```sh
$ ./bin/console phyxo:install
```
And create first user :
```sh
$ ./bin/console phyxo:user:create
```

## Contributing

If you'd like to contribute, please read the [CONTRIBUTING.md file](CONTRIBUTING.md). You can submit
 a pull request, or feel free to use any other way you'd prefer.

## Running tests

You must have install phyxo first, and update base_url in behat.yml.dist

phyxo is tested using a BDD framework - [Behat](http://www.behat.org).
To run test :

```
$ ./bin/behat
```

But there's also unit tests in phyxo runned with [Atoum](http://atoum.org).
To run tests :
```
$ ./bin/atoum
```

Tests are automatically runned by travis (see badge above)

You can also get coverage report for tests by running :
```
$ ./bin/atoum -ebpc -c .atoum.coverage.php
```


And javascript is tested using [Jasmine](http://jasmine.github.io/).
To run tests, go to [local, Jasmine test page](http://localhost/phyxo/tests/functional/)

## Demo

You can find, discover and play with a [demo](https://demo.phyxo.net/)

## Documentation

Documentation is available on the [wiki](../../wiki). Work in progress...

## Todo

 * Add more tests
 * See [TODO file](TODO.md).

Phyxo
======

This project is based on Piwigo. It aims to provide support for PostgreSQL and SQLite engines.

Requirements
------------

This project use severals librairies that need at least PHP 5.3.0


Installation
------------

Clone this repository :
```
$ git clone git@github.com:nikrou/phyxo.git
```

Download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.

```
$ curl -sS https://getcomposer.org/installer | php
```

Update dependencies via composer :
```
$ composer.phar install --dev
```

Install Phyxo through web interface.

Contributing
------------

If you'd like to contribute, please read the [CONTRIBUTING.md file](CONTRIBUTING.md). You can submit
 a pull request, or feel free to use any other way you'd prefer.

Running tests
-------------

You must have install phyxo first, and update base_url in behat.yml.dist

phyxo is tested using a BDD framework - [Behat](http://www.behat.org).
To run test :

```
$ ./bin/behat
```

Todo
----

 * Add more tests
 * See [TODO file](TODO.md)

Phyxo
======

[![Join the chat at https://gitter.im/nikrou/phyxo](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/nikrou/phyxo?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

This project is based on Piwigo. It aims to provide support for PostgreSQL and SQLite engines.

Requirements
------------

This project use severals librairies that need at least PHP 7.0.0

[![PHP 7 ready](http://php7ready.timesplinter.ch/nikrou/phyxo/master/badge.svg)](https://travis-ci.org/nikrou/phyxo)
[![Dependency Status](https://www.versioneye.com/user/projects/5849ce6ca662a500413e7b6b/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/5849ce6ca662a500413e7b6b)
[![Build Status](https://travis-ci.org/nikrou/phyxo.svg?branch=master)](https://travis-ci.org/nikrou/phyxo)

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

But there's also unit tests in phyxo runned with [Atoum](http://atoum.org).
To run tests :
```
$ ./bin/atoum
```

Tests are automatically runned by travis (see badge above)

And javascript is tested using [Jasmine](http://jasmine.github.io/).
To run tests, go to [local, Jasmine test page](http://localhost/phyxo/tests/functional/)

Demo
----

You can find, discover and play with a [demo](https://demo.phyxo.net/)


Todo
----

 * Add more tests
 * See [TODO file](TODO.md)

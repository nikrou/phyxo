Phyxo 1.7.0 - 2018-01-20
========================
* Remove template extensions. Use inheritance instead.
* PHP minimum version is now 7.0.0. PHP 5 reaches end of life and is now only security fixes.
* Switch to Swiftmailer instead of phpmailer

Phyxo 1.6.5 - 2017-03-14
========================
* Fix warning in session start

Phyxo 1.6.4 - 2017-01-12
========================
* Fix security issue with phpmailer

Phyxo 1.6.3 - 2017-01-05
========================
* Fix issue #9. Problem to update.

Phyxo 1.6.2 - 2017-01-05
========================
* Fix issue #8. Update plupload
* Fix links to image in admin area
* Fix security issue with phpmailer

Phyxo 1.6.1 - 2016-12-07
========================
* Fix issue #6. Themes were incorrecly retrieved from database.
* Fix small issue in installation.

Phyxo 1.6.0 - 2016-12-06
========================
* Remove fetchRemote. Mark as deprecated.
* Refactoring of TabSheet class
* Refactoring of plugins, themes and languages classes
* move admin.php to admin/index.php
* Add sessions class.
* Remove session_regenerate_id.
* use non minified js files.

Phyxo 1.5.0 - 2016-02-09
========================
* Move webservice to Phyxo namespace
* Move Calendars to Phyxo namespace
* Fix issue with session_regenerate_id with php < 7.0.3
* Remove serialize for some configuration keys. Add migration script
* No more pngfix

Phyxo 1.4.1 - 2016-02-02
========================
* Fix issue with php 7 and old Smarty release.
  Thanks to Julien <lolop@openmailbox.org>

Phyxo 1.4.0 - 2015-05-10
========================
* Fix missing dependency
* Add repositories for comments and users
* Fix severals issues.
* DBLayer::in could accept commat separated value instead of array

Phyxo 1.3.0 - 2014-12-25
========================
* Fix issue in menu mangement
* Change copyright to map new domain name : phyxo.net (wip)
* Fix issue with Behat dependencies
* Add moderation for tags added/removed by visitors
* Several fixes

Phyxo 1.2.1 - 2014-12-14
========================
* Fix issue for missing files in archive
* Fix upgrade core.

Phyxo 1.2.0 - 2014-09-28
========================
* Add javascript tests using [Jasmine](http://jasmine.github.io/)
* Add php unit tests using [Atoum](http://atoum.org/)
* Add functional tests using [Behat](http://www.behat.org).
* Remove some @ for Smarty (@translate becomes translate)
* Make assets (js, css, images) for admin independant of public pages
* Replace count(*) by count(1)
* Replace array_from_query by query2array
* Move Themes, Plugins, Languages, Updates classes to Phyxo namespace
* Use anonymous function instead of create_function construction
* Use DBLayer instead of functions
* user_tags plugin will have its own repository
* Use sql-formatter to display queries
* Add jquery-migrate to show warning for old jquery syntax.
* Allow visitors to add/remove tags to photos

Phyxo 1.1.0 - 2014-07-11
========================
* Merge from upstream
* Now use multiple html form to upload media instead of flash plugin.

Phyxo 1.0.1 - 2014-04-18
========================
* Fix issue in session
* Update about page
* Fix issue when updating categories user cache
* Fix issue in SQLite (missing close function)
* Add user_tags plugin in core plugins

Phyxo 1.0.0 - 2014-04-17
========================
* first public release

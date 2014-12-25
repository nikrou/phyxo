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

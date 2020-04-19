# Phyxo 2.0.1 - 2020-04-19

- updates some translations
- fix various bugs (missing titles on albums pages). Thanks to @regexgit for report
- fix batch actions (missing localStorage keys and datepicker library). Thanks to @regexgit for report
- restore image ordre configuration in theme. Thanks to @regexgit for report

# Phyxo 2.0.0 - 2020-04-13

- move all administration URL under symfony routing. Fix #34.
- remove deprecated functions/methods : delete_elements, delete_element_files, safe_version_compare, get_icon, get_moment, create_navigation_bar
- use symfony translation system. Fix #37
- use voters for permissions. Fix #26
- use symfony event system. Fix #22
- remove Smarty and use Twig as template engine
- add favicon (and logo)

# Phyxo 1.10.3 - 2019-10-24

- Fix menubar incorrectly saved causing error 500
- Fix small issues in template: links to home, links to calendar

# Phyxo 1.10.2 - 2019-10-05

- Fix issue #36 installation process was broken. .env file was missing preventing installation process to start. Thanks to @regexgit

# Phyxo 1.10.1 - 2019-09-07

- Fix upgrade from branch 1.9. Upgrade script has not the correct name.

# Phyxo 1.10.0 - 2019-09-05

- Move webservice and media factory (i.php) under symfony router.
- Use symfony sessions.
- Default theme is now Treflez.
- Add symfony commands to install phyxo, create or list users.

# Phyxo 1.9.8 - 2019-09-02

- Fix issue with upgrade link on admin area index page.
- URL update was not correct
- Prepare upgrade process for next major release

# Phyxo 1.9.7 - 2019-01-12

- Fix issue preventing users using nginx as http server to connect.

# Phyxo 1.9.6 - 2018-12-31

- Fix random page when no photos.
- Fix retrieve of elegant config after upgrade
- Fix permission on image (space after comma in explode function)

# Phyxo 1.9.5 - 2018-12-24

- Small misspelling fix
- Fix empty collation for mysql.

# Phyxo 1.9.4 - 2018-12-23

- Fix more small issues using my new friend PHPStan

# Phyxo 1.9.3 - 2018-12-09

- Fix many small issues in various files: thanks to PHPStan
- Improve upgrade.
- Upgrade symfony to 4.2.1
- Fix assets path for Elegant theme.

# Phyxo 1.9.2 - 2018-12-02

- No question mark in url (default)
- Fix misspelling in Favorites repository
- Fix small regression in derivative params.

# Phyxo 1.9.1 - 2018-11-17

- Fix migration from 1.8.0. Forget to remove old php files.

# Phyxo 1.9.0 - 2018-11-16

- Improve installation process
- Admin responsive.
- Use Symfony in front of Phyxo. Use severals components
- Sanitize code. (Work in progress : lot of work to do)

# Phyxo 1.8.0 - 2018-07-03

- Make an admin theme responsive (more work to do)

# Phyxo 1.7.0 - 2018-01-20

- Remove template extensions. Use inheritance instead.
- PHP minimum version is now 7.0.0. PHP 5 reaches end of life and is now only security fixes.
- Switch to Swiftmailer instead of phpmailer

# Phyxo 1.6.5 - 2017-03-14

- Fix warning in session start

# Phyxo 1.6.4 - 2017-01-12

- Fix security issue with phpmailer

# Phyxo 1.6.3 - 2017-01-05

- Fix issue #9. Problem to update.

# Phyxo 1.6.2 - 2017-01-05

- Fix issue #8. Update plupload
- Fix links to image in admin area
- Fix security issue with phpmailer

# Phyxo 1.6.1 - 2016-12-07

- Fix issue #6. Themes were incorrecly retrieved from database.
- Fix small issue in installation.

# Phyxo 1.6.0 - 2016-12-06

- Remove fetchRemote. Mark as deprecated.
- Refactoring of TabSheet class
- Refactoring of plugins, themes and languages classes
- move admin.php to admin/index.php
- Add sessions class.
- Remove session_regenerate_id.
- use non minified js files.

# Phyxo 1.5.0 - 2016-02-09

- Move webservice to Phyxo namespace
- Move Calendars to Phyxo namespace
- Fix issue with session_regenerate_id with php < 7.0.3
- Remove serialize for some configuration keys. Add migration script
- No more pngfix

# Phyxo 1.4.1 - 2016-02-02

- Fix issue with php 7 and old Smarty release.
  Thanks to Julien <mailto:lolop@openmailbox.org>

# Phyxo 1.4.0 - 2015-05-10

- Fix missing dependency
- Add repositories for comments and users
- Fix severals issues.
- DBLayer::in could accept commat separated value instead of array

# Phyxo 1.3.0 - 2014-12-25

- Fix issue in menu mangement
- Change copyright to map new domain name : phyxo.net (wip)
- Fix issue with Behat dependencies
- Add moderation for tags added/removed by visitors
- Several fixes

# Phyxo 1.2.1 - 2014-12-14

- Fix issue for missing files in archive
- Fix upgrade core.

# Phyxo 1.2.0 - 2014-09-28

- Add javascript tests using [Jasmine](http://jasmine.github.io/)
- Add php unit tests using [Atoum](http://atoum.org/)
- Add functional tests using [Behat](http://www.behat.org).
- Remove some @ for Smarty (@translate becomes translate)
- Make assets (js, css, images) for admin independant of public pages
- Replace count(\*) by count(1)
- Replace array_from_query by query2array
- Move Themes, Plugins, Languages, Updates classes to Phyxo namespace
- Use anonymous function instead of create_function construction
- Use DBLayer instead of functions
- user_tags plugin will have its own repository
- Use sql-formatter to display queries
- Add jquery-migrate to show warning for old jquery syntax.
- Allow visitors to add/remove tags to photos

# Phyxo 1.1.0 - 2014-07-11

- Merge from upstream
- Now use multiple html form to upload media instead of flash plugin.

# Phyxo 1.0.1 - 2014-04-18

- Fix issue in session
- Update about page
- Fix issue when updating categories user cache
- Fix issue in SQLite (missing close function)
- Add user_tags plugin in core plugins

# Phyxo 1.0.0 - 2014-04-17

- first public release

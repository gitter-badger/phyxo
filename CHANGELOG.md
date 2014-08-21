Phyxo 1.2.0 - 2014-08-15
========================
* Add javascript tests using jasmine
* Add php unit tests using atoum
* Remove @ for Smarty (@translate becomes translate)
* Make assets (js, css, images) for admin independant of public pages
* Replace count(*) by count(1)
* Replace array_from_query by query2array
* Move Themes, Plugins, Languages, Updates classes to Phyxo namespace
* Use anonymous function instead of create_function construction (at some places)
* Use DBLayer instead of functions
* user_tags plugin will have its own repository
* Use sql-formatter to display queries
* Add jquery-migrate to show warning for old jquery syntax.

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
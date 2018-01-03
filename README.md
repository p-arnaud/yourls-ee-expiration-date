YOURLS EE Expiration Date
====================

Plugin for [YOURLS](http://yourls.org) `1.7.2`.

Description
-----------
This plugin enables the feature of expiration date for your short URLs.

API
---
This plugin can extends the API plugin: [yourls-api-edit-url](https://github.com/timcrockford/yourls-api-edit-url)
You can update expiration date adding *url-date-active* parameter (true/false) and *url-date* parameter (format (YYYY-MM-DD)).

Example:
 /yourls-api.php?username=username&password=password&format=json&action=update&url=ozh&url-date-active=true&url-date=2018-02-03&shorturl=ozh

Installation
------------
1. In `/user/plugins`, create a new folder named `yourls-ee-expiration-date`.
2. Drop these files in that directory.
3. Go to the Plugins administration page ( *eg* `http://sho.rt/admin/plugins.php` ) and activate the plugin.
4. Have fun!

License
-------
Licence MIT.

Repository
--------------
[Plugin's sources](https://github.com/p-arnaud/yourls-ee-expiration-date)

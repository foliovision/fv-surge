# FV Surge

We love the Surge cache plugin for WordPress, however it’s hard to setup for e-commerce or ad-driven websites. That’s why we created FV Surge.

Improvements over [original Surge plugin](https://github.com/kovshenin/surge):

* Default configuration file `surge-cache-config.php` gets created when installing plugin (check the surge_installed wp_option) with:
  * cache time set to 12 hours
  * all cookies except WordPress login cookie and Commenter cookie ignored
* New configuration variable: `ignore_all_cookies_except` – set to array with the WordPress login cookie and Commenter cookie names and all other cookies will be ignored for caching
* New configuration variable: `cache_cookies` - can be used alongside `ignore_all_cookies_except` to get array of cookie names that should vary the cache
* Added wp-admin bar button to purge the cache
* Added wp-admin Tools page that lists the cache content, letting you purge individual entries
* Added wp-admin Settings page (just lists the configuration variables for easier checking)
* Simplified cache invalidation - ignore nested loops, to make sure related articles do not purge cache of other pages
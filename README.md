# FV Surge

We love the Surge cache plugin for WordPress, however it’s hard to setup for e-commerce or ad-driven websites. That’s why we created FV Surge.

Improvements over [original Surge plugin](https://github.com/kovshenin/surge):

* New configuration variable: `ignore_all_cookies` – set to true, and all cookies will be ignored for caching
* New configuration variable: `exclude_cookies` – use to exclude logged in users and commenters from caching. Has to be used **together** with `ignore_all_cookies`.
* Added wp-admin bar button to purge the cache
* Added wp-admin Tools page that lists the cache content, letting you purge individual entries
* Added wp-admin Settings page (just lists the configuration variables for easier checking)
* Simplified cache invalidation - ignore nested loops, to make sure related articles do not purge cache of other pages
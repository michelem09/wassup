# WassUp Real Time Analytics
Contributors: michelem, helened  
Donate link:  [donate](https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=michele%40befree%2eit&item_name=WassUp&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=IT&bn=PP%2dDonationsBF&charset=UTF%2d8)   
Tags: analytics, counter, hit, online, statistics, stats, tracker, traffic, trends, user, visitor, web     
Requires at least: WordPress 2.2  
Tested up to: 4.6.1  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

#### Analyze your website traffic with accurate, real-time stats, live views, visitor counts, top stats, IP geolocation, customizable tracking, and more.
-----
## Description

WassUp is a Wordpress plugin to analyze your visitors traffic with accurate, real-time stats, lots of detailed chronological information, customizable tracking, live views, pageview counts, visitor counts, top stats, charts, IP geolocation, map, two aside widgets, and a dashboard widget.

Wassup does in-depth visitor tracking and shows you incredible details about your site's latest hits...more than you can get from almost any other single plugin. It is very useful for SEO and statistics maniacs who want to see accurate, up-to-date stats displayed in a straightforward, easy to understand manner.

The aim of WassUp is the timely knowledge of what your visitors do when they surf your site. It is not intended to show grouped statistics over preset, long-term time periods like visitors per month, pageviews per quarter, and so on (there are many others tools to better gain that, like Google Analytics). WassUp's flexible, easy-to-read views are the best for learning the details about your visitors' latest activities. With it's customizable filters and search capability, you can drill deeply into the data to learn even more about specific visitors, visitor types, etc.

### Detailed Specs:
#### WassUp comes with 4 admin screen panels for viewing your visitors' activities and for customizing those views
* There is a fancy "Visitors Details" screen that lets you to see almost everything about your visitors and what they do on your site and that includes search capability, view filters, plus a chart and top stats summary.
* There is an ajax "Spy View" screen (like Digg Spy) that lets you monitor your visitors live, with optional geolocation on a Google!maps world map. 
* There is a "Current Visitors Online" screen that shows a summary of your online visitors in real-time.
* There is an "Options" panel with lots of customizable settings for WassUp. 

There is a nice Dashboard widget that shows a line chart of hits over time (24 hours default) and a count of current visitors online and their latest activities.

#### WassUp comes with two useful sidebar Widgets that lets you display your site's latest data to your visitors
* The "Online" widget shows counts of current visitors online and includes options to display logged-in usernames and country flags.
* The "Top Stats" widgets lets you display trending or timed top items about your site based on the latest stats. You can list top search engine keywords, top external referrers, top url requests, top articles, top browsers, top OSes, and more.
* The widgets are fully customizable.

#### WassUp's advanced tracking features can: 
* Distinguish registered users from anonymous visitors, and administrators from regular users.
* Identify and label new browsers, robots, and feed readers, heuristically.
* Track page requests that generate 404 (not found) redirects.
* Detect some spiders that pretend to be regular visitors/browsers.
* Expose malware activity - including spam, hack attempts, script injection, and other exploit attempts.

WassUp works with two anti-spam functions to detect and omit (if you want) referrers spammers and comment spammers. It also detects and records unauthorized users' login attempts, script injection, and other exploit attempts.  Please note that WassUp only identifies exploit attempts. It does not block them or otherwise protect your site. You need a separate security plugin for that.

For people with database size limitations, WassUp has a few options to manage the database table growth: you can empty it; you can delete old records automatically; and you can set a warning notice for when it exceeds a preset size limit.

#### WassUp gives a detailed chronology of your hits with a lot of information for each single user session:
* ip / hostname
* referrer
* spider
* search engines used
* keywords
* SERP (search engine result page)
* operating system / language / browser
* pages viewed (chronologically and per user session)
* complete user agent
* name of user logged in
* name of comment's author
* spam and hack attempts

#### Wassup admin console has flexible view filters that show:
* records by time period
* record count per page
* records by entry type (spider, users logged in, comment authors, search engine, referrer)
* search by keyword
* expand/collapse informations (with ajax support)
* usage chart (Google!chart)
* top stats lists with aggregate data (top queries, requests, os, browsers)

#### There are many options to customize how WassUp tracks and displays data:
* Enable/Disable recording (tracking)
* Screen refresh frequency (minutes)
* Screen resolution (browser width)
* User permission levels 
* Top stats selections
* Record or not logged-in users
* Record or not spiders and bots
* Record or not exploit attempts
* Record or not comment spammers
* Record or not referrer spammers
* registered users to exclude from recording
* IP or hostname to exclude from recording
* Email alert for table growth
* Auto delete of old records  

### IMPORTANT NOTICES
* WassUp is incompatible with static page caching plugins such as "WP Super-Cache". 
* WassUp is NOT a security plugin. It does not block unwanted visitors nor stop malware attempts on your site.

## Screenshots
1. Wassup - Visitor Details view.
2. Wassup - SPY Visitors view.

You can find more screenshots at [http://www.wpwp.org](http://www.wpwp.org)  

-----
## Installation

### Installation

You can install this plugin automatically from Wordpress admin panel. Navigate to Plugins >>Add New and type "WassUp" plugin name. Activate it and you are done!

OR, if you prefer to install the plugin manually, follow these instructions:

1. Download the plugin, WassUp (Real-Time Visitor Tracking), to your local computer
1. Unpack this plugin's zip or gz file with your preferred unzip/untar program or use the command line: `tar xzvf wassup.tar.gz` (linux)
1. Upload the entire "wassup" folder to your `wp-content/plugins` directory on your host server using an ftp client
1. Login to Wordpress admin panel and navigate to Plugins page
1. Activate WassUp plugin and you are done!


### Upgrading** 

Check your current visitors count under WassUp >>Current Visitors Online panel. If your site is busy, STOP! Don't upgrade. Wait until there are no visitors or follow the "Safe Upgrade Instructions" in the next section.

You can upgrade this plugin automatically from Wordpress admin panel: navigate to "Plugins" page, and under WassUp plugin name, click the "Update Now" link.

OR, if you prefer to manually upgrade, follow these instructions:

1. Deactivate WassUp plugin under Wordpress admin panel >>Plugins page
1. Delete the "wassup" folder from `wp-content/plugins/` on your host server
1. Download and unzip the new "WassUp" file to your local computer
1. Upload the entire "wassup" folder to your `wp-content/plugins` directory on your host server
1. Activate WassUp plugin under Wordpress admin panel >>Plugins page

### **IMPORTANT Safe Upgrade Instructions

To safely upgrade WassUp when your site is busy, you must manually stop visitor recording beforehand, do the upgrade, then manually resume recording afterwards:
 
1. In WordPress admin panel, navigate to WassUp >>Options >>[Genernal Setup] tab. Uncheck the box for "Enable statistics recording" and save.
1. Navigate to Plugins page and click the "Update Now" link under "WassUp" plugin name or follow the manual upgrade instructions above
1. After the upgrade is done, go back to WassUp >>Options >>[General Setup] tab, and check the box for "Enable statistics recording" and save.

### Usage
When you activate this plugin (as described in "Installation"), it works "as is". You don't have anything to do. Wait for visitors to hit your site and start seeing details (click the dashboard and go to WassUp page)

### Compatibility Notice 
* WassUp is incompatible with the following static page caching plugins: [WP Super Cache], [WP Cache] and [WP Hyper Cache]. 

-----

## Changelog
### v1.9.1:
* patched a security loophole in 'wassupURI::add_siteurl' method.
* revised plugin code to comply with the latest requirements for inclusion in Wordpress.org plugin repository.
* new compatibility script `/lib/compatibility.php` to check for Wordpress and PHP compatibility issues
* new compatibility subfolder `/lib/compat-lib/` with additional modules and javascripts required to run Wassup plugin in older Wordpress setups added. This subfolder can be deleted from Wassup package when unneeded.
* updated `jquery.js` version to 1.12.4 and `jquery-migrate.js` to 1.4.1 in compatibility subfolder, `/lib/compat-lib/js/`.
* updated Google!Maps API link to use a common API key for Wassup-Spy (required by Google since 2016-06-22).
* updated WassUp `wp-cron` scheduled tasks to terminate (and restart) at "reset-to-default", recording stop/start, and at plugin deactivate/reactivate events.
* updated Wassup table export function to omit all known spam/malware records from export by default...to avoid propagation of malware code when exported records are imported into other applications.
* updated `wassup_Akismet` class to abort remote requests with timeout error after 5 seconds to avoid plugin slowdown due to slow server response.
* updated `UADetector` and `wDetector` classes to improve browser and os detection (Microsoft Edge, Win10).
* updated translation template, "wassup.pot".
* updated "readme.txt" to comply with Wordpress.org plugin repository guidelines.
* fixed errors caused by disabled `set_time_limit` function in some configurations.
* fixed a `preg_match` error that affected 404 and spam detection.
* fixed a "script timeout" calculation/test error in Visitor-details.
* fixed incorrect Wassup menu "href" values in network admin area.
* fixed a MySQL timezone/offset calculation error in some queries.
* fixed a scheduled task validation error that caused some wp-cron tasks to fail.
* miscellaneous minor bugfixes.
* miscellaneous minor text changes
* minor css changes for small screen devices.

### v1.9: 
* improved Wordpress 4.x and PHP 5.6-mysqli compatibility.
* improved namespace compatibility with other plugins (Akismet).
* improved aside widgets with multi-widget capability and own options.
* improved plugin performance in MySQL by caching expensive queries.
* improved table management with new save and delete options (delayed insert, delete by record id)
* improved security with more input validation, deprecated functions removal, and escaped output.
* improved browser and os detection (Win10)
* improved tracker to omit some pre-fetch requests and the utility hits, `/wp-cron.php?doing_wp_cron` and `/admin-ajax.php`, from recording.
* improved tracker filters to allow some wildcards and hostname in exclusion filters.
* improved css styles for Wassup admin and dashboard widget and validated stylesheets as 100% W3C CSS3 compliant. 
* new features for multisite network compatibility added.
* new "FAQ" and "Donate" panels added in Wassup-Options submenu.
* new "top stats" popup-window added in Visitor Details panel.
* new javascripts, `jquery-migrate.js` and `wassup.js`, added to `/js` folder.
* updated `jquery.js` and `jquery-ui.js` versions.
* updated translation template (wassup.pot).
* updated "readme.txt".
* fixed search engine referrer data to substitute "not provided" for missing keywords from secure searches (https-to-http omission).
* fixed a fatal error in Wassup-options caused by disabled PHP functions in some host configurations.
* fixed a bug in Wassup-options that caused table export to fail.
* fixed an activation failure problem in `upgrade.php` that occurred in some host configurations.
* 3 new classes added to code: `wassupDb` for MySQL table operations and caching, `wassupURI` to format and clean urls/links for safe output, and `Wassup_Widget` a base widget for building Wassup widgets.
* miscellaneous minor text changes.
* miscellaneous minor bug fixes.

### v1.8.6
* Removed deprecated Wordpress methods
* Small text changes

### v1.8.5: Important fix for SPY visitors view 
* Changed main API tool to get GEOIP data
* Small CSS changes

### v1.8.4
* Migrated Google Maps API code to support v3
* Removed Google Maps API key
* Added some new locales: English (United Kingdom) [complete], Persian [partial], Sinhalese [partial], Vietnamese [partial]
* Added dashicon to admin menu (dashicons-chart-area)
* Added donate button to WassUp menu
* fixed CSS for WassUp menu

### v1.8.3.1 
* fixed security issue: Change the UserAgent of the browser to include html tags, and by accessing a WordPress blog with WassUp installed, the tag is executed when going to "View Details" from the administrative page and viewing the access logs.

### v1.8.3 
* fixed typo that caused a php "foreach" error.
* fixed errors in upgrade function.
* 'Top Articles' added to "Top Stats" options
* improved tracking of logged-in users.
* improved referrer, search engine, and spam detection
* improved namespace compatibility with other Wordpress plugins.
* updated jQuery to v1.6.4 and jqueryUI to v1.8.16
* miscellaneous minor code and style changes.

### v1.8.2 
* fixed a regex bug that caused a `preg.match` compilation warning in some configurations.
* fixed a typo in `wassup_install` function that caused plugin activation to fail in some configurations.
* updated refresh timer to have a range limit (0-180 min.) with a value of 0 disabling the timer.
* improved spider, spam and screen resolution detection.
* miscellaneous minor code and style changes.

### v1.8.1 
* fixed a bug that caused `set_time_limit` warnings to display to visitors. 
* new upgrade instructions in `readme.txt`.
* miscellaneous minor code changes.
 
### v1.8 
* new table, "wassup_meta", for data caching and extended tracking.
* new web service, [freegeoip.net](http://freegeoip.net), for IP Geolocation. Thanks to [@AlexandreFiori](http://twitter.com/alexandrefiori) for giving us access to his API.
* new admin interface style.
* improved browser, OS, and search engine detection.
* improved security and performance.
* improved compatibility with Wordpress 3.0-3.0.1 and security plugins.
* miscellaneous code improvements and bug fixes.

### v1.7.2.1 
* disabled page reload triggered by WassUp screen resolution tracking.
* fixed a security loophole found in main.php module.

### v1.7.2 
* new clickable refresh timer in "Visitor Details" submenu.
* initial sample record added to WassUp table for new installs.
* improved browser, OS, and search engine detection.
* code changes for better Wordpress integration.
* WassUp Widget localized for language translation.
* more language translations added.

## Upgrade Notice 

### v1.9.1 
* Critical security, compatibility and bugfix upgrade. 64MB memory is now required for Wassup! See [codex document "Editing wp-config.php"](https://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP) to increase memory allocated to Wordpress before upgrading. DO NOT UPGRADE when your site busy! Read [installation instructions](http://wordpress.org/plugins/wassup/installation/) for safe upgrade instructions.

-----

## Infos
### Plugin Home
* [http://www.wpwp.org](http://www.wpwp.org "http://www.wpwp.org")

### Plugin Development
* For pre-release bugfixes and other changes to WassUp, you can download the development version of Wassup from GitHub:
[https://github.com/michelem09/wassup](https://github.com/michelem09/wassup "https://github.com/michelem09/wassup")
* For the latest browsers, os, and spider detection updates, you can download the `uadetector.class.php` module separately on GitHub:
[https://github.com/hdunk/uadetector.class.php](https://github.com/hdunk/uadetector.class.php "https://github.com/hdunk/uadetector.class.php")

### Developer Home
* Michele M: [http://www.michelem.org](http://www.michelem.org "http://www.michelem.org")
* Helene D: [http://helenesit.com](http://helenesit.com "http://helenesit.com")

### Credits
* [Jquery](http://www.jquery.com) for the amazing Ajax framework
* [FAMFAMFAM](http://www.famfamfam.com/) for the flags icons
* A big thanks to [Helene D.](http://helenesit.com/) for her help to improve WassUp!

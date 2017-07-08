<?php
/**
 * Functions to create and update WassUp tables.
 *
 * @package WassUp Real-time Analytics
 * @subpackage upgrade.php module
 * @since version 1.8
 * @author Helene D. <http://helenesit.com>
 *
 * This module is loaded once by the 'wassup_install' hook function when plugin is installed/upgraded.
 */
//abort if this is direct uri request for file
if(!empty($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME'])===realpath(preg_replace('/\\\\/','/',__FILE__))){
	//try track this uri request
	if(!headers_sent()){
		//triggers redirect to 404 error page so Wassup can track this attempt to access itself (original request_uri is lost)
		header('Location: /?p=404page&werr=wassup403'.'&wf='.basename(__FILE__));
		exit;
	}else{
		//'wp_die' may be undefined here
		die('<strong>Sorry. Unable to display requested page.</strong>');
	}
//abort if no WordPress
}elseif(!defined('ABSPATH') || empty($GLOBALS['wp_version'])){
	//show escaped bad request on exit
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
//-------------------------------------------------
function log_me($message) {
    if ( WP_DEBUG === true ) {
        if ( is_array($message) || is_object($message) ) {
            error_log( print_r($message, true) );
        } else {
            error_log( $message );
        }
    }
}

/**
 * Initialize and return Wassup multisite network settings
 * @since v1.9
 * @param boolean (for network-wide activation)
 * @return array
 */
function wassup_network_install($networkwide=false){
	global $wpdb;
	$network_settings=get_site_option('wassup_network_settings');
	$network_defaults=array('wassup_active'=>1);
	if($networkwide){
		$network_defaults=array(
			'wassup_active'=>1,
			'wassup_table'=>$wpdb->base_prefix."wassup",
			'wassup_menu'=>1,
		);
		//no network table for subdomain networks
		if(is_subdomain_install()) $network_defaults['wassup_table']="";
		if(!empty($network_settings)) {
			//check that previous network table exists
			if(!empty($network_settings['wassup_table']) && !wassupDb::table_exists($network_settings['wassup_table'])) $network_settings['wassup_table']=$network_defaults['wassup_table'];
			$network_settings=wp_parse_args($network_settings,$network_defaults);
		}else{
			$network_settings=$network_defaults;
		}
	}else{
		$network_settings=$network_defaults;
	}
	return $network_settings;
} //end wassup_network_install

/**
 * Initialize and return wassup_settings for a subsite
 * @since v1.9
 * @param array
 * @return array
 */
function wassup_subsite_install($network_settings=array()){
	global $wassup_options;
	$subsite_settings=array();
	if(is_multisite() && !is_subdomain_install()){
		$subsite_settings=get_blog_option($GLOBALS['current_blog']->blog_id,'wassup_settings');
		//duplicate the main site setting for all subsites
		if(empty($subsite_settings)){
			$subsite_settings = get_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings');
			if(empty($subsite_settings)) $subsite_settings=$wassup_options->defaultSettings();
			//replace some subsite defaults with network settings
			if(empty($network_settings)) $network_settings=get_site_option('wassup_network_settings');
			if(!empty($network_settings['wassup_table'])){
				$subsite_settings['wassup_table']=$network_settings['wassup_table'];
			}
		}
	}
	return $subsite_settings;
} //end wassup_subsite_install

/**
 *  Updates wassup settings after table install/upgrade
 * - reset some wassup settings
 * - check for compatibility issues and add to alert_messages
 * - called by wassup_install and wassup_updateTable functions (if browser times out).
 * @since v1.9
 * @param string
 * @return void
 */
function wassup_settings_install($wassup_table=""){
	global $wp_version,$wassup_options;

	if(empty($wassup_table) || !wassupDb::table_exists($wassup_table)) $wassup_table=$wassup_options->wassup_table;
	if(empty($wassup_table)){
		if(is_network_admin()) $wassup_table=$wpdb->base_prefix."wassup";
		else $wassup_table=$wpdb->prefix."wassup";
	}
	//Reset some wassup settings
	//reset 'dbengine' MySQL setting with each upgrade...because host server settings can change
	$wassup_options->wassup_dbengine = $wassup_options->defaultSettings('wassup_dbengine');
	//reschedule optimization after table upgrade
	$wassup_options->wassup_optimize=$wassup_options->defaultSettings('wassup_optimize');
	//update settings for 'spamcheck'
	if (empty($wassup_options->wassup_spamcheck)) {
		$wassup_options->wassup_spamcheck = "0";
		//#set wassup_spamcheck=1 if either wassup_refspam=1 or wassup_spam=1
		if($wassup_options->wassup_spam == "1" || $wassup_options->wassup_refspam =="1") $wassup_options->wassup_spamcheck="1";
	}
	$wassup_options->whash =$wassup_options->get_wp_hash();

	//do compatibility checks after upgrade/activation
	$compat_notice="";
	if (empty($wassup_options->wassup_alert_message) || stristr($wassup_options->wassup_alert_message,'error')===false){
		if(!is_multisite() || is_network_admin() || is_main_site()){
			//compatibility test for mysql @since v1.9
			if(wassup_compatCheck('mysqldb')==false){
				$compat_notice=__("COMPATIBILITY WARNING: non-MySQL database type detected!","wassup")." ".__("WassUp uses complex MySQL queries that may not run on a different database type.","wassup");
			}elseif(wassup_compatCheck("WP_CACHE")==true){
				$compat_notice = __("WassUp cannot generate accurate statistics with page caching enabled.","wassup")." ".__("If your cache plugin stores whole Wordpress pages/posts as static HTML, then WassUp won't run properly. Please deactivate your cache plugin and remove \"WP_CACHE\" from \"wp_config.php\" or switch to a different statistics plugin.","wassup");
			}else{
				//show warning when 'WP_MEMORY_LIMIT' < 64MB @since v1.9
				$mem=wassup_compatCheck("WP_MEMORY_LIMIT");
				if($mem !==true){
					if(version_compare($wp_version,'3.8','>')) $recmem="64M";
					else $recmem="40M";
					$compat_notice=sprintf(__("WARNING: Insufficient memory: %s found! A minimum allocation of %s is recommended for WassUp and Wordpress.","wassup"),$mem."M",$recmem);
					if(version_compare($wp_version,'4.0','<')) $compat_link='[https://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP]';
					else $compat_link='[codex document "Editing wp-config.php"](https://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP)';
					$compat_notice .="  ".sprintf(__("See %s for information about increasing Wordpress memory.","wassup"),$compat_link);
				}
			}
			if(!empty($compat_notice)) $wassup_options->wassup_alert_message=$compat_notice;
			//for upgrade to v1.9+: flag for old wassup widget 
			if(empty($compat_notice) && !empty($wassup_options->wassup_version) && version_compare($wassup_options->wassup_version,'1.9','<')){
				$old_widget="wassup_widget";
				$admin_message=" ".__("IMPORTANT: Wassup Widget has changed and must be re-installed.","wassup");
				if(version_compare($wp_version,'2.8','<')){
					if(is_active_widget($old_widget)) $wassup_options->wassup_alert_message .=$admin_message;
				}elseif(is_active_widget(false,false,$old_widget)){
					$wassup_options->wassup_alert_message .=$admin_message;
				}
			}
		} //!is_multisite
	} //end if wassup_alert_message
	$wassup_options->wassup_table= $wassup_table;
	$wassup_options->wassup_upgraded= time();
} //end wassup_settings_install

/**
 * Table install manager function that calls either 'wassup_createTable' or 'wassup_updateTable' depending on whether this is a new install or an upgrade.
 * @param none
 * @return boolean
 */ 
function wassup_tableInstaller($wassup_table=""){
	global $wpdb, $wassup_options;

	//set wassup table names
	if(empty($wassup_table))$wassup_table=$wassup_options->wassup_table;
	if(empty($wassup_table)){
		if(is_network_admin()) $wassup_table=$wpdb->base_prefix."wassup";
		else $wassup_table=$wpdb->prefix."wassup";
		$wassup_options->wassup_table=$wassup_table;
	}
	$wassup_tmp_table = $wassup_table."_tmp";
	$wassup_meta_table = $wassup_table."_meta";
	$wcharset=true;
	$wsuccess=false;
	//CREATE/UPGRADE table
	if(wassupDb::table_exists($wassup_table)){
		//extend php script execution time to 10.1 minutes to prevent the 'script timeout' error that can cause activation failure when wp_wassup table is very large. @since v1.9
		//Note: browser timeout (blank screen, no error) can still occur when table upgrade takes a long time.
		$stimeout=ini_get("max_execution_time");
		if(is_numeric($stimeout) && $stimeout>0 && (int)$stimeout < 610){
			$disabled_funcs=ini_get('disable_functions');
			if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
				@set_time_limit(610);
			}
		}
		$wcharset=false;
		$wsuccess=wassup_updateTable($wassup_table);
	}else{
		$wassup_options->wassup_table=$wassup_table;
		if(wassup_createTable()){	//1st attempt
			$wcharset=true;
		}
		//2nd attempt: no character set in table
		if(!wassupDb::table_exists($wassup_table)){ 
			$wcharset=false;
			wassup_createTable($wassup_table,$wcharset);
		}
	}
	//check that install was successful, issue warnings
	if($wsuccess || wassupDb::table_exists($wassup_table)){
		$wsuccess=true;
		$wcharset=true;
		//double-check that temp and meta were created
		if(!wassupDb::table_exists($wassup_tmp_table)){
			wassup_createTable($wassup_tmp_table,$wcharset);
		}
		if(!wassupDb::table_exists($wassup_meta_table)){
			wassup_createTable($wassup_meta_table,$wcharset);
			if(!wassupDb::table_exists($wassup_meta_table) && $wcharset){
				$wcharset=false;
				wassup_createTable($wassup_meta_table,$wcharset);
			}
		}
		//store wassup main table name in Wassup settings
		$wassup_options->wassup_table=$wassup_table;
	}
	return($wsuccess);
} //#end function wassup_tableInstaller

/**
 * Create or upgrade wassup tables:
 * - build new 'wp_wassup' table using 'dbDelta'
 * - add a first record to new 'wp_wassup' table.
 * - build/upgrade 'wp_wassup_meta' table structure using 'dbDelta'
 * @param (2) string,boolean
 * @return boolean
 */
function wassup_createTable($wtable="",$withcharset=true) {
	global $wpdb, $current_user, $wassup_options, $wdebug_mode;

	if (empty($wassup_options->wassup_table)) {
		if(is_network_admin()) $wassup_table=$wpdb->base_prefix . "wassup";
		else $wassup_table = $wpdb->prefix . "wassup";
		$wassup_options->wassup_table=$wassup_table;
	}else{
		$wassup_table =$wassup_options->wassup_table;
	}
	$wassup_tmp_table = $wassup_table."_tmp";
	$wassup_meta_table = $wassup_table."_meta";
	if(empty($wtable)) $wtable_name = $wassup_table;
	else $wtable_name = $wtable;
	$is_new_table = false;

	//use Wordpress' "dbDelta" to create table structure
	//Note that since v1.8.3: Wordpress' "dbdelta" function is no longer used to upgrade pre-existing tables in Wassup because it fails on wassup's table structure in Wordpress 3.1+ (throws MySQL ALTER TABLE error).
	if(!function_exists('dbDelta')){
		if(file_exists(ABSPATH.'wp-admin/includes/upgrade.php')) require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		elseif(file_exists(ABSPATH.'wp-admin/upgrade-functions.php')) require_once(ABSPATH.'wp-admin/upgrade-functions.php');
		else exit(1);
	}
	//...Set default character set and collation (on new table)
	$charset_collate = '';
   	//Add charset on new table only
	if (wassupDb::table_exists($wtable_name)) {
		$is_new_table = false;
		$withcharset = false;
	} else {
		$is_new_table = true;
	}
	//#don't do charset/collation when < MySQL 4.1 or when DB_CHARSET is undefined
	//Note: it is possible that table default charset !== WP database charset on preexisting MySQL database and tables (from WP2.3 or less) because old charsets persist after upgrades
	$mysqlversion=$wpdb->get_var("SELECT version() as version");
	if ($withcharset && version_compare($mysqlversion,'4.1.0','>') && defined('DB_CHARSET') && !empty($wpdb->charset)) {
		$charset_collate = 'DEFAULT CHARACTER SET '.$wpdb->charset;
		//add collate only when charset is specified
		if (!empty($wpdb->collate)) $charset_collate .= ' COLLATE '.$wpdb->collate;
	}
	//table builds should not be interrupted, so run in background in case of browser timeout
	ignore_user_abort(1);

	//wassup table structure
	if ($wtable_name == $wassup_table || $wtable_name == $wassup_tmp_table) {
		$sql_createtable=sprintf("CREATE TABLE `%s` (
  `id` mediumint(9) unsigned NOT NULL auto_increment,
  `wassup_id` varchar(60) NOT NULL,
  `timestamp` varchar(20) NOT NULL,
  `ip` varchar(50) default NULL,
  `hostname` varchar(150) default NULL,
  `urlrequested` text,
  `agent` varchar(255) default NULL,
  `referrer` text,
  `search` varchar(255) default NULL,
  `searchpage` int(11) unsigned default '0',
  `os` varchar(15) default NULL,
  `browser` varchar(50) default NULL,
  `language` varchar(5) default NULL,
  `screen_res` varchar(15) default NULL,
  `searchengine` varchar(25) default NULL,
  `spider` varchar(50) default NULL,
  `feed` varchar(50) default NULL,
  `username` varchar(50) default NULL,
  `comment_author` varchar(50) default NULL,
  `spam` varchar(5) default '0',
  `url_wpid` varchar(50) default '0',
  `subsite_id` mediumint(9) unsigned default 0,
  UNIQUE KEY `id` (`id`),
  KEY `idx_wassup` (`wassup_id`(32)),
  KEY `ip` (`ip`),
  KEY `timestamp` (`timestamp`)) %s;",$wtable_name,$charset_collate);
	//Note: index (username,ip) was removed because of problems with non-romanic language display
	//since v1.8: Increased 'ip' col width to 50 for ipv6 support
	//since v1.9: New index on 'ip' col
	//since v1.9: New col 'subsite_id' added for multisite support
	//since v1.9: Dropped 'os' and 'browser' indices.
	//since v1.9: Dropped combined index '(wassup_id, timestamp)' and replaced with single index on 'wassup_id' to reduce overall table size

	//...Include a first record if new table (not temp table)
	$sql_firstrecord = '';
	if ($wtable_name == $wassup_table && $is_new_table) {
		if (!class_exists('UADetector'))
			include_once (WASSUPDIR.'/lib/uadetector.class.php');
		$ua = new UADetector;
		if (empty($current_user->user_login)) get_currentuserinfo();
		$logged_user = (!empty($current_user->user_login)? $current_user->user_login: "");
		$screen_res="";
		$sessionhash=$wassup_options->whash;
		if(isset($_COOKIE['wassup_screen_res'.$sessionhash])){
			$screen_res=esc_attr(trim($_COOKIE['wassup_screen_res'.$sessionhash]));
			if($screen_res == "x") $screen_res="";
		}
		$currentLocale = get_locale();
		$locale = preg_replace('/^[a-z]{2}_/','',strtolower($currentLocale));
		$subsite_id=0;
		if(!empty($GLOBALS['current_blog']->blog_id)) $subsite_id=$GLOBALS['current_blog']->blog_id;
		$sql_firstrecord = sprintf("INSERT INTO `$wassup_table` (`wassup_id`, `timestamp`, `ip`, `hostname`, `urlrequested`, `agent`, `referrer`, `search`, `searchpage`, `os`, `browser`, `language`, `screen_res`, `searchengine`, `spider`, `feed`, `username`, `comment_author`, `spam`,`url_wpid`, `subsite_id`) VALUES ('%032s','%s','%s','%s','%s','%s','%s','','','%s','%s','%s','%s','','','','%s','','0','0','%s')",
			1, current_time('timestamp'),
			'127.0.0.1', 'localhost', 
			'[404] '.__('Welcome to WassUP','wassup'), 
			$ua->agent . ' WassUp/'.WASSUPVERSION.' (http://www.wpwp.org)', 
			'http://www.wpwp.org', $ua->os, 
			trim($ua->name.' '.$ua->majorVersion($ua->version)),
			$locale,$screen_res,$logged_user,$subsite_id);
	} // end if wassup && is_new_table

	//...create/upgrade wassup table

	//Don't use Wordpress' "dbdelta" function on pre-existing "wp_wassup" table because "dbDelta" fails to upgrade wp_wassup's large table structure in Wordpress 3.1+ (throws MySQL ALTER TABLE error). @since v1.8.3
	$result=false;
	if ($wtable_name != $wassup_table) {
		$result = dbDelta($sql_createtable);
	} elseif (!empty($sql_firstrecord)) {
		$result = dbDelta(array($sql_createtable,$sql_firstrecord));
	} 
	//try create table with wpdb::query if dbDelta failed. @since v1.9
	if(!wassupDb::table_exists($wtable_name)){
		$result=$wpdb->query("$sql_createtable");
		if(!wassupDb::table_exists($wtable_name)){
			$error_msg="\n<br/>".sprintf(__("An error occurred during the install of table %s.","wassup"),$wtable_name)."\n<br/>";
			if(!empty($result) && is_wp_error($result)){
				$errno=$result->get_error_code();
				if((int)$errno > 0){
					$error_msg.=" Error# $errno: ".$result->get_error_message()."\n";
				}
			}
			echo $error_msg;
			exit(1);
		}elseif(!empty($sql_firstrecord)){
			$result=$wpdb->query("$sql_firstrecord");
		}
	}else{
		if ($wtable == "" && version_compare($mysqlversion,'4.1.0','>')) {
			//'CREATE TABLE LIKE' syntax not supported in MySQL 4.1 or less
			$result = dbDelta("CREATE TABLE $wassup_tmp_table LIKE {$wassup_table}");
		}
	}
	} //end if wassup_table
	//"wassup_meta" table to extend wassup. Used as a temporary data cache and to capture additional visitor data. @since v1.8
	if ($wtable == "") $wtable_name = $wassup_meta_table;
	if ($wtable_name == $wassup_meta_table) {

	// Wassup Meta Table Structure:
	// `wassup_key` can be either a foreign key for wp_wassup (contains data from an indexed column) or can be text, ex: for geoip it would contain the wp_wassup key, `ip`.
	// `meta_key` is an abbreviation descriptive of the value stored, ex: 'geoip','chart'.
	// `meta_value` is the value stored. Can be text, number or a serialized array.
	// `meta_expire` is a timestamp that is an expiration date in unix timestamp format...for temporary/cache-only records.
	$sql_create_meta = sprintf("CREATE TABLE `%s` (
  `meta_id` integer(15) unsigned auto_increment,
  `wassup_key` varchar(150) NOT NULL,
  `meta_key` varchar(80) NOT NULL,
  `meta_value` longtext,
  `meta_expire` integer(10) unsigned default '0',
  UNIQUE KEY meta_id (`meta_id`),
  INDEX (`wassup_key`),
  KEY `meta_key` (`meta_key`)) %s;",$wassup_meta_table,$charset_collate);
		$result = dbDelta($sql_create_meta);	//create table

		//try create table with wpdb::query if dbDelta failed
		if(!wassupDb::table_exists($wtable_name)){
			$result=$wpdb->query("$sql_createtable");
			if(!wassupDb::table_exists($wtable_name)){
				$error_msg="\n<br/>".sprintf(__("An error occurred during the install of table %s.","wassup"),$wtable_name)."\n<br/>";
				if(!empty($result) && is_wp_error($result)){
					$errno=$result->get_error_code();
					if((int)$errno > 0){
						$error_msg.=" Error# $errno: ".$result->get_error_message()."\n";
					}
				}
				if($wdebug_mode){
					echo $error_msg;
					exit(1);
				}
			}
		}
	} //end if wassup_meta_table 
	return true;
} //end function wassup_createTable

/**
 * Upgrade Wassup's table structure and data:
 * - update wassup tables structure and indices
 * - drop and recreate 'wassup_tmp' table
 * - drop and rebuild all indices on wassup tables except 'id' (also optimizes)
 * - retroactively upgrade wp_wassup table data for new agent types
 * @param none
 * @return boolean
 */
function wassup_updateTable($wtable=""){
	global $wpdb,$wp_version,$wassup_options,$wdebug_mode;
	if(!empty($wtable) && wassupDb::table_exists($wtable)){
		$wassup_table=$wtable;
		$wassup_options->wassup_table=$wtable;
	}else{
		if(empty($wassup_options->wassup_table)){
			if(is_network_admin()) $wassup_table=$wpdb->base_prefix . "wassup";
			else $wassup_table = $wpdb->prefix . "wassup";
			$wassup_options->wassup_table=$wassup_table;
		}else{
			$wassup_table=$wassup_options->wassup_table;
		}
		//abort if bad wassup table name
		if(!wassupDb::table_exists($wassup_table)){
			echo __FUNCTION__." ERROR: $wassup_table does NOT exist!";
			exit(1);
		}
	}
	$wassup_tmp_table = $wassup_table."_tmp";
	$wassup_meta_table = $wassup_table."_meta";
	//Extend php script execution time to 10.5 minutes to prevent the 'script timeout' error that can cause activation failure when wp_wassup table is very large. @since v1.9
	$stimer_start=time();
	$stimeout=ini_get("max_execution_time");
	if(is_numeric($stimeout)){
		if($stimeout>0 && (int)$stimeout < 630){
			$disabled_funcs=ini_get('disable_functions');
			if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
				$result=@set_time_limit(630);
				if($result) $stimeout=630;
			}
		}elseif($stimeout==0){ //unlimited/server maximum
			$stimeout=630;
		}else{
			$stimeout=0;
		}
	}else{
		$stimeout=0;
	}
	if(empty($stimeout)) $stimeout=58; //use default timeout minus 2 secs
	//get wait timeout length and size of wassup_table in mysql
	$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout FROM dual");
	$rows=$wpdb->get_var("SELECT COUNT(*) AS rows FROM `$wassup_table`");
	$error_msg="";
	$error_count=0;
	//wassup_version must be valid version#, so reset if needed
	$from_version=$wassup_options->wassup_version;
	if(empty($from_version) || !is_numeric($from_version) || version_compare($from_version,WASSUPVERSION,">")){
		$from_version=0;
	}
	$mysqlversion=$wpdb->get_var("SELECT version() as version");
	$sql="";
	//Do the upgrades
	$dbtasks=array();
	$dbtask_keys=array();
	//create wp-cron action for background updates 
	//Note that 'LOW_PRIORITY' is strictly for separate cron/ajax processes only..otherwise it will cause long waits when site is busy
	$low_priority="";
	if(version_compare($wp_version,'3.0','>')){
		$low_priority="LOW_PRIORITY";
		add_action('wassup_upgrade_dbtasks',array('wassupDb','scheduled_dbtask'),10,1);
		if(empty($wassup_options->wassup_googlemaps_key)){
			add_action('wassup_scheduled_api_upg',array('wassupOptions','lookup_apikey'),10,1);
		}
	}
	//Since Wordpress 3.1, 'wassup_createTable' no longer upgrades "wp_wassup" table structure because of an ALTER TABLE error in the "dbDelta" function. @since v1.8.3
	//Do table structure upgrades
	//skip some upgrade checks when script timeout is small number @since v1.9.1
	if($stimeout >180){
	// Upgrade from version < v1.8.4:
	// -add 'spam' field to table - v1.3.9
	// -increase 'wassup_id' field size - v1.5.1
	// -increase size of 'searchengine' + 'spider' fields - v1.7
	// -add field 'url_wpid' column for post_id tracking - v1.8.3
	// -increase size of 'ip' field for IPv6 addresses - v1.8.3
	if((!empty($from_version) && version_compare($from_version,"1.8.4","<"))){
		//add 'spam' field to table
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM `%s` LIKE 'spam'",$wassup_table));
		if(empty($col)){
			$result=$wpdb->query(sprintf("ALTER TABLE `%s` ADD COLUMN `spam` VARCHAR(5) DEFAULT '0'",$wassup_table));
		}
		//increase 'wassup_id' field size
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM `%s` LIKE 'wassup_id'",$wassup_table));
		if(!empty($col->Type) && $col->Type !="varchar(60)"){
			$result=$wpdb->query(sprintf("ALTER TABLE `%s` MODIFY `wassup_id` varchar(60) NOT NULL",$wassup_table));
		}
		//increase size of 'searchengine' and 'spider' fields
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM `%s` LIKE 'searchengine'",$wassup_table));
		if(!empty($col->Type) && $col->Type !="varchar(25)"){
			$result=$wpdb->query(sprintf("ALTER TABLE `%s` MODIFY `searchengine` varchar(25) DEFAULT NULL",$wassup_table));
		}
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM `%s` LIKE 'spider'",$wassup_table));
		if(!empty($col->Type) && $col->Type !="varchar(50)"){
			$wpdb->query(sprintf("ALTER TABLE `%s` MODIFY `spider` varchar(50) DEFAULT NULL",$wassup_table));
		}
		//add field 'url_wpid' column for post_id tracking 
		$col=$wpdb->get_var(sprintf("SHOW COLUMNS FROM `%s` LIKE 'url_wpid'",$wassup_table));
		if(empty($col)){
			$result=$wpdb->query(sprintf("ALTER TABLE `%s` ADD COLUMN `url_wpid` varchar(50) DEFAULT NULL",$wassup_table));
		}
		//increase size of 'ip' field for IPv6 addresses
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM `%s` LIKE 'ip'",$wassup_table));
		if(!empty($col->Type) && $col->Type !="varchar(50)"){
			$result=$wpdb->query(sprintf("ALTER TABLE `%s` MODIFY `ip` varchar(50) DEFAULT NULL",$wassup_table));
		}
	}
	}//end if stimeout >180
	$result=false;
	$error_msg="";
	// Upgrade from v1.8.7:
	// -add new field, `subsite_id` for multisite compatibility
	// -drop indices on 'os', 'browser', and (wassup_id,timestamp)
	// -add index on 'ip' column indices on 'os', 'browser', and (wassup_id,timestamp)
	//if(empty($from_version) || version_compare($from_version,'1.8.7','<=')){
		//add table column, 'subsite_id' for multisite
		$col=$wpdb->get_var(sprintf("SHOW COLUMNS FROM `%s` LIKE 'subsite_id'",$wassup_table));
		if(empty($col)){
			$result=$wpdb->query("ALTER TABLE `$wassup_table` ADD COLUMN `subsite_id` mediumint(9) UNSIGNED DEFAULT 0");
		}
		//drop indices on 'os','browser', and (wassup_id+timestamp) columns
		$wkeys=$wpdb->get_results("SHOW INDEX FROM `$wassup_table` WHERE Column_name='browser' OR Column_name='os' OR (Column_name='timestamp' AND Key_name LIKE 'idx_wassup%')");
		if(!empty($wkeys)){
			if(is_array($wkeys) && !empty($wkeys[0]->Key_name)){
				foreach($wkeys AS $dropkey){
					$keyresult=$wpdb->query(sprintf("DROP INDEX `%s` ON `$wassup_table`",$dropkey->Key_name));
				}
			}
		}
		//add new index on ip...add to index rebuild queue
		$wkey=$wpdb->get_results(sprintf("SHOW INDEX FROM `%s` WHERE Column_name='ip'",$wassup_table));
		if(empty($wkey)){
			//add to index rebuild queue
			$dbtask_keys['ip']=sprintf("ALTER TABLE `%s` ADD INDEX `ip` (`ip`)",$wassup_table);
		}
	//}
	// Upgrade from v1.9:
	// -remove all Wassup 1.9 scheduled actions from wp-cron
	if(!empty($from_version) && $from_version=="1.9"){
		remove_action('wassup_scheduled_optimize',array('wassupDb','scheduled_dbtask'));
		remove_action('wassup_scheduled_dbtasks',array('wassupDb','scheduled_dbtask'));
		remove_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
		remove_action('wassup_scheduled_purge','wassup_auto_cleanup');
		wp_clear_scheduled_hook('wassup_scheduled_optimize');
		wp_clear_scheduled_hook('wassup_scheduled_purge');
		wp_clear_scheduled_hook('wassup_scheduled_cleanup');
	}
	//log errors
	if($wdebug_mode && !empty($result) && is_wp_error($result)){
		$errno=$result->get_error_code();
		if(!empty($errno)){
			$error_msg .="\n".__FUNCTION__.' ERROR: "subsite_id" column was NOT created';
			$error_msg .="\t SQL error# $errno: ".$result->get_error_message();
		}
	}
	// For all upgrades:
	// - drop wassup_tmp table
	// - clear all cached records from wassup_meta table, or
	// - create wassup_meta table, if missing
	// - drop and rebuild all indices except 'id' and 'meta_id'
	// - recreate wassup_tmp table
	// note that wassup_meta_table and wassup_tmp_table are also added by 'wassup_Tableinstaller' function after script ends, if needed
	// Drop wassup_tmp table
	if(version_compare($wp_version,"2.8","<")){
		$result=mysql_query(sprintf("DROP TABLE IF EXISTS `%s`",$wassup_tmp_table));
	}else{
		 $result=$wpdb->query(sprintf("DROP TABLE IF EXISTS `%s`",$wassup_tmp_table));
		if($wdebug_mode && !empty($result) && is_wp_error($result)){
			$errno=$result->get_error_code();
			if(!empty($errno)){
				$error_msg .="\n".__FUNCTION__.' ERROR: problem dropping wassup_tmp table';
				$error_msg .="\t SQL error# $errno: ".$result->get_error_message();
			}
		}
	}
	if(!empty($error_msg)){
		echo $error_msg;
		exit(1);
	}
	//index rebuild could take a long time, so finish process in background in case of a browser timeout
	ignore_user_abort(1);
	$indices_tables=array($wassup_table); //for index rebuild below
	// Create wassup_meta table, if missing
	if(!wassupDb::table_exists($wassup_meta_table)){
		wassup_createTable($wassup_meta_table,true);
	}else{
		//or clear cached records from wassup_meta...
		$sql=sprintf("DELETE FROM %s WHERE `meta_expire`>0",$wassup_meta_table);
		$result=$wpdb->query($sql);
		if($wdebug_mode && !empty($result) && is_wp_error($result)){
			$errno=$result->get_error_code();
			if(!empty($errno)){
				$error_msg .="\n".__FUNCTION__.' ERROR: clear cache/wassup_meta table problem';
				$error_msg .="\t SQL error# $errno: ".$result->get_error_message();
			}
			echo $error_msg;
			exit(1);
		}
		$indices_tables[]=$wassup_meta_table;
	}
	// Drop wassup tables indices
	if($stimeout >90 || $rows < 25000){
	foreach ($indices_tables AS $wtbl){
		//get list of all wassup indices except id
		$wkeys=$wpdb->get_col(sprintf("SHOW INDEX FROM `%s` WHERE Key_name NOT LIKE '%%id'",$wtbl),2);
		if(!empty($wkeys) && is_array($wkeys)){
			//note: "show index" lists keys multiple time for indices on more than 1 column
			foreach(array_unique($wkeys) AS $idx){
				$result=$wpdb->query(sprintf("DROP INDEX `%s` ON `%s`",$idx,$wtbl));
				//queue the indices rebuild
				//..don't rebuild duplicate keys or idx_wassup
				if(preg_match('/^[a-z][a-z\-_]+\d+$/i',$idx)==0 && $idx != "idx_wassup"){
					$dbtask_keys[$idx]=sprintf("ALTER TABLE `%s` ADD INDEX `%s` (`%s`)",$wtbl,$idx,$idx); //index name included to prevent duplicate keys being created
				}
			} //end foreach(2)
		} //end if !wkeys
	}
	} //end if stimeout
	// Rebuild indices
	//increase mysql session timeout to 10 minutes for index rebuild
	if(is_numeric($mtimeout) && $mtimeout< 600) $result=$wpdb->query("SET wait_timeout=600");
	$result=false;
	//rebuild wassup_id index first, in case of script timeout @since v1.9.1
	$wkey=$wpdb->get_results(sprintf("SHOW INDEX FROM `%s` WHERE Column_name='wassup_id'",$wassup_table));
	if(empty($wkey)) $result=$wpdb->query(sprintf("ALTER TABLE `%s` ADD KEY idx_wassup (wassup_id(32))",$wassup_table));
	if($wdebug_mode && !empty($result) && is_wp_error($result)){
		$error_msg .="\n".__FUNCTION__.' ERROR: create wassup_id index on '.$wassup_table.' problem';
		$errno=$result->get_error_code();
		if(!empty($errno)) $error_msg .="\t SQL error# $errno: ".$result->get_error_message();
		echo $error_msg;
		exit(1);
	}
	//rebuild other indices
	if(!empty($dbtask_keys)){
		foreach ($dbtask_keys AS $sql_create_idx) {
			$result=$wpdb->query($sql_create_idx);
			if($wdebug_mode && !empty($result) && is_wp_error($result)){
				$error_msg .="\n".__FUNCTION__.' ERROR: create index problem';
				$errno=$result->get_error_code();
				if(!empty($errno)) $error_msg .="\t SQL error# $errno: ".$result->get_error_message();
			}
		}
		$dbtask_keys=array();
	}
	// Re-create wassup_tmp table
	wassup_createTable($wassup_tmp_table,true);

	//Do retroactive data updates by version#
	//Retroactive data updates are run separately from table structure upgrades (via wp_cron). @since v1.9
	//For upgrade from < v1.8:
	// -retroactively fix incorrect OS "win2008" (="win7") in table
	if(version_compare($from_version,"1.8","<")){
		$upd_timestamp=strtotime("1 January 2009");
		//queue the table data fixes
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='win7' WHERE `timestamp`>'%d' AND `os`='win2008'",$upd_timestamp);
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='win7 x64' WHERE `timestamp`>'%d' AND `os`='win2008 x64'",$upd_timestamp);
	}
	//For upgrade from <= v1.9:
	// -retroactively update data to replace the old "NA" text in `os` and `browser` fields with null
	// -retroactively update search engine data to use "_notprovided_" instead of null as keywords from Google secure search
	// -retroactively fix os and browser data for win8, win10, and ie11
	if(version_compare($from_version,"1.9","<=")){
		//retroactively update data to replace old "NA" text
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='' WHERE `timestamp`<'%d' AND (`os`='NA' OR `os`='N/A')",strtotime("1 January 2007"));
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `browser`='' WHERE `timestamp`<'%d' AND (`browser`='NA' OR `browser`='N/A')",strtotime("1 January 2007"));
		//retroactively insert "_notprovided_" keyword in empty search field from Google Secure Search after Dec 2012
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `search`='_notprovided_',`searchengine`='Google' WHERE `timestamp`>='%d' AND `search`='' AND `searchengine`='' AND `referrer`!='' AND (`referrer` LIKE 'https://www.google.%%' OR `referrer` LIKE 'https://%%_.google.com')",strtotime("1 December 2012"));
		//fix misnamed newer os and browsers versions
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='Win8' WHERE `timestamp`>='%d' AND (`os`='WinNT 6.3' OR `os`='WinNT 6.2')",strtotime("1 January 2013"));
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='Win8 x64' WHERE `timestamp`>='%d' AND (`os`='WinNT 6.3 x64' OR `os`='WinNT 6.2 x64')",strtotime("1 January 2013"));
		$dbtasks[]=sprintf("UPDATE $low_priority $wassup_table SET `browser`='IE 11' WHERE `timestamp`>='%d' AND `browser`='' AND (`os` LIKE 'WinNT 6.3%%' OR `os` LIKE 'Win8%%') AND `agent` LIKE '%%; rv:11.0%%'",strtotime("1 January 2013"));
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='Win10' WHERE `timestamp`>='%d' AND (`os`='WinNT 10' OR `os`='WinNT 10.0')",strtotime("1 January 2015"));
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `os`='Win10 x64' WHERE `timestamp`>='%d' AND (`os`='WinNT 10 x64' OR `os`='WinNT 10.0 x64')",strtotime("1 January 2015"));
		$dbtasks[]=sprintf("UPDATE $low_priority `$wassup_table` SET `browser`='IE 11' WHERE `timestamp`>='%d' AND `browser`='' AND (`os` LIKE 'Win10%%' OR `os` LIKE 'WinNT 10%%') AND `agent` LIKE '%% Edge%%'",strtotime("1 January 2015"));
	} //end if 1.9

	//For all upgrades: 
	// New in v1.9.4: get a new api key
	if(empty($wassup_options->wassup_googlemaps_key)){
		if(!empty($low_priority)){
			wp_schedule_single_event(time()+600,'wassup_scheduled_api_upg');
		}else{
			$key=wassupOptions::lookup_apikey();
		}
	}
	//Queue the retroactive updates
	//schedule retroactive updates via cron so it dosen't slow down activation
	if(count($dbtasks)>0){
		$arg=array('dbtasks'=>$dbtasks);
		if(!empty($low_priority)){
			wp_schedule_single_event(time()+300,'wassup_upgrade_dbtasks',$arg);
		}else{
			wassupDb::scheduled_dbtask($arg);
		}
	}

	//Lastly, check for browser timeout..may not work because of output redirection in Wordpress during plugin install, so also use timer.
	//'echo chr(0);' to send null to browser to check if it is still alive - doesn't work
	//...after 1 minute (normal http request keepAlive time) or browser abort, run 'wassup_settings_install' and save settings
	if(connection_aborted() || (time() - $stimer_start) > 57){
		$wassup_options->wassup_alert_message="Wassup ".WASSUPVERSION.": ".__("Database created/upgraded successfully","wassup");
		wassup_settings_install($wassup_table);
		$wassup_options->wassup_upgraded=time();
		$wassup_options->wassup_version=WASSUPVERSION;
		//$wassup_options->wassup_active=1;
		$wassup_options->saveSettings();
	}
	return true;
} //end function wassup_updateTable

/**
 * Check for wassup tables structure problems.
 * @since v1.9
 */
function wassup_upgradeCheck($wtable=""){
	global $wpdb,$wassup_options;
	if(empty($wassup_options->wassup_table)){
		if(is_network_admin()) $wassup_table=$wpdb->base_prefix . "wassup";
		else $wassup_table = $wpdb->prefix . "wassup";
		$wassup_options->wassup_table=$wassup_table;
	}else{
		$wassup_table =$wassup_options->wassup_table;
	}
	$wassup_meta_table=$wassup_table . "_meta";
	$wassup_tmp_table=$wassup_table . "_tmp";
	$upg_ok=true;
	$msg="";
	//check for tables and structural updates to wassup tables
	if(!empty($wtable) && $wtable != $wassup_table){
		if(!table_exists($wtable)) $upg_ok=false;
	}else{
		if(wassupDb::table_exists($wassup_table)){
			//check if 'subsite_id' column exists
			if(is_multisite()){
				$col=$wpdb->get_row("SHOW COLUMNS FROM `$wassup_table` LIKE 'subsite_id'");
				if(empty($col) || is_wp_error($col)) $upg_ok=false;
			}
		}else{
			$upg_ok=false;
		}
		//check for wassup_meta and wassup_tmp tables
		if($upg_ok && empty($table)){
			if(!wassupDb::table_exists($wassup_meta_table)) $upg_ok=false;
			elseif(!wassupDb::table_exists($wassup_tmp_table)) $upg_ok=false;
		}
	}
	return $upg_ok;
} //end wassup_upgradeCheck

/**
 * Check for Wordpress configuration problems that might affect WassUp running properly.
 * @param string
 * @return boolean
 * @since v1.8
 */
function wassup_compatCheck($item_to_check) {
	global $wpdb,$wp_version;
	$result = false;
	//wp-footer: test for "wp_footer()" function in 'footer.php'
	if ($item_to_check == "wp_footer") {
		$result=true;
		$footer_file =  STYLESHEETPATH."/footer.php";
		if (!file_exists($footer_file)) $footer_file = TEMPLATEPATH."/footer.php";
		if (file_exists($footer_file)) {
			$footer = file_get_contents($footer_file);
			//Note: if "wp_footer()" is commented-out in template code, it will still match as true in test below
			if (stristr($footer,'wp_footer(')!==false || stristr($footer,'wp_footer (')!==false) $result=true;
			else $result=false;
		} else {
			$result=false;
		}
	//check for WP_CACHE constant added by caching plugins
	} elseif ($item_to_check == "WP_CACHE") {
		$result=false;
		if (defined('WP_CACHE') && WP_CACHE!==false && trim(WP_CACHE)!=="") {
			$result=true;
		}
	//check for MySQL database @since v1.9
	} elseif($item_to_check=="mysqldb") {
		$result=true;
		if(version_compare($wp_version,'3.3','>')&& empty($wpdb->is_mysql)) $result=false;
	//check for adequate Wordpress Memory @since v1.9
	} elseif ($item_to_check == "WP_MEMORY_LIMIT") {
		$result=true;
		if(defined('WP_MEMORY_LIMIT')) $wp_memory=WP_MEMORY_LIMIT;
		else $wp_memory=@ini_get('memory_limit');
		$mem=0;
		if(preg_match('/^(\-?\d+)(\s?\w)?/',$wp_memory,$match)>0){
			$mem = (int)$match[1]; 
			if (!empty($match[2]) && strtolower($match[2])=='g') $mem = (int)$match[1]*1024;
		}
		if($mem >0){
			if($mem < 32){
				$result=$mem;
			}elseif($mem < 40){
				if(version_compare($wp_version,"3.5",">=")) $result=$mem;
			}elseif($mem < 64){
				if(version_compare($wp_version,"3.8",">=")) $result=$mem;
			}
		}
	} else {
		$result=true; //default
	}
	return $result;
} //end wassup_compatCheck
?>

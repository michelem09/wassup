<?php
/*
Plugin Name: WassUp Real Time Analytics
Plugin URI: http://www.wpwp.org
Description: Analyze your website traffic with accurate, real-time stats, live views, visitor counts, top stats, IP geolocation, customizable tracking, and more. For Wordpress 2.2+
Version: 1.9.4.2
Author: Michele Marcucci, Helene Duncker
Author URI: http://www.michelem.org/
Text Domain: wassup
Domain Path: /language
License: GPL2

Copyright (c) 2007-2016 Michele Marcucci
Released under the GNU General Public License GPLv2 or later
http://www.gnu.org/licenses/gpl-2.0.html

Disclaimer:
  This program is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
  See the GNU General Public License for more details.
*/
//-------------------------------------------------
//# No direct requests for plugin file "wassup.php"
$wassupfile=preg_replace('/\\\\/','/',__FILE__); //for windows
//abort if this is direct request for file
if((!empty($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME'])===realpath($wassupfile)) ||
   (!empty($_SERVER['PHP_SELF']) && preg_match('#'.str_replace('#','\#',preg_quote($_SERVER['PHP_SELF'])).'$#',$wassupfile)>0)){
	//try track this uri request for "wassup.php"
	if(!headers_sent()){
		//can't track 403-forbidden, so use 404 instead
		//location value triggers redirect to WordPress' 404 error page so Wassup can track this attempt to access itself (original uri request is lost)
		header('Location: /?p=404page&werr=wassup403'.'&wf='.basename($wassupfile));
		exit;
	}else{
		//'wp_die' is undefined here
		die('<strong>Sorry. Unable to display requested page.</strong>');
	}
//abort if no WordPress
}elseif(!defined('ABSPATH') || empty($GLOBALS['wp_version'])){
	//'wp_die' is undefined here
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
//-------------------------------------------------
//### Setup and startup functions
/**
 * Set up WassUp environment in Wordpress
 * @since v1.9
 */
function wassup_init($init_settings=false){
	global $wp_version,$wassup_options,$wdebug_mode;

	//define wassup globals & constants
	if(!defined('WASSUPVERSION')){
		define('WASSUPVERSION','1.9.4.2');
		define('WASSUPDIR',dirname(preg_replace('/\\\\/','/',__FILE__)));
	}
	//turn on debugging (global)...Use cautiously! Will display errors from all plugins, not just WassUp
	$wdebug_mode=false;
	if(defined('WP_DEBUG') && WP_DEBUG==true) $wdebug_mode=true;
	if($wdebug_mode){
		$active_plugins=maybe_serialize(get_option('active_plugins'));
		//turn off debug mode if this is ajax action request @since v1.9.2
		if((!empty($_REQUEST['action']) && isset($_REQUEST['wajax'])) || (defined('DOING_AJAX') && DOING_AJAX)){
			$wdebug_mode=false;
			@wassup_disable_errors();
		}elseif(isset($_REQUEST['wc-ajax']) && preg_match('#/woocommerce\.php#',$active_plugins)>0){	//woocommerce ajax
			$wdebug_mode=false;
			@wassup_disable_errors();
		}else{
			wassup_enable_errors();
			if(headers_sent()){
				//an error was likely displayed to screen
				echo "\n".'<!-- wassup_init start -->';
			}
		}
	}
	//load language translation
	if(empty($GLOBALS['locale']) || strlen($GLOBALS['locale'])>5) $current_locale=get_locale();
	else $current_locale=$GLOBALS['locale'];
	if(!empty($current_locale) && $current_locale !="en_US"){
		$moFile=WASSUPDIR."/language/".$current_locale.".mo";
		if(@is_readable($moFile)){
			load_textdomain('wassup',$moFile);
		}elseif(strlen($current_locale)<6){
			//try load translation file with language code x2 as locale @since v1.9.3
			$lang_only=substr($current_locale,0,2).'_'.strtoupper(substr($current_locale,0,2));
			if($lang_only != $current_locale && preg_match('/^[a-z]{2}_[A-Z]{2}$/',$lang_only)>0){
				$moFile=WASSUPDIR."/language/".$lang_only."mo";
				if(@is_readable($moFile))
					load_textdomain('wassup',$moFile);
			}
		}
	}
	//load required modules
	//check Wordpress and PHP compatibility and load compatibility modules before using 'plugins_url' function
	$php_vers=phpversion();
	$is_compatible=true;
	if(version_compare($wp_version,'4.5','<') || version_compare($php_vers,'5.2','<')){
		include_once(WASSUPDIR.'/lib/compatibility.php');
		$is_compatible=wassup_check_compatibility();
	}
	if($is_compatible){
		if(!class_exists('wassupOptions')) require_once(WASSUPDIR.'/lib/wassup.class.php');
		define('WASSUPURL',plugins_url(basename(WASSUPDIR)));
		//additional modules are loaded as needed
		//require_once(WASSUPDIR.'/lib/wassupadmin.php');
		//require_once(WASSUPDIR.'/lib/main.php');
		//include_once(WASSUPDIR.'/lib/uadetector.class.php');

		//initialize wassup settings for new multisite subsites
		if($init_settings){
			$wassup_options=new wassupOptions(true);
			//save settings only if this is a network subsite
			if(is_multisite() && !is_network_admin() && !is_main_site() && $wassup_options->network_activated_plugin()){
				if(empty($wassup_options->wassup_version) || $wassup_options->wassup_version !=WASSUPVERSION){
					$wassup_options->wassup_version=WASSUPVERSION;
					$wassup_options->saveSettings();
				}
			}
		}else{
			//Load existing wassup wp_option settings, if any
			$wassup_settings=get_option('wassup_settings');
			if(count($wassup_settings)>1 && !empty($wassup_settings['wassup_version']) && $wassup_settings['wassup_version']==WASSUPVERSION){
				$wassup_options=new wassupOptions;
			}else{
				//'else' shouldn't happen unless 'wassup_settings' wp_option record is corrupted or deleted/locked by another application
				$wassup_options=new wassupOptions(true);
				//corruption maybe caused by a 'wassup_install' interrupt or time out before update is done, so try re-save settings @since v1.9.2
				//$wassup_options->wassup_version=WASSUPVERSION;
				$wassup_options->saveSettings();
			}
		}
	}else{
		if(function_exists('is_network_admin') && is_network_admin()){
			add_action('network_admin_notices','wassup_show_compat_message');
		}else{
			add_action('admin_notices','wassup_show_compat_message');
		}
	}
	if($wdebug_mode && headers_sent()){
		//an error message was likely displayed to screen
		echo "\n".'<!-- wassup_init end -->'."\n";
	}
	return $is_compatible;
} //end wassup_init

/**
 * Install or upgrade Wassup plugin.
 *  - check wordpress compatibility
 *  - set initial plugin settings
 *  - check for multisite and set initial wassup network settings
 *  - create/upgrade Wassup tables.
 *  - save wassup settings and wassup network settings.
 * @todo - enable network activation for subdomain networks
 * @param boolean (for multisite network activation)
 * @return void
 */
function wassup_install($network_wide=false) {
	global $wpdb,$wp_version,$wassup_options;

	$wassup_settings=get_option('wassup_settings'); //save old settings
	$wassup_network_settings=array();
	//first check Wordpress compatibility via 'wassup_init'
	if(!defined('WASSUPURL')){
		if(!wassup_init(true)){
			wassup_show_compat_message();
			exit(1);
		}
	}
	//additional install/upgrade functions in "upgrade.php" module
	if (file_exists(WASSUPDIR.'/lib/upgrade.php')) {
		require_once(WASSUPDIR.'/lib/upgrade.php');
	} else {
		echo sprintf(__("File %s does not exist!","wassup"),WASSUPDIR.'/lib/upgrade.php');
		exit(1);
	}
	//initialize/update wassup_settings in wp_options
	if(empty($wassup_options) || empty($wassup_options->wassup_version) || $wassup_options->wassup_version != WASSUPVERSION){
		$wassup_options=new wassupOptions(true);
	}
	//network-wide settings for multisite @since v1.9
	if(is_multisite()){
		if(is_network_admin()){
			$network_wide=true;
			//no network activation in subdomain networks, subdomain sites must activate Wassup separately @TODO
			if(is_subdomain_install()){
				//long error message is NOT displaying for network activation error in WordPress 4.6.1, so use 'wp_die' instead of 'echo/exit' @since v1.9.1
				$err = __("Sorry! \"Network Activation\" is DISABLED for subdomain networks.","wassup");
				$err .= ' '.sprintf(__("%s must be activated on each subdomain site separately.","wassup"),'<strong>Wassup Plugin</strong>');
				$err .=' <br/>'.__("Activate plugin on your parent domain (main site) to set default options for your network.","wassup");
				$err .= '<br/><br/><a href="'.network_admin_url("plugins.php").'">'.__("Back to Plugins","wassup").'</a>';
				wp_die($err);
			}
			$wassup_network_settings=wassup_network_install($network_wide);
		}else{
			$network_wide=false;
			$wassup_network_settings=wassup_network_install($network_wide);
			$subsite_settings=wassup_subsite_install($wassup_network_settings);
			if(!empty($subsite_settings)) $wassup_options->loadSettings($subsite_settings);
		}
	}else{
		$network_wide=false;
	}
	//set table names
	//reset Wassup table name if wpdb prefix has changed @since v1.9
	if(empty($wassup_options->wassup_table) || !wassupDb::table_exists($wassup_options->wassup_table)){
		if($network_wide){
			if(empty($wassup_network_settings['wassup_table']) || !wassupDb::table_exists($wassup_network_settings['wassup_table'])) $wassup_table=$wpdb->base_prefix . "wassup";
			else $wassup_table= $wassup_network_settings['wassup_table'];
		}else{
			$wassup_table=$wpdb->prefix . "wassup";
		}
	}else{
		$wassup_table=$wassup_options->wassup_table;
	}
	$wassup_meta_table=$wassup_table."_meta";
	$wassup_options->wassup_table=$wassup_table;

	//turn off 'wassup_active' setting and cancel all scheduled Wassup wp-cron jobs for upgrades only
	$active_status=1;
	if(!empty($wassup_settings)){
		//save current 'wassup_active' setting prior to upgrade for later restore
		$active_status=$wassup_settings['wassup_active'];
		if($network_wide && !empty($wassup_network_settings['wassup_active'])){
			$wassup_network_settings['wassup_active']="0";
			update_site_option('wassup_network_settings',$wassup_network_settings);
		}elseif(!empty($active_status)){
			$wassup_options->wassup_active="0";
			$wassup_options->saveSettings();
		}
		//cancel all scheduled Wassup wp-cron jobs in case Wassup wp-cron jobs not previously canceled
		if(!empty($wassup_options->wassup_upgraded) || (!empty($wassup_options->wassup_version) && version_compare($wassup_options->wassup_version,"1.9",">="))){
			wassup_cron_terminate();
		}
	}
	//Do the table upgrade
	$admin_message="";
	$wsuccess=false;
	//upgrade table for new version of WassUp, after reset-to-default, or when table structure is outdated
	if(empty($wassup_options->wassup_version) || WASSUPVERSION != $wassup_options->wassup_version || $wassup_options->wassup_upgraded==0 || !wassup_upgradeCheck()){
		//increase script timeout to 16 minutes to prevent activation failure due to script timeout (browser timeout can still occur)
		$stimeout=ini_get("max_execution_time");
		if(is_numeric($stimeout) && $stimeout>0 && $stimeout < 990){
			//check for 'set_time_limit' in disabled functions before changing script timeout
			$disabled_funcs=ini_get('disable_functions');
			if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
				@set_time_limit(990);
			}
		}
		//do the table upgrade
		$wassup_options->wassup_upgraded=0;
		$wsuccess=wassup_tableInstaller($wassup_table);
		if($wsuccess){
			$admin_message=__("Database created/upgraded successfully","wassup");
		}else{
			$admin_message=__("An error occurred during the upgrade. WassUp table structure may not have been updated properly.","wassup");
		}
	}else{
		//separate message for re-activation without table upgrade @since v1.9
		$admin_message=__("activation successful","wassup");
		if(!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'plugin-install.php')==false){
			if(!is_multisite() || is_main_site() || is_network_admin()) $admin_message=__("activation successful. No upgrade necessary.","wassup");
		}
	}
	//verify that main table is installed, then save settings
	$wassup_table=$wassup_options->wassup_table; //in case changed
	$wassup_meta_table=$wassup_table."_meta";
	if(wassupDb::table_exists($wassup_options->wassup_table)){
		$wassup_options->wassup_alert_message=$admin_message;
		//update settings
		if($wsuccess){
			//update network settings
			if($network_wide){
				if(!is_subdomain_install()) $wassup_network_settings['wassup_table']=$wassup_table;
				else unset($wassup_network_settings['wassup_table']);
				$wassup_network_settings['wassup_active']=1;
				update_site_option('wassup_network_settings',$wassup_network_settings);
			}
			//update site settings
			//check the upgrade timestamp to prevent repeat of 'wassup_settings_install()' (runs in 'upgrade.php')
			if(empty($wassup_options->wassup_upgraded) || ((int)time() - (int)$wassup_options->wassup_upgraded) >180){
				wassup_settings_install($wassup_table);
			}
			$wassup_upgraded=time();
			$wassup_options->wassup_version=WASSUPVERSION;
		}elseif(!wassup_upgradeCheck($wassup_table)){
			//table not upgraded - exit with error
			if(!empty($admin_message)) $error_msg=$admin_message;
			else $error_msg='<strong style="color:#c00;padding:5px;">'.sprintf(__("%s: database upgrade failed!","wassup"),"Wassup ".WASSUPVERSION).'</strong>';
			if($wdebug_mode) $error_msg .=" <br/>wassup table: $wassup_table &nbsp; wassup_meta table: $wassup_meta_table";
			echo $error_msg;
			exit(1);
		}
		$wassup_options->wassup_active=$active_status;
		$wassup_options->saveSettings();

		//schedule regular cleanup of temp recs @since v1.9
		if(!empty($active_status)) wassup_cron_startup();
	}else{
		//table not upgraded - exit with error
		$error_msg='<strong style="color:#c00;padding:5px;">'.sprintf(__("%s: plugin install/upgrade failed!","wassup"),"Wassup ".WASSUPVERSION).'</strong>';
		if($wdebug_mode) $error_msg .=" <br/>wassup table: $wassup_table &nbsp; wassup_meta table: $wassup_meta_table";
		echo $error_msg;
		exit(1); //exit with error
	}
} //end wassup_install

/**
 * Completely remove all wassup tables and options from Wordpress and deactivate plugin, if needed.
 *
 * NOTES:
 *  - no Wassup classes, globals, constants or functions are used during uninstall per Wordpress 'uninstall' hook requirement
 *  - no compatibility functions are loaded, so use 'function_exists' check for functions after Wordpress 2.2
 * @param boolean (for multisite uninstall)
 * @return void
 */
function wassup_uninstall($network_wide=false){
	global $wpdb,$wp_version,$current_user;
	$wassup_network_settings=array();
	if(empty($current_user->ID)) wp_get_current_user();
	//for multisite uninstall,
	if(function_exists('is_multisite') && is_multisite()){
		if($network_wide || is_network_admin()){
			$network_wide=true;
			$subsite_ids=$wpdb->get_col(sprintf("SELECT `blog_id` FROM `$wpdb->blogs` WHERE `site_id`='%d' ORDER BY `blog_id` DESC",$GLOBALS['current_blog']->site_id));
		}
		if(empty($subsite_ids) || is_wp_error($subsite_ids)){
			$network_wide=false;
			$subsite_ids = array("0"); //current site uninstall
		}
	}else{	//for single-site uninstall
		$network_wide=false;
		$subsite_ids = array("0");
	}
	//wassup should not be active during network uninstall
	if($network_wide){
		$wassup_network_settings=get_site_option('wassup_network_settings');
		if(!empty($wassup_network_settings['wassup_active'])){
			$wassup_network_settings['wassup_active']="0";
			update_site_option('wassup_network_settings',$wassup_network_settings);
		}
	}
	//remove tables and settings for each subsite (or single-site)
	foreach($subsite_ids as $subsite_id){
		if($network_wide) switch_to_blog($subsite_id);
		$wassup_settings = get_option('wassup_settings');
		//first, stop recording (when plugin is still running)
		if(!$network_wide && !empty($wassup_settings['wassup_active'])){
			$wassup_settings['wassup_active']="0";
			update_option('wassup_settings',$wassup_settings);
		}
		//wp-ajax actions may persist, so remove it
		remove_action('wp_ajax_wassup_action_handler','wassup_action_handler');
		//wp-cron actions may persist, so remove them
		remove_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
		remove_action('wassup_scheduled_purge','wassup_auto_cleanup');
		remove_action('wassup_scheduled_dbtasks',array('wassupDb','scheduled_dbtask'));
		//scheduled tasks in wp-cron persist, so remove them
		if(version_compare($wp_version,'3.0','>')){
			wp_clear_scheduled_hook('wassup_scheduled_cleanup');
			wp_clear_scheduled_hook('wassup_scheduled_purge');
			wp_clear_scheduled_hook('wassup_scheduled_dbtasks');
			if(!is_multisite() || is_main_site() || empty($wassup_network_settings['wassup_table'])){
				remove_action('wassup_scheduled_optimize',array('wassupDb','scheduled_dbtask'));
				wp_clear_scheduled_hook('wassup_scheduled_optimize');
			}
		}
		//aside widgets persist, so remove them
		$wassup_widgets=array('wassup_online','wassup_topstats');
		foreach ($wassup_widgets AS $wwidget){
			if(function_exists('unregister_widget')){
				unregister_widget($wwidget."Widget");
			}else{
				wp_unregister_sidebar_widget($wwidget);
			}
			//cleanup aside widget options from wp_option
			$deleted=$wpdb->query(sprintf("DELETE FROM {$wpdb->prefix}options WHERE `option_name` LIKE 'widget_%s'",$wwidget.'%'));
		}
		//if plugin is still running, deactivate it
		if(function_exists('is_plugin_active') && function_exists('deactivate_plugins')){
			$wfile=preg_replace('/\\\\/','/',__FILE__);
			$wassupplugin=plugin_basename($wfile);
			if(is_plugin_active($wassupplugin)){
				deactivate_plugins($wassupplugin);
			}elseif(is_plugin_active(basename(dirname(__FILE__)))){
				deactivate_plugins(basename(dirname(__FILE__)));
			}
		}
		//remove wassup tables
		//$wassup_table = $wassup_settings['wassup_table'];
		$wassup_table = $wpdb->prefix."wassup";
		$table_tmp_name = $wassup_table."_tmp";
		$table_meta_name = $wassup_table."_meta";
		//purge wassup tables- WARNING: this is a permanent erase!!
		if(version_compare($wp_version,'3.1','>')){
			$dropped=$wpdb->query("DROP TABLE IF EXISTS $table_meta_name");
			$dropped=$wpdb->query("DROP TABLE IF EXISTS $table_tmp_name");
			$dropped=$wpdb->query("DROP TABLE IF EXISTS $wassup_table");
		}
		//make sure tables were dropped (compatibility code) @since v1.9.2
		if($wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'",like_escape($wassup_table)))==$wassup_table){
			//"if exists" in wpdb::query caused error in early versions of Wordpress
			if($wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'",like_escape($table_meta_name)))==$table_meta_name){
				$dropped=$wpdb->query("DROP TABLE $table_meta_name");
			}
			if($wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'",like_escape($table_tmp_name)))==$table_tmp_name){
				$dropped=$wpdb->query("DROP TABLE $table_tmp_name");
			}
			$dropped=$wpdb->query("DROP TABLE $wassup_table");
		}
		//delete settings from wp_option and wp_usermeta tables
		delete_option('wassup_settings');
		//only current_user's settings are deleted here, all other users' wassup_settings are deleted outside foreach loop
		if(function_exists('delete_user_option')) {
			delete_user_option($current_user->ID,'_wassup_settings');
		}else{
			delete_usermeta($current_user->ID,$wpdb->prefix.'_wassup_settings');
		}
	} //end foreach
	//lastly, delete network settings & users' settings
	if($network_wide){
		restore_current_blog();
		delete_site_option('wassup_network_settings');
		$deleted=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `meta_key` LIKE '%%_wassup_settings'",$wpdb->base_prefix."usermeta"));
	}elseif(!function_exists('is_multisite') || !is_multisite()){
		//delete 'wassup_settings' from wp_usermeta for all users in single-site install
		$deleted=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `meta_key` LIKE '%%_wassup_settings'",$wpdb->prefix."usermeta"));
	}
} //end wassup_uninstall

/** Stop Wassup wp-cron and wp-ajax on deactivation. @since v1.9 */
function wassup_deactivate(){
	global $wp_version;
	//wp-ajax action may persist, so remove it
	remove_action('wp_ajax_wassup_action_handler','wassup_action_handler');
	//wassup_cron_terminate();
	//wp-cron actions may persist, so remove them
	remove_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
	remove_action('wassup_scheduled_purge','wassup_auto_cleanup');
	remove_action('wassup_scheduled_dbtasks',array('wassupDb','scheduled_dbtask'));
	//scheduled tasks in wp-cron persist, so remove them
	if(version_compare($wp_version,'3.0','>')){
		wp_clear_scheduled_hook('wassup_scheduled_cleanup');
		wp_clear_scheduled_hook('wassup_scheduled_purge');
		wp_clear_scheduled_hook('wassup_scheduled_dbtasks');
		if(!is_multisite() || is_main_site() || empty($wassup_network_settings['wassup_table'])){
			remove_action('wassup_scheduled_optimize',array('wassupDb','scheduled_dbtask'));
			wp_clear_scheduled_hook('wassup_scheduled_optimize');
		}
	}
}
/**
 * Start Wassup plugin
 *  -assign plugin functions to Wordpress init (preload)
 * @since v1.9
 */
function wassup_start(){
	//startup wassup
	add_action('init','wassup_preload',11);
	add_action('login_init','wassup_preload',11); //separate action
	add_action('admin_init','wassup_admin_preload',11);
	add_action('plugins_loaded','wassup_load');
}

/**
 * Perform plugin tasks for before http headers are sent.
 *  -block obvious xss and sql injection attempts on Wassup itself
 *  -initialize new network subsite settings (via 'wassup_init'), if any
 *  -setup maintenance tasks for wp-ajax/wp_cron/wp_login hook actions
 *  -start wassup tracking
 */
function wassup_preload(){
	global $wp_version,$current_user,$wassup_options,$wdebug_mode;
	//block any obvious sql injection attempts involving WassUp
	$request_uri=$_SERVER['REQUEST_URI'];
	if(!$request_uri) $request_uri=$_SERVER['SCRIPT_NAME']; // IIS
	if(stristr($request_uri,'wassup')!==false && strstr($request_uri,'err=wassup403')===false){
		$error_msg="";
		//don't test logged-in user requests
		if(!is_user_logged_in()){
			if(preg_match('/(<|&lt;?|&#0*60;?|%3C)scr(ipt|[^0-9a-z\-_])/i',$request_uri)>0){
				$error_msg=__('Bad request!','wassup');
				if($wdebug_mode) $error_msg .=" xss not allowed.";
			}elseif(preg_match('/[&?][^=]+=\-[19]+|(select|update|delete|alter|drop|union|create)[ %&][^w]+wp_/i',str_replace(array('\\','&#92;','"','%22','&#34;','&quot;','&#39;','\'','`','&#96;'),'',$request_uri))>0){
				$error_msg=__('Bad request!','wassup');
				if($wdebug_mode) $error_msg .=" special chars not allowed.";
			}
		}
		//abort bad requests
		if(!empty($error_msg)){
			if($wdebug_mode){
				wp_die($error_msg.' :'.esc_attr(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','---',$_SERVER['REQUEST_URI'])));
			}
			//redirect bad requests to 404 error page
			if(!headers_sent()) header('Location: /?p=404page&werr=wassup403');
			else wp_die($error_msg);
			exit;
		}
	}
	//load Wassup settings and includes
	if(!defined('WASSUPURL')){
		//load Wassup settings for new network subsites
		if(function_exists('is_network_admin') && !is_network_admin() && !is_main_site()) $is_compatible=wassup_init(true);
		else $is_compatible=wassup_init();
		if(!$is_compatible){	//do nothing
			return;
		}
	}
	if($wdebug_mode && headers_sent()){
		//an error message was likely displayed to screen
		echo "\n".'<!-- wassup_preload start -->';
	}
	//fix for object error seen in support forum, but redundant to fix already in 'wassup_init' @since v1.9.2
	if(empty($wassup_options)){
		if(!class_exists('wassupOptions')) require_once(WASSUPDIR.'/lib/wassup.class.php');
		$wassup_options=new wassupOptions;
		if(empty($wassup_options)) return; //nothing to do
	}
	//reset wassup user settings at login @since v1.9
	add_action('wp_login',array($wassup_options,'resetUserSettings'),9,2);
	//assign action handler for wp-ajax operations @since v1.9.1
	if(!function_exists('wassup_action_handler')){
		require_once(WASSUPDIR .'/lib/action.php');
	}
	add_action('wp_ajax_wassup_action_handler','wassup_action_handler');
	//for backward compatibility with older versions of Wordpress
	//..runs 'admin_init' hook functions for Wordpress 2.2 - 2.5
	if(function_exists('wassup_compat_preload')){
		wassup_compat_preload();
	}
	//Start maintenance tasks & visitor tracking
	if(!empty($wassup_options) && $wassup_options->is_recording_active()){
		//add actions for wp-cron scheduled tasks - @since v1.9
		if(!has_action('wassup_scheduled_dbtasks')) add_action('wassup_scheduled_dbtasks',array('wassupDb','scheduled_dbtask'),10,1);
		if(!is_multisite() || is_main_site() || !$wassup_options->network_activated_plugin()){
			if (!has_action('wassup_scheduled_optimize')) add_action('wassup_scheduled_optimize',array('wassupDb','scheduled_dbtask'),10,1);
		}
		//add custom actions for cleanup of inactive wassup_tmp records and expired cache records, if needed
		if(!has_action('wassup_scheduled_cleanup')) add_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
		if(!empty($wassup_options->delete_auto) && $wassup_options->delete_auto !="never"){
			if(!has_action('wassup_scheduled_purge')) add_action('wassup_scheduled_purge','wassup_auto_cleanup');
		}

		//track visitors
		wassupPrepend();
	}
	if($wdebug_mode && headers_sent()){
		//an error message was likely displayed to screen
		echo "\n".'<!-- wassup_preload end -->'."\n";
	}
} // end wassup_preload

/**
 * Perform plugin actions for before start of page rendering.
 *  - load Wassup widgets
 *  - load footer tag/javascripts
 */
function wassup_load() {
	global $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	//load widgets and visitor tracking footer scripts
	if(!empty($wassup_options)){
		if($wassup_options->is_recording_active()){
			add_action("widgets_init",'wassup_widget_init',9);
			if(is_admin()) add_action('admin_footer','wassup_foot');
			else add_action('wp_footer','wassup_foot');
		}
		//load admin interface
		if(is_admin()){
			if(!function_exists('wassup_admin_load')) require_once(WASSUPDIR.'/lib/wassupadmin.php');
			wassup_admin_load();
		}
	}
} //end wassup_load

//-------------------------------------------------
//### Admin functions
// WassUp admin panels and menus display functions are in a separate module, "wassupadmin.php" @since v1.9.1
/**
 * Perform plugin admin tasks for before http headers are sent.
 *  - run 'initialize settings' for new network subsites, if needed
 *  - hook function for plugin deactivation actions
 *  - run Wassup ajax requests manually, if needed
 *  - run export request, if any
 *  - hook function for javascript libraries and frameworks load
 * @since v1.9
 */
function wassup_admin_preload() {
	global $wpdb, $wp_version, $wassup_options, $wdebug_mode;

	if(!defined('WASSUPURL')){
		//initializes new network subsites settings, if needed
		if(function_exists('is_network_admin') && !is_network_admin() && !is_main_site()) $is_compatible=wassup_init(true);
		else $is_compatible=wassup_init();
		if(!$is_compatible) return;	//nothing to do
	}
	//uninstall on deactivation when 'wassup_uninstall' option is set...applies to multisite subdomains and Wordpress 2.x setups only
	if(!empty($wassup_options->wassup_uninstall)){
		register_deactivation_hook(__FILE__,'wassup_uninstall');
	}else{
		register_deactivation_hook(__FILE__,'wassup_deactivate');
	}
	if(!empty($_GET['page']) && stristr($_GET['page'],"wassup")!==false){
		//manually run ajax action handler for Wassup ajax only
		if(!empty($_REQUEST['action']) && isset($_REQUEST['wajax'])){
			if(!defined('DOING_AJAX') || !DOING_AJAX){	//superfluous test..applies to 'admin-ajax.php' requests only
				do_action('wp_ajax_wassup_action_handler',$_REQUEST['action']);
			}
			exit;
		}
		//do export early
		if(isset($_REQUEST['export'])){
			export_wassup();
		}
	}
	//wassup scripts and css
	add_action('admin_enqueue_scripts','wassup_add_scripts',12);
} //end wassup_admin_preload

/**
 * Loads javascript and css files for Wassup admin pages.
 * - Enqueues "spia.js", "jquery-ui.js" (various), "jquery-migrate.js" (also queues "jquery.js")
 * - Resets "thickbox.js" to Wassup's internal copy and enqueues it.
 * - Enqueues "wassup.js" and "wassup.css" for Wassup panels
 */
function wassup_add_scripts(){
	global $wp_version,$wdebug_mode;
	$vers=WASSUPVERSION;
	if($wdebug_mode) $vers.='b'.rand(0,9999);
	if(!empty($_GET['page']) && stristr($_GET['page'],'wassup') !== FALSE){
		$wassuppage=wassupURI::get_menu_arg();
		wp_register_script('wassup',WASSUPURL.'/js/wassup.js',array(),$vers);
		if($wassuppage == "wassup-spia" || $wassuppage=="wassup-spy"){
			wp_enqueue_script('spia', WASSUPURL.'/js/spia.js', array('jquery','wassup'), $vers);
		}elseif($wassuppage == "wassup-options"){
			//use Wordpress' jquery-ui.js only when current
			if(version_compare($wp_version,'4.5','>=') || !function_exists('wassup_compat_add_scripts')){
				wp_enqueue_script('jquery-ui-dialog');
				wp_enqueue_script('jquery-ui-tabs');
			}
			//never use Wordpress' jquery-ui.css
			wp_dequeue_style('jquery-ui-tabs.css');
			wp_dequeue_style('jquery-ui-theme.css');
			wp_dequeue_style('jquery-ui-dialog.css');
			wp_dequeue_style('jquery-ui-core.css');
			wp_dequeue_style('jquery-ui.css');
		}
		//use Wassup's custom copy of thickbox.js always
		if(file_exists(WASSUPDIR.'/js/thickbox/thickbox.js')){
			wp_deregister_script('thickbox');
			wp_dequeue_style('thickbox.css');
			//register Wassup's thickbox.js
			wp_enqueue_script('thickbox',WASSUPURL.'/js/thickbox/thickbox.js',array('jquery'),'3');
		}
		//enqueue jquery-migrate.js (and 'jquery.js')
		wp_enqueue_script('jquery-migrate');
		wp_enqueue_script('wassup');	//wassup.js @since v1.9
		//queue wassup stylesheet link tag
		wp_enqueue_style('wassup', WASSUPURL.'/css/wassup.css',array(),$vers);
	}elseif(strpos($_SERVER['REQUEST_URI'],'/widgets.php')!==false || strpos($_SERVER['REQUEST_URI'],'/customize.php')!==false){
		//customizer css for wassup-widget control style
		wp_enqueue_style('wassup',WASSUPURL.'/css/wassup.css',array(),$vers);
	} //end if GET['page']
} //end wassup_add_scripts

/**
 * Check validity of export request then run 'wassupDb::backup_table' to export WassUp main table data into SQL format
 * @param integer
 * @return void
 */
function export_wassup(){
	global $wpdb, $current_user, $wassup_options, $wdebug_mode;

	//#1st verify that export request is valid
	if(!isset($_REQUEST['export'])) return;
	$exportdata=false;
	$badrequest=false;
	$err_msg="";
	$wassup_user_settings=array();
	//user must be logged in to export @since v1.9
	if(!is_object($current_user) || empty($current_user->ID)){
		$user=wp_get_current_user();
	}
	if(!empty($current_user->ID)){
		$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
		//wp_nonce validation of export request @since v1.9
		if(empty($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'],'wassupexport-'.$current_user->ID)) {
			$err_msg=__("Export ERROR: nonce failure!","wassup");
		}
	}else{
		 $err_msg=__("Export ERROR: login required!","wassup");
	}
	//abort invalid export requests
	if($err_msg){
		if(empty($current_user->ID)){
			wp_die($err_msg);
		}else{
			wassup_log_message($err_msg);
			wp_safe_redirect(wassupURI::get_admin_url('admin.php?page=wassup-options&tab=3'));
		}
		exit;
	}
	$err_msg="";
	$wassup_table=$wassup_options->wassup_table;
	$wherecondition="";
	//omit spam records from export @since v1.9.1
	if(empty($wassup_options->export_spam)){
		$wherecondition =" AND `spam`='0'";
	}
	//for multisite compatibility
	if($wassup_options->network_activated_plugin()){
		if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)) $wherecondition .= sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
	}
	//sorted by record id field
	$wherecondition .= ' ORDER BY `id`';
	$start_id=0;
	if(isset($_REQUEST['startid']) && is_numeric($_REQUEST['startid'])){
		$start_id=(int)$_REQUEST['startid'];
	}
	//# check for records before exporting...
	$numrecords=0;
	$exportdata=false;
	$numrecords=$wpdb->get_var(sprintf("SELECT COUNT(`wassup_id`) FROM `%s` WHERE `id` > %d %s",esc_attr($wassup_table),$start_id,$wherecondition));
	if(!is_numeric($numrecords)) $numrecords=0;
	if($numrecords > 0){
		//too big for export, abort @TODO
		if($numrecords > 9999999){
			$err_msg=__("Too much data for Wassup export! Use a separate MySQL Db tool instead.","wassup");
		}else{
			//Could take a long time, so increase script execution time-limit to 11 min
			$stimeout=ini_get('max_execution_time');
			if(is_numeric($stimeout) && $stimeout>0 && $stimeout < 660){
				$disabled_funcs=ini_get('disable_functions');
				if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
					$stimeout=11*60;
					@set_time_limit($stimeout);
				}
			}
			//do the export
			if($_REQUEST['export']=="csv"){
				wassupDb::export_records($wassup_table,$start_id,"$wherecondition","csv");
			}else{
				wassupDb::export_records($wassup_table,$start_id,"$wherecondition","sql");
			}
		}
	}else{
		//failed export message
		$err_msg=__("ERROR: Nothing to Export.","wassup");
	} //end if numrecords > 0
	//if get here, something went wrong with export
	if(!empty($err_msg)){
		wassup_log_message($err_msg);
	}
	//reload screen to show error message
	$reload_uri=remove_query_arg(array('export','whash','type','_wpnonce'));
	wp_safe_redirect($reload_uri);
	exit;
} //end export_wassup

/** Save summary message from export or other action in either wassup_meta or user_metadata @since v1.9.4 */
function wassup_log_message($msg,$msgtype="",$msgkey="0"){
	global $current_user;
	//msgtype,msgkey parameters for wassup_meta msg
	if(!empty($msgtype)){
		$expire=time()+86401; //24-hour expire
		if(empty($msgkey) && !empty($_REQUEST['mid'])){
			$msgkey=$_REQUEST['mid'];
		}
		$saved=wassupDb::update_wassupmeta($msgkey,$msgtype,$msg,$expire);
	}else{
		if(!is_object($current_user) || empty($current_user->ID)){
			$user=wp_get_current_user();
		}
		$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
		$wassup_user_settings['ualert_message']=$msg;
		update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
	}
}
/** Turns off all error notices except fatal errors. */
function wassup_disable_errors(){
	ini_set('error_reporting',E_ERROR);
	//error_reporting(0);	//same as above
	ini_set('display_errors','Off');
}
/** Turns on all error notices */
function wassup_enable_errors(){
	global $wp_version;
	ini_set('display_errors','On');
	//don't use 'strict standards' in old Wordpress versions (part of E_ALL since PHP 5.4)
	$php_vers=phpversion();
	if(version_compare($php_vers,'5.0','>=')){
		if(version_compare($wp_version,'4.0','>=')){
			ini_set('error_reporting',E_ALL);
		}else{
			ini_set('error_reporting',E_ALL & ~E_STRICT & ~E_DEPRECATED);
		}
	}else{
		ini_set('error_reporting',E_ALL);
	}
} //end wassup_enable_errors

if (!function_exists('microtime_float')) {
function microtime_float() {	//replicates microtime(true) from PHP5
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
}
//-------------------------------------------------
//### Tracking functions
/** Start tracking: check for cookie, assign 'wassupappend' to action hook to record tracking */
function wassupPrepend() {
	global $wpdb,$wp_version,$wassup_options,$current_user,$wscreen_res,$wdebug_mode;
	if(empty($wassup_options)) $wassup_options=new wassupOptions;
	//wassup must be active for recording to begin
	if(empty($wassup_options) || !$wassup_options->is_recording_active()){	//do nothing
		return;
	}
	//New in v1.9.4: don't track ajax requests from some plugins
	$active_plugins=maybe_serialize(get_option('active_plugins'));
	//don't track Woocommerce ajax requests
	if(isset($_REQUEST['wc-ajax']) && preg_match('#/woocommerce\.php#',$active_plugins)>0){
		return;
	}
	$wassup_table=$wassup_options->wassup_table;
	$wassup_tmp_table=$wassup_table."_tmp";
	$wscreen_res="";
	//session tracking with cookie
	$session_timeout=false;
	$wassup_timer=0;
	$wassup_id="";
	$subsite_id=(!empty($GLOBALS['current_blog']->blog_id)?$GLOBALS['current_blog']->blog_id:0);
	$cookie_subsite=0;
	$cookieUser="";
	$sessionhash=$wassup_options->whash;
	//in case whash was reset
	if(!isset($_COOKIE['wassup'.$sessionhash])) $sessionhash=wassup_get_sessionhash();
	if(isset($_COOKIE['wassup'.$sessionhash])){
		//use cookie separator '##' instead of '::' to avoid conflict with ipv6 address notation @since v1.9
		$cookie_data = explode('##',esc_attr(base64_decode(urldecode($_COOKIE['wassup'.$sessionhash]))));
		if(count($cookie_data)>3){
			$wassup_id = $cookie_data[0];
			$wassup_timer=(int)$cookie_data[1] - time();
			if(!empty($cookie_data[2])){
				$wscreen_res = $cookie_data[2];
			}
			//username in wassup cookie @since v1.8.3
			if(!empty($cookie_data[5])) $cookieUser=$cookie_data[5];
			if($wassup_timer <= 0 || $wassup_timer > 86400){
				$session_timeout=true;
			}
			//don't reuse wassup_id when subsite changed
			if(is_multisite()){
				if(preg_match('/^([0-9]+)b_/',$wassup_id,$pcs)>0) $cookie_subsite=$pcs[1];
				if($subsite_id != $cookie_subsite) $wassup_id="";
			}
		}
	}
	//for tracking 404 hits when it is 1st visit record
	$urlRequested=$_SERVER['REQUEST_URI'];
	$req_code=200;
	if(is_404()){
		$req_code=404;
	}elseif(function_exists('http_response_code')){
		$req_code=http_response_code();	//PHP 5.4+ function
	}elseif(isset($_SERVER['REDIRECT_STATUS'])){
		$req_code=(int)$_SERVER['REDIRECT_STATUS'];
	}
	//get screen resolution from cookie or browser header data, if any
	if (empty($wscreen_res) && isset($_COOKIE['wassup_screen_res'.$sessionhash])) {
		$wscreen_res = esc_attr(trim($_COOKIE['wassup_screen_res'.$sessionhash]));
		if ($wscreen_res == "x") $wscreen_res="";
	}
	if (empty($wscreen_res) && isset($_SERVER['HTTP_UA_PIXELS'])) {
		//resolution in IE/IEMobile header sometimes
		$wscreen_res = str_replace('X',' x ',$_SERVER['HTTP_UA_PIXELS']);
	}
	if (empty($wscreen_res) || preg_match('/(\d+\sx\s\d+)/i',$wscreen_res)==0){
		$wscreen_res = "";
		$ua=(!empty($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:"");
		if(stristr($urlRequested,'login.php')!==false){
			add_action('login_footer','wassup_foot');
		}elseif(strstr($ua,'MSIE')===false && strstr($ua,'rv:11')===false && strstr($ua,'Edge/')===false){
			//place wassup tag and javascript in document head
			if(is_admin()){
				add_action('admin_head','wassup_head');
			}else{
				add_action('wp_head','wassup_head');
			}
		}
	}
	$timenow=current_time('timestamp');
	//check if user is logged-in
	$logged_user="";
 	if(empty($current_user->user_login)) $user=wp_get_current_user();
	if(!empty($current_user->user_login)){
		$logged_user=$current_user->user_login;
		//for recent successful login.. undo hack attempt label
		//wassup timer is additional check in case this is successful hack
		if(!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'wp-login.php')>0 && $wassup_timer >=0 && $wassup_timer < 5401){
			if(empty($wassup_id)) $wsession_id=wassup_get_sessionid();
			else $wsession_id=$wassup_id;
			//retroactively undo "hack attempt" label
			$wassup_dbtask[]=sprintf("UPDATE `$wassup_table` SET `username`='%s', `spam`='0' WHERE `wassup_id`='%s' AND `spam`!='0' AND `timestamp`>'%d'",$logged_user,$wsession_id,$timenow-90);
		}
		//timeout logged-in users after 10 mins to trigger save into temp for online counts @since v1.9.2
		if($wassup_timer > 5361){ //for 1st save/cookie reset
			$session_timeout=true;
		}elseif($wassup_timer < 4801){	//timeout after ~10 mins
			$session_timeout=true;	//...for save to temp
		}
	}
	//Track visitor
	//Exclude for logged-in user in admin area (unless session_timeout is set)
	if(!is_admin() || empty($logged_user) || $session_timeout || $req_code !=200 || empty($wassup_id)){
		//use 'send_headers' hook for feed, media, and files except when request is wp-admin which doesn't run this hook
		if(is_feed() || preg_match('#[=/\?&](feed|atom)#',$urlRequested)>0){
			if(is_feed() && !headers_sent()){
				add_action('send_headers','wassupAppend');
			}else{
				wassupAppend($req_code);
			}
		}elseif(preg_match('/(\.(3gp|7z|f4[pv]|mp[34])(?:[\?#]|$))/i',$urlRequested)>0){
			//this is audio, video, or archive file request
			if(!is_admin() && !headers_sent()){
				add_action('send_headers','wassupAppend');
			}else{
				wassupAppend($req_code);
			}
		}elseif(preg_match('/([^\?#&]+\.([a-z]{1,4}))(?:[\?#]|$)/i',$urlRequested)>0 && basename($urlRequested)!="robots.txt"){
			//this is multimedia or specific file request
			if(!is_admin() && !headers_sent()){
				add_action('send_headers','wassupAppend');
			}else{
				wassupAppend($req_code);
			}
		}elseif(!is_admin()){
			//use 'send_headers' hook for cookie write or 404 and shutdown hook for all others
			if(empty($wassup_id) || $req_code!=200){
				if(!headers_sent()){
					add_action('send_headers','wassupAppend',15);
				}else{
					add_action('shutdown','wassupAppend',1);
				}
			}elseif($session_timeout && ($wassup_timer <=0 || $wassup_timer >86400) && !headers_sent()){
				//for cookie re-write
				add_action('send_headers','wassupAppend',15);

			}else{
				add_action('shutdown','wassupAppend',1);
			}
		}else{
			//use 'admin_footer' hook for admin area hits @since v1.9.1
			add_action('admin_footer','wassupAppend',15);
		}
		//add tracking for login page separately since 'shutdown' hook doesn't seem to run on login page @since v1.9.1
		if(empty($logged_user) && stristr($urlRequested,'login.php')!==FALSE){
			add_action('login_footer','wassupAppend',15);
		}
	} //end if !is_admin
	//do retroactive update, if any
	if(!empty($wassup_dbtask)){
		$args=array('dbtasks'=>$wassup_dbtask);
		wassupDb::scheduled_dbtask($args);
	}
} //end wassupPrepend

/**
 * Track visitors: collect browser/visitor data and save record in wassup table
 * @param string (http request code)
 */
function wassupAppend($req_code=0) {
	global $wpdb,$wp_version,$current_user,$wassup_options,$wscreen_res,$wdebug_mode;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	//wassup must be active for recording to begin
	if(empty($wassup_options) || !$wassup_options->is_recording_active()){	//nothing to do
		return;
	}
	//load additional wassup modules as needed
	if(!class_exists('wDetector')) require_once(WASSUPDIR.'/lib/main.php');
	if(!class_exists('UADetector')) include_once(WASSUPDIR.'/lib/uadetector.class.php');
	$wpurl=wassupURI::get_wphome();
	$blogurl=wassupURI::get_sitehome();
	$network_settings=array();
	if(is_multisite()){
		$network_settings=get_site_option('wassup_network_settings');
	}
	//identify media requests
	$is_media=false;
	$fileRequested="";
	if(preg_match('#^(/(?:[0-9a-z.\-\/_]+\.(?:3gp|avi|bmp|flv|gif|gifv|ico|img|jpe?g|mkv|mov|mpa|mpe?g|mp[234]|ogg|oma|omg|png|pdf|pp[st]x?|psd|svg|swf|tiff|vob|wav|webm|wma|wmv))|(?:[0-9a-z.\-\/_]+(?:zoom(?:in|out)\.cur)))(?:[\?\#&]|$)#i',$_SERVER['REQUEST_URI'],$pcs)>0){
		$is_media=true;
		if(ini_get('allow_url_fopen')) $fileRequested=$blogurl.$pcs[1];
	}
	$debug_output="";
	if($wdebug_mode){
		if($is_media || is_feed() || (!is_page() && !is_home() && !is_single() && !is_archive())){
			//turn off error display for media, feed, and any non-html requests
			$wdebug_mode=false;
			@wassup_disable_errors();
		}else{
			if(is_admin() || headers_sent()){
				echo "\n".'<!-- *WassUp DEBUG On '."\n";   //hide errors
				echo 'time: '.date('H:i:s').'     locale: '.$GLOBALS['locale'];
			}else{
				$debug_output="\n".'<!-- *WassUp DEBUG On '."\n";   //hide errors
				$debug_output .='time: '.date('H:i:s').'     locale: '.$GLOBALS['locale'];
			}
			wassup_enable_errors();
		}
	} //end if $wdebug_mode
	$error_msg="";
	$wassup_table = $wassup_options->wassup_table;
	$wassup_tmp_table = $wassup_table . "_tmp";
	$wassup_meta_table = $wassup_table."_meta";
	$wassup_recid=0;
	$temp_recid=0;
	$dup_urlrequest=0;
	$wassup_dbtask=array();	//for scheduled db operations
	$wassup_rec=array();
	$recent_hit=false;
	//init wassup table fields...
	$wassup_id = "";
	$timenow = current_time("timestamp");
	$ipAddress = "";
	$IP="";
	$hostname = "";
	$urlRequested = $_SERVER['REQUEST_URI'];
	if(empty($urlRequested) && !empty($_SERVER['SCRIPT_NAME'])){
		$urlRequested=$_SERVER['SCRIPT_NAME']; // IIS
	}
	$referrer = (isset($_SERVER['HTTP_REFERER'])? $_SERVER['HTTP_REFERER']: '');
	$userAgent = (isset($_SERVER['HTTP_USER_AGENT']) ? rtrim($_SERVER['HTTP_USER_AGENT']) : '');
	if(strlen($userAgent) >255){
		$userAgent=substr(str_replace(array('  ','%20%20','++'),array(' ','%20','+'),$userAgent),0,255);
	}
	$search_phrase="";
	$searchpage="0";
	$searchengine="";
	$os="";
	$browser="";
	$language = (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? esc_attr($_SERVER['HTTP_ACCEPT_LANGUAGE']) : '');
	//fields and vars for spam detection...
	$spider="";
	$feed="";
	$logged_user="";
	$comment_user = (isset($_COOKIE['comment_author_'.COOKIEHASH]) ? utf8_encode($_COOKIE['comment_author_'.COOKIEHASH]) : '');
	$spam=0;
	$article_id=0;
	if(empty($article_id) && preg_match('#(/wp-admin|/wp-content|/wp-includes|wp-login|/feed/|/category/|/tag/)#',$urlRequested)==0){
		$page_obj=get_page_by_path($urlRequested);
		if(!empty($page_obj->ID)) $article_id=$page_obj->ID;
	}
	if(empty($article_id) && (is_single() || is_page())){
		if(!empty($GLOBALS['post']->ID)){
			$article_id=$GLOBALS['post']->ID;
		}
	}
	$subsite_id=(!empty($GLOBALS['current_blog']->blog_id)?$GLOBALS['current_blog']->blog_id:0);
	$multisite_whereis="";
	if($wassup_options->network_activated_plugin() && !empty($GLOBALS['current_blog']->blog_id)){
		$multisite_whereis = sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
	}
	//Set more table fields from http_header and other visit data
	$unknown_spider=__("Unknown Spider","wassup");
	$unknown_browser=__("Unknown Browser","wassup");
	$ua = new UADetector();
	if(!empty($ua->name)){
		if($ua->agenttype == "B"){
			$browser = $ua->name;
			if(!empty($ua->version)){
				$browser .= " ".wMajorVersion($ua->version);
				if (strstr($ua->version,"Mobile")!==false){
					$browser .= " Mobile";
				}
			}
		}else{
			$spider = $ua->name;
			if ($ua->agenttype == "F") {
				if(!empty($ua->subscribers)){
					$feed=$ua->subscribers;
				}else{
					$feed=$spider;
				}
			} elseif ($ua->agenttype == "H" || $ua->agenttype == "S") {
				//it's a script injection bot|spammer
				if ($spam == "0") $spam = 3;
			}
		} //end else agenttype
		$os=$ua->os;
		//check for screen resolution
		if(empty($wscreen_res) && !empty($ua->resolution)){
			if(preg_match('/^\d+x\d+$/',$ua->resolution)>0){
				$wscreen_res=str_replace('x',' x ',$ua->resolution);
			}else{
				$ua->resolution;
			}
		}
		if(empty($language) && !empty($ua->language)){
			$language=$ua->language;
		}
		if($wdebug_mode){
			if(is_admin() || headers_sent()){
				if(!empty($debug_output)){
					echo $debug_output;
					echo "\nwassupappend-debug#1";
					$debug_output="";
				}
				echo "\nUAdetecter results: \$ua=".serialize($ua);
			}else{
				$debug_output .="\nUAdetecter results: \$ua=".serialize($ua);
			}
		}
	} //end if $ua->name
	//Set visitor identifier fields: username, wassup_id, ipAddress, hostname
	//re-lookup username in case login was not detected in 'init'
	$is_admin_login = false;
	if(empty($current_user->user_login)) $user=wp_get_current_user();
	if(!empty($current_user->user_login)) {
		$logged_user = $current_user->user_login;
		$is_admin_login = $wassup_options->is_admin_login($current_user);
	}
	$session_timeout = false;
	$wassup_timer=0;
	$cookieIP = "";
	$cookieHost = "";
	$cookieUser = "";
	$sessionhash=$wassup_options->whash;
	//in case hash was reset
	if(!isset($_COOKIE['wassup'.$sessionhash])){
		$sessionhash=wassup_get_sessionhash();
	}
	//# Check for cookies in case this is an ongoing visit
	if(isset($_COOKIE['wassup'.$sessionhash])){
		$cookie_data = explode('##',esc_attr(base64_decode(urldecode($_COOKIE['wassup'.$sessionhash]))));
		if(count($cookie_data)>3){
			$wassup_id = $cookie_data[0];
			$wassup_timer = $cookie_data[1];
			if(!empty($cookie_data[2])) $wscreen_res = $cookie_data[2];
			$cookieIP = $cookie_data[3];
			if(!empty($cookie_data[4])) $cookieHost = $cookie_data[4];
			//username in wassup cookie @since v1.8.3
			if(!empty($cookie_data[5])) $cookieUser = $cookie_data[5];
		}
	}
	//Get visitor ip/hostname from http_header
	if(!empty($cookieIP)){
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		$IP=wassup_clientIP($ipAddress);
		if($cookieIP==$IP){
			$hostname=$cookieHost;
		}elseif(strpos($_SERVER['REMOTE_ADDR'],$cookieIP)!==false){
			$IP=$cookieIP;
			$hostname=$cookieHost;
		}elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strpos($_SERVER['HTTP_X_FORWARDED_FOR'],$cookieIP)!==false){
			$ipAddress=$_SERVER['HTTP_X_FORWARDED_FOR'];
			$IP=$cookieIP;
			$hostname=$cookieHost;
		}else{
			$ipAddress=wassup_get_clientAddr();
			$IP=wassup_clientIP($ipAddress);
			if($cookieIP==$IP) $hostname=$cookieHost;
			else $hostname=wassup_get_hostname($IP);
		}
	}else{
		$ipAddress=wassup_get_clientAddr();
		$IP=wassup_clientIP($ipAddress);
		$hostname=wassup_get_hostname($IP);
	}
	if (empty($ipAddress)) $ipAddress = $_SERVER['REMOTE_ADDR'];
	if (empty($IP)) $IP = wassup_clientIP($ipAddress);
	if (empty($hostname)) $hostname = "unknown";
	if(empty($logged_user)){
		//only one use for username in cookie...to omit admin logout information
		//$logged_user=$cookieUser;
		if(!empty($cookieUser) && strpos($urlRequested,'loggedout')>0){
			$logged_user=$cookieUser;
			$is_admin_login=$wassup_options->is_admin_login($logged_user);
		}
	}
	//get screen resolution value from cookie or browser header data, if any...before new cookie is written
	if(empty($wscreen_res)){
		if(isset($_COOKIE['wassup_screen_res'.$sessionhash])) {
			$wscreen_res=esc_attr(trim($_COOKIE['wassup_screen_res'.$sessionhash]));
			if($wscreen_res == "x") $wscreen_res = "";
		}
		if(empty($wscreen_res) && isset($_SERVER['HTTP_UA_PIXELS'])) {
			//resolution in IE/IEMobile header sometimes
			$wscreen_res=str_replace('X',' x ',esc_attr($_SERVER['HTTP_UA_PIXELS']));
		}
	}
	//check for session timeout and wassup_id in temp-only record
	if(!empty($wassup_id)){
		if((int)$wassup_timer - time() < 1){
			$session_timeout=true;
		}
		//don't share wassup_id across multisite subsites
		if(preg_match('/^([0-9]+)b_/',$wassup_id,$pcs)>0){
			if($pcs[1]!=$subsite_id) $session_timeout=true;
		}
	}
	//assign a wassup_id for visit and write cookie
	if(empty($wassup_id) || $session_timeout || (!empty($wscreen_res) && empty($cookie_data[2]))){
		//reset wassup_id for timeout/new visit only
		if(empty($wassup_id) || $session_timeout){
			$args=array('ipAddress'=>$ipAddress,
				'hostname'=>$hostname,
				'logged_user'=>$logged_user,
				'timestamp'=>$timenow,
				'userAgent'=>$userAgent,
				'subsite_id'=>$subsite_id,
			);
			if(empty($logged_user) && !empty($cookieUser)){
				$args['logged_user']=$cookieUser;
			}
			$wassup_id=wassup_get_sessionid($args);
			$wassup_timer=((int)time() + 2700); //use 45 minutes timer
			//longer session time for logged-in users @since v1.9
			if(!empty($logged_user)){
				$wassup_timer=(int)time()+5400;
			}
		}
		//put the cookie in the oven and set the timer...
		//this must be done before headers sent
		if(!headers_sent()){
			if (defined('COOKIE_DOMAIN')) {
				$cookiedomain = preg_replace('#^(https?\://)?(www\d?\.)?#','',strtolower(COOKIE_DOMAIN));
				if(defined('COOKIEPATH')){
					$cookiepath=COOKIEPATH;
				}else{
					$cookiepath = "/";
				}
			} else {
				$cookieurl = parse_url(get_option('home'));
				$cookiedomain = preg_replace('/^www\d?\./i','',$cookieurl['host']);
				$cookiepath = $cookieurl['path'];
			}
			if(!empty($logged_user)) $cookieUser=$logged_user;
			$expire = 0; //expire on browser close - based on utc timestamp, not on Wordpress time
			$wassup_cookie_value = urlencode(base64_encode($wassup_id.'##'.$wassup_timer.'##'.$wscreen_res.'##'.$IP.'##'.$hostname.'##'.$cookieUser));
			setcookie("wassup".$sessionhash, "$wassup_cookie_value", $expire, $cookiepath, $cookiedomain);
		}
		unset($temp_id, $tempUA, $templen);
	} //end if empty(wassup_id)
	//Retrieve request response code
	if(!is_numeric($req_code) || empty($req_code)){
		$req_code=200;
		if(is_404()){
			$req_code=404;
		}elseif(function_exists('http_response_code')){
			$req_code=http_response_code(); //PHP 5.4+ function
		}elseif(isset($_SERVER['REDIRECT_STATUS'])){
			$req_code=(int)$_SERVER['REDIRECT_STATUS'];
		}
	}
	//sometimes missing media can show as 200, so if request is a media file, also check that file exist
	if($is_media){
		if($req_code==200 && !empty($fileRequested) && !file_exists($fileRequested)){
			$req_code=404;
		}
	}elseif(preg_match('#(^/[0-9a-z\-/\._]+\.([a-z]{1,4}))(?:[\?&\#]|$)#i',$urlRequested,$pcs)>0 && basename($urlRequested)!="robots.txt"){
		//identify file requests
		if(ini_get('allow_url_fopen')) $fileRequested=$blogurl.$pcs[1];
	}
	if($wdebug_mode){
		if(is_admin() || headers_sent()){
			if(!empty($debug_output)){
				echo $debug_output;
				echo "\nwassupappend-debug#2";
				$debug_output="";
			}
			echo "\n\$req_code=$req_code";
		}
	}
	$hackercheck=true;	//for malware checking
	//do an early quick-check for xss code on url and label as spam/malware for temp record, even if spam detection is disabled  @since v1.9.1
	if(preg_match('/(document\.write(?:ln)?|(?:<|&lt;|&#0*60;?|%3C)scr(?:ipt|[^0-9a-z\-_])|[0t;];script|[ +;0]src=|[ +;]href=|%20href=)([^0-9a-z]|http[:s]|ftp[:s])/',$urlRequested)>0){
		$spam=3;
		$hackercheck=false;
	//no malware checks on logged-in users unless 404 activity
	}elseif(empty($wassup_options->wassup_hack) || (!empty($logged_user) && (int)$req_code!=404)){
		$hackercheck=false;
	//no malware checks on feed, multimedia, or simple file/archive requests
	}elseif(is_feed() || $is_media || preg_match('#^/[0-9a-z\-\._]+\.(css|gz|jar|pdf|rdf|rtf|txt|xls|xlt|xml|Z|zip)$#i',$_SERVER['REQUEST_URI'])>0){
		$hackercheck=false;
	}
	@ignore_user_abort(1); // finish script in background if visitor aborts
	//## Start Exclusion controls:
	//#1 First exclusion control is for admin user
	if($wassup_options->wassup_admin=="1" || !$is_admin_login || (strpos($urlRequested,'wp-login.php')>0 && strpos($urlrequested,'loggedout')===false)){
	//#2 Exclude wp-cron utility hits...unless external host
	if (stristr($urlRequested,"/wp-cron.php?doing_wp_cron")===false || empty($_SERVER['SERVER_ADDR']) || $IP!=$_SERVER['SERVER_ADDR']){
	//#3 Exclude wp-admin visits unless possible malware attempt
	if ((!is_admin() && stristr($urlRequested,"/wp-admin/")===false && stristr($urlRequested,"/wp-includes/")===false) || $req_code!=200 || $hackercheck){
		//Get single post/page id, if archive has only 1 post
		if(empty($article_id) && isset($GLOBALS['posts'])){
			if((is_archive() || is_search()) && count($GLOBALS['posts'])==1 && !empty($GLOBALS['posts'][0]->ID)){
				$article_id=$GLOBALS['posts'][0]->ID;
			}
		}
	//#4 Exclude users on exclusion list
	if (empty($wassup_options->wassup_exclude_user) || empty($logged_user) || preg_match('/(?:^|\s*,)\s*('.preg_quote($logged_user).')\s*(?:,|$)/',$wassup_options->wassup_exclude_user)==0){
		//'preg_match' replaces 'explode' for faster matching of users, url requests, and ip addresses @since v1.9
		//@TODO: exclude page requests by post_id
	//#5 Exclude urls on exclusion list
	if (empty($wassup_options->wassup_exclude_url) || preg_match('#(?:^|\s*,)\s*((?:'.str_replace('#','\#',preg_quote($blogurl)).')?'.str_replace('#','\#',preg_quote($urlRequested)).')\s*(?:,|$)#i',$wassup_options->wassup_exclude_url)==0){
		//url matching may be affected by html-encoding, url-encoding, query parameters, and labels on the url - so do those exclusions separately
		$exclude_visit = false;
		if (!empty($wassup_options->wassup_exclude_url)) {
			$exclude_list = explode(',',str_replace(', ',',',$wassup_options->wassup_exclude_url));
			//reverse the pattern/item checked in regex
			foreach ($exclude_list as $exclude_url) {
				$xurl=str_replace($blogurl,'',rtrim(trim($exclude_url),'/'));
			if(!empty($xurl)){
				$regex='#^('.str_replace('#','\#',preg_quote($xurl)).')([&?\#/].+|$)#i';
				if(preg_match($regex,$urlRequested)>0){
					$exclude_visit=true;
					break;
				}elseif(preg_match($regex,esc_attr($urlRequested))>0){
					$exclude_visit=true;
					break;
				}elseif(preg_match($regex,urlencode($urlRequested))>0){
					$exclude_visit=true;
					break;
				}
			}
			}//end foreach
		}
	//#6 Exclude IPs on exclusion list...
	if ((empty($wassup_options->wassup_exclude) || preg_match('#(?:^|\s*,)\s*('.preg_quote($IP).')\s*(?:,|$)#',$wassup_options->wassup_exclude)==0) && !$exclude_visit){
		//match for wildcards in exclude list @since v1.9
		if(strpos($wassup_options->wassup_exclude,'*')!= 0){
			$exclude_list = explode(',',str_replace(', ',',',$wassup_options->wassup_exclude));
			//reverse the pattern/item_checked in regex
			foreach ($exclude_list as $xip) {
			if(!empty($xip) && strpos($xip,'*')!=0){
				$regex='/^'.str_replace('\*','([0-9a-f\.:]+)',preg_quote($xip)).'$/i';
				if(preg_match($regex,$IP)>0){
					$exclude_visit=true;
					break;
				}
			}
			}//end foreach
		}
	//#7 Exclude hostnames on exclusion list @since v1.9
	if ((empty($wassup_options->wassup_exclude_host) || preg_match('#(?:^|\s*,)\s*('.preg_quote($hostname).')\s*(?:,|$)#',$wassup_options->wassup_exclude_host)==0) && !$exclude_visit){
		//match for wildcards in exclude list
		if(strpos($wassup_options->wassup_exclude_host,'*')!==false){
			$exclude_list = explode(',',str_replace(', ',',',$wassup_options->wassup_exclude_host));
			//reverse the pattern/item_checked in regex
			foreach ($exclude_list as $xhost) {
			if(!empty($xhost) && strpos($xhost,'*')!==false){
				$regex='/^'.str_replace('\*','([0-9a-z\-_]+)',preg_quote($xhost)).'$/i';
				if(preg_match($regex,$hostname)>0){
					$exclude_visit=true;
					break;
				}
			}
			}//end foreach
		}
	//#8 Exclude requests for plugins files from recordings
	if ((stristr($urlRequested,"/".PLUGINDIR) === FALSE || stristr($urlRequested,"forum") !== FALSE || $hackercheck) && !$exclude_visit) {
	//#9 Exclude requests for themes files from recordings
	if (stristr($urlRequested,"/wp-content/themes") === FALSE || stristr($urlRequested,"comment") !== FALSE || $req_code==404) {
	//#10 Exclude for logged-in users
	if ($wassup_options->wassup_loggedin == 1 || !is_user_logged_in()) {
		//check user agent string for attack code
		if($spam==0 && wassupURI::is_xss($userAgent)){
			$spam=3;
		}
	//#11 Exclude for wassup_attack (via libwww-perl or xss in user agent)
	if ($wassup_options->wassup_attack==1 || (stristr($userAgent,"libwww-perl")===FALSE && $spam==0)) {
		// Check for duplicates, previous spam check, and screen resolution and get previous settings to prevent redundant checks on same visitor.
		// Dup==same wassup_id and URL, and timestamp<180 secs
		$wpageviews=0;
		$spamresult=0;
		$recent_hit=array();
		//don't wait for slow responses...set mysql wait timeout to 7 seconds
		$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout AS mtimeout FROM DUAL");
		if(!is_numeric($mtimeout) || is_wp_error($mtimeout)){
			$mtimeout=0;
		}
		$wpdb->query("SET wait_timeout=7");
		if($wdebug_mode){
			if(is_admin() || headers_sent()){
				if(!empty($debug_output)){
					echo $debug_output;
					echo "\nwassupappend-debug#3";
					$debug_output="";
				}
				echo "\nSet MySQL wait_timeout=7 from ".$mtimeout;
			}else{
				$debug_output .="\nSet MySQL wait_timeout=7 from ".$mtimeout;
			}
		}
		//get recent hits with same wassup_id
		$recent_hit=$wpdb->get_results(sprintf("SELECT SQL_NO_CACHE `wassup_id`, `ip`, `timestamp`, `urlrequested`, `screen_res`, `username`, `browser`, `os`, `spider`, `feed`, `spam`, `language`, `agent`, `referrer` FROM `$wassup_tmp_table` WHERE `wassup_id`='%s' AND `timestamp` >'%d' %s ORDER BY `timestamp` DESC",esc_sql($wassup_id),$timenow-183,$multisite_whereis));
		//check for duplicate hit
		if(!empty($recent_hit) && !is_wp_error($recent_hit)){
			$wpageviews=count($recent_hit);
			//check 1st record only
			//record is dup if same url and same user-agent
			if($recent_hit[0]->agent == $userAgent || empty($recent_hit[0]->agent)){
				if($recent_hit[0]->urlrequested == $urlRequested || $recent_hit[0]->urlrequested == '[404] '.$urlRequested){
					$dup_urlrequest=1;
				}elseif($is_media && $req_code == 200 && preg_match('/\.(gif|ico|jpe?g|png|tiff)$/i',$fileRequested) >0){
					//exclude images/photos only after confirmation of other valid page hit by visitor
					$dup_urlrequest=1;
				}
			}
			//retrieve previous spam check results
			$spamresult=$recent_hit[0]->spam;
			//don't use hack-attempt label from recent hit when user is logged-in
			if((int)$spamresult==3 && !empty($logged_user)){
				//if(strpos($recent_hit[0]->urlrequested,'wp-login.php')>0 || (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'wp-login.php')>0))){
					$spamresult=0;
				//}
			}
			//retroactively update screen_res
			//...queue the update because of "delayed insert"
			if (empty($recent_hit[0]->screen_res) && !empty($wscreen_res)) {
				$wassup_dbtask[]=sprintf("UPDATE `$wassup_table` SET `screen_res`='%s' WHERE `wassup_id`='%s'",$wscreen_res,$recent_hit[0]->wassup_id);
			}
		}else{
			$recent_hit=array();
		} //end if recent_hit
		//done duplicate check...restore normal timeout
		if(!empty($mtimeout)){
			$wpdb->query("SET wait_timeout=$mtimeout");
		}else{
			$wpdb->query("SET wait_timeout=90");
		}
	//#12 Exclude duplicates
	if($dup_urlrequest == 0){
		//get previously recorded settings for this visitor to avoid redundant tests
		if($spam==0 && (int)$spamresult >0) $spam=$spamresult;
		if(!empty($recent_hit)){
			if(empty($wscreen_res)){
				if(!empty($recent_hit[0]->screen_res)){
					$wscreen_res=$recent_hit[0]->screen_res;
				}
			}
			if($recent_hit[0]->agent == $userAgent || empty($userAgent)){
				$os=$recent_hit[0]->os;
				$browser=$recent_hit[0]->browser;
				$spider=$recent_hit[0]->spider;
				//feed reader only if this page is feed
				if(!empty($recent_hit[0]->feed) && is_feed()){
					$feed=$recent_hit[0]->feed;
				}
			}
		}
	//#13 Exclude admin/admin-ajax requests with same session cookie as recent hit but does not show as a logged user request (ex: /wp-admin/post.php hit from edit link in website page?)
	if((!is_admin() && stristr($urlRequested,"/wp-admin/")===false) || $urlRequested !='/wp-admin/admin-ajax.php' || empty($recent_hit) || ((empty($recent_hit[0]->username) || $recent_hit[0]->username != $cookieUser) && stristr($recent_hit[0]->urlrequested,"/wp-admin/")===false)){
		//check for xss attempts on referrer
		if($spam==0 && $hackercheck && empty($logged_user)){
			//...skip if referrer is own blog
			if(!empty($referrer) && !$is_admin_login && stristr($referrer,$wpurl)!=$referrer && stristr($referrer,$blogurl)!=$referrer && $referrer!=$blogurl.$urlRequested){
				if(wassupURI::is_xss($referrer)){
					$spam=3;
				}elseif(wIsAttack($referrer)){
					$spam=3;
				}
			}
		}
	//#14 Exclude 404 hits unless 1st visit or malware attempt
	if($req_code == 200 || empty($recent_hit) || ($hackercheck && ($spam!=0 || stristr($urlRequested,"/wp-")!==FALSE || preg_match('#\.(php\d?|aspx?|bat|cgi|dll|exe|ini|js|jsp|msi|sh)([^0-9a-z.\-_]|$)|([\\\.]{2}|\/\.|root[^a-z0-9\-_]|[^a-z0-9\-_]passw|\=admin[^a-z0-9\-_]|\=\-\d+|(bin|etc)\/)|[\*\,\'"\:\(\)$`]|[^0-9a-z](src|href|style)[ +]?=|&\#?([0-9]{2,4}|lt|gt|quot);|(?:<|%3c|&lt;?|&\#0*60;?|&\#x0*3c;?)[jpsv]|(?:user|author|admin|id)\=\-?\d+|(administrator|base64|bin|code|config|cookie|delete|document|drop|drupal|eval|exec|exit|function|iframe|insert|install|java|joomla|load|null|repair|script|select|setting|setup|shell|system|table|union|upgrade|update|upload|where|window|wordpress)#i',$urlRequested)>0))){ //v1.9.3.1 bugfix: parenthesis correction
		//omit 'admin-ajax.php' and 'wp-login.php' from malware checks @since v1.9.3
		if($hackercheck && $spam==0 && $urlRequested !='/wp-login.php' && $urlRequested !='/wp-admin/admin-ajax.php'){
			$pcs=array();
		//identify malware
		//xss attempt
		if(wassupURI::is_xss($urlRequested)){
			$spam=3;
		//non-admin users trying to access root files, password or ids or upgrade script are up to no good
		}elseif(!$is_admin_login){
			$pcs=array();
			if(preg_match('#\.\./\.\./(etc/passwd|\.\./\.\./)#i',$urlRequested)>0){
				$spam=3;
			}elseif(preg_match('#[\[&\?/\-_](code|dir|document_root\]?|id|page|thisdir)\=([a-t]+tp\://.+|[/\\\\\'"\-&\#%]|0+[e\+\-\*x]|[a-z]:)#i',$urlRequested,$pcs)>0){
				if(!empty($pcs[2])) $spam=3;
				elseif($req_code==404) $spam=3;
			}elseif(preg_match('#\/wp\-admin.*[^0-9a-z_](install(\-helper)?|update(\-core)?|upgrade)\.php([^0-9a-z\-_]|$)#i',$urlRequested)>0){
				$spam=3;
			}
		}
		//visitors requesting non-existent server-side scripts are up to no good
		if($spam==0 && preg_match('#(?:(^\/\.[0-9a-z]{3,})|(\.(?:cgi|aspx?|jsp?))|([\*\,\'"\:\(\)$`].*)|(.+\=\-1)|(\/[a-z0-9\-_]+\.php[456]?))(?:[^0-9a-z]|$)#i',$urlRequested,$pcs)>0){
			if(!empty($pcs[3]) && preg_match('/([0-9\.\-=;]+)/',$urlRequested)>0){
				$spam=3;
			}elseif(empty($logged_user) && empty($cookieUser)){
				if(!empty($pcs[1]) || !empty($pcs[3]) || !empty($pcs[4])){
					$spam=3;
				}elseif(!empty($pcs[2])){
					if($req_code==404){
						$spam=3;
					}elseif(!empty($fileRequested) && !file_exists($fileRequested)){
						$req_code=404;
						$spam=3;
					}elseif(empty($recent_hit)){
						$spam=3;
					}
				}elseif(!empty($pcs[5])){
					if($req_code==404){
						$spam=3;
					}elseif($pcs[5]!='/index.php' && $pcs[5]!='/wp-login.php'){
						if(empty($recent_hit)){
							$spam=3;
						}elseif(strpos($urlRequested,'wp-admin/')>0){
							$spam=3;
						}elseif(!empty($fileRequested) && !file_exists($fileRequested)){
							$req_code=404;
							$spam=3;
						}elseif(strpos($urlRequested,'wp-includes/')>0){
							$spam=3;
						}
					}
				}
				if($spam==0 && preg_match('/[^a-z\-_](admin|administrator|base64|bin|code|config|cookie|delete|dll|document|drop|etc|eval|exec|exit|function|href|ini|insert|install|login|passw|root|script|select|setting|setup|table|update|upgrade|upload|wp\-|where|window)([^0-9a-z\.\-_]|$)/',$urlRequested)>0){
					if($req_code==404){
						$spam=3;
					}elseif(!empty($pcs[2])){
						$spam=3;
					}elseif(!empty($pcs[5])){
						if(strpos($urlRequested,'/wp-')>0) $spam=3;
						elseif($pcs[5]!='/index.php') $spam=3;
					}
				}
			} //elseif empty(logged_user)
		}
		//regular visitors trying to access admin area are up to no good
		if(empty($spam) && empty($logged_user) && empty($cookieUser)){
			$pcs=array();
			if(preg_match('#[^0-9a-z_]wp\-admin\/.+\.php\d?([^a-z0-9_]|$)#i',$urlRequested)>0){
				$spam=3;
			}elseif(preg_match('#\/wp\-(config|load|settings)\.php#',$urlRequested)>0){
			//regular visitor trying to access setup files is up to no good
				$spam=3;
			}elseif(preg_match('/[\\\.]{2,}/',$urlRequested)>0){
				$spam=3;
			}elseif(preg_match('#(?:[\/\\.]([^\/\\.]+\.php[456]?)|([^\/\\.]+\.(?:cgi|aspx?i|bat|dll|exe|ini|msi|sh)))(?:[^0-9a-z.\-_]|$)#i',$urlRequested,$pcs)>0) {
			//regular visitor requesting server-side scripts is likely malware
				if(!empty($pcs[2])) $spam=3;
				elseif(wIsAttack($urlRequested)) $spam=3;
			//regular visitor querying userid/author or other non-page item by id number is likely malware
			}elseif(preg_match('#[?&]([0-9a-z\-_]+)\=(\-)?\d+$#i',$urlRequested,$pcs)>0){
				if(!empty($pcs[2])){
					$spam=3;
				}elseif($req_code == 404){
					if(preg_match('#(code|dir|document_root|path|thisdir)#',$pcs[1])>0){
						$spam=3;
					}elseif(wIsAttack($urlRequested)){
						$spam=3;
					}
				}
			//regular visitor attempts to access "upload" page is likely malware
			}elseif(preg_match('#[\?&][0-9a-z\-_]*(page\=upload)(?:[^0-9a-z\-_]|$)#i',$urlRequested)>0){
				$spam=3;
			}elseif($req_code==404 && wIsAttack($urlRequested)){
				$spam=3;
			}
		} //end if empty logged_user
			//retroactively update recent visitor records as spam/malware
			if($spam == "3" && $spamresult == "0" && !empty($recent_hit) && empty($logged_user)){
				$wassup_dbtask[] = sprintf("UPDATE `$wassup_table` SET `spam`='3' WHERE `wassup_id`='%s' AND `spam`='0' AND `username`=''",$wassup_id);
			}
		} //end if hackercheck && spam==0
	//#15 Exclude for hack/malware attempts
	if($wassup_options->wassup_hack==1 || $spam!=3){
		//#Identify user-agent...
		$agent=(!empty($browser)?$browser:$spider);
		//identify agent with wGetBrowser
 		if(empty($agent) || stristr($agent,'unknown')!==false || $agent==$unknown_browser || $agent==$unknown_spider || stristr($agent,"mozilla")==$agent || stristr($agent,"netscape")==$agent || stristr($agent,"default")!==false){
			if(!empty($userAgent)){
				list($browser,$os)=wGetBrowser($userAgent);
				if(!empty($browser)) $agent=$browser;
				if ($wdebug_mode){
					if(is_admin() || headers_sent()){
						if(!empty($debug_output)){
							echo $debug_output;
							echo "\nwassupappend-debug#4";
							$debug_output="";
						}
						echo "\n".date('H:i:s.u').' wGetBrowser results: $browser='.$browser.'  $os='.$os;
					}else{
						$debug_output .= "\n".date('H:i:s.u').' wGetBrowser results: $browser='.$browser.'  $os='.$os;
					}
				}
			}
		}
		//# Some spiders, such as Yahoo and MSN, don't always give a unique useragent, so test against known hostnames/IP to identify these spiders
		$spider_hosts='/^((65\.55|207\.46)\.\d{3}.\d{1,3}|.*\.(crawl|yse)\.yahoo\.net|ycar\d+\.mobile\.[a-z0-9]{3}\.yahoo\.com|msnbot.*\.search\.msn\.com|crawl[0-9\-]+\.googlebot\.com|baiduspider[0-9\-]+\.crawl\.baidu\.com|\.domaintools\.com|(crawl(?:er)?|spider|robot)\-?\d*\..*)$/';
		//#Identify spiders from known spider domains
		if(empty($agent) || preg_match($spider_hosts,$hostname)>0 || stristr($agent,'unknown')!==false){
			list($spider,$spidertype,$feed) = wGetSpider($userAgent,$hostname,$browser);
			if($wdebug_mode){
				if(is_admin() || headers_sent()){
					if(!empty($debug_output)){
						echo $debug_output;
						echo "\nwassupappend-debug#5";
						$debug_output="";
					}
					echo "\n".date('H:i:s.u').' wGetSpider results: $spider='.$spider.'  $spidertype='.$spidertype.' $feed='.$feed;
				}else{
					$debug_output .= "\n".date('H:i:s.u').' wGetSpider results: $spider='.$spider.'  $spidertype='.$spidertype.' $feed='.$feed;
				}
			}
			//it's a browser
			if($spidertype == "B" && $urlRequested != "/robots.txt"){
				if (empty($browser)) $browser = $spider;
				$spider = "";
				$feed = "";
			//it's a script injection bot|spammer
			}elseif($spidertype == "H" || $spidertype == "S"){
				if ($spam == "0") $spam = 3;
			}
		}
		//#Identify spiders and feeds with wGetSpider...
		if(empty($spider) && empty($logged_user)){
			if(empty($userAgent) && empty($agent)){
				//no userAgent == spider
				$spider=$unknown_spider;
			}else{
				if(strlen($agent)<5 || empty($os) || preg_match('#\s?([a-z]+(?:bot|crawler|google|spider|reader|agent))[^a-z]#i',$userAgent)>0 || strstr($urlRequested,"robots.txt")!==FALSE || is_feed()){
					list($spider,$spidertype,$feed) = wGetSpider($userAgent,$hostname,$browser);
					if($wdebug_mode){
						if(is_admin() || headers_sent()){
							if(!empty($debug_output)){
								echo $debug_output;
								echo "\nwassupappend-debug#6";
								$debug_output="";
							}
							echo "\n".date('H:i:s.u').' wGetSpider results: $spider='.$spider.'  $spidertype='.$spidertype.' $feed='.$feed;
						}else{
							$debug_output .="\n".date('H:i:s.u').' wGetSpider results: $spider='.$spider.'  $spidertype='.$spidertype.' $feed='.$feed;
						}
					}
				}elseif(!empty($browser) && preg_match('#^(Google Chrome|Netscape|Mozilla|Mozilla Firefox)([0-9./ ]|$)#i',$browser)>0){
					//old browser name is likely spider
					list($spider,$spidertype,$feed)=wGetSpider($userAgent,$hostname,$browser);
				}elseif(preg_match('#(msie|firefox|chrome)[\/ ](\d+)#i',$userAgent,$pcs)>0){
					//obsolete browser is likely a spider
					if($pcs[2]<8 || ($pcs[2]<30 && $pcs[1]!="msie")){
						list($spider,$spidertype,$feed)=wGetSpider($userAgent,$hostname,$browser);
					}
				}
				//it's a browser
				if(!empty($spider)){
				if($spidertype == "B" && $urlRequested != "/robots.txt"){
					if(empty($browser)) $browser=$spider;
					$spider="";
					$feed="";
				}elseif($spidertype == "H" || $spidertype == "S"){
					if($spam == "0") $spam=3;
				}
				}
			}
			//if 1st request is "robots.txt" this is a bot
			//if empty user-agent, this is a bot
			if(empty($spider)){
				if(strstr($urlRequested,"robots.txt")!==FALSE && empty($recent_hit)) $spider=$unknown_spider;
				elseif(empty($browser) && empty($userAgent)) $spider=$unknown_spider;
			}
			//Finally, check for disguised spiders via excessive pageviews activity (threshold: 8+ views in < 16 secs)
			if($wpageviews >7 && empty($spider)){
				$pageurls=array();
				$visitstart=$recent_hit[7]->timestamp;
				if(($timenow - $recent_hit[$wpageviews-1]->timestamp < 16 && $wpageviews >9)|| $timenow - $visitstart < 12){
					$is_spider=true;
					$pageurls[]="$urlRequested";
					$n_404=0;
					//spider won't hit same page 2+ times
				foreach($recent_hit AS $w_pgview){
					if(stristr($w_pgview->urlrequested,"robots.txt")!==false){
						$is_spider=true;
						break;
					}elseif(in_array($w_pgview->urlrequested,$pageurls)){
						$is_spider=false;
						break;
					//spider won't have multiple 404's
					}elseif(preg_match('/^\[\d{3}\]\s/',$w_pgview->urlrequested)>0){
						if($n_404 >2){
							$is_spider=false;
							break;
						}
						$n_404=$n_404+1;
					}else{
						$pageurls[]=$w_pgview->urlrequested;
					}
				} //end foreach
				if($is_spider) $spider=$unknown_spider;
				} //end if timestamp
			} //end if wpageviews >7
		} //end if empty($spider)
		//identify spoofers of Google/Yahoo
		if(!empty($spider)){
			if(!empty($hostname) && preg_match('/^(googlebot|yahoo\!\sslurp)/i',$spider)>0 && preg_match('/\.(googlebot|yahoo)\./i',$hostname)==0){
				$spider= __("Spoofer bot","wassup");
			}
			//for late spider identification, update previous records
			if($wpageviews >1 && empty($recent_hit[0]->spider)){
				$wassup_dbtask[]=sprintf("UPDATE `$wassup_table` SET `spider`='%s' WHERE `wassup_id`='%s' AND `spider`='' ",esc_attr($spider),$recent_hit[0]->wassup_id);
			}
		}
	//#16 Spider exclusion control
	if ($wassup_options->wassup_spider == 1 || $spider == '') {
		$goodbot = false;
		//some valid spiders to exclude from spam/hack checks
		if($hostname!="" && !empty($spider) && preg_match('#^(googlebot|bingbot|msnbot|yahoo\!\sslurp)#i',$spider)>0 && preg_match('#\.(googlebot|live|msn|yahoo)\.(com|net)$#i',$hostname)>0){
			$goodbot = true;
		}
		//do spam exclusion controls, unless disabled in wassup_spamcheck
		//## Check for referrer spam...
		if($wassup_options->wassup_spamcheck == 1 && $spam == 0 && !$goodbot){
			$spamComment = New wassup_checkComment;
			//skip referrer check if from own blog
			if($wassup_options->wassup_refspam == 1 && !empty($referrer) && !$is_admin_login && stristr($referrer,$wpurl)!=$referrer && stristr($referrer,$blogurl)!=$referrer && $referrer!=$blogurl.$urlRequested){
				$refdomain=wassupURI::get_urldomain($referrer);
				$sitedomain=wassupURI::get_urldomain();
			//New in v1.9.4: skip referrer check if from own domain
			if($refdomain != $sitedomain || strpos($referrer,'=')!==false){
				//New in v1.9.4: skip referrer check if on whitelist
			if(empty($wassup_options->refspam_whitelist) || preg_match('#(?:^|\s*,)\s*('.preg_quote($refdomain).')\s*(?:,|$)#',$wassup_options->refspam_whitelist)==0){
				//check if referrer is a previous comment spammer
				if($spamComment->isRefSpam($referrer)>0){
					$spam=2;
				}else{
					//check for known referer spammer
					$isspam=wGetSpamRef($referrer,$hostname);
					if ($isspam) $spam = 2;
				}
			} //end if refspam_whitelist
			} //end if refdomain
			} //end if wassup_refspam
		//## Check for comment spammer...
		// No spam check on spiders unless there is a comment or forum page request...
		if ($spam == 0 && (empty($spider) || stristr($urlRequested,"comment")!== FALSE || stristr($urlRequested,"forum")!== FALSE  || !empty($comment_user))) {
			//check for previous spammer detected by anti-spam plugin
			$spammerIP = $spamComment->isSpammer($IP);
			if($spammerIP > 0) $spam=1;
			//set as spam if both URL and referrer are "comment" and browser is obsolete or Opera
			if ($spam== 0 && $wassup_options->wassup_spam==1 && stristr($urlRequested,"comment")!== FALSE && stristr($referrer,"#comment")!==FALSE && (stristr($browser,"opera")!==FALSE || preg_match('/^(AOL|Netscape|IE)\s[1-6]$/',$browser)>0)) {
				$spam=1;
			}
			//#lastly check for comment spammers using Akismet API
			if ($spam == 0 && $wassup_options->wassup_spam == 1 && stristr($urlRequested,"comment")!== FALSE && stristr($urlRequested,"/comments/feed/")== FALSE && !$is_media) {
				$akismet_key = get_option('wordpress_api_key');
				$akismet_class = WASSUPDIR.'/lib/akismet.class.php';
				if (!empty($akismet_key) && is_readable($akismet_class)) {
					include_once($akismet_class);
					// load array with comment data
					$comment_user_email = (!empty($_COOKIE['comment_author_email_'.COOKIEHASH])? utf8_encode($_COOKIE['comment_author_email_'.COOKIEHASH]):"");
					$comment_user_url = (!empty($_COOKIE['comment_author_url_'.COOKIEHASH])? utf8_encode($_COOKIE['comment_author_url_'.COOKIEHASH]):"");
					$Acomment = array(
					'author' => $comment_user,
					'email' => $comment_user_email,
					'website' => $comment_user_url,
					'body' => (isset($_POST["comment"])? $_POST["comment"]:""),
					'permalink' => $urlRequested,
					'user_ip' => $ipAddress,
					'user_agent' => $userAgent);
					//'Akismet' class renamed 'wassup_Akismet' due to a conflict with Akismet 3.0+ @since v1.9
					$akismet=new wassup_Akismet($wpurl,$akismet_key,$Acomment);
					// Check if it's spam
					if($akismet->isSpam() && !$akismet->errorsExist()) $spam=1;
				} //end if !empty(akismet_key)
			} //end if wassup_spam
		} //end if spam == 0
			//retroactively update record for spam/malware attempt
			if(!empty($spam) && $spamresult == "0" && !empty($recent_hit)){
				//queue the update...
				$wassup_dbtask[] = sprintf("UPDATE `$wassup_table` SET `spam`='%d' WHERE `wassup_id`='%s' AND `spam`='0' ",$spam,$wassup_id);
			}
		} //end if wassup_spamcheck == 1
	//#17 Exclusion control for spam and malware
	if ($spam == 0 || ($wassup_options->wassup_spam == 1 && $spam == 1) || ($wassup_options->wassup_refspam == 1 && $spam == 2) || ($wassup_options->wassup_hack == 1 && $spam == 3)) {
	//#18 exclusion for wp-content/plugins (after 404/hackcheck)
	if (stristr($urlRequested,"/".PLUGINDIR)===FALSE || stristr($urlRequested,"forum") !== FALSE || $spam==3) {
		//## More user/referrer details for recording
		//get language/locale
		if(empty($language) && !empty($recent_hit[0]->language)) $language=$recent_hit[0]->language;
		if($wdebug_mode){
			if(headers_sent()) echo "\n  language=$language";
			else $debug_output .= "\n  language=$language";
		}
		if(preg_match('/\.[a-z]{2,3}$/i',$hostname) >0 || preg_match('/[a-z\-_]+\.[a-z]{2,3}[^a-z]/i',$referrer) >0 || strlen($language)>2){
			//get language/locale info from hostname or referrer data
			$language=wGetLocale($language,$hostname,$referrer);
		}
		if($wdebug_mode){
			if(headers_sent()) echo "\n...language=$language (after geoip/wgetlocale)";
			else $debug_output .= " ...language=$language (after geoip/wgetlocale)";
		}
		// get search engine and search keywords from referrer
		$searchengine="";
		$search_phrase="";
		$searchpage="";
		$searchlocale="";
		//don't check own blog for search engine data
		if (!empty($referrer) && $spam == "0" && stristr($referrer,$blogurl)!=$referrer && !$wdebug_mode) {
			$ref=(is_string($referrer)?$referrer:mb_convert_encoding(strip_tags($_SERVER['HTTP_REFERER']),"HTML-ENTITIES","auto"));
			//test for Google secure search and use generic "_notprovided_" for missing keyword @since v1.9
			//TODO: Yahoo now has secure searching since 4/2014
			$pcs=array();
			if (preg_match('#^https\://(www\.google(?:\.com?)?\.([a-z]{2,3}))/(url\?(?:.+[^q]+q=([^&]*)(?:&|$)))?#',$ref,$pcs)>0){
				$searchdomain=$pcs[1];
				$searchengine="Google";
				if($pcs[2]!="com" && $pcs[2]!="co"){
					$searchlocale=$pcs[2];
					$searchengine .=" ".strtoupper($searchlocale);
				}
				//get the query keywords - will always be empty, until Google changes its policy
				if(empty($pcs[4])) $search_phrase="_notprovided_";
				else $search_phrase=$pcs[4];
				if(!empty($pcs[3]) && preg_match('/&cd\=(\d+)(?:&|$)/',$ref,$pcs2)>0){
					$searchpage=$pcs2[1];
				}
				unset($pcs,$pcs2);
			//get GET type search results, ex: search=x
			}elseif (strpos($ref,'=')!==false) {
				$se=wGetSE($ref);
				if(is_array($se) && !empty($se['searchengine'])){
					$searchengine=$se['searchengine'];
					$search_phrase=$se['keywords'];
					$searchpage=$se['page'];
					$searchlang=$se['language'];
					$searchlocale=$se['locale'];
				}
				if ($search_phrase != '') {
					$sedomain = parse_url($referrer);
					$searchdomain = $sedomain['host'];
				}
			}
			//get other search results type, ex: search/x
			if ($search_phrase == '') {
				$se=wSeReferer($ref);
				if (!empty($se['Query']))  {
					$search_phrase = $se['Query'];
					$searchpage = $se['Pos'];
					$searchdomain = $se['Se'];
				//check for empty secure searches
				} elseif(strpos($ref,'https://www.bing.')!==false || strpos($ref,'https://www.yahoo.')!==false || strpos($ref,'https://www.google.')!==false) {
					$search_phrase = "_notprovided_";
					$searchpage = 1;
					$searchdomain = substr($ref,8);
				} else {
					$searchengine = "";
				}
			}
			if ($search_phrase != '')  {
			if (!empty($searchengine)) {
				if (stristr($searchengine,"images")===FALSE && stristr($referrer,'&imgurl=')===FALSE) {
				// 2011-04-18: "page" parameter is now used on referrer string for Google Images et al.
				if (preg_match('#page[=/](\d+)#i',$referrer,$pcs)>0) {
					if ($searchpage != $pcs[1]) {
						$searchpage = $pcs[1];
					}
				} else {
				// NOTE: Position retrieved in Google Images is the position number of image NOT page rank position like web search
					$searchpage=(int)($searchpage/10)+1;
				}
				}
				//append country code to search engine name
				if (preg_match('/(\.([a-z]{2})$|^([a-z]{2})\.)/i',$searchdomain,$match)) {
					$searchcountry="";
					if(!empty($match[2])){
						$searchcountry=$match[2];
					}elseif(!empty($match[3])){
						$searchcountry=$match[3];
					}
					if(!empty($searchcountry) && $searchcountry!="us"){
						//v1.9.3.1 bugfix: avoid duplicate country code in searchengine name
						if(stristr($searchengine," $searchcountry")===false) $searchengine .=" ".strtoupper($searchcountry);
						if($language == "us" || empty($language) || $language=="en"){
							//make tld consistent with language
							if($searchcountry=="uk") $searchcountry="gb";
							elseif($searchcountry=="su") $searchcountry="ru";
							$language=$searchcountry;
						}
					}
				}
			} else {
				$searchengine = $searchdomain;
			}
			//use search engine country code as locale
			$searchlocale = trim($searchlocale);
			if(!empty($searchlocale)){
				if($language == "us" || empty($language) || $language=="en"){
					$language=$searchlocale;
				}
			}
		} //end if search_phrase
		} //end if (!empty($referrer)
		if ($searchpage == "") $searchpage = 0;
		//Prepare to save to table...
		//make sure language is 2-digits and lowercase
		$pcs=array();
		if(!empty($language) && preg_match('/^(?:[a-z]{2}\-)?([a-z]{2})(?:$|,)/i',$language,$pcs)>0){
			$language=strtolower($pcs[1]);
		}else{
			$language="";
		}
		//tag 404 requests in table
		if($req_code==404) $urlRequested="[404] ".$_SERVER['REQUEST_URI'];
		// #Record visit in wassup tables...
		// #create record to add to wassup tables...
		$wassup_rec = array('wassup_id'=>$wassup_id,
				'timestamp'=>$timenow,
				'ip'=>$ipAddress,
				'hostname'=>$hostname,
				'urlrequested'=>wassupDb::xescape($urlRequested),
				'agent'=>wassupDb::xescape($userAgent),
				'referrer'=>wassupDb::xescape($referrer),
				'search'=>$search_phrase,
				'searchpage'=>$searchpage,
				'searchengine'=>$searchengine,
				'os'=>$os,
				'browser'=>$browser,
				'language'=>$language,
				'screen_res'=>$wscreen_res,
				'spider'=>$spider,
				'feed'=>$feed,
				'username'=>$logged_user,
				'comment_author'=>$comment_user,
				'spam'=>$spam,
				'url_wpid'=>$article_id,
				'subsite_id'=>$subsite_id,
				);
		// Insert the visit record into wassup temp table
		$temp_recid = wassup_insert_rec($wassup_tmp_table,$wassup_rec);
		//Omit link prefetch and preview requests from main table but add them to wassup_tmp for visitor counts only  @since v1.9
		if((!isset($_SERVER['HTTP_X_MOZ']) || (strtolower($_SERVER['HTTP_X_MOZ'])!='prefetch')) && (!isset($_SERVER["HTTP_X_PURPOSE"]) || strtolower($_SERVER['HTTP_X_PURPOSE'])!='preview')){
			$error_msg="";
			// Insert the visit record into wassup table
			$wassup_recid=wassup_insert_rec($wassup_table,$wassup_rec);
			if(!empty($wassup_recid) && is_wp_error($wassup_recid)){
				$errno=$wassup_recid->get_error_code();
				if(!empty($errno)) $error_msg="\nError saving record: $errno: ".$wassup_recid->get_error_message()."\n";

				$wassup_recid=false;
			}elseif(empty($wassup_recid) || !is_numeric($wassup_recid)){
				if(!empty($wpdb->insert_id)){
					$wassup_recid=$wpdb->insert_id;
				}elseif(!empty($wassup_options->delayed_insert) && is_numeric($temp_recid)){
					$wassup_recid=$temp_recid; //positive val for delayed insert
				}else{
					$error_msg="an unknown error occurred during save";
				}
			}
			if($wdebug_mode){
				if(headers_sent()){
					if(!empty($wassup_recid)){
						echo "\nWassUp record data:";
						print_r($wassup_rec);
						echo "*** Visit recorded ***";
					}else{
						echo "\n *** Visit was NOT recorded! ".$error_msg." *** -->";
					}
				}else{
					if(!empty($wassup_recid)){
						$debug_output .= "\n WassUp record data:\n";
						$debug_output .=print_r($wassup_rec,true); //debug
						$debug_output .= "\n *** Visit recorded ***"; //debug
					}else{
						$debug_output .= "\n *** Visit was NOT recorded ".$error_msg." ***"; //debug
					}
				}
			}
		} //end if prefetch
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #18 Excluded by: wp-content/plugins (after 404)";
		else $debug_output .="\n #18 Excluded by: wp-content/plugins (after 404)";
	} //end if !wp-content/plugins
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #17 Excluded by: wassup_spam";
		else $debug_output .="\n #17 Excluded by: wassup_spam";
	} //end if $spam == 0
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #16 Excluded by: wassup_spider";
		else $debug_output .="\n #16 Excluded by: wassup_spider";
	} //end if wassup_spider
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #15 Excluded by: wassup_hack";
		else $debug_output .="\n #15 Excluded by: wassup_hack";
	} //end if wassup_hack
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #14 Excluded by: is_404";
		else $debug_output .="\n #14 Excluded by: is_404";
	} //end if !is_404
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #13 Excluded by: !wp-admin (ajax)";
		else $debug_output .="\n #13 Excluded by: !wp_admin (ajax)";
	} //end if !wp_admin (ajax) && recent_hit
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #12 Excluded by: dup_urlrequest";
		else $debug_output .="\n #12 Excluded by: dup_urlrequest";
	} //end if dup_urlrequest == 0
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #11 Excluded by: wassup_attack";
		else $debug_output .="\n #11 Excluded by: wassup_attack";
	} //end if wassup_attack
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #10 Excluded by: wassup_loggedin";
		else $debug_output .="\n #10 Excluded by: wassup_loggedin";
	} //end if wassup_loggedin
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #9 Excluded by: wp-content/themes";
		else $debug_output .="\n #9 Excluded by: wp-content/themes";
	} //end if !themes
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #8 Excluded by: wp-content/plugins (or hostname wildcard)";
		else $debug_output .="\n #8 Excluded by: wp-content/plugins (or hostname wildcard)";
	} //end if !plugins
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #7 Excluded by: exclude_host (or IP wilcard)";
		else $debug_output .="\n #7 Excluded by: exclude_host (or IP wildcard)";
	} //end if wassup_exclude_host
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #6 Excluded by: wassup_exclude";
		else $debug_output .="\n #6 Excluded by: wassup_exclude";
	} //end if wassup_exclude
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #5 Excluded by: exclude_url";
		else $debug_output .="\n #5 Excluded by: exclude_url";
	} //end if wassup_exclude_url
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #4 Excluded by: exclude_user";
		else $debug_output .="\n #4 Excluded by: exclude_user";
	} //end if wassup_exclude_user
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #3 Excluded by: is_admin";
		else $debug_output .="\n #3 Excluded by: is_admin";
	} //end if !is_admin
	} //end if wp-cron.php?doing_wp_cron===FALSE //#2
	}elseif($wdebug_mode){
		if(headers_sent()) echo "\n #1 Excluded by: is_admin_login";
		else $debug_output .="\n #1 Excluded by: is_admin_login";
	} //end if !is_admin_login

	//add excluded visitors to wassup_tmp table for accurate online counts @since v1.9
	if(empty($temp_recid) && $dup_urlrequest==0){
		$in_temp=0;
		//check that visitor is not already in temp
		if(!empty($logged_user)){
			//check for username
			$result=$wpdb->get_var(sprintf("SELECT COUNT(`wassup_id`) AS in_temp FROM `$wassup_tmp_table` WHERE `wassup_id`='%s' AND `timestamp`>'%d' AND `username`!=''",$wassup_id,$timenow - 540));
		}else{
			$result=$wpdb->get_var(sprintf("SELECT COUNT(`wassup_id`) AS in_temp FROM `$wassup_tmp_table` WHERE `wassup_id`='%s' AND `timestamp`>'%d' AND `spider`=''",$wassup_id,$timenow-160));
		}
		if(is_numeric($result)) $in_temp=(int)$result;
		if($wdebug_mode){
			if(headers_sent()) echo "\nin_temp=".$result;
			else $debug_output .="\nin_temp=".$result;
		}
		//add new temp record
		if($in_temp==0){
		if(empty($wassup_rec)){
			$pcs=array();
			if(!empty($language) && preg_match('/^(?:[a-z]{2}\-)?([a-z]{2})(?:$|,)/i',$language,$pcs)>0){
				$language=strtolower($pcs[1]);
			}else{
				$language="";
			}
			//tag 404 requests in table
			if($req_code=="404"){
				$urlRequested="[404] ".$_SERVER['REQUEST_URI'];
			}
			// #Record visit in wassup tables...
			$wassup_rec = array('wassup_id'=>$wassup_id,
				'timestamp'=>$timenow,
				'ip'=>$ipAddress,
				'hostname'=>$hostname,
				'urlrequested'=>wassupDb::xescape($urlRequested),
				'agent'=>wassupDb::xescape($userAgent),
				'referrer'=>wassupDb::xescape($referrer),
				'search'=>$search_phrase,
				'searchpage'=>$searchpage,
				'searchengine'=>$searchengine,
				'os'=>$os,
				'browser'=>$browser,
				'language'=>$language,
				'screen_res'=>$wscreen_res,
				'spider'=>$spider,
				'feed'=>$feed,
				'username'=>$logged_user,
				'comment_author'=>$comment_user,
				'spam'=>$spam,
				'url_wpid'=>$article_id,
				'subsite_id'=>$subsite_id,
				);
			}
			//insert the record into the wassup_tmp table
			$temp_recid=wassup_insert_rec($wassup_tmp_table,$wassup_rec);
		}
	} //end if temp_recid
	$timestamp=$timenow;
	$now=time();
	//## Automatic database monitoring and cleanup tasks...check every few visits
	//# Notify admin if alert is set and wassup table > alert
	if((int)$timestamp%139 == 0){
	if($wassup_options->wassup_remind_flag == 1 && (int)$wassup_options->wassup_remind_mb>0){
		$tusage=0;
		$fstatus = wassupDb::table_status($wassup_table);
		$data_lenght = 0;
		if (!empty($fstatus) && is_object($fstatus)) {
			//db size = db records + db index
			$data_lenght=$fstatus->Data_length+$fstatus->Index_length;
			$tusage = ($data_lenght/1024/1024);
		}
		if($tusage >0 && $tusage > $wassup_options->wassup_remind_mb){
			if(!empty($network_settings['wassup_table']) && $network_settings['wassup_table']==$wassup_table){
				$recipient = get_site_option('admin_email');
			}else{
				$recipient = get_bloginfo('admin_email');
			}
			$sender = 'From: '.get_bloginfo('name').' <wassup_noreply@'.parse_url($blogurl,PHP_URL_HOST).'>';
                        $subject=sprintf(__("%s WassUp Plugin table has reached maximum size!","wassup"),'['.__("ALERT","wassup").']');
                        $message = __('Hi','wassup').",\n".__('you have received this email because your WassUp Database table at your Wordpress blog','wassup')." ($wpurl) ".__('has reached the maximum value set in the options menu','wassup')." (".$wassup_options->wassup_remind_mb." Mb).\n\n";
                        $message .= __('This is only a reminder, please take the actions you want in the WassUp options menu','wassup')." (".admin_url("admin.php?page=wassup-options").")\n\n".__('This alert now will be removed and you will be able to set a new one','wassup').".\n\n";
                        $message .= __('Thank you for using WassUp plugin. Check if there is a new version available here:','wassup')." http://wordpress.org/extend/plugins/wassup/\n\n".__('Have a nice day!','wassup')."\n";
                        wp_mail($recipient, $subject, $message, $sender);
                        $wassup_options->wassup_remind_flag = 2;
                        $wassup_options->saveSettings();
		}
	} //if wassup_remind_flag
	} //if timestamp%139

	//# schedule purge of temporary records - also done hourly in wp-cron
	if(((int)$timestamp)%11 == 0){
		$starttime=0;
		if(version_compare($wp_version,'3.0','>')) $starttime=wp_next_scheduled('wassup_scheduled_cleanup');
		if(empty($starttime) || ($starttime - $now) >660){
			//keep logged-in user records in temp for up to 10 minutes, anonymous user records for up to 3 minutes, and spider records for only 1 minute @since v1.9
			$wassup_dbtask[]=sprintf("DELETE FROM `$wassup_tmp_table` WHERE `timestamp`<'%d' OR (`timestamp`<'%d' AND `username`='') OR (`timestamp`<'%d' AND `spider`!='')",(int)($timestamp - 10*60),(int)($timestamp - 3*60),(int)($timestamp - 60));
			if(((int)$timestamp)%5 == 0){
				//Purge expired cache data from wassup_meta
				$result=$wpdb->query(sprintf("DELETE FROM `$wassup_meta_table` WHERE `meta_expire`>'0' AND `meta_expire`<'%d'",$now - 3600));
			}
		}
	}
	//# schedule table optimization ...check every few visits
	if(((int)$timestamp)%141 == 0 && (!is_multisite() || is_main_site() || !$wassup_options->network_activated_plugin())){
		//Optimize table when optimize timestamp is older than current time
		if(!empty($wassup_options->wassup_optimize) && is_numeric($wassup_options->wassup_optimize) && $now >(int)$wassup_options->wassup_optimize){
			$optimize_sql=sprintf("OPTIMIZE TABLE `%s`",$wassup_table);
			if(version_compare($wp_version,'3.0','<')){
				$wassup_dbtask[]=$optimize_sql;
			}else{
				$args=array('dbtasks'=>array("$optimize_sql"));
				wp_schedule_single_event(time()+620,'wassup_scheduled_optimize',$args);
			}
			//save new optimize timestamp
			$wassup_options->wassup_optimize = $wassup_options->defaultSettings('wassup_optimize');
			$wassup_options->saveSettings();
		}
	}
	//# Lastly, perform scheduled database tasks
	if(count($wassup_dbtask)>0){
		$args=array('dbtasks'=>$wassup_dbtask);
		if(is_admin() || version_compare($wp_version,'3.0','<')){
			wassupDb::scheduled_dbtask($args);
		}else{
			wp_schedule_single_event(time()+40,'wassup_scheduled_dbtasks',$args);
		}
		if($wdebug_mode){
			if(headers_sent()){
				echo "\nWassup scheduled tasks:";
				print_r($wassup_dbtask);
			}
		}
	}
	if($wdebug_mode){ //close comment tag to hide debug data from visitors
		if(headers_sent()){
			echo "\n--> \n";
		}else{
			$debug_output .= "<br />\n--> \n";
			//add debug output to wp_footer output - @TODO
			$expire=time()+180;
			$wassup_key=wassup_clientIP($_SERVER['REMOTE_ADDR']);
			wassupDb::update_wassupmeta($wassup_key,'_debug_output',$expire,$debug_output);
		}
		//restore normal mode
		@ini_set('display_errors',$mode_reset);
	} //end if wdebug_mode
} //end wassupAppend

/** Insert a new record into Wassup table */
function wassup_insert_rec($wTable,$wassup_rec,$delayed=false){
	global $wpdb,$wassup_options;

	$wassup_table=$wassup_options->wassup_table;
	$wassup_tmp_table=$wassup_table."_tmp";
	$insert_id=false;
	$delayed=false;
	//check that wassup_rec is valid associative array
	if(is_array($wassup_rec)){
		if(($wTable==$wassup_table || $wTable==$wassup_tmp_table) && !empty($wassup_rec['wassup_id'])){
			//check for 'subsite_id' column in single site setups and remove if missing
			if(!is_multisite()){
				$col=$wpdb->get_var(sprintf("SHOW COLUMNS FROM `%s` LIKE 'subsite_id'",$wassup_table));
				if(empty($col)) unset($wassup_rec['subsite_id']);
			}
			//insert temp record
			if($wTable==$wassup_tmp_table){
				//if plugin update was interrupted, temp table could be missing
				if(!wassupDb::table_exists($wassup_tmp_table)){
					$result=$wpdb->query(sprintf("CREATE TABLE `%s` LIKE `$wassup_table`",$wassup_tmp_table));
				}
				$insert_id=wassupDb::table_insert($wassup_tmp_table,$wassup_rec);
			}else{
				if(!empty($wassup_options->delayed_insert)) $delayed=true;
				$insert_id=wassupDb::table_insert($wTable,$wassup_rec,$delayed);
				//try regular insert when delayed insert fails on MySql server
				if(!empty($insert_id) && is_wp_error($insert_id) && $delayed){
					$insert_id=wassupDb::table_insert($wTable,$wassup_rec,false);
					//always bypass delayed insert
					$wassup_options->delayed_insert=0;
					$wassup_options->saveSettings();
				}
			}
		}elseif(strpos($wTable,'wassup')!==false){
			$insert_id=wassupDb::table_insert($wTable,$wassup_rec);
		}
	}
	return $insert_id;
}//end wassup_insert_rec
/**
 * Assign an id for current visitor session from a combination of date/hour/min/ip/loggeduser/useragent/hostname.
 * This is not unique so that multiple visits from the same ip/userAgent within a 30 minute-period, can be tracked, even when session/cookies is disabled.
 * @since v1.9
 * @param args (array)
 * @return string
 */
function wassup_get_sessionid($args=array()){
	global $wpdb,$current_user,$wassup_options;
	if(!empty($args) && is_array($args)) extract($args);
	if(empty($timestamp)) $timestamp=current_time('timestamp');
	if(empty($ipAddress)) $ipAddress=$_SERVER['REMOTE_ADDR'];
	if(empty($IP)) $IP=wassup_clientIP($ipAddress);
	if(empty($subsite_id)) $subsite_id=(!empty($GLOBALS['current_blog']->blog_id)?$GLOBALS['current_blog']->blog_id:0);
	if(empty($sessionhash)) $sessionhash=$wassup_options->whash;
	$session_id="";
	//check for a recent visit from this ip address when no cookie
	if(!isset($_COOKIE['wassup'.$sessionhash])){
		$wassup_table=$wassup_options->wassup_table;
		$wassup_tmp_table=$wassup_table."_tmp";
		$multisite_whereis="";
		if($wassup_options->network_activated_plugin() && !empty($subsite_id)){
			$multisite_whereis=sprintf(" AND `subsite_id`=%d",subsite_id);
		}
		if(!isset($userAgent)){
			$userAgent=(isset($_SERVER['HTTP_USER_AGENT'])?rtrim($_SERVER['HTTP_USER_AGENT']):'');
			if(strlen($userAgent) >255){
				$userAgent=substr(str_replace(array('  ','%20%20','++'),array(' ','%20','+'),$userAgent),0,255);
			}
		}
		//reuse wassup_id from very recent hit from this ip (within 45 secs)
		$result=$wpdb->get_results(sprintf("SELECT SQL_NO_CACHE `wassup_id`, `ip`, `timestamp`, `username`, `spam`, `agent`, `referrer` FROM `$wassup_tmp_table` WHERE `ip`='%s' AND `timestamp` >'%d' AND `agent`='%s' %s ORDER BY `timestamp` LIMIT 1",esc_attr($IP),$timestamp-45,esc_attr($userAgent),$multisite_whereis));
		if (!empty($result) && !is_wp_error($result)){
			$session_id=$result[0]->wassup_id;
		}
	}
	if(empty($session_id)){
		if(!isset($logged_user) && !empty($current_user->user_login)){
			$logged_user=$current_user->user_login;
		}else{
			$logged_user="";
		}
		if(empty($hostname)) $hostname=wassup_get_hostname($IP);
		$tempUA="";
		if(isset($_SERVER['HTTP_USER_AGENT'])){
			$tempUA=str_replace(array(' ','	','http://','www.','%20','+','%','&','#','.','$',';',':','-','>','<','`','*','/','\\','"','\'','!','@','=','_',')','('),'',preg_replace('/(&#0?37;|&amp;#0?37;|&#0?38;#0?37;|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['HTTP_USER_AGENT']));
		}
		$templen=strlen($tempUA);
		if($templen==0) $tempUA="UnknownSpider";
		elseif($templen<10) $tempUA .=$templen."isTooSmall";
		if(!empty($logged_user)){
			$sessiontime=intval(gmdate('i',$timestamp)/90);
			$temp_id=sprintf("%-040.40s",gmdate('Ymd',$timestamp).$sessiontime.str_replace(array('.',':','-'),'',substr(strrev($ipAddress),2).strrev($logged_user).strrev($tempUA).$sessiontime.gmdate('dmY',$timestamp).strrev($hostname)).$templen.rand());
		}else{
			$sessiontime=intval(gmdate('i',$timestamp)/30);
			$temp_id=sprintf("%-040.40s",gmdate('YmdH',$timestamp).$sessiontime.str_replace(array('.',':','-'),'',substr(strrev($ipAddress),2).strrev($tempUA).$sessiontime.gmdate('HdmY',$timestamp).strrev($hostname)).$templen.rand());
		}
		//#assign new wassup id from "temp_id"
		$session_id= (int)$subsite_id.'b_'.md5($temp_id);
	}
	return $session_id;
} //end wassup_get_sessionid

/**
 * Retrieve a hash value to assign to a session cookie
 * - replaces 'COOKIEHASH' which breaks up a continuous session with user login/reauthorization
 */
function wassup_get_sessionhash($ip=0){
	global $wassup_options;
	if(empty($ip)) $ip=wassup_clientIP($_SERVER['REMOTE_ADDR']);
	$sessionhash=wassupDb::get_wassupmeta($ip,'_sessionhash');
	if(empty($sessionhash)){
		$sessionhash=$wassup_options->whash;
		//keep this hash value for 2 hours so still works even if wassup_options (and whash) is reset
		$expire=time()+2*3600;
		$cacheid=wassupDb::update_wassupmeta($ip,'_sessionhash',$sessionhash,$expire);
	}
	return $sessionhash;
}

/**
 * search url string for key=value pairs and assign to assoc array
 * note: php's "parse_str" and Wordpress' "wp_parse_args" does the same thing
 * @access public
 * @return array
 */
function wGetQueryVars($urlstring){
	$qvar = array();
	if (!empty($urlstring)) {
		$wtab=parse_url($urlstring);
		if(key_exists("query",$wtab)){
			//use 'parse_str' when possible
			parse_str($wtab["query"],$qvar);
		}else{	//for partial urls
			//remove any anchor links from end of url
			if(preg_match('/([^#]+)#.*/',$urlstring,$pcs)>0) $query=$pcs[1];
			else $query=$urlstring;
			$i=0;
			while($query){
				$pcs=array();
				//exclude 1st part of url up to and including the "?"
			if(preg_match('/(?:[^?]*\?)?([^=&]+)(=[^&]+)?/',$query,$pcs)>0){
				$name=$pcs[1];
				if(empty($pcs[2])) $qvar[$name]=true;
				else $qvar[$name]=substr($pcs[2],1);
				$newquery=substr($query,strlen($pcs[0])+1);
				$query=$newquery;
			}else{
				$name=$query;
				$qvar[$name]=true;
				$query="";
			}
			$i++;
			if($i >=20) break; //bad query, end loop
			} //end while
		}
	}
	return $qvar;
} //end wGetQueryVars

/**
 * Find search engine referrals from lesser-known search engines or from engines that use a url-format (versus GET) for search query
 * @param boolean
 * @return array
 */
function wSeReferer($ref = false) {
	$SeReferer = (is_string($ref) ? $ref : mb_convert_encoding(strip_tags($_SERVER['HTTP_REFERER']), "HTML-ENTITIES", "auto"));
	if ($SeReferer == "") {	//nothing to do;
		return false;
	}
	//Check against Google, Yahoo, MSN, Ask and others
	if(preg_match('#^https?://([^/]+).*[&\?](prev|q|p|s|search|searchfor|as_q|as_epq|query|keywords|term|encquery)=([^&]+)#i',$SeReferer,$pcs) > 0){
		$SeDomain = trim(strtolower($pcs[1]));
		if ($pcs[2] == "encquery") {
			$SeQuery = " *".__("encrypted search","wassup")."* ";
		} else {
			$SeQuery = $pcs[3];
		}

	//Check for search engines that show query as a url with 'search' and keywords in path (ex: Dogpile.com)
	}elseif(preg_match('#^https?://([^/]+).*/(results|search)/web/([^/]+)/(\d+)?#i',$SeReferer,$pcs)>0){
		$SeDomain = trim(strtolower($pcs[1]));
		$SeQuery = $pcs[3];
		if (!empty($pcs[4])) {
			$SePos=(int)$pcs[4];
		}
	//Check for search engines that show query as a url with 'search' in domain and keywords in path (ex: twitnitsearch.appspot.com)
	}elseif(preg_match('#^https?://([a-z0-9_\-\.]*(search)(?:[a-z0-9_\-\.]*\.(?:[a-z]{2,4})))/([^/]+)(?:[a-z_\-=/]+)?/(\d+)?#i',$SeReferer."/",$pcs)>0){
		$SeDomain = trim(strtolower($pcs[1]));
		$SeQuery = $pcs[3];
		if (!empty($pcs[4])) {
			$SePos=(int)$pcs[4];
		}
	}
	unset ($pcs);
	//-- We have a query
	if(isset($SeQuery)){
		// Multiple URLDecode Trick to fix DogPile %XXXX Encodes
		if (strstr($SeQuery,'%')) {
			$OldQ=$SeQuery;
			$SeQuery=urldecode($SeQuery);
			while($SeQuery != $OldQ){
				$OldQ=$SeQuery;
				$SeQuery=urldecode($SeQuery);
			}
		}
		if (!isset($SePos)) {
			if(preg_match('#[&\?](start|startpage|b|cd|first|stq|pi|page)[=/](\d+)#i',$SeReferer,$pcs)) {
				$SePos = $pcs[2];
			} else {
				$SePos = 1;
			}
			unset ($pcs);
		}
		$searchdata=array("Se"=>$SeDomain, "Query"=>$SeQuery,
				  "Pos"=>$SePos, "Referer"=>$SeReferer);
	} else {
		$searchdata=false;
	}
	return $searchdata;
} //end function wSeReferrer

/**
 * Parse referrer string for match from a list of known search engines and, if found, return an array containing engine name, search keywords, results page#, and language.
 * Notes:
 * -To distinguish "images", "mobile", or other types of searches that uses subdomains, the "images" and "mobile" subdomains must be entered above the main search domain in the search engines array list.
 * - New or obscure search engines, search engines with a URL-formatted referrer string, and any search engine not listed here, are identified by another function, "wSeReferer()".
 *
 * @param string
 * @return array
 */
function wGetSE($referrer = null){
	$key = null;
	$search_phrase="";
	$searchpage="";
	$searchengine="";
	$searchlang="";
	$selocale="";
	$blogurl = preg_replace('#(https?\://)?(www\.)?#','',strtolower(get_option('home')));
	//list of well known search engines.
	//  Structure: "SE Name|SE Domain(partial+unique)|query_key|page_key|language_key|locale|charset|"
	$lines = array(
		"360search|so.360.com|q|||cn|utf8|",
		"360search|www.so.com|q|||cn|utf8|",
		"Abacho|.abacho.|q|||||",
		"ABC Sok|verden.abcsok.no|q|||no||",
		"Aguea|chercherfr.aguea.com|q|||fr||",
		"Alexa|.alexa.com|q|||||",
		"Alice Adsl|rechercher.aliceadsl.fr|qs|||fr||",
		"Allesklar|www.allesklar.|words|||de||",
		"AllTheWeb|.alltheweb.com|q|||||",
		"All.by|.all.by|query|||||",
		"Altavista|.altavista.|q|||||",
		"Altavista|.altavista.|aqa|||||", //advanced query
		"Apollo Lv|apollo.lv|q|||lv||",
		"Apollo7|apollo7.de|query|||de||",
		"AOL|recherche.aol.|query|||||",
		"AOL|search.aol.|query|||||",
		"AOL|.aol.|query|||||",
		"AOL|.aol.|q|||||",
		"Aport|sm.aport.ru|r|||ru||",
		"Arama|.arama.com|q|||de||",
		"Arcor|.arcor.de|Keywords|||de||",
		"Arianna|arianna.libero.it|query|||it||",
		"Arianna|.arianna.com|query|||it|",
		"Ask|.ask.com|ask|||||",
		"Ask|.ask.com|q|||||",
		"Ask|.ask.com|searchfor|||||",
		"Ask|.askkids.com|ask|||||",
		"Atlas|search.atlas.cz|q|||cz||",
		"Atlas|searchatlas.centrum.cz|q|||cz||",
		"auone Images|image.search.auone.jp|q|||jp||",
		"auone|search.auone.jp|q|||jp||",
		"Austronaut|.austronaut.at|q|||at||",
		"avg.com|search.avg.com|q|cd|hl|||",
		"Babylon|search.babylon.com|q|||||",
		"Baidu|.baidu.com|wd|||cn|utf8|",
		"Baidu|.baidu.com|word|||cn|utf8|",
		"Baidu|.baidu.com|kw|||cn|utf8|",
		"Biglobe Images|images.search.biglobe.ne.jp|q|||jp||",
		"Biglobe|.search.biglobe.ne.jp|q|||jp||",
		"Bing Images|.bing.com/images/|q|first||||",
		"Bing Images|.bing.com/images/|Q|first||||",
		"Bing|.bing.com|q|first||||",
		"Bing|.bing.com|Q|first||||",
		"Bing|search.msn.|q|first|||",
		"Bing|.it.msn.com|q|first||it||",
		"Bing|msnbc.msn.com|q|first||||",
		"Bing Cache|cc.bingj.com|q|first||||",
		"Bing Cache|cc.bingj.com|Q|first||||",
		"Blogdigger|.blogdigger.com|q|||||",
		"Blogpulse|.blogpulse.com|query|||||",
		"Bluewin|.bluewin.ch|q|||ch||",
		"Bluewin|.bluewin.ch|searchTerm|||ch||",
		"Centrum|.centrum.cz|q|||cz||",
		"chedot.com|search.chedot.com|text|||||",
		"Claro Search|claro-search.com|q|||||",
		"Clix|pesquisa.clix.pt|question|||pt||",
		"Conduit Images|images.search.conduit.com|q|||||",
		"Conduit|search.conduit.com|q|||||",
		"Comcast|search.comcast.net|q|||||",
		"Crawler|www.crawler.com|q|||||",
		"Compuserve|websearch.cs.com|query|||||",
		"Darkoogle|.darkoogle.com|q|cd|hl|||",
		"DasTelefonbuch|.dastelefonbuch.de|kw|||de||",
		"Daum|search.daum.net|q|||||",
		"Delfi Lv|smart.delfi.lv|q|||lv||",
		"Delfi EE|otsing.delfi.ee|q|||ee||",
		"Digg|digg.com|s|||||",
		"dir.com|fr.dir.com|req|||fr||",
		"DMOZ|.dmoz.org|search|||||",
		"DuckDuckGo|duckduckgo.com|q|||||",
		"Earthlink|.earthlink.net|q|||||",
		"Eniro|.eniro.se|q|||se||",
		"Euroseek|.euroseek.com|string|||||",
		"Everyclick|.everyclick.com|keyword|||||",
		"Excite|search.excite.|q|||||",
		"Excite|.excite.co.jp|search|||jp||",
		"Exalead|.exalead.fr|q|||fr||",
		"Exalead|.exalead.com|q|||fr||",
		"eo|eo.st|x_query|||st||",
		"Facebook|.facebook.com|q|||||",
		"Facemoods|start.facemoods.com|s|||||",
		"Fast Browser Search|.fastbrowsersearch.com|q|||||",
		"Francite|recherche.francite.com|name|||fr||",
		"Findhurtig|.findhurtig.dk|q|||dk||",
		"Fireball|.fireball.de|q|||de||",
		"Firstfind|.firstsfind.com|qry|||||",
		"Fixsuche|.fixsuche.de|q|||de||",
		"Flix.de|.flix.de|keyword|||de||",
		"Fooooo|search.fooooo.com|q|||||",
		"Free|.free.fr|q|||fr||",
		"Free|.free.fr|qs|||fr||",
		"Freecause|search.freecause.com|p|||||",
		"Freenet|suche.freenet.de|query|||de||",
		"Freenet|suche.freenet.de|Keywords|||de||",
		"Gnadenmeer|.gnadenmeer.de|keyword|||de||",
		"Godago|.godago.com|keywords||||",	//check
		"Gomeo|.gomeo.com|Keywords|||||",
		"goo|.goo.ne.jp|MT|||jp||",
		"Good Search|goodsearch.com|Keywords|||||",
		"Google|.googel.com|q|cd|hl|||",
		"Google|www.googel.|q|cd|hl|||",
		"Google|wwwgoogle.com|q|cd|hl|||",
		"Google|gogole.com|q|cd|hl|||",
		"Google|gppgle.com|q|cd|hl|||",
		"Google Blogsearch|blogsearch.google.|q|start||||",
		"Google Custom Search|google.com/cse|q|cd|hl|||",
		"Google Custom Search|google.com/custom|q|cd|hl|||",
		"Google Groups|groups.google.|q|start||||",
		"Google Images|.google.com/images?|q|cd|hl|||",
		"Google Images|images.google.|q|cd|hl|||",
		"Google Images|/imgres?imgurl=|prev|start|hl|||", //obsolete
		"Google Images JP|image.search.smt.docomo.ne.jp|MT|cd|hl|jp||",
		"Google JP|search.smt.docomo.ne.jp|MT|cd|hl|jp||",
		"Google JP|.nintendo.co.jp|gsc.q|cd|hl|jp||",
		"Google Maps|maps.google.|q||hl|||",
		"Google News|news.google.|q|cd|hl|||",
		"Google Scholar|scholar.google.|q|cd|hl|||",
		"Google Shopping|google.com/products|q|cd|hl|||",
		"Google syndicated search|googlesyndicatedsearch.com|q|cd|hl|||",
		"Google Translate|translate.google.|prev|start|hl|||",
		"Google Translate|translate.google.|q|cd|hl|||",
		"Google Translate|translate.googleusercontent.com|prev|start|hl|||",
		"Google Translate|translate.googleusercontent.com|q|cd|hl|||",
		"Google Video|video.google.com|q|cd|hl|||",
		"Google Cache|.googleusercontent.com|q|cd|hl|||",
		"Google Cache|http://64.233.1|q|cd|hl|||",
		"Google Cache|http://72.14.|q|cd|hl|||",
		"Google Cache|http://74.125.|q|cd|hl|||",
		"Google Cache|http://209.85.|q|cd|hl|||",
		"Google|www.google.|q|cd|hl|||",
		"Google|www.google.|as_q|start|hl|||",
		"Google|.google.com|q|cd|hl|||",
		"Google|.google.com|as_q|start|hl|||",
		"Gooofullsearch|.gooofullsearch.com|q|cd|hl|||",
		"Goyellow.de|.goyellow.de|MDN|||de||",
		"Hit-Parade|.hit-parade.com|p7|||||",
		"Hooseek.com|.hooseek.com|recherche|||||",
		"HotBot|hotbot.|query|||||",
		"ICQ Search|.icq.com|q|||||",
		"Ilse NL|.ilse.nl|search_for|||nl||",
		"Incredimail|.incredimail.com|q|||||",
		"InfoSpace|infospace.com|q|||||",
		"InfoSpace|dogpile.com|q|||||",
		"InfoSpace|search.fbdownloader.com|q|||||",
		"InfoSpace|searches3.globososo.com|q|||||",
		"InfoSpace|search.kiwee.com|q|||||",
		"InfoSpace|metacrawler.com|q|||||",
		"InfoSpace|tattoodle.com|q|||||",
		"InfoSpace|searches.vi-view.com|q|||||",
		"InfoSpace|webcrawler.com|q|||||",
		"InfoSpace|webfetch.com|q|||||",
		"InfoSpace|search.webssearches.com|q|||||",
		"Ixquick|ixquick.com|query|||||",
		"Ixquick|ixquick.de|query|||de||",
		"Jyxo|jyxo.1188.cz|q|||cz||",
		"Jumpy|.mediaset.it|searchWord|||it||",
		"Kataweb|kataweb.it|q|||it||",
		"Kvasir|kvasir.no|q|||no||",
		"Kvasir|kvasir.no|searchExpr|||no||",
		"Latne|.latne.lv|q|||lv||",
		"Looksmart|.looksmart.com|key|||||",
		"Lo.st|.lo.st|x_query|||||",
		"Lycos|.lycos.com|query|||||",
		"Lycos|.lycos.it|query|||it||",
		"Lycos|.lycos.|query||||",
		"Lycos|.lycos.|q|||||",
		"maailm.com|.maailm.com|tekst|||||",
		"Mail.ru|.mail.ru|q|||ru|utf8|",
		"MetaCrawler DE|.metacrawler.de|qry|||de||",
		"Metager|meta.rrzn.uni-hannover.de|eingabe|||de||",
		"Metager|metager.de|eingabe|||de||",
		"Metager2|.metager2.de|q|||de||",
		"Meinestadt|.meinestadt.de|words|||||",
		"Mister Wong|.mister-wong.com|keywords|||||",
		"Mister Wong|.mister-wong.de|keywords|||||",
		"Monstercrawler|.monstercrawler.com|qry|||||",
		"Mozbot|.mozbot.fr|q|||fr||",
		"Mozbot|.mozbot.co.uk|q|||gb||",
		"Mozbot|.mozbot.com|q|||||",
		"El Mundo|.elmundo.es|q|||es||",
		"MySpace|.myspace.com|qry|||||",
		"MyWebSearch|.mywebsearch.com|searchfor||||||",
		"MyWebSearch|.mywebsearch.com|searchFor||||||",
		"MyWebSearch|.mysearch.com|searchfor||||||",
		"MyWebSearch|.mysearch.com|searchFor||||||",
		"MyWebSearch|search.myway.com|searchfor||||||",
		"MyWebSearch|search.myway.com|searchFor||||||",
		"Najdi|.najdi.si|q|||si||",
		"Nate|.nate.com|q|||kr|EUC-KR|", //check charset
		"Naver|.naver.com|query|||kr||",
		"Needtofind|search.need2find.com|searchfor|||||",
		"Neti|.neti.ee|query|||ee|iso-8859-1|",
		"Nifty Videos|videosearch.nifty.com|kw|||||",
		"Nifty|.nifty.com|q|||||",
		"Nifty|.nifty.com|Text|||||",
		"Nifty|search.azby.fmworld.net|q|||||",
		"Nifty|search.azby.fmworld.net|Text|||||",
		"Nigma|nigma.ru|s|||ru||",
		"Onet.pl|szukaj.onet.pl|qt|||pl||",
		"OpenDir.cz|.opendir.cz|cohledas|||cz||",
		"Opplysningen 1881|.1881.no|Query|||no||",
		"Orange|busca.orange.es|q|||es||",
		"Orange|lemoteur.ke.voila.fr|kw|||fr||",
		"PagineGialle|paginegialle.it|qs|||it||",
		"Picsearch|.picsearch.com|q|||||",
		"Poisk.Ru|poisk.ru|text|||ru|windows-1251|",
		"qip.ru|search.qip.ru|query|||ru||",
		"Qualigo|www.qualigo.|q|||||",
		"Rakuten|.rakuten.co.jp|qt|||jp||",
		"Rambler|nova.rambler.ru|query|||ru||",
		"Rambler|nova.rambler.ru|words|||ru||",
		"RPMFind|rpmfind.net|query|||||",
		"Road Runner|search.rr.com|q|||||",
		"Sapo|pesquisa.sapo.pt|q|||pt||",
		"Search.com|.search.com|q|||||",
		"Search.ch|.search.ch|q|||ch||",
		"Searchy|.searchy.co.uk|q|||gb||",
		"Setooz|.setooz.com|query|||||",
		"Seznam Videa|videa.seznam.cz|q|||cz||",
		"Seznam|.seznam.cz|q|||cz||",
		"Sharelook|.sharelook.fr|keyword|||fr||",
		"Skynet|.skynet.be|q|||be||",
		"sm.cn|.sm.cn|q|||cn||",
		"sm.de|.sm.de|q|||de||",
		"SmartAdressbar|.smartaddressbar.com|s|||||",
		"So-net Videos|video.so-net.ne.jp|kw|||jp||",
		"So-net|.so-net.ne.jp|query|||jp||",
		"Sogou|.sogou.com|query|||cn|gb2312|",
		"Sogou|.sogou.com|keyword|||cn|gb2312|",
		"Soso|.soso.com|q|||cn|gb2312|",
		"Sputnik|.sputnik.ru|q|||ru||",
		"Start.no|start.no|q|||||",
		"Startpagina|.startpagina.nl|q|cd|hl|nl||",
		"Suche.info|suche.info|Keywords|||||",
		"Suchmaschine.com|.suchmaschine.com|suchstr|||||",
		"Suchnase|.suchnase.de|q|||de||",
		"Supereva|supereva.it|q|||it||",
		"T-Online|.t-online.de|q|||de|",
		"TalkTalk|.talktalk.co.uk|query|||gb||",
		"Teoma|.teoma.com|q|||||",
		"Terra|buscador.terra.|query|||||",
		"Tiscali|.tiscali.it|query|||it||",
		"Tiscali|.tiscali.it|key|||it||",
		"Tiscali|.tiscali.cz|query|||cz||",
		"Tixuma|.tixuma.de|sc|||de||",
		"La Toile Du Quebec|.toile.com|q|||ca||",
		"Toppreise.ch|.toppreise.ch|search|||ch|ISO-8859-1|",
		"TrovaRapido|.trovarapido.com|q|||||",
		"Trusted-Search|.trusted-search.com|w|||||",
		"URL.ORGanzier|www.url.org|q||l|||",
		"Vinden|.vinden.nl|q|||nl||",
		"Vindex|.vindex.nl|search_for|||nl||",
		"Virgilio|mobile.virgilio.it|qrs|||it||",
		"Virgilio|.virgilio.it|qs|||it||",
		"Voila|.voila.fr|rdata|||fr||",
		"Voila|.lemoteur.fr|rdata|||fr||",
		"Volny|.volny.cz|search|||cz||",
		"Walhello|.walhello.info|key|||||",
		"Walhello|www.walhello.|key|||||",
		"Web.de|suche.web.de|su|||de||",
		"Web.de|suche.web.de|q|||de||",
		"Web.nl|.web.nl|zoekwoord|||nl||",
		"Weborama|.weborama.fr|QUERY|||fr||",
		"WebSearch|.websearch.com|qkw|||||",
		"WebSearch|.websearch.com|q|||||",
		"Wedoo|.wedoo.com|keyword|||||",
		"Winamp|search.winamp.com|q|||||",
		"Witch|www.witch.de|search|||de||",
		"Wirtualna Polska|szukaj.wp.pl|szukaj|||pl||",
		"Woopie|www.woopie.jp|kw|||jp||",
		"wwW.ee|search.www.ee|query|||ee||",
		"X-recherche|.x-recherche.com|MOTS|||||",
		"Yahoo! Directory|search.yahoo.com/search/dir|p|||||",
		"Yahoo! Videos|video.search.yahoo.co.jp|p|||jp||",
		"Yahoo! Images|image.search.yahoo.co.jp|p|||jp||",
		"Yahoo! Images|images.search.yahoo.com|p|||||",
		"Yahoo! Images|images.search.yahoo.com|va|||||",
		"Yahoo! Images|images.yahoo.com|p|||||",
		"Yahoo! Images|images.yahoo.com|va|||||",
		"Yahoo!|search.yahoo.co.jp|p|||jp||",
		"Yahoo!|search.yahoo.co.jp|vp|||jp||",
		"Yahoo!|jp.hao123.com|query|||jp||",
		"Yahoo!|home.kingsoft.jp|keyword|||jp||",
		"Yahoo!|search.yahoo.com|p|||||",
		"Yahoo!|search.yahoo.com|q|||||",
		"Yahoo!|search.yahoo.|p|||||",
		"Yahoo!|search.yahoo.|q|||||",
		"Yahoo!|answers.yahoo.com|p|||||",
		"Yahoo!|.yahoo.com|p|||||",
		"Yahoo!|.yahoo.com|q|||||",
		"Yam|search.yam.com|k|||||",
		"Yandex Images|images.yandex.ru|text|||ru||",
		"Yandex Images|images.yandex.com|text|||ru||",
		"Yandex Images|images.yandex.|text|||||",
		"Yandex|yandex.ru|text|||ru||",
		"Yandex|yandex.com|text|||ru||",
		"Yandex|.yandex.|text|||||",
		"Yasni|.yasni.com|query|||||",
		"Yasni|www.yasni.|query|||||",
		"Yatedo|.yatedo.com|q|||||",
		"Yatedo|.yatedo.fr|q|||fr||",
		"Yippy|search.yippy.com|query|||||",
		"YouGoo|www.yougoo.fr|q|||fr||",
		"Zapmeta|.zapmeta.com|q|||||",
		"Zapmeta|.zapmeta.com|query|||||",
		"Zapmeta|www.zapmeta.|q|||||",
		"Zapmeta|www.zapmeta.|query|||||",
		"Zhongsou|p.zhongsou.com|w|||||",
		"Zoohoo|zoohoo.cz|q|||cz|windows-1250|",
		"Zoznam|.zoznam.sk|s|||sk||",
		"Zxuso|.zxuso.com|wd|||||",
	);
	foreach($lines as $line_num => $serec) {
		list($nome,$domain,$key,$page,$lang,$selocale,$charset)=explode("|",$serec);
		//match on both domain and key..
		if (strpos($domain,'http') === false) {
			$se_regex='/^https?\:\/\/[a-z0-9\.\-]*'.preg_quote($domain,'/').'.*[&\?]'.$key.'\=([^&]+)/i';
		} else {
			$se_regex='/^'.preg_quote($domain,'/').'.*[&\?]'.$key.'\=([^&]+)/i';
		}
		$se = preg_match($se_regex,$referrer,$match);
		if (!$se && strpos($referrer,$domain)!==false && strpos(urldecode($referrer),$key.'=')!==false) {
			$se=preg_match($se_regex,urldecode($referrer),$match);
		}
		if ($se) {	// found it!
			$searchengine = $nome;
			$search_phrase = "";
			$svariables=array();
			// Google Images or Google Translate needs additional processing of search phrase after 'prev='
			if ($nome == "Google Images" || $nome == "Google Translate") {
				//'prev' is an encoded substring containing actual "q" query, so use html_entity_decode to show [&?] in url substring
				$svariables = wGetQueryVars(html_entity_decode(preg_replace('#/\w+\?#i','', urldecode($match[1]))));
				$key='q';	//q is actual search key
			} elseif ($nome == "Google Cache") {
				$n = strpos($match[1],$blogurl);
				if ($n !== false) {
				//blogurl in search phrase: cache of own site
					$search_phrase = esc_attr(urldecode(substr($match[1],$n+strlen($blogurl))));
					$svariables = wGetQueryVars($referrer);
				} elseif (strpos($referrer,$blogurl)!==false && preg_match('/\&prev\=([^&]+)/',$referrer,$match)!==false) {
					//NOTE: 'prev=' requires html_entity_decode to show [&?] in url substring
					$svariables = wGetQueryVars(html_entity_decode(preg_replace('#/\w+\?#i','', urldecode($match[1]))));
				} else {
				//no blogurl in search phrase: cache of an external site with referrer link
					$searchengine = "";
					$referrer = "";
				}
			} else {
				$search_phrase = esc_attr(urldecode($match[1]));
				$svariables = wGetQueryVars($referrer);
			}
			//retrieve search engine parameters
			if(!empty($svariables[$key]) && empty($search_phrase)){
				$search_phrase=esc_attr($svariables[$key]);
			}
			if(!empty($page) && !empty($svariables[$page]) && is_numeric($svariables[$page])){
				$searchpage=(int)$svariables[$page];
			}
			if(!empty($lang) && !empty($svariables[$lang]) && strlen($svariables[$lang])>1){
				$searchlang=esc_attr($svariables[$lang]);
			}
			//Indentify locale via Google search's parameter, 'gl'
			if(strstr($nome,'Google')!==false && !empty($svariables['gl'])){
				$selocale=esc_attr($svariables['gl']);
			}
			break 1;
		} elseif (strstr($referrer,$domain)!==false) {
			$searchengine = $nome;
		} //end if se
	} //end foreach
	//search engine or key is not in list, so check for search phrase instead
	if (empty($search_phrase) && !empty($referrer)) {
		//Check for general search phrases
		if(preg_match('#^https?://([^/]+).*[&?](q|search|searchfor|as_q|as_epq|query|keywords?|term|text|encquery)=([^&]+)#i',$referrer,$pcs) > 0){
			if (empty($searchengine)) $searchengine=trim(strtolower($pcs[1]));
			if ($pcs[2] =="encquery"){
				$search_phrase=" *".__("encrypted search","wassup")."* ";
			}else{
				$search_phrase = $pcs[3];
			}
		//Check separately for queries that use nonstandard search variable to avoid retrieving values like "p=parameter" when "q=query" exists
		}elseif(preg_match('#^https?://([^/]+).*(?:results|search|query).*[&?](aq|as|p|su|s|kw|k|qo|qp|qs|string)=([^&]+)#i',$referrer,$pcs) > 0){
			if (empty($searchengine)) $searchengine = trim(strtolower($pcs[1]));
			$search_phrase = $pcs[3];
		}
	} //end if search_phrase
	//do a separate check for page number, if not found above
	if (!empty($search_phrase)) {
		if(empty($searchpage) && preg_match('#[&\?](start|startpage|b|cd|first|stq|p|pi|page)[=/](\d+)#i',$referrer,$pcs)>0){
			$searchpage = $pcs[2];
		}
	}
	return array('keywords'=>$search_phrase,'page'=>$searchpage,'searchengine'=>$searchengine,'language'=>$searchlang,'locale'=>$selocale);
} //end wGetSE

/**
 * Extract browser and platform info from a user agent string and return the values
 * @param string
 * @return array (browser, os)
 */
function wGetBrowser($agent="") {
	global $wassup_options,$wdebug_mode;
	if(empty($agent)) $agent=$_SERVER['HTTP_USER_AGENT'];
	$browsercap=array();
	$browscapbrowser="";
	$browser="";
	$os="";
	//check PHP browscap data for browser and platform
	//'browscap' must be set in "php.ini", 'ini_set' doesn't work
	if(ini_get("browscap")!=""){
		$browsercap = get_browser($agent,true);
		if(!empty($browsercap['platform'])){
		if(stristr($browsercap['platform'],"unknown") === false){
			$os=$browsercap['platform'];
			if(!empty($browsercap['browser'])){
				$browser=$browsercap['browser'];
			}elseif(!empty($browsercap['parent'])){
				$browser=$browsercap['parent'];
			}
			if(!empty($browser) && !empty($browsercap['version'])){
				$browser=$browser." ".wMajorVersion($browsercap['version']);
			}
		}
		}
		//reject generic browscap browsers (ex: mozilla, default)
		if(preg_match('/^(mozilla|default|unknown)/i',$browser) >0){
			$browscapbrowser="$browser";	//save just in case
			$browser="";
		}
		$os=trim($os);
		$browser=trim($browser);
		if($wdebug_mode){
			if(headers_sent()){
				echo "\nPHP Browscap data from get_browser: \c";
				if(is_array($browsercap)|| is_object($browsercap)) print_r($browsercap);
				else echo $browsercap;
			}
		}
	}
	//use wDetector class when browscap browser is empty/unknown
	if ( $os == "" || $browser == "") {
		$dip=@new wDetector("",$agent);
		if(!empty($dip)){
			$browser=trim($dip->browser." ".wMajorVersion($dip->browser_version));
			if($dip->os!="") $os=trim($dip->os." ".$dip->os_version);
		}
		//use saved browscap info when Detector has no result
		if(!empty($browscapbrowser) && $browser == "") $browser=$browscapbrowser;
	}
	return array($browser,$os);
} //end function wGetBrowser

//return a major version # from a version string argument
function wMajorVersion($versionstring) {
	$version=0;
	if (!empty($versionstring)) {
		$n = strpos($versionstring,'.');
		if ($n >0) {
			$version= (int) substr($versionstring,0,$n);
		}
		if ($n == 0 || $version == 0) {
			$p = strpos($versionstring,'.',$n+1);
			if ($p) $version= substr($versionstring,0,$p);
		}
	}
	if ($version > 0) return $version;
	else return $versionstring;
}

/**
 * Extract spider information from a user agent string and return an array.
 *  Return values: (name, type=[R|B|F|H|L|S|V], feed subscribers) where types are: R=robot, B=browser/downloader, F=feed reader, H=hacker/spoofer/injection bot, L=Link checker/sitemap generator, S=Spammer/email harvester, V=css/html Validator
 * @param string(3)
 * @return array
 */
function wGetSpider($agent="",$hostname="", $browser=""){
	if(empty($agent) && !empty($_SERVER['HTTP_USER_AGENT'])){
		$agent=$_SERVER['HTTP_USER_AGENT'];
	}
	$ua=rtrim($agent);
	//if(empty($ua)) return false;	//nothing to do
	$spiderdata=false;
	$crawler="";
	$feed="";
	$os="";
	$pcs=array();
	//identify obvious script injection bots
	if(!empty($ua)){
		//New in v1.9.3.1: check for more variations of <script> and <a> tags embedded in user agent string
		if(stristr($ua,'location.href')!==FALSE){
			$crawlertype="H";
			$crawler="Script Injection bot";
		}elseif(preg_match('/(<|&lt;?|&#0*60;?|%3C)a(\s|%20|&#32;|\+)href/i',$ua)>0){
			$crawlertype="H";
			$crawler="Script Injection bot";
		}elseif(preg_match('/(<|&lt;?|&#0*60;?|%3C)scr(ipt|[^0-9a-z\-_])/i',$ua)>0){
			$crawlertype="H";
			$crawler="Script Injection bot";
		}elseif(preg_match('/select.*(\s|%20|\+|%#32;)from(\s|%20|\+|%#32;)wp_/i',$ua)>0){
			$crawlertype = "H";
			$crawler = "Script Injection bot";
		}
	}
	//check for crawlers that mis-identify themselves as a browser but come from a known crawler domain - the most common of these are MSN (ie6,win2k3), and Yahoo!
	if(substr($_SERVER["REMOTE_ADDR"],0,6) == "65.55." || substr($_SERVER["REMOTE_ADDR"],0,7) == "207.46." || substr($_SERVER["REMOTE_ADDR"],0,7)=="157.55."){
		$crawler = "BingBot";
		$crawlertype="R";
	}elseif(!empty($hostname) && preg_match('/([a-z0-9\-\.]+){1,}\.(?:[a-z]+){2,4}$/',$hostname)>0){
		if(substr($hostname,-14)==".yse.yahoo.net" || substr($hostname,-16)==".crawl.yahoo.net" || (substr($hostname,-10)==".yahoo.com" && substr($hostname,0,3)=="ycar")){
			if(!empty($ua) && stristr($ua,"Slurp")){
				$crawler = "Yahoo! Slurp";
				$crawlertype="R";
			}else{
				$crawler = "Yahoo!";
				$crawlertype="R";
			}
		}elseif(substr($hostname,-8) == ".msn.com" && strpos($hostname,"msnbot")!== FALSE){
			$crawler = "BingBot";
			$crawlertype="R";
		}elseif(substr($hostname,-14) == ".googlebot.com"){
			//googlebot mobile can show as browser, sometimes
			if(!empty($ua) && stristr($ua,"mobile")){
				$crawler="Googlebot-Mobile";
				$crawlertype="R";
			}else{
				$crawler="Googlebot";
				$crawlertype="R";
			}
		}elseif(substr($hostname,0,11)=="baiduspider"){
			$crawler="Baiduspider";
			$crawlertype="R";

		}elseif(substr($hostname,-16)==".domaintools.com"){
			$crawler="Whois.domaintools.com";
			$crawlertype="R";
		}
	} //end if $hostname
	$pcs=array();
	$pcs2=array();
	if(empty($crawler)){
		// check for crawlers that identify themselves clearly in their user agent string with words like bot, spider, and crawler
		if ((!empty($ua) && preg_match('#(\w+[ \-_]?(bot|crawl|google|reader|seeker|spider|feed|indexer|parser))[0-9/ -:_.;\)]#',$ua,$pcs) >0) || preg_match('#(crawl|feed|google|indexer|parser|reader|robot|seeker|spider)#',$hostname,$pcs2) >0){
			if(!empty($pcs[1])) $crawler=$pcs[1];
			elseif(!empty($pcs2[1])) $crawler="unknown_spider";
			$crawlertype="R";
		}
		// check browscap data for crawler if available
		if(empty($crawler) && !empty($ua) && ini_get("browscap")!=""){
			$browsercap = get_browser($ua,true);
			//if no platform(os), assume crawler...
			if(!empty($browsercap['platform'])) {
				if($browsercap['platform'] != "unknown") $os=$browsercap['platform'];

			}
			if(!empty($browsercap['crawler']) || !empty($browsercap['stripper']) || $os == ""){
				if(!empty($browsercap['browser'])){
					$crawler=$browsercap['browser'];
				}elseif(!empty($browsercap['parent'])){
					$crawler=$browsercap['parent'];
				}
				if (!empty($crawler) && !empty($browsercap['version'])){
					$crawler=$crawler." ".$browsercap['version'];
				}
			}
			//reject unknown browscap crawlers (ex: default)
			if(preg_match('/^(default|unknown|robot)/i',$crawler) > 0){
				$crawler="";
			}
		}
	}
	//get crawler info. from a known list of bots and feedreaders that don't list their names first in UA string.
	//Note: spaces removed from UA string for this bot comparison
	$crawler=trim($crawler);
	if(empty($crawler) || $crawler=="unknown_spider"){
		$uagent=str_replace(" ","",$ua);
		$key=null;
		// array format: "Spider Name|UserAgent keywords (no spaces)| Spider type (R=robot, B=Browser/downloader, F=feedreader, H=hacker, L=Link checker, M=siteMap generator, S=Spammer/email harvester, V=CSS/Html validator)
		$lines=array(
			"Internet Archive|archive.org_bot|R|",
			"Internet Archive|.archive.org|R|",
			"Baiduspider|Baiduspider/|R|",
			"Baiduspider|.crawl.baidu.com|R|",
			"BingBot|MSNBOT/|R|","BingBot|msnbot.|R|",
			"Exabot|Exabot/|R|",
			"Exabot|.exabot.com|R|",
			"Googlebot|Googlebot/|R|",
			"Googlebot|.googlebot.com|R|",
			"Google|.google.com||",
			"SurveyBot|SurveyBot/||",
			"WebCrawler.link|.webcrawler.link|R|",
			"Yahoo! Slurp|Yahoo!Slurp|R|",
			"Yahoo!|.yse.yahoo.net|R|",
			"Yahoo!|.crawl.yahoo.net|R|",
			"YandexBot|YandexBot/|R|",
			"AboutUsBot|AboutUsBot/|R|",
			"80bot|80legs.com|R|",
			"Aggrevator|Aggrevator/|F|",
			"AlestiFeedBot|AlestiFeedBot||",
			"Alexa|ia_archiver|R|", "AltaVista|Scooter-|R|",
			"AltaVista|Scooter/|R|", "AltaVista|Scooter_|R|",
			"AMZNKAssocBot|AMZNKAssocBot/|R|",
			"AppleSyndication|AppleSyndication/|F|",
			"Apple-PubSub|Apple-PubSub/|F|",
			"Ask.com/Teoma|AskJeeves/Teoma)|R|",
			"Ask Jeeves/Teoma|ask.com|R|",
			"AskJeeves|AskJeeves|R|",
			"BlogBot|BlogBot/|F|","Bloglines|Bloglines/|F|",
			"Blogslive|Blogslive|F|",
			"BlogsNowBot|BlogsNowBot|F|",
			"BlogPulseLive|BlogPulseLive|F|",
			"IceRocket BlogSearch|icerocket.com|F|",
			"Charlotte|Charlotte/|R|",
			"Xyleme|cosmos/0.|R|", "cURL|curl/|R|",
			"Daumoa|Daumoa-feedfetcher|F|",
			"Daumoa|DAUMOA|R|",
			"Daumoa|.daum.net|R|",
			"Die|die-kraehe.de|R|",
			"Diggit!|Digger/|R|",
			"disco/Nutch|disco/Nutch|R|",
			"DotBot|DotBot/|R|",
			"Emacs-w3|Emacs-w3/v||",
			"ananzi|EMC||",
			"EnaBot|EnaBot||",
			"esculapio|esculapio/||", "Esther|esther||",
			"everyfeed-spider|everyfeed-spider|F|",
			"Evliya|Evliya||", "nzexplorer|explorersearch||",
			"eZ publish Validator|eZpublishLinkValidator||",
			"FacebookExternalHit|facebook.com/externalhit|R|",
			"FastCrawler|FastCrawler|R|",
			"FDSE|FDSErobot|R|",
			"Feed::Find|Feed::Find||",
			"FeedBurner|FeedBurner|F|",
			"FeedDemon|FeedDemon/|F|",
			"FeedHub FeedFetcher|FeedHub|F|",
			"Feedreader|Feedreader|F|",
			"Feedshow|Feedshow|F|",
			"Feedster|Feedster|F|",
			"FeedTools|feedtools|F|",
			"Feedfetcher-Google|Feedfetcher-google|F|",
			"Felix|FelixIDE/||",
			"FetchRover|ESIRover||",
			"fido|fido/||",
			"Fish|Fish-Search-Robot||", "Fouineur|Fouineur||",
			"Freecrawl|Freecrawl|R|",
			"FriendFeedBot|FriendFeedBot/|F|",
			"FunnelWeb|FunnelWeb-||",
			"gammaSpider|gammaSpider||","gazz|gazz/||",
			"GCreep|gcreep/||",
			"GetRight|GetRight|R|",
			"GetURL|GetURL.re||","Golem|Golem/||",
			"Google Favicon|GoogleFavicon|R|",
			"GreatNews|GreatNews|F|",
			"Gregarius|Gregarius/|F|",
			"Gromit|Gromit/||",
			"gsinfobot|gsinfobot||",
			"Gulliver|Gulliver/||", "Gulper|Gulper||",
			"GurujiBot|GurujiBot||",
			"havIndex|havIndex/||",
			"heritrix|heritrix/||", "HI|AITCSRobot/||",
			"HKU|HKU||", "Hometown|Hometown||",
			"HostTracker|host-tracker.com/|R|",
			"ht://Dig|htdig/|R|","HTMLgobble|HTMLgobble||",
			"Hyper-Decontextualizer|Hyper||",
			"iajaBot|iajaBot/||",
			"IBM_Planetwide|IBM_Planetwide,||",
			"ichiro|ichiro||",
			"Popular|gestaltIconoclast/||",
			"Ingrid|INGRID/||","Imagelock|Imagelock||",
			"IncyWincy|IncyWincy/||",
			"Informant|Informant||",
			"InfoSeek|InfoSeek||",
			"InfoSpiders|InfoSpiders/||",
			"Inspector|inspectorwww/||",
			"IntelliAgent|IAGENT/||",
			"ISC Systems iRc Search|ISCSystemsiRcSearch||",
			"Israeli-search|IsraeliSearch/||",
			"IRLIRLbot/|IRLIRLbot||",
			"Italian Blog Rankings|blogbabel|F|",
			"Jakarta|Jakarta||", "Java|Java/||",
			"JBot|JBot||",
			"JCrawler|JCrawler/||",
			"JoBo|JoBo||", "Jobot|Jobot/||",
			"JoeBot|JoeBot/||",
			"JumpStation|jumpstation||",
			"image.kapsi.net|image.kapsi.net/|R|",
			"kalooga/kalooga|kalooga/kalooga||",
			"Katipo|Katipo/||",
			"KDD-Explorer|KDD-Explorer/||",
			"KIT-Fireball|KIT-Fireball/||",
			"KindOpener|KindOpener||", "kinjabot|kinjabot||",
			"KO_Yappo_Robot|yappo.com/info/robot.html||",
			"Krugle|Krugle||",
			"LabelGrabber|LabelGrab/||",
			"Larbin|larbin_||",
			"libwww-perl|libwww-perl||",
			"lilina|Lilina||",
			"Link|Linkidator/||","LinkWalker|LinkWalker|L|",
			"LiteFinder|LiteFinder||",
			"logo.gif|logo.gif||","LookSmart|grub-client||",
			"Lsearch/sondeur|Lsearch/sondeur||",
			"Lycos|Lycos/||",
			"Magpie|Magpie/||","MagpieRSS|MagpieRSS|F|",
			"Mail.RU|Mail.RU_Bot/|R|",
			"marvin/infoseek|marvin/infoseek||",
			"Mattie|M/3.||","MediaFox|MediaFox/||",
			"Megite2.0|Megite.com||",
			"NEC-MeshExplorer|NEC-MeshExplorer||",
			"MindCrawler|MindCrawler||",
			"Missigua Locator|MissiguaLocator||",
			"MJ12bot|MJ12bot|R|","mnoGoSearch|UdmSearch||",
			"MOMspider|MOMspider/||","Monster|Monster/v||",
			"Moreover|Moreoverbot||","Motor|Motor/||",
			"MSNBot|MSNBOT/|R|","MSN|msnbot.|R|",
			"MSRBOT|MSRBOT|R|","Muninn|Muninn/||",
			"Muscat|MuscatFerret/||",
			"Mwd.Search|MwdSearch/||",
			"MyBlogLog|Yahoo!MyBlogLogAPIClient|F|",
			"Naver|NaverBot||",
			"Naver|Cowbot||",
			"NDSpider|NDSpider/||",
			"Nederland.zoek|Nederland.zoek||",
			"NetCarta|NetCarta||",
			"NetMechanic|NetMechanic||",
			"NetScoop|NetScoop/||",
			"NetNewsWire|NetNewsWire||",
			"NewsAlloy|NewsAlloy||",
			"newscan-online|newscan-online/||",
			"NewsGatorOnline|NewsGatorOnline||",
			"Exalead NG|NG/|R|",
			"NHSE|NHSEWalker/||","Nomad|Nomad-V||",
			"Nutch/Nutch|Nutch/Nutch||",
			"ObjectsSearch|ObjectsSearch/||",
			"Occam|Occam/||",
			"Openfind|Openfind||",
			"OpiDig|OpiDig||",
			"Orb|Orbsearch/||",
			"OSSE Scanner|OSSEScanner||",
			"OWPBot|OWPBot||",
			"Pack|PackRat/||","ParaSite|ParaSite/||",
			"Patric|Patric/||",
			"PECL::HTTP|PECL::HTTP||",
			"PerlCrawler|PerlCrawler/||",
			"Phantom|Duppies||","PhpDig|phpdig/||",
			"PiltdownMan|PiltdownMan/||",
			"Pimptrain.com's|Pimptrain||",
			"Pioneer|Pioneer||",
			"Portal|PortalJuice.com/||","PGP|PGP-KA/||",
			"PlumtreeWebAccessor|PlumtreeWebAccessor/||",
			"Poppi|Poppi/||","PortalB|PortalBSpider/||",
			"psbot|psbot/|R|",
			"Python-urllib|Python-urllib/|R|",
			"R6_CommentReade|R6_CommentReade||",
			"R6_FeedFetcher|R6_FeedFetcher|F|",
			"radianrss|RadianRSS||",
			"Raven|Raven-v||",
			"relevantNOISE|relevantnoise.com||",
			"Resume|Resume||", "RoadHouse|RHCS/||",
			"RixBot|RixBot||",
			"Robbie|Robbie/||", "RoboCrawl|RoboCrawl||",
			"RoboFox|Robofox||",
			"Robozilla|Robozilla/||",
			"Rojo|rojo1|F|",
			"Roverbot|Roverbot||",
			"RssBandit|RssBandit||",
			"RSSMicro|RSSMicro.com|F|",
			"Ruby|Rfeedfinder||",
			"RuLeS|RuLeS/||",
			"Runnk RSS aggregator|Runnk||",
			"SafetyNet|SafetyNet||",
			"Sage|(Sage)|F|",
			"SBIder|sitesell.com|R|",
			"Scooter|Scooter/||",
			"ScoutJet|ScoutJet||",
			"Screaming Frog SEO Spider|ScreamingFrogSEOSpider/|L|",
			"SearchProcess|searchprocess/||",
			"Seekbot|seekbot.net|R|",
			"SimplePie|SimplePie/|F|",
			"Sitemap Generator|SitemapGenerator||",
			"Senrigan|Senrigan/||",
			"SeznamBot|SeznamBot/|R|",
			"SeznamScreenshotator|SeznamScreenshotator/|R|",
			"SG-Scout|SG-Scout||", "Shai'Hulud|Shai'Hulud||",
			"Simmany|SimBot/||",
			"SiteTech-Rover|SiteTech-Rover||",
			"shelob|shelob||",
			"Sleek|Sleek||",
			"Slurp|.inktomi.com/slurp.html|R|",
			"Snapbot|.snap.com|R|",
			"SnapPreviewBot|SnapPreviewBot|R|",
			"Smart|ESISmartSpider/||",
			"Snooper|Snooper/b97_01||", "Solbot|Solbot/||",
			"Sphere Scout|SphereScout|R|",
			"Sphere|sphere.com|R|",
			"spider_monkey|mouse.house/||",
			"SpiderBot|SpiderBot/||",
			"Spiderline|spiderline/||",
			"SpiderView(tm)|SpiderView||",
			"SragentRssCrawler|SragentRssCrawler|F|",
			"Site|ssearcher100||",
			"StackRambler|StackRambler||",
			"Strategic Board Bot|StrategicBoardBot||",
			"Suke|suke/||",
			"SummizeFeedReader|SummizeFeedReader|F|",
			"suntek|suntek/||",
			"Sygol|.sygol.com||",
			"Syndic8|Syndic8|F|",
			"TACH|TACH||","Tarantula|Tarantula/||",
			"tarspider|tarspider||","Tcl|dlw3robot/||",
			"TechBOT|TechBOT||","Technorati|Technoratibot||",
			"Teemer|Teemer||","Templeton|Templeton/||",
			"TitIn|TitIn/||","TITAN|TITAN/||",
			"Twiceler|.cuil.com/twiceler/|R|",
			"Twiceler|.cuill.com/twiceler/|R|",
			"Twingly|twingly.com|R|",
			"UCSD|UCSD-Crawler||", "UdmSearch|UdmSearch/||",
			"UniversalFeedParser|UniversalFeedParser|F|",
			"UptimeBot|uptimebot||",
			"URL_Spider|URL_Spider_Pro/|R|",
			"VadixBot|VadixBot||", "Valkyrie|Valkyrie/||",
			"Verticrawl|Verticrawlbot||",
			"Victoria|Victoria/||",
			"vision-search|vision-search/||",
			"void-bot|void-bot/||", "Voila|VoilaBot||",
			"Voyager|.kosmix.com/html/crawler|R|",
			"VWbot|VWbot_K/||",
			"W3C_Validator|W3C_Validator/|V|",
			"w3m|w3m/|B|", "W3M2|W3M2/||", "w3mir|w3mir/||",
			"w@pSpider|w@pSpider/||",
			"WallPaper|CrawlPaper/||",
			"WebCatcher|WebCatcher/||",
			"webCollage|webcollage/|R|",
			"webCollage|collage.cgi/|R|",
			"WebCopier|WebCopierv|R|",
			"WebFetch|WebFetch|R|", "WebFetch|webfetch/|R|",
			"WebMirror|webmirror/||",
			"webLyzard|webLyzard||", "Weblog|wlm-||",
			"WebReaper|webreaper.net|R|",
			"WebVac|webvac/||", "webwalk|webwalk||",
			"WebWalker|WebWalker/||",
			"WebWatch|WebWatch||",
			"WebStolperer|WOLP/||",
			"WebThumb|WebThumb/|R|",
			"Wells Search II|WellsSearchII||",
			"Wget|Wget/||",
			"whatUseek|whatUseek_winona/||",
			"whiteiexpres/Nutch|whiteiexpres/Nutch||",
			"wikioblogs|wikioblogs||",
			"WikioFeedBot|WikioFeedBot||",
			"WikioPxyFeedBo|WikioPxyFeedBo||",
			"Wild|Hazel's||",
			"Wired|wired-digital-newsbot/||",
			"Wordpress Pingback/Trackback|Wordpress/||",
			"WWWC|WWWC/||",
			"XGET|XGET/||",
			"Xenu Link Sleuth|XenuLinkSleuth/|L|",
			"yacybot|yacybot||",
			"Yahoo FeedSeeker|YahooFeedSeeker|F|",
			"Yahoo MMAudVid|Yahoo-MMAudVid/|R|",
			"Yahoo MMCrawler|Yahoo-MMCrawler/|R|",
			"Yahoo!SearchMonkey|Yahoo!SearchMonkey|R|",
			"YahooSeeker|YahooSeeker/|R|",
			"Yandex|.yandex.com|R|",
			"YoudaoBot|YoudaoBot|R|",
			"Tailrank|spinn3r.com/robot|R|",
			"Tailrank|tailrank.com/robot|R|",
			"Yesup|yesup||",
			"Internet|User-Agent:||",
			"Robot|Robot|R|", "Spider|spider|R|");
		foreach($lines as $line_num => $spider) {
			list($nome,$key,$crawlertype)=explode("|",$spider);
			if($key !=""){
				if(strpos($uagent,$key)!==false || (strpos($hostname,$key)!==false && strlen($key)>6)){
					$crawler=trim($nome);
					if(!empty($crawlertype) && $crawlertype == "F") $feed=$crawler;
					break 1;
				}
			}
		}
	} // end if crawler
	//If crawler not on list, use first word in useragent for crawler name
	if(empty($crawler)){
		$pcs=array();
		//Assume first word in useragent is crawler name
		if(preg_match('/^(\w+)[\/ \-\:_\.;]/',$ua,$pcs) > 0){
			if(strlen($pcs[1])>1 && $pcs[1]!="Mozilla"){
				$crawler=$pcs[1];
			}
		}
		//Use browser name for crawler as last resort
		//if (empty($crawler) && !empty($browser)) $crawler = $browser;
	}
	//#do a feed check and get feed subcribers, if available
	if(preg_match('/([0-9]{1,10})\s?subscriber/i',$ua,$subscriber) > 0){
		// It's a feedreader with some subscribers
		$feed=$subscriber[1];
		if(empty($crawler) && empty($browser)){
			$crawler=__("Feed Reader","wassup");
			$crawlertype="F";
		}
	}elseif(empty($feed) && (is_feed() || preg_match("/(feed|rss)/i",$ua)>0)){
		if(!empty($crawler)){
			$feed=$crawler;
		}elseif(empty($browser)){
			$crawler=__("Feed Reader","wassup");
			$feed=__("feed reader","wassup");
		}
		$crawlertype="F";
	}
	if($crawler=="Spider" || $crawler=="unknown_spider" || $crawler=="robot"){
		$crawler = __("Unknown Spider","wassup");
	}
	$spiderdata=array($crawler,$crawlertype,trim($feed));
	return $spiderdata;
} //end function wGetSpider

//#get the visitor locale/language
function wGetLocale($language="",$hostname="",$referrer="") {
	global $wdebug_mode;
	$clocale="";
	$country="";
	$langcode=trim(strtolower($language));
	$llen=strlen($langcode);
	//change language code to 2-digits
	if($llen >2 && preg_match('/([a-z]{2})(?:-([a-z]{2}))?(?:[^a-z]|$)/i',$langcode,$pcs)>0){
		if(!empty($pcs[2])) $language=strtolower($pcs[2]);
		elseif(!empty($pcs[1])) $language=strtolower($pcs[1]);
	}elseif($llen >2){
		$langarray=explode("-",$langcode);
		$langarray=explode(",",$langarray[1]);
		list($language)=explode(";",$langarray[0]);
	}
	//use 2-digit top-level domains (TLD) for country code, if any
	if (strlen($hostname)>2 && preg_match('/\.[a-z]{2}$/i', $hostname)>0){
		$country=strtolower(substr($hostname,-2));
		//ignore domains commonly used for media
		if($country == "tv" || $country == "fm") $country="";
	}
	$pcs=array();
	if(empty($language) || $language=="us" || $language=="en"){
		//major USA-only ISP hosts always have "us" as language code
		if (empty($country) && strlen($hostname)>2 && preg_match('/(\.[a-z]{2}\.comcast\.net|\.verizon\.net|\.windstream\.net)$/',$hostname,$pcs)>0) {
			$country="us";
		//retrieve TLD country code and language from search engine referer string
		}elseif(!empty($referrer)){
			$pcs=array();
			//google search syntax: hl=host language
			if (preg_match('/\.google\.(?:com?\.([a-z]{2})|([a-z]{2})|com)\/[a-z]+.*(?:[&\?]hl\=([a-z]{2})\-?(\w{2})?)/i',$referrer,$pcs)>0) {
				if(!empty($pcs[1])){
					$country=strtolower($pcs[1]);
				}elseif(!empty($pcs[2])){
					$country=strtolower($pcs[2]);
				}elseif(!empty($pcs[3]) || !empty($pcs[4])){
					if(!empty($pcs[4])) $language=strtolower($pcs[4]);
					else $language=strtolower($pcs[3]);
				}
			}
		}
	}
	//Make tld code consistent with locale code
	if(!empty($country)){
		if($country=="uk"){	//United kingdom
			$country="gb";
		}elseif($country=="su"){ //Soviet Union
			$country="ru";
		}
	}
	//Make language code consistent with locale code
	if($language == "en"){		//"en" default is US
		if(empty($country)) $language="us";
		else $language=$country;
	}elseif($language == "uk"){	//change UK to UA (Ukranian)
		$language="ua";
	}elseif($language == "ja"){	//change JA to JP
		$language="jp";
	}elseif($language == "ko"){	//change KO to KR
		$language="kr";
	}elseif($language == "da"){	//change DA to DK
		$language="dk";
	}elseif($language == "ur"){	//Urdu = India or Pakistan
		if($country=="pk") $language=$country;
		else $language="in";
	}elseif($language == "he" || $language == "iw"){
		if(empty($country)) $language="il";	//change Hebrew (iso) to IL
		else $language=$country;
	}
	//Replace language with locale for widely spoken languages
	if(!empty($country)){
		if(empty($language) || $language=="us" || preg_match('/^([a-z]{2})$/',$language)==0){
			$language=$country;
		}elseif($language=="es"){
			//for Central/South American locales
			$language=$country;
		}
	}
	if(!empty($language) && preg_match('/^[a-z]{2}$/',$language)>0){
		$clocale=$language;
	}
	return $clocale;
} //end function wGetLocale

/**
 * Check referrer string and referrer host (or hostname) for spam
 *   -checks referrer string for known spammer content (faked referrer).
 *   -checks referer host against know list of spammers
 * @param string $referrer, string $hostname
 * @return boolean
 */
function wGetSpamRef($referrer,$hostname="") {
	global $wdebug_mode;
	$ref=esc_attr(strip_tags(str_replace(" ","",html_entity_decode($referrer))));
	$badhost=false;
	$referrer_host = "";
	$referrer_path = "";
	if(empty($referrer) && !empty($hostname)){
		$referrer_host=$hostname;
		$hostname="";
	}elseif(!empty($referrer)){
		$rurl=parse_url(strtolower($ref));
		if(isset($rurl['host'])){
			$referrer_host=$rurl['host'];
			$thissite=parse_url(get_option('home'));
			$thisdomain=wassupURI::get_urldomain();
			//exclude current site as referrer
			if(isset($thissite['host']) && $referrer_host == $thissite['host']){
				$referrer_host="";
			//Since v1.8.3: check the path|query part of url for spammers
			}else{
				//rss.xml|sitemap.txt in referrer is faked
				if(preg_match('#.+/(rss\.xml|sitemap\.txt)$#',$ref)>0) $badhost=true;
				//membership|user id in referrer is faked
				elseif(preg_match('#.+[^a-z0-9]((?:show)?user|u)\=\d+$#',$ref)>0) $badhost=true;
				//youtu.be video in referrer is faked
				elseif(preg_match('#(\.|/)youtu.be/[0-9a-z]+#i',$ref)>0) $badhost=true;
				//some facebook links in referrer are faked
				elseif(preg_match('#(\.|/)facebook\.com\/ASeaOfSins$#',$ref)>0) $badhost=true;
				//domain lookup in referrer is faked
				elseif(preg_match('#/[0-9a-z_\-]+(?:\.php|/)(\?[a-z]+\=(?:http://)?(?:[0-9a-z_\-\.]+)?)'.preg_quote($thisdomain).'(?:[^0-9a-z_\-.]|$)#i',$ref)>0) $badhost=true;
			}
		} else {	//faked referrer string
			$badhost=true;
		}
		if(!$badhost){
			//shortened URL is likely FAKED referrer string!
			if(!empty($referrer_host) && wassup_urlshortener_lookup($referrer_host)) $badhost=true;
			//a referrer with domain in all caps is likely spam
			elseif(preg_match('#https?\://[0-9A-Z\-\._]+\.([A-Z]{2,4})$#',$ref)>0) $badhost=true;
		}
	} //end elseif
	//#Assume any referrer name similar to "viagra/zanax/.." is spam and mark as such...
	if (!$badhost && !empty($referrer_host)) {
		$lines = array("ambien","ativan","blackjack","bukakke",
		"casino","cialis","ciallis", "celebrex","cumdripping",
		"cumeating","cumfilled","cumpussy","cumsucking",
		"cumswapping","diazepam","diflucan","drippingcum",
		"eatingcum","enhancement","finasteride","fioricet",
		"gabapentin","gangbang","highprofitclub","hydrocodone",
		"krankenversicherung","lamisil","latinonakedgirl",
		"levitra", "libido", "lipitor","lortab","melatonin",
		"meridia","NetCaptor","orgy-","phentemine",
		"phentermine","propecia","proscar","pussycum",
		"sildenafil","snowballing","suckingcum","swappingcum",
		"swingers","tadalafil","tigerspice","tramadol",
		"ultram-","valium","valtrex","viagra","viagara",
		"vicodin","xanax","xenical","xxx-","zoloft","zovirax",
		"zanax"
		);
		foreach ($lines as $badreferrer) {
			if (strstr($referrer_host,$badreferrer)!== FALSE){
				$badhost=true;
				break 1;
			}elseif(preg_match('#([^a-z]|^)(free|orgy|penis|porn)([^a-z])#',$referrer)>0){
				$badhost=true;
				break 1;
			}
		}
	}
	//check against lists of known bad hosts (spammers)
	if(!$badhost){
		if($hostname != $referrer_host){
			$badhost = wassup_badhost_lookup($referrer_host,$hostname);
		}elseif(!empty($hostname)){
			$badhost = wassup_badhost_lookup($hostname);
		}
	}
	return $badhost;
} //end wGetSpamRef

/**
 * Compare a hostname (and referrer host) arguments against a list of known spammers.
 * @param string(2)
 * @return boolean
 * @since v1.9
 */
function wassup_badhost_lookup($referrer_host,$hostname="") {
	global $wdebug_mode;

	if ($wdebug_mode) echo "\$referrer_host = $referrer_host.\n";
	$badhost=false;
	//1st compare against a list of recent referer spammers
	$lines = array(	'209\.29\.25\.180',
			'78\.185\.148\.185',
			'93\.90\.243\.63',
			'1\-free\-share\-buttons\.com',
			'amazon\-seo\-service\.com',
			'amsterjob\.com',
			'anonymizeme\.pro',
			'burger\-imperia\.com',
			'buttons\-for\-website\.com',
			'canadapharm\.atwebpages\.com',
			'candy\.com',
			'celebritydietdoctor\.com',
			'celebrity\-?diets\.(com|org|net|info|biz)',
			'chairrailmoldingideas\.com',
			'[a-z0-9]+\.cheapchocolatesale\.com',
			'cheapguccinow\.com',
			'chocolate\.com',
			'competmy24site\.com',
			'couplesresortsonline\.com',
			'creditcardsinformation\.info',
			'.*\.css\-build\.info',
			'.*dietplan\.com',
			'Digifire\.net',
			'disimplantlari\.(net|org)',
			'disimplantlari\.gen\.tr',
			'disteli\.org',
			'disteli\.gen\.tr',
			'diszirkonyum\.(net|org)',
			'diszirkonyum\.gen\.tr',
			'dogcareinsurancetips\.sosblog\.com',
			'dollhouserugs\.com',
			'dreamworksdentalcenter\.com',
			'e\-gibis\.co\.kr',
			'ebellybuttonrings\.blogspot\.com',
			'epuppytrain\.blogspot\.com',
			'estetik\.net\.tr',
			'exactinsurance\.info',
			'find1friend\.com',
			'footballleagueworld\.co\.uk',
			'freefarmvillesecrets\.info',
			'frenchforbeginnerssite\.com',
			'gameskillinggames\.net',
			'gardenactivities\.webnode\.com',
			'globalringtones\.net',
			'gofirstrow\.eu',
			'gossipchips\.com',
			'gskstudio\.com',
			'hearcam\.org',
			'hearthealth\-hpe\.org',
			'highheelsale\.com',
			'homebasedaffiliatemarketingbusiness\.com',
			'hosting37\d{2}\.com/',
			'howgrowtall\.(com|info)',
			'hundejo\.com',
			'hvd\-store\.com',
			'insurancebinder\.info',
			'internetserviceteam\.com',
			'intl\-alliance\.com',
			'it\.n\-able\.com',
			'justanimal\.com',
			'justbazaar\.com',
			'knowledgehubdata\.com',
			'koreanracinggirls\.com',
			'lacomunidad\.elpais\.com',
			'lactoseintolerancesymptoms\.net',
			'laminedis\.gen\.tr',
			'leadingleaders\.net',
			'lhzyy\.net',
			'lifehacklane\.com',
			'linkwheelseo\.net',
			'liquiddiet[a-z\-]*\.com',
			'locksmith[a-z\-]+\.org',
			'lockyourpicz\.com',
			'materterapia\.net',
			'menshealtharts\.com',
			'ip87\-97\.mwtv\.lv',
			'mydirtyhobbycom\.de',
			'myhealthcare\.com',
			'myoweutthdf\.edu',
			'odcadide\.iinaa\.net',
			'onlinemarketpromo\.com',
			'outletqueens\.com',
			'pacificstore\.com',
			'peter\-sun\-scams\.com',
			'pharmondo\.com',
			'pinky\-vs\-cherokee\.com',
			'pinkyxxx\.org',
			'[a-z]+\.pixnet\.net',
			'pizza\-imperia\.com',
			'pizza\-tycoon\.com',
			'play\-mp3\.com',
			'21[89]\-124\-182\-64\.cust\.propagation\.net',
			'propertyjogja\.com',
			'prosperent\-adsense\-alternative\.blogspot\.com',
			'qweojidxz\.com',
			'ragedownloads\.info',
			'rankings\-analytics\.com',
			'[a-z\-]*ringtone\.net',
			'rufights\.com',
			'scripted\.com',
			'seoindiawizard\.com',
			'share\-buttons\-for\-free\.com',
			'singlesvacationspackages\.com',
			'sitetalk\-revolution\.com',
			'smartforexsignal\.com',
			'springhouseboston\.org',
			'stableincomeplan\.blogspot\.com',
			'staphinfectionpictures\.org',
			'static\.theplanet\.com',
			'[a-z]+\-[a-z]+\-symptoms\.com',
			'thebestweddingparty\.com',
			'thik\-chik\.com',
			'thisweekendsmovies\.com',
			'top10\-way\.com',
			'uggbootsnewest\.net',
			'uggsmencheap\.com',
			'uggsnewest\.com',
			'unassigned\.psychz\.net',
			'ultrabait\.biz',
			'usedcellphonesforsales\.info',
			'vietnamvisa\.co',
			'[a-z\-\.]+vigra\-buy\.info',
			'vitamin\-d\-deficiency\-symptoms\.com',
			'vpn\-privacy\.org',
			'w3data\.co',
			'watchstock\.com',
			'web\-promotion\-services\.net',
			'wh\-tech\.com',
			'wholesalelivelobster\.com',
			'wineaccessories\-winegifts\.com',
			'wizzed\.com',
			'wordpressseo\-plugin\.info',
			'writeagoodcoverletter\.com',
			'writeagoodresume\.net',
			'yeastinfectionsymptomstreatments\.com'
			);
	foreach($lines as $spammer) {
		if(!empty($spammer)){
		if(preg_match('#^'.$spammer.'$#',$referrer_host)>0){
			// found it!
			$badhost=true;
			break 1;
		}elseif(!empty($hostname) && preg_match('#(^|\.)'.$spammer.'$#i',$hostname)>0){
			$badhost=true;
			break 1;
		}
		}
	}
	//2nd check against a customized list of spammers in a file...removed in v1.9.1 because spammer lists are obsolete
	return $badhost;
} //end wassup_badhost_lookup()

/**
 * Returns true when hostname is from a known url shortener domain
 * @since v1.9
 * @param string
 * @return boolean
 */
function wassup_urlshortener_lookup($urlhost){
	$is_shortenedurl=false;
	if(!empty($urlhost)){
		if(strpos($urlhost,'/')!==false){
			$hurl=parse_url($urlhost);
			if(!empty($hurl['host'])) $urlhost=$hurl['host'];
			else $urlhost="";
		}
	}
	if(!empty($urlhost)){
		//some urls from http://longurl.org/services and https://code.google.com/p/shortenurl/wiki/URLShorteningServices (up to "m")
		$url_shorteners=array(
			'0rz.tw','1url.com',
			'2.gp','2big.at','2.ly','2tu.us',
			'4ms.me','4sq.com','4url.cc',
			'6url.com','7.ly',
			'a.gg','adf.ly','adjix.com','alturl.com','amzn.to',
			'b23.ru','bcool.bz','binged.it','bit.do','bit.ly','budurl.com',
			'canurl.com','chilp.it','chzb.gr','cl.ly','clck.ru','cli.gs','coge.la','conta.cc','cort.as','cot.ag','crks.me','ctvr.us','cutt.us',
			'dlvr.it','durl.me','doiop.com',
			'fon.gs',
			'gaw.sh','gkurl.us','goo.gl',
			'hj.to','hurl.me','hurl.ws',
			'ikr.me','is.gd',
			'j.mp','jdem.cz','jijr.com',
			'kore.us','krz.ch',
			'l.pr','lin.io','linkee.com','ln-s.ru','lnk.by','lnk.gd','lnk.ly','lnk.ms','lnk.nu','lnkd.in','ly.my',
			'migre.me','minilink.org','minu.me','minurl.fr','moourl.com','mysp.in','myurl.in',
			'ow.ly',
			'shorte.st','shorturl.com','shrt.st','shw.me','snurl.com','sot.ag','su.pr','sur.ly',
			't.co','tinyurl.com','tr.im',
		);
		if(in_array($urlhost,$url_shorteners)){
			$is_shortenedurl=true;
		}elseif(preg_match('/(^|[^0-9a-z\-_])(tk|to)\.$/',$urlhost)>0){
			$is_shortenedurl=true;
		}
	}
	return $is_shortenedurl;
} //end wassup_urlshortener_lookup

/**
 * return a validated ip address from http header
 * @since v1.9
 * @param string
 * @return string
 */
function wassup_get_clientAddr($ipAddress=""){
	return wassupIP::get_clientAddr($ipAddress);
} //end wassup_get_clientAddr

// lookup the hostname from an ip address via cache or via gethostbyaddr command @since v1.9
function wassup_get_hostname($IP=""){
	return wassupIP::get_hostname($IP);
} //end wassup_get_hostname

// Return a single ip (the client IP) from a comma-separated IP address with no ip validation. @since v1.9
function wassup_clientIP($ipAddress){
	if(!empty($ipAddress)) return wassupIP::clientIP($ipAddress);
	return false;
}

//return 1st valid IP address in a comma-separated list of IP addresses -Helene D. 2009-03-01
function wValidIP($multiIP) {
	if(!empty($multiIP)) return wassupIP::validIP($multiIP);
	return false;
} //end function wValidIP

/**
 * Add Wassup meta tag and javascripts to html document head
 * -add javascript function to retrieve screen resolution
 */
function wassup_head() {
	global $wassup_options, $wscreen_res;
	//Since v.1.8: removed meta tag to reduce plugin bloat
	//print '<meta name="wassup-version" content="'.WASSUPVERSION.'" />'."\n";
	//add screen resolution javascript to blog header
	$sessionhash=$wassup_options->whash;
	if($wscreen_res == "" && isset($_COOKIE['wassup_screen_res'.$sessionhash])){
		$wscreen_res=esc_attr(trim($_COOKIE['wassup_screen_res'.$sessionhash]));
		if($wscreen_res == "x") $wscreen_res="";
	}
	if(empty($wscreen_res) && isset($_SERVER['HTTP_UA_PIXELS'])){
		//resolution in IE/IEMobile header sometimes
		$wscreen_res=str_replace('X',' x ',$_SERVER['HTTP_UA_PIXELS']);
	}
	//get visitor's screen resolution with javascript and a cookie
	if(empty($wscreen_res) && !isset($_COOKIE['wassup_screen_res'.$sessionhash])){
		echo "\n";?>
<script type="text/javascript">
//<![CDATA[
function wassup_get_screenres(){
	var screen_res = screen.width + " x " + screen.height;
	if(screen_res==" x ") screen_res=window.screen.width+" x "+window.screen.height;
	if(screen_res==" x ") screen_res=screen.availWidth+" x "+screen.availHeight;
	if (screen_res!=" x "){document.cookie = "wassup_screen_res<?php echo $sessionhash;?>=" + encodeURIComponent(screen_res)+ "; path=/; domain=" + document.domain;}
}
wassup_get_screenres();
//]]>
</script><?php
	}
} //end function wassup_head

/**
 * Output Wassup tag and javascripts in html document footer.
 * -call screen resolution javascript function for IE users
 * -put a timestamp in page footer as page caching test
 * -output any stored debug data
 */
function wassup_foot() {
	global $wassup_options, $wscreen_res, $wdebug_mode;
	//'screen_res' javascript function called in footer because Microsoft browsers (IE/Edge) do not report screen height or width until after document body starts rendering. @since v1.8.2
	$sessionhash=$wassup_options->whash;
	if(empty($wscreen_res) && !isset($_COOKIE['wassup_screen_res'.$sessionhash])){
		$ua=(!empty($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:"");
		if(strpos($ua,'MSIE')>0 || strpos($ua,'rv:11')>0 || strpos($ua,'Edge/')>0 || stristr($_SERVER['REQUEST_URI'],'login.php')!==false){
			echo "\n";?>
<script type="text/javascript">wassup_get_screenres();</script>
<?php
		} //end if MSIE
	} //end if 'wscreen_res'
	//Output a comment with a current timestamp to verify that page is not cached (i.e. visit is being recorded).
	echo "\n<!-- <p class=\"small\"> WassUp ".WASSUPVERSION." ".__("timestamp","wassup").": ".date('Y-m-d h:i:sA T')." (".gmdate('h:iA',time()+(get_option('gmt_offset')*3600)).")<br />\n";
	echo __("If above timestamp is not current time, this page is cached","wassup").".</p> -->\n";
	//output any debug_output stored in wassup_meta @since v1.9.1
	if($wdebug_mode){
		$wassup_key=wassup_clientIP($_SERVER['REMOTE_ADDR']);
		$debug_output=wassupDb::get_wassupmeta($wassup_key,'_debug_output');
		if(!empty($debug_output) && is_string($debug_output)){
			echo $debug_output;
		}
	}
} //end wassup_foot

//-------------------------------------------------
//### Utility functions
/**
 * Schedule wassup cleanup tasks in wp-cron.
 *  - called at plugin install/reactivation, settings reset-to-default, and when recording setting is changed to wassup_active=1
 * @since v1.9.1
 */
function wassup_cron_startup(){
	global $wp_version,$wassup_options;
	if(version_compare($wp_version,'3.0','>=')){
		if(!has_action('wassup_scheduled_cleanup')){
			add_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
		}
		wp_schedule_event(time()+1800,'hourly','wassup_scheduled_cleanup');
		//do regular purge of old records
		if(!empty($wassup_options->delete_auto) && $wassup_options->delete_auto!="never"){
			if(!has_action('wassup_scheduled_purge')){
				add_action('wassup_scheduled_purge','wassup_auto_cleanup');
			}
			//do purge at 2am
			$starttime=strtotime("tomorrow 2:00am");
			wp_schedule_event($starttime,'daily','wassup_scheduled_purge');
		}
	}
}
/**
 * Delete all scheduled tasks from wp-cron.
 *  - called at plugin deactivation, settings reset-to-default, and when recording setting is changed to wassup_active=0
 * @since v1.9.1
 */
function wassup_cron_terminate(){
	global $wp_version;
	//delete scheduled tasks from wp-cron
	remove_action('wassup_scheduled_cleanup','wassup_temp_cleanup');
	remove_action('wassup_scheduled_purge','wassup_auto_cleanup');
	remove_action('wassup_scheduled_dbtasks',array('wassupDb','scheduled_dbtask'));
	if(version_compare($wp_version,'3.0','>=')){
		$wassup_network_settings=get_site_option('wassup_network_settings');
		wp_clear_scheduled_hook('wassup_scheduled_cleanup');
		wp_clear_scheduled_hook('wassup_scheduled_purge');
		wp_clear_scheduled_hook('wassup_scheduled_dbtasks');
		if(!is_multisite() || is_main_site() || empty($wassup_network_settings['wassup_table'])){
			remove_action('wassup_scheduled_optimize',array('wassupDb','scheduled_dbtask'));
			wp_clear_scheduled_hook('wassup_scheduled_optimize');
		}
	}
}
/** For cleanup of temp records via wp-cron. @since v1.9 */
function wassup_temp_cleanup($dbtasks=array()){
	global $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	if(!empty($wassup_options) && $wassup_options->is_recording_active()){
		//do scheduled cleanup
		if(empty($dbtasks)){
			wassupDb::temp_cleanup();
		}else{
			//do non-scheduled cleanup
			wassupDb::scheduled_dbtask(array('dbtasks'=>$dbtasks));
		}
	}
}
/** For automatic delete of old records via wp-cron. @since v1.9 */
function wassup_auto_cleanup(){
	global $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	//check that user can do auto delete
	if(!empty($wassup_options) && $wassup_options->is_recording_active()){
		if(!empty($wassup_options->delete_auto) && $wassup_options->delete_auto!="never"){
			//check last auto delete timestamp to ensure purge occurs only once a day
			$wassup_table=$wassup_options->wassup_table;
			$now=time();
			$delete_auto_time=wassupDb::get_wassupmeta($wassup_table,'_delete_auto');
			if(empty($auto_delete_time) || $auto_delete_time < $now - 24*3600){
				wassupDb::auto_cleanup();
			}
		}
	}
}
/**
 * Detect signs of script injection and hack attempts in http_headers: request_uri, http_referer
 * @author Helene D. <http://helenesit.com>
 * @since version 1.8, updated for HTTP_REFERER and parameter in v1.9
 * @param string
 * @return boolean
 */
function wIsAttack($http_target="") {
	global $wdebug_mode;
	$is_attack=false;
	$targets=array();
	if(!empty($http_target)){
		if(is_array($http_target)) $targets=$http_target;
		else $targets[]=$http_target;
	}else{
		$targets[]=$_SERVER['REQUEST_URI'];
		if(!empty($_SERVER['HTTP_REFERER'])) $targets[]=$_SERVER['HTTP_REFERER'];
	}
	if(!empty($targets)){
		foreach ($targets AS $target) {
			if(preg_match('#["<>`^]|[^/][~]|\.\*|\*\.#',str_replace(array('&lt;','&#60;','%3C','&rt;','&#62;','%3E','&quot;','%5E'),array("<","<","<",">",">",">","\"",'^'),$target))>0 || (preg_match('/[\\\']/',str_replace('%5C','\\',$target))>0 && preg_match('/((?:q|search|s|p)\=[^\\\'&=]+)([\\\']*\'[^\'&]*)&/',str_replace('%5C','\\',$target))==0)){
				$is_attack=true;break;
			}elseif(preg_match('#(\.+[\\/]){3,}|[<>&\\\|:\?$!]{2,}|[+\s]{5,}|(%[0-9A-F]{2,3}){5,}#',str_replace(array('%20','%21','%24','%26','%2E','%2F','%3C','%3D','%3F','%5C'),array(' ','!','$','&','+','.','/','<','>','?','\\'),$target))>0){
				$is_attack=true;break;
			}elseif(preg_match('/(?:^|[^a-z_\-])(select|update|delete|alter|drop|union|create)[ %&].*(?:from)?.*wp_\w+/i',str_replace(array('\\','&#92;','"','%22','&#34;','&quot;','&#39;','\'','`','&#96;'),'',$target))>0){
				$is_attack=true;break;
			}elseif(preg_match('#([\<\;C](script|\?|\?php)[^a-z0-9])|(\.{1,2}/){3,}|\=\.\./#i',$target)>0 || preg_match('/[^a-z0-9_\/\-](function|script|window|cookie)[^a-z0-9_\- ]/i',$target)>0 || preg_match('/[^0-9A-Za-z]+(GET|POST)[^0-9A-Za-z]/',str_replace(array('%20','%2B'),array(' ','+'),$target))>0){
				$is_attack=true;break;
			}elseif(preg_match('/[^a-z_\-](dir|href|location|path|document_root.?|rootfolder)(\s|%20)?\=/i',$target)>0){
				$is_attack=true;break;
			}elseif(preg_match('/\.(bat|bin|cfm|cmd|exe|ini|msi||[cr]?sh)([^a-z0-9]+|$)/i',$target)>0 || (preg_match('/\.dll(^a-z0-9_\-]+|$)/',$target)>0 && strpos($target,'.att.net/')===false) || preg_match('/[^0-9a-z_]setup\.[a-z]{2,4}([^0-9a-z]+|$)/',$target)>0){
				$is_attack=true;break;
			}elseif(preg_match('#[\\/](dev|drivers?|etc|program\sfiles|root|system|system32|windows)[/\\%&]#i',str_replace('%20',' ',$target))>0 || preg_match('#(c|file)\:[\\/]+.*install#i',$target)>0){
				$is_attack=true;break;
			}elseif(preg_match('/[^a-z0-9$%][$`%]?([a-km-rt-z_][a-z0-9_\-]+)[`%]?\s?\=\s?\-[190x]+/i',str_replace(array('&36;','%24','%20','&#96;','%60','%3D','&#61;','%2D','&#45;'),array('$','$',' ','`','`','=','=','-','-'),$target))>0){
				$is_attack=true;break;
			}elseif(preg_match('/[^a-z0-9_](admin|administrator|superuser|root|uid|username|user_?id)\=[-&%]/i',$target)>0 || preg_match('/(admin|administrator|id|root|user)\=(-1|0[x&]|0$)/',$target)>0){
				$is_attack=true;break;
			}elseif(preg_match('/[^0-9a-z_][\$\[`]+/',$target)>0 || (preg_match('/[{}]/',$target)>0 && strpos($target,'.asp')===false) || preg_match('/(&#0?37;|&amp;#0?37;|&#0?38;#0?37;|%)(?:[01][0-9A-F]|7F)/',$target)>0){
				$is_attack=true;break;
			}
		} //end foreach
	} //end if targets
	return $is_attack;
} //end wIsAttack

//-------------------------------------------------
//### Website content functions
// START initializing Widget
function wassup_widget_init(){
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	$wassup_widget_classes=array(
		'wassup_onlineWidget',
		'wassup_topstatsWidget',
	);
	if(!class_exists('wassup_onlineWidget')) include_once(WASSUPDIR.'/widgets/widgets.php');
	foreach($wassup_widget_classes as $wwidget){
		if(!empty($wwidget) && class_exists($wwidget)){
			if(function_exists('register_widget')) register_widget($wwidget);
			elseif(function_exists('wassup_compat_register_widget')) wassup_compat_register_widget($wwidget);	//compatibility function
		}
	}
}

/**
 * TEMPLATE TAG: wassup_sidebar
 * Displays Wassup Current Visitors Online widget directly from "sidebar.php" template or from a page template
 * Usage: wassup_sidebar('1:before_widget_tag','2:after_widget_tag','3:before_title_tag','4:after_title_tag','5:title','6:list css-class','7:max-width in chars','8:top_searches_limit, 9:top_referrers_limit, 10:top_browsers_limit, 11:top_os_limit)
 */
function wassup_sidebar($before_widget='',$after_widget='',$before_title='',$after_title='',$wtitle='',$wulclass='',$wchars=0,$wsearchlimit=0,$wreflimit=0,$wtopbrlimit=0,$wtoposlimit=0){
	global $wpdb,$wassup_options,$wdebug_mode;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	if(!function_exists('wassup_widget_get_cache')){
		include_once(WASSUPDIR.'/widgets/widget_functions.php');
	}
	if(empty($before_widget) || empty($after_widget) || strpos($before_widget,'>')===false || strpos($after_widget,'</')===false){
		$before_widget='<div id="wassup_sidebar" class="widget wassup-widget">';
		$after_widget='</div>';
	}
	if(empty($before_title) || empty($after_title) || strpos($before_title,'>')===false || strpos($after_title,'</')===false){
		$before_title='<h2 class="widget-title wassup-widget-title">';
		$after_title='</h2>';
	}
	if($wtitle!="") $title=$wtitle;
	else $title=__("Visitors Online","wassup");
	if($wulclass!="" && preg_match('/([^a-z0-9\-_]+)/',$wulclass)>0) $wulclass=""; //no special chars allowed
	if($wulclass!="") $ulclass=' class="'.$wulclass.'"';
	else $ulclass="";
	$chars=(int)$wchars;
	$cache_key="_online";
	//check for cached 'wassup_sidebar' html
	$widget_html=wassup_widget_get_cache('wassup_sidebar',$cache_key);
	if(empty($widget_html)){
		//show widget stats only when WassUp is active
		if(empty($wassup_options) || !$wassup_options->is_recording_active()){
			return;	//nothing to do
		}
		//base widget info
		$widget_html="\n".$before_widget;
		if(!empty($title)) $widget_html.='
	'.$before_title.$title.$after_title;
		$widget_html .='
	<p class="small">'.__("No Data","wassup").'</p>'.wassup_widget_foot_meta().$after_widget;
		//calculate widget users online and top stats data
		$online_html="";
		$top_html="";
		$instance=array(
				'title'=>"",
				'ulclass'=>$wulclass,
				'chars'=>$chars,
				'online_total'=>1,
				'online_loggedin'=>1,
				'online_comauth'=>1,
				'online_anonymous'=>1,
				'online_other'=>1,
				'top_searches'=>(int)$wsearchlimit,
				'top_referrers'=>(int)$wreflimit,
				'top_browsers'=>(int)$wtopbrlimit,
				'top_os'=>(int)$wtoposlimit,
		);
		//get online counts
		$html=wassup_widget_get_online_counts($instance);
		if(!empty($html)){
			$online_html= "\n".$before_widget;
			if(!empty($title)) $online_html.='
	'.$before_title.$title.$after_title;
			$online_html .='
	<ul'.$ulclass.'>
	'.$html.'
	</ul>'.wassup_widget_foot_meta().$after_widget;
		}
		//get top stats
		if($instance['top_searches']>0 || $instance['top_referrers']>0 || $instance['top_browsers']>0 || $instance['top_os']>0){
			$to_date=current_time('timestamp');
			$from_date=$to_date-24*60*60;
			$i=0;
			foreach(array('searches','referrers','browsers','os') AS $item){
				$html="";
				$limit=$instance['top_'.$item];
				if($limit >0) $html=wassup_widget_get_topstat($item,$limit,$chars,$from_date);
				if(!empty($html)){
					$title=$before_title.wassup_widget_stat_gettext($item).$after_title;
					if($i>0) $top_html .="\n".$after_widget;
					$top_html .="\n".$before_widget;
					$top_html .='
	'.$title.'
	<ul'.$ulclass.'>'.$html.'
	</ul>';
					$i++;
				}
			} //end foreach
			//append footer meta to end of widget
			if(!empty($top_html)) $top_html .=wassup_widget_foot_meta().$after_widget;
		} //end if top_searches>0
		//cache the new sidebar widget data
		if(!empty($top_html) || !empty($online_html)){
			$widget_html=$top_html.$online_html;
			$refresh=1;
			$cacheid=wassup_widget_save_cache($widget_html,'wassup_sidebar',$cache_key,$refresh);
		}
	} //end if widget_html
	echo "\n".'<div class="wassup_sidebar">'."\n";
	echo wassup_widget_css(true); //true==embed widget style
	echo $widget_html;
	echo "\n".'</div>';
} //end wassup_sidebar
//-------------------------------------------------
//## Add essential hooks after functions have been defined
//uninstall hook for complete plugin removal from WordPress
register_activation_hook($wassupfile,'wassup_install');
if(function_exists('register_uninstall_hook')){
	register_uninstall_hook($wassupfile,'wassup_uninstall');
}
unset($wassupfile); //to free memory
wassup_start();	//start WassUp
?>

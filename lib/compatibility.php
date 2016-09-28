<?php
/**
 * Checks Wordpress and PHP compatibility and loads compatibility functions as needed.
 *
 * IMPORTANT NOTE: this module is loaded by 'wassup_init' function before the WASSUPURL constant is set and before the 'wassup_options' global is set.
 * Don't use WASSUPURL constant or the $wassup_options global variable here and don't call 'wassup_init' to set them!
 *
 * @package WassUp Real-time Analytics
 * @subpackage	compatibility.php module
 * @since:	v1.9.1
 * @author:	helened <http://helenesit.com>
 */
//-------------------------------------------------
//# No direct requests for this plugin module
$wfile=preg_replace('/\\\\/','/',__FILE__); //for windows
//abort if this is direct uri request for file
if((!empty($_SERVER['PHP_SELF']) && preg_match('#'.preg_quote($_SERVER['PHP_SELF']).'$#',$wfile)>0) || 
   (!empty($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME'])===realpath($wfile))){
	//try track this uri request
	if(!headers_sent()){
		//triggers redirect to 404 error page so Wassup can track this attempt to access itself (original request_uri is lost)
		header('Location: /?p=404page&err=wassup403'.'&wf='.basename($wfile));
		exit;
	}else{
		//'wp_die' may be undefined here
		die('<strong>Sorry. Unable to display requested page.</strong>');
	}
	exit;
//abort if no WordPress
}elseif(!defined('ABSPATH') || empty($GLOBALS['wp_version'])){
	//'wp_die' is undefined here
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0?37;|&amp;#0?37;|&#0?38;#0?37;|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
$wassup_compatlib=dirname($wfile).'/compat-lib';
//-------------------------------------------------
//Check WordPress version and load Wordpress-compatibility modules
if(version_compare($GLOBALS['wp_version'],'3.1','<')){
	if(file_exists($wassup_compatlib.'/compat_wp.php')){
		require_once($wassup_compatlib.'/compat_wp.php');
	}else{
		wp_die(__("Sorry, WassUp compatibility library is required for Wassup to run in your Wordpress setup.","wassup"));
	}
}
if(version_compare($GLOBALS['wp_version'],'4.5','<')){
	if(file_exists($wassup_compatlib.'/compat_functions.php')){
		include_once($wassup_compatlib.'/compat_functions.php');
	}
}
//-------------------------------------------------
//Check PHP version and load PHP-compatibility module if needed
if(version_compare(PHP_VERSION,'5.2','<')){
	if(file_exists($wassup_compatlib.'/compat_php.php')){
		require_once($wassup_compatlib.'/compat_php.php');
	}else{
		wp_die(__("Sorry, WassUp compatibility library is required for Wassup to run on your server.","wassup"));
	}
}
unset($wfile,$wassup_compatlib); //to free memory

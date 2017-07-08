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
	exit;
//abort if no WordPress
}elseif(!defined('ABSPATH') || empty($GLOBALS['wp_version'])){
	//show escaped bad request on exit
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
//-------------------------------------------------
/**
 * Return true if this Wordpress version is compatible with this Wassup
 * @since v1.9.4
 */
function wassup_check_compatibility(){
	global $wp_version;
	$php_vers=phpversion();
	$wassup_compatlib=WASSUPDIR.'/lib/compat-lib';
	$is_compatible=true;
	//check for minimum Wordpress compatibility
	if(version_compare($wp_version,'4.5','<') || version_compare($php_vers,'5.2','<')){
		if(version_compare($wp_version,'2.2','<')){
			$is_compatible=false;
		}elseif(version_compare($wp_version,'3.1','<') && function_exists('is_multisite') && is_multisite()){
			$is_compatible=false;
		}else{
			$is_compatible=wassup_load_compat_modules();
		}
	}
	return $is_compatible;
}
/** Load compatibility modules for Wordpress and PHP - @since v1.9.1 */
function wassup_load_compat_modules(){
	global $wp_version;
	$php_vers=phpversion();
	$is_compatible=true;
	$wassup_compatlib=WASSUPDIR.'/lib/compat-lib';
	if(!file_exists($wassup_compatlib.'/compat_wp.php') && version_compare($wp_version,'3.1','<')){
		$is_compatible=false;
	}elseif(!file_exists($wassup_compatlib.'/compat_wassup.class.php') && version_compare($php_vers,'5.2','<')){
		$is_compatible=false;
	}else{
		//Wordpress 2.8-3.1 & PHP 4.1-5.2 compatibility
		if(version_compare($wp_version,'3.1','<')){
			require_once($wassup_compatlib.'/compat_wp.php');
			include_once($wassup_compatlib.'/compat_functions.php');
			//load 'Wassup_Widget' base widget without the 'WP_Widget' parent class
			if(version_compare($wp_version,'2.8','<')){
				if(!class_exists('Wassup_Widget')){
					include_once($wassup_compatlib.'/compat_widget.php');
				}
			}
		//Wordpress 3.1-4.5 & PHP 4.1-5.2 compatibility
		}elseif(version_compare($wp_version,'4.5','<') || version_compare($php_vers,'5.2','<')){ 
			if(file_exists($wassup_compatlib.'/compat_functions.php')){
				include_once($wassup_compatlib.'/compat_functions.php');
			}
		}
		//PHP 4.1-5.2 compatibility
		if(version_compare($php_vers,'5.2','<')){
			if(!class_exists('wassupOptions')){
				include_once($wassup_compatlib.'/compat_wassup.class.php');
			}
		}
	}
	return $is_compatible;
} //end wassup_load_compat_modules
/**
 * Show a message if this Wordpress version is incompatible with this copy of Wassup
 * @since v1.9.1
 */
function wassup_show_compat_message(){
	global $wp_version;
	$msg="";
	if(version_compare($wp_version,'2.2','<')){
		$msg= __("Sorry, WassUp requires WordPress 2.2 or higher to work","wassup");
	}else{
		$php_vers=phpversion();
		$wassup_compatlib=WASSUPDIR.'/lib/compat-lib';
		$download_link='<a href="https://github.com/michelem09/wassup/releases/tag/v'.WASSUPVERSION.'">GitHub</a>';
		if(version_compare($wp_version,'3.1','<')){
			if(!file_exists($wassup_compatlib.'/compat_wp.php')){
				$msg= __("WARNING! WassUp's backward compatibility modules are missing.","wassup");
				$msg .= ' '.sprintf(__('Download and install the full version of Wassup with compatibility library included directly from %s.','wassup'),$download_link);
			}
			//multisite compatibility message @since v1.9.2
			//WassUp works only in WP3.1 or higher for multisite 
			if(function_exists('is_multisite') && is_multisite()){
				$msg =__("Sorry, WassUp requires WordPress 3.1 or higher to work in multisite setups","wassup");
			}
		}elseif(version_compare($php_vers,'5.2','<') && !file_exists($wassup_compatlib.'/compat_wassup.class.php')){
			$msg= __("WARNING! WassUp's PHP compatibility modules are missing.","wassup");
			$msg .= ' '.sprintf(__('Download and install the full version of Wassup with compatibility library included directly from %s.','wassup'),$download_link);
		}
	}
	if(!empty($msg)){
		if(version_compare($wp_version,'4.1','>=')) $mstyle='class="notice notice-warning is-dismissible"';
		else $mstyle='class="fade error" style="padding:1em;"';
		echo '<div '.$mstyle.'>'.$msg.'</div>';
	}
}
?>

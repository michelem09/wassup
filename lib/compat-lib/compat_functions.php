<?php
/**
 * Additional functions and action hooks required for Wassup to run in older versions of Wordpress
 *
 * IMPORTANT NOTE: this module is loaded by 'wassup_init' function before the WASSUPURL constant is set and before the 'wassup_options' global is set.
 * Don't use WASSUPURL constant or $wassup_options global variable here and don't call 'wassup_init' function to set them!
 *
 * @package WassUp Real-time Analytics
 * @subpackage	compat_functions.php module
 * @since:	v1.8
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
//abort if no WordPress
}elseif(!defined('ABSPATH') || empty($GLOBALS['wp_version'])){
	//show escaped bad request on exit
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
//-------------------------------------------------
//nothing to do here
if(version_compare($GLOBALS['wp_version'],'4.5','>=')){
	return;
}
//Compatibility functions and action hooks for Wassup pages
$wassup_compatlib=dirname($wfile);
if(!empty($_GET['page']) && stristr($_GET['page'],'wassup')!==FALSE){
if(version_compare($GLOBALS['wp_version'],'4.5','<')){
	/** Load latest 'jquery.js' and 'jquery-migrate.js' in Wassup */
	function wassup_compat_add_scripts(){
		$compatlib=dirname(preg_replace('/\\\\/','/',__FILE__));
		$wassupurl=plugins_url(basename(dirname(dirname($compatlib))));
		//use newer 'jquery.js' version in Wassup
		if(file_exists($compatlib.'/js/jquery.js')){
			wp_deregister_script('jquery');	
			wp_register_script('jquery',$wassupurl.'/lib/compat-lib/js/jquery.js',FALSE,'1.12.4'); 
		}
		//use newer 'jquery-migrate.js' in Wassup
		if(file_exists($compatlib.'/js/jquery-migrate.js')){
			wp_deregister_script('jquery-migrate');	
			wp_register_script('jquery-migrate',$wassupurl.'/lib/compat-lib/js/jquery-migrate.js',array('jquery'),'1.4.1');
		}
		if(!empty($_GET['page']) && $_GET['page'] == 'wassup-options'){
			//use wassup's copy of 'jqueryui.js'
			wp_deregister_script('jqueryui');
			wp_deregister_script('jquery-ui-core');	
			wp_deregister_script('jquery-ui-widget');
			wp_deregister_script('jquery-ui-tabs');	
			wp_deregister_script('jquery-ui-dialog');
			wp_enqueue_script('jqueryui', $wassupurl.'/lib/compat-lib/js/jquery-ui/js/jquery-ui.min.js', array('jquery'), '1.10.4');
		}
	} //end wassup_compat_add_scripts
	add_action('admin_enqueue_scripts','wassup_compat_add_scripts',11);

	/** Add Wassup.css and tags with minor changes by WP version */
	function wassup_compat_add_css(){
		global $wp_version,$wdebug_mode;
		$compatlib=dirname(preg_replace('/\\\\/','/',__FILE__));
		$wassupurl=plugins_url(basename(dirname(dirname($compatlib))));
		$wassup_settings=get_option('wassup_settings');
		$vers=$wassup_settings['wassup_version'];
		if($wdebug_mode) $vers .='b'.rand(0,9999);
		//add wassup stylesheets after other stylesheets
		if(version_compare($wp_version,'2.8','<')){
			echo '<link rel="stylesheet" href="'.$wassupurl.'/css/wassup.css?ver='.$vers.'" type="text/css" />'."\n";
			//override some default css settings for old WP versions
			echo '<style type="text/css">
.wassup-wp-legacy #wassup-message{padding-left:35px;}
</style>';
		}elseif(version_compare($wp_version,'4.2','<') && version_compare($wp_version,'3.3','>')){
			echo '
<style type="text/css">
#wassup-screen-links{margin-top:-2px;}
</style>';
		}
	}
	add_action('admin_head','wassup_compat_add_css',13);
} //end if wp_version < 4.5

if(version_compare($GLOBALS['wp_version'],'3.0','<')){
	/** add an 'ajaxurl' definition to Wassup javascripts */
	function wassup_compat_embed_scripts(){
		global $wp_version;
		if(version_compare($wp_version,'2.7','<')){
			$wassupfolder=basename(dirname(dirname(dirname(__FILE__))));
			$ajaxurl=admin_url('admin.php?page='.$wassupfolder);
		}else{
			$ajaxurl=admin_url('index.php?page=wassup-stats');
		}
		echo '<script type="text/javascript">var ajaxurl="'.$ajaxurl.'";</script>'."\n";
	}
	add_action('admin_head','wassup_compat_embed_scripts',11);
} //end if wp_version < 3.0
} //end if page==Wassup

//-------------------------------------------------
//Recreate missing action hooks in Wordpress @since v1.9.1
//Run missing action hooks via other Wordpress action hooks
if(version_compare($GLOBALS['wp_version'],'2.8','<')){
	/** run 'admin_enqueue_scripts' from 'admin_init' action hook */
	function wassup_compat_admin_preload(){
		 do_action('admin_enqueue_scripts');
	}
	add_action('admin_init','wassup_compat_admin_preload');
} //end if wp_version < 2.8

if(version_compare($GLOBALS['wp_version'],'2.5','<')){
	/** run 'admin_init' from 'init' action hook */
	function wassup_compat_preload(){
		if(is_admin()) do_action('admin_init');
	}
	//'init' actions already run at this point...so this script is called from 'wassup_preload' function
	add_action('init','wassup_compat_preload',12);

	/** run 'wp_dashboard_setup' from 'plugins_loaded' action hook */
	function wassup_compat_load(){
		if(empty($_GET['page']) && (substr($_SERVER['REQUEST_URI'],-10)=='/wp-admin/' || strpos($_SERVER['REQUEST_URI'],'index.php')>0)){
			do_action('wp_dashboard_setup');
		}
	}
	add_action('plugins_loaded','wassup_compat_load',11);
} //end if wp_version < 2.5

//-------------------------------------------------
//Widget compatibility functions and classes
if(version_compare($GLOBALS['wp_version'],'3.8','<')){
	/** Widget control form css adjustments by Wordpress version */
	function wassup_compat_widget_form_css(){
		global $wp_version,$wdebug_mode;
		$vers=WASSUPVERSION;
		if($wdebug_mode) $vers.='b'.rand(0,9999);
		//add stylesheet for Wordpress 2.2-2.8
		if(version_compare($wp_version,'2.8','<')){ ?>
<link rel="stylesheet" href="<?php echo WASSUPURL.'/css/wassup.css?ver='.$vers;?>" type="text/css" /><?php
			echo "\n";
		}?>
<style type="text/css"><?php
		//some css override styles
		if(version_compare($wp_version,'2.8','<')) echo "\n".'.wassup-widget-ctrl{margin:-15px -25px 0;}';
		else echo "\n".'.wassup-widget-ctrl{margin:-10px -11px 0;}';
		echo "\n".'.wassup-widget-ctrl td{padding:0;line-height:1.1em;}'."\n";?>
</style><?php
		echo "\n";
	}
	//load 'Wassup_Widget' base widget without the 'WP_Widget' parent class
	if(version_compare($GLOBALS['wp_version'],'2.8','<')){
		if(!class_exists('Wassup_Widget')){
			include_once($wassup_compatlib.'/compat_widget.php');
		}
	}
}
?>

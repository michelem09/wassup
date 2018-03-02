<?php
/**
 * Compatibility functions for old Wordpress versions (v2.3 - v3.1).
 *
 * Emulates missing Wordpress functions that Wassup requires to run.
 * Renamed from '/lib/compat_functions.php' @since v1.9
 *
 * @package WassUp Real-time Analytics
 * @subpackage	compat_wp.php module
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
if(version_compare($GLOBALS['wp_version'],'3.1','>')){
	return;
}
//define Wordpress 2.3 - 2.6 functions used in Wassup
if(version_compare($GLOBALS['wp_version'],'2.8','<')){
if(!function_exists('wp_safe_redirect')){ //added in Wordpress 2.3
	//just use wp_redirect in old Wordpress versions
	function wp_safe_redirect($location,$status="302"){
		wp_redirect($location,$status);
		exit;
	}
}
if(!function_exists('build_query')){	//added in Wordpress 2.3
	function build_query($data=array()){
		$query=false;
		if(function_exists('http_build_query')){ //PHP 5 function
			 $query=http_build_query($data);
		}else{
			foreach($data AS $key => $value){
				if(!empty($key) && preg_match('/^[0-9a-z\-_]+$/i',$key)>0){
				 	if(empty($query)) $query=$key;
					else $query .='&'.$key;
					if($value!="") $query .='='.urlencode($value);
				}
			}
		}
		return $query;
	}
}
if(!function_exists('like_escape')){	//added in Wordpress 2.5
	function like_escape($text){	//deprecated in Wordpress 4.0
		global $wpdb;
		if(method_exists($wpdb,'esc_like')) $escaped_text=$wpdb->esc_like($text);
		else $escaped_text=str_replace(array("%","_"),array("\\%","\\_"),trim($text));
		return $escaped_text;
	}
}
if(!function_exists('get_avatar')){	//added in Wordpress 2.5
	function get_avatar($userid=0,$imgsize=18){return "";}
}
if(!function_exists('has_action')){	//added in Wordpress 2.5
	function has_filter($tag,$function_to_check=false){
		$wp_filter=$GLOBALS['wp_filter'];
		$has=false;
		if(!empty($wp_filter[$tag])){
			foreach($wp_filter[$tag] as $callbacks){
				if(!empty($callbacks)){$has=true;break;}
			}
			if($has && $function_to_check!==false){
				$has=false;
				if(is_string($function_to_check)) $idx=$function_to_check;
				elseif(function_exists('_wp_filter_build_unique_id')) $idx=_wp_filter_build_unique_id($tag,$function_to_check,10);
				else $idx=false;
				if($idx!==false){
					foreach((array)array_keys($callbacks) as $priority){
						if(isset($callbacks[$priority][$idx])){
							$has=$priority;break;
						}
					}
				}
			}
		}
		return $has;
	}
	function has_action($tag,$function_to_check = false){
		return has_filter($tag,$function_to_check);
	}
}
if(!function_exists('add_thickbox')){	//added in Wordpress 2.5
	function add_thickbox(){
		$compatlib=dirname(preg_replace('/\\\\/','/',__FILE__));
		//register Wassup's thickbox.js
		if(file_exists($compatlib.'/js/thickbox/thickbox.js')){
			wp_enqueue_script('thickbox',$compatlib.'/js/thickbox/thickbox.js',array('jquery'),'3.1');
		}
		//thickbox style handled in 'compat_functions.php' module
	}
}
if(!function_exists('wp_enqueue_style')){	//added in Wordpress 2.6
	function wp_enqueue_style($name,$file="",$dep=array(),$vers=0){
		//do nothing...handled in 'compat_functions.php' module
		$do_nothing=1;
	}
}
if(!function_exists('site_url')){	//added in Wordpress 2.6
	function site_url($path='',$scheme=null){ //Wordpress install url
		//scheme not used in old Wordpress
		$url=get_option('siteurl');
		if(empty($url)) $url=get_option('home');
		if(!empty($path) && is_string($path) && strpos($path,'..')===false){
			$url .='/'.ltrim($path,'/');
		}
		return $url;
	}
	function admin_url($path=""){
		$url=site_url('/wp-admin/');
		if(!empty($path) && is_string($path) && strpos($path,'..')===false){
			$url .=ltrim($path,'/');
		}
		return $url;
	}
	function content_url($path=""){
		if(defined('WP_CONTENT_URL')){
			$url=WP_CONTENT_URL.'/';
		}else{
			$url=site_url('/wp-content/');
		}
		if(!empty($path) && is_string($path) && strpos($path,'..')===false){
			$url .=ltrim($path, '/');
		}
		return $url;
	}
	function includes_url($path="",$scheme=null){
		$url=site_url('/'.WPINC.'/',$scheme);
		if(!empty($path) && is_string($path) && strpos($path,'..')===false){
			$url .=ltrim($path, '/');
		}
		return $url;
	}
	function plugins_url($path=""){
		$url=content_url('/plugins/');
		if(!empty($path) && is_string($path) && strpos($path,'..')===false){
			$url .=ltrim($path, '/');
		}
		return $url;
	}
}
} //end if Wordpress < 2.8
//-------------------------------------------------
//define Wordpress 2.8 - 3.0 functions used in Wassup
if(version_compare($GLOBALS['wp_version'],'3.0','<')){
if(!function_exists('get_user_by')){	//added in Wordpress 2.8
	function get_user_by($ufield,$uvalue){
		$user=false;
		if(!empty($uvalue)){
			if($ufield=="login"){
				if(function_exists('get_userdatabylogin')) $user=get_userdatabylogin($uvalue);
			}elseif(is_numeric($uvalue)){
				$user=get_userdata($uvalue); //ID is default
			}
		}
		return $user;
	}
}
if(!function_exists('esc_attr')){	//added in Wordpress 2.8
	function esc_attr($text){return attribute_escape($text);}
	function esc_html($html){return wp_specialchars($html, ENT_QUOTES);}
	function esc_url($url,$protocols=null,$context='display'){
		$cleaned_url=clean_url($url,$protocols,$context);
		if(empty($cleaned_url) && !empty($url)){  //oops, clean_url chomp
			$cleaned_url=attribute_escape(strip_tags(html_entity_decode(wp_kses($url,array()))));
		}
		return $cleaned_url;
	}
	function esc_url_raw($url,$protocols=null){
		return esc_url($url,$protocols,'db');
	}
	function esc_sql($data){
		global $wpdb;
		if(empty($wpdb->use_mysqli)) return mysql_real_escape_string($data);
		else return mysqli_real_escape_string();
	}
}
if(!function_exists('delete_user_option')){	//added in Wordpress 3.0
	function delete_user_option($user_id,$option_name,$option_value=''){
		if(function_exists('delete_user_meta')) return delete_user_meta($user_id,$option_name);
		else return delete_usermeta($user_id,$option_name,$option_value);
	}
}
if(!function_exists('is_multisite')){	//added in Wordpress 3.0
	//multisite functions not applicable in old Wordpress versions
	function is_multisite(){return false;}
	function is_subdomain_install(){return false;}
	function is_main_site($site_id=null){return true;}
}
} //end if Wordpress < 3.0
//-------------------------------------------------
//define Wordpress 3.1 functions used in Wassup
if(!function_exists('is_network_admin')){	//added in Wordpress 3.1
	function is_network_admin(){
		if(isset($GLOBALS['current_screen'])) return $GLOBALS['current_screen']->in_admin('network');
		elseif(defined('WP_NETWORK_ADMIN')) return WP_NETWORK_ADMIN;
		return false;
	}
}
if(!function_exists('wp_dequeue_style')){	//added in Wordpress 3.1
	function wp_dequeue_style($name){
		//do nothing...handled in 'compat_functions.php' module
		$do_nothing=1;
	}
}
?>

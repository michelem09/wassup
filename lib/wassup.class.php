<?php
/**
 * Classes for managing Wassup settings, Wassup tables, and url links.
 *
 *   wassupOptions: displays and update plugin settings
 *   wassupDb     : manages custom tables
 *   wassupURI    : generates safe url links for display
 *
 * @package	WassUp Real-time Analytics
 * @subpackage	wassup.class.php module
 * @author	helened <http://helenesit.com>
 */
//abort if this is direct uri request for file
$wfile=preg_replace('/\\\\/','/',__FILE__); //for windows
if((!empty($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME'])===realpath($wfile)) ||
   (!empty($_SERVER['PHP_SELF']) && preg_match('#'.str_replace('#','\#',preg_quote($_SERVER['PHP_SELF'])).'$#',$wfile)>0)){
	//try track this uri request
	if(!headers_sent()){
		//triggers redirect to 404 error page so Wassup can track this attempt to access itself (original request_uri is lost)
		header('Location: /?p=404page&werr=wassup403'.'&wf='.basename($wfile));
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
unset($wfile);	//to free memory
//-------------------------------------------------
if (!class_exists('wassupOptions')) {
/**
 * Class for display, update, and validation of settings form data.
 * @author: helened <http://helenesit.com>
 */
class wassupOptions {
	/* general/detail settings */
	var $wassup_refresh = "3";	
	var $wassup_userlevel = "8";
	var $wassup_screen_res = "800";	
	var $wassup_default_type = "everything";	
	var $wassup_default_spy_type = "everything";
	var $wassup_default_limit = "10";
	var $wassup_top10;
	var $wassup_time_format = "24";
	var $wassup_time_period = "1";

	/* recording settings */
	var $wassup_active = "1";	
	var $wassup_loggedin = "1";
	var $wassup_admin = "1";
	var $wassup_spider = "1";
	var $wassup_exclude = "";
	var $wassup_exclude_host = "";	//for exclusion by hostname @since v1.9
	var $wassup_exclude_url = "";
	var $wassup_exclude_user = "";

	/* spam and malware settings */
	var $wassup_spamcheck = "1";
	var $wassup_spam = "1";
	var $wassup_refspam = "1";
	var $wassup_attack = "1";
	var $wassup_hack = "1";	
	var $refspam_whitelist="";	//new in v1.9.4: for incorrectly labeled referrer spam

	/* table/file management settings */
	var $wassup_table;
	var $wassup_dbengine = "";
	var $wassup_uninstall;	//for uninstall of wassup tables
	var $delete_auto = "never";
	var $delete_filter = "";
	var $wassup_optimize = "0";	//for scheduled optimization
	var $wassup_remind_mb = "100";
	var $wassup_remind_flag = "1";
	var $delayed_insert = "1";	//for use of "Delayed" option in MySQL INSERT command
	var $export_spam = "0";	//since v1.9.1: no spam in exported data
	var $export_omit_recid="0"; //since v1.9.1

	/* chart/map display settings */
	var $wassup_dashboard_chart = 0;
	var $wassup_chart = "1";
	var $wassup_chart_type = "2";
	var $wassup_geoip_map = "1";
	var $wassup_googlemaps_key;

	/* temporary action settings */
	var $whash = "";
	var $wassup_alert_message = "";	//to display alerts
	var $wassup_version = "";
	var $wassup_upgraded = 0;	//upgrade timestamp @since v1.9

	/**
	 * PHP4 constructor.
	 *
	 * optional argument to set default values for new/empty class vars @since v1.9
	 * @param boolean $add_defaults 
	 * @return void
	 *
	 */
	public function wassupoptions($add_defaults=false){
		if($add_defaults)$this->_initSettings();
		else $this->loadSettings();
	}
	/** loads current settings/initializes empty class vars. */
	private function _initSettings(){
		$settings=$this->getSettings(true);
		$this->loadSettings($settings);
	}
	/** loads default settings into class vars. */
	public function loadDefaults(){
		$defaults = $this->defaultSettings();
		$this->loadSettings($defaults);
	}
	/**
	 * Return array of default values or one default variable.
	 * @param string
	 * @return string|array
	 */
	public function defaultSettings($dsetting="") {
		global $wpdb,$wdebug_mode;
		$retvalue=false;
		//default user settings @since v1.9
		if($dsetting=="user_settings" || $dsetting=="wassup_user_settings"){
		$user_defaults=array(
			'detail_filter'	=>$this->wassup_default_type,
			'detail_chart'	=>$this->wassup_chart,
			'detail_limit'	=>$this->wassup_default_limit,
			'detail_time_period'=>$this->wassup_time_period,
			'from_date'	=>0,
			'to_date'	=>0,
			'spy_filter'	=>$this->wassup_default_spy_type,
			'spy_map'	=>$this->wassup_geoip_map,
			'ualert_message'=>"",
			'unonce'	=>rand(1,999999),
			'umark' 	=>"",
			'uip'   	=>"",
			'urecid'	=>0,
			'utimestamp'	=>0,
			'uwassupid'	=>"",
			'uversion'	=>WASSUPVERSION,
			);
			return $user_defaults;
		}
		//default top stats settings
		$top10_defaults = array(
			"toplimit"=>"10",
			"topsearch"=>"1",
			"topreferrer"=>"1",
			"toppostid"=>"1",
			"toprequest"=>"1",
			"topbrowser"=>"1",
			"topos"=>"1",
			"toplocale"=>"0",
			"topvisitor"=>"0",
			"topreferrer_exclude"=>"",
			"top_nofrontpage"=>"0",
			"top_nospider"=>"0",
			);
		if($dsetting=="top10" || $dsetting=="wassup_top10" || $dsetting=="top_stats"){
			return $top10_defaults;
		}
		//general default settings
		$defaults = array(
			'wassup_active'		=>"1",
			'wassup_loggedin'	=>"1",
			'wassup_admin'		=>"1",
			'wassup_spider'		=>"1",
			'wassup_attack'		=>"1",
			'wassup_hack'		=>"1",
			'wassup_spamcheck'	=>"1",
			'wassup_spam'		=>"1",
			'wassup_refspam'	=>"1",
			'refspam_whitelist'	=>"",
			'wassup_exclude'	=>"",
			'wassup_exclude_host'	=>"",
			'wassup_exclude_url'	=>"",
			'wassup_exclude_user'	=>"",
			'wassup_chart'		=>"1",
			'wassup_chart_type'	=>"2",
			'delete_auto'		=>"never",
			'delete_filter'		=>"",
			'wassup_remind_mb'	=>"100",
			'wassup_remind_flag'	=>"1",
			'wassup_refresh'	=>"3",
			'wassup_userlevel'	=>"8",
			'wassup_screen_res'	=>"800",
			'wassup_default_type'	=>"everything",
			'wassup_default_spy_type'=>"everything",
			'wassup_default_limit'	=>"10",
			'wassup_dashboard_chart'=>"0",
			'wassup_geoip_map'	=>"1",	//1=default value @since v1.9
			'wassup_googlemaps_key'	=>"",
			'wassup_time_format'	=>"24",
			'wassup_time_period'	=>"1",
			'wassup_alert_message'	=>"",
			'wassup_uninstall'	=>"0",
			'wassup_optimize'=>"0",
			'wassup_top10'	=>$top10_defaults,
			'whash' 	=>"",
			'wassup_table'	=>$wpdb->prefix . "wassup",
			'wassup_dbengine'=>"",
			'delayed_insert'=>"1",
			'export_spam'	=>"0",
			'export_omit_recid'=>"0",
			'wassup_version'=>"",
			'wassup_upgraded'=>0,
		);
		//use main site settings for default in multisite, except table name and engine @since v1.9
		if(is_multisite()){
			$network_settings=get_site_option('wassup_network_settings');
			if(!is_main_site() && !is_network_admin()){
				$main_site_settings=get_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings',$defaults);
				if(!empty($main_site_settings)){
					$defaults=$main_site_settings;
					if(!empty($network_settings['wassup_table'])) $defaults['wassup_table']=$network_settings['wassup_table'];
				}elseif(!empty($network_settings['wassup_table'])){
					$defaults['wassup_table']=$network_settings['wassup_table'];
				}
			}
		}
		//never discard google maps api key with "reset-to-default"
		if(!empty($this->wassup_googlemaps_key)){
			$defaults['wassup_googlemaps_key']= $this->wassup_googlemaps_key;
		}
		//never discard wassup_version' with "reset-to-default"
		if(!empty($this->wassup_version)){
			$defaults['wassup_version']= $this->wassup_version;
		}
		$wassupdb_installed=false;
		//never change 'wassup_table' with "reset-to-default" ..unless table doesn't exist or is shared network table
		if(!empty($this->wassup_table) && $this->wassup_table != $defaults['wassup_table'] && wassupDb::table_exists($this->wassup_table)){
			$defaults['wassup_table']= $this->wassup_table;
			$defaults['wassup_dbengine']= $this->wassup_dbengine;
			$defaults['wassup_optimize']= $this->wassup_optimize;
			$wassupdb_installed=true;
		}elseif(wassupDb::table_exists($defaults['wassup_table'])){
			$wassupdb_installed=true;
		}
		//reset table engine with reset-to-default ..after default wassup_table is set
		if($dsetting=='wassup_dbengine' || empty($defaults['wassup_dbengine'])){
			$tengine="";
			if($dsetting!='wassup_dbengine' && $defaults['wassup_table']== $this->wassup_table && !empty($this->wassup_dbengine)){
				$tengine=$this->wassup_dbengine;
			}elseif(!$wassupdb_installed){
				$result=$wpdb->get_results("SHOW VARIABLES LIKE 'storage_engine'",ARRAY_A);
				if(!empty($result) && !is_wp_error($result) && !empty($result[0]->Value)) $tengine=$result[0]->Value;
			}else{
				//$tengine=wassupDb::get_db_setting('engine',$defaults['wassup_table']); //TODO: find cause of mysql timeout error
				$result=$wpdb->get_results("SHOW VARIABLES LIKE 'storage_engine'",ARRAY_A);
				if(!empty($result) && !is_wp_error($result) && !empty($result[0]->Value)) $tengine=$result[0]->Value;
			}
			$defaults['wassup_dbengine']=$tengine;
		}
		//never change optimize schedule with reset-to-default ..unless table engine has changed
		if($dsetting=='wassup_optimize' || (empty($dbsetting) && $this->wassup_dbengine != $defaults['wassup_dbengine'])){
			$tengine=$defaults['wassup_dbengine'];
			//set optimization when for table engine's myisam, archive, or innodb with file-per-table option only
			if($tengine=="myisam" || $tengine=="archive"){
				$defaults['wassup_optimize']=strtotime("next Sunday 2:00am");
			}elseif($wassupdb_installed && wassupDb::is_optimizable_table($defaults['wassup_table'])){
				$defaults['wassup_optimize']=strtotime("next Sunday 2:00am");
			}else{
				$defaults['wassup_optimize']="0";
			}
		}else{
			$defaults['wassup_optimize']=$this->wassup_optimize;
		}
		//reset hash with reset-to-default
		$defaults['whash']=$this->whash; 
		if($dsetting=='whash' || empty($dsetting)){
			$defaults['whash']=$this->get_wp_hash();
		}
		//serialize top10 array for insert into wp_options
		if(empty($dsetting)) $defaults['wassup_top10']=maybe_serialize($top10_defaults);
		//check that can use delayed insert
		//$defaults['delayed_insert']=$this->delayed_insert;
		if(empty($dsetting) || $dsetting=="delayed_insert"){
			$tengine=$defaults['wassup_dbengine'];
			if (stristr($tengine,"isam")===false && $tengine !="archive"){
				$defaults['delayed_insert']="0";
			}else{
				$delayed_queue_size=wassupDb::get_db_setting("delayed_queue_size");
				if(!is_numeric($delayed_queue_size) || (int)$delayed_queue_size==0){
					$defaults['delayed_insert']="0";
				}else{
					$max_delayed_threads=wassupDb::get_db_setting("max_delayed_threads");
					if((int)$max_delayed_threads==0) $defaults['delayed_insert']="0";
				}
			}
		}
		//Return default value for "dsetting" argument, if any
		if(!empty($dsetting)){
			if ($dsetting == "user_setting" || $dsetting == "wassup_user_settings"){
				$retvalue = $user_defaults;
			}elseif ($dsetting == "top10" || $dsetting == "wassup_top10" || $dsetting == "top_stats"){
				$retvalue = $top10_defaults;
			}elseif(isset($defaults[$dsetting])){
				$retvalue = $defaults[$dsetting];
			}else{
				$retvalue = false;
			}
		}else{
			$retvalue=$defaults;
		}
		return $retvalue;
	} //end defaultSettings

	/** Load up class variables with wp_option settings. */
	public function loadSettings($settings=array()){
		if(empty($settings)){
			$settings=get_option('wassup_settings');
		}
		if(!empty($settings) && is_array($settings)){
			$this->_options2class($settings);
		}else{
			return false;
		}
		return true;
	}

	/**
	 * Retrieve wassup settings from 'wp_options' in an array.
	 *  - includes optional flag argument to add new settings and omit deprecated settings.
	 * @since version 1.8
	 * @param boolean $add_defaults
	 * @return array $settings
	 */
	public function getSettings($add_defaults=false){
		global $wpdb;
		$current_opts=get_option('wassup_settings');
		if($add_defaults || empty($current_opts)){
			//in multisite, use main site as defaults
			if(is_multisite() && !is_network_admin() && !is_main_site()){
				$default_opts=get_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings');
				if(empty($default_opts)) $default_opts=$this->defaultSettings();
				else $default_opts['wassup_table']=$this->defaultSettings('wassup_table');
			}else{
				$default_opts=$this->defaultSettings();
			}
			if(!empty($current_opts)){
				foreach($default_opts as $skey=>$defaultvalue){
					if(array_key_exists($skey,$current_opts))$settings[$skey]=$current_opts[$skey];
					else $settings[$skey]=$defaultvalue;
				}
			}else{
				$settings=$default_opts;
			}
		}else{
			$settings=$current_opts;
		}
		return $settings;
	} //end getSettings

	/** Save class vars as 'wassup_settings' in wp_options. */
	public function saveSettings() {
		global $wpdb;
		//only administrators can save to wp_options
		if(!current_user_can('manage_options')){
			return false;
		}
		$settings_array = array();
		$obj = $this;
		//convert class vars into array
		foreach (array_keys(get_class_vars(get_class($obj))) as $k){
			if (is_array($obj->$k)) {
				//serialize any arrays within $obj
				if (count($obj->$k)>0) {
					$settings_array[$k] = maybe_serialize($obj->$k);
				} else {
					$settings_array[$k] = "";
				}
			} else {
				$settings_array[$k] = "{$obj->$k}";
			}
		}
		update_option('wassup_settings', $settings_array);
		return true;
	} //end saveSettings

	/** delete 'wassup_settings' from wp_options table and reset class vars to defaults. */
	public function deleteSettings(){
		global $wpdb;
		//only administrators can delete from wp_options
		if(!current_user_can('manage_options')){
			return false;
		}
		$this->loadDefaults();
		if(!is_multisite()){
			delete_option('wassup_settings');
		}else{
			if(function_exists('is_network_admin') && is_network_admin()) $subsite_id=$GLOBALS['current_site']->blog_id;
			else $subsite_id=$GLOBALS['current_blog']->blog_id;
			delete_blog_option($subsite_id,'wassup_settings');
		}
	}
	/**
	 * Reset Wassup user option '_wassup_settings' to default.
	 *  - runs when a user logs in, after upgrade/install, and with reset-to-default
	 *  - contains 2 user arguments as required by 'wp_login' hook.
	 *
	 * @since v1.9
	 * @param (2) string(username), object(WP_User)
	 * @return array $wassup_user_settings
	 */
	public function resetUserSettings($user_login="",$user=false){
		global $current_user;
		if(!defined('WASSUPURL')){
			if(!wassup_init()) return;	//nothing to do
		}
		if(empty($user)) $user=$current_user;
		if(empty($user->ID)) $user=wp_get_current_user();
		$wassup_user_settings=get_user_option('_wassup_settings',$user->ID);
		if(!empty($wassup_user_settings)){
			$wassup_user_defaults=$this->defaultSettings('wassup_user_settings');
			$wassup_user_settings=$wassup_user_defaults;
			update_user_option($user->ID,'_wassup_settings',$wassup_user_settings);
		}
		return $wassup_user_settings;
	}
	/**
	 * Return an array of valid input field values or a single default value for a field in wassup settings form.
	 *  - value returned could be field value, field name, or the sql associated with the field, depending on the $meta param
	 * @param string(3) ($field,$meta,$selected)
	 * @return array
	 */
	public function getFieldOptions($field,$meta="",$selected="") {
		$field_options = array();
		$field_options_meta = array();
		$field_options_sql = array();
		$default_key = "";	//default value
		switch ($field) {
		case "wassup_screen_res":
			//"Options" setting
			$field_options = array("640","800","1024","1200",1600);
			$field_options_meta = array("&nbsp;640",
				"&nbsp;800",
				"1024",
				"1200",
				"1600");
			$default_key=1;
			break;
		case "wassup_userlevel":
			//"Options" setting
			$field_options = array("8","6","2","1","0");
			$field_options_meta = array(
				__("Administrator"),
				'&nbsp;'.__("Editor"),
				'&nbsp;'.__("Author"),
				'&nbsp;'.__("Contributor"),
				'&nbsp;'.__("Subscriber"));
			break;
		case "wassup_chart_type":
			//"Options" setting
			$field_options = array("1","2");
			$field_options_meta = array(
				__("One - 2 lines chart 1 axis","wassup"),
				__("Two - 2 lines chart 2 axes","wassup"));
			$default_key = "1";
			break;
		case "wassup_default_type":
		case "wassup_default_spy_type":
			$sitehome = wassupURI::get_sitehome();
			$wurl = parse_url(strtolower($sitehome));
			$sitehome = $wurl['host'];
			if(is_multisite() && !is_subdomain_install() && !empty($wurl['path'])) $sitehome=$wurl['host'].$wurl['path'];
			$sitedomain=rtrim(str_replace('.','\\.',$sitehome),'/ ');
			$field_options = array("everything", 
				"spider",
				"nospider",
				"spam",
				"nospam",
				"nospamspider",
				"loggedin",
				"comauthor",
				"searchengine",
				"referrer");
			$field_options_meta = array(__("Everything","wassup"),
				__("Spider","wassup"),
				__("No spider","wassup"),
				__("Spam","wassup"),
				__("No Spam","wassup"),
				__("No Spam, No Spider","wassup"),
				__("Users logged in","wassup"),
				__("Comment authors","wassup"),
				__("Referrer from search engine","wassup"),
				__("Referrer from ext link","wassup"));
			$field_options_sql = array("",
				" AND spider!=''",
				" AND spider=''",
				" AND spam>0",
				" AND spam=0",
				" AND spam=0 AND spider=''",
				" AND username!=''",
				" AND comment_author!=''",
				" AND searchengine!=''",
				" AND referrer!='' AND searchengine='' AND TRIM(LEADING 'http://' FROM TRIM(LEADING 'https://' FROM `referrer`)) NOT RLIKE '^(www".'\\.'.")?(".$sitedomain."/)'",
				//" AND referrer!='' AND referrer NOT LIKE 'http://".$sitedomain."%' AND referrer NOT LIKE 'https://".$sitedomain."%' AND referrer NOT LIKE 'http://www.".$sitedomain."%'",
				);
			break;
		case "wassup_default_limit":
			//"Options" setting, report and chart option
			$field_options = array("10","20","50","100");
			$field_options_meta = array("&nbsp;10",
				"&nbsp;20",
				"&nbsp;50",
				"100");
			break;
		case "delete_auto":
			//"Options" settings
			$field_options = array("never", 
					"-1 day",
					"-1 week", 
					"-2 weeks", 
					"-1 month", 
					"-3 months",
					"-6 months",
					"-1 year");
			$field_options_meta = array(
				__("Don't delete anything","wassup"),
				__("24 hours","wassup"),
				__("7 days","wassup"),
				__("2 weeks","wassup"),
				__("1 month","wassup"),
				__("3 months","wassup"),
				__("6 months","wassup"),
				__("1 year","wassup"));
			break;
		case "delete_filter":
			$field_options = array("all",
				"spider",
				"spam",
				"spider_spam");
			$field_options_meta = array(__("All"),
				__("Spider"),
				__("Spam"),
				__("Spider and spam","wassup"));
			$field_options_sql=array("",
				" AND `spider`!=''",
				" AND `spam`!='0' AND `spam`!=''",
				" AND (`spider`!='' OR (`spam`!='0' AND `spam`!=''))");
			break;
		case "sort_group":
			//TODO add to dislay options in Main/details screen
			$field_options = array("IP","URL");
			$field_options_meta = array(
				__("IP Address","wassup"),
				__("URL Request","wassup"));
			break;
		case "wassup_time_period": 
			//"Details" report and chart option
			$field_options = array(".05",".25","0.5","1","7","14","30","90","180","365","0");
			$field_options_meta = array(
				__("1 hour"),
				__("6 hours"),
				__("12 hours"),
				__("24 hours"),
				__("7 days"),
				__("2 weeks"),
				__("1 month"),
				__("3 months"),
				__("6 months"),
				__("1 year"),
				__("all time","wassup"),
			);
			$default_key=3; //default:meta[3]=24 hours
			break;
		default: 	//enable/disable is default
			$field_options =  array("1","0");
			$field_options_meta =  array("Enable","Disable");
		} //end switch
		if (empty($default_key)) $default_key=0;
		$retval = "";
		if ($meta == "meta") {
			//return 1 item
			if ($selected!="") {
				$key = array_search($selected,$field_options);
				if ($key) {
					$retval=$field_options_meta[$key];
				} elseif (!is_numeric($default_key)) {
					$key = array_search($default_key,$field_options);
					$retval=$field_options_meta[$key];
				} else {
					$retval=$field_options_meta[$default_key];
				}
			//return array of items
			} else {
				$retval=$field_options_meta;
			}
		} elseif ($meta == "default") {
			$retval = $default_key;
		} elseif ($meta == "sql") {
			if (!empty($field_options_sql)) {
			if ($selected!="") { //return 1 item
				$key = array_search($selected,$field_options);
				if($key) $retval=$field_options_sql[$key];
				else $retval=$field_options_sql[$default_key];
			} else { $retval=$field_options_sql; }
			}
		}else{
			$retval=$field_options;
		}
		return $retval;
	} //end getFieldOptions

	/**
	 * Generates <options> tags for use in a <select> form field.
	 * - 1st argument $itemkey must an input field name from the "wassup-options" form or from 'getFieldOptions' above.
	 * @param mixed(3) (string $itemkey,integer $selected, string $optionargs)
	 * @return string (html)
	 */
	public function showFieldOptions($itemkey,$selected="",$optionargs=""){
		$form_items =$this->getFieldOptions($itemkey);
		if(count($form_items) > 0){
			$form_items_meta=$this->getFieldOptions($itemkey,"meta");
			if($selected == ""){ 
				if(isset($this->$itemkey)){
					$selected=$this->$itemkey;
				}else{ 
					$default=$this->getFieldOptions($itemkey,"default");
					if(!empty($default) && is_numeric($default)) $selected=$form_items[$default];
					else $selected=$form_items[0];
				}
			}
			foreach($form_items as $k => $option_item){
				echo "\n\t\t".'<option value="'.$optionargs.$option_item.'"';
				if($selected==$option_item) echo ' selected="SELECTED">';
				else echo '>';
				echo $form_items_meta[$k].'&nbsp;&nbsp;</option>';
			}
		}
	} //end showFieldOptions

	/** strip bad characters from a text or textarea input string. @since v1.9 */
	public function cleanFormText($input){
		$cleantext="";
		if(function_exists('sanitize_text_field')) $text=sanitize_text_field($input);
		else $text=strip_tags(html_entity_decode(wp_kses($input,array())));
		//only alphanumeric chars allowed with few exceptions
		//since v1.9.3 allow '@' char for email searches
		//v1.9.4 bugfix: allow '/?&=' chars for url searches
		$cleantext=preg_replace('#([^0-9a-z\-_\.,\:\*\#/&\?=@\'" ]+)#i','',$text);
		return $cleantext;
	}
	/** strip bad characters from a text or textarea input for URLs. @since v1.9 */
	public function cleanFormURL($input){
		$cleanurl="";
		$loc=esc_url_raw($input);
		//only alphanumeric chars allowed with some exceptions
		$cleanurl=preg_replace('/([^0-9a-z\-_\.,\:\*#\/&\?=;% ]+)/i','',strip_tags(html_entity_decode(preg_replace('/(&#0?37;|&amp;#0?37;|&#0?38;#0?37;|%)(?:[01][0-9A-F]|7F)/i','',$loc))));
		return $cleanurl;
	}
	/** Save settings form changes stored in $_POST global.  @since v1.9 */
	public function saveFormChanges(){
		global $current_user;
		//only administrators can save to wp_options
		if(!current_user_can('manage_options')){
			$admin_message=__("Permission denied! Sorry, you must be an 'administrator' to change settings.","wassup");
			return $admin_message;
		}
		$admin_message=__("Nothing to do!","wassup");
		if(!empty($_POST)){
		if(!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'page=wassup-options')!==false){
			if(!is_object($current_user) || empty($current_user->ID)) wp_get_current_user();
			$sitehome=wassupURI::get_sitehome();
			//save multisite network settings
			$network_settings=array();
			$site_settings=array();
			if(is_multisite()){
				$network_settings=get_site_option('wassup_network_settings');
				if(is_network_admin() && !empty($network_settings['wassup_table']) && !is_main_site()){
					$site_settings=get_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings');
				}
				if(!empty($_POST['_network_settings'])){
					$network_settings['wassup_active']=(!empty($_POST['network_active'])?"1":"0");
					if($this->network_activated_plugin()){
						$network_settings['wassup_menu']=(!empty($_POST['wassup_menu'])?"1":"0");
					}
					update_site_option('wassup_network_settings',$network_settings);
				}
			}
			$this->wassup_active=(!empty($_POST['wassup_active'])?"1":"0");
			$this->wassup_spamcheck=(!empty($_POST['wassup_spamcheck'])?"1":"0");
			$this->wassup_screen_res=(int)$_POST['wassup_screen_res'];
			$this->wassup_userlevel=(int)$_POST['wassup_userlevel'];
			$this->wassup_dashboard_chart=(!empty($_POST['wassup_dashboard_chart'])?"1":"0");
			$this->wassup_geoip_map=(!empty($_POST['wassup_geoip_map'])?"1":"0");
			if(!empty($_POST['wassup_googlemaps_key'])){
				$this->wassup_googlemaps_key=$this->cleanFormText($_POST['wassup_googlemaps_key']);
			}else{
				$this->wassup_googlemaps_key="";
			}
			$this->wassup_chart=(!empty($_POST['wassup_chart'])?"1":"0");
			if(!empty($_POST['wassup_chart_type'])) $this->wassup_chart_type=(int)$_POST['wassup_chart_type'];
			else $this->wassup_chart_type="2";
			$this->wassup_time_format=$this->cleanFormText($_POST['wassup_time_format']);
			$this->wassup_time_period=$this->cleanFormText($_POST['wassup_time_period']);
			if(!empty($_POST['wassup_refresh'])){
				if(is_numeric($_POST['wassup_refresh'])&& $_POST['wassup_refresh']>0 && $_POST['wassup_refresh']<=180)
					$this->wassup_refresh=(int)$_POST['wassup_refresh'];
			}else{
				$this->wassup_refresh="0";
			}
			$this->wassup_default_type=$this->cleanFormText($_POST['wassup_default_type']);
			$this->wassup_default_limit=$this->cleanFormText($_POST['wassup_default_limit']);
			$top_ten=array(
				"toplimit"=>(isset($_POST['toplimit'])?(int)$_POST['toplimit']:"10"),
				"topsearch"=>(isset($_POST['topsearch'])?$_POST['topsearch']:"0"),
				"topreferrer"=>(!empty($_POST['topreferrer'])?"1":"0"),
				"toppostid"=>(!empty($_POST['toppostid'])?"1":"0"),
				"toprequest"=>(!empty($_POST['toprequest'])?"1":"0"),
				"topbrowser"=>(!empty($_POST['topbrowser'])?"1":"0"),
				"topos"=>(!empty($_POST['topos'])?"1":"0"),
				"toplocale"=>(!empty($_POST['toplocale'])?"1":"0"),
				"topvisitor"=>(!empty($_POST['topvisitor'])?"1":"0"),
				"topreferrer_exclude"=>$this->cleanFormURL($_POST['topreferrer_exclude'],'*'),
				"top_nofrontpage"=>(!empty($_POST['top_nofrontpage'])?"1":"0"),
				"top_nospider"=>(!empty($_POST['top_nospider'])?"1":"0"),
			);
			$this->wassup_top10=maybe_serialize($top_ten);
			$this->wassup_loggedin=(!empty($_POST['wassup_loggedin'])?"1":"0");
			$this->wassup_admin=(!empty($_POST['wassup_admin'])?"1":"0");
			$this->wassup_spider=(!empty($_POST['wassup_spider'])?"1":"0");
			$this->wassup_spam=(!empty($_POST['wassup_spam'])?"1":"0");
			$this->wassup_refspam=(!empty($_POST['wassup_refspam'])?"1":"0");
			if($_POST['refspam_whitelist'] != $this->refspam_whitelist)
				$this->refspam_whitelist=$this->cleanFormText($_POST['refspam_whitelist']);
			$this->wassup_hack=(!empty($_POST['wassup_hack'])?"1":"0");
			$this->wassup_attack=(!empty($_POST['wassup_attack'])?"1":"0");
			if($_POST['wassup_exclude'] != $this->wassup_exclude)
				$this->wassup_exclude=$this->cleanFormText($_POST['wassup_exclude']);
			if ($_POST['wassup_exclude_host'] != $this->wassup_exclude_host)
				$this->wassup_exclude_host=$this->cleanFormText($_POST['wassup_exclude_host']);
			if ($_POST['wassup_exclude_user'] != $this->wassup_exclude_user)
				$this->wassup_exclude_user=$this->cleanFormText($_POST['wassup_exclude_user']);
			if ($_POST['wassup_exclude_url'] != $this->wassup_exclude_url){
				if(wassupURI::is_root_url($sitehome))
					$this->wassup_exclude_url=str_replace($sitehome,'',$this->cleanFormURL($_POST['wassup_exclude_url']));
				else
					$this->wassup_exclude_url=str_replace(rtrim($sitehome,'/'),'/',$this->cleanFormURL($_POST['wassup_exclude_url']));
			}
			if(!empty($_POST['wassup_remind_mb'])&& is_numeric($_POST['wassup_remind_mb'])){
				$this->wassup_remind_mb=(int)$_POST['wassup_remind_mb'];
			}elseif(!isset($_POST['wassup_remind_mb'])){
				$this->wassup_remind_mb=100;
			}else{
				$this->wassup_remind_mb=0;
			}
			if(!empty($_POST['wassup_remind_flag'])){
				$this->wassup_remind_flag=(int)$_POST['wassup_remind_flag'];
				if(empty($_POST['wassup_remind_mb']))$this->wassup_remind_mb=100;
				if(!empty($site_settings)){
					$site_settings['wassup_remind_flag']=$this->wassup_remind_flag;
					$site_settings['wassup_remind_mb']=$this->wassup_remind_mb;
				}
			}
			if(isset($_POST['delete_auto'])){
				$this->delete_auto=$this->cleanFormText($_POST['delete_auto']);
				if(isset($_POST['delete_filter']))$this->delete_filter=$this->cleanFormText($_POST['delete_filter']);
				//schedule daily delete auto
				if(!empty($this->delete_auto) && $this->delete_auto!="never"){
					$starttime=wp_next_scheduled('wassup_scheduled_purge');
					if(empty($starttime)){
						//start purge at 2am
						$starttime=strtotime("tomorrow 2:00am");
						wp_schedule_event($starttime,'daily','wassup_scheduled_purge');
					}
				}else{
					wp_clear_scheduled_hook('wassup_scheduled_purge');
				}
			}else{
				wp_clear_scheduled_hook('wassup_scheduled_purge');
			}
			//save export options
			$this->export_spam=(!empty($_POST['export_spam'])?"1":"0");
			$this->export_omit_recid=(!empty($_POST['export_omit_recid'])?"1":"0");
			//save optimization timestamp and delayed_insert boolean values @since v1.9
			if(isset($_POST['wassup_optimize_on'])){
				if($this->wassup_optimize=="0")
					$this->wassup_optimize=$this->defaultSettings('wassup_optimize');
			}else{
				$this->wassup_optimize="0";
			}
			$this->delayed_insert=(!empty($_POST['delayed_insert'])?"1":"0");
			if(!empty($site_settings)){
				$site_settings['wassup_optimize']=$this->wassup_optimize;
				$site_settings['delayed_insert']=$this->delayed_insert;
			}
			if (!empty($_POST['wassup_dbengine'])) $this->wassup_dbengine=$this->cleanFormText($_POST['wassup_dbengine']);
			if($this->saveSettings()){
				$admin_message=__("Wassup options updated successfully","wassup")."." ;
				$this->resetUserSettings($current_user->user_login,$current_user);
				if(!empty($site_settings)) update_blog_option($GLOBALS['current_site']->blog_id,$site_settings);
			}
		} //end if HTTP_REFERER
		} //end if !empty($_POST)
		return $admin_message;
	} //end saveFormChanges

	/**
	 * get timezone (and offset) directly from the host server using the 'date' shell command on *nix and 'tzutil' on windows.
	 * @since 1.8
	 * @param none
	 * @return array (*string $timezone, *string $offset)
	 */
	public function getHostTimezone($getoffset=false){
		global $wdebug_mode;
		$hostTZ=false;
		$hostTimezone=array();
		$is_nix_server=true;
		//cannot use 'date' for timezone on Windows
		if(defined('PHP_OS') && preg_match('/^win/i',PHP_OS)>0){
			$is_nix_server=false;
		}elseif(defined('OS') && stristr(OS,'windows')!==false){
			$is_nix_server=false;
		}else{
			if (!empty($_SERVER['SERVER_SOFTWARE'])) {
				$php_os = $_SERVER['SERVER_SOFTWARE'];
			} elseif (function_exists('apache_get_version')) { 
				$php_os = apache_get_version();
			}
			if(preg_match('/win/i',$php_os)>0 && stristr($php_os,'darwin')===FALSE) $is_nix_server=false;
		}
		if($is_nix_server){
			if($getoffset) $cmd='date +"%Z|%z"';
			else $cmd='date +"%Z"';
			$hostvalue=$this->run_shell_cmd($cmd);
		}else{
			//try 'tzutil' for win8+
			$cmd='tzutil /g'; //to show current timezone
			$hostvalue=$this->run_shell_cmd($cmd);
		}
		if(!empty($hostvalue)){
			if(is_array($hostvalue)){
				$hostTZ=$hostvalue[0];
			}elseif(is_string($hostvalue)){
				$hostTZ=$hostvalue;
			}
			if(!empty($hostTZ)){
				if (strpos($hostTZ,'|')!==false) {
					$hostTimezone=explode('|',$hostTZ);
					$hostTimezone[1]=substr($hostTimezone[1],0,3);
				}else{
					$hostTimezone[0]=$hostTZ;
				}
			}
		}
		if($wdebug_mode){
			if(headers_sent()){
				echo "\n<!-- PHP_OS=".PHP_OS." &nbsp;\$is_nix_server=$is_nix_server &nbsp; \$cmd=$cmd &nbsp; \$hostTZ=$hostTZ -->";
			}
		}
		return $hostTimezone;
	} //end getHostTimezone

	/** Return true if 'wassup_active' is set in single-site or if set in both network and subsite settings @since v1.9.1 */
	public function is_recording_active(){
		$is_recording=false;
		if(!empty($this->wassup_active)){
			if(!is_multisite() || !$this->network_activated_plugin()){
				$is_recording=true;
			}else{
				$network_settings=get_site_option('wassup_network_settings');
	 			if(!empty($network_settings['wassup_active'])) $is_recording=true;
			}
		}
		return $is_recording;
	}
	/**
	 * Return true if site uses American English format for dates and numbers
	 * @since v1.9
	 * @param string
	 * @return boolean
	 */
	static function is_USAdate($ftype="date"){
		$is_usaformat=false;
		//check for US|Euro date format in wordpress options
		$wp_dateformat=get_option('date_format');
		$i=strpos($wp_dateformat,'j');
		if($i===false)$i=strpos($wp_dateformat,'d');
		if($i>1 && strpos($wp_dateformat,'Y')!==0)$is_usaformat=true;
		return $is_usaformat;
	}

	/**
	 * Verify that a user has administrator role.
	 *  - "username" param can be empty, string, or WPUser object
	 * @since v1.9
	 * @param string $username
	 * @return boolean
	 */
	static function is_admin_login($username=""){
		global $current_user;
		$is_admin_login=false;
		if($username===false)return false;
		if(empty($username))$username=$current_user;
		if(is_object($username)){
			if($username == $current_user && function_exists('is_super_admin') && is_super_admin()) $is_admin_login=true;
			elseif(!empty($username->roles)&& in_array("administrator",$username->roles)) $is_admin_login=true;
			elseif(!empty($username->user_level)&& $username->user_level>7)$is_admin_login=true;
		}elseif(is_string($username)){
			if($current_user->user_login == $username) $udata=$current_user;
			else $udata=get_user_by("login",$username);
			if($udata == $current_user && function_exists('is_super_admin') && is_super_admin()) $is_admin_login=true;
			elseif(!empty($udata->roles)&& in_array("administrator",$udata->roles)) $is_admin_login=true;
			elseif(!empty($udata->user_level)&& $udata->user_level>7) $is_admin_login=true;
		}
		return $is_admin_login;
	} //end is_admin_login

	/** Verify that wassup is network-activated in Wordpress multisite. @since v1.9 */
	static function network_activated_plugin($plugin_file=""){
		global $wpdb;
		$is_network_activated=false;
		$wassupplugin=plugin_basename(WASSUPDIR."/wassup.php");
		if(empty($plugin_file)) $plugin_file=$wassupplugin;
		if(is_multisite()){
			$plugins=get_site_option('active_sitewide_plugins');
			if(isset($plugins[$plugin_file])){
				$is_network_activated=true;
			}elseif($plugin_file == $wassupplugin){
				$network_settings=get_site_option('wassup_network_settings');
				if(!empty($network_settings['wassup_table']) && $network_settings['wassup_table']==$wpdb->base_prefix."wassup") $is_network_activated=true;
			}
		}
		return $is_network_activated;
	}
	/** Return the user capabilities string equivalent of a user level number. @since v1.9 */
	public function get_access_capability($userlevel=""){
		if($userlevel=="" || !is_numeric($userlevel)){
			$userlevel=$this->wassup_userlevel;
		}
		$access='read';	//default
		if(is_numeric($userlevel)){
			if($userlevel >=8) $access='manage_options';	//Admin
			elseif($userlevel >=6) $access='publish_pages';	//Editor
			elseif($userlevel >=2) $access='publish_posts';	//Author
			elseif($userlevel >=1) $access='edit_posts';	//Contributor
			else $access='read';
		}
		return $access;
	}

	/** Set hash value for wassup ajax requests and cookies */
	static function get_wp_hash($hashkey="") {
		$wassuphash="";
		if (empty($hashkey)) {
			if(defined('AUTH_KEY'))$hashkey=AUTH_KEY;	//in WP 3.X
			elseif(defined('SECRET_KEY'))$hashkey=SECRET_KEY; //in WP 2.X
			else $hashkey="wassup-".sprintf('%03d',rand(0,999));
			//for multisite, append subsite_id to hashkey
			if(is_multisite() && !is_network_admin()) $hashkey .= $GLOBALS['current_blog']->blog_id;
		}
		$wassuphash=wp_hash($hashkey);
		return $wassuphash;
	}
	/** Retrieve or query a Google!Map API key   @since v1.9.4 */
	public function get_apikey(){
		$apikey="";
		//try user's own api key
		if(!empty($this->wassup_googlemaps_key)){
			$apikey=$this->wassup_googlemaps_key;
		}else{
			//check for a builtin api key, if exist
			$meta_key="_googlemaps_key";
			if(is_multisite()) $sitehome=network_home_url();
			else $sitehome=get_option('home');
			$homedomain=wassupURI::get_urldomain($sitehome);
			$apikey=wassupDb::get_wassupmeta($homedomain,$meta_key);
		}
		return $apikey;
	}
	/** Do a remote lookup of Google!Map API key  @since v1.9.4 */
	static function lookup_apikey(){
		global $wdebug_mode;
		$error_msg="";
		$apikey=false;
		//no lookup key if key is already in settings
		$wassup_settings=get_option('wassup_settings');
		if(!empty($wassup_settings['wassup_googlemaps_key'])){
			return;
		}
		$ip=0;
		//for computers behind proxy servers:
		if(isset($_SERVER['SERVER_ADDR'])){
			$ip=wassupIP::validIP($_SERVER['SERVER_ADDR']);
		}
		if(empty($ip) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])){
			$ip=wassupIP::validIP($_SERVER['HTTP_X_FORWARDED_HOST']);
		}
		if(empty($ip) && !empty($_SERVER['HTTP_X_FORWARDED_SERVER'])){
			$ip=wassupIP::validIP($_SERVER['HTTP_X_FORWARDED_SERVER']);
		}
		if(empty($ip)){
			$ipAddress=wassupIP::get_clientAddr();
			$ip=wassupIP::clientIP($ipAddress);
		}
		//do lookup
		$api_url="http://helenesit.com/utils/wassup-webservice/?ws=mk&ip=".$ip;
		if($wdebug_mode) $api_url .='&debug_mode=1';
		if(!function_exists('wFetchAPIData')){
			include_once(WASSUPDIR."/lib/main.php");
		}
		$jsondata=wFetchAPIData($api_url);
		if(!empty($jsondata)){
			if(strpos($jsondata,'{')!==false) $apidata=json_decode($jsondata,true);
			else $apidata=$jsondata;
			if(is_array($apidata) && !empty($apidata['wassup_googlemaps_key'])){
				 $apikey=$apidata['wassup_googlemaps_key'];
			}elseif(is_string($apidata) && preg_match('/[^0-9a-z\-_]/',$apidata)==0){
				$apikey=$apidata;
			}else{
				if(is_wp_error($apidata)){
					$error_msg=__FUNCTION__." googlemaps key lookup error ".$apidata->get_error_code().": ".$apidata->get_error_message();
				}else{
					$error_msg=__FUNCTION__." googlemaps key lookup error: Non-json data returned";
					$error_msg .= maybe_serialize($apidata);
				}
			}
		}else{
			$error_msg=__FUNCTION__." googlemaps key lookup for ".$api_url." failed";
		}
		//save apikey
		if(!empty($apikey)){
			$meta_key="_googlemaps_key";
			if(is_multisite()) $sitehome=network_home_url();
			else $sitehome=get_option('home');
			$homedomain=wassupURI::get_urldomain($sitehome);
			$updated=wassupDb::update_wassupmeta($homedomain,$meta_key,$apikey,0);
		}elseif(!empty($error_msg)){ //debug
			if($wdebug_mode){
				return $error_msg;
			}
		}
	} //end lookup_apikey

	/**
	 * Return a PHP command that can execute an external program via the server shell ('shell_exec' or 'exec').
	 * @since v1.9.1
	 */
	static function get_shell_cmd(){
		$php_shell='exec';
		$safe_mode=false;
		if(version_compare(PHP_VERSION,'5.4','<')){
			$safe_mode= strtolower(ini_get("safe_mode"));
			if($safe_mode!= "on" && $safe_mode!= "1") $safe_mode=false;
		}
		//check that 'shell_exec' is enabled
		if(!$safe_mode){
			$disabled_funcs=ini_get('disable_functions');
			if(empty($disabled_funcs) || strpos($disabled_funcs,'shell_exec')===false){
				$php_shell='shell_exec';
			}
		}
		return $php_shell;
	}
	/**
	 * Execute an external program via the server shell ('shell_exec' or 'exec').
	 * @since v1.9.1
	 */
	public function run_shell_cmd($cmd=false){
		$ret_value=false;
		if(!empty($cmd)){
			$php_shell=$this->get_shell_cmd();
			//run date command on host server
			if($php_shell=="exec"){
				@exec($cmd,$ret_value,$ret_code);
				if($ret_code !="0") $ret_value=false;
			}elseif(!empty($php_shell)){
				$ret_value=call_user_func($php_shell,$cmd);
			}
		}
		return $ret_value;
	}
	/**
	 * Convert associative array to this class object
	 * @since 1.8
	 * @param array (assoc)
	 * @return boolean
	 */
	private function _options2class($options_array) { 
		if(!empty($options_array) && is_array($options_array)){
		foreach ($options_array as $o_key => $o_value){
			if(isset($this->$o_key)){ //returns false for null values
				$this->$o_key = $o_value;
			}elseif(function_exists('property_exists')){	//PHP 5.1+ function
				if(property_exists($this,$o_key)){
					$this->$o_key = $o_value;
				}
			}elseif(array_key_exists($o_key,$this)){ //valid for objects in PHP 4.0.7 thru 5.2 only
				$this->$o_key = $o_value;
			}
		} //end foreach
		}else{
			return false;
		}
		return true;
	} //end _options2class

	/** display a system notice or user message in admin panel. */
	public function showMessage($message="") {
		global $wp_version,$current_user;
		$notice_html="";
		if(empty($message)){
			//prioritize user alerts and show anytime
			if(!is_object($current_user) || empty($current_user->ID)) $user=wp_get_current_user();
			$wassup_user_settings = get_user_option('_wassup_settings',$current_user->ID);
			if(!empty($wassup_user_settings['ualert_message'])){
				$message=$wassup_user_settings['ualert_message'];
			}elseif(!empty($this->wassup_alert_message) && (empty($_GET['page']) || stristr($_GET['page'],'wassup')!==false)){
				$message=$this->wassup_alert_message;
			}
		}
		if(!empty($message)){
			$error=__("error","wassup");
			$warning=__("warning","wassup");
			$updated=__("updated","wassup");
			$upgraded=__("upgraded","wassup");
			$deleted=__("deleted","wassup");
			$mclass="fade updated";
			if(stristr($message,$error)!==false){
				if(version_compare($wp_version,'4.1','>=')) $mclass="notice error";
				else $mclass="fade error";
			}elseif(version_compare($wp_version,'4.1','>=')){
				if(stristr($message,$warning)!==false) $mclass="notice notice-warning is-dismissible";
				elseif(stristr($message,$updated)!==false || stristr($message,$upgraded)!==false || stristr($message,$deleted)!==false) $mclass="notice updated is-dismissible";
				else $mclass="notice notice-info is-dismissible";
			}
			//preface message with version # when not wassup page
			if(empty($_GET['page'])){
				$notice_html ='<div id="wassup-message" class="'.$mclass.'">WassUp '.WASSUPVERSION.' '.esc_attr($message).'</div>';
			}elseif(strpos($_GET['page'],'wassup')===false){
				$notice_html ='<div id="wassup-message" class="'.$mclass.'">WassUp '.WASSUPVERSION.' '.esc_attr($message).'</div>';
			}else{
				$notice_html='<div id="wassup-message" class="'.$mclass.'">'.esc_attr($message).'</div>';
			}
			//show alert message
			echo $notice_html."\n";
			//clear displayed alert message from settings
			if($message == $this->wassup_alert_message){
				$this->wassup_alert_message="";
				$this->saveSettings();
			}
			//clear displayed user alert from settings
			if(!empty($wassup_user_settings['ualert_message']) && $message==$wassup_user_settings['ualert_message']){
				$wassup_user_settings['ualert_message']="";
				update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
			}
		}
	}
	/** displays an alert (error) message. */
	public function showError($message="") {
		$this->showMessage($message);
	}
	/** remove a notice from wassup_alert_message. @since v1.9 */
	public function clearMessage($message_text=""){
		if(!empty($message_text)){
			if($this->wassup_alert_message==$message_text){
				$this->wassup_alert_message="";
				$this->saveSettings();
			}elseif(stristr($this->wassup_alert_message,$message_text)!==false){
				$this->wassup_alert_message="";
				$this->saveSettings();
			}
		}else{
			$this->wassup_alert_message="";
			$this->saveSettings();
		}
	}
} //end class wassupOptions
} //end if !class_exists

if(!class_exists('wassupDb')){
/**
 * Static class for WassUp table operations and data caching.
 *
 * @since v1.9
 * @author helened <http://helenesit.com>
 */
class wassupDb{
	/** Private constructor for true static class - prevents direct creation of object */
	private function __construct(){}

	/** Verify that a table exists in wordpress db */
	static function table_exists($table){
		global $wpdb;
		$is_table=false;
		if(!empty($table)&& preg_match('/^([a-z0-9\-_\.]+)$/i',$table)>0){
			if(method_exists($wpdb,'esc_like')) $sql=sprintf("SHOW TABLES LIKE '%s'",$wpdb->esc_like($table));
			else $sql=sprintf("SHOW TABLES LIKE '%s'",like_escape($table));
			$result=$wpdb->get_var($sql);
			if(!empty($result) && !is_wp_error($result)){
				if($result == $table) $is_table=true;
			}
		}
		return $is_table;
	} //table_exists

	/**
 	 * Perform a "table status" query and cache the result.
	 *
	 *  - table_status is cached for up to 1 hour to avoid overusing this costly mysql operation
	 * @param string
	 * @return object
	 */
	static function table_status($table){
		global $wpdb,$wdebug_mode;
		$table_status=false;
		if(empty($table) || !self::table_exists($table)){
			$error_msg= __FUNCTION__." failed - missing table name";
		}else{
			$meta_key='_table_status';
			$table_status=self::get_wassupmeta($table,$meta_key);
			if(empty($table_status)){
				$table_status=$wpdb->get_row(sprintf("SHOW TABLE STATUS LIKE '%s'",self::esc_like($table)));
				if(is_wp_error($table_status)){
					$error_msg=" table_status error# ".$table_status->get_error_code().": ".$table_status->get_error_message();
					$table_status=false;
				}elseif(!empty($table_status)){
					$expire=time()+3602;
					$cache_id=self::update_wassupmeta(esc_attr($table),$meta_key,$table_status,$expire);
				}else{
					$table_status=false;
				}
			}
		}
		if(!empty($error_msg)){
			if($wdebug_mode) echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		//always return an object
		if(empty($table_status) || !is_object($table_status)){
			if(empty($table_status['Data_length'])) $tstatus=array('Rows'=>0,'Data_length'=>0,'Index_length'=>0,'Engine'=>"",'Type'=>"");
			else $tstatus=$table_status;
			$table_status=(object)$tstatus;
		}
		return $table_status;
	} //table_status

	/**
 	 * Insert a record into a wassup table.
	 *  - insert record is an associative array
	 *  - a record id number is returned on success.
	 * @param string $table, array $new_record, boolean $delayed
	 * @return string
	 */
	static function table_insert($table,$new_record,$delayed=false){
		global $wpdb,$wassup_options,$wdebug_mode;
		$rec_id=false;
		$error_msg="";
		if($delayed===true){
			if(empty($wassup_options->delayed_insert)) $delayed=false;
		}else{
			$delayed=false;
		}
 		//table and record must be specfied
		if(empty($table)|| empty($new_record)){
			$error_msg=__FUNCTION__." failed - missing table or record parameter";
		}elseif(!is_array($new_record)){
			//record must be array
			$error_msg=__FUNCTION__." failed - insert record NOT an array $new_record";
		}
		if(!empty($error_msg)){
			$error=new WP_Error('1',$error_msg);
			return $error;
		}
		//insert the record
		if(!$delayed && method_exists($wpdb,'insert')){
			//insert with 'wpdb::insert' method 
			$rec_id=$wpdb->insert($table,$new_record);
		}else{
			//insert with 'wpdb::query' for delayed insert
			$cols="";
			$vals="";
			$i=0;
			foreach($new_record AS $field=>$val){
				if(is_numeric($val))$value=$val;
				else $value=self::sanitize($val,true);
				if($i==0){
					$cols="`$field`";
					$vals="$value";
				}else{
					$cols .=", `$field`";
					$vals .=", $value";
				}
				$i++;
			} //end foreach
			//delayed insert option for myISAM and ISAM tables
			if($delayed) $delayed="DELAYED";
			else $delayed="";
			$rec_id=$wpdb->query(sprintf("INSERT $delayed INTO `$table` (%s) VALUES (%s)",$cols,$vals));
		}
		return $rec_id;
	} //end table_insert

	/**
	 * perform a delete operation on a table
	 * @param string, string
	 * @return integer (no. deleted)
	 */
	static function table_delete($wtable="",$where_condition){
		global $wpdb,$wassup_options,$wdebug_mode;
		$deleted=false;
		if(!empty($where_condition)){
			if(empty($table))$table=$wassup_options->wassup_table;
			if(self::table_exists($table) && stristr($where_condition,'where')!==false){
				$sql=sprintf("DELETE FROM %s %s",$table,$where_condition);
				$deleted=$wpdb->query($sql);
				if(!empty($deleted) && is_wp_error($deleted)){
					$error_msg=' &nbsp; WP_Error in '.__FUNCTION__.' '.$deleted->get_error_message()." \n<br/>SQL=\$wpdb->query($sql)";
					$deleted=0;
				}
			}else{
				$error_msg=' &nbsp; Error in '.__FUNCTION__.' missing table or bad where condition';
			}
		}else{
			$error_msg=' &nbsp; Error in '.__FUNCTION__.' missing where condition';
		}
		if(!empty($error_msg)){
			if($wdebug_mode) echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		return $deleted;
	}

	/**
	 * Deletes "cached" records from 'wp_wassup_meta' table.
	 * - deletes records matching meta_key==parameter or 
	 * - deletes all records starting with "_" (underscore) when empty parameter
	 * @param string $meta_key
	 * @return boolean
	 */
	static function clear_cache($meta_key=""){
		global $wpdb,$wassup_options,$wdebug_mode;
		$cleared=false;
		$cache_table=$wassup_options->wassup_table ."_meta";
	 	// deletes all records starting with "_" (underscore)
		if(empty($meta_key)){
			$sql=sprintf("DELETE FROM %s WHERE `meta_key` LIKE '\\_%%' AND `meta_expire`>0",$cache_table);	//TODO: verify that escaped '_' requires 2 backslashes, not 1
			$deleted=$wpdb->query($sql);
			if(!empty($deleted) && is_wp_error($deleted)){
				$error_msg=' &nbsp; clear_cache error# '.$deleted->get_error_code()." ".$deleted->get_error_message()." \n<br/>SQL=\$wpdb->query($sql)";
				$deleted=0;
			}
	 	//delete records matching meta_key==parameter
		}elseif(preg_match('/^([0-9a-z_\-\.]+)$/',$meta_key)>0){
			$deleted=self::delete_wassupmeta('','*',$meta_key);
		}
		if(!empty($deleted)&& is_numeric($deleted))$cleared=true;
		if(!empty($error_msg)){
			if($wdebug_mode) echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		return $cleared;
	} //end clear_cache

	/**
	 * verify that a table can be optimized.
	 * - table engines: MyISAM, ARCHIVE, or InnoDb with "innodb_file_per_table" option enabled can be optimized.
	 * @param string (table_name)
	 * @return boolean
	 */
	static function is_optimizable_table($table_name=""){
		global $wpdb,$wassup_options;
		$is_optimizable=false;
		$tengine="";
		if(empty($table_name))$table_name=$wassup_options->wassup_table;
		if(!empty($table_name)){
			$tstatus=self::table_status($table_name);
			if(is_object($tstatus) && isset($tstatus->Engine)){
				$tengine=strtolower($tstatus->Engine);
				if(empty($tengine)|| $tengine=="myisam" || $tengine=="archive" || $tengine=="isam"){
					$is_optimizable=true;
				}elseif($tengine=="innodb"){
					$innodb_optimizable=$wpdb->get_var("SELECT @@global.innodb_file_per_table AS innodb_optimizable FROM DUAL");
					if(!empty($innodb_optimizable)&& !is_wp_error($innodb_optimizable) && ($innodb_optimizable=="1" || strtolower($innodb_optimizable)=="on")) $is_optimizable=true;
				}
			}
		}
		return $is_optimizable;
	} //end is_optimizable_table

	/**
	 * method for PHP5.5 and Mysqli compatibility.
	 * @todo - remove all references to old mysql_client_encoding and delete this method
	 */
	static function mysql_client_encoding(){
		global $wpdb;
		if (empty($wpdb->use_mysqli)) return mysql_client_encoding();
		else return mysqli_character_set_name();
	}

	/**
	 * Save wassup data to wp_wassup_meta table for caching.
	 *  - records are cached with a default expiration of 24 hours, if none is given as argument
	 * @param mixed(4): string(2) $wassup_key $meta_key, string/array $metavalue, integer $expire (as time)
	 * @return string $meta_id
	 */
	static function save_wassupmeta($wassup_key,$meta_key,$metavalue,$expire=false){
		global $wpdb,$wassup_options,$wdebug_mode;
		$table=$wassup_options->wassup_table.'_meta';
		$meta_id=false;
		//wassup_key or meta_key must be specified
		if(empty($wassup_key)&& empty($meta_key)){
			$error_msg=__FUNCTION__." failed! - missing both 'wassup_key' and 'meta_key' parameters";
			if($wdebug_mode)echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
			return false;
		}
		//24-hour expire default... rounded to minute
		if($expire===false || !is_numeric($expire))$expire= (ceil(time()/60))*60+24*3600;
		//serialize $metavalue array
		if(is_array($metavalue)|| is_object($metavalue))$meta_value=maybe_serialize($metavalue);
		else $meta_value=$metavalue;
		$meta_record=array('wassup_key'=>$wassup_key,
				   'meta_key'=>$meta_key,
				   'meta_value'=>"$meta_value",
				   'meta_expire'=>(int)$expire);
		//save to table
		$meta_id=self::table_insert($table,$meta_record);
		return $meta_id;
	} //end save_wassupmeta

	/**
	 * Update existing cache data in 'wp_wassup_meta' table.
	 * @param mixed(4): string(2) $wassup_key $meta_key, string/array $metavalue, integer $expire (as time)
	 * @return string $meta_id
	 */
	static function update_wassupmeta($wassup_key,$meta_key,$metavalue,$expire=false){
		global $wpdb,$wassup_options,$wdebug_mode;
		$table=$wassup_options->wassup_table.'_meta';
		$meta_id=false;
		$error_msg="";
		//both wassup_key and meta_key must be specified for update
		if(empty($wassup_key)|| empty($meta_key)){
			$error_msg=__FUNCTION__." failed! - missing either 'wassup_key' or 'meta_key' parameter";
		}else{
			//get meta_id of existing record, if any
			$result=$wpdb->get_var(sprintf("SELECT `meta_id` FROM $table WHERE `wassup_key`='%s' AND `meta_key`='%s' LIMIT 1",esc_attr($wassup_key),esc_attr($meta_key)));
			//update the record (or insert new)
			if(!empty($result) && !is_wp_error($result)) $meta_id=$result;
			if(empty($meta_id)){
				$meta_id=self::save_wassupmeta($wassup_key,$meta_key,$metavalue,$expire);
			}else{
				//24-hour expire default... rounded to minute
				if($expire===false || !is_numeric($expire))$expire=(ceil(time()/60))*60+24*3600;
				//serialize $metavalue array
				if(is_array($metavalue)|| is_object($metavalue))$meta_value=maybe_serialize($metavalue);
				else $meta_value=$metavalue;
				$qry=sprintf("UPDATE `$table` SET `meta_value`='%s', `meta_expire`=%d WHERE `meta_id`=%d",$meta_value,$expire, $meta_id);
				if(!empty($qry)){
					$result=$wpdb->query($qry);
					if(is_wp_error($result)) $error_msg=' &nbsp; update_wassupmeta Error#'.$result->get_error_code().': '.$result->get_error_message()." \n<br/>SQL=\$wpdb->query($qry)";
				}
			}
		}
		if(!empty($error_msg)){
			if($wdebug_mode)echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		return $meta_id;
	} //end update_wassupmeta

	/**
	 * Retrieve an unexpired 'meta_value' from wassup_meta table or an array of 'meta_value's for multiple matching records.  
	 * -records with 'meta_expire' timestamp older than the current time are not returned.
	 * -optional parameter to use 'SQL_NO_CACHE' to force mySQL to lookup data instead of using internal query cache
	 * @param string (2), boolean
	 * @return mixed (string or array)
	 */
	static function get_wassupmeta($wassup_key,$meta_key="",$sql_nocache=false){
		global $wpdb,$wassup_options,$wdebug_mode;
		if(empty($wassup_options))$wassup_options=new wassupOptions;
		$meta_table=$wassup_options->wassup_table.'_meta';
		$meta_value=false;
		$sql_no_cache="";
		$expired=time()-30;
		$sql="";
		$result=false;
		if(!empty($sql_nocache)) $sql_nocache="SQL_NO_CACHE";
		else $sql_nocache="";
		//check that we have a wassup_meta table
		if(!self::table_exists($meta_table)){
			$error_msg=__FUNCTION__." failed - table $meta_table not found!";
		//check for matching wassup_key
		}elseif(!empty($wassup_key)){
			if(!empty($meta_key)){
				$sql=sprintf("SELECT $sql_nocache `meta_value` FROM `$meta_table` WHERE `wassup_key`='%s' AND `meta_key`='%s' AND (`meta_expire`=0 OR `meta_expire`>%d) ORDER BY `meta_expire` DESC LIMIT 1",$wassup_key,$meta_key,$expired);
				//$result=$wpdb->get_var($sql);
				$result=$wpdb->get_results($sql);
				if(!empty($result) && !is_wp_error($result) && !empty($result[0]->meta_value)) $meta_value=$result[0]->meta_value;
			}else{
				//return an array of all results with same wassup_key
				$sql=sprintf("SELECT $sql_nocache `meta_value` FROM `$meta_table` WHERE `wassup_key`='%s' AND (`meta_expire`=0 OR `meta_expire`>%d)",$wassup_key,$expired);
				$result=$wpdb->get_col($sql);
				if(!empty($result) && !is_wp_error($result)) $meta_value=$result;
			}
		//check for matching meta_key
		}elseif(!empty($meta_key)){
			//return an array of all results with same meta_key
			$sql=sprintf("SELECT $sql_nocache `meta_value` FROM `$meta_table` WHERE `meta_key`='%s' AND (`meta_expire`=0 OR `meta_expire`>%d)",$meta_key,$expired);
			$result=$wpdb->get_col($sql);
			if(!empty($result) && !is_wp_error($result)) $meta_value=$result;
		}
		//unserialize arrays/objects before output
		if(!empty($meta_value) && !is_array($meta_value)&& !is_object($meta_value)){
			$results=maybe_unserialize(html_entity_decode($meta_value));
			if(is_array($results)|| is_object($results))$meta_value=$results;
		}
		if($wdebug_mode){
			if(!empty($result) && is_wp_error($result)){
				$errno=$result->get_error_code();
				if($errno >0) $error_msg=__FUNCTION__.' query failed - MySQL error#'.$errno.' '.$result->get_error_message()." \n<br/>SQL=$sql";
				echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
			}
		}
		return $meta_value;
	} //end get_wassupmeta

	/**
	 * Delete a 'wp_wassup_meta' record.
	 *  - single or multiple records may be deleted by 'meta_id', or 'wassup_key', or 'meta_key' regardless of expire timestamp
	 *  - 'wassup_key' and 'meta_key' arguments are both required when 'meta_id'=null
	 *  - wildcards (*) character can be used for bulk delete by either 'meta_key' or 'wassup_key', but not both.
	 *  USAGE: meta_id|"", [(wassup_key|*),(meta_key|*))].
	 *
	 * @param mixed (integer $meta_id, string $wassup_key, string $meta_key)
	 * @return integer
	 */
	static function delete_wassupmeta($meta_id,$wassup_key="",$meta_key=""){
		global $wpdb,$wassup_options,$wdebug_mode;
		$meta_table=$wassup_options->wassup_table.'_meta';
		$rows_affected=0;
		$deleted=0;
		$sql="";
		//prepare the sql delete statement from function parameters
		if(self::table_exists($meta_table)){
			if(!empty($meta_id)){
				$sql=sprintf("DELETE FROM $meta_table WHERE `meta_id`=%d",$meta_id);
			}elseif(!empty($wassup_key)&& !empty($meta_key)){
				if($wassup_key !="*" && $meta_key !="*"){
					$sql=sprintf("DELETE FROM $meta_table WHERE `meta_key`='%s' AND `wassup_key`='%s'",esc_attr($meta_key),esc_attr($wassup_key));
				}elseif($meta_key !="*"){
					$sql=sprintf("DELETE FROM $meta_table WHERE `meta_key`='%s'",esc_attr($meta_key));
				}elseif($wassup_key !="*"){
					$sql=sprintf("DELETE FROM $meta_table WHERE `wassup_key`='%s'",esc_attr($wassup_key));
				}
			}else{
				$error_msg=__FUNCTION__.' failed - bad or missing arguments!'."&nbsp; (meta_id=".esc_attr($meta_id)." , &nbsp;wassup_key=".esc_attr($wassup_key)." , &nbsp;meta_key=".esc_attr($meta_key).")";
			}
		}else{
			$error_msg=__FUNCTION__.' failed - table '.$meta_table.' does not exist!';
		}
		//do the delete
		if(!empty($sql)){
			$deleted=$wpdb->query($sql);
			$errno=0;
			if(is_wp_error($deleted)){
				$errno=$deleted->get_error_code();
				if($errno >0)$error_msg=' delete_wassupmeta failed with MySQL error#'.$errno.' '.$deleted->get_error_message(). " \n<br/>SQL=$sql";
			}
			$deleted=$wpdb->rows_affected+0;
		}
		if(!empty($error_msg)){
			if($wdebug_mode) echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		return $deleted;
	} //end delete_wassupmeta

	/**
	 * clean data for insertion into mySQL.
	 *  - to prevent SQL injection - renamed from wSanitizeData
	 *  - an alternative to "wpdb::prepare" for older WP versions.
	 * @since WassUp 1.7 (as wSanitizeData)
	 * @param string $var, boolean $quotes
	 * @return string
	 */
	static function sanitize($var,$quotes=false){
		global $wpdb;
		//clean strings and add quotes
		if(is_string($var)){
			$varstr=stripslashes($var);
			//sanitize urls separately
			if(strpos($varstr,'://')!==false || strpos($varstr,'/')===0){
				$varstr=esc_url_raw($varstr);
			}else{
				$varstr=esc_sql($varstr);
			}
 			if($quotes) $var="'". $varstr ."'";
			else $var=$varstr;
		//convert boolean variables to binary boolean
		}elseif(is_bool($var)&& $quotes){
			$var=($var)?1:0;
		//convert null variables to SQL NULL
		}elseif(is_null($var)&& $quotes){
			$var="NULL";
		}
		//note numeric values do not need to be sanitized
		return "$var";
	} //end sanitize

	/** simple escape for db save to prevent xss propagation. @since v1.9.1 */
	static function xescape($str){
		//change '" 'to &quot; and '<' to &lt; for db save
		if(!empty($str) && !is_numeric($str)){
			$xescaped=str_replace(array('"','<','\\x3c','%3c'),array('&quot;','&lt;','&092;x3c','&037;3c'),$str);
		}else{
			$xescaped=$str;
		}
		return $xescaped;
	}

	/**
	 * Escape special characters for use in an SQL 'like' query.
	 *  - uses 'wpdb::esc_like' or 'like_escape' if not available
	 */
	static function esc_like($sqlstring){
		global $wpdb;
		$escaped_string=$sqlstring;
		if(!empty($sqlstring)){
			if(method_exists($wpdb,'esc_like')) $escaped_string= $wpdb->esc_like($sqlstring);
			else $escaped_string=like_escape($sqlstring);
		}
		return $escaped_string;
	}

	/**
	 * Return an MySQL system variable value.
	 * @todo find cause of mysql timeout error when looking up dbengine for wp_wassup table
	 * @since 1.7.2 (as wassupOptions::getMySQLsetting)
	 */
	static function get_db_setting($mysql_var,$mysql_table="") {
		global $wpdb,$wassup_options,$wdebug_mode;
		$mysql_value=false;
		$error_msg="";
		if(empty($mysql_table)) $mysql_table=$wassup_options->wassup_table;
		//get the table storage engine
		if($mysql_var=="engine" || $mysql_var=="dbengine"){
			if(!empty($mysql_table)){
				//use "show create table" for table engine lookup instead of the cpu-intensive "show table status" query  @since v1.9
				$sql="SHOW CREATE TABLE $mysql_table";
				$result=$wpdb->get_results($sql,ARRAY_N);
				if(!empty($result)){
					if(!is_wp_error($result)){
						if(!empty($result[0][1]) && preg_match('/\sENGINE\=(\w+)\s/',$result[0][1],$pcs)>0) $mysql_value=$pcs[1];
					}else{
						$errno=$result->get_error_code();
						if($errno >0) $error_msg=' get_db_setting('.$mysql_var.') failed with MySQL error#'.$errno.' '.$myresult->get_error_message()."\n";
					}
				}
			}
			//default to db storage engine when no table
			if(empty($mysql_value)) {
				$result=$wpdb->get_results("SHOW VARIABLES LIKE 'storage_engine'",ARRAY_A);
				if(!empty($result) && !is_wp_error($result) && !empty($result[0]->Value)) $mysql_value=$result[0]->Value;
			}
		//get the timezone 
		}elseif ($mysql_var == "timezone") {
			$sql_timezone=false;
			$sql_sys_timezone="";
			$result=$wpdb->get_results("SHOW VARIABLES LIKE '%zone'");
			if(!is_wp_error($result)){
			foreach ($result as $col) {
				if ($col->Variable_name == "system_time_zone") {
					$sql_sys_timezone=$col->Value;
				} elseif ($col->Variable_name == "time_zone") {
					$sql_timezone=$col->Value;
				} elseif ($col->Variable_name == "timezone") {
					$sql_timezone=$col->Value;
				}
			}
			if ($sql_timezone == "SYSTEM" || empty($sql_timezone)) {
				$host_timezone=$wassup_options->getHostTimezone();
				if (!empty($host_timezone)) {
					$sql_timezone=$host_timezone[0];
				} else {
					$sql_timezone=$sql_sys_timezone;
				}
			}
			}
			if (!empty($sql_timezone)) $mysql_value=$sql_timezone;
		//get timezone offset for today's date.
		}elseif($mysql_var=="tzoffset" || $mysql_var=="TZoffset" || $mysql_var=="UTCoffset" || $mysql_var=="offset"){
			//calculate mysql timezone offset by converting MySQL's NOW() and MySQL's UTC_TIMESTAMP() to Unix timestamps then subtract UTC_TIMESTAMP value from NOW value
			$tzoffset=false;
			$mysql_dt=$wpdb->get_row("SELECT NOW() AS mysql_time, UTC_TIMESTAMP() AS mysql_utc FROM DUAL");
			if(!empty($mysql_dt) && !is_wp_error($mysql_dt)){
				$mysql_utc=(int)(strtotime($mysql_dt->mysql_utc)/1800)*1800;
				$mysql_time=(int)(strtotime($mysql_dt->mysql_time)/1800)*1800;
				if(!empty($mysql_utc)){
					$tzoffset=($mysql_time - $mysql_utc)/3600;
				}
			}
			$mysql_value=$tzoffset;
		//get a mysql variable with parameter name
		} elseif(!empty($mysql_var)) { 
			$result=$wpdb->get_results(sprintf("SHOW VARIABLES LIKE '%s'",self::esc_like(esc_attr($mysql_var))));
			if (!is_wp_error($result) && !empty($result)) {
			foreach ($result as $col) {
				if ($col->Variable_name == $mysql_var) {
					$mysql_value=$col->Value;
					break 1;
				}
			}
			}
		}
		if($wdebug_mode){
			if(!empty($result) && is_wp_error($result)){
				$errno=$result->get_error_code();
				if($errno >0) $error_msg .=' get_db_setting('.$mysql_var.') failed with MySQL error#'.$errno.' '.$result->get_error_message();
			}
			if(!empty($error_msg)) echo "\n<!-- ".__CLASS__." ERROR: ".$error_msg." -->";
		}
		return $mysql_value;
	} //end get_db_setting

	/**
	 * Convert an offset to MySQL "[+-]hh:mm" format.
	 *  - offset is converted from seconds or hours to MySQL "[+-]hh:mm" format.
	 * @since 1.8 (as wassupOptions::formatTimezoneOffset)
	 */
	static function format_tzoffset($offset=false) {
		$tzoffset=false;
		if(preg_match('/^[\-+]?[0-9\.]+$/',$offset)>0){ //must be a number
			//convert seconds to hours:minutes
			$n=false;
			if($offset > 12 || $offset < -12) $noffset=$offset/3600;
			else $noffset=$offset;
			$n = strpos($noffset,'.');
			if($n !== false){
				$offset_hrs=substr($noffset,0,$n);
				$offset_min=(int)substr($noffset,$n+1)*6;
			}else{
				$offset_hrs=$noffset;
				$offset_min=0;
			}
			if($offset < 0) $tzoffset=sprintf("%d:%02d",$offset_hrs,$offset_min);
			else $tzoffset="+".sprintf("%d:%02d",$offset_hrs,$offset_min);
		}elseif(preg_match('/^([\-+])?(\d{1,2})?\:(\d{2})/',$offset,$match)>0){
			if(empty($match[2])) $match[2]="0";
			if(!empty($match[1]) && $match[1]=="-") $tzoffset="-".sprintf("%d:%02d",$match[2],$match[3]);
			else $tzoffset="+".sprintf("%d:%02d",$match[2], $match[3]);
		}
		return $tzoffset;
	} //end format_tzoffset

	/**
	 * Perform scheduled/delayed db operations on wassup tables.
	 *  - for use with 'wp_schedule_event' hook.
	 * @param array
	 * @return void
	 */
	static function scheduled_dbtask($args=array()){
		global $wpdb,$wdebug_mode;
		//get dbtasks argument
		$dbtasks=array();
		if(!empty($args)){
			if(!is_array($args)) $args=maybe_unserialize($args);
			if(isset($args['dbtasks'])) extract($args);
			elseif(is_array($args)) $dbtasks=$args;
			else $dbtasks[]=$args;
		}
		$wassup_settings=get_option('wassup_settings');
		$network_settings=array();
		//check that Wassup recording is active
		$wassup_active=false;
		if(!empty($wassup_settings['wassup_active'])){
			if(!is_multisite()){
				$wassup_active=true;
			}else{
				$network_settings=get_site_option('wassup_network_settings');
				if(!empty($network_settings['wassup_active'])){
					$wassup_active=true;
					if(!empty($network_settings['wassup_table'])) $wassup_settings['wassup_table']=$network_settings['wassup_table'];
				}
			}
		}
		//do the tasks
		if($wassup_active){
			$affected_recs=0;
			$dbtask_errors=array();
			//unserialize dbtasks array if needed
			if(!empty($dbtasks) && !is_array($dbtasks)){
				$arr=maybe_unserialize($dbtasks);
				if(is_array($arr)) $dbtasks=$arr;
			}
			if(!empty($dbtasks) && is_array($dbtasks)){
				$table_prefix=$wassup_settings['wassup_table'];
				//some db operations can be slow on large tables, so extend script execution time up to 30 minutes
				$disabled_funcs=ini_get('disable_functions');
				if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')) @set_time_limit(1800);
				//increase mysql session timeout to 15 minutes
				$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout AS mtimeout FROM DUAL");
				if(!empty($mtimeout) && !is_wp_error($mtimeout) && is_numeric($mtimeout) && $mtimeout< 900){
					$result=$wpdb->query("SET wait_timeout=900");
				}
			foreach($dbtasks as $db_sql){
				$results=false;
				$error_l10=__("ERROR","wassup");
				$error_msg="";
				//limit allowed sql to certain tasks and to Wassup tables only
				if(strpos($db_sql,"DELETE FROM `$table_prefix")!==false){
					$results=$wpdb->query($db_sql);
				}elseif(strpos($db_sql,"UPDATE LOW_PRIORITY `$table_prefix")!==false){
					$results=$wpdb->query($db_sql);
				}elseif(strpos($db_sql,"UPDATE  `$table_prefix")!==false){
					$results=$wpdb->query($db_sql);
				}elseif(strpos($db_sql,"UPDATE `$table_prefix")!==false){
					$results=$wpdb->query($db_sql);
				}elseif(strpos($db_sql,"OPTIMIZE TABLE `$table_prefix")!==false){
					//limit wassup optimize to 1 per day in multisite
					$wassup_table='`'.$wassup_settings['wassup_table'].'`';
					$wassup_meta_table='`'.$wassup_settings['wassup_table'].'_meta`';
					if(strpos($db_sql,$wassup_table)>0){
						$timestamp=time();
						$last_optimized=self::get_wassupmeta($wassup_table,'_optimize');
						if(empty($last_optimized) || ($timestamp - $last_optimized)>24*3600){
							//save timestamp to prevent repeat of optimize
							$expire=time()+7*24*3600;
							$wassup_optimized=self::update_wassupmeta($wassup_table,'_optimize',$timestamp,$expire);
							//do optimize
							$results=$wpdb->query($db_sql);
						}else{
							$error_msg=" ".$error_l10.": limit of 1 optimize task in 24-hours ".esc_attr($db_sql);
						}
					}elseif(strpos($db_sql,$wassup_meta_table)>0){
						$results=$wpdb->query($db_sql);
					}else{
						$error_msg=" ".$error_l10.": unknown optimize request ".esc_attr($db_sql);
					}
				}else{
					//bad dbtask, create an error record
					$error_msg=" ".$error_l10.": Unknown task ".esc_attr($db_sql);
				}
				//check for errors in mysql results
				if(!empty($results) && is_wp_error($results)){
					$dbtask_errors[]=" ".$error_l10.": wpdb error#".$results->get_error_code().": ".$results->get_error_message()." for sql=$db_sql";
				}elseif(!empty($error_msg)){
					$dbtask_errors[]=$error_msg;
				}else{
					$affected_recs += $wpdb->rows_affected + 0;
				}
			} //end foreach
			}else{
				//bad dbtask argument, so create error
				$error_msg=" ".$error_l10.": Nothing to do.";
				if(!empty($dbtasks)){
					$error_msg .="..dbtasks not an array ".esc_attr($dbtasks);
				}else{
					$error_msg .="..empty argument";
					if(!empty($args)) $error_msg .=" ".esc_attr($args);
				}
			} //end if dbtasks
		} //end if wassup_active
		//email error output from cron as these are not logged
		if(!empty($wdebug_mode)){
			$message="";
			if(!empty($dbtask_errors)){
				$subject=sprintf(__("%s error!","wassup"),'Wassup wp-cron');
				$message=sprintf(__("%s encountered an error.","wassup"),"scheduled_dbtask")."\n";
				foreach($dbtask_errors AS $error_msg){
					$message .=$error_msg."\n";
				}
			}
			if(!empty($message)){
				$blogurl = wassupURI::get_sitehome();
				$recipient=get_bloginfo('admin_email');
				$sender='From: '.get_bloginfo('name').' <wassup_noreply@'.parse_url($blogurl,PHP_URL_HOST).'>';
				wp_mail($recipient,$subject,$message,$sender);
			}
		}
		//return $affected_recs; //don't return anything
	} //end scheduled_dbtask

	/**
	 * cleanup wassup temporary records (hourly via wp-cron):
	 *  - delete inactive records from wassup_tmp
	 *  - delete expired cache records from wassup_meta
	 *
	 * Inactive wassup_tmp records vary by visitor type: 
	 * - logged-in users are inactive after 10 minutes
	 * - anonymous users are inactive after 3 minutes
	 * - spiders are inactive after 1 minute
	 * @since v1.9
	 */
	static function temp_cleanup(){
		global $wpdb,$wassup_options;
		if(!defined('WASSUPURL')){
			if(!wassup_init()) return;	//nothing to do
		}
		$wassup_table=$wassup_options->wassup_table;
		$wassup_tmp_table=$wassup_table . "_tmp";
		$wassup_meta_table=$wassup_table . "_meta";
		$timestamp=current_time('timestamp');
		$timenow=(int)time();
		//delete inactive records from wassup_tmp table
		$result=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `timestamp`<'%d' OR (`timestamp`<'%d' AND `username`='') OR (`timestamp`<'%d' AND `spider`!='')",$wassup_tmp_table,(int)$timestamp - 10*60,(int)$timestamp - 3*60,(int)$timestamp - 60));
		//delete expired cache records from wassup_meta
		$result=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `meta_expire`>'0' AND `meta_expire`<'%d'",$wassup_meta_table,$timenow - 3600));
	}
	/**
	 * Do automatic purge of old records from wassup table (daily via wp-cron)
	 * @since v1.9
	 */
	static function auto_cleanup(){
		global $wpdb,$wassup_options;
		if(!defined('WASSUPURL')){
			if(!wassup_init()) return;	//nothing to do
		}
		$deleted=0;
		//do purge of old records
		if(!empty($wassup_options->delete_auto) && $wassup_options->delete_auto!="never"){
			$wassup_table=$wassup_options->wassup_table;
			$wassup_meta_table=$wassup_table . "_meta";
			$timestamp=current_time('timestamp');
			$timenow=(int)time();
			//use visit timestamp not current time for delete
			$delete_from= @strtotime($wassup_options->delete_auto,$timestamp);
			$delete_filter=""; 
			$rows=0;
			if(is_numeric($delete_from) && $delete_from < $timestamp){
				$delete_condition="`timestamp`<'$delete_from'";
				//check for delete filters
				if(!empty($wassup_options->delete_filter)){
				if($wassup_options->delete_filter!="all"){
					$delete_filter=$wassup_options->getFieldOptions("delete_filter","sql",$wassup_options->delete_filter);
					if(!is_string($delete_filter)) $delete_filter="";
				}else{
					$delete_filter="";
				}
				}
				$multisite_whereis="";
				if($wassup_options->network_activated_plugin() && !empty($GLOBALS['current_blog']->blog_id)){
					$multisite_whereis = sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
				}
				$delete_filter .= $multisite_whereis;
				$result=$wpdb->get_var(sprintf("SELECT COUNT(`id`) FROM `%s` WHERE `timestamp`<'%d' %s",$wassup_table,$delete_from,$delete_filter));
				if(!empty($result) && !is_wp_error($result) && is_numeric($result)) $rows=$result;

			} //end if delete_from
			//do delete only when there are 50+ records
			if($rows >50){
				$deleted=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `timestamp`<'%d' %s",$wassup_table,$delete_from,$delete_filter));
				//save delete_auto timestamp to prevent multiple auto deletes in 1 day
				if(!empty($deleted) && !is_wp_error($deleted)){
					$expire=time()+24*3600;
					$cache_id=wassupDb::update_wassupmeta($wassup_table,'_delete_auto',$timestamp,$expire);
					//clear table_status from wassup_meta cache after auto delete
					$result=$wpdb->query(sprintf("DELETE FROM `%s` WHERE `wassup_key`='%s' AND `meta_key`='_table_status'",$wassup_table."_meta",$wassup_table));
					//reschedule optimize to run today when bulk delete larger than 1000 records
					if($deleted >1000 && !empty($wassup_options->wassup_optimize)){
						$last_week=current_time("timestamp")-7*24*3600;
						if($wassup_options->wassup_optimize >$last_week){
							$wassup_options->wassup_optimize=$last_week;
							$wassup_options->saveSettings();
						}
					}
				}else{
					$deleted=0;
				}
			}
		} //end if delete_auto
		if(!empty($wdebug_mode)){
			//email delete message from cron
			$message="";
			if($deleted > 0){
				$subject=__("Wassup auto-delete notice","wassup");
				$message =sprintf(__("Auto-delete deleted %d old %s records today.","wassup"),$deleted,$wassup_options->wassup_table);
				$blogurl = wassupURI::get_sitehome();
				$recipient=get_bloginfo('admin_email');
				$sender='From: '.get_bloginfo('name').' <wassup_noreply@'.parse_url($blogurl,PHP_URL_HOST).'>';
				wp_mail($recipient,$subject,$message,$sender);
			}
		}
	} //end auto_cleanup

	/** Retrieve records from a table by record id @since v1.9.4 */
	static function get_records($table,$startid=0,$condition="",$limit=0){
		global $wpdb,$wdebug_mode;

		if(empty($table) || !self::table_exists($table)){
			$error_msg=__("Missing or incorrect table name","wassup").' '.$table;
			$error=new WP_Error('1',$error_msg);
			return $error;
		}
		//Extend php script timeout to 10 minutes
		$mtimeout=60;
		$stimeout=ini_get('max_execution_time');
		if(is_numeric($stimeout) && $stimeout>0 && $stimeout < 990){
			$disabled_funcs=ini_get('disable_functions');
			if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
				$stimeout=10*60;
				@set_time_limit($stimeout);
			}
		}elseif($stimeout===0){
			$stimeout=10*60;
		}
		//set mysql wait timeout to 15 minutes
		$timelimit=15*60;
		$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout AS mtimeout FROM DUAL");
		if(!empty($mtimeout) && !is_wp_error($mtimeout)){
			if(!is_numeric($mtimeout) || $mtimeout < $timelimit){
				$results=$wpdb->query(sprintf("SET wait_timeout = %d",($timelimit+60)));
			}elseif($mtimeout >3600){
				$timelimit=3600; //up to 1 hour
			}else{
				$timelimit=$mtimeout;
			}
		}else{
			$results=$wpdb->query(sprintf("SET wait_timeout = %d",($timelimit+60)));
		}
		//@TODO - check table for autoincrement field name and use it for recid field
		$recid='id';
		// do mysql query and return results
		if(!is_numeric($startid)) $startid=0;
		if(stristr($condition,' WHERE ')===false){
			$qry=sprintf("SELECT * FROM `%s` WHERE `id` > %d %s",esc_attr($table),$startid,$condition);
		}elseif($startid >0){
			$qry=sprintf("SELECT * FROM `%s` %s AND `id` > %d",esc_attr($table),$condition,$startid);
		}else{
			$qry=sprintf("SELECT * FROM `%s` %s",esc_attr($table),$condition);
		}
		if(!empty($limit) && is_numeric($limit) && $limit >0 && stristr($qry,' LIMIT ')===false){
			$qry .= sprintf(" LIMIT %d",$limit);
		}
		$table_records=$wpdb->get_results($qry);
		return($table_records);
	} //end get_records

	/** Export Wassup records in SQL or CSV format  @since v1.9.4 */
	static function export_records($table,$start_id,$wherecondition,$dtype="sql"){
		global $wpdb,$current_user,$wassup_options,$wdebug_mode;
		//#1st verify that export request is valid
		if(!isset($_REQUEST['export']) || !is_user_logged_in()){
			$err_msg =__("Export ERROR: Invalid Export request","wassup");
			$wassup_options->wassup_alert_message=$err_msg;
			$wassup_options->saveSettings();
			return false;
		}
		//start script timer
		$stimer_start=time();
		$msg="";
		$err_msg=false;
		if(!is_object($current_user) || empty($current_user->ID)){
			$user=wp_get_current_user();
		}
		$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
		if(empty($table)) $table=$wassup_options->wassup_table;
		$sql_table_name=$wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'",wassupDb::esc_like(esc_attr($table))));
		if(empty($sql_table_name) || $sql_table_name!=$table || is_wp_error($sql_table_name)){
			$err_msg=sprintf(__('Export ERROR: TABLE %s not found!','wassup'), esc_attr($table));
			wassup_log_message($err_msg);
			return false;
		}
		//for validating field data in export
		$search=array("\x00", "\x0a", "\x0d", "\x1a");
		$replace=array('\0','\n','\r','\Z');
		$ints=array();
		$table_structure=$wpdb->get_results(sprintf("SHOW COLUMNS FROM `%s`",esc_attr($table)));
		if(!is_wp_error($table_structure) && false != $table_structure){
			foreach($table_structure as $col){
			//differentiate numeric from char fields
			if((0===strpos(strtolower($col->Type),'tinyint')) ||
			   (0===strpos(strtolower($col->Type),'smallint')) ||
			   (0===strpos(strtolower($col->Type),'mediumint')) ||
			   (0===strpos(strtolower($col->Type),'int')) ||
			   (0===strpos(strtolower($col->Type),'bigint')) ||
			   (0===strpos(strtolower($col->Type),'timestamp'))){
				$ints[$col->Field]="1";
			}
			}
		}else{
			$err_msg=sprintf(__('Export ERROR: Unable to get TABLE %s structure!','wassup'), esc_attr($table));
			wassup_log_message($err_msg);
			return false;
		}
		$SEG_LIMIT=1000;  //1000 records per write block
		//only sql or csv export formats supported
		if($dtype=="csv" || $dtype=="CSV"){
			$dtype="csv";
		}else{
			$dtype="sql";
			$sql_header="";
			$sql_fields="";
			$sql_data="";
			//create SQL header from table structure
			$result=$wpdb->get_results(sprintf("SHOW CREATE TABLE `%s`",esc_attr($table)),ARRAY_N);
			if(empty($result[0][1]) || is_wp_error($result)){
				$err_msg=sprintf(__('Error with "SHOW CREATE TABLE" for %s.','wassup'), esc_attr($table));
				wassup_log_message($err_msg);
			} else {
				$table_create=$result[0][1];
				$sql_header="#\n# " . sprintf(__('Table structure of table %s','wassup'),esc_attr($table))."\n#\n";
				$sql_header .= preg_replace(array('/^CREATE\sTABLE\s(IF\sNOT\sEXISTS\s)?/i', '/AUTO_INCREMENT\=\d+\s/i'),array('CREATE TABLE IF NOT EXISTS ',''),$table_create).' ;';
				$sql_header .= "\n#\n# ".sprintf(__('Data contents of table %s','wassup'),esc_attr($table))."\n#\n";
			}
		}
		//set starting rec id of export query
		if(empty($start_id) || !is_numeric($start_id)){
			$start_id=0;
			if(isset($_REQUEST['startid']) && is_numeric($_REQUEST['startid'])){
				$start_id=(int)$_REQUEST['startid'];
			}
		}
		$lastexported_id=0;
		$row_count=0;
		$exporttot=0;
		$n=0;
		//open output stream for export
		$output=false;
		$outfile=$table.gmdate('Y-m-d').'.'.$dtype;
		$output=fopen('php://output','w');
		if($output){
			//Extend php script timeout to 10 minutes
			$stimeout=ini_get('max_execution_time');
			if(is_numeric($stimeout) && $stimeout>0 && $stimeout < 600){
				$disabled_funcs=ini_get('disable_functions');
				if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
					$stimeout=10*60;
					@set_time_limit($stimeout);
				}
			}elseif($stimeout===0){
				$stimeout=10*60;
			}else{
				$stimeout=60;	//assume 1 minute
			}
			//extend mysql timeout
			$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout AS mtimeout FROM DUAL");
			if(!empty($mtimeout) && !is_wp_error($mtimeout) && is_numeric($mtimeout) && $mtimeout< 900){
				$result=$wpdb->query("SET wait_timeout=900");
			}
		do{
			//get records
			$exportrecs=wassupDb::get_records($table,$start_id,"$wherecondition",$SEG_LIMIT);
			if(is_wp_error($exportrecs)){
				$errno=$exportrecs->get_error_code();
				$err_msg=sprintf(__("%s Export ERROR: %s","wassup"),strtoupper($dtype),$errno.' '.$exportrecs->get_error_message());
				$exportrecs=array();
				break;
			}
			$rec_count=count($exportrecs);
			if($rec_count ==0){
				//nothing to do
				$err_msg=sprintf(__("%s Export ERROR: No data","wassup"),strtoupper($dtype));
				break;
			}
			//get header info by export type
			if($n==0){
				//get field names from 1st record
				$wassup_rec=(array)$exportrecs[0];
				//omit record id field, if specified
				if(!empty($wassup_options->export_omit_recid)){
					unset($wassup_rec['id']);
				}
				if(!is_multisite()) unset($wassup_rec['subsite_id']);
				$fields=array_keys($wassup_rec);
				//start download
				header('Content-Disposition: attachment; filename='.$outfile);
				header('Pragma: no-cache');
				header('Expires: 0');
				header('Content-Type: text/'.$dtype.'; charset=utf-8');
				//header infos
				if($dtype == "csv"){
					//CSV header is a single row of column names
					fputcsv($output,$fields);
				}else{
					//write sql header
					fwrite($output,$sql_header);
					$i=0;
					//field list for sql-insert 
					$sql_fields="INSERT INTO `".esc_attr($table).'` (';
					foreach($fields AS $col){
						if(empty($wassup_options->export_omit_recid) || $col != 'id'){
							if($i >0) $sql_fields .=',';
							$sql_fields .='`'.$col.'`';
							$i++;
						}
					}
					$sql_fields .= ') VALUES';
				}
			}
			$exp_n=0;
			$sql_data="";
			//output records
			foreach($exportrecs AS $wassup_obj){
				$wassup_rec=(array)$wassup_obj;
				//track record id #
				if($wassup_rec['id'] > $start_id){
					$lastexported_id=$wassup_rec['id'];
				}
				//omit rec id + subsite fields from data
				if(!empty($wassup_options->export_omit_recid)){
					unset($wassup_rec['id']);
				}
				if(!is_multisite()) unset($wassup_rec['subsite_id']);
				if($dtype == "csv"){
					//convert timestamp
					$date24time=gmdate('Y-m-d H:i:s',$wassup_rec['timestamp']);
					$wassup_rec['timestamp']=$date24time;
					fputcsv($output,$wassup_rec);
				}else{
					//output sql-insert statement
					if($exp_n == 0){
						$sql_data =$sql_fields;
					}elseif($exp_n%100==0){
						//write completed stmt
						$sql_data .=';';
						fwrite($output,$sql_data);
						$sql_data ="\n$sql_fields";
					}else{
						$sql_data .=',';
					}
					//the data
					$sql_data .="\n(";
					$i=0;
					foreach($wassup_rec AS $key =>$val){
						if($i >0) $sql_data .= ",";
						if(isset($ints[$key])){
							$sql_data .=(''===$val)?"''":$val;
						}else{
							$sql_data .="'".esc_sql(str_replace($search,$replace,$val))."'";
						}
						$i++;
					} //end foreach
					$sql_data .=")";
				}
				$exp_n +=1;
			} //end foreach exportrec
			$exporttot +=$exp_n;
			if($lastexported_id > $start_id) $start_id=$lastexported_id;
			//output last sql statement
			if(!empty($sql_data)){
				$sql_data .=';'."\n";
				fwrite($output,$sql_data);
			}
			//stop export when stimeout limit is reached
			$time_passed = time() - $stimer_start;
			$n+=1;
		} while($rec_count >0 && $time_passed < $stimeout);
			//add msg, close stream and flush buffer
			//show last record id # in message
			if(!empty($lastexported_id)){
				$msg = $exporttot." ".strtoupper($dtype)." ".__("records exported!","wassup");
				$msg .= " ".__("Last export record id","wassup").": ".$lastexported_id;
			}
			//output footer @TODO
			$sql_footer="";
			//save export message
			if(isset($_REQUEST['mid'])) $msgkey=$_REQUEST['mid'];
			else $msgkey="0";
			if(!empty($msg)){
				wassup_log_message($msg,"_export_msg",$msgkey);
			}elseif(!empty($err_msg)){
				wassup_log_message($err_msg,"_export_msg",$msgkey);
			}
			//close export stream, flush buffer
			fclose($output);
			if($n >0) die();
		}else{
			$err_msg=strtoupper($dtype).' Export ERROR: Cannot open stream php://output';
		} //end if output
		//export failed if get here, so save error and return
		if(!empty($err_msg)){
			wassup_log_message($err_msg." ".$msg);
		}else{
			$err_msg=__("Export failed!","wassup")." ".$msg;
			wassup_log_message($err_msg);
		}
	} //end export_records

} //end class wassupDb
} //end if !class_exists

if(!class_exists('wassupURI')){
/**
 * Static class containing methods to format and clean urls/links for safe output.
 * @since v1.9
 * @author helened <http://helenesit.com>
 */
class wassupURI {
	/** Private constructor for true static class - prevents direct creation of object. */
	private function __construct(){}
	/**
	 * Return a value of true if url argument is a root url and false when url constains a subdirectory path or query parameters.
	 *  - renamed from url_rootcheck() function.
	 */
	static function is_root_url($url){
		$isroot=false;
		if(strpos($url,'.')>0){
			$urlparts=parse_url($url);
			if(!empty($urlparts['host'])) $isroot=true;
			if(!empty($urlparts['path']) && $urlparts['path'] !="/"){
				$isroot=false;
			}elseif(!empty($urlparts['query'])){
				$isroot=false;
			}
		}
		return $isroot;
	}
	/**
	 * Return a url with "$blogurl" prepended for sites that have wordpress installed in a separate folder.
	 *  - renamed from wAddSiteurl() function.
	 */
	static function add_siteurl($inputurl){
		if(preg_match('/^https?\:/',$inputurl)===false){
			if(function_exists('get_site_url')){ //WP 3.0+
				$outputurl=get_site_url($inputurl);
			}else{
				$siteurl=rtrim(self::get_sitehome(),"/");
				$wpurl=rtrim(self::get_wphome(),"/");
				if(strcasecmp($siteurl,$wpurl)==0)$outputurl=$inputurl;
				elseif(stristr($inputurl,$siteurl)===FALSE && self::is_root_url($siteurl))$outputurl=$siteurl."/".ltrim($inputurl,"/");
				else $outputurl=$inputurl;
				$outputurl=rawurldecode(html_entity_decode($outputurl)); //dangerous
			}
			return self::cleanURL($outputurl);
		}else{
			return self::cleanURL($inputurl); //security fix
		}
	}
	/** Return the url and "path" for wordpress site's "home". */
	static function get_sitehome(){
		if(is_multisite() && is_network_admin()){
			$sitehome=network_home_url();
		}else{
			$sitehome=get_option('siteurl');
		}
		if(empty($sitehome)) $sitehome=get_option('home');
		return $sitehome;
	} //end get_sitehome

	//** Return the url and "path" for wordpress admin. */
	static function get_wphome(){
		if(is_multisite() && is_network_admin()){
			$wphome=network_admin_url();
		}else{
			$wphome=admin_url();
		}
		if(empty($wphome)) $wphome=get_option('wpurl');
		return $wphome;
	} //end get_wphome

	/** Return the "domain" part of a url/this site @since v1.9.4 */
	static function get_urldomain($urlparam=""){
		$domain=false;
		$url=array();
		//default param is this site's url
		if(empty($urlparam)) $urlparam=self::get_sitehome();
		if(strpos($urlparam,'//')!==false) $url=parse_url($urlparam);
		if(!empty($url['host'])){
			$domain=preg_replace('/^(w{2,3}\d?\.)/','',$url['host']);
		}elseif(preg_match('#(?://|ww[0-9w]\.|^)([^?.\# %/=&]+\.(?:[^?\# %/=&]+))(?:[?\# /]|$)#',$urlparam,$pcs)>0){
			$domain=preg_replace('/^(w{2,3}\d?\.)/','',$pcs[1]);
		}
		return $domain;
	}
	/**
	 * Return request url in a link tag or span tag if it is suspicious or 404.
	 *   Security Note: returned href, tooltip, and tag contents are escaped within 'add_siteurl' or 'disarm_attack methods, or by 'stringShortener' function
	 *
	 * @param string, integer, integer
	 * @return string (html)
	 * @since v1.9
	 */
	static function url_link($urlrequested,$chars=0,$spam=0){
		global $wassup_options;
		$urllink=false;
		if($chars===false){	//no string shortening
			$chars=0;
		}elseif(empty($chars) || !is_numeric($chars)){
			$chars=(int)$wassup_options->wassup_screen_res/10;
		}
		$request=strtolower($urlrequested);
		if(strlen($request)>60) $tooltip=' title="'.self::cleanURL($request).'" ';
		else $tooltip="";
		if($chars >0) $cleaned_uri=stringShortener("$urlrequested",round($chars*.9,0));
		else $cleaned_uri=self::cleanURL("$urlrequested");
		//no link for spam, 404, wp-admin, wp-login or any possible unidentified spam @since v1.9.1
		if(!empty($spam) || self::is_xss($urlrequested)){
			$urllink='<span class="malware"'.$tooltip.'>'.$cleaned_uri.'</span>';
		}elseif(preg_match('/\/wp\-(?:admin|content|includes)\/|\/wp\-login\.php|^\[[0-9]{3}\]/',$urlrequested)>0){
			$urllink='<span'.$tooltip.'>'.$cleaned_uri.'</span>';
		}else{
			$urllink='<a href="'.self::add_siteurl($request).'" target="_BLANK">'.$cleaned_uri.'</a>';
		}
		return $urllink;
	}
	/** Return an external referrer link or a text string if link is internal, or is spam or 404.  @since v1.9 */
	static function referrer_link($wrec,$chars=0){
		global $wassup_options;
		if(empty($wrec) || !is_object($wrec) || empty($wrec->referrer)){
			return false; //nothing to do
		}
		if($chars===false){	//no string shortening
			$chars=0;
		}elseif(empty($chars) || !is_numeric($chars)){
			$chars=(int)($wassup_options->wassup_screen_res/10);
		}
		$referrerlink=false;
		$referer="";
		$spam=0;
		if(!empty($wrec->referrer)) $referer=$wrec->referrer;
		if(!empty($wrec->malware_type)) $spam=$wrec->malware_type;
		if(!empty($referer)){
			$wpurl=self::get_wphome();
			$siteurl=self::get_sitehome();
			$adminurl=admin_url("");
			$tooltip="";
			$ref=strtolower($referer);
			if(strlen($ref)>60) $tooltip=' title="'.self::cleanURL($ref).'" ';
			if($chars >0) $cleaned_uri=stringShortener("$referer",round($chars*.9,0));	//v1.9.4 bugfix
			else $cleaned_uri=self::cleanURL("$referer");
			//referrer from site or site-admin
			if(stristr($referer,$wpurl)==$referer || stristr($referer,$siteurl)==$referer){
				//direct hit when referrer == request
				if(!empty($wrec->urlrequested) && ($ref == $siteurl.$wrec->urlrequested || $ref == rtrim($siteurl.$wrec->urlrequested,'/'))){
					$referrerlink='<span>'.__("direct hit","wassup").'</span>';
				}elseif($spam==2 || self::is_xss($ref)){
					//show spam referrers w/o link
					$referrerlink='<span class="malware"'.$tooltip.'>'.$cleaned_uri.'</span>';
				}elseif($spam >0){
					$referrerlink='<span'.$tooltip.'>'.$cleaned_uri.'</span>';
				}elseif(is_user_logged_in()){
					//show 'wp-login', 'wp-includes', and 'wp-content' referrers to logged-in users
					if(strpos($ref,'wp-login.php')>0 || strpos($ref,'/wp-includes/')>0 || strpos($ref,'/wp-content/')>0){
						$referrerlink='<a href="'.self::cleanURL($referer).'" target=_"BLANK"'.$tooltip.'>'.$cleaned_uri.'</a>';
					}else{
						$referrerlink="<span{$tooltip}>".__("from your site","wassup")."</span>";
					}
				}else{
					$referrerlink="<span{$tooltip}>".__("from your site","wassup")."</span>";
				}
			//external referrer
			}else{
				$favicon_img="";
				//no link for spam or wp-admin
				if($spam==2 || self::is_xss($ref)){
					$referrerlink='<span class="malware"'.$tooltip.'>'.$cleaned_uri.'</span>';
				}elseif($spam >0 || strpos($ref,'http')===false || strpos($ref,'http')>0 || preg_match('/\/wp\-(?:admin|content|includes)\/|\/wp\-login\.php/i',$ref)>0){
					$referrerlink='<span'.$tooltip.'>'.$cleaned_uri.'</span>';
				}else{
					$rurl=parse_url($referer);
					if(!empty($rurl['host']) && preg_match('/\.[a-z]{2,4}$/',$rurl['host'])>0){
						$favicon_img='<img src="http://www.google.com/s2/favicons?domain='.$rurl['host'].'" class="favicon"> ';
					}
					$referrerlink=$favicon_img.'<a href="'.self::cleanURL($referer).'" target=_"BLANK"'.$tooltip.'>'.$cleaned_uri.'</a>';
				}
			}
		}else{
			$referrerlink='<span>'.__("direct hit","wassup").'</span>';
		}
		return $referrerlink;
	} //end referrer_link

	/** Return a referrer link for search engines. @since v1.9 */
	static function se_link($wrec,$chars=0,$keywords=""){
		global $wassup_options;
		if(empty($wrec) || !is_object($wrec) || empty($wrec->referrer)){ //nothing to do
			return false;
		}
		if($chars===false){	//no string shortening
			$chars=0;
		}elseif(empty($chars) || !is_numeric($chars)){
			$chars=(int)($wassup_options->wassup_screen_res/10);
		}
		$selink=false;
		$referer="";
		$spam=0;
		if(!empty($wrec->referrer)) $referer=$wrec->referrer;
		if(!empty($wrec->malware_type)) $spam=$wrec->malware_type;
		if(!empty($referer)){
			$tooltip="";
			$ref=strtolower($referer);
			if(strlen($ref)>60) $tooltip=' title="'.self::cleanURL($referer).'" ';
			if(empty($spam) && preg_match('/\/wp\-(?:admin|content|includes)\/|\/wp\-login\.php/i',$ref)==0 && !self::is_xss($ref)){
				if(!empty($keywords)){
					$selink='<a href="'.self::cleanURL($referer).'" target=_"BLANK"'.$tooltip.'><span>'.esc_attr($keywords).'</span></a>';
				}else{
					if($chars >0) $selink='<a href="'.self::cleanURL($referer).'" target=_"BLANK"'.$tooltip.'><span>'.stringShortener($referer,round($chars*.8,0)).'</span></a>';
					else $selink='<a href="'.self::cleanURL($referer).'" target=_"BLANK"'.$tooltip.'><span>'.self::cleanURL($referer).'</span></a>';
				}
			}
		}
		return $selink;
	}

	/** Remove all ascii codes and replace with '---' in url. Can be used before saving to database. @since v1.9 */
	static function neutralize($urlstring){
		if(!empty($urlstring) && !is_numeric($urlstring)){
			$cleaned=wp_kses(preg_replace('/(&#0*37;|&amp;#0*37;|&#0*38;#0*37;|%)([01][0-9A-F]|7F)/i','---',$urlstring),array());
		}else{
			$cleaned=$urlstring;
		}
		return $cleaned;
	}
	/** returns true if a string(url) contains possible xss code. @since v1.9.1 */
	static function is_xss($string){
		$isxss=false;
		if(!empty($string)){
			if(preg_match('/(?:<|%3c|&lt;?|&#0*60;?|&#x0*3c;?)(\?|java|(?:vb?|j)?script|img\s+(?:style|dynsrc|lowsrc|src))|[^1-9abd-z\-_.](?:href|(?:img)?src)\s*[=\\%&]|[^1-9abd-z](on[a-fhiklmopr-u][a-z]+|mouse[a-z]+|fscommand|seeksegmenttime)\s*[=\\%&]|[^1-9abd-z\-_.]javascript\:|data\:\s*(?:text|image)\/[^;]+;|[\?@]import[^1-9a-z.\-_]|document\.(?:location|cookie|write(?:ln)?)[^0-9a-z\-_]|(exec\scmd|applet\scode|object\sdata)\s*[=\\&%]|\.(?:tostring|fromcharcode)[\(\\%&]|style\s*[=\\%&>][^:]+\:\s*url\s*\(|[&;]{|(?:&quot;|["\'`]|;)\s*[;<>]|\)\s*[;\'"\\%&`<>]|(?:&#x?[0-9]+|[\'";`])\s*(?:>|&gt;?|&#0*62)|(?:script|[\?;])(?:>|&gt;?|&#0*62)|\\\\x3c|\\\\u003c/i',$string)>0){
				$isxss=true;
			}
		}
		return $isxss;
	}
	/**
	 * Return a string with some chars replaced with safer html-encoded versions and with ascii codes removed.
	 *  - for displaying questionable data in requesturl, referrer and user agent.
	 * @since v1.9
	 */
	static function disarm_attack($urlstring=false){
		if(!empty($urlstring) && !is_numeric($urlstring)){
			$cleaned=str_replace(array(' ','!','$','"','&&','\'','(',')','*',',','-->','<','>','\\','^','`','{','|','~'),array('&#032;','&#033;','&#036;','&quot;','&amp;&amp;','&#039;','&#040;','&#041;','&#042;','&#044;','&#045;&#045;&gt;','&lt;','&gt;','&#092;','&#094;','&#096;','&#123;','&#124;','&#126;'),htmlentities(stripslashes(html_entity_decode(preg_replace('/(&#0*37;|&amp;#0*37;|&#0*38;#0*37;|%)([01][0-9A-F]|7F)/i','---',$urlstring)))));
		}else{
			$cleaned=$urlstring;
		}
		return $cleaned;
	}
	/** Return a url that is sanitized of potentially dangerous code. */
	static function cleanURL($url=""){
		if(!empty($url) && !is_numeric($url)){
			//don't use 'esc_url' on query string because 'esc_url' will prefix it with a 'http://'
			if(preg_match('#^(/|\?|http)#',$url)>0){
				$cleaned=str_replace(array('&#038;','&#38;','&amp;'),'&',esc_url(self::disarm_attack($url)));
			}else{
				$allowed=array('http','https','ftp','ftps','mailto','news','irc','gopher','nntp','feed','telnet','mms','rtsp','svn','tel','fax','xmpp','webcal');
				$cleaned=wp_kses_bad_protocol(self::disarm_attack($url),$allowed);
			}
		}else{
			$cleaned=$url;
		}
		return $cleaned;
	} //end cleanURL

	/**
	 * Return the 'page' query parameter or the menu link query parameter 'ml' for a wassup-stats page from the URI
	 * @param none
	 * @return string
	 */
	static function get_menu_arg(){
		$menuarg="wassup";
		if(isset($_GET['page'])) $menuarg=htmlspecialchars($_GET['page']);
		if(stristr($menuarg,"wassup")!==false){
			if(isset($_GET['ml'])){
				$menuarg=htmlspecialchars($_GET['ml']);
			}else{
				$wassupfolder=basename(WASSUPDIR);
				if($menuarg=="wassup-stats"){
					$menuarg="wassup";
				}elseif($menuarg=="wassup-spia"){
					$menuarg="wassup-spy";
				}elseif($menuarg==$wassupfolder){
					$menuarg="wassup";
				}elseif($menuarg=="wassup-options"){
					if(isset($_GET['tab'])){
						if($_GET['tab']=="donate") $menuarg="wassup-donate";
						elseif($_GET['tab']=="faq") $menuarg="wassup-faq";
					}
				}
			}
		}
		return $menuarg;
	}
	/** check for admin uri with valid referrer via _wpnonce */
	static function is_valid_admin_referer($action="-1",$wpage=""){
		global $wp_version;
		$is_valid_referer=false;
		if(version_compare($wp_version,'2.8','>=') || $action=="-1"){
			$is_valid_referer=check_admin_referer($action);
		}elseif(is_admin() && !empty($_SERVER['HTTP_REFERER'])){
			//old 'check_admin_referer' echoes output
			if(isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'],$action)){
				if(isset($_REQUEST['_wp_http_referer'])){
					if(strpos($_SERVER['HTTP_REFERER'],$_REQUEST['_wp_http_referer'])!==false) $is_valid_referer=true;
				}else{
					if(empty($wpage)) $wpage=self::get_menu_arg();
					if(!empty($wpage) && strpos($_SERVER['HTTP_REFERER'],"page=$wpage")!==false) $is_valid_referer=true;
				}
			}
		}
		return $is_valid_referer;
	}
	/** Return network or admin url for a wassup link. @since v1.9.1 */
	static function get_admin_url($link_part){
		$url="";
		if(!empty($link_part)){
			if(is_multisite() && is_network_admin()) $url=network_admin_url($link_part);
			else $url=admin_url($link_part);
		}
		return($url);
	}
	/** Return a url appropriate for Wassup ajax actions. @since v1.9.1 */
	static function get_ajax_url($action=""){
		global $wp_version;
		$ajaxurl=self::get_admin_url('admin-ajax.php');
		if(version_compare($wp_version,'2.7','<')){
			$wassupfolder=basename(WASSUPDIR);
			$ajaxurl=admin_url('admin.php?page='.$wassupfolder);
		}elseif(version_compare($wp_version,'3.0','<')){
			$ajaxurl=admin_url('index.php?page=wassup-stats');
		}elseif($action=="Topstats"){
			$ajaxurl=self::get_admin_url('index.php?page=wassup-stats');
		}elseif($action=="Export"){
			$ajaxurl=self::get_admin_url('index.php?page=wassup-options&tab=3');
		}
		return $ajaxurl;
	}
} //end Class wassupURI
} //end if !class_exists

if(!class_exists('wassupIP')){
/**
 * class containing methods to detect and display ip addresses and doains on the internet.
 * @since v1.9.4
 * @author helened <http://helenesit.com>
 */
class wassupIP {
	/** Return a single ip (the client IP) from a comma-separated IP address with no ip validation. @since v1.9 */
	static function clientIP($ipAddress){
		$IP=false;
		if(!empty($ipAddress)){
			$ip_proxy=strpos($ipAddress,",");
			//if proxy, get 2nd ip...
			if($ip_proxy!==false){
				$IP=substr($ipAddress,(int)$ip_proxy+1);
			}else{
				$IP=$ipAddress;
			}
		}
		return $IP;
	}

	/** return 1st valid IP address in a comma-separated list of IP addresses -Helene D. 2009-03-01 */
	static function validIP($multiIP) {
		//in case of multiple forwarding
		$ips=explode(",",$multiIP);
		$goodIP=false;
		//look through forwarded list for a good IP
		foreach ($ips as $ipa) {
			$IP=trim(strtolower($ipa));
			//exclude badly formatted ip's @since v1.9.3
			if(!empty($IP)){
				//exclude dummy IPv4 addresses
				if(preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/',$IP)>0){
					if($IP!="0.0.0.0" && $IP!="127.0.0.1" && substr($IP,0,8)!="192.168." && substr($IP,0,3)!="10." && substr($IP,0,4)!="172." && substr($IP,0,7)!='192.18.' && substr($IP,0,4)!='255.' && substr($IP,-4)!='.255'){
						$goodIP=$IP;
					}elseif(substr($IP,0,4)=="172." && preg_match('/172\.(1[6-9]|2[0-9]|3[0-1])\./',$IP)===false){
						$goodIP=$IP;
					}
				//exclude dummy IPv6 addresses 
				}elseif(preg_match('/^(?:((?:[0-9a-f]{1,4}\:){1,}(?:\:?[0-9a-f]{1,4}){1,})|(\:\:(?:[0-9a-f]{1,4})?))$/i',$IP)>0){
					$ipv6=str_replace("0000","0",$IP);
					if($ipv6!='::' && $ipv6!='0:0:0:0:0:0:0:0' && $ipv6!='::1' && $ipv6!='0:0:0:0:0:0:0:1' && substr($ipv6,0,2)!='fd' && substr($ipv6,0,5)!='ff01:' && substr($ipv6,0,5)!='ff02:' && substr($ipv6,0,5)!='2001:'){
						$goodIP=$IP;
					}
				}
				if(!empty($goodIP)) break;
			}
		} //end foreach
		return $goodIP;
	} //end function validIP
	/**
	 * return a validated ip address from http header
	 * @since v1.9
	 * @param string
	 * @return string
	 */
	static function get_clientAddr($ipAddress=""){
		$proxy="";
		$hostname="";
		$IP="";
		//Get the visitor IP from Http_header
		if(empty($ipAddress)){
			$ipAddress=(isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:"");
		}
		$IPlist=$ipAddress;
		$proxylist=$ipAddress;
		$serverAddr=(isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:"");
		//for computers behind proxy servers:
		//if(!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) $serverAddr=$_SERVER['HTTP_X_FORWARDED_HOST'];
		//elseif(!empty($_SERVER['HTTP_X_FORWARDED_SERVER'])) $serverAddr=$_SERVER['HTTP_X_FORWARDED_SERVER'];
		//
		//check that the client IP is not equal to the host server IP
		if(isset($_SERVER['HTTP_CLIENT_IP']) && $serverAddr!=$_SERVER['HTTP_CLIENT_IP'] && $ipAddress!=$_SERVER['HTTP_CLIENT_IP']){
			if(strpos($proxylist,$_SERVER["HTTP_CLIENT_IP"])===false){
				$IPlist=$_SERVER['HTTP_CLIENT_IP'].",".$proxylist;
				$proxylist=$IPlist;
			}
			$ipAddress=$_SERVER['HTTP_CLIENT_IP'];
		}
		if(isset($_SERVER['HTTP_X_REAL_IP']) && $serverAddr!=$_SERVER['HTTP_X_REAL_IP'] && $ipAddress!=$_SERVER['HTTP_X_REAL_IP']){
			if(strpos($proxylist,$_SERVER["HTTP_X_REAL_IP"])===false){
				$IPlist=$_SERVER['HTTP_X_REAL_IP'].",".$proxylist;
				$proxylist=$IPlist;
			}
			$ipAddress=$_SERVER['HTTP_X_REAL_IP'];
		}
		//check for IP addresses from Cloudflare CDN-hosted sites
		if(isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $serverAddr!=$_SERVER['HTTP_CF_CONNECTING_IP'] && $ipAddress!=$_SERVER['HTTP_CF_CONNECTING_IP']){
			if(strpos($proxylist,$_SERVER["HTTP_CF_CONNECTING_IP"])===false){
				$IPlist=$_SERVER['HTTP_CF_CONNECTING_IP'].",".$proxylist;
				$proxylist=$IPlist;
			}
			$ipAddress=$_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		//check for proxy addresses
		if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) && $serverAddr!=$_SERVER['HTTP_X_FORWARDED_FOR'] && $ipAddress!=$_SERVER['HTTP_X_FORWARDED_FOR']){
			if(strpos($proxylist,$_SERVER['HTTP_X_FORWARDED_FOR'])===false){
				$IPlist=$_SERVER['HTTP_X_FORWARDED_FOR'].",".$proxylist;
				$proxylist=$IPlist;
			}
			$ipAddress=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if(!empty($_SERVER["HTTP_X_FORWARDED"]) && $serverAddr!=$_SERVER["HTTP_X_FORWARDED"] && $ipAddress!=$_SERVER['HTTP_X_FORWARDED']){
			if(strpos($proxylist,$_SERVER['HTTP_X_FORWARDED'])===false){
				$IPlist=$_SERVER['HTTP_X_FORWARDED'].",".$proxylist;
				$proxylist=$IPlist;
			}
			$ipAddress=$_SERVER['HTTP_X_FORWARDED'];
		}
		//try get valid IP
		$IP = self::validIP($ipAddress);
		if(empty($IP) && $ipAddress!=$proxylist){
			$proxylist=preg_replace('/(^|[^0-9\.])'.preg_quote($ipAddress).'($|[^0-9\.])/','',$IPlist);
			$IP=self::validIP($proxylist);
		}
		if(!empty($IP)){
			$p=strpos($IPlist,$IP)+strlen($IP)+1;
			if($p < strlen($IPlist)) $proxylist=substr($IPlist,$p);
			else $proxylist="";
		}
		//check client hostname for known proxy gateways
		if(!empty($IP)){
			$hostname=self::get_hostname($IP);
			if(preg_match('/(cloudflare\.|cache|gateway|proxy|unknown$|localhost$|\.local(?:domain)?$)/',$hostname)>0){
				$ip1=$IP;
				if(!empty($proxylist)) $IP=self::validIP($proxylist);
				if(!empty($IP)){
					$p=strpos($IPlist,$IP)+strlen($IP)+1;
					if($p < strlen($IPlist)) $proxylist=substr($IPlist,$p);
					else $proxylist="";
				}else{
					$IP=$ip1;
				}
			}
			if(!empty($proxylist)) $proxy=self::validIP($proxylist);
			if(!empty($proxy)) $ipAddress=$proxy.','.$IP;
			else $ipAddress=$IP;
		}
		return $ipAddress;
	} //end get_clientAddr

	/** lookup the hostname from an ip address via cache or via gethostbyaddr command @since v1.9 */
	static function get_hostname($IP=""){
		if(empty($IP)) $IP=self::clientIP($_SERVER['REMOTE_ADDR']);
		//first check for cached hostname
		$hostname=wassupDb::get_wassupmeta($IP,'hostname');
		if(empty($hostname)){
			if($IP=="127.0.0.1" || $IP=='::1' || $IP=='0:0:0:0:0:0:0:1'){
				$hostname="localhost";
			}elseif($IP=="0.0.0.0" || $IP=='::' || $IP=='0:0:0:0:0:0:0:0'){
				$hostname="unknown";
			}else{
				$hostname=@gethostbyaddr($IP);
				if(!empty($hostname) && $hostname!=$IP && $hostname!="localhost" && $hostname!="unknown"){
					$meta_key='hostname';
					$meta_value=$hostname;
					$expire=time()+48*3600; //cache for 2 days
					$cache_id=wassupDb::update_wassupmeta($IP,$meta_key,$meta_value,$expire);
				}
			}
		}
		return $hostname;
	} //end get_hostname
} //end Class
} //end if !class_exists
?>

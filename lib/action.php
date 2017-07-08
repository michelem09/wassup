<?php
/**
 * Performs an action and outputs result as html - for ajax tasks.
 *
 * @package WassUp Real-time Analytics
 * @subpackage action.php
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
/**
 * Wassup ajax action handler function.
 * 
 * @since v1.9.1
 * @param string (action type)
 */
function wassup_action_handler($action=""){
	global $wpdb,$wassup_options,$current_user,$wdebug_mode;
	//check for action request
	if(empty($action) && !empty($_REQUEST['action'])){
		$action=$_REQUEST['action'];
	}
	if($action=="wassup_action_handler"){
		$action="";
		if(!empty($_REQUEST['type'])) $action=$_REQUEST['type'];
	}
	//check for invalid Wassup action requests
	if(empty($action)){
		if($_REQUEST['action']=="wassup_action_handler"){
			$msg=__("Missing or invalid parameter!","wassup");
			die($msg);
		}else{
			//return not exit, in case is Wordpress action
			return;
		}
	}
	//must have a Wassup referrer
	if(empty($_SERVER['HTTP_REFERER']) || stristr($_SERVER['HTTP_REFERER'],"wassup")===false){
		die(__("Bad request!","wassup"));
	}
	//..must be logged in
	if(!is_user_logged_in()) die(__("Login required!","wassup"));

	// check for valid hash
	if(!isset($_REQUEST['whash'])){	//don't reveal 'whash' name
		die(__('Missing or invalid parameter!','wassup'));
	}
	$whash=$_REQUEST['whash'];
	$wassup_settings=get_option('wassup_settings');
	if(!empty($wassup_settings['whash']) && ($whash == $wassup_settings['whash'] || $whash == htmlspecialchars($wassup_settings['whash'],ENT_QUOTES))){
			$hashfail=false;
	}else{
		$hashfail=true;
	}
	if($hashfail){ //don't reveal 'hash'
		die(__('invalid parameter!','wassup'));
	}
	if(empty($current_user->ID)) $user=wp_get_current_user();
	if(!class_exists('wassupOptions')){
		if(!wassup_init()) die(__("Nothing to do","wassup"));
	}
	if(empty($wassup_options->wassup_table)) $wassup_options=new wassupOptions;
	//#Ajax action / no output (unless error)
	// ACTION: DELETE ON THE FLY FROM VISITOR DETAILS VIEW
	if($action=="deleteID"){
		if(!empty($_REQUEST['id'])){
			//id must be simple chars
			$wassup_id=$wassup_options->cleanFormText($_REQUEST['id']);
			$wassup_table=$wassup_options->wassup_table;
			if($wassup_id==$_REQUEST['id'] && !empty($wassup_table)){
				//only administrators can delete
				if(current_user_can('manage_options') && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'],'wassupdeleteID-'.$current_user->ID)){
					$deleted=$wpdb->query(sprintf("DELETE FROM `$wassup_table` WHERE `wassup_id`='%s'",$wassup_id));
					if(!is_numeric($deleted)){
						$msg=__("An error occurred during delete of","wassup")." id=".$wassup_id." ";
						if(!empty($deleted) && is_wp_error($deleted)){
							$errno=$deleted->get_error_code();
							if((int)$errno > 0) $msg.="<br/> $errno: ".$deleted->get_error_message()."\n";
						}else{
							$msg .='<br/> '.__("Error","wassup").':'.esc_attr($deleted);
						}
						$deleted=0;
					}else{
						$msg=sprintf(__("%d records deleted!","wassup"),$deleted);
					}
					if($deleted==0){
						if(!empty($atype)) return $msg;
						else echo $msg;
					}
				}else{
					die(__("Error","wassup").": admin login required!");
				}
			}else{
				die(__("Error","wassup").": invalid parameter: ".esc_attr($_REQUEST['id']));
			}
		}else{
			die(__("Error","wassup").": missing id parameter");
		}
		exit;

	// ACTION: RETURN MESSAGE FROM AN EXPORT
	}elseif($action=="exportmessage"){
		$msg="";
		$msgid='0';
		if(isset($_REQUEST['mid'])) $msgid=$_REQUEST['mid'];
		if(!empty($msgid)){
			$msg=wassupDb::get_wassupmeta($msgid,'_export_msg');
		}
		echo $msg;
		exit;
	}
	//#Ajax action with html output
	$vers='?ver='.WASSUPVERSION;
	if($wdebug_mode) $vers.='b'.rand(0,9999);
	$html_head= '
<!DOCTYPE html>
<html>
<head>
 <title>WassUp '.esc_attr($action).'</title>
 <link rel="stylesheet" href="'.WASSUPURL.'/css/wassup.css'.$vers.'" type="text/css" />
</head>
<body class="wassup-ajax">'."\n";
	$html_foot='
</body>
</html>';
	//#retrieve common query arguments
	$to_date=0;
	$from_date=0;
	if (isset($_REQUEST['to_date']) && is_numeric($_REQUEST['to_date'])) {
		$to_date = (int)$_REQUEST['to_date'];
	} else {
		$to_date = current_time('timestamp');
	}
	if (isset($_REQUEST['from_date']) && is_numeric($_REQUEST['from_date'])) {
		$from_date = (int)$_REQUEST['from_date'];
	} else {
		$from_date = ($to_date - 180);	//3 minutes
	}
	//#check that date range is valid
	if ($to_date < $from_date || $from_date < strtotime("Jan 1,2005") || $to_date > time()+86400) { //bad date sent
		die(__("ERROR: bad date parameter","wassup"));
	}
	// ACTION: RUN SPY VIEW
	if($action == "Spia"){
		$rows=0;
		$spytype="";
		//cannot use 'get_user_option' for spy timestamp...query caching causes duplicates (needs SQL_NO_CACHE)
		//$wassup_user_settings=get_user_option('_wassup_settings');
		//$from_spydate=$wassup_user_settings['utimestamp'];
		$from_spydate=wassupDb::get_wassupmeta($current_user->user_login,"_spytimestamp",true);
		if(empty($from_spydate) || !is_numeric($from_spydate)) $from_spydate="";
		if(!empty($_REQUEST['rows']) && is_numeric($_REQUEST['rows'])) $rows = (int)$_REQUEST['rows'];
		if(!empty($_REQUEST['spiatype'])) $spytype=$wassup_options->cleanFormText($_REQUEST['spiatype']);
		if(!function_exists('wassup_spiaView')){
			require_once(WASSUPDIR . "/lib/main.php");
		}
		//force browser to disable caching for ajax request
		nocache_headers();
		wassup_spiaView($from_spydate,$rows,$spytype);
		exit;

	// ACTION: SHOW TOP TEN
	} elseif ($action=="Topstats") {
		$top_limit=0;
		$title="";
		$res=670;
		if(isset($_REQUEST['width']) && is_numeric($_REQUEST['width'])){
			$res = (int)$_REQUEST['width'];
		}
		if(!function_exists('wassup_top10view')){
			require_once(WASSUPDIR . "/lib/main.php");
		}
		//show title and print button in popup window
		if(!empty($_REQUEST['popup'])){
			$res=$wassup_options->wassup_screen_res;
			echo '<html>
<head>
<title>'.$title.'</title>
<link rel="stylesheet" id="wassup-style-css"  href="'.WASSUPURL.'/css/wassup.css?ver='.$vers.'" type="text/css" media="all" />
<script type="text/javascript">function printstat(){if(typeof(window.print)!="undefined")window.print();}</script>
</head>
<body class="wassup-ajax">
<div id="wassup-wrap" class="topstats topstats-print">'."\n";
		}else{
			echo $html_head; 
			echo '<div id="wassup-wrap" class="topstats">'."\n";
			$title=false;
		}
		wassup_top10view($from_date,$to_date,$res,$top_limit,$title);
		echo '</div><!-- /wassup-wrap -->'."\n";
		echo $html_foot;
		exit;
	}else{
		die(__("Error: Nothing to do!","wassup"));
	} //end if action
} //end wassup_action_handler
?>

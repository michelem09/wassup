<?php
/**
 * Defines Wassup functions and classes for Wassup admin pages and widget
 *
 * @package WassUp Real-time Analytics
 * @subpackage wassupadmin.php module
 * @since:	v1.9
 * @author:	helened <http://helenesit.com>
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
/**
 * Setup actions, filters and settings for admin page, menus, and dashboard widget.
 *  - reset Wassup user settings for new upgrades
 *  - add Wassup messages to Admin notices
 *  - add Wassup css and embedded javascripts to Admin header
 *  - add Wassup menus, submenus, and links to Admin menus and links
 *  - add Wassup dashboard widgets to Admin dashboard
 *  - load the admin interface
 * @since v1.9
 */
function wassup_admin_load(){
	global $current_user, $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	//get/set user-specific wassup_settings
	if(!is_object($current_user) || empty($current_user->ID)) wp_get_current_user();
	$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
	//reset user settings after plugin upgrade
	if(!empty($wassup_user_settings) && (empty($wassup_user_settings['uversion']) || $wassup_user_settings['uversion'] != WASSUPVERSION)){
		$wassup_user_settings=$wassup_options->resetUserSettings($current_user->user_login,$current_user);
	}
	//admin_notices filter to show Wassup messages @since v1.9
	if(is_network_admin()){
		add_action('network_admin_notices',array(&$wassup_options,'showMessage'));
	}elseif(empty($_GET['page'])|| stristr($_GET['page'],'wassup')!==false){
		add_action('admin_notices',array(&$wassup_options,'showMessage'));
	}elseif(!empty($wassup_user_settings['ualert_message'])){
		//show user-specific messages in all admin panels
		add_action('admin_notices',array(&$wassup_options,'showMessage'));
	}
	//for embed of javascripts and css tags in admin head
	add_action('admin_head','wassup_embeded_scripts',11);
	add_action('admin_head','wassup_add_css',11);
	//for admin menu and dashboard submenu
	if($wassup_options->network_activated_plugin() && is_network_admin()){
		add_action('network_admin_menu','wassup_add_pages');
	}else{
		add_action('admin_menu','wassup_add_pages');
	}
	//show dashboard widget when 'wassup_active' is set and when user is admin
	if($wassup_options->is_recording_active() || $wassup_options->is_admin_login()){
		//initialize dashboard widget
		if(is_multisite()) $network_settings=get_site_option('wassup_network_settings');
		else $network_settings=array();
		if(is_network_admin() && !empty($network_settings['wassup_table'])){
			add_action('wp_network_dashboard_setup',array('wassup_Dashboard_Widgets','init'));
		}elseif(!empty($wassup_options->wassup_dashboard_chart)){
			add_action('wp_dashboard_setup',array('wassup_Dashboard_Widgets','init'));
		}
	}
	if(!empty($_GET['page']) && stristr($_GET['page'],'wassup')!==FALSE){
		//initialize user settings for Wassup, as needed
		if(empty($wassup_user_settings)) {
			$wassup_user_settings=$wassup_options->defaultSettings('wassup_user_settings');
			update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
		}
		//for display of Wassup page contents...only add-on modules need do this
		//add_action('wassup_page_content','wassup_page_contents',10,1);
	}
} //end wassup_admin_load

/**
 * Embed javascripts into document head of Wassup admin panel pages.
 * -embed timer and automatic reload javascripts in Visitor details
 * -embed jquery code for ajax actions in Visitor details/Online
 * -embed Google!Map API tag and scripts for map setup in Wassup-spy.
 * -embed thickbox loading image tag in Wassup pages
 *
 * @since v1.9
 * @param string $wassuppage
 * @return void
 */
function wassup_embeded_scripts($wassuppage="") {
	global $current_user,$wassup_options,$wdebug_mode;

	$vers=WASSUPVERSION;
	if($wdebug_mode) $vers .='b'.rand(0,9999);
	//restrict embedded javascripts to wassup admin pages only...
	if(!empty($_GET['page']) && stristr($_GET['page'],'wassup')!== FALSE){
		if(empty($wassuppage)) $wassuppage=wassupURI::get_menu_arg();
		//assign a value to whash, if none
		if (empty($wassup_options->whash)) {
			$wassup_options->whash = $wassup_options->get_wp_hash();
			$wassup_options->saveSettings();
		}
		if(empty($current_user->ID)) $user=wp_get_current_user();
		$wassup_user_settings=get_user_option('_wassup_settings');
		$wnonce=(!empty($wassup_user_settings['unonce'])?$wassup_user_settings['unonce']:'');
		//preassign parameters for ajax actions
		$action_param=array('action'=>"wassup_action_handler",'wajax'=>1,'whash'=>$wassup_options->whash);
		//screen refresh setting
		$wrefresh = (int) $wassup_options->wassup_refresh;
	//embed javascripts on wassup pages
	if($wassuppage=="wassup"){
		//set auto refresh URL 
		$refresh_loc='location.reload(true)';
		//don't use "location.reload" when POST data exists or when some GET params like 'deleteMarked' are set
		if(!empty($_POST) || isset($_GET['deleteMARKED']) || isset($_GET['chart']) || isset($_GET['dip']) || isset($_GET['mark']) || isset($_GET['search-submit'])){
			$URLQuery=trim(html_entity_decode($_SERVER['QUERY_STRING']));
			if(empty($URLQuery) && preg_match('/[^\?]+\?([A-Za-z\-_]+.*)/',html_entity_decode($_SERVER['REQUEST_URI']),$pcs)>0) $URLQuery=$pcs[1];
			if(!empty($URLQuery)){
				$refresh_loc='location.href="?'.wassupURI::cleanURL($URLQuery).'"';
				if(isset($_GET['deleteMARKED']) || isset($_GET['chart']) || isset($_GET['dip']) || isset($_GET['mark']) || isset($_GET['search-submit'])){
					$remove_args=array('deleteMARKED','dip','chart','mark','submit-search');
					$newURL=remove_query_arg($remove_args);
 					if(!empty($newURL) && $newURL != $_SERVER['REQUEST_URI']){
						$refresh_loc='location.href="'.wassupURI::cleanURL($newURL).'"';
					}
				}
			}
		}
		//restrict refresh to range 0-180 mins (3 hrs)
 		if($wrefresh < 0 || $wrefresh >180){
			$wrefresh=3; //3 minutes default;
		} 
		//embed refresh javascripts
?>
<script type='text/javascript'>
//<![CDATA[
  var paused=" *<?php _e('paused','wassup'); ?>* ";
  function wassupReload<?php echo $wnonce;?>(wassuploc){if(wassuploc!=="") location.href=wassuploc;else location.reload(true);}
  function wSelfRefresh(){<?php echo $refresh_loc;?>}
  jQuery(document).ready(function($){
	$("a.showhide").click(function(){var id=$(this).attr('id');$("div.navi"+id).toggle("slow");return false;});
	$("a.toggleagent").click(function(){var id=$(this).attr('id');$("div.naviagent"+id).slideToggle("slow");return false;});
	$("img.delete-icon").mouseover(function(){$(this).attr("src","<?php echo WASSUPURL.'/img/b_delete2.png';?>");}).mouseout(function() {$(this).attr("src","<?php echo WASSUPURL.'/img/b_delete.png';?>");});
	$("img.table-icon").mouseover(function(){$(this).attr("src","<?php echo WASSUPURL.'/img/b_select2.png';?>");}).mouseout(function(){$(this).attr("src","<?php echo WASSUPURL.'/img/b_select.png';?>");});<?php
		echo "\n";
		//only administrators can delete
		if(current_user_can('manage_options')){
			//add nonce to query vars to validate deleteID @since v1.9.1.
			$action_param['_wpnonce']=wp_create_nonce('wassupdeleteID-'.$current_user->ID);
			//format 'action_param' for ajax post data
			$postparams="";
			foreach($action_param AS $key => $value){
				if(preg_match('/[0-9a-z\-_ ]/i',$key)>0) {
					$postparams .= "'".$key."':'".preg_replace('/\'/','\\\'',esc_attr($value))."',";
				}
			}?>
	$("a.deleteID").click(function(){
		var id=$(this).attr('id');
		$("div#delID"+id).css("background-color","#ffcaaa");
		$("div#delID"+id).find("ul.url li").css("background-color","#ffcaaa");
		$.ajax({
		  url: ajaxurl,
		  method: 'POST',
  		  data: {'type':'deleteID','id':id,<?php echo $postparams;?>},
		  success: function(html){
		  	if(html=="") $("div#delID"+id).fadeOut("slow");
		  	else $("div#delID"+id).find('p.delbut').append("<br/><br/><small style='color:#404;font-weight:bold;text-align:right;float:right;'> <nobr><?php _e('Sorry, delete failed!','wassup');?></nobr> "+html+" </small>");
		  	},
		  error: function(XMLHttpReq,txtStatus,errThrown){
		  	$("div#delID"+id).find('p.delbut').append("<br/><br/><small style='color:#404;font-weight:bold;text-align:right;float:right;'> <nobr><?php _e('Delete record failed!','wassup');?></nobr> "+txtStatus+": "+errThrown+"</small>");
		  	},
		});
		return false;
	});<?php
			echo "\n";
		}?>
	$("a.show-search").toggle(function(){<?php
		if (empty($_GET['search'])){
			echo "\n";?>
		$("div.search-ip").slideDown("slow");$("a.show-search").html("<?php _e('Hide Search','wassup');?>");
	},function(){
		$("div.search-ip").slideUp("slow");$("a.show-search").html("<?php _e('Search','wassup');?>");return false;<?php	
		} else {
			echo "\n";?>
		$("div.search-ip").slideUp("slow");$("a.show-search").html("<?php _e('Search','wassup');?>");
	},function(){
		$("div.search-ip").slideDown("slow");$("a.show-search").html("<?php _e('Hide Search','wassup');?>");return false;<?php
			echo "\n";
		}?>
	});
	$("a.toggle-all").toggle(function(){
		$("div.togglenavi").slideDown("slow");$("a.toggle-all").html("<?php _e('Collapse All','wassup');?>");
	},function(){
		$("div.togglenavi").slideUp("slow");$("a.toggle-all").html("<?php _e('Expand All','wassup');?>");return false;
	});
	$("a.toggle-allcrono").toggle(function(){
		$("div.togglecrono").slideUp("slow");$("a.toggle-allcrono").html("<?php _e('Expand Chronology','wassup');?>");
	},function(){
		$("div.togglecrono").slideDown("slow");$("a.toggle-allcrono").html("<?php _e('Collapse Chronology','wassup');?>");return false;
	});
<?php
		if ($wrefresh > 0) {  ?>
	$("#CountDownPanel").click(function(){
		var timeleft=_currentSeconds*1000;
		if(tickerID !=0){
			clearInterval(tickerID);
			clearTimeout(selftimerID);
			tickerID=0;
			$(this).css('color','#999').html(paused);
		}
		else{
			if(_currentSeconds < 1) timeleft=1000;
			selftimerID=setTimeout('wSelfRefresh()',timeleft);
			tickerID=window.setInterval("CountDownTick()",1000);
			$(this).css('color','#555');
		}
	});
<?php
		} //end if $wrefresh > 0 (2nd)
?>
  }); //end jQuery(document).ready
//]]>
</script><?php
		echo "\n";
	}elseif($wassuppage == "wassup-online"){
		//always refresh wassup-online page every 1-3 mins
		if($wrefresh >3 || $wrefresh < 1) $wrefresh=3;
?>
<script type="text/javascript">
//<![CDATA[
  function wSelfRefresh(){location.reload(true)}
  var refreshID=setTimeout('wSelfRefresh()',<?php echo ($wrefresh*60000)+2000;?>);
  jQuery(document).ready(function($){
	$("a.showhide").click(function(){var id=$(this).attr('id');$("div.navi"+id).toggle("slow");return false;});
	$("a.toggle-all").toggle(function(){
		$("div.togglenavi").slideDown("slow");$("a.toggle-all").html("<?php _e('Collapse All','wassup'); ?>");
	},function(){
		$("div.togglenavi").slideUp("slow");$("a.toggle-all").html("<?php _e('Expand All','wassup');?>");return false;
	});
  });
//]]>
</script><?php
		echo "\n";
	}elseif($wassuppage=="wassup-options" || $wassuppage=="wassup-donate"){
?>
<script type="text/javascript">
//<![CDATA[
<?php
	//New in v1.9.4: ajax script to check download status of dynamically generated export file
?>
var exportID="";
var exportTimerCount=0;
var exportTimerID=0;
function checkExportstatus(msgID){
	if(exportID == "") exportID=msgID;
	exportTimerCount +=1;
	jQuery(function($){
		if(exportTimerCount >30){ //stop timer after 1 min
			var msg="<?php echo __('timed out!','wassup');?>";
			$("#wassup-dialog >p").append(msg);
			stopExportTimer();
		}
		var request = $.ajax({
			url: ajaxurl,
			method: "POST",
			dataType: "html",
		  	data: {'type':"exportmessage",'mid':exportID,<?php
			//format 'action_param' for ajax post data
			$postparams="";
			foreach($action_param AS $key => $value){
				if(preg_match('/[0-9a-z\-_ ]/i',$key)>0) {
				$postparams .= "'".$key."':'".preg_replace('/\'/','\\\'',esc_attr($value))."',";
				}
			}
			echo $postparams;?>},
			});
		request.done(function(msg){
			if(msg == ""){
				$("#wassup-dialog >p").append("..");
			}else{
				$("#wassup-dialog >p").html(msg);
				exportTimerCount=0;
				stopExportTimer();
			}
		});
	});
}
function startExportTimer(msgID){
	exportTimerID=setInterval("checkExportstatus()",2000,msgID);
	exportTimerCount=0;
	jQuery(function($){
		$("#wassup-overlay").addClass("ui-widget-overlay");
		$("#wassup-dialog >p").html("<?php echo __('Retrieving data for export. Download will start soon. Please wait.','wassup');?> ");
		$("#wassup-dialog").dialog("open");
		$("#wassup-dialog").on("dialogclose",function(event,ui){
			stopExportTimer();
		});
	});
}
function stopExportTimer(){
	if(exportTimerID >0) clearInterval(exportTimerID);
	if(exportTimerCount==0) exportTimerID=0;
	jQuery(function($){
		$("#wassup-overlay").removeClass("ui-widget-overlay");
	});
}
jQuery(document).ready(function($) {
	//initialize tabs
	var tabs=$('#tabcontainer').tabs();
	$('.submit-opt').click(function(){$(this).css("background-color","#d71");});
	$('.default-opt').click(function(){$(this).css("background-color","#d71");});
	$("a#BCdonate").toggle(function(){$('div#bc_placeholder').slideDown("slow");},function(){$('div#bc_placeholder').slideUp("slow");return false;});
<?php
	//new in v1.9.4: dialog and javascripts for export action
?>
	$('#wassup-dialog').dialog({
		modal:true,
		autoOpen:false,
		draggable:false,
		resizable:false,
	});
	$(".export-link").click(function(e){
		e.preventDefault();
		e.returnValue=false;
		//only 1 instance of "export" allowed at a time
		if(exportTimerID==0){
			exportID=$(this).attr('id');
			startExportTimer(exportID);
			location.href=$(this).attr('href');
		}
	});
});
//]]>
</script><?php
		echo "\n";
	}elseif($wassuppage=="wassup-spia" || $wassuppage=="wassup-spy"){
		// GEO IP Map
		//google!Maps map init and marker javascripts in document head @since v1.9
		if($wassup_user_settings['spy_map']== 1 || !empty($_GET['map'])){
			//check for api key for Google!maps
			$apikey=$wassup_options->get_apikey();
			echo '<script src="https://maps.googleapis.com/maps/api/js?key='.esc_attr($apikey).'" type="text/javascript"></script>';
		} //end if spy_map
		//add 'action_param' query params to ajaxurl
		$action_param['type']="Spia";
		$ajaxurl=wassupURI::get_ajax_url("Spia");
		$spyajax=add_query_arg($action_param,$ajaxurl);
?>
<script type="text/javascript">
//<![CDATA[
function wassupReload<?php echo $wnonce;?>(wassuploc){if(wassuploc!=="")location.href=wassuploc;}
jQuery(document).ready(function($){
	$('#spyContainer > div:gt(4)').fadeEachDown(); // initial fade
	$('#spyContainer').spy({
		limit:15,
		fadeLast:5,
		ajax: '<?php echo wassupURI::cleanURL($spyajax);?>',
		timeout:5000,
		'timestamp':spiaTimestamp,
		'method':"html",
		fadeInSpeed:800,
		});
	$('#spy-pause').click(function(){
		$(this).css("background-color","#ebb");$("#spy-play").css("background-color","#eae9e9");<?php
		if(!empty($wassup_user_settings['spy_map']) || !empty($_GET['map'])) echo '$("div#spia_map").css({"opacity":"0.7","background":"none"});';?>
		if(spyRunning==1) spyRunning=0;
	});
	$('#spy-play').click(function(){
		$(this).css("background-color","#cdc");$("#spy-pause").css("background-color","#eae9e9");<?php
		if(!empty($wassup_user_settings['spy_map']) || !empty($_GET['map'])) echo '$("div#spia_map").css("opacity","1");';?>
		if(spyRunning==0) spyRunning=1;
	});
});
<?php
		if ($wassup_user_settings['spy_map']==1 || !empty($_GET['map'])) {?>
var spiamap;
var pinuser={url:"<?php echo WASSUPURL.'/img/marker_user.png';?>",size: new google.maps.Size(20.0,34.0),origin: new google.maps.Point(0,0),anchor: new google.maps.Point(10.0,34.0)};
var pinlogged={url:"<?php echo WASSUPURL.'/img/marker_loggedin.png';?>",size: new google.maps.Size(20.0,34.0),origin: new google.maps.Point(0,0),anchor: new google.maps.Point(10.0,34.0)};
var pinauthor={url: "<?php echo WASSUPURL.'/img/marker_author.png';?>",size: new google.maps.Size(20.0,34.0),origin: new google.maps.Point(0,0),anchor: new google.maps.Point(10.0,34.0)};
var pinbot={url: "<?php echo WASSUPURL.'/img/marker_bot.png';?>",size: new google.maps.Size(20.0,34.0),origin: new google.maps.Point(0,0),anchor: new google.maps.Point(10.0,34.0)};
function wassupMapinit(canvas,clat,clon){
	var mapOptions={zoom:3, mapTypeId:google.maps.MapTypeId.ROADMAP};
	spiamap=new google.maps.Map(document.getElementById(canvas), mapOptions);
	var pos=new google.maps.LatLng(clat,clon);
	spiamap.setCenter(pos);
}
function showMarkerinfo(mmap,mlat,mlon,marker,markerwin){
	document.body.scrollTop=document.documentElement.scrollTop=0;
	mmap.panTo(new google.maps.LatLng(mlat,mlon));
	mmap.setZoom(5);
	markerwin.open(mmap,marker);
}
<?php
		} //end if spy_map
?>
//]]>
</script><?php
		echo "\n";
	}else{ //end if wassuppage == "wassup-spia"
	}
?>
<script type='text/javascript'>var tb_pathToImage="<?php echo WASSUPURL.'/js/thickbox/loadingAnimation.gif';?>";</script>
<?php
	} //end if _GET['page']
} //end wassup_embeded_scripts

/**
 * Add wassup stylesheets tags and embeds css code in document head.
 * -add link tags to jquery-ui stylesheets in Wassup options page
 * -add thickbox.css link tag in wassup pages (as override)
 * -embed styles for overriding some default Wordpress & plugins styles
 * -assign an admin body class (wassup, wassup-wp-legacy) for wassup page styling
 */
function wassup_add_css() {
	global $wassup_options,$wdebug_mode;
	//jqueryui-css and thickbox.css to wassup pages
	$wassuppage=wassupURI::get_menu_arg();
	if(!empty($wassuppage) && strpos($wassuppage,'wassup')!==FALSE){
		//TODO: Add a WassUp favicon to wassup pages
		//output the stylesheet links
		//always use Wassup's jquery-ui.css in Wassup-options
		if($wassuppage=="wassup-options"){
			echo "\n";
			if(!$wdebug_mode){
				echo '<link href="'.WASSUPURL.'/css/jquery-ui/jquery-ui.min.css" rel="stylesheet" type="text/css" />'."\n";
			}else{
				echo '<link href="'.WASSUPURL.'/css/jquery-ui/jquery-ui.css" rel="stylesheet" type="text/css" />'."\n";
			}
		}
		//always use Wassup's thickbox.css in Wassup panels
		if($wassuppage=="wassup" || $wassuppage=="wassup-online"){?>
<link rel="stylesheet" href="<?php echo WASSUPURL.'/js/thickbox/thickbox.css';?>" type="text/css" /><?php
			echo "\n";
		}
		// Override some Wordpress css and Wassup default css settings on Wassup pages
?>
<style type="text/css">
#contextual-help-link{display:none;}
.update-nag{display:none;} /* nag messes up tab menus, so hide it */
</style>
<!--[if lt IE 8]>
<style type="text/css">#wassup-menu li{width:120px;}</style>
<![endif]-->
<?php
		echo "\n";
	}else{
		//embed style for Wassup admin notices in admin panels
?>
<style type="text/css">
#wassup-message{font-size:13px;color:#447;padding:10px;}
#wassup-message.error{color:#d00;}
#wassup-message.notice-warning{color:#447;}
#wassup-message.updated{color:#040;}
</style><?php
	}
	//add "wassup" and "wassup_legacy" body classes for Wassup pages and widget styles @since v1.9
	add_filter('admin_body_class','wassup_add_body_class');
} //end wassup_add_css

/**
 * Add "wassup" and "wassup-wp-legacy" body class to Wassup pages.
 * @since v1.9
 * @param string (comma-separated classes)
 * @return string 
 */
function wassup_add_body_class($classes) {
	global $wp_version;
	$body_class="";
	if(empty($_GET['page'])|| stristr($_GET['page'],'wassup')!==FALSE){ 
		$body_class="wassup";
		if(version_compare($wp_version,'3.8','<')) $body_class="wassup-wp-legacy";
	}elseif(strpos($_SERVER['REQUEST_URI'],'widgets.php')>0){
		if(version_compare($wp_version,'3.8','<')) $body_class="wassup-wp-legacy";
	}
	if(!empty($body_class)){
		if(is_array($classes)) $classes[]=$body_class;
		else $classes .=" $body_class";
	}
	return $classes;
}
/**
 * WassUp admin menus, submenus, and plugin links setup.
 * - adds Wassup main admin menu
 * - adds 'wassup-stats' admin dashboard submenu
 * - adds 'settings' link to plugins panel.
 */
function wassup_add_pages() {
	global $wp_version, $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	$menu_access=$wassup_options->get_access_capability();
	$wassupfolder=basename(WASSUPDIR);
	//only administrators can see wassup's top-level admin menu...other users see "Wassup-stats" dashboard submenu (and dash widget) @since v1.9
	$show_wassup_menu=false;
	if(current_user_can('manage_options')){
		$show_wassup_menu=true;
		if(is_multisite() && !is_super_admin() && !is_network_admin()){
			$network_settings=get_site_option('wassup_network_settings');
			if(empty($network_settings['wassup_menu'])) $show_wassup_menu=false;
		}
	}
	//show Wassup's top-level menu
	if($show_wassup_menu){
		// add the default submenu first (important!)
		if(version_compare($wp_version,'3.8','>=')) add_menu_page('Wassup','WassUp',$menu_access,$wassupfolder,'WassUp','dashicons-chart-area');
		else add_menu_page('Wassup','WassUp',$menu_access,$wassupfolder,'WassUp');
		add_submenu_page($wassupfolder,__("Visitor Details","wassup"),__("Visitor Details","wassup"),$menu_access,$wassupfolder,'WassUp');
		add_submenu_page($wassupfolder,__("Spy Visitors","wassup"),__("SPY Visitors","wassup"),$menu_access,'wassup-spia','WassUp');
		add_submenu_page($wassupfolder,__("Current Visitors Online","wassup"),__("Current Visitors Online","wassup"),$menu_access, 'wassup-online','WassUp');
		//WassUp settings available at 'manage_options' access level only
		add_submenu_page($wassupfolder,__("Options","wassup"),__("Options","wassup"),'manage_options','wassup-options','WassUp');
	}
	//add Wassup Stats submenu on WP2.7+ dashboard menu
	//add "settings" to action links on "plugins" page
	if(version_compare($wp_version,'2.7','>=')){
		add_submenu_page('index.php',__("WassUp Stats","wassup"),__("WassUp Stats","wassup"),$menu_access,'wassup-stats','WassUp');

		add_filter("plugin_action_links_".$wassupfolder."/wassup.php",'wassup_plugin_links',-10,2);
	}elseif(version_compare($wp_version,'2.5','>=')){
		add_filter('plugin_action_links','wassup_plugin_links',-10,2);	//WP 2.5+ filter
	}
} //end wassup_add_pages
/**
 * Adds a 'settings' link to Wassup-options page in the action links on Wordpress' plugins panel.
 * @since v1.8
 * @param (2) array, string
 * @return array
 */
function wassup_plugin_links($links, $file){
	global $wassup_options;
	if(!defined('WASSUPURL')){
		if(!wassup_init()) return;	//nothing to do
	}
	if($file == plugin_basename(WASSUPDIR."/wassup.php")){
		if(is_multisite() && is_network_admin() && $wassup_options->network_activated_plugin()){
			$links[] = '<a href="'.network_admin_url('admin.php?page=wassup-options').'">'.__("Settings").'</a>';
		}else{
			$links[] = '<a href="'.admin_url('admin.php?page=wassup-options').'">'.__("Settings").'</a>';
		}
	}
	return $links;
} // end function wassup_plugin_links
/**
 * Add a horizontal navigation (tab) menu to Wassup pages.
 * - automatically adds tab links for each submenu in Wassup's main menu when available (admin users only)
 * - adds tab links to Wassup-stats dashboard submenu using the 'ml' query parameter
 * - appends a "Donate" and "FAQ" tab to menu
 * @author helened
 * @since v1.9
 */
function wassup_menu_links($selected=""){
	global $submenu,$wp_version,$wassup_options,$wdebug_mode;
	if(empty($selected)){
		$selected=(isset($_GET['page'])?$_GET['page']:"");
		$i=strpos($selected,"#"); //remove anchor from param
		if(!empty($i)) $selected=substr($selected,0,$i);
	}
	$wassupfolder=basename(WASSUPDIR);
	echo "\n";?>
	<div id="wassup-screen-links">
	<ul id="wassup-menu"><?php
	if(!empty($submenu[$wassupfolder]) && is_array($submenu[$wassupfolder])){
		$wassupmenu=$submenu[$wassupfolder];
		//submenus from wassup addons are included here
		$submenu_count=count($wassupmenu);
		for($i=$submenu_count-1;$i>=0;$i--){
			$menu_access=$wassupmenu[$i][1];
			$menu_page=$wassupmenu[$i][2];
			$menu_name=$wassupmenu[$i][3];
			$menu_class="";
			if($menu_page=="$selected"){
				$menu_class=" current";
			}elseif($menu_page==$wassupfolder && ($selected=="wassup-stats" || $selected=="wassup")){
				$menu_class=" current";
			}elseif($menu_page=="wassup-spia" && $selected=="wassup-spy"){
				$menu_class=" current";
			}
			if(current_user_can($menu_access)){
				//add extra tab for faq next-to options
				if($menu_page=="wassup-options"){
					$menu_class="";
					if($selected == "wassup-faq"){
						$menu_class=" current";
					}
					$menu_name ="FAQ";
					echo "\n";?>
		<li id="faq-link" class="wassup-menu-link<?php echo $menu_class;?>"><a href="<?php echo wassupURI::get_admin_url('admin.php?page='.$menu_page.'&ml=wassup-faq');?>"><?php echo $menu_name;?></a></li><?php
					$menu_class="";
					if($selected =="wassup-options"){
						$menu_class=" current";
					}
					$menu_name="Options";
				}elseif($menu_page=="wassup-online"){
					$menu_name =__("Current Visitors Online","wassup");
				}
				echo "\n";?>
		<li id="options-link" class="wassup-menu-link<?php echo $menu_class;?>"><a href="<?php echo wassupURI::get_admin_url('admin.php?page='.$menu_page);?>"><?php echo $menu_name;?></a></li><?php
			}
		}//end for
		echo "\n";?>
		<li id="donate-link" class="wassup-menu-link"><?php
		$donate_link_url="";
		if(is_multisite() && is_network_admin()){
			$donate_link_url=network_admin_url('admin.php?page=wassup-options&tab=donate');
		}elseif(current_user_can('manage_options')){
			$donate_link_url=admin_url('admin.php?page=wassup-options&tab=donate');
		}
		wassup_donate_link($donate_link_url);?></li><?php
	}else{
		if (($selected=="wassup-stats" || $selected=="wassup") && !empty($_GET['ml'])) $selected=$_GET['ml'];
		echo "\n";?>
		<li id="menu-link-3" class="wassup-menu-link<?php if($selected=='wassup-online') echo ' current';?>"><a href="<?php echo wassupURI::get_admin_url('index.php?page=wassup-stats&ml=wassup-online');?>"><?php _e('Current Visitors Online','wassup');?></a></li>
		<li id="menu-link-2" class="wassup-menu-link<?php if($selected=='wassup-spia' || $selected=='wassup-spy') echo ' current';?>"><a href="<?php echo wassupURI::get_admin_url('index.php?page=wassup-stats&ml=wassup-spia');?>"><?php _e('SPY Visitors','wassup');?></a></li>
		<li id="menu-link-1" class="wassup-menu-link<?php if($selected=='wassup' || $selected==$wassupfolder || $selected=='wassup-stats') echo ' current';?>"><a href="<?php echo wassupURI::get_admin_url('index.php?page=wassup-stats');?>"><?php _e('Visitor Details','wassup');?></a></li><?php
		echo "\n";?>
		<li id="donate-link" class="wassup-menu-link"><?php wassup_donate_link();?></li><?php
	} //end if submenu
	echo "\n";?>
	</ul><div style="clear:right;"></div>
	</div><?php
} //end wassup_menu_links

function wassup_donate_link($link_url=""){
	global $wdebug_mode;
	//display Paypal link/form for donate tab 
	if(!empty($link_url) && strpos($link_url,'//')!==false){
		echo '<a href="'.$link_url.'"><img src="'.WASSUPURL.'/img/donate-button-sm.png" alt="'.__("Donate","wassup").'"/></a>';
	}else{
		echo "\n";?>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_donations">
<input type="hidden" name="business" value="michele@befree.it">
<input type="hidden" name="lc" value="US">
<input type="hidden" name="item_name" value="Wassup Wordpress Plugin">
<input type="hidden" name="no_note" value="0">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_SM.gif:NonHostedGuest">
<input type="image" src="<?php echo WASSUPURL.'/img/donate-button-sm.png';?>" border="0" name="submit" id="submit-donate" alt="DONATE" style="margin:0;padding:1px 3px;vertical-align:center;" align="center"/><?php 
		if(!$wdebug_mode){
			echo "\n";?><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"><?php
		}
		echo "\n";?>
		</form>
		<?php
	}
} //end wassup_donate_link

/**
 * Wassup page manager for displaying Wassup admin pages and forms.
 *  - saves and validate settings changes
 *  - perform manual records delete
 *  - displays admin notices
 *  - displays page header and footer sections
 *  - calls function to display main page content
 */
function WassUp() {
	global $wpdb,$wp_version,$current_user,$wassup_options,$wdebug_mode;
	$wassuppage=wassupURI::get_menu_arg();
	if($wassuppage == "wassup" && isset($_REQUEST['wajax'])){
		return;	//no output for ajax requests
	}
	$starttime=microtime_float();	//start script runtime
	//extend php script timeout..to 3 minutes
	$stimeout=ini_get('max_execution_time');
	if(is_numeric($stimeout) && $stimeout>0 && $stimeout<180){
		$disabled_funcs=ini_get('disable_functions');
		if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
			@set_time_limit(180+1);
		}
	}
	$wassupfolder=basename(WASSUPDIR);
	//load settings for user, site, and network (if needed)
	if(!is_object($current_user) || empty($current_user->ID)) wp_get_current_user();
	$wassup_user_settings = get_user_option('_wassup_settings',$current_user->ID);
	$tab=0;
	if(isset($_GET['tab'])) $tab=esc_attr($_GET['tab']);
	$admin_message="";
	$wassup_table = $wassup_options->wassup_table;
	$network_settings=array();
	$site_settings=array();
	//add a select condition for subsite in multisite
	$multisite_whereis="";
	if(is_multisite()){
		//use table name/optimize setting from network/main site @since v1.9.1
		$network_settings=get_site_option('wassup_network_settings');
		if(!empty($network_settings['wassup_table'])){
			$multisite_whereis=sprintf(" AND `subsite_id`=%d",$GLOBALS['current_blog']->blog_id);
			if(!is_network_admin() && !is_main_site()){
				$site_settings=get_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings');
				$wassup_options->wassup_optimize=$site_settings['wassup_optimize'];
			}
			$wassup_table=$network_settings['wassup_table'];
		}
	}
	$wassup_tmp_table = $wassup_table."_tmp";
	// RUN THE DELETE/SAVE/RESET FORM OPTIONS 
	// Processed here so that any resulting "admin_message" or errors will display with page
	//DELETE NOW options...
	if(!empty($_POST) && ($wassuppage== "wassup-options" || $wassuppage == "wassup" || $wassuppage=="wassup-stats" || $wassuppage=="wassup-donate")){
	if($wassuppage=="wassup-options" || $wassuppage=="wassup-donate"){
	//check user capability and verify admin referer/wp nonce before processing form changes and delete requests @since v1.9
	if(current_user_can('manage_options') && wassupURI::is_valid_admin_referer('wassupsettings-'.$current_user->ID)){
	//workaround code for Google Chrome's empty 'onclick=submit()' "delete NOW" value @since v1.9
	if((isset($_POST['delete_now']) || 
	    isset($_POST['do_delete_manual']) || 
	    isset($_POST['do_delete_auto']) || 
	    isset($_POST['do_delete_recid']) || 
	    isset($_POST['do_delete_empty'])) &&
	   !isset($_POST['submit-options']) && 
	   !isset($_POST['submit-options2']) && 
	   !isset($_POST['submit-options3']) &&
	   !isset($_POST['submit-options4']) &&
	   !isset($_POST['reset-to-default'])){
		$deleted=0;
	if (isset($_POST['do_delete_manual'])){
	if (!empty($_POST['delete_manual']) && $_POST['delete_manual'] !== "never") {
		$delete_filter = ""; 
		$do_delete=false;
		$timenow=current_time("timestamp");
		$to_date=@strtotime($_POST['delete_manual'],$timenow);
		if (is_numeric($to_date) && $to_date < $timenow) {
			if(!empty($_POST['delete_filter_manual'])){
			if($_POST['delete_filter_manual']!="all") {
				$delete_filter=$wassup_options->getFieldOptions("delete_filter","sql",esc_attr($_POST['delete_filter_manual']));
				if(!empty($delete_filter))$do_delete=true;
			}else{
				$do_delete=true;
			}
			}
			$delete_filter.= $multisite_whereis;
			if($do_delete){
				$deleted=$wpdb->query(sprintf("DELETE FROM %s WHERE `timestamp`<'%d' %s",$wassup_table,$to_date,$delete_filter));
			}
			if($wdebug_mode){
				echo "\n<!-- Delete Manual: ";
				echo "delete_filter=\$delete_filter";
				echo "\n -->";
			}
		}
	} //end if delete_manual
	}elseif(isset($_POST['do_delete_auto'])){
	if (!empty($_POST['delete_auto']) && $_POST['delete_auto'] !== "never") {
		$delete_filter = ""; 
		$do_delete=false;
		$wassup_options->delete_auto=esc_attr($_POST['delete_auto']);
		$wassup_options->delete_filter=esc_attr($_POST['delete_filter']);
		if($wassup_options->saveSettings()) $admin_message = __("Wassup options updated successfully","wassup")."." ;
		$timenow=current_time("timestamp");
		$to_date=@strtotime($_POST['delete_auto'],$timenow);
		if (is_numeric($to_date)&& $to_date < $timenow) {
			if(!empty($_POST['delete_filter'])){
			if($_POST['delete_filter']!="all") {
				$delete_filter=$wassup_options->getFieldOptions("delete_filter","sql",esc_attr($_POST['delete_filter']));
				if(!empty($delete_filter))$do_delete=true;
			}else{
				$do_delete=true;
			}
			}
			$delete_filter .= $multisite_whereis;
			if($do_delete){
				$deleted=$wpdb->query(sprintf("DELETE FROM %s WHERE `timestamp`<'%d' %s",$wassup_table,$to_date,$delete_filter));
				//log daily delete time to prevent multiple auto deletes in 1 day
				if($deleted>0){
					$expire=time()+24*3600;
					$cache_id=wassupDb::update_wassupmeta($wassup_table,'_delete_auto',$timestamp,$expire);
				}
			}
			if($wdebug_mode){
				echo "\n<!-- Delete auto: ";
				echo "delete_filter=\$delete_filter";
				echo "\n -->";
			}
		} //end if numeric
	} //end if delete_auto
	}elseif(isset($_POST['do_delete_recid'])){
		//Delete up to specific recid number @since v1.9
		if(!empty($_POST['delete_recid']) && is_numeric($_POST['delete_recid'])){
			$delete_filter=$multisite_whereis;
			$delete_recid=(int)$_POST['delete_recid'];
			if($delete_recid >0){
				$deleted=$wpdb->query(sprintf("DELETE FROM $wassup_table WHERE `id`<=%d %s",$delete_recid,$delete_filter));
			}
		}
	}elseif (!empty($_POST['do_delete_empty'])) {
		$delete_filter=$multisite_whereis;
		if(!empty($delete_filter)){
			$deleted=$wpdb->query(sprintf("DELETE FROM %s WHERE `id`>0 %s",esc_attr($wassup_table),$delete_filter));
		}else{
			$deleted=$wpdb->query(sprintf("DELETE FROM %s",esc_attr($wassup_table)));
		}
	}else{
		$admin_message = __("Nothing to do! Check a \"Delete\" option and try again","wassup");
	}
		//clear table_status cache and reschedule table optimize after bulk delete @since v1.9
		if ($deleted > 0) {
			$admin_message=sprintf(__("%d records DELETED permanently!","wassup"),$deleted);
			$result=wassupDb::delete_wassupmeta("",$wassup_table,'_table_status');
			if($deleted>250 && !empty($wassup_options->wassup_optimize) && !isset($_POST['do_delete_empty'])){
				$last_week=current_time("timestamp")-7*24*3600;
				if($wassup_options->wassup_optimize >$last_week){
					$wassup_options->wassup_optimize=$last_week;
					$wassup_options->saveSettings();
				}
				//reset optimize in main site when plugin is network-activated @since v1.9.1
				if(!empty($network_settings['wassup_table']) && !empty($site_settings['wassup_optimize'])){
					if($site_settings['wassup_optimize'] >$last_week){
						$site_settings['wassup_optimize']=$last_week;
						update_blog_option($GLOBALS['current_site']->blog_id,'wassup_settings',$site_settings);
					}
				}
			}
		}
		if(empty($admin_message))
			$admin_message=__("0 records deleted!","wassup");
		$tab=3;
	} //end if delete_now
	if (!isset($_POST['delete_now'])) {
	if (isset($_POST['submit-options']) || 
	    isset($_POST['submit-options2']) || 
	    isset($_POST['submit-options3'])) {
		//keep copy of original settings before save
		$wassup_settings=get_option('wassup_settings');
		//form input validated and saved in wassupOptions::saveFormChanges() @since v1.9
		$admin_message=$wassup_options->saveFormChanges();
		//after save, stop scheduled wp-cron tasks when wassup_active is changed to "0" and restart if changed to "1" @since v1.9.1
		if(empty($wassup_options->wassup_active)) wassup_cron_terminate();
		elseif(empty($wassup_settings['wassup_active']) && (!is_multisite() || !empty($network_settings['wassup_active']))) wassup_cron_startup();
		if(isset($_POST['submit-options'])) $tab=1;
		if(isset($_POST['submit-options2'])) $tab=2;
		if(isset($_POST['submit-options3'])) $tab=3;
	} elseif (isset($_POST['submit-options4'])) {	//uninstall checkbox
		if (!empty($_POST['wassup_uninstall'])) {
			$wassup_options->wassup_uninstall="1";
			$wassup_options->wassup_active="0"; //disable recording now
			//for uninstall, stop all wassup wp-cron tasks @since v1.9.1
			wassup_cron_terminate();
		} else {
			$wassup_options->wassup_uninstall = "0";
		}
		if ($wassup_options->saveSettings()) {
			$admin_message = __("Wassup uninstall option updated successfully","wassup")."." ;
		}
		$tab=4;
	} elseif (isset($_POST['reset-to-default'])) {
		//for reset-to-default, stop and restart scheduled wassup wp-cron tasks @since v1.9.1
		wassup_cron_terminate(); //stop wp-cron
		$wassup_options->loadDefaults();
		if ($wassup_options->saveSettings()) {
			$admin_message = __("Wassup options reset successfully","wassup")."." ;
			$wassup_user_settings=$wassup_options->resetUserSettings();
			if($wassup_options->is_recording_active()) wassup_cron_startup(); //restart wp-cron
			//New in v1.9.4: reset-to-default updates Wassup's map apikey
			if(empty($wassup_options->wassup_googlemaps_key)){
				$key=$wassup_options->lookup_apikey();
			}
		}
	}
	} //end if !delete_now
	}else{
		$admin_message = __("Sorry! You're not allowed to do that.","wassup");
	} //end if current_user_can
	} //end if wassup_options
	if($wassuppage=="wassup" && isset($_POST['submit-spam'])){
		if(current_user_can('manage_options') && wassupURI::is_valid_admin_referer('wassupspam-'.$current_user->ID,$_GET['page'])){
			$wassup_options->wassup_spamcheck =(!empty($_POST['wassup_spamcheck'])?"1":"0");
			$wassup_options->wassup_spam=(!empty($_POST['wassup_spam'])?"1":"0");
			$wassup_options->wassup_refspam=(!empty($_POST['wassup_refspam'])?"1":"0");
			$wassup_options->wassup_hack=(!empty($_POST['wassup_hack'])?"1":"0");
			$wassup_options->wassup_attack=(!empty($_POST['wassup_attack'])?"1":"0");
			if ($wassup_options->saveSettings()) {
				$admin_message = __("Wassup spam options updated successfully","wassup")."." ;
			}
		}else{
			$admin_message = __("Sorry! You're not allowed to do that.","wassup");
		}
	}
	} //end if _POST
	//deleteMARKED processed here so admin messages will display
	if(($wassuppage == "wassup" || $wassuppage=="wassup-stats") && !empty($_GET['deleteMARKED']) && !empty($_GET['dip'])){
		// DELETE EVERY RECORD MARKED BY IP
		//check user capability and validate wp_nonce before delete marked @since v1.9
		if(current_user_can('manage_options') && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'],'wassupdelete-'.$current_user->ID)){
			$dip=$wassup_options->cleanFormText($_GET['dip']);
			$deleted=0;
			if(!empty($dip) && $dip == $wassup_user_settings['uip']){
				$to_date = current_time("timestamp");
				if(isset($_GET['last']) && is_numeric($_GET['last'])) $wlast=$_GET['last'];
				else $wlast = $wassup_user_settings['detail_time_period'];
				//delete within selected date range
				if($wlast == 0){
					$from_date="0";	//all time
				}else{
					$from_date=$to_date - (int)(($wlast*24)*3600);
					//extend start date to within a rounded time	
					if($wlast < .25) $from_date=((int)($from_date/60))*60;
					elseif($wlast < 7) $from_date=((int)($from_date/300))*300;
					elseif($wlast < 30) $from_date=((int)($from_date/1800))*1800;
					elseif($wlast < 365) $from_date=((int)($from_date/86400))*86400;
					else $from_date=((int)($from_date/604800))*604800;
				}
				$sql=sprintf("DELETE FROM `$wassup_table` WHERE `ip`='%s' AND `timestamp` BETWEEN '%d' AND '%d' %s",$dip,$from_date,$to_date,$multisite_whereis);
				$deleted=$wpdb->query($sql);
				if(!empty($deleted) && is_wp_error($deleted)){
					$errno=$deleted->get_error_code();
					$error_msg=" deleteMARKED error#$errno ".$deleted->get_error_message()."\n SQL=".$sql;
					$deleted=$wpdb->rows_affected+0;
				}
			}
			$admin_message="";
			if(!empty($error_msg) && $wdebug_mode) $admin_message= $error_msg." ";
			$admin_message .= (int)$deleted." ".__('records deleted','wassup');
		}else{
			$admin_message = __("Sorry! You're not allowed to delete records.","wassup");
		} //end if current_user_can
	} //end if deleteMarked
	//add a horizontal menu for easier menu navigation in WP 2.7+
	if (version_compare($wp_version, '2.7', '>=')) { 
		wassup_menu_links($wassuppage);
	}
	//#display an admin message or an alert.
	//..must be above "wassup-wrap" div, but below wassup menus
	if(empty($wassup_options->wassup_alert_message) && empty($wassup_user_settings['ualert_message'])){
		if(empty($admin_message)){
			//display as a system message when not recording
			if(!$wassup_options->is_recording_active()){
				$admin_message=__("WARNING: WassUp is NOT recording new statistics.","wassup");
			if($wassup_options->is_admin_login()){
				if(!is_multisite() || !empty($network_settings['wassup_active'])){
					$admin_message .="  ".__("To collect visitor data you must check \"Enable statistics recording\" in \"WassUp-Options: General Setup\" tab","wassup");
				}elseif(is_network_admin() || is_main_site()){
					$admin_message .="  ".__("To collect visitor data you must check \"Enable Statistics Recording for network\" in \"WassUp-Options: General Setup\" tab","wassup");
				}else{
					$admin_message .="  ".__("Contact your site administrator about enabling statistics recording for the network.","wassup");
				}
			}else{
				if(!is_multisite() || !empty($network_settings['wassup_active'])){
					$admin_message .="  ".__("Contact your site administrator about enabling statistics recording.","wassup");
				}else{
					$admin_message .="  ".__("Contact your site administrator about enabling statistics recording for the network.","wassup");
				}
			}
			} //end if is_recording_active
		}
		if(!empty($admin_message)){
			$wassup_options->wassup_alert_message=$admin_message;
			$wassup_options->saveSettings();
		}
	}
	if(!empty($wassup_options->wassup_alert_message) || !empty($wassup_user_settings['ualert_message'])){
		if(is_network_admin()) do_action('network_admin_notices');
		else do_action('admin_notices');
	}?>
	<div id="wassup-wrap" class="wrap <?php echo $wassuppage;if(version_compare($wp_version,'2.3','<')) echo ' wassup-wp-legacy';?>">
		<div id="icon-plugins" class="icon32 wassup-icon"></div><?php
	// DISPLAY PAGE CONTENT
	if ($wdebug_mode) echo "\n<!--  wassup page=".$wassuppage." -->";
	//separate action to display page contents that can be used by add-on modules @since v1.9
	if(has_action('wassup_page_content')){
		do_action('wassup_page_content',array('wassuppage'=>$wassuppage,'tab'=>$tab));
	}elseif($wassuppage=="wassup" || $wassuppage=="wassup-stats" || $wassuppage==$wassupfolder){?>
		<h2>WassUp - <?php if(isset($_GET['last']) && is_numeric($_GET['last']) && $_GET['last']>0 && $_GET['last']<90) _e("Latest Hits","wassup");else _e("Visitor Details", "wassup");?></h2><?php 
		wassup_page_contents(array('wassuppage'=>$wassuppage,'tab'=>$tab));
	}elseif ($wassuppage == "wassup-online"){?>
		<h2>WassUp - <?php _e("Current Visitors Online", "wassup"); ?></h2><?php
		wassup_page_contents(array('wassuppage'=>$wassuppage,'tab'=>$tab));
	}elseif ($wassuppage == "wassup-spia" || $wassuppage == "wassup-spy"){?>
		<h2>WassUp - <?php _e("SPY Visitors", "wassup"); ?></h2><?php
		wassup_page_contents(array('wassuppage'=>$wassuppage,'tab'=>$tab));
	}elseif ($wassuppage=="wassup-options" || $wassuppage=="wassup-donate"){?>
		<h2>WassUp - <?php _e('Options','wassup'); ?></h2><?php
		if (!function_exists('wassup_optionsView')) include_once(WASSUPDIR.'/lib/settings.php');
		wassup_optionsView($tab);
	}elseif ($wassuppage=="wassup-faq"){ ?>
		<h2>WassUp - <?php _e('Frequently Asked Questions','wassup'); ?></h2><?php
		if (!function_exists('wassup_faq')) include_once(WASSUPDIR.'/lib/faq.php');
		wassup_faq();
	}else{
		return;
	}
	// End calculating execution time of script
	$totaltime=sprintf("%8.8s",(microtime_float() - $starttime));?>
		<p><small><a href="http://www.wpwp.org" title="<?php _e('Donate','wassup');?>" target="_blank"><?php echo __("Donations are really welcome","wassup");?></a>
		<span class="separator">|</span> WassUp ver: <?php echo WASSUPVERSION;?>
		<span class="separator">|</span> <?php echo sprintf(__("Check the %s for updates, bug reports and your hints to improve it","wassup"),'<a href="http://www.wpwp.org" target="_BLANK">'.__("Official WassUp page","wassup").'</a>');?>
		<span class="separator">|</span> <a href="https://wordpress.org/support/plugin/wassup" title="<?php echo __("WassUp Support","wassup");?>"><?php echo __("Wassup Support","wassup");?></a>
		<nobr><span class="separator">|</span> <?php echo __('Exec time','wassup').": $totaltime"; ?></nobr>
		</small></p>
	</div>	<!-- end wassup-wrap --><?php

	//New in v1.9.4: start the refresh timer at end of page render
	if($wassuppage == "wassup"){
		$wrefresh = (int)$wassup_options->wassup_refresh;
		if($wrefresh >0){
			echo "\n";?>
<script type="text/javascript">ActivateCountDown("CountDownPanel",<?php echo ($wrefresh*60);?>);</script><?php
		}
	}
	echo "\n";
} //end WassUp

/**
 * Display the contents of a Wassup admin panel page
 * @param string
 * @return none
 */
function wassup_page_contents($args=array()){
	global $wpdb, $wp_version, $current_user, $wassup_options, $wdebug_mode;
	if(!empty($args) && is_array($args)) extract($args);	
	if ($wdebug_mode) {
		$mode_reset=ini_get('display_errors');
		//don't check for 'strict' php5 standards (part of E_ALL since PHP 5.4)
		if (defined('PHP_VERSION') && version_compare(PHP_VERSION, 5.4, '<')) @error_reporting(E_ALL);
		else @error_reporting(E_ALL ^ E_STRICT); //E_STRICT=php5 only
		@ini_set('display_errors','On');	//debug
		echo "\n<!-- *WassUp DEBUG On-->\n";
		echo "<!-- *normal setting: display_errors=$mode_reset ";
		echo " parameters=";
		if(is_array($args)) print_r($args);
		else echo $args;
		echo "-->\n";
	}
	//load additional wassup modules as needed
	if(!class_exists('WassupItems')){
		require_once(WASSUPDIR.'/lib/main.php');
		include_once(WASSUPDIR.'/lib/uadetector.class.php');
	}
	$stimer_start=time(); //start script timer, to avoid timeout
	//extend php script timeout length for large datasets
	$stimeout=ini_get("max_execution_time");
	$can_set_timelimit=true;
	if(is_numeric($stimeout) && $stimeout>0 && $stimeout <180){
		$disabled_funcs=ini_get('disable_functions');
		if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
			$result=@set_time_limit(180);
			if($result) $stimeout=180;
		}else{
			$can_set_timelimit=false;
		}
	}
	//if unable to read timeout, use 60 sec default (-2 secs)
	if(empty($stimeout) || !is_numeric($stimeout)) $stimeout=58;
	$wpurl=wassupURI::get_wphome();
	$blogurl=wassupURI::get_sitehome();
	$wassup_options->loadSettings();	//needed in case "update_option is run elsewhere in wassup (widget)
	$wassup_table = $wassup_options->wassup_table;
	$wassup_tmp_table = $wassup_table."_tmp";
	//for subsite queries in multisite/network-activated setup
	$multisite_whereis="";
	if($wassup_options->network_activated_plugin()){
		if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)) $multisite_whereis = sprintf(" AND `subsite_id`=%s",$GLOBALS['current_blog']->blog_id);
	}
	//get custom wassup settings for current user
	if(empty($current_user->ID)) wp_get_current_user();
	$wassup_user_settings=get_user_option('_wassup_settings');
	$wnonce=(!empty($wassup_user_settings['unonce'])?$wassup_user_settings['unonce']:'');
	//set ajax query parameters 'action_param' for "action.php"
	$action_param=array('action'=>"wassup_action_handler",'wajax'=>1,'whash'=>$wassup_options->whash);
	//assign url of current wassup page
	$wassupfolder=basename(WASSUPDIR);
	if(empty($wassuppage)) $wassuppage=wassupURI::get_menu_arg();
	if(isset($_GET['ml'])){
		$wassupmenulink='index.php?page=wassup-stats&ml='.$_GET['ml'];
	}elseif($_GET['page']=="wassup-stats"){
		$wassupmenulink='index.php?page=wassup-stats';
	}else{
		$wassupmenulink='admin.php?page='.$_GET['page'];
	}
	$wassuppageurl=wassupURI::get_admin_url($wassupmenulink);
	$expcol='
	<table width="100%" class="toggle"><tbody><tr>
		<td align="left" class="legend"><a href="#" class="toggle-all">'.__('Expand All','wassup').'</a></td>
	</tr></tbody></table>';
	$scrolltop='<div class="scrolltop"><a href="#wassup-wrap" onclick="wScrollTop();return false;">'.__("Top","wassup").'&uarr;</a></div>';
	//some display options
	if($wassup_options->is_USAdate()) $dateformat='m/d/Y';
	else $dateformat='Y/m/d';
	$show_avatars=get_option('show_avatars');
	if(!empty($show_avatars)) $show_avatars=true;
	else $show_avatars=false;
	//for stringShortener calculated values
	if (!empty($wassup_options->wassup_screen_res)){
		$screen_res_size = (int) $wassup_options->wassup_screen_res;
	}else{
		$screen_res_size = 800;
	}
	$max_char_len = (int)($screen_res_size)/($screen_res_size*0.01);
	if((version_compare($wp_version,'3.1','>=') && is_admin_bar_showing()===false) || version_compare($wp_version,'2.7','<')){
		//set larger chart size and screen_res when there is no admin sidebar
		$screen_res_size=$screen_res_size+160;
		$max_char_len=$max_char_len+16;
	}
	//for wassup chart size
	$res = (int)$wassup_options->wassup_screen_res;
	if(empty($res)) $res = $screen_res_size;
	if ($res < 800) $res=620;
	elseif ($res < 1024) $res=740;
	else $res=1000; //1000 is Google api's max chart width 

	// HERE IS THE VISITORS ONLINE VIEW
	if ($wassuppage == "wassup-online") {
		echo "\n";?>
		<p class="legend"><?php echo __("Legend", "wassup").': &nbsp; <span class="box-log">&nbsp;&nbsp;</span> '.__("Logged-in Users", "wassup").' &nbsp; <span class="box-aut">&nbsp;&nbsp;</span> '.__("Comment Authors", "wassup").' &nbsp; <span class="box-spider">&nbsp;&nbsp;</span> '.__("Spiders/bots", "wassup"); ?></p><br />
		<?php
		//use variable timeframes for online counts: spiders-1 min, regular visitors-3 minutes, logged-in users-10 minutes @since v1.9
		$to_date=current_time('timestamp')-3;
		$from_date=$to_date - 10*60;	//-10 minute from timestamp for logged-in user counts
		$whereis=sprintf("`timestamp`>'%d' AND (`username`!='' OR `timestamp`>'%d' OR (`timestamp`>'%d' AND `spider`='')) %s",$from_date,$to_date - 1*60,$to_date - 3*60,$multisite_whereis);
		if($wdebug_mode) echo "\n<!--   Online whereis=$whereis -->";
		$currenttot=0;
		$currentlogged=0;
		$currentauth=0;
		$qryC=false;
		$TotOnline=New WassupItems($wassup_tmp_table,"","",$whereis);
		if(!empty($TotOnline->totrecords))
			$currenttot = $TotOnline->calc_tot("count",null,null,"DISTINCT");
		if ($currenttot > 0) {
			$currentlogged = $TotOnline->calc_tot("count",null,"AND `username`!=''","DISTINCT");
			$currentauth = $TotOnline->calc_tot("count",null,"AND `comment_author`!='' AND `username`=''","DISTINCT");
			$sql=sprintf("SELECT SQL_NO_CACHE `id`, wassup_id, count(wassup_id) as numurl, max(`timestamp`) as max_timestamp, `ip`, `hostname`, `searchengine`, `search`, `searchpage`, `urlrequested`, `referrer`, `agent`, `browser`, `spider`, `feed`, `os`, `screen_res`, GROUP_CONCAT(DISTINCT `username` ORDER BY `username` SEPARATOR '| ') AS login_name, `comment_author`, `language`, `spam` AS malware_type, `url_wpid` FROM $wassup_tmp_table WHERE %s GROUP BY `wassup_id` ORDER BY max_timestamp DESC",$whereis);
			$qryC=$wpdb->get_results($sql);
			if(!empty($qryC) && is_wp_error($qryC)){
				$errno=$qryC->get_error_code();
				$error_msg=" qryC error#$errno ".$qryC->get_error_message()."\n whereis=".esc_attr($whereis)."\n SQL=".esc_attr($sql);
				$qryC=false;
			}
		}
		//show online summary counts @since v1.9
	?><div class="centered"><div id="usage">
		<ul>
		<li><?php echo "<span>".(int)$currenttot."</span> ".__('Visitors online','wassup');?></li>
		<li><?php echo "<span>".(int)$currentlogged."</span> ".__('Logged-in Users','wassup');?></li>
		<li><?php echo "<span>".(int)$currentauth."</span> ".__('Comment authors','wassup');?></li>
		</ul>
	</div></div><?php
		if(!empty($qryC) && is_array($qryC)){
			echo "\n";?>
	<div id="onlineContainer" class="main-tabs"><?php
			print $expcol;
		foreach($qryC as $cv){
			if($wassup_options->wassup_time_format == 24){
				$timed=gmdate("H:i:s", $cv->max_timestamp);
			}else{
				$timed=gmdate("h:i:s a",$cv->max_timestamp);
			}
			$referrer="";
			$ip=wassup_clientIP($cv->ip);
			if(empty($ip))$ip=__("unknown","wassup");
			if($cv->referrer != '' && stristr($cv->referrer,$wpurl)!=$cv->referrer){
				if($cv->searchengine == ""){
					$referrer=wassupURI::referrer_link($cv,$max_char_len);
				}else{
					$referrer=wassupURI::se_link($cv,$max_char_len);
				}
			} else { 
				if(empty($cv->referrer) || $cv->referrer== $wpurl.$cv->urlrequested){
					$referrer=__("Direct hit", "wassup"); 
				}else{
					$referrer=__("From your site", "wassup"); 
				}
			} 
			$numurl=$cv->numurl;
			$Ousername="";
			$ulclass="users";
			$unclass="";
			$logged_user="";
			// User is logged in or is a comment's author
			if($cv->login_name != "" || $cv->comment_author !=""){
				$utype="";
				$logged_user=trim($cv->login_name,'| ');
				if($logged_user != ""){
					if(strpos($logged_user,'|')!==false){
						$loginnames=explode('|',$logged_user);
						foreach($loginnames AS $name){
							$logged_user=trim($name);
							if(!empty($logged_user)) break;
						}
					}
					$utype=__("LOGGED IN USER","wassup");
					$ulclass = "userslogged";
					$udata=false;
					if(!empty($logged_user)) $udata=get_user_by("login",esc_attr($logged_user));
					if($udata!==false && $wassup_options->is_admin_login($udata)){
						$utype=__("ADMINISTRATOR","wassup");
						$ulclass .=" adminlogged";
					}
					if(!empty($udata->ID)){
						if($show_avatars){
							$Ousername='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.get_avatar($udata->ID,'20').' '.esc_attr($logged_user).'</strong></span></li>';
						}else{
							$Ousername='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.esc_attr($logged_user).'</strong></span></li>';
						}
					}else{
						$Ousername='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.esc_attr($logged_user).'</strong></span></li>';
					}
					$unclass="sum-box-log";
				}
				if($cv->comment_author != ""){
					$Ousername .='<li class="users"><span class="indent-li-agent">'.__("COMMENT AUTHOR","wassup").': <strong>'.esc_attr($cv->comment_author).'</strong></span></li>';
					$ulclass = "users";
					if(empty($unclass)) $unclass="sum-box-aut";
				}
			}
			if(!empty($cv->spider)) $unclass="sum-box-spider";
			if(!empty($cv->malware_type)) $unclass="sum-box-spam";
			if(strlen($ip)>20) $unclass .=" sum-box-ipv6";
			echo "\n";?>
		<div class="sum-rec"><?php
		// Visitor Record - raw data (hidden)
		$raw_div="raw-".substr($cv->wassup_id,0,25).rand(0,99);
		echo "\n";?>
		<div id="<?php echo $raw_div;?>" style="display:none;"><?php
			$args=array('numurl'=>$numurl,'rk'=>$cv);
			wassup_rawdataView($args);?>
		</div>
		<div class="sum-nav">
			<div class="sum-box">
				<span class="sum-box-ip <?php echo $unclass;?>"><?php if($numurl >1){ ?><a href="#" class="showhide" id="<?php echo (int)$cv->id;?>"><?php echo esc_attr($ip);?></a><?php }else{ echo esc_attr($ip);}?></span>
			</div>
			<div class="sum-det">
				<p class="delbut"><a href="#TB_inline?height=400&width=<?php echo $res.'&inlineId='.$raw_div;?>" class="thickbox"><img class="table-icon" src="<?php echo WASSUPURL.'/img/b_select.png" alt="'.__('show raw table','wassup').'" title="'.__('Show the items as raw table','wassup');?>" /></a></p>
				<span class="det1"> <?php echo wassupURI::url_link($cv->urlrequested,$max_char_len,$cv->malware_type);?> </span>
				<span class="det2"><strong><?php echo $timed;?> - </strong><?php echo $referrer;?></span>
			</div>
		</div>
		<div class="detail-data"><?php
		if(!empty($Ousername)){
			echo "\n";?>
		<ul class="<?php print $ulclass; ?>">
			<?php print $Ousername; ?>
		</ul>
<?php
		}
		if($numurl >1){ ?>
			<div style="display: none;" class="togglenavi navi<?php echo (int)$cv->id ?>">
				<ul class="url"><?php 
			$sql=sprintf("SELECT SQL_NO_CACHE `timestamp`, `urlrequested`, `spam` FROM `$wassup_tmp_table` WHERE `wassup_id`='%s' AND `timestamp`>'%d' %s ORDER BY `timestamp`",$cv->wassup_id,$from_date,$multisite_whereis);
			$qryCD=$wpdb->get_results($sql);
			if(!empty($qryCD) && is_wp_error($qryCD)){
				$errno=$qryCD->get_error_code();
				$error_msg=" qryCD error#$errno ".$qryCD->get_error_message()."\n SQL=$sql";
				$qryCD=false;
			}
			$i=1;
			if(!empty($qryCD) && is_array($qryCD)){
			foreach ($qryCD as $cd) {
				if ($wassup_options->wassup_time_format == 24){
					$time2 = '<span class="time">'.gmdate("H:i:s", $cd->timestamp).' </span>';
				}else{
					$time2 = '<span class="time">'.gmdate("h:i:s a", $cd->timestamp).'</span>';
				}
				$num = ($i&1);
				if ($num == 0) $classodd = "urlodd";
				else  $classodd = "url";
				echo "\n";?>
			<li class="<?php echo $classodd.' navi'.(int)$cv->id;?> wassup-nowrap"><span class="request-time"><?php echo $time2.' &rarr; ';?></span><span class="request-uri"><?php echo wassupURI::url_link($cd->urlrequested,$max_char_len,$cv->malware_type);?></span></li><?php
				$i++;
			} //end foreach qryCD
			} //end if qryCD
			echo "\n";?>
			</ul>
		</div>
<?php		} //end if numurl
		echo "\n";?>
		</div><!-- /detail-data -->
		</div><!-- /sum-rec --><?php
		} //end foreach qryC
		echo $expcol;
		} //end if currenttot
		echo "\n";?>
	</div><!-- /main-tabs -->
	<?php if(!empty($witemstot) && $witemstot >=10) echo $scrolltop;?>
<?php
	// HERE IS THE SPY MODE VIEW
	} elseif ($wassuppage=="wassup-spy" || $wassuppage=="wassup-spia"){
		//parameter to filter spy by visitor type
		if (isset($_GET['spiatype'])) {
			$spytype = $wassup_options->cleanFormText($_GET['spiatype']);
			$wassup_user_settings['spy_filter']=$spytype;
			update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
		}elseif(!empty($wassup_user_settings['spy_filter'])){
			$spytype=$wassup_user_settings['spy_filter'];
		}elseif(!empty($wassup_options->wassup_default_spy_type)){
			$spytype=$wassup_options->wassup_default_spy_type;
		}else{
			$spytype=$wassup_options->wassup_default_type;
		}
		echo "\n";?>
	<p class="legend" style="padding:2px 0 0 5px; margin:0;"><?php echo __("Legend", "wassup").': &nbsp; <span class="box-log">&nbsp;&nbsp;</span> '.__("Logged-in Users", "wassup").' &nbsp; <span class="box-aut">&nbsp;&nbsp;</span> '.__("Comments Authors", "wassup").' &nbsp; <span class="box-spider">&nbsp;&nbsp;</span> '.__("Spiders/bots", "wassup"); ?></p>
	<form id="spy-opts-form">
	<table class="legend"><tbody>
	<tr><td align="left" width="150">
		<span id="spy-pause"><a href="#?" onclick="return pauseSpy();"><?php _e("Pause", "wassup"); ?></a></span>
		<span id="spy-play"><a href="#?" onclick="return playSpy();"><?php _e("Play", "wassup"); ?></a></span>
	</td><td align="right" width="105"><?php
		if(!empty($_GET['map'])){
			$wassup_user_settings['spy_map']=1;
			update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
		}elseif(isset($_GET['map'])){
			$wassup_user_settings['spy_map']=0;
			update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
		}
		if(empty($wassup_user_settings['spy_map'])){
			echo "\n";?>
			<span style="text-align:right"><a href="<?php echo $wassuppageurl.'&map=1';?>" class="icon"><img src="<?php echo WASSUPURL.'/img/map_add.png" alt="'.__('Show map','wassup').'" title="'.__('Show ip geo location on map','wassup'); ?>"/></a> <a href="<?php echo $wassuppageurl.'&map=1';?>"><?php _e("Show map","wassup");?></a></span> <span class="separator">|</span><?php
		}
		//filter by type of visitor (wassup_default_spy_type)
		$selected=$spytype;
		$optionargs=$wassuppageurl.'&spiatype=';
		echo "\n";?>
		<span class="spy-opt-right"><?php _e('Spy items by','wassup'); ?>: 
		<select name="navi" onChange="wassupReload<?php echo $wnonce;?>(this.options[this.selectedIndex].value);"><?php
		$wassup_options->showFieldOptions("wassup_default_spy_type","$selected","$optionargs");?>
		</select> &nbsp;</span>
	</td></tr>
	</tbody></table>
	</form><?php
		//set map's initial center from Wordpress' timezone location @since v1.9
		if(!empty($wassup_user_settings['spy_map'])){
			//get the initial center position for map 
			$tz_name=get_option('timezone_string');
			if(!empty($tz_name)){
			if(stristr($tz_name,'America/')!==false) $pos="37,-97";
			elseif(stristr($tz_name,'Africa/')!==false) $pos="0,0";
			elseif(stristr($tz_name,'Asia/')!==false) $pos="31,121";
			elseif(stristr($tz_name,'Australia/')!==false) $pos="-27.4,153";
			elseif(stristr($tz_name,'Europe/')!==false) $pos="45.5,9.4";
			elseif(stristr($tz_name,'Indian/')!==false) $pos="28.6,77";
			elseif(stristr($tz_name,'Pacific/')!==false) $pos="21,-158";
			}
			//...or set default center position to either USA or Europe, depending on Wordpress "date" format
			if(empty($pos)){
				$pos="37,-97"; //center is USA
				//center is Europe
				if(!$wassup_options->is_USAdate()) $pos="45.5,9.4";
			}
			echo "\n";?>
	<div id="map_placeholder" class="placeholder">
		<div id="spia_map" style="width:90%;height:370px;"></div>
	</div>
	<?php
			echo '<script type="text/javascript">wassupMapinit(\'spia_map\','.$pos.');</script>';
		} //end if spy_map
		echo "\n";?>
	<div id="spyContainer"><?php
		//display last few hits here
		$to_date=current_time('timestamp');
		$from_date=($to_date - 24*(60*60)); //display last 10 visits in 24 hours...
		wassup_spiaView($from_date,0,$spytype,$wassup_table); ?>
	</div><!-- /spyContainer -->
	<?php echo $scrolltop;?>
	<br />
<?php
	// HERE IS THE MAIN/DETAILS VIEW
	}elseif ($wassuppage=="wassup" || $wassuppage==$wassupfolder || $wassuppage=="wassup-stats"){
		if(!$wassup_options->is_recording_active()){
			if(!is_multisite() || !empty($network_settings['wassup_active'])){?>
			<p style="color:red;font-weight:bold;"><?php _e("WassUp recording is disabled", "wassup");?></p><?php
			}else{?>
			<p style="color:red;font-weight:bold;"><?php _e("WassUp recording is disabled for network.", "wassup");?></p><?php
			}
		}
		$remove_it=array(); //for GET param cleanup
		$stickyFilters=""; //filters that remain in effect after page reloads
		$timenow=current_time('timestamp');
		//## GET parameters that can change user settings
		if (isset($_GET['chart'])) { // [0|1] only
			if ($_GET['chart'] == 0) {
				$wassup_user_settings['detail_chart']=0;
			} elseif ($_GET['chart'] == 1) {
				$wassup_user_settings['detail_chart']=1;
			}
			$remove_it[]='chart';
		}
		//## GET params that filter detail display
		//
		//# Filter detail list by IP address
		//Get the current marked IP, if set
		$wip="";
		$dip="";
		if (isset($_GET['mark'])) { // [0|1] only
			if ($_GET['mark'] == 0) {
				$wassup_user_settings['umark']="0";
				$wassup_user_settings['uip'] = "";
				$remove_it[]='wip';
				$wip="";
			}elseif (isset($_GET['wip'])){
				$wassup_user_settings['umark'] = "1";
				$wip=$wassup_options->cleanFormText($_GET['wip']);
				$wassup_user_settings['uip']=$wip;
			}
			$remove_it[]='mark';
		}elseif (isset($_GET['wip'])){
			$wassup_user_settings['umark'] = "1";
			$wip=$wassup_options->cleanFormText($_GET['wip']);
		}elseif(!empty($wassup_user_settings['umark'])){
			//clear wmark setting when 'mark' and 'wip' are not on query string (visitor detail)
			$wassup_user_settings['umark']="0";
			$wassup_user_settings['uip'] = "";
		}
		//# Filter detail list by date range...
		$to_date = current_time("timestamp");	//wordpress time function
		if (isset($_GET['last']) && is_numeric($_GET['last'])) { 
			$wlast = $_GET['last'];
		} else {
			$wlast = $wassup_user_settings['detail_time_period']; 
		}
		if ($wlast == 0) {
			$from_date = "0";	//all time
		} else {
			$from_date = $to_date - (int)(($wlast*24)*3600);
			//extend start date to within a rounded time	
			if ($wlast < .25) { 	//start on 1 minute
				$from_date = ((int)($from_date/60))*60;
			} elseif ($wlast < 7) {
				$from_date = ((int)($from_date/300))*300;
			} elseif ($wlast < 30) {
				$from_date = ((int)($from_date/1800))*1800;
			} elseif ($wlast < 365) {
				$from_date = ((int)($from_date/86400))*86400;
			} else {
				$from_date = ((int)($from_date/604800))*604800;
			}
		}
		//# Filter detail lists by visitor type...
		if (isset($_GET['type'])) {
			$wtype = $wassup_options->cleanFormText($_GET['type']);
		} else {
			$wtype = $wassup_user_settings['detail_filter'];
		}
		//Show a specific page and number of items per page...
		$witems = (int)$wassup_user_settings['detail_limit'];
		if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
			$witems = (int)$_GET['limit']; 
			if ($witems >0 && $witems != (int)$wassup_user_settings['detail_limit']) $wassup_user_settings['detail_limit']=$witems;
		}
		if ((int)$witems < 1 ) { $witems = 10; }
		// current page and items per page as limit
		if (isset($_GET['pp']) && is_numeric($_GET['pp'])) {
			$wpages = (int)$_GET['pp'];
		} else {
			$wpages = 1;
		}
		if ( $wpages > 1 ) {
			$wlimit = " LIMIT ".(($wpages-1)*$witems).",$witems";
		} else {
			$wlimit = " LIMIT $witems";
		}
		// Filter detail lists by a searched item
		if(!empty($_GET['search'])){
			$wsearch=$wassup_options->cleanFormText($_GET['search']);
		}else{
			$wsearch="";
			//remove blank search parameter
			if(isset($_GET['search'])) $remove_it[]='search';
		}
		if(isset($_GET['submit-search'])) $remove_it[]='search-submit';
		//for clean up of deleted info from query string
		if (isset($_GET['deleteMARKED'])) {
			$remove_it[]='deleteMARKED';
			$remove_it[]='dip';
			if(isset($_GET['dip'])) $dip=$wassup_options->cleanFormText($_GET['dip']);
			if(!empty($dip)){
				if($dip == $wip){
					$remove_it[]='wip';
					$wip="";
				}
				if($dip == $wsearch){
					$remove_it[]='search';
					$wsearch="";
				}
			}
		}elseif(isset($_GET['dip'])){
			$remove_it[]='dip';
		}
		//sticky filters for query string
		if(!empty($wip)) $stickyFilters .='&wip='.$wip;
		if(isset($wlast)) $stickyFilters .='&last='.$wlast;
		if(!empty($wtype)) $stickyFilters .='&type='.$wtype;
		if(!empty($wsearch)) $stickyFilters .='&search='.$wsearch;
		//set wwhereis clause as parameter for 'wassupItems' and all calculations @since v1.9
		$wwhereis=$multisite_whereis;
		if(!empty($wtype) && $wtype != 'everything'){
			$wwhereis .=$wassup_options->getFieldOptions("wassup_default_type","sql",$wtype);
		}
		//add ip to wwhereis clause when user selects "filter by IP" option
		if(!empty($wip) && $wip == $wsearch && empty($_GET['deleteMARKED'])){
			$wwhereis .=" AND `ip`='$wip'";
		}
		update_user_option($current_user->ID,'_wassup_settings',$wassup_user_settings);
		
		//Clear non-sticky filter parameters from URL before applying new filters 
		$URLQuery=trim(html_entity_decode($_SERVER['QUERY_STRING']));
		//'remove_query_arg' function replaces "preg_replace" to remove args from query string @since v1.9.1
		if(!empty($remove_it)){
			$newURL=remove_query_arg($remove_it,$_SERVER['REQUEST_URI']);
			if(!empty($newURL) && $newURL !=$_SERVER['REQUEST_URI'] && preg_match('/[^\?]+\?([A-Za-z\-_]+.*)/',$newURL,$pcs)>0){
				$URLQuery=$pcs[1];
			}
		}elseif(empty($URLQuery) && preg_match('/[^\?]+\?([A-Za-z\-_]+.*)/',html_entity_decode($_SERVER['REQUEST_URI']),$pcs)>0){
			$URLQuery=$pcs[1];
		}
		?>
	<form id="detail-opts-form">
		<table class="legend"><tbody>
		<tr><td align="left"> &nbsp; </td><td class="legend" align="left"><?php
		//selectable filter by date range
		$selected=$wlast;
		$new_last=preg_replace(array('/&last=[^&]+/','/&pp=[^&]+/'),'',$URLQuery);
		_e('Show details from the last','wassup');?>:
		<select name="last" onChange="wassupReload<?php echo $wnonce;?>(this.options[this.selectedIndex].value);"><?php 
		$optionargs=esc_attr("?".$new_last."&last=");
		$wassup_options->showFieldOptions("wassup_time_period","$selected","$optionargs");
		echo "\n";?>
		</select><?php
		if($wdebug_mode){
			echo "\n<!-- \$new_last=$new_last   \$optionargs=$optionargs -->\n";
		}?></td>
		<td class="legend" align="right"><?php _e('Items per page','wassup'); ?>: <select name="navi" onChange="wassupReload<?php echo $wnonce;?>(this.options[this.selectedIndex].value);"><?php
		//selectable filter by number of items on page
		$selected=$witems;
		$new_limit = preg_replace(array('/&pp=[^&]+/','/&limit=[^&]+/'),'',$URLQuery);
		$optionargs=esc_attr("?".$new_limit."&limit=");
		$wassup_options->showFieldOptions("wassup_default_limit","$selected","$optionargs");
		echo "\n";?>
		</select><span class="separator">|</span>
		<?php
		//selectable filter by type of visitor
		_e('Filter items for','wassup');?>: <select name="type" onChange="wassupReload<?php echo $wnonce;?>(this.options[this.selectedIndex].value);"> <?php
		$selected=$wtype;
		$new_type=preg_replace(array('/&pp=[^&]+/','/&type=[^&]+/'),"",$URLQuery);
		$optionargs=esc_attr("?".$new_type."&type=");
		$wassup_options->showFieldOptions("wassup_default_type","$selected","$optionargs");
		echo "\n";?>
		</select>
		</td></tr>
		</tbody></table>
	</form><?php
		// Instantiate class to count items
		$wTot = New WassupItems($wassup_table,$from_date,$to_date,$wwhereis,$wlimit);
		$wTot->WpUrl=$wpurl;
		$witemstot=0;
		$wpagestot=0;
		$wspamtot=0;
		$markedtot=0;
		$searchtot=0;
		$ipsearch="";
		//don't apply "search" for marked ip (in whereis)
		if(!empty($wsearch) && $wsearch==$wip){
			$ipsearch=$wsearch;
			$wsearch="";
		}
		//to prevent browser timeouts, send <!--heartbeat-->
		echo "\n<!--heartbeat-->";
		// MAIN QUERY
		if(!empty($wTot->totrecords)){
			$witemstot=$wTot->calc_tot("count",$wsearch,null,"DISTINCT");
			echo "\n<!--heartbeat-->";
			if(!empty($wsearch))$wpagestot=$wTot->calc_tot("count",$wsearch);
			else $wpagestot=(int)$wTot->totrecords;
			echo "\n<!--heartbeat-->";
			$wspamtot=$wTot->calc_tot("count",$wsearch,"AND `spam`>'0'");
			// Check if some records were marked
			if (!empty($wip)){
				if (empty($ipsearch)){
					echo "\n<!--heartbeat-->";
					$markedtot=$wTot->calc_tot("count",$wsearch," AND `ip`='".$wip."'","DISTINCT");
				}else{
					//avoid redundant calculations when search and mark/wip are the same
					$markedtot=$witemstot;
				}
			}
			// Check if some records were searched
			if(!empty($wsearch)) {
				//searchtot is the same query as witemstot above and shouldn't be re-calculated (visitor detail fix)
				//$searchtot=$wTot->calc_tot("count",$wsearch,null,"DISTINCT");
				$searchtot=$witemstot;
			}elseif(!empty($ipsearch)){
				$searchtot=$markedtot;
			}
		}
		if(!empty($ipsearch)) $wsearch=$ipsearch;
		// Print Site Usage summary
		echo "\n";?>
	<div class='centered'>
		<div id='usage'>
			<ul><li><span style="border-bottom:2px solid #0077CC;"><?php echo (int)$witemstot;?></span> <?php _e('Visits','wassup');?></li>
			<li><span style="border-bottom:2px dashed #FF6D06;"><?php echo (int)$wpagestot;?></span> <?php _e('Pageviews','wassup');?></li>
			<li><span><?php echo @number_format(($wpagestot/$witemstot),2);?></span> <?php _e('Pages/Visits','wassup');?></li>
			<li><span class="spamtoggle"><nobr><?php
		//add spam form overlay when spamcheck is enabled and user is admin or can 'manage_options'
		$hidden_spam_form=false;
		if($wassup_options->wassup_spamcheck == 1 && ($wassup_options->is_admin_login() || current_user_can('manage_options'))){
			$hidden_spam_form=true;
		}
		if($hidden_spam_form) echo '<a href="#TB_inline?width=400&inlineId=hiddenspam" class="thickbox">';
		echo $wspamtot.'<span class="plaintext">(';
		if(!empty($wspamtot)){
			echo @number_format(($wspamtot*100/$wpagestot),1);
		}else{
			echo "0";
		}
		echo '%)</span>';
		if($hidden_spam_form) echo '</a>';
		echo '</span> '.__('Spams','wassup');?></nobr></li>
			</ul><br/>
			<div id="chart_placeholder" class="placeholder" align="center"></div>
		</div>
	</div><?php
		$checked='checked="CHECKED"';
		// hidden spam options
		if($hidden_spam_form){
			echo "\n";?>
	<div id="hiddenspam" style="display:none;">
		<h2><?php _e('Spam/Malware Options','wassup'); ?></h2>
		<form id="hiddenspam-form" action="" method="post">
		<?php
		//wp_nonce field in hidden spam form  @since v1.9
		wp_nonce_field('wassupspam-'.$current_user->ID);
		echo "\n";?>
		<p><input type="checkbox" name="wassup_spamcheck" value="1" <?php if($wassup_options->wassup_spamcheck==1) echo $checked;?>/> <strong><?php _e('Enable Spam and Malware Check on Records','wassup');?></strong></p>
		<p class="indent-opt"><input type="checkbox" name="wassup_spam" value="1" <?php if($wassup_options->wassup_spam==1) echo $checked;?>/> <?php _e('Record Akismet comment spam attempts','wassup');?></p>
		<p class="indent-opt"><input type="checkbox" name="wassup_refspam" value="1" <?php if($wassup_options->wassup_refspam==1) echo $checked;?>/> <?php _e('Record referrer spam attempts','wassup');?></p>
		<p class="indent-opt"><input type="checkbox" name="wassup_attack" value="1" <?php if($wassup_options->wassup_attack==1) echo $checked;?>/> <?php _e("Record attack/exploit attempts (libwww-perl agent)","wassup");?></p>
		<p class="indent-opt"><input type="checkbox" name="wassup_hack" value="1" <?php if($wassup_options->wassup_hack==1) echo $checked;?>/> <?php _e("Record admin break-in/hacker attempts","wassup");?></p>
		<p><input type="submit" name="submit-spam" class="button" value="<?php _e('Save Settings','wassup'); ?>" /></p>
		</form>
	</div> <!-- /hiddenspam --><?php
		}
		echo "\n";?>
	<table class="legend"><tbody><tr>
	<td align="left" width="28">
		<a href="#" onclick='wSelfRefresh();'><img src="<?php echo WASSUPURL; ?>/img/reload.png" id="refresh" class="icon" alt="<?php echo __('refresh screen','wassup').'" title="'.__('refresh screen','wassup');?>" /></a></td>
	<td class="legend" align="left"><?php 
		echo sprintf(__('Auto refresh in %s seconds','wassup'),'<span id="CountDownPanel">---</span>');?></td>
	<td align="right" class="legend"><?php
		echo "\n";
		//chart options
		if($wassup_user_settings['detail_chart'] == "1"){?>
		<a href="?<?php echo esc_attr($URLQuery.'&chart=0');?>" class="icon"><img src="<?php echo WASSUPURL.'/img/chart_delete.png" class="icon" alt="'.__('hide chart','wassup').'" title="'.__('Hide the chart','wassup');?>"/></a><a href="?<?php echo esc_attr($URLQuery.'&chart=0');?>"><?php _e("Hide chart","wassup");?></a><?php 
		}else{?>
		<a href="?<?php echo esc_attr($URLQuery.'&chart=1');?>" class="icon"><img src="<?php echo WASSUPURL.'/img/chart_add.png" alt="'.__('show chart','wassup').'" title="'.__('Show the chart','wassup'); ?>"/></a><a href="?<?php echo esc_attr($URLQuery.'&chart=1');?>"><?php _e("Show chart","wassup");?></a><?php
		}?> <span class="separator">|</span>
		<?php
		//Top Stats window/popup params
		//v1.9.4 bugfix: topstats from_date cannot be "0"
		if($from_date==0 && $wlast==0){
			$from_date=$wpdb->get_var(sprintf("SELECT MIN(`timestamp`) FROM `$wassup_table` WHERE `timestamp` < '%d'",$to_date));
		}
		//for date range shown in topstats report
		$wdformat = get_option("date_format");
		if(($to_date - $from_date)>24*3600){
			$stats_range=gmdate("$wdformat",$from_date)." - ".gmdate("$wdformat",$to_date);
		}else{
			$stats_range=gmdate("$wdformat H:00",$from_date)." - ".gmdate("$wdformat H:00",$to_date);
		}
		$ajaxurl=wassupURI::get_ajax_url("Topstats");
		$statsurl=add_query_arg(array_merge(array('type'=>"Topstats",'from_date'=>$from_date,'to_date'=>$to_date),$action_param),$ajaxurl);
		?> <a id="topstats_win" href="<?php echo wassupURI::cleanURL($statsurl.'&KeepThis=true&height=400&width='.($res+250)).'" class="thickbox" title="'.sprintf(__('Top Stats for %s','wassup'),$stats_range);?>"><?php _e('Show top stats','wassup');?></a> <?php
		//top stats popup window selection @since v1.9
		?><a id="topstats_popup" class="icon" onclick="window.open('<?php echo wassupURI::cleanURL($statsurl).'&popup=1\',\'topstats-popup\',\'height=400,width='.($res+250).',left=100,top=50,status=1,scrollbars=1,location=0,toolbar=0,statusbar=0,menubar=0';?>');return false;" href="#" title="<?php echo sprintf(__('Top stats for %s in popup','wassup'),$stats_range);?>"><img src="<?php echo WASSUPURL;?>/img/popup.png" alt="" title="Top Stats in popup window" /></a> <span class="separator">|</span> 
		<a href="#" class='show-search'><?php 
		if(!empty($wsearch)) _e('Hide Search','wassup'); 
		else _e('Search','wassup');?></a>
	</td></tr>
	<tr><td align="left" class="legend" colspan="2"><?php
		//Searched items
		if (!empty($wsearch)) { 
			echo sprintf(__('%s matches found for search','wassup'),'<strong>'.(int)$searchtot.'</strong>').": <strong>$wsearch</strong><br/>";
		}
		// Marked items
		if($wassup_user_settings['umark']==1){
			echo sprintf(__("%s items marked for IP","wassup"),'<strong>'.(int)$markedtot.'</strong>').": <strong>$wip</strong>";
			if(empty($wsearch)){?> <span class="separator">|</span> <a href="?<?php echo wassupURI::cleanURL(preg_replace('/&pp=[^&]+/','',$URLQuery)."&search=".$wip).'" title="'.__("Filter by marked IP","wassup");?>"><?php _e("Filter by marked IP","wassup");?></a><?php }
		}
		//Search form
		?></td>
	<td align="right" class="legend">
		<div class="search-ip" <?php if (empty($wsearch)) echo 'style="display: none;"'; ?>>
		<form id="wassup-ip-search" class="wassup-search" action="" method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page'];?>"/><?php
		if(isset($_GET['ml'])){	//'ml' query param is hidden input field @since v1.9.1
			echo "\n";?>
		<input type="hidden" name="ml" value="<?php echo $_GET['ml'];?>"/><?php
		}
		if (!empty($stickyFilters)) {
			$wfilterargs=wGetQueryVars(preg_replace(array('/&type=[^&]+/','/&wip=[^&]+/'),"",$stickyFilters));
			if (!empty($wfilterargs) && is_array($wfilterargs)) {
				foreach($wfilterargs AS $fkey=>$fval){
					echo "\n"; ?>
		<input type="hidden" name="<?php echo $fkey.'" value="'.$fval; ?>" /><?php
				}
			}
		}
		echo "\n"; ?>
		<input type="text" size="25" name="search" value="<?php echo esc_attr($wsearch);?>"/><input type="submit" name="submit-search" value="<?php echo __('Search');?>" class="button button-secondary wassup-button"/>
		</form>
		</div> <!-- /search-ip -->
	</td></tr>
	</tbody></table>
	<div id="detailContainer" class="main-tabs"><?php
		$expcol = '
	<table width="100%" class="toggle"><tbody><tr>
		<td align="left" class="legend"><a href="#" class="toggle-all">'.__('Expand All','wassup').'</a></td>
		<td align="right" class="legend"><a href="#" class="toggle-allcrono">'.__('Collapse Chronology','wassup').'</a></td>
	</tr></tbody></table>';
		echo $expcol;
	//show  page breakdown
	//paginate only when total records > items per page
	if($witemstot > $witems){
		$p=new wassup_pagination();
		$p->items($witemstot);
		$p->limit($witems);
		$p->currentPage($wpages);
		$p->target($wassuppageurl.$stickyFilters);
		echo "<!--heartbeat-->\n";
		$p->calculate();
		$p->adjacents(5);
		echo "\n";?>
		<div id="pag" align="center"><?php $p->show();?></div><?php
 	}
	//# Detailed List of Wassup Records...
	$wmain=$wTot->calc_tot("main",$wsearch);
	echo "\n<!--heartbeat-->";
	$error_msg="";
	$data_error="";
	if($witemstot>0 && is_array($wmain) && count($wmain)>0){
		$rkcount=0;
	foreach($wmain as $rk){
		//monitor for script timeout limit and extend, if needed @since v1.9
		$time_passed=time() - $stimer_start;
		if($time_passed > ($stimeout-10)){
			if($rkcount>0){
				//report is hung, so terminate here
				$data_error=__("Records display interrupted.","wassup")." - script timeout/partial data.";
			}else{	//no data, database problem
				$data_error=__("Unable to display records.","wassup")." - script timeout/no data.";
			}
			break;
		}
		$rkcount++;
		$dateF = gmdate("d M Y", $rk->max_timestamp);
		if ($wassup_options->wassup_time_format == 24) {
			$datetimeF = gmdate('Y-m-d H:i:s', $rk->max_timestamp);
			$timeF = gmdate("H:i:s", $rk->max_timestamp);
		} else {
			$datetimeF = gmdate('Y-m-d h:i:s a', $rk->max_timestamp);
			$timeF = gmdate("h:i:s a", $rk->max_timestamp);
		}
		$ip=wassup_clientIP($rk->ip);
		if ($rk->hostname != "" && $rk->hostname !="unknown") $hostname = $rk->hostname; 
		else $hostname = __("unknown");
		$numurl = (int)$rk->page_hits;
		$unclass="";
		$ulclass="users";
		$Ouser="";
		$Ospider="";
		$referrer="";
		$urlrequested="";
		//for logged-in user/administrator in ul list
		$logged_user=trim($rk->login_name,'| ');
		if($logged_user != ""){
			if(strpos($logged_user,'|')!==false){
				$loginnames=explode('|',$logged_user);
				foreach($loginnames AS $name){
					$logged_user=trim($name);
					if(!empty($logged_user)){
						break;
					}
				}
			}
			$utype=__("LOGGED IN USER","wassup");
			$ulclass="userslogged";
			$udata=false;
			//check for administrator
			if(!empty($logged_user)){
				$udata=get_user_by("login",esc_attr($logged_user));
				if($wassup_options->is_admin_login($udata)){
					$utype = __("ADMINISTRATOR","wassup");
					$ulclass .= " adminlogged";
				}
			}
			if(!empty($udata->ID)){
				if($show_avatars) $Ouser='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.get_avatar($udata->ID,'20').' '.esc_attr($logged_user).'</strong></span></li>';
				else $Ouser='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.esc_attr($logged_user).'</strong></span></li>';
			}else{
				$Ouser='<li class="users"><span class="indent-li-agent">'.$utype.': <strong>'.esc_attr($logged_user).'</strong></span></li>';
			}
			$unclass="sum-box-log";
			if($wdebug_mode){
				if (!empty($udata->roles)){
					echo "\n <!-- udata-roles=\c";
					print_r($udata->roles);
					echo "\n -->";
				}
			}
		}
		//for comment author in ul list
		if($rk->comment_author != ""){
			$Ouser .='<li class="users"><span class="indent-li-agent">'.__("COMMENT AUTHOR","wassup").': <strong>'.esc_attr($rk->comment_author).'</strong></span></li>';
			if(empty($unclass)) $unclass="sum-box-aut";
		}
		//for spider/feed in ul list
		if(!empty($rk->spider)){
			if($rk->feed != ""){
				$Ospider='<li class="feed"><span class="indent-li-agent">'.__("FEEDREADER","wassup").': <strong><a href="#" class="toggleagent" id="'.(int)$rk->id.'">'.esc_attr($rk->spider).'</a></strong></span></li>';
				if(is_numeric($rk->feed)){
					$Ospider .='<li class="feed"><span class="indent-li-agent">'.__("SUBSCRIBER(S)","wassup").': <strong>'.(int)$rk->feed.'</strong></span></li>';
				}
			}else{
				$Ospider='<li class="spider"><span class="indent-li-agent">'.__("SPIDER","wassup").': <strong><a href="#" class="toggleagent" id="'.(int)$rk->id.'">'.esc_attr($rk->spider).'</a></strong></span></li>';
			}
			$unclass="sum-box-spider";
		}
		//for spam in ul list
		if(!empty($rk->malware_type)){
			$unclass="sum-box-spam";
		}
		if(strlen($ip)>20) $unclass .=" sum-box-ipv6";
		echo "\n";?>
	<div id="delID<?php echo esc_attr($rk->wassup_id);?>" class="sum-rec <?php if($wassup_user_settings['umark']==1 && $wassup_user_settings['uip']==$ip) echo 'sum-mark';?>"> <?php
		// Visitor Record - raw data (hidden)
		$raw_div="raw-".substr($rk->wassup_id,0,25).rand(0,99);
		echo "\n"; ?>
		<div id="<?php echo $raw_div;?>" style="display:none;"><?php
			$args=array('numurl'=>$numurl,'rk'=>$rk);
			wassup_rawdataView($args);?>
		</div>
		<div class="sum-nav<?php if ($wassup_user_settings['umark']==1 && $wassup_user_settings['uip']==$ip) echo ' sum-nav-mark';?>">
			<div class="sum-box">
				<span class="sum-box-ip <?php echo $unclass;?>"><?php if($numurl >1){ ?><a href="#" class="showhide" id="<?php echo (int)$rk->id;?>"><?php echo esc_attr($ip);?></a><?php }else{ echo esc_attr($ip);}?></span>
				<span class="sum-date"><?php print $datetimeF; ?></span>
			</div>
			<div class="sum-det">
				<p class="delbut"><?php
		// Mark/Unmark IP
		echo "\n";
		$deleteurl="";
		if($wassup_user_settings['umark']==1 && $wassup_user_settings['uip']==$ip){
			if(is_multisite() && is_network_admin()){
				$deleteurl=wp_nonce_url(network_admin_url('admin.php?'.$URLQuery.'&deleteMARKED=1&dip='.$ip),'wassupdelete-'.$current_user->ID);
			}elseif(current_user_can('manage_options')){
				$deleteurl=wp_nonce_url(admin_url('admin.php?'.$URLQuery.'&deleteMARKED=1&dip='.$ip),'wassupdelete-'.$current_user->ID);
			}
			if(!empty($deleteurl)){?>
					<a href="<?php echo wassupURI::cleanURL($deleteurl);?>" class="deleteIP"><img class="delete-icon" src="<?php echo WASSUPURL.'/img/b_delete.png" alt="'.__('delete','wassup').'" title="'.__('Delete ALL marked records with this IP','wassup');?>"/></a><?php
			}?>
					<a href="?<?php echo wassupURI::cleanURL($URLQuery.'&mark=0');?>"><img class="unmark-icon" src="<?php echo WASSUPURL.'/img/error_delete.png" alt="'.__('unmark','wassup').'" title="'.__('UnMark IP','wassup');?>"/></a><?php
		}else{
			if(current_user_can('manage_options')){?>
					<a href="#" class="deleteID" id="<?php echo esc_attr($rk->wassup_id);?>"><img class="delete-icon" src="<?php echo WASSUPURL.'/img/b_delete.png" alt="'.__('delete','wassup').'" title="'.__('Delete this record','wassup');?>"/></a><?php
			}?>
					<a href="?<?php echo wassupURI::cleanURL($URLQuery.'&mark=1&wip='.$ip);?>"><img class="mark-icon" src="<?php echo WASSUPURL.'/img/error_add.png" alt="'.__('mark','wassup').'" title="'.__('Mark IP','wassup');?>"/></a><?php
		}
		echo "\n";?>
					<a href="#TB_inline?height=400&width=<?php echo $res.'&inlineId='.$raw_div; ?>" class="thickbox"><img class="table-icon" src="<?php echo WASSUPURL.'/img/b_select.png" alt="'.__('show raw table','wassup').'" title="'.__('Show the items as raw table','wassup'); ?>" /></a>
				</p>
				<span class="det1"><?php
			$char_len=round($max_char_len*.9,0);
			echo wassupURI::url_link($rk->urlrequested,$char_len,$rk->malware_type);?></span>
				<span class="det2"><strong><?php
			_e('Referrer','wassup');
			if(empty($rk->referrer)){
				$referrer=__("direct hit","wassup");
			}elseif(empty($rk->searchengine)){
				$referrer=wassupURI::referrer_link($rk,$char_len);
			}else{
				$referrer=wassupURI::se_link($rk,$char_len);
			}?>: </strong><?php echo $referrer;?><br />
				<strong><?php _e('Hostname','wassup');?>:</strong> <?php echo esc_attr($hostname); ?></span>
			</div>
		</div> <!-- /sum-nav -->
		<div class="detail-data">
			<?php 
			// Referer is search engine
			if($rk->searchengine != ""){
				$seclass = 'searcheng';
			if(stristr($rk->searchengine,"images")!==FALSE || stristr($rk->referrer,'&imgurl=')!==FALSE){
				$seclass .= ' searchmedia'; 
				$pagenum = intval(number_format(($rk->searchpage / 19),1))+1;
				$url = parse_url($rk->referrer); 
				$page = (number_format(($rk->searchpage / 19), 0) * 18); 
				$ref = $url['scheme']."://".$url['host']."/images?q=".str_replace(' ', '+', $rk->search)."&start=".$page;
			}else{
				if(stristr($rk->searchengine,"video")!==FALSE || stristr($rk->searchengine,"music")!==FALSE){
					$seclass .=' searchmedia';
				}
				$pagenum = (int)$rk->searchpage;
				$ref = $rk->referrer;
			}
			if($rk->search == "_notprovided_") $keywords='('.__("not provided","wassup").')';
			else $keywords=$rk->search;
			$serk=$rk;
			$serk->referrer=$ref;
			?><ul class="<?php echo $seclass; ?>">
			<li class="searcheng"><span class="indent-li-agent"><?php _e('SEARCH ENGINE','wassup'); ?>: <strong><?php print esc_attr($rk->searchengine)." (".__("page","wassup").": $pagenum)"; ?></strong></span></li>
			<li class="searcheng"><span><?php _e("KEYWORDS","wassup");?>: <strong><?php echo wassupURI::se_link($serk,$char_len,$keywords);?></strong></span></li>
			</ul>
<?php 			} //end if searchengine
			if(!empty($Ouser)){
				echo "\n";?>
			<ul class="<?php echo $ulclass;?>">
				<?php echo $Ouser;?>
			</ul><?php
			}
			// Visitor is a Spider or Bot
			if(!empty($rk->spider)){
				if($rk->feed != ""){
					echo "\n";?>
			<ul class="spider feed"><?php echo $Ospider;?></ul><?php 
				}else{
					echo "\n";?>
			<ul class="spider"><?php echo $Ospider;?></ul>
<?php				}
			}
			// Visitor is a Spammer
			if($rk->malware_type > 0 && $rk->malware_type < 3){ ?>
			<ul class="spam">
			<li class="spam"><span class="indent-li-agent"><?php
				echo '<strong>'.__("Probably SPAM!","wassup").'</strong>'; 
				if($rk->malware_type==2){
					echo ' ('.__("Referer Spam","wassup").')';
				}elseif(!empty($wassup_options->spam)){
					echo ' (Akismet '.__("Spam","wassup").')';
				}else{
					echo ' ('.__("Comment Spam","wassup").')';
				}?> </span></li>
			</ul><?php
			// Visitor is MALWARE/HACK attempt
			}elseif($rk->malware_type == 3){
				echo "\n";?>
			<ul class="spam">
			<li class="spam"><span class="indent-li-agent">
			<?php _e("Probably hack/malware attempt!","wassup"); ?></span></li>
			</ul><?php 
			}
			//hidden user agent string
			?><div class="togglenavi naviagent<?php echo $rk->id ?>" style="display: none;"><ul class="useragent">
				<li class="useragent"><span><?php _e('User Agent','wassup'); ?>: <strong><?php 
			if(wassupURI::is_xss($rk->agent)){
				echo '<span class="malware">'.wassupURI::disarm_attack($rk->agent).'</span>';
			}else{
				echo '<span>'.wassupURI::disarm_attack($rk->agent).'</span>';
			}
			?></strong></span></li>
			</ul></div><?php
			// User flag/os/browser
			if ($rk->spider == "" && ($rk->os != "" || $rk->browser != "")) {
				$flag="&nbsp; ";
				if ($rk->language != "") {
					$lang=esc_attr($rk->language);
					if(file_exists(WASSUPDIR."/img/flags/".$lang.".png")){
						$flag='<img src="'.WASSUPURL.'/img/flags/'.$lang.'.png" alt="'.$lang.'" title="'.__("Language","wassup").': '.strtoupper($lang).'"/>';
					}else{
						$flag=$lang;
					}
				}
				echo "\n";?>
			<ul class="agent">
			<li class="agent"><span class="indent-li-agent"><?php echo $flag.'&nbsp; '.__("OS","wassup"); ?>: <strong><a href="#" class="toggleagent" id="<?php echo (int)$rk->id;?>"><?php echo esc_attr($rk->os);?></a></strong></span></li>
			<li class="agent"><span class="indent-li-browser"><?php _e("BROWSER","wassup");?>:&nbsp;<strong><a href="#" class="toggleagent" id="<?php echo (int)$rk->id;?>"><?php echo esc_attr($rk->browser);?></a></strong></span></li><?php 
				if($rk->resolution !=""){
					echo "\n";?>
			<li class="agent"><span class="indent-li-res"><?php _e("RESOLUTION","wassup");?>:&nbsp;<strong><?php echo esc_attr($rk->resolution);?></strong></span></li><?php
				}
				echo "\n";?>
			</ul><?php
			}
			echo "\n";
			if($numurl >1){
			?><div style="display:visible;" class="togglecrono navi<?php echo (int)$rk->id ?>">
			<ul class="url"><?php 
				$sql=sprintf("SELECT CONCAT_WS('', SUBSTRING(`timestamp`, 1, 7), TRIM(TRAILING '/' FROM`urlrequested`)) AS urlid, `timestamp`, `urlrequested` FROM `$wassup_table` WHERE `wassup_id`='%s' %s ORDER BY `timestamp` ASC",esc_attr($rk->wassup_id),$multisite_whereis);
				$qryCD=$wpdb->get_results($sql);
				if(!empty($qryCD) && is_wp_error($qryCD)){
					$errno=$qryCD->get_error_code();
					$error_msg=" qryCD error#$errno ".$qryCD->get_error_message()."\n SQL=".esc_attr($sql);
					$qryCD=false;
				}
				$i=1;
				$char_len=round($max_char_len*.92,0);
				$urlid="";
			if(!empty($qryCD) && is_array($qryCD)){
			foreach ($qryCD as $cd){
				if ($wassup_options->wassup_time_format == 24) {
					$time2 = '<span class="time">'.gmdate("H:i:s", $cd->timestamp).' </span>';
				} else {
					$time2 = '<span class="time">'.gmdate("h:i:s a", $cd->timestamp).'</span>';
				}
				$num = ($i&1);
				if ($num == 0) $classodd = "urlodd"; 
				else  $classodd = "url";
				//skip duplicate urls within 15mins
				if ($i==$numurl || $cd->urlid != $urlid){
					echo "\n"; ?>
			<li class="<?php echo $classodd.' navi'.(int)$rk->id;?> wassup-nowrap"><span class="request-time"><?php echo $time2.' &rarr; ';?></span><span class="request-uri"><?php echo wassupURI::url_link($cd->urlrequested,$char_len,$rk->malware_type);?></span></li><?php
				}
				$urlid=$cd->urlid;
				$i++;
			}
			}
			echo "\n";?>
			</ul>
			</div><!-- /url --><?php
			} //end if numurl>1
?>
		</div><!-- /detail-data -->
		<p class="sum-footer"></p>
	</div><!-- /delID... --><?php
		} //end foreach wmain as rk
		echo $expcol;
	} //end if witemstot > 0
	echo "\n";
	if ($witemstot > $witems) {?>
	<div align="center"><?php $p->show();?></div><br /><?php
		echo "\n";
	}
	if(!empty($data_error)){?>
		<p><?php echo $data_error;?></p><?php
		echo "\n";
	}?>
	</div><!-- /main-tabs --><?php
	// Print Google chart last to speed up detail display
	if (!empty($wassup_user_settings['detail_chart']) || (!empty($_GET['chart']) && "1" == $_GET['chart'])) {
		$chart_type = (!empty($wassup_options->wassup_chart_type))? $wassup_options->wassup_chart_type: "2";
		//show Google!Charts image
		$html='<p style="padding-top:10px;">'.__("Too few records to print chart","wassup").'...</p>';
		if ($wpagestot > 12) {
			//extend script timeout for chart
			if($can_set_timelimit && (time()-$stimer_start)>$stimeout-30){
				@set_time_limit($stimeout);
				$stimer_start=time();
			}
			$chart_url=$wTot->TheChart($wlast,$res,"180",$wsearch,$chart_type,"bg,s,e9e9ea|c,lg,90,deeeff,0,e9e9ea,0.8","page",$wtype);
			$html='<img src="'.$chart_url.'" alt="'.__("Graph of visitor hits","wassup").'" class="chart" width="'.$res.'" />';
		}
	} else {
		$html='<p style="padding-top:10px">&nbsp;</p>';
	} //end if wassup_chart==1
	echo "\n";?>
	<script type="text/javascript">jQuery('div#chart_placeholder').html(<?php echo "'".$html."'";?>).css("background-image","none");</script>
	<?php if(!empty($witemstot) && $witemstot >=10) echo $scrolltop;?><?php
	} else {
		echo "\n<h3>".sprintf(__("Invalid page request %s","wassup"),"$wassuppage").'</h3>';
	} //end MAIN/DETAILS VIEW

	//display MySQL errors/warnings - for debug
	if($wdebug_mode){
		if(!empty($error_msg)) echo "\n".__FUNCTION__." ERROR: ".$error_msg;
		@ini_set('display_errors',$mode_reset);	//turn off debug
	}
} //end wassup_page_contents

if (!class_exists('wassup_Dashboard_Widgets')){
/**
 * Static class container for WassUp dashboard widgets functions
 * @since v1.9
 * @author helened - 2014-11-05
 */
class wassup_Dashboard_Widgets{
	//Private constructor for true static class - prevents direct creation of object
	private function __construct(){}

	static function init(){
		global $wp_version,$wassup_options;
		$dashwidget_access=$wassup_options->get_access_capability();
		if(!empty($dashwidget_access) && current_user_can($dashwidget_access)){
			//load Wassup modules as needed
			if(!class_exists('WassupItems')) require_once(WASSUPDIR."/lib/main.php");
			add_action('admin_head',array(__CLASS__,'add_dash_css'),20);
			if(is_network_admin()){
				wp_add_dashboard_widget('wassup-dashwidget1','Visitors Summary',array(__CLASS__,'dash_widget1'));
			}else{
				if(version_compare($wp_version,'2.7','<')){
					//for backward compatibility
					add_action('activity_box_end',array(__CLASS__,'dash_chart'));
				}else{
					add_meta_box('wassup-dashwidget1','Visitors Summary',array(__CLASS__,'dash_widget1'),'dashboard','side','high');
				}
			}
		}
	}
	static function remove_dash_widget($widgetid="wassup-dashwidget1"){
		remove_meta_box($widgetid,'dashboard','side');
	}
	static function add_dash_css(){
		global $wdebug_mode;

		$vers=WASSUPVERSION;
		if($wdebug_mode) $vers.='b'.rand(0,9999);
		echo "\n";?>
<link rel="stylesheet" href="<?php echo WASSUPURL.'/css/wassup.css?ver='.$vers;?>" type="text/css" /><?php
	}
	/** Print a chart in the dashboard for WP < 2.2-2.6 */
	static function dash_chart(){
		global $wpdb,$wassup_options;
		$wassup_table=$wassup_options->wassup_table;
		$wassupfolder=plugin_basename(WASSUPDIR);
		$chart_type=($wassup_options->wassup_chart_type >0)? $wassup_options->wassup_chart_type: "2";
		$to_date=current_time("timestamp");
		$ctime=1;
		$date_from=$to_date - (int)(($ctime*24)*3600);
		$whereis="";
		$Chart=New WassupItems($wassup_table,$date_from,$to_date,$whereis);
		$chart_url="";
		if($Chart->totrecords >1){
			$chart_url=$Chart->TheChart($ctime,"400","125","",$chart_type,"bg,s,efebef|c,lg,90,edffff,0,efebef,0.8","dashboard");
		}?>
	<h3>WassUp <?php _e('Stats','wassup'); ?> <cite><a href="admin.php?page=<?php echo $wassupfolder; ?>"><?php _e('More','wassup'); ?> &raquo;</a></cite></h3>
	<div id="wassup-dashchart" class="placeholder" align="center">
		<img src="<?php echo esc_url($chart_url);?>" alt="WassUp <?php _e('visitor stats chart','wassup'); ?>"/>
	</div>
<?php
	} //end dash_chart

	/** Output WassUp main dashboard widget */
	static function dash_widget1(){
		global $wpdb,$wp_version,$wassup_options,$wdebug_mode;

		$wassup_table=$wassup_options->wassup_table;
		$wassup_tmp_table=$wassup_table."_tmp";
		$chart_type=($wassup_options->wassup_chart_type >0)?$wassup_options->wassup_chart_type:"2";
		$res=((int)$wassup_options->wassup_screen_res-160)/2;
		$to_date=current_time("timestamp");
		$ctime=1;
		$date_from=$to_date - (int)(($ctime*24)*3600);
		$whereis="";
		if(is_multisite() && $wassup_options->network_activated_plugin()){
			if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)) $whereis .=sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
		}
		$Chart=New WassupItems($wassup_table,$date_from,$to_date,$whereis);
		$chart_url="";
		if($Chart->totrecords >1){
			$chart_url=$Chart->TheChart($ctime,$res,"180","",$chart_type,"bg,s,f3f5f5|c,lg,90,edffff,0,f3f5f5,0.8","dashboard");
		}
		$max_char_len=40;
		echo "\n";?>
	<div class="wassup-dashbox"<?php
		 if(version_compare($wp_version,"3.5","<")) echo ' style="margin:-10px;"';
		 elseif(version_compare($wp_version,"3.8","<")) echo ' style="margin:-10px -12px -10px -10px;"';?>>
		<cite><a href="<?php echo admin_url('index.php?page=wassup-stats');?>"><?php _e('More Stats','wassup');?> &raquo;</a></cite><?php
		echo "\n";
		//Show chart...
		if(!empty($chart_url)){?>
		<div class="wassup-dashitem no-bottom-border">
			<p id="wassup-dashchart" class="placeholder" align="center" style="margin:0 auto;padding:0;"><img src="<?php echo$chart_url.'" alt="[img: WassUp '.__('visitor stats chart','wassup').']';?>"/></p>
		</div><?php
			echo "\n";
		}
		//Show online count
		$currenttot=0;
		if($wassup_options->is_recording_active()){
			//use variable timeframes for online counts: spiders for 1 min, regular visitors for 3 minutes, logged-in users for 10 minutes
			$to_date=current_time('timestamp');
			$from_date=$to_date - 10*60;	//-10 minutes
			$sql=sprintf("SELECT `wassup_id`, MAX(`timestamp`) as max_timestamp, `ip`, `urlrequested`, `referrer`, `searchengine`, `spider`, `username`, `comment_author`, `language`, `spam` AS malware_type FROM `$wassup_tmp_table` WHERE `timestamp`>'%d' AND (`username`!='' OR `timestamp`>'%d' OR (`timestamp`>'%d' AND `spider`='')) %s GROUP BY `wassup_id` ORDER BY max_timestamp DESC",$from_date,$to_date - 1*60,$to_date - 3*60,$whereis);
			$qryC=$wpdb->get_results($sql);
			if(!empty($qryC)){
			 	if(is_array($qryC)) $currenttot=count($qryC);
				elseif(!empty($qryC) && is_wp_error($qryC)) $error_msg=" error# ".$qryC->get_error_code().": ".$qryC->get_error_message()."\nSQL=".esc_attr($sql)."\n";
			}
			if($wdebug_mode){
				echo "\n<!-- ";
				if(!empty($error_msg)){
					echo "wassup_Dashboard_Widgets ERROR: ".$error_msg;
				}elseif($currenttot >0){
					echo "&nbsp; &nbsp; qryC=";
					print_r($qryC);
				}
				echo "\n-->";
			}
		} //end if is_recording_active
		if($currenttot > 0){ ?>
		<div class="wassup-dashitem no-top-border">
			<h5><?php echo '<strong>'.$currenttot."</strong>".__("Visitors online","wassup");?></h5><?php
			echo "\n";?>
		</div>
		<div class="wassup-dashitem"><?php
			$Ousername=array();
			$Ocomment_author=array();
			$prev_url="";
			$prev_wassupid="";
			$char_len=$max_char_len;
			$siteurl=wassupURI::get_sitehome();
			$wpurl=wassupURI::get_wphome();
			foreach($qryC as $cv){
				//don't show duplicates
			if(($cv->urlrequested!=$prev_url || $cv->wassup_id!=$prev_wassupid)){
				$prev_url=$cv->urlrequested;
				$prev_wassupid=$cv->wassup_id;
				if($wassup_options->wassup_time_format == 24) $timed=gmdate("H:i:s", $cv->max_timestamp);
				else $timed=gmdate("h:i:s a", $cv->max_timestamp);
				$ip=wassup_clientIP($cv->ip);
				$referrer="";
				if($cv->referrer !='' && stristr($cv->referrer,$wpurl)!=$cv->referrer && stristr($cv->referrer,$siteurl)!=$cv->referrer){
					if($cv->searchengine !="") $referrer=wassupURI::se_link($cv,$char_len);
					else $referrer=wassupURI::referrer_link($cv,$char_len);
				}
				$requrl=wassupURI::url_link($cv->urlrequested,$char_len,$cv->malware_type);
				if($cv->username!="" || $cv->comment_author!=""){
				if($cv->username!=""){
					$Ousername[]=esc_attr($cv->username);
					if(!empty($cv->comment_author))$Ocomment_author[]=esc_attr($cv->comment_author);
				}elseif($cv->comment_author!=""){
					$Ocomment_author[]=esc_attr($cv->comment_author);
				}
				}
				//don't show admin requests to users
				if(preg_match('#\/wp\-(admin|includes|content)\/#',$cv->urlrequested)==0 || current_user_can('manage_options')){
					echo "\n";?>
			<p><strong><?php echo esc_attr($timed);?></strong> &middot; <?php echo esc_attr($ip); ?> &rarr; <?php echo $requrl;
				if(!empty($referrer)) echo '<br />'.__("Referrer","wassup").': <span class="widgetref">'.$referrer.'</span>';?></p><?php
				}
			} //end if cv->urlrequested
			} //end foreach qryC
			echo "\n";?>
		</div><?php
			if(count($Ousername)>0){
				natcasesort($Ousername);
				echo "\n";?>
		<div class="wassup-dashitem<?php if(count($Ocomment_author)==0)echo ' no-bottom-border';?>"><p><?php
				echo __('Registered users','wassup').': <span class="loggedin">'.implode('</span> &middot; <span class="loggedin">',array_unique($Ousername)).'</span>';?></p></div><?php
			} 
			if(count($Ocomment_author)>0){
				natcasesort($Ocomment_author);
				echo "\n";?>
		<div class="wassup-dashitem no-bottom-border"><p><?php
				echo __('Comment authors','wassup').': <span class="commentaut">'.implode('</span> &middot; <span class="commentaut">',$Ocomment_author).'</span>';?></p></div><?php
			}
		}elseif($wassup_options->is_recording_active()){ ?>
		<div class="wassup-dashitem no-top-border no-bottom-border">
			<h5><strong>1</strong> <?php _e("Visitor online","wassup");?></h5>
		</div><?php

		}else{ ?>
		<div class="wassup-dashitem no-top-border no-bottom-border">
			<p><?php echo "&nbsp; ".__("No online data!","wassup");?></p>
		</div><?php
		} //end if currentot>0
		echo "\n";?>
		<div class="wassup-dashitem no-top-border no-bottom-border"><span class="wassup-marque"><?php echo __("powered by","wassup").' <a href="http://www.wpwp.org/" title="WassUp - '.__("Real Time Visitors Tracking","wassup").'">WassUp</a>';?></span></div>
	</div><!-- /wassup-dashbox --><?php
		$wdebug_mode=false; //turn off debug after display of widget due to ajax conflict.
	} //end dash_widget1
} //end Class wassup_Dashboard_Widgets
} //end if class_exists
?>

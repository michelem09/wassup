<?php
/**
 * Defines Wassup_Widget base widget class and classes for Wassup's primary widgets: Visitors Online and Top Stats.
 *
 * @package WassUp Real-time Analytics
 * @subpackage widgets.php module
 * @since:	v1.9
 * @author:	Helene D. <http://helenesit.com>
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
if(!defined('WASSUPURL')){
	if(!wassup_init()) exit;	//nothing to do
}
//load widget-functions.php module
if(!function_exists('wassup_widget_clear_cache')) require_once(WASSUPDIR.'/widgets/widget_functions.php');

//Wassup's base widget
if(!class_exists('Wassup_Widget')){
/**
 * Base class for building Wassup aside widgets
 *  - sets common default options for all child widgets
 *  - adds wassup-widget.css to page header
 *  - generate a unique 'wassup_widget_id' for widget caching
 *
 * Wassup_Widget API:
 *  - Extensions of Wassup_Widget must use the prefix 'wassup_' in the widget class name
 *  - Extensions of Wassup_Widget must overwrite the 3 parent methods:
 *      ::form  - control form for editing widget settings
 *          -add the field, 'wassup_widget_id' to form
 *          -use '::wassup_parse_args' method instead of 'wp_parse_args' to update for latest defaults
 *      ::update - processes and saves widget settings
 *          -update for the 'wassup_widget_id' field
 *      ::widget - displays the widget.
 */
class Wassup_Widget extends WP_Widget{
	/** __construct */
	function wassup_widget($id="wassup_widget",$name="Wassup Widget",$widget_opts=array(),$control_opts=array()){
		global $wp_version;
		$default_widget_opts=array(
			'widget_description'=>"WassUp ".__("base widget","wassup"),
			'classname'=>"wassup-widget",
		);
		$default_control_opts=array(
			'width'=>246,
			'height'=>400,
		);
		//widget control dimensions different in WP versions
		if(version_compare($wp_version,'3.8','<')){
			$default_control_opts=array('width'=>250,'height'=>550);
		}
		$this->wassup_default_opts=array(
			'title'=>"",
			'ulclass'=>"links",
			'chars'=>0,
			'refresh'=>60,
			'wassup_widget_id'=>0,
		);
		if(empty($widget_opts)) $widget_opts=$default_widget_opts;
		else $widget_opts=wp_parse_args($widget_opts,$default_widget_opts);
		if(empty($control_opts)) $control_opts=$default_control_opts;
		else $control_opts=wp_parse_args($control_opts,$default_control_opts);
		//instantiate parent
		parent::__construct($id,$name,$widget_opts,$control_opts);
		$this->wassup_add_css();
	}
	/** Widget control form - for widget options */
	function form($old_instance){
		$defaults=array( 
			'title'=>"",
			'chars'=>0,
			'refresh'=>60,
			'wassup_widget_id'=>0,
		);
		$instance=$this->wassup_parse_args($old_instance,$defaults);
		$checked='checked="checked"';
		$disabled='disabled="disabled"';
		echo "\n";?>
	<div class="wassup-widget-ctrl">
		<ul class="widget-items">
		<li class="widget-li no-top-border">
			<label for="<?php echo $this->get_field_id('title');?>"><strong><?php _e("Title","wassup");?></strong>:</label> &nbsp;<input type="text" name="<?php echo $this->get_field_name('title').'" id="'.$this->get_field_id('title');?>" class="title wide_text" value="<?php echo esc_attr($instance['title']);?>"/>
		</li>
		<li class="widget-li">
			<h4><?php _e("Widget style options","wassup");?>:</h4>
			<ul>
			<li><label for="<?php echo $this->get_field_id('ulclass');?>"><?php echo sprintf(__("Class attribute for %s list:","wassup"),"&lt;ul&gt");?></label> <input type="text" class="medium-text" name="<?php echo $this->get_field_name('ulclass').'" id="'.$this->get_field_id('ulclass');?>" value="<?php echo esc_attr($instance['ulclass']);?>"/>
			</li>
			<li><label for="<?php echo $this->get_field_id('chars');?>"><?php _e("Max. chars to display from left","wassup");?>:</label> <input type="text" class="stats-number" name="<?php echo $this->get_field_name('chars').'" id="'.$this->get_field_id('chars');?>" value="<?php echo (int)$instance['chars'];?>"/>
			<p class="note" style="padding-left:3px;">&nbsp;<?php echo __("enter \"0\" for theme default/line wrap of long texts","wassup");?></p></li>
			</ul>
		</li>
		</ul>
		<input type="hidden" name="<?php echo $this->get_field_name('refresh').'" id="'.$this->get_field_id('refresh');?>" value="<?php echo (int)$instance['refresh'];?>"/>
		<input type="hidden" name="<?php echo $this->get_field_name('wassup_widget_id').'" id="'.$this->get_field_id('wassup_widget_id');?>" value="<?php echo $instance['wassup_widget_id'];?>"/>
	</div><!-- /wassup-widget-ctrl --><?php
	} //end form

	/** saves widget options */
	function update($new_instance=array(),$old_instance=array()){
		global $wassup_options;
		$instance=false;
		$instance['title']=(isset($new_instance['title'])?$wassup_options->cleanFormText($new_instance['title']):"");
		$instance['chars']=(int)$new_instance['chars'];
		$instance['ulclass']=$wassup_options->cleanFormText($new_instance['ulclass']);
		$instance['wassup_widget_id']=$new_instance['wassup_widget_id'];
		//purge widget cache to apply new settings
		wassup_widget_clear_cache($instance['wassup_widget_id']);
		return $instance;
	} //end update

	/** displays widget content on web site */
	function widget($wargs,$instance=array()){
		global $wassup_options,$wdebug_mode;
		$widget_opt=$wargs;
		if(empty($instance['wassup_widget_id'])) $instance=$this->wassup_get_widget_id($instance);
		$wassup_widget_id=$instance['wassup_widget_id'];
		//get widget head and foot content
		$title=((!empty($instance['title']))?trim($instance['title']):"");
		$ulclass="";
		$widget_title="";
		if(!empty($title)) $widget_title=$widget_opt['before_title'].esc_attr($title).$widget_opt['after_title'];
		if(!empty($instance['ulclass'])) $ulclass=' class="'.$instance['ulclass'].'"';
		$widget_head='
	'.$widget_title;
		$widget_foot=wassup_widget_foot_meta();
		$html='
	<ul'.$ulclass.'><li>'.__("No Data","wassup").'</li></ul>';
		//display widget
		if(!empty($html)){
			echo "\n".$widget_opt['before_widget'];
			echo $widget_head.$html.$widget_foot;
			echo "\n".$widget_opt['after_widget'];
		}
	} //end widget

	/* Do NOT Override the methods below */
	/** adds head style tag for widget/widget-form display */
	function wassup_add_css(){
		//widget css - one style tag for multiple widgets
		if(!is_admin()){
			//styles for widget display
			if(!has_action('wp_head','wassup_widget_css')){
				add_action('wp_head','wassup_widget_css');
			}
		}elseif(strpos($_SERVER['REQUEST_URI'],'/widgets.php')>0 || strpos($_SERVER['REQUEST_URI'],'/customize.php')>0){
			//styles for widget control/settings form
			//'wassup_widget_form_css' uses priority 11 to print after 'widgets.css'
			if(!has_action('admin_head','wassup_widget_form_css')){
				add_action('admin_head','wassup_widget_form_css',11);
			}
		}
	}
	/** create a unique id for caching Wassup widgets html */
	function wassup_get_widget_id($instance){
		global $wassup_options;
		$wassup_widget_id=$this->option_name."-".$this->number;
		//add blog_id for unique ids in network activation
		if($wassup_options->network_activated_plugin() && !empty($GLOBALS['current_blog']->blog_id)) $wassup_widget_id .="-b".(int)$GLOBALS['current_blog']->blog_id;
		$instance['wassup_widget_id']=$wassup_widget_id;
		return $instance;
	}
	/** update for new widget settings, add new default values */
	function wassup_parse_args($old_instance,$defaults){
		$all_defaults=wp_parse_args($defaults,$this->wassup_default_opts);
		if(empty($old_instance['wassup_widget_id'])){
			$instance=$this->wassup_get_widget_id($all_defaults);
		}else{
			$instance=wp_parse_args($old_instance,$all_defaults);
		}
		return $instance;
	}
} //end class
} //end if class_exists Wassup_Widget

/**
 * Current Visitors Online widget
 *
 * - Show counts of visitors currently browsing your site.
 */
class wassup_onlineWidget extends Wassup_Widget{
	/** PHP4-compatible __construct */
	function wassup_onlinewidget(){
		$widget_id="wassup_online";
		$widget_name='WassUp '.__("Online","wassup");
		$widget_description= __("Show counts of your site's visitors who are currently online.","wassup");
		$widget_opts=array('description'=>$widget_description,'classname'=>"wassup-widget");
		$control_opts=array('description'=>$widget_description);
		//instantiate parent
		parent::wassup_widget($widget_id,$widget_name,$widget_opts,$control_opts);
	} //end __construct

	/** Widget control form - for widget options */
	function form($old_instance=array()){
		$defaults=array( 
			'online_title'=>__("Online Now","wassup"),
			'online_total'=>1,
			'online_loggedin'=>0,
			'online_comauth'=>0,
			'online_anonymous'=>0,
			'online_other'=>0,
			'show_usernames'=>0,
			'show_flags'=>0,
			'refresh'=>60,
		);
		$instance=$this->wassup_parse_args($old_instance,$defaults);
		$checked='checked="checked"';
		$disabled='disabled="disabled"';
		echo "\n";?>
	<div class="wassup-widget-ctrl">
		<ul class="widget-items">
		<li class="widget-li no-top-border">
			<label for="<?php echo $this->get_field_id('online_title');?>"><strong><?php _e("Title","wassup");?></strong>:</label> &nbsp;<input type="text" name="<?php echo $this->get_field_name('online_title').'" id="'.$this->get_field_id('online_title');?>" class="title wide_text" value="<?php echo esc_attr($instance['online_title']);?>"/>
		</li>
		<li class="widget-li">
			<h4><?php _e("Show online counts for:","wassup");?></h4>
			<ul style="padding-top:2px;">
			<li><label for="<?php echo $this->get_field_id('online_total');?>"><input type="checkbox" name="<?php echo $this->get_field_name('online_total').'" id="'.$this->get_field_id('online_total');?>" value="1" <?php if(!empty($instance['online_total'])){echo $checked;}?>/> <?php _e("All current visitors","wassup");?></label></li>
			<li>&nbsp; &nbsp;<label for="<?php echo $this->get_field_id('online_loggedin');?>"><input type="checkbox" name="<?php echo $this->get_field_name('online_loggedin').'" id="'.$this->get_field_id('online_loggedin');?>" value="1" <?php if(!empty($instance['online_loggedin'])){echo $checked;}?>/> <?php _e("Logged-in users","wassup");?></label></li>
			<li>&nbsp; &nbsp;<label for="<?php echo $this->get_field_id('online_comauth');?>"><input type="checkbox" name="<?php echo $this->get_field_name('online_comauth').'" id="'.$this->get_field_id('online_comauth');?>" value="1" <?php if(!empty($instance['online_comauth'])){echo $checked;}?>/> <?php _e("Comment authors","wassup");?></label></li>
			<li>&nbsp; &nbsp;<label for="<?php echo $this->get_field_id('online_anonymous');?>"><input type="checkbox" name="<?php echo $this->get_field_name('online_anonymous').'" id="'.$this->get_field_id('online_anonymous');?>" value="1" <?php if(!empty($instance['online_anonymous'])){echo $checked;}?>/> <?php _e("Regular visitors","wassup");?></label></li>
			<li>&nbsp; &nbsp;<label for="<?php echo $this->get_field_id('online_others');?>"><input type="checkbox" name="<?php echo $this->get_field_name('online_others').'" id="'.$this->get_field_id('online_others');?>" value="1" <?php if(!empty($instance['online_others'])){echo $checked;}?>/> <?php _e("Others","wassup");?></label></li>
			</ul>
		</li>
		<li class="widget-li">
			<h4><?php _e("Online Users Details","wassup");?></h4>
			<ul>
			<li><table class="legend"><tbody><tr><td class="checkbox"><input type="checkbox" name="<?php echo $this->get_field_name('show_usernames').'" id="'.$this->get_field_id('show_usernames');?>" value="1" <?php if(!empty($instance['show_usernames'])){echo $checked;}?>/></td><td><label for="<?php echo $this->get_field_id('show_usernames');?>"><?php _e("Show online usernames to registered users","wassup");?></label></td></tr>
			<tr><td class="checkbox"><input type="checkbox" name="<?php echo $this->get_field_name('show_flags').'" id="'.$this->get_field_id('show_flags');?>" value="1" <?php if(!empty($instance['show_flags'])){echo $checked;}?>/></td><td><label for="<?php echo $this->get_field_id('show_flags');?>"><?php _e("Show country flags of users online","wassup");?></label></td></tr></tbody></table></li>
			</ul>
		</li>
		<li class="widget-li">
			<h4><?php _e("Widget style options","wassup");?>:</h4>
			<ul>
			<li><label for="<?php echo $this->get_field_id('ulclass');?>"><?php echo sprintf(__("Class attribute for %s list:","wassup"),"&lt;ul&gt");?></label> <input type="text" class="medium-text" name="<?php echo $this->get_field_name('ulclass').'" id="'.$this->get_field_id('ulclass');?>" value="<?php echo esc_attr($instance['ulclass']);?>"/>
			</li>
			<li><label for="<?php echo $this->get_field_id('chars');?>"><?php _e("Max. chars to display from left","wassup");?>:</label> <input type="text" class="stats-number" name="<?php echo $this->get_field_name('chars').'" id="'.$this->get_field_id('chars');?>" value="<?php echo (int)$instance['chars'];?>"/>
			<p class="note" style="padding-left:3px;">&nbsp;<?php echo __("enter \"0\" for theme default/line wrap of long texts","wassup");?></p></li>
			</ul>
		</li>
		<li class="widget-li no-bottom-border">
			<p class="note">&middot;&nbsp;<?php echo __("online counts are automatically cached for 1 minute.","wassup");?></p>
			<p class="note">&middot;&nbsp;<?php echo __("empty results are not displayed.","wassup");?></p>
		</li>
		</ul>
		<input type="hidden" name="<?php echo $this->get_field_name('refresh').'" id="'.$this->get_field_id('refresh');?>" value="60"/>
		<input type="hidden" name="<?php echo $this->get_field_name('wassup_widget_id').'" id="'.$this->get_field_id('wassup_widget_id');?>" value="<?php echo $instance['wassup_widget_id'];?>"/>
	</div><!-- /wassup-widget-ctrl --><?php
	} //end form

	/** saves widget options */
	function update($new_instance=array(),$old_instance=array()){
		global $wassup_options;
		$instance=false;
		if(!empty($new_instance['wassup_widget_id'])){
			$instance['online_title']=(isset($new_instance['online_title'])?$wassup_options->cleanFormText($new_instance['online_title']):"");
			$instance['online_total']=(isset($new_instance['online_total'])?(int)$new_instance['online_total']:"0");
			$instance['online_loggedin']=(isset($new_instance['online_loggedin'])?(int)$new_instance['online_loggedin']:"0");
			$instance['online_comauth']=(isset($new_instance['online_comauth'])?(int)$new_instance['online_comauth']:"0");
			$instance['online_anonymous']=(isset($new_instance['online_anonymous'])?(int)$new_instance['online_anonymous']:"0");
			$instance['online_others']=(isset($new_instance['online_others'])?(int)$new_instance['online_others']:"0");
			$instance['show_usernames']=(isset($new_instance['show_usernames'])?(int)$new_instance['show_usernames']:0);
			$instance['show_flags']=(isset($new_instance['show_flags'])?(int)$new_instance['show_flags']:0);
			$instance['chars']=(int)$new_instance['chars'];
			$instance['ulclass']=$wassup_options->cleanFormText($new_instance['ulclass']);
			$instance['wassup_widget_id']=$new_instance['wassup_widget_id'];
			//purge widget cache to apply new settings
			wassup_widget_clear_cache($instance['wassup_widget_id']);
		}
		return $instance;
	} //end update

	/** displays widget content on web site */
	function widget($wargs,$instance=array()){
		global $wp_version,$wassup_options,$wdebug_mode;
		$widget_opt=$wargs;
		if(empty($instance['wassup_widget_id'])) $instance=$this->wassup_get_widget_id($instance);
		$wassup_widget_id=$instance['wassup_widget_id'];
		//get widget head and foot content
		$title=((!empty($instance['online_title']))?trim($instance['online_title']):"");
		$ulclass="";
		$widget_title="";
		if(!empty($title)) $widget_title=$widget_opt['before_title'].esc_attr($title).$widget_opt['after_title'];
		if(!empty($instance['ulclass'])) $ulclass=' class="'.$instance['ulclass'].'"';
		$widget_head='
	'.$widget_title;
		$widget_foot=wassup_widget_foot_meta();
		$html="";
		//get widget main content
		//...1st check for cached widget content
		if(!empty($instance['show_usernames']) && is_user_logged_in()){
			$cache_key="_online_users";
		}else{
			//don't show usernames to regular visitors
			$cache_key="_online";
			$instance['show_usernames']=0;
		}
		if($wdebug_mode){
			echo "\n<!-- widget instance param=\c";
			print_r($instance);
			echo "\n -->";
			echo "\n<!-- widget_opt=\c";
			print_r($widget_opt);
			echo "\n cache_key=$cache_key";
			echo "\n -->";
		}
		$refresh=(isset($instance['refresh']) && is_numeric($instance['refresh'])?(int)$instance['refresh']:60);
		if($refresh >0) $html=wassup_widget_get_cache($wassup_widget_id,$cache_key);
		//...get new widget content
		if(empty($html)){
			if($wassup_options->is_recording_active()){
				$html=wassup_widget_get_online_counts($instance);
				//cache the new widget content
				if($refresh >0){
					$cacheid=wassup_widget_save_cache($html,$wassup_widget_id,$cache_key,$refresh);
				}
			}else{
				$html='
	<ul'.$ulclass.'><li>'.__("No Data","wassup").'</li></ul>';
			}
		}
		//display widget
		if(!empty($html)){
			echo "\n".$widget_opt['before_widget'];
			echo $widget_head.$html.$widget_foot;
			echo "\n".$widget_opt['after_widget'];
		}
		if($wdebug_mode){
			//display sample widget for format debuging
			$sample_html="";
			if(function_exists('wassup_sample_widget')){
				$sample_html=wassup_sample_widget("online");
			}
			if(!empty($sample_html)){
				echo "\n".$widget_opt['before_widget'];
				echo $sample_html;
				echo $widget_foot;
				echo "\n".$widget_opt['after_widget'];
			}
		}
	} //end widget
} //end class wassup_onlineWidget

/**
 * Top Stats widget
 *
 * - Lists top stats or trending stats on your site, depending on statistics timeframe used.
 */
class wassup_topstatsWidget extends Wassup_Widget{
	/** PHP4-compatible __construct */
	function wassup_topstatswidget(){
		global $wp_version;
		$widget_id="wassup_topstats";
		$widget_name='WassUp '.__("Top Stats","wassup");
		$widget_description= __("List your site's most popular or trending items from Wassup's latest stats data.","wassup");
		$widget_opts=array('description'=>$widget_description);
		$control_opts=array('height'=>700,'description'=>$widget_description);
		//instantiate parent
		parent::wassup_widget($widget_id,$widget_name,$widget_opts,$control_opts);	//parent::__construct()
	} //end __construct

	/** Widget control form - for widget options */
	function form($old_instance=array()){
		global $wp_version,$wassup_options;
		$defaults=array( 
			'title'=>"",
			'top_articles'=>5,
			'top_searches'=>0,
			'top_referrers'=>0,
			'top_requests'=>0,
			'top_browsers'=>0,
			'top_os'=>0,
			'top_locale'=>0,
			'stat_counts'=>0,
			'stat_timeframe'=>"1", //1 day default
			'stat_refresh'=>10,	//10min default cache
		);
		$instance=$this->wassup_parse_args($old_instance,$defaults);
		$title=$instance['title'];
		$checked='checked="checked"';
		$disabled='disabled="disabled"';
		$i=0;
		echo "\n";?>
	<div class="wassup-widget-ctrl">
		<ul class="widget-items">
		<li class="widget-li" style="border-top:0 none;">
			<label for="<?php echo $this->get_field_id('title');?>"><strong><?php _e("Title","wassup");?></strong>:</label> &nbsp;<input class="wide_text" type="text" name="<?php echo $this->get_field_name('title').'" id="'.$this->get_field_id('title');?>" value="<?php echo esc_attr($title);?>"/>
			<p class="note" style="padding-left:10px;"><?php echo __("of first checked item below","wassup");?></p>
		</li>
		<li class="widget-li"><h4><?php _e("List Top Results for","wassup");?>:</h4>
			<ul style="padding-top:2px;">
			<li style="padding-top:0;">
			<table class="wassup-droppable"><tbody>
			<tr><th align="left"><nobr><?php _e("Stat item","wassup");?></nobr></th>
			<th align="right" style="text-align:right;"><?php _e("max limit","wassup");?>&nbsp;</th></tr><?php
				$i++;
				$tooltip='<span title="'.__("Titles of posts and pages","wassup").'">'.__("Latest articles","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_articles"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_articles').'" id="'.$this->get_field_id('show_top_articles');?>" value="1" <?php if(!empty($instance['top_articles']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_articles').'" id="'.$this->get_field_id('top_articles');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_articles'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip='<span title="'.__("Search engine searches","wassup").'">'.__("Latest searches","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_searches"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_searches').'" id="'.$this->get_field_id('show_top_searches');?>" value="1" <?php if(!empty($instance['top_searches']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_searches').'" id="'.$this->get_field_id('top_searches');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_searches'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip='<span title="'.__("External links that generated referrals to your site","wassup").'">'.__("Latest referrers","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_referrers"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_referrers').'" id="'.$this->get_field_id('show_top_referrers');?>" value="1" <?php if(!empty($instance['top_referrers']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_referrers').'" id="'.$this->get_field_id('top_referrers');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_referrers'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip=__("Latest URL requests","wassup");
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_requests"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_requests').'" id="'.$this->get_field_id('show_top_requests');?>" value="1" <?php if(!empty($instance['top_requests']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_requests').'" id="'.$this->get_field_id('top_requests');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_requests'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip='<span title="'.__("Client browser software","wassup").'">'.__("Latest browsers","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_browsers"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_browsers').'" id="'.$this->get_field_id('show_top_browsers');?>" value="1" <?php if(!empty($instance['top_browsers']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_browsers').'" id="'.$this->get_field_id('top_browsers');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_browsers'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip='<span title="'.__("Client device/operating software","wassup").'">'.__("Latest OS","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_os"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_os').'" id="'.$this->get_field_id('show_top_os');?>" value="1" <?php if(!empty($instance['top_os']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_os').'" id="'.$this->get_field_id('top_os');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_os'];?>" /></td>
			</tr><?php
				$i++;
				$tooltip='<span title="'.__("Visitors country/language","wassup").'">'.__("Latest locale","wassup").'</span>';
				echo "\n";?>
			<tr id="row-<?php echo $i;?>" class="draggable" draggable="true">
				<td><label for="show_top_locale"><input type="checkbox" name="<?php echo $this->get_field_name('show_top_locale').'" id="'.$this->get_field_id('show_top_locale');?>" value="1" <?php if(!empty($instance['top_locale']))echo $checked;?>/>&nbsp;&nbsp;<?php echo $tooltip;?></label></td><td align="right"><input name="<?php echo $this->get_field_name('top_locale').'" id="'.$this->get_field_id('top_locale');?>" class="stats-limit" type="number" min="0" max="100" value="<?php echo (int)$instance['top_locale'];?>" /></td>
			</tr>
			</tbody></table>
			</li>
			<li><label for="<?php echo $this->get_field_id('stat_counts');?>"><strong><?php _e("Show counts for each item","wassup");?></strong>:</label> &nbsp;<input type="checkbox" name="<?php echo $this->get_field_name('stat_counts').'" id="'.$this->get_field_id('stat_counts');?>" value="1" <?php if(!empty($instance['stat_counts']))echo $checked;?>/></li>
			<li><label for="<?php echo $this->get_field_id('stat_timeframe');?>"><strong><?php _e("Statistics timeframe","wassup");?></strong>:</label> &nbsp;<select name="<?php echo $this->get_field_name('stat_timeframe').'" id="'.$this->get_field_id('stat_timeframe');?>"><?php $wassup_options->showFieldOptions("wassup_time_period",$instance['stat_timeframe']);?></select>
			<p class="note" style="padding-left:3px;">&nbsp;<?php echo __("select 1-30 days for latest top results, 1-12 hours for trending results","wassup");?></p></li>
			<li><label for="<?php echo $this->get_field_id('stat_refresh');?>"><strong><?php echo __("Refresh statistics every:","wassup");?></strong> <nobr><input class="stats-number" name="<?php echo $this->get_field_name('stat_refresh').'" id="'.$this->get_field_id('stat_refresh');?>" type="number" min="0" max="7200" value="<?php echo (int)$instance['stat_refresh'];?>"/> <?php _e("minutes","wassup");?></nobr></label></li>
			</ul>
		</li>
		<li class="widget-li">
			<h4><?php _e("Widget style options","wassup");?>:</h4>
			<ul>
			<li><label for="<?php echo $this->get_field_id('ulclass');?>"><?php echo sprintf(__("Class attribute for %s list:","wassup"),"&lt;ul&gt");?></label> <input type="text" class="medium-text" name="<?php echo $this->get_field_name('ulclass').'" id="'.$this->get_field_id('ulclass');?>" value="<?php echo esc_attr($instance['ulclass']);?>"/>
			</li>
			<li><label for="<?php echo $this->get_field_id('chars');?>"><?php _e("Max. chars to display from left","wassup");?>:</label> <input type="text" class="stats-number" name="<?php echo $this->get_field_name('chars').'" id="'.$this->get_field_id('chars');?>" value="<?php echo (int)$instance['chars'];?>"/>
			<p class="note" style="padding-left:3px;">&nbsp;<?php echo __("enter \"0\" for theme default/line wrap of long texts","wassup");?></p></li>
			</ul>
		</li>
		<li class="widget-li" style="border-bottom:0 none;">
			<p class="note">&middot; <?php echo __("known spammers and spiders are excluded from results.","wassup");?></p>
			<p class="note">&middot; <?php echo __("empty results are not displayed.","wassup");?></p>
		</li>
		</ul><!-- /widget-items -->
		<input type="hidden" name="<?php echo $this->get_field_name('wassup_widget_id').'" id="'.$this->get_field_id('wassup_widget_id');?>" value="<?php echo $instance['wassup_widget_id'];?>"/>
	</div><!-- /wassup-widget-ctrl --><?php
	} //end form

	/** saves widget options */
	function update($new_instance=array(),$old_instance=array()){
		global $wp_version,$wassup_options;
		$instance=false;
		if(!empty($new_instance['wassup_widget_id'])){
			$default_limit=5;
			$instance['title']=$wassup_options->cleanFormText($new_instance['title']);
			if(!empty($new_instance['show_top_articles'])) $instance['top_articles']=(empty($new_instance['top_articles'])?$default_limit:(int)$new_instance['top_articles']);
			else $instance['top_articles']=0;
			if(!empty($new_instance['show_top_searches'])) $instance['top_searches']=(empty($new_instance['top_searches'])?$default_limit:(int)$new_instance['top_searches']);
			else $instance['top_searches']=0;
			if(!empty($new_instance['show_top_referrers'])) $instance['top_referrers']=(empty($new_instance['top_referrers'])?$default_limit:(int)$new_instance['top_referrers']);
			else $instance['top_referrers']=0;
			if(!empty($new_instance['show_top_requests'])) $instance['top_requests']=(empty($new_instance['top_requests'])?$default_limit:(int)$new_instance['top_requests']);
			else $instance['top_requests']=0;
			if(!empty($new_instance['show_top_browsers'])) $instance['top_browsers']=(empty($new_instance['top_browsers'])?$default_limit:(int)$new_instance['top_browsers']);
			else $instance['top_browsers']=0;
			if(!empty($new_instance['show_top_os'])) $instance['top_os']=(empty($new_instance['top_os'])?$default_limit:(int)$new_instance['top_os']);
			else $instance['top_os']=0;
			if(!empty($new_instance['show_top_locale'])) $instance['top_locale']=(empty($new_instance['top_locale'])?$default_limit:(int)$new_instance['top_locale']);
			else $instance['top_locale']=0;
			$instance['stat_counts']=((!empty($new_instance['stat_counts']))?(int)$new_instance['stat_counts']:"0");
			if(is_numeric($new_instance['stat_timeframe'])) $instance['stat_timeframe']=$new_instance['stat_timeframe'];
			else $instance['stat_timeframe']="1";
			$instance['stat_refresh']=(int)$new_instance['stat_refresh'];
			$instance['refresh']=$instance['stat_refresh']*60;
			$instance['chars']=(int)$new_instance['chars'];
			$instance['ulclass']=(!empty($new_instance['ulclass'])?$wassup_options->cleanFormText($new_instance['ulclass']):'');
			$instance['wassup_widget_id']=$new_instance['wassup_widget_id'];
			//purge widget cache to apply new settings
			wassup_widget_clear_cache($instance['wassup_widget_id']);
		}
		return $instance;
	} //end update

	/** displays widget content on web site */
	function widget($wargs,$instance=array()){
		global $wp_version,$wassup_options,$wdebug_mode;
		$widget_opt=$wargs;
		if(empty($instance['wassup_widget_id'])) $instance=$this->wassup_get_widget_id($instance);
		$wassup_widget_id=$instance['wassup_widget_id'];
		if($wdebug_mode){
			echo "\n<!-- widget instance param=\c";
			print_r($instance);
			echo "\n -->";
		}
		//get widget head and foot content
		$ulclass=' class="topstats"';
		$widget_head="";
		$widget_foot='';
		if(!empty($instance['title'])){
			$widget_head=$widget_opt['before_title'].esc_attr($instance['title']).$widget_opt['after_title'];
		}
		if(!empty($instance['ulclass'])){
			$ulclass=' class="topstats '.$instance['ulclass'].'"';
		}
		//get widget main content
		$widget_html="";
		$cache_key="_topstats";
		$refresh=(is_numeric($instance['stat_refresh'])?(int)$instance['stat_refresh']*60:600);
		if($refresh >0) $widget_html=wassup_widget_get_cache($wassup_widget_id,$cache_key);
		if(!empty($widget_html)){
			if($wdebug_mode) echo "\n".'<!-- cached contents for widget '.$wassup_widget_id.' found -->';
			echo $widget_html;
		}elseif(!$wassup_options->is_recording_active()){
			if(!empty($widget_head)){
				//display "no data" for inactive wassup
				$widget_html='
	'.$widget_head.'
	<ul'.$ulclass.'><li>'.__("No Data","wassup").'</li></ul>
	'.wassup_widget_foot_meta();
				echo "\n".$widget_opt['before_widget'];
				echo $widget_html;
				echo "\n".$widget_opt['after_widget'];
			}elseif($wdebug_mode){
				echo "\n<!-- no widget data for $wassup_widget_id ... wassup inactive -->";
			}
		}elseif(!empty($instance['top_articles'])|| !empty($instance['top_searches'])|| !empty($instance['top_referrers'])|| !empty($instance['top_requests'])|| !empty($instance['top_browsers'])|| !empty($instance['top_os'])|| !empty($instance['top_locale'])){
			$html="";
			$chars=0;
			if(!empty($instance['chars']))$chars=(int)$instance['chars'];
			$to_date=current_time('timestamp');
			if(!is_numeric($instance['stat_timeframe'])) $instance['stat_timeframe']=1;
			if($instance['stat_timeframe']>0) $from_date=$to_date - $instance['stat_timeframe']*24*60*60;
			else $from_date=0;	//all time
			$top_items=array("articles","searches","referrers","requests","browsers","os","locale");
			$i=0;
			foreach($top_items AS $item){
				$html="";
				$limit=(!empty($instance['top_'.$item])?(int)$instance['top_'.$item]:"0");
				if($limit >0)
					$html=wassup_widget_get_topstat($item,$limit,$chars,$from_date,$instance['stat_counts']);
				//top item html
				if(!empty($html)){
					$title="";
					if(empty($widget_head)){
						if($instance['stat_timeframe']>0 && $instance['stat_timeframe']<1) $item_heading = __("Latest","wassup");
						else $item_heading = __("Top","wassup");
						$title=$widget_opt['before_title'].wassup_widget_stat_gettext($item,$item_heading).$widget_opt['after_title'];
					}else{
						$title=$widget_head;
					}
					if($i>0)$widget_html.="\n".$widget_opt['after_widget'];
					$widget_html.="\n".str_replace('wassup_topstats','wassup_top'.$item,$widget_opt['before_widget']);
					$widget_html.='
	'.$title.'
	<ul'.$ulclass.'>'.$html.'
	</ul>';
					$i++;
				}
			}
			//display widget html
			if(!empty($widget_html)){
				//append footer meta to end of widget
				$widget_html.=wassup_widget_foot_meta().$widget_opt['after_widget'];
				echo $widget_html;
				//cache widget html for next go round
				if($refresh>0){
					$cacheid=wassup_widget_save_cache($widget_html,$wassup_widget_id,$cache_key,$refresh);
				}
			}
		}elseif($wdebug_mode){
			echo "\n<!-- nothing to do. -->";
		} //end elseif top_articles ...
	} //end widget
} //end class wassup_topstatsWidget
?>

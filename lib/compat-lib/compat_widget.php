<?php
/**
 * Creates 'Wassup_Widget' base class without a 'WP_Widget' parent and adds 'wassup_compat_register_widget' function to emulate 'register_widget' for Wordpress 2.2 - 2.7
 *
 * @package WassUp Real-time Analytics
 * @subpackage 	compat_widget.php module
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
	//show escaped bad request on exit
	die("Bad Request: ".htmlspecialchars(preg_replace('/(&#0*37;?|&amp;?#0*37;?|&#0*38;?#0*37;?|%)(?:[01][0-9A-F]|7F)/i','',$_SERVER['REQUEST_URI'])));
}
unset($wfile);	//to free memory
//nothing to do here
if(version_compare($GLOBALS['wp_version'],'2.8','>=')){
	return;
}
//-------------------------------------------------
if(!class_exists('Wassup_Widget')){
/**
 * Base class for building Wassup aside widgets for Wordpress 2.2 - 2.7
 *  - based on 'WP_Widget' class in Wordpress 2.8
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
class Wassup_Widget{
	var $id_base;
	var $name;
	var $option_name;
	var $widget_options;
	var $control_options;
	var $id;
	var $updated = false;
	var $number = false;
	/** PHP4 constructor */
	function wassup_widget($id_base="wassup_widget",$name="Wassup Widget",$widget_opts=array(),$control_opts=array()){
		if(empty($id_base)) $this->id_base=preg_replace( '/(Widget$)/','',strtolower(get_class($this)));
		else $this->id_base=strtolower($id_base);
		$this->name=$name;
		$this->option_name='widget_'.$this->id_base;
		$default_control_opts=array(
			'width'=>260,
			'height'=>450,
			'id_base'=>$this->id_base,
		);
		$default_widget_opts=array(
			'classname'=>$this->option_name,
		);
		$this->wassup_default_opts=array(
			'title'=>"",
			'ulclass'=>"links",
			'chars'=>0,
			'refresh'=>60,
			'wassup_widget_id'=>0,
		);
		if(empty($widget_opts)) $this->widget_options=$default_widget_opts;
		else $this->widget_options=wp_parse_args($widget_opts,$default_widget_opts);
		if(empty($control_opts)) $this->control_options=$default_control_opts;
		else $this->control_options=wp_parse_args($control_opts,$default_control_opts);
		$this->id=$this->id_base; //single widget only
		$this->number=1;
		$this->wassup_add_css();
	}
	/** Widget control form - for widget options */
	function form($old_instance=array()){
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
		if(empty($instance) || !is_array($instance)){
			$instance=$this->get_settings();
		}
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

	/* Do NOT Override the methods below --------------------- */
	/** adds head style tag for widget/widget-form display */
	function wassup_add_css(){
		//widget css
		if(!is_admin()){
			//styles for widget display
			add_action('wp_head','wassup_widget_css');
		}elseif(strpos($_SERVER['REQUEST_URI'],'/widgets.php')>0 || strpos($_SERVER['REQUEST_URI'],'/customize.php')>0){
			//styles for widget control/settings form
			//'wassup_widget_form_css' uses priority 11 to print after 'widgets.css'
			add_action('admin_head','wassup_widget_form_css',11);
		}
	}
	/** create a unique id for caching Wassup widgets html */
	function wassup_get_widget_id($instance=array()){
		if(empty($instance)) $instance=$this->get_settings();
		$instance['wassup_widget_id']=$this->option_name."-".$this->number;
		return $instance;
	}
	/** update for new widget settings, add new default values */
	function wassup_parse_args($old_instance,$defaults){
		//update settings from _POST
		if(!empty($_POST[$this->id_base])){
			$new_instance=$this->update_callback();
			$old_instance=$new_instance;
		}
		if(empty($old_instance)) $old_instance=$this->get_settings();
		$all_defaults=wp_parse_args($defaults,$this->wassup_default_opts);
		if(empty($old_instance['wassup_widget_id'])){
			$instance=$this->wassup_get_widget_id($all_defaults);
		}else{
			$instance=wp_parse_args($old_instance,$all_defaults);
		}
		return $instance;
	}

	/* WP_Widget methods - Do NOT Override --------------------- */
	/** name attribute for input fields */
	function get_field_name($field_name){
		return $this->id_base.'['.$field_name.']';
	}
	/** id attribute for input fields */
	function get_field_id($field_name){
		return $this->id_base."-".$field_name;
	}
	/** update widget settings */
	function update_callback(){
		$instance=array();
		$settings=array();
		if(!empty($_POST) && isset($_POST[$this->id_base])){
			if(is_array($_POST[$this->id_base])) $settings=$_POST[$this->id_base];
			else $settings=maybe_unserialize($_POST[$this->id_base]);
			if(empty($settings) || !is_array($settings)) $settings=$_POST;
			if(!empty($settings)){
				$new_instance=stripslashes_deep($settings);
				$old_instance=$this->get_settings();
				$instance=$this->update($new_instance,$old_instance);
				if(!empty($instance) && is_array($instance)){
					$this->save_settings($instance);
					$this->updated=true;
				}else{
					$instance=array();
				}
			}
		}
		return $instance;
	}
	function get_settings(){
		$settings=maybe_unserialize(get_option($this->option_name));
		if(!is_array($settings)) $settings=array();
		return $settings;
	}
	function save_settings($settings){
		update_option($this->option_name,$settings);
	}
} //end class

/**
 * A crude emulation of 'register_widget' function for older versions of Wordpress.
 *
 * instantiates widget class and assigns parameters for 'wp_register_sidebar_widget' and 'wp_register_widget_control' hooks from class variables and methods
 * @param string $widget_class
 * @return void
 */
function wassup_compat_register_widget($widget_class){
	global $wassup_widgets;
	if(empty($wassup_widgets) || !is_array($wassup_widgets)) $wassup_widgets=array();
	if(class_exists($widget_class) && empty($wassup_widgets[$widget_class])){
		$wassup_widgets[$widget_class]=new $widget_class;
		wp_register_sidebar_widget($wassup_widgets[$widget_class]->id,$wassup_widgets[$widget_class]->name,array(&$wassup_widgets[$widget_class],'widget'),$wassup_widgets[$widget_class]->widget_options);
		wp_register_widget_control($wassup_widgets[$widget_class]->id,$wassup_widgets[$widget_class]->name,array(&$wassup_widgets[$widget_class],'form'),$wassup_widgets[$widget_class]->control_options);
	}
}
} //end if Wassup_Widget
?>

<?php
/**
 * Classes and functions for displaying WassUp reports, stats, chart, and map
 *
 * @package WassUp Real-time Analytics
 * @subpackage main.php module
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
if(!class_exists('wassup_pagination')){
/**
 * Digg style paginator class based on the work of Victor De La Rocha - http://www.mis-algoritmos.com.
 */
class wassup_pagination{
	var $total_pages;
	var $limit;
	var $target;
	var $page;
	var $adjacents;
	var $showCounter;
	var $className;
	var $parameterName;
	var $nextT;
	var $nextI;
	var $prevT;
	var $prevI;
	var $urlF;
	var $calculate;
	var $pagination;
	//PHP4 constructor
	function wassup_pagination(){
		$this->total_pages=null;
		$this->limit=null;
		$this->target="";
		$this->page=1;
		$this->adjacents=2;
		$this->showCounter=false;
		$this->className="pagination";
		$this->parameterName="pp";	//formerly pages
		//Buttons next and previous
		$this->nextT=__("Next","wassup");
		$this->nextI="&#187;";	//&#9658;
		$this->prevT=__("Previous","wassup");
		$this->prevI="&#171;";	//&#9668;
		$this->urlF=false;	//urlFriendly
		$this->calculate=false;
		$this->pagination="";
	}
	function items($value){$this->total_pages=intval($value);}
	function limit($value){$this->limit=intval($value);}
	function target($value){$this->target=$value;}
	function currentPage($value){$this->page=intval($value);}
	function adjacents($value){$this->adjacents=intval($value);}
	function showCounter($value=""){$this->showCounter=($value===true)?true:false;}
	//to change the pagination '<div>' css class
	function changeClass($value=""){$this->className=$value;}
	function nextLabel($value){$this->nextT = $value;}
	function nextIcon($value){$this->nextI = $value;}
	function prevLabel($value){$this->prevT = $value;}
	function prevIcon($value){$this->prevI = $value;}
	function parameterName($value=""){$this->parameterName=$value;}
	//to change urlFriendly
	function urlFriendly($value="%"){
		//"preg_match" replaces deprecated "eregi" function @since v1.9
		if(preg_match('/^ *$/i',$value)>0){
			$this->urlF=false;
			return false;
		}
		$this->urlF=$value;
	}
	function show(){
		if(!$this->calculate){
			if($this->calculate()) echo "<div class=\"$this->className\">$this->pagination</div>";
		}elseif(!empty($this->pagination)){
			echo "<div class=\"$this->className\">$this->pagination</div>";
		}
	}
	function get_pagenum_link($id){
		if(strpos($this->target,'?')===false){
			if($this->urlF) return str_replace($this->urlF,$id,$this->target);
			else return "$this->target?$this->parameterName=$id";
		}else{
			return "$this->target&$this->parameterName=$id";
		}
	}
	function calculate(){
		$this->pagination="";
		$error=false;
		if($this->urlF && $this->urlF !='%' && strpos($this->target,$this->urlF)===false){
			echo 'Especificaste un wildcard para sustituir, pero no existe en el target<br />';
			$error=true;
		}elseif($this->urlF && $this->urlF=='%' && strpos($this->target,$this->urlF)===false){
			echo 'Es necesario especificar en el target el comodin';
			$error=true;
		}
		if($this->total_pages==null){
			echo sprintf(__("You must specify the %s","wassup"),' <strong>'.__("number of pages","wassup").'</strong> ($class->items(1000))<br />');
			$error=true;
		}
		if($this->limit==null){
			echo sprintf(__("You must specify the %s to show per page","wassup"),' <strong>'.__("limit of items","wassup").'</strong>').' ($class->limit(10))<br />';
			$error=true;
		}
		if($error)return false;
		$n=trim($this->nextT.' '.$this->nextI);
		$p=trim($this->prevI.' '.$this->prevT);
		if($this->page==0)$this->page=1;
		$prev=$this->page-1;
		$next=$this->page+1;
		$lastpage=ceil($this->total_pages/$this->limit);
		$lpm1=$lastpage-1;
		if($lastpage>1){
			if($this->page>1)$this->pagination .="<a href=\"".$this->get_pagenum_link($prev)."\">$p</a>";
			else $this->pagination .="<span class=\"disabled\">$p</span>";
			if($lastpage < 7+($this->adjacents*2)){
				for($counter=1;$counter<=$lastpage;$counter++){
					if($counter==$this->page)$this->pagination .="<span class=\"current\">$counter</span>";
					else $this->pagination .="<a href=\"".$this->get_pagenum_link($counter)."\">$counter</a>";
				}
			}elseif($lastpage > 5+($this->adjacents*2)){ //enough pages to hide some
				if($this->page < 1+($this->adjacents*2)){
					for($counter=1;$counter< 4+($this->adjacents*2);$counter++){
						if($counter==$this->page)$this->pagination .="<span class=\"current\">$counter</span>";
						else $this->pagination .="<a href=\"".$this->get_pagenum_link($counter)."\">$counter</a>";
					}
					$this->pagination .="...";
					$this->pagination .="<a href=\"".$this->get_pagenum_link($lpm1)."\">$lpm1</a>";
					$this->pagination .="<a href=\"".$this->get_pagenum_link($lastpage)."\">$lastpage</a>";
				}elseif($lastpage - ($this->adjacents*2) > $this->page && $this->page >($this->adjacents*2)){
					$this->pagination .="<a href=\"".$this->get_pagenum_link(1)."\">1</a>";
					$this->pagination .="<a href=\"".$this->get_pagenum_link(2)."\">2</a>";
					$this->pagination .="...";
					for($counter=$this->page-$this->adjacents;$counter<=$this->page+$this->adjacents;$counter++)
						if($counter==$this->page) $this->pagination .="<span class=\"current\">$counter</span>";
						else $this->pagination .="<a href=\"".$this->get_pagenum_link($counter)."\">$counter</a>";
					$this->pagination .="...";
					$this->pagination .="<a href=\"".$this->get_pagenum_link($lpm1)."\">$lpm1</a>";
					$this->pagination .="<a href=\"".$this->get_pagenum_link($lastpage)."\">$lastpage</a>";
				}else{
					$this->pagination .="<a href=\"".$this->get_pagenum_link(1)."\">1</a>";
					$this->pagination .="<a href=\"".$this->get_pagenum_link(2)."\">2</a>";
					$this->pagination .="...";
					for($counter=$lastpage - (2+($this->adjacents*2));$counter<=$lastpage;$counter++)
						if($counter==$this->page)$this->pagination .="<span class=\"current\">$counter</span>";
						else $this->pagination .="<a href=\"".$this->get_pagenum_link($counter)."\">$counter</a>";
				}
			}
			if($this->page< $counter-1)$this->pagination .="<a href=\"".$this->get_pagenum_link($next)."\">$n</a>";
			else $this->pagination .="<span class=\"disabled\">$n</span>";
			if($this->showCounter)$this->pagination .="<div class=\"pagination_data\">($this->total_pages ".__("Pages","wassup").")</div>";
		}
		$this->calculate=true;
		return true;
	} //end calculate
} //end class wassup_pagination
} //end if !class_exists

if(!class_exists('wDetector')){
/**
 * Class for lightweight user agent detection
 *  - Loosely based on Detector class by Mohammad Hafiz bin Ismail (info@mypapit.net)
 *  - Renamed to 'wDetector' (from 'Detector') for better compatibility with other plugins @since v1.9
 */
class wDetector{
	var $browser;
	var $browser_version;
	var $os_version;
	var $os;
	var $useragent;
	function wdetector($ip="",$ua=""){
		$this->useragent=$ua;
		$this->check_os($ua);
		$this->check_browser($ua);
	}
	function check_os($useragent){
		$os=""; 
		$version="";
		if(preg_match("/Xbox; Xbox/",$useragent,$match)){$os="Xbox";}
		elseif(preg_match("/Windows NT 10\.0/",$useragent,$match)){$os="Win10";}
		elseif(preg_match("/Windows NT 6\.3/",$useragent,$match)){$os="Win8";}
		elseif(preg_match("/Windows NT 6\.2/",$useragent,$match)){$os="Win8";}
		elseif(preg_match("/Windows NT 6\.1/",$useragent,$match)){$os="Win7";}
		elseif(preg_match("/Windows NT 6\.0/",$useragent,$match)){$os="WinVista";}
		elseif(preg_match("/Windows NT 5\.2/",$useragent,$match)){$os="Win2003";}
		elseif(preg_match("/Windows NT 5\.1/",$useragent,$match)){$os="WinXP";}
		elseif(preg_match("/(?:Windows NT 5\.0|Windows 2000)/",$useragent,$match)){$os="Win2000";}
		elseif(preg_match("/(?:WinNT|Windows\s?NT)\s?([0-4\.]+)?/",$useragent,$match)){$os="WinNT";$version=$match[1];}
		elseif(preg_match("/Windows\sPhone\s(8|10)\./",$useragent,$match)){$os="Win".$match[1].' Mobile';}
		elseif(preg_match("/Mac OS X/",$useragent,$match)){$os="MacOSX";}
		elseif(preg_match("/(Mac_PowerPC|Macintosh)/",$useragent,$match)){$os="MacPPC";}
		elseif(preg_match("/Windows ME/",$useragent,$match)){$os="WinME";}
		elseif(preg_match("/(?:Windows95|Windows 95|Win95|Win 95)/",$useragent,$match)){$os="Win95";}
		elseif(preg_match("/(?:Windows98|Windows 98|Win98|Win 98|Win 9x)/",$useragent,$match)){$os="Win98";}
		elseif(preg_match("/(?:WindowsCE|Windows CE|WinCE|Win CE)/",$useragent,$match)){$os="WinCE";}
		elseif(preg_match("/Windows\sPhone\sOS\s\d+/",$useragent,$match)){$os="WinCE";}
		elseif(preg_match("/PalmOS/",$useragent,$match)){$os="PalmOS";}
		elseif(preg_match("/\(PDA(?:.*)\)(.*)Zaurus/",$useragent,$match)){$os="Sharp Zaurus";}
		elseif(preg_match("/Android\s*([0-9\.]+)/",$useragent,$match)){$os="Android";$version=$match[1];}
		elseif(preg_match("/Linux\s*((?:i[0-9]{3})?\s*(?:[0-9]\.[0-9]{1,2}\.[0-9]{1,2})?\s*(?:i[0-9]{3})?)?/",$useragent,$match)){$os="Linux";$version=$match[1];}
		elseif(preg_match("/NetBSD\s*((?:i[0-9]{3})?\s*(?:[0-9]\.[0-9]{1,2}\.[0-9]{1,2})?\s*(?:i[0-9]{3})?)?/",$useragent,$match)){$os="NetBSD";$version=$match[1];}
		elseif(preg_match("/OpenBSD\s*([0-9\.]+)?/",$useragent,$match)){$os="OpenBSD";$version=$match[1];}
		elseif(preg_match("/CYGWIN\s*((?:i[0-9]{3})?\s*(?:[0-9]\.[0-9]{1,2}\.[0-9]{1,2})?\s*(?:i[0-9]{3})?)?/",$useragent,$match)){$os="CYGWIN";$version=$match[1];}
		elseif(preg_match("/SunOS\s*([0-9\.]+)?/",$useragent,$match)){$os="SunOS";$version=$match[1];}
		elseif(preg_match("/IRIX\s*([0-9\.]+)?/",$useragent,$match)){$os="SGI IRIX";$version=$match[1];}
		elseif (preg_match("/FreeBSD\s*((?:i[0-9]{3})?\s*(?:[0-9]\.[0-9]{1,2})?\s*(?:i[0-9]{3})?)?/",$useragent,$match)){$os="FreeBSD";$version=$match[1];}
		elseif(preg_match("/SymbianOS\/([0-9\.]+)/i",$useragent,$match)){$os="SymbianOS";$version=$match[1];}
		elseif (preg_match("/Symbian\/([0-9\.]+)/i",$useragent,$match)){$os="Symbian";$version=$match[1];}
		elseif (preg_match("/PLAYSTATION\s([0-9]+)/",$useragent,$match)){$os="Playstation";$version=$match[1];}
		$this->os=$os;
		$this->os_version=$version;
	}
	function check_browser($useragent) {
		$browser="";
		$version="";
		$match=array();
		if(strpos($useragent,' Gecko/')>0 && preg_match("#^Mozilla\/[0-9.\s]+\(Windows\s(?:NT|Phone)\s[0-9.]+.+\).+(?:\sChrome|Safari)\/[0-9.]+.+\sEdge\/([0-9\.]+)#",$useragent,$match)){
			$browser="Edge";
			$version=$match[1];
		}elseif(preg_match("#^Mozilla\/[0-9.\s]+\(Windows\sNT\s[0-9.]+;.+;\s?rv\:([0-9.]+)\)#",$useragent,$match)){
			$browser="IE";
			$version=$match[1];
		}elseif(preg_match("/^Mozilla(?:.*)compatible;\sMSIE\s(?:.*)Opera\s([0-9\.]+)/",$useragent,$match)){
			$browser = "Opera";
		}elseif(preg_match("/^Opera\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Opera";
		}elseif(preg_match("/^Mozilla(?:.*)compatible;\siCab\s([0-9\.]+)/",$useragent,$match)){
			$browser = "iCab";
		}elseif(preg_match("/^iCab\/([0-9\.]+)/",$useragent,$match)){
			$browser = "iCab";
		}elseif(preg_match("/^Mozilla(?:.*)compatible;\sMSIE\s([0-9\.]+)/",$useragent,$match)){
			$browser = "IE";
		}elseif(preg_match("/^(?:.*)compatible;\sMSIE\s([0-9\.]+)/",$useragent,$match)){
			$browser = "IE";
		}elseif(preg_match("/^Mozilla(?:.*)(?:.*)Chrome/",$useragent,$match)){
			$browser = "Google Chrome";
		}elseif(preg_match("/^Mozilla(?:.*)(?:.*)Safari\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Safari";
		}elseif(preg_match("/^Mozilla(?:.*)\(Macintosh(?:.*)OmniWeb\/v([0-9\.]+)/",$useragent,$match)){
			$browser = "Omniweb";
		}elseif(preg_match("/^Mozilla(?:.*)\(compatible; Google Desktop/",$useragent,$match)){
			$browser = "Google Desktop";
		}elseif(preg_match("/^Mozilla(?:.*)\(compatible;\sOmniWeb\/([0-9\.v-]+)/",$useragent,$match)){
			$browser = "Omniweb";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)(?:Camino|Chimera)\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Camino";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)Netscape\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Netscape";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)(?:Fire(?:fox|bird)|Phoenix)\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Firefox";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)Minefield\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Minefield";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)Epiphany\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Epiphany";
		}elseif(preg_match("/^Mozilla(?:.*)Galeon\/([0-9\.]+)\s(?:.*)Gecko/",$useragent,$match)){
			$browser = "Galeon";
		}elseif(preg_match("/^Mozilla(?:.*)Gecko(?:.*?)K-Meleon\/([0-9\.]+)/",$useragent,$match)){
			$browser = "K-Meleon";
		}elseif(preg_match("/^Mozilla(?:.*)rv:([0-9\.]+)\)\sGecko/",$useragent,$match)){
			$browser = "Mozilla";
		}elseif(preg_match("/^Mozilla(?:.*)compatible;\sKonqueror\/([0-9\.]+);/",$useragent,$match)){
			$browser = "Konqueror";
		}elseif(preg_match("/^Mozilla\/(?:[34]\.[0-9]+)(?:.*)AvantGo\s([0-9\.]+)/",$useragent,$match)){
			$browser = "AvantGo";
		}elseif(preg_match("/^Mozilla(?:.*)NetFront\/([34]\.[0-9]+)/",$useragent,$match)){
			$browser = "NetFront";
		}elseif(preg_match("/^Mozilla\/([34]\.[0-9]+)/",$useragent,$match)){
			$browser = "Netscape";
		}elseif(preg_match("/^Liferea\/([0-9\.]+)/",$useragent,$match)){
			$browser = "Liferea";
		}elseif(preg_match("/^curl\/([0-9\.]+)/",$useragent,$match)){
			$browser = "curl";
		}elseif(preg_match("/^links\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "Links";
		}elseif(preg_match("/^links\s?\(([0-9\.]+)/i",$useragent,$match)){
			$browser = "Links";
		}elseif(preg_match("/^lynx\/([0-9a-z\.]+)/i",$useragent,$match)){
			$browser = "Lynx";
		}elseif(preg_match("/^Wget\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "Wget";
		}elseif(preg_match("/^Xiino\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "Xiino";
		}elseif(preg_match("/^W3C_Validator\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "W3C Validator";
		}elseif(preg_match("/^Jigsaw(?:.*) W3C_CSS_Validator_(?:[A-Z]+)\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "W3C CSS Validator";
		}elseif(preg_match("/^Dillo\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "Dillo";
		}elseif(preg_match("/^amaya\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "Amaya";
		}elseif(preg_match("/^DocZilla\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "DocZilla";
		}elseif(preg_match("/^fetch\slibfetch\/([0-9\.]+)/i",$useragent,$match)){
			$browser = "FreeBSD libfetch";
		}elseif(preg_match("/^Nokia([0-9a-zA-Z\-.]+)\/([0-9\.]+)/i",$useragent,$match)){
			$browser="Nokia";
		}elseif(preg_match("/^SonyEricsson([0-9a-zA-Z\-.]+)\/([a-zA-Z0-9\.]+)/i",$useragent,$match)){
			$browser="SonyEricsson";
		}
		if(empty($version) && !empty($match[1]) && preg_match("/^\d+(\.\d+)?/",$match[1],$pcs)>0){
			$version=$pcs[0];
		}
		$this->browser=$browser;
		$this->browser_version=$version;
	} //end check_browser
} //end class wDetector

/**
 * Class to check for previous comment spam activity
 *  - Looks for previous spammer comment from IP or referrer url
 */
class wassup_checkComment{
	/** check for previous comment spam */
	function isSpammer($authorIP=""){
		global $wpdb;
		if(empty($authorIP))$authorIP=$_SERVER['REMOTE_ADDR'];
		if(!empty($authorIP)){
			$sql=sprintf("SELECT COUNT(comment_ID) AS spam_comment FROM {$wpdb->prefix}comments WHERE comment_author_IP='%s' AND comment_approved='spam'",$authorIP);
			$spam_comment=$wpdb->get_var($sql);
		}
		if(!empty($spam_comment) && !is_wp_error($spam_comment)) return true;
		else return false;
	}
	/** check for referrer spam that is also comment spam - @since v1.8 */
	function isRefSpam($referrerURL) {
		global $wpdb;
		if(!empty($referrerURL)){
			$sql=sprintf("SELECT COUNT(comment_ID) AS spam_comment FROM {$wpdb->prefix}comments WHERE comment_author_url='%s' AND comment_approved='spam'",$referrerURL);
			$spam_comment=$wpdb->get_var($sql);
		}
		if(!empty($spam_comment) && !is_wp_error($spam_comment)) return true;
		else return false;
	}
} //end Class
} //end if !class_exists('wDetector')

/** Truncate $input string to a length of $max */
function stringShortener($input,$max=0,$sep='(...)',$exceedFromEnd=0){
	//check for valid input
	$strng=rtrim($input);
	if(empty($strng) || !is_string($input)){
		return esc_attr($input);	//v1.9.4 bugfix
	}
	//temporarily replace all %-hex chars with literals and trim the input string of whitespaces...re-encoded after truncation
	$instring=rtrim(stripslashes(rawurldecode(html_entity_decode(wassupURI::disarm_attack($input))))," +\t");
	if(empty($instring)) $instring=$input;	//v1.9.4 bugfix
	$inputlen=strlen($instring);
	$max=(is_numeric($max))?(integer)$max:$inputlen;
	if($max <$inputlen){
		$separator=($sep)?$sep:'(...)';
		$modulus=(($max%2));
		$halfMax=floor($max/2);
		$begin="";
		if(!$modulus){
			$begin=substr($instring, 0, $halfMax);
		}else{
			$begin=(!$exceedFromEnd)? substr($instring, 0, $halfMax+1) : substr($instring, 0, $halfMax);
		}
		$end="";
		if(!$modulus){
			$end=substr($instring,$inputlen-$halfMax);
		}else{
			$end=($exceedFromEnd)? substr($instring,$inputlen-$halfMax-1) :substr($instring,$inputlen-$halfMax);
		}
		//$extracted=substr($instring, strpos($instring,$begin)+strlen($begin),$inputlen-$max); //not used here
		$outstring=$begin.$separator.$end;
		if(strlen($outstring) >= $inputlen){  //Because "Fir(...)fox" is longer than "Firefox"
			$outstring=$instring;
		}
		// uses 'esc_attr' and 'esc_html' to make malicious code harmless when echoed to the screen
		$outstring=esc_attr(esc_html($outstring,ENT_QUOTES));
	} else {
		$outstring=esc_attr(esc_html($instring,ENT_QUOTES));
	}
	return $outstring;
} //end function stringShortener

/**
 * Display a single wassup record as a "raw" list of fields
 * @since v1.9
 * @param array (of arguments)
 * @return void
 */
function wassup_rawdataView($args=array()){
	global $wpdb,$wassup_options;

	//get arguments
	$rk=false;
	if(is_array($args) && !empty($args['rk'])) extract($args);
	elseif(is_object($args) && !empty($args->wassup_id)) $rk=$args;
	if(!empty($rk) && is_object($rk) && !empty($rk->wassup_id)){
		$logged_user="";
		echo "\n";?>
	<div class="wassup-raw">
		<h2><?php _e("Raw data","wassup");?>:</h2>
		<span class="raw"><?php echo __("Visitor type","wassup").': ';
		if(!empty($rk->login_name)){
			$logged_user=trim($rk->login_name,', ');
			if(strpos($logged_user,',')!==false){
				$loginnames=explode(',',$logged_user);
				foreach($loginnames AS $name){
					$logged_user=trim($name);
					if(!empty($logged_user)){
						break;
					}
				}
			}
			if(!empty($logged_user)) echo __("Logged-in user","wassup").' - '.esc_attr($logged_user);
		}elseif($rk->malware_type=="3"){ 
			_e("Spammer/Hacker","wassup");
		}elseif($rk->malware_type !="0"){ 
			_e("Spammer","wassup");
		}elseif($rk->comment_author != ""){ 
			echo __("Comment author","wassup").' - '.esc_attr($rk->comment_author);
		}elseif($rk->feed != ""){ 
			echo __("Feed","wassup").' - '.esc_attr($rk->feed);
		}elseif($rk->spider != ""){ 
			echo __("Spider","wassup").' - '.esc_attr($rk->spider);
		}else{
			 _e("Regular visitor","wassup");
		}?></span>
		<ul class="raw">
		<li><span class="field"><?php echo __("IP","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->ip);?></span></li>
		<li><span class="field"><?php echo __("Hostname","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->hostname);?></span></li>
		<li><span class="field"><?php echo __("Url Requested","wassup");?>:</span><span class="raw"><?php
		$p_title="";
		if($rk->urlrequested=='/' && empty($rk->url_wpid)){
			echo $rk->urlrequested;
		}else{  
			echo wassupURI::cleanURL($rk->urlrequested);
		}?></span>
		<li><span class="field"><?php _e("Post/page ID","wassup");?>:</span><span class="raw"><?php
		echo $rk->url_wpid;
		if(!empty($rk->url_wpid) && is_numeric($rk->url_wpid)){
			$result=$wpdb->get_var(sprintf("SELECT `post_title` from {$wpdb->prefix}posts WHERE `ID`=%d",(int)$rk->url_wpid));
			if(empty($result) || is_wp_error($result)) $p_title=" ** ". __("none or deleted post","wassup")." ** ";
			else $p_title=$result;
			if(!empty($p_title)) echo '</span><nobr> &nbsp; &nbsp; '.__("Title","wassup").': </nobr><span class="raw">'.esc_attr($p_title);
		}?></span></li>
		<li><span class="field"><?php echo __("Referrer","wassup");?>:</span><span class="raw"><?php echo wassupURI::cleanURL($rk->referrer);?></span></li><?php
		if(!empty($rk->search) || !empty($rk->searchengine) || !empty($rk->searchpage)){
			echo "\n";?>
		<li><span class="field"><?php echo __("Search Engine","wassup");?></span>:<span class="raw"><?php echo esc_attr($rk->searchengine);?></span></li>
		<li><span class="field"><?php echo __("Search","wassup");?></span>:<span class="raw"><?php echo esc_attr($rk->search);?></span></li>
		<li><span class="field"><?php echo __("Page","wassup");?></span>:<span class="raw"><?php echo esc_attr($rk->searchpage);?></span></li><?php
		}?>
		<li><span class="field"><?php echo __("User Agent","wassup");?>:</span><span class="raw"><?php echo wassupURI::disarm_attack($rk->agent);?></span></li><?php
		if($rk->browser != ""){
			echo "\n";?>
		<li><span class="field"><?php echo __("Browser","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->browser);?></span></li><?php
		}?>
		<li><span class="field"><?php echo __("OS","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->os);?></span></li>
		<li><span class="field"><?php echo __("Locale/Language","wassup")?>:</span><span class="raw"><?php echo esc_attr($rk->language);?></span></li>
		<li><span class="field"><?php echo __("Screen Resolution","wassup");?>:</span><span class="raw"><?php if(!empty($rk->resolution)) echo esc_attr($rk->resolution);elseif(!empty($rk->screen_res)) echo $rk->screen_res;?></span></li><?php
		if(trim($rk->login_name,', ')!=""){
			echo "\n";?>
		<li><span class="field"><?php echo __("Username","wassup");?>:</span><span class="raw"><?php echo esc_attr(trim($rk->login_name,', '));?></span></li><?php
		}
		if($rk->comment_author != ""){
			echo "\n";?>
		<li><span class="field"><?php echo __("Comment Author","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->comment_author);?></span></li><?php
		}
		if($rk->spider != ""){
			echo "\n";
			if($rk->feed != ""){
				if($rk->feed == $rk->spider){?>
		<li><span class="field"><?php echo __("Feed","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->feed);?></span></li><?php
				}else{?>
		<li><span class="field"><?php echo __("Feed","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->spider)." ".esc_attr($rk->feed);?></span></li><?php
				}
			}else{?>
		<li><span class="field"><?php echo __("Spider","wassup");?>:</span><span class="raw"><?php echo esc_attr($rk->spider);?></span></li><?php
			}
		}?>
		<li><span class="field"><?php echo __("Spam","wassup");?>:</span><span class="raw"><?php echo (int)$rk->malware_type.' &nbsp;';
			if($rk->malware_type=="1") echo '('.__("comment spam","wassup").')';
			elseif($rk->malware_type=="2") echo '('.__("referrer spam","wassup").')';
			elseif($rk->malware_type=="3") echo '('.__("hack/malware attempt","wassup").')';
			else echo '('.__("not spam","wassup").')';?></span></li>
		<li><span class="field"><?php echo 'Wassup ID';?>:</span><span class="raw"><?php echo esc_attr($rk->wassup_id);?></span></li>
		<li><span class="field"><?php
		$rawtimestamp=0;
		if(!empty($numurl) && $numurl > 1) echo __("End timestamp","wassup");
		else _e("Timestamp","wassup");?>:</span><span class="raw"><?php
		if(!empty($rk->max_timestamp)) $rawtimestamp=$rk->max_timestamp;
		elseif(!empty($rk->timestamp)) $rawtimestamp=$rk->timestamp;
		if(!empty($rawtimestamp)){
			if($wassup_options->wassup_time_format == 24) $datetimeF=gmdate('Y-m-d H:i:s',$rawtimestamp);
			else $datetimeF=gmdate('Y-m-d h:i:s a',$rawtimestamp);
			echo $datetimeF.' ( '.(int)$rawtimestamp.' )';
		}else{
			echo __("unknown","wassup");
		}?></span></li>
		</ul><?php
		if(!empty($numurl) && $numurl > 1){
			echo '<span class="indent-raw raw">'.sprintf(__("%d URLs visited in session","wassup"),(int)$numurl).'</span><br/>';
		}?>
	</div><?php
	} //end if rk
} //end wassup_rawdataView

/**
 * Retrieve newest data, geolocate visitors, format and display as html.
 *  - For 'spia.js', an ajax/jQuery plugin that shows live activity
 *  - Output html is displayed/streamed in the old Digg Spy style (2008)
 */
function wassup_spiaView ($from_date="",$rows=0,$spytype="",$spy_datasource="") {
	global $wpdb,$wp_version,$current_user,$wassup_options,$wdebug_mode;
	if(!class_exists('wassupOptions')){
		if(!wassup_init()) return;	//nothing to do
	}
	if(empty($wassup_options)) $wassup_options=new wassupOptions;
	$wassup_table=$wassup_options->wassup_table;
	if(!is_object($current_user) || empty($current_user->ID)) $user=wp_get_current_user();
	$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
	$show_avatars=get_option('show_avatars');
	if(!empty($show_avatars)) $show_avatars=true;
	else $show_avatars=false;
	//check for arguments...
	$to_date=current_time("timestamp");
	if(empty($from_date)) $from_date= (int)$to_date - 7;
	if($rows == 0 || !is_numeric($rows)) $rows=15;
	if(empty($spytype)){
		if(!empty($wassup_user_settings['spy_filter'])) $spytype=$wassup_user_settings['spy_filter'];
		elseif(!empty($wassup_options->wassup_default_spy_type)) $spytype=$wassup_options->wassup_default_spy_type;
		else $spytype="everything";
	}
	//temp table is default data source
	if(empty($spy_datasource)) $spy_datasource=$wassup_table."_tmp";
	//mysql clause where conditions
	$multisite_whereis="";
	if($wassup_options->network_activated_plugin()){
		if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)) $multisite_whereis=sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
	}
	$whereis=$wassup_options->getFieldOptions("wassup_default_type","sql",$spytype).$multisite_whereis;
	$wassup_dbtask=array();
	$screen_res_size=670;
	if(!empty($wassup_options->wassup_screen_res)) $screen_res_size= (int)$wassup_options->wassup_screen_res;
	if($screen_res_size < 670) $screen_res_size=670;
	$max_char_len=($screen_res_size)/10;
	$spy_timestamp=$to_date;
	$map="spiamap";
	//define google geoip record and create javascript marker icon
	$geoip_rec=array('ip'=>"",'latitude'=>"",'longitude'=>"",'city'=>"",'country_code'=>"");
	$geo_markers=0;
	if($spy_datasource == $wassup_table) $qryC = $wpdb->get_results(sprintf("SELECT `id`, `wassup_id`, `timestamp`, `ip`, `hostname`, `searchengine`, `urlrequested`, `agent`, `referrer`, `spider`, `feed`, `username`, `comment_author`, `language`, `spam` FROM %s WHERE `timestamp` >'%d' %s ORDER BY `timestamp` DESC LIMIT %d",$spy_datasource,$from_date,$whereis,$rows));
	else $qryC = $wpdb->get_results(sprintf("SELECT `id`, `wassup_id`, `timestamp`, `ip`, `hostname`, `searchengine`, `urlrequested`, `agent`, `referrer`, `spider`, `feed`, `username`, `comment_author`, `language`, `spam` FROM %s WHERE `timestamp` >'%d' %s ORDER BY `timestamp` LIMIT %d",$spy_datasource,$from_date,$whereis,$rows));
	if(!empty($qryC) && !is_wp_error($qryC)){
		$qrows=count($qryC);
		$row_count=0;
		$char_len=$max_char_len*.9;
	//display the rows...
	foreach ($qryC as $cv){
		$unclass="";
		$ulclass="users";
		$visitor=__("Regular visitor","wassup");
		$referrer=__('Direct hit','wassup');
		$requesturl="";
		$map_icon="pinuser";
		if ($wassup_options->wassup_time_format == "12") {
		   	$timef=gmdate('h:i:s A', $cv->timestamp);
		} else {
		   	$timef=gmdate('H:i:s', $cv->timestamp);
		}
		$ip=wassup_clientIP($cv->ip);
		if(empty($cv->searchengine))$referrer=wassupURI::referrer_link($cv->referrer,$cv->urlrequested,$char_len,$cv->spam);
		else $referrer=wassupURI::se_link($cv->referrer,$char_len,$cv->spam);
		$requesturl=wassupURI::url_link($cv->urlrequested,$char_len,$cv->spam);
		if($cv->hostname !="" && $cv->hostname !="unknown") $hostname=$cv->hostname; 
		else $hostname=__("unknown");
		if(!empty($cv->spam)){
			$unclass="sum-box-spam";
			$ulclass="spider";
			$map_icon="pinbot";
			if($cv->spam == "1"){
				//comment spam
				$visitor = __("Spammer","wassup").": ".esc_attr($hostname);
			}else{ //hack attempt
				$visitor = __("Spam/Malware","wassup").": ".esc_attr($hostname);
			}
			if($cv->spider != "") $map_icon="pinbot";
		}elseif($cv->spider != ""){
			if($cv->feed != "") $visitor=__("Feedreader","wassup").": ".esc_attr($cv->spider);
			else $visitor=__("Spider","wassup").": ".esc_attr($cv->spider);
			$unclass="sum-box-spider";
			$ulclass="spider";
			$map_icon="pinbot";
		}elseif($cv->username != ""){
			// User is logged in or is a comment's author
			$unclass="sum-box-log";
			$ulclass="userslogged";
			$visitor=__("Logged user","wassup").": ".esc_attr($cv->username);
			$map_icon="pinlogged";
		}elseif($cv->comment_author != ""){
			$unclass="sum-box-aut";
			$ulclass="users";
			$visitor= __("Comment author","wassup").": $cv->comment_author";
			$map_icon="pinauthor";
		} //end if cv->spam
		// Start getting GEOIP info
		$location="";
		$lat = "";
		$lon = "";
		$flag = "";
		$markerHtml="";
		if($ip !=$geoip_rec['ip'] && preg_match('#^(127\.0\.0\.1|192\.168\.|10\.10\.|\:\:1)#',$ip)==0){
			//geolocate a new visitor IP
			$geoip_rec=wGeolocateIP($ip);
			echo "\n\t<!-- heartbeat -->";
			$lat = $geoip_rec['latitude'];
			$lon = $geoip_rec['longitude'];
			$location = wGetLocationname($geoip_rec);
		} elseif ($ip == $geoip_rec['ip']) {
			//previous visit from same IP, so reuse data
			$lat = $geoip_rec['latitude'];
			$lon = $geoip_rec['longitude'];
			$location = wGetLocationname($geoip_rec);
		}
		if (!empty($geoip_rec['country_code'])){
			$locale=strtolower($geoip_rec['country_code']);
			if(!empty($geoip_rec['country'])) $flag_title=__("Country","wassup").': '.$geoip_rec['country'];
			else $flag_title=__("Country","wassup").': '.$geoip_rec['country_code'];
			if(file_exists(WASSUPDIR."/img/flags/".$locale.".png")) {
				$flag='<img src="'.WASSUPURL.'/img/flags/'.$locale.'.png" title="'.$flag_title.'" />';
				//update language/locale code when different from geoip country code (not us)
				if(empty($cv->language) || ($cv->language =="us" && $locale!="us")){
					$wassup_dbtask[]=sprintf("UPDATE `$wassup_table` SET `language`='%s' WHERE `wassup_id`='%s' AND `language`='%s'",$locale,$cv->wassup_id,$cv->language);
				}
			}
		}
		if(empty($flag) && !empty($cv->language)&& file_exists(WASSUPDIR."/img/flags/".$cv->language.".png")){
			$flag='<img src="'.WASSUPURL.'/img/flags/'.$cv->language.'.png" title="'.__("Language","wassup").': '.strtoupper($cv->language).'"/>';
		}
		// output Javascript to add marker to the map
		$markerjs="";
		$ipclick='<span class="sum-box-ip '.$unclass.'">'.$ip.'</span>';
		if($wassup_user_settings['spy_map']==1 && !empty($lon)&& !empty($lat)){
			if($cv->username !=""){
				$udata=get_user_by("login",esc_attr($cv->username));
				if(!empty($udata->ID)){
					if($show_avatars) $visitor = __("Logged user","wassup").": ".get_avatar($udata->ID,'16')." ".esc_attr($cv->username);
					else $visitor = __("Logged user","wassup").": ".esc_attr($cv->username);
				}
			}
			$markerHtml='<div><div class="bubble">'.$visitor.'<br />IP: '.$ip."<br />".__("Country:","wassup").' '.$flag.' '.$location."<br />".__("URL Request:","wassup")." $timef - $requesturl".'<br /></div></div>';
			$markerjs=wAdd_GeoMarker('spiamap',$cv->id,$lat,$lon,$markerHtml,$map_icon,true);
			//clickable ip repositions and zooms map at ip marker @since v1.9
			$ipclick='<a href="#spia_map" onclick="showMarkerinfo(spiamap,'.$lat.','.$lon.',marker'.(int)$cv->id.',minfo'.(int)$cv->id.');return false;"><span class="sum-box-ip '.$unclass.'">'.$ip.'</span></a>';
		} //end if spy_map
		echo "\n";?>
	<div class="sum-spy">
	<div class="sum-rec sum-nav-spy"><?php echo $markerjs;echo "\n";?>
		<div class="sum-box"><?php echo $ipclick;?></div>
		<div class="sum-det sum-det-spy">
			<span class="det1"><?php echo $requesturl; ?></span>
			<span class="det2"><strong><?php echo $timef; ?> - </strong> <?php print $referrer; ?></span>
			<span class="det2"><?php echo "$flag $location\n";?></span>
		</div>
	</div>
	</div><!-- /sum-spy --><?php
			$row_count +=1;
			$spy_timestamp=$cv->timestamp;
		} //end foreach
		if($spy_datasource == $wassup_table) $spy_timestamp=$qryC[0]->timestamp;
		$expire=time()+60; //1 minute expire
		$saved=wassupDb::update_wassupmeta($current_user->user_login,"_spytimestamp",$spy_timestamp,$expire);
		//note that update_user_option could not be used for spy timestamp tracking because user meta queries are cached, causing duplicates
		if(count($wassup_dbtask)>0){
			$args=array('dbtasks'=>$wassup_dbtask);
			if(is_admin() || version_compare($wp_version,'2.8','<')){
				wassupDb::scheduled_dbtask($args);
			}else{
				wp_schedule_single_event(time()+30,'wassup_scheduled_dbtasks',$args);
			}
		}
	}else{
		if(!is_wp_error($qryC) && !empty($wdebug_mode) && $to_date - $from_date >90 && $to_date%23 == 0){
			//display a "no activity" message occasionally in wdebug_mode as visual indicator that spia.js javascript is running
			echo "\n";?>
	<div class="sum-spy">
	<div class="sum-rec sum-nav-spy" style="width:auto;padding:3px;">
		<span class="det3"><?php
			if($wassup_options->wassup_time_format == "12"){
				echo gmdate('h:i:s A',$to_date);
			}else{
				echo gmdate('H:i:s',$to_date);
			}
			echo ' - '.__("No visitor activity","wassup");?> &nbsp; &nbsp; :-( &nbsp; </span>
	</div>
	</div><?php
		}
		echo "\n";
	} //end if !empty($qryC)
} //end function wassup_spiaView

/** Return javascript to add a marker to a google map. @since v1.8 */
function wAdd_GeoMarker($map,$item_id,$lat,$lon,$markerHtml,$map_icon,$pan=true) {
	$markerjs='<script type="text/javascript">var pos=new google.maps.LatLng('.$lat.','.$lon.');var marker'.$item_id.'=new google.maps.Marker({map:'.$map.',position:pos,icon:'.$map_icon.',animation:google.maps.Animation.DROP});var mcontent=\''.str_replace('\'','"',$markerHtml).'\';var minfo'.$item_id.'=new google.maps.InfoWindow({content:mcontent});';
	$markerjs .='google.maps.event.addListener(marker'.$item_id.',"click",function(){minfo'.$item_id.'.open('.$map.',marker'.$item_id.')});'.$map.'.setZoom(3);';
	if($pan)$markerjs .=$map.'.panTo(pos);';
	$markerjs .='</script>';
	return $markerjs;
}

/**
 * return a location name formatted for wassup_spiaView from array argument
 * @since v1.8
 */
function wGetLocationname($geoip_rec=array()) {
	$country_code="";
	if(!empty($geoip_rec['country_code']))$country_code=strtoupper($geoip_rec['country_code']);
	if (!empty($geoip_rec['country'])) {
		$location = $geoip_rec['country'].' ('.$country_code.')';
		if(!empty($geoip_rec['city'])){
			$location.=' '.sprintf(__("City: %s","wassup"),$geoip_rec['city']);
			if ($country_code == "US" && !empty($geoip_rec['region'])) $location .= ', '.$geoip_rec['region'];
			elseif ($country_code == "US" && !empty($geoip_rec['region_code'])) $location .= ', '.$geoip_rec['region_code'];
		}elseif ($country_code == "US" && !empty($geoip_rec['region'])) {
			$location.=' '.sprintf(__("City: %s","wassup"),__("unknown","wassup")).', '.$geoip_rec['region'];
		}else{
			$location.=' '.sprintf(__("City: %s","wassup"),__("unknown","wassup"));
		}
	}elseif (!empty($geoip_rec['country_name'])) {
		$location = $geoip_rec['country_name'].' ('.$country_code.')';
		if(!empty($geoip_rec['city'])){
			$location.=' '.sprintf(__("City: %s","wassup"),$geoip_rec['city']);
			if ($country_code == "US" && !empty($geoip_rec['region'])) $location .= ', '.$geoip_rec['region'];
			elseif ($country_code == "US" && !empty($geoip_rec['region_code'])) $location .= ', '.$geoip_rec['region_code'];
		}elseif ($country_code == "US" && !empty($geoip_rec['region'])) {
			$location.=' '.sprintf(__("City: %s","wassup"),__("unknown","wassup")).', '.$geoip_rec['region'];
		}else{
			$location.=' '.sprintf(__("City: %s","wassup"),__("unknown","wassup"));
		}
	} else {
		$location = __("Country: unknown, City: unknown","wassup");
	}
	return wptexturize($location);
}

/** 
 * Return geographic location and coordinates for an IP address and cache the data in 'wassup_meta' table.
 * Since version 1.8
 * @param array (ip address or hostname)
 * @return array (ip, location, latitude, longitude, country)
 */
function wGeolocateIP($ip) {
	global $wpdb, $wdebug_mode;
	$geourl = "http://freegeoip.net/json/$ip";
	//$geourl = "http://www.telize.com/geoip/$ip"; //API not public as of 11/15/15
	$geoip = array('ip'=>$ip,'latitude'=>"",'longitude'=>"",'city'=>"",'country_code'=>"");
	if(!empty($ip) && $ip!= "127.0.0.1" && $ip!= "::1" && substr($ip,0,8)!= "192.168."){
		$geodata=false;
		$cached=false;
		//1st  check for cached copy of geoip in wassup_meta
		$geodata = wassupDb::get_wassupmeta($ip,'geoip');
		if(!empty($geodata) && is_array($geodata)){
			$geoip = $geodata;
			$cached=true;
		}else{
			//keep checking
			$geodata=false;
		}
		//2nd try PHP geoip extension function 'geoip_record_by_name'
		if(empty($geodata) && function_exists('geoip_record_by_name')){
			$geodata=geoip_record_by_name($ip);
			if(is_array($geodata) && !empty($geodata['country_code'])) $geoip=$geodata;
			//keep checking
			if(!is_array($geodata) || empty($geodata['city'])) $geodata=false;
		}
		//3rd: remote lookups of geoip (web service api)
		//..uses Wordpress 'wp_remote_get' or 'cURL' for geoip
		if(empty($geodata)){
			$geodata=wFetchAPIData($geourl);
			if(!empty($geodata) && !is_wp_error($geodata)){
				if(!is_array($geodata)) $geodata=json_decode($geodata,true);
				if(is_array($geodata) && !empty($geodata['country_code'])) $geoip=$geodata;
				else $geodata=false;
			}else{
				$geodata=false;
			}
		}
		if(!empty($geodata['country_code'])){
			$geoip = $geodata;
			$geoip['country_code']=strtolower($geodata['country_code']);
		}
		//cache geoip data (with city) for up to 3-days
		if(!$cached){
			if(!empty($geoip['country_code'])){
				if(!empty($geoip['city'])) $expire = time() + 3*24*3600;
				else $expire= time()+24*3600; //1 day cache
			}else{
				$expire= time()+30*60; //30 min cache
			}
			$cache_id=wassupDb::save_wassupmeta($ip,'geoip',$geoip,$expire);
		} //end if !empty(geoip['city'])
	} //end if !empty(ip)
	return $geoip;
} //end function wGeolocateIP

/** 
 * Return an associative array containing the top statistics results from MySql query
 * parameters are: stat_type, limit, from-condition (mysql)
 * return array keys('top_count','top_item','visit_timestamp",["top_group","top_link"])
 * function renamed from 'wGetStats' to avoid name conflicts
 * @author Helene D. 2009-03-0$hostname=@gethostbyaddr($IP);4
 * @param string, integer, string
 * @return array
 */
function get_wassupstat($stat_type, $stat_limit=10, $stat_condition="",$return_sql=false) {
	global $wpdb, $wassup_options, $wdebug_mode;
	if(!class_exists('wassupOptions')){
		if(!wassup_init()) return;	//nothing to do
		$wassup_options=new wassupOptions;
	}elseif(empty($wassup_options)){
		$wassup_options=new wassupOptions;
	}
	if(!is_array($wassup_options->wassup_top10)){
		$top_ten = unserialize(html_entity_decode($wassup_options->wassup_top10));
	}else{
		$top_ten=$wassup_options->wassup_top10;
	}
	$wpurl= strtolower(wassupURI::get_wphome());
	$blogurl= strtolower(wassupURI::get_sitehome());
	$wassup_table=$wassup_options->wassup_table;
	if (empty($stat_limit) || !(is_numeric($stat_limit))) $stat_limit=10;
	//set mysql where condition, if needed
	if (empty($stat_condition)) {
		$to_date = current_time('timestamp');
		$from_date = ((int)$to_date - 24*(60*60)); //24 hours
		$stat_condition = " `timestamp` >='$from_date'";
	}
	$sql="";
	//top search phrases...
	if($stat_type == "searches" || $stat_type=="search"){
		$sql=sprintf("SELECT count(*) AS top_count, `search` AS top_item, max(`timestamp`) AS visit_timestamp, `referrer` AS top_link FROM `$wassup_table` WHERE %s AND `search`!='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition,$stat_limit);

	//Top external referrers...
	}elseif($stat_type=="referrers" || $stat_type=="referrer"){
		//exclude internal referrals
		$wurl = parse_url($blogurl);
		$sitedomain = $wurl['host'];
		$exclude_list = $sitedomain;
		if ($wpurl != $blogurl) {
			$wurl = parse_url($wpurl);
			$wpdomain = $wurl['host'];
			$exclude_list .= ",".$wpdomain;
		}
		//exclude external referrers
		if (!empty($top_ten['topreferrer_exclude'])) {
			$exclude_list .= ",".$top_ten['topreferrer_exclude'];
		}
		//create mysql conditional statement to exclude referrers
		$exclude_referrers = "";
		$exclude_array = array_unique(explode(",", str_replace(', ',',',$exclude_list)));
		$regex_domains="";
		foreach ($exclude_array as $exclude_domain) {
			$www='www\\.';
			if(preg_match('#^(www\d?\.)(.+)#i',$exclude_domain,$pcs)>0){
				if(!empty($pcs[1])) $www=str_replace('.','\\.',$pcs[1]);
				$exclude_domain=$pcs[2];
			}
			//wildcard(*) allowed in domain @since v1.9
			if(empty($regex_domains)) $regex_domains=str_replace(array('.','*'),array('\\.','.*'),rtrim(trim($exclude_domain),'*,'));
			else $regex_domains.="|".str_replace(array('.','*'),array('\\.','.*'),rtrim(trim($exclude_domain),'*,'));
		} //end foreach
		if(!empty($regex_domains)){
			$exclude_referrers .=" AND TRIM(LEADING 'http://' FROM TRIM(LEADING 'https://' FROM `referrer`)) NOT RLIKE '^(".$www.")?(".$regex_domains.")' AND `referrer` NOT RLIKE '.*:(".$www.")?(".$regex_domains.")' AND `referrer` NOT RLIKE '.*="."https?://(".$www.")?(".$regex_domains.")'";
		}
		//exclude the major search engines from referrers
		$exclude_referrers .=" AND TRIM(LEADING 'http://' FROM TRIM(LEADING 'https://' FROM `referrer`)) NOT RLIKE '^(".$www.")?".'([0-9]|[a-z]|\\-|\\.|_)*\\.?(google'.'\\.'."com|yahoo".'\\.'."com|bing".'\\.'."com)'";
		$sql=sprintf("SELECT count(*) AS top_count, TRIM(LEADING '//' FROM TRIM(LEADING 'http:' FROM TRIM(LEADING 'https:' FROM `referrer`))) AS top_item, max(`timestamp`) AS visit_timestamp, `referrer` AS top_link FROM `$wassup_table` WHERE %s AND `referrer`!='' AND `search`='' AND `spam`='0' %s GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d", $stat_condition, $exclude_referrers, $stat_limit);

	//top url requests...
	}elseif($stat_type == "urlrequested" || $stat_type=="requests"){
		$stat_condition1=$stat_condition." AND `urlrequested` NOT LIKE '%?p=%' AND `urlrequested` NOT LIKE '%&p=%'";
		$stat_condition2=$stat_condition." AND `urlrequested` LIKE '%?p=%' OR `urlrequested` LIKE '%&p=%'";
		//exclude labels ('#xxxx') and query parameters from url except for '[?&]p=xx' to better match urls in MySQL @since v1.9
		$sql=sprintf("SELECT count(*) AS top_count, LOWER(TRIM(TRAILING '/' FROM SUBSTRING_INDEX(SUBSTRING_INDEX(`urlrequested`, '/index.php', 1), '#', 1))) AS top_group, max(`timestamp`) AS visit_timestamp, LOWER(`urlrequested`) AS top_item, SUBSTRING_INDEX(`urlrequested`, '#', 1) AS top_link FROM `$wassup_table` WHERE %s AND `spam`='0' GROUP BY 2 UNION SELECT count(*) AS top_count, LOWER(TRIM(TRAILING '&' FROM SUBSTRING_INDEX(`urlrequested`, '#', 1))) AS top_group, max(`timestamp`) AS visit_timestamp, LOWER(`urlrequested`) AS top_item, SUBSTRING_INDEX(`urlrequested`, '#', 1) AS top_link FROM `$wassup_table` WHERE %s AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition1, $stat_condition2, $stat_limit);
	//top browser...
	}elseif($stat_type == "browser" || $stat_type=="browsers"){
		$sql=sprintf("SELECT count(DISTINCT `wassup_id`) AS top_count, SUBSTRING_INDEX(SUBSTRING_INDEX(`browser`, ' 0.', 1), '.', 1) AS top_item, max(`timestamp`) AS visit_timestamp FROM `$wassup_table` WHERE %s AND `browser`!='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition, $stat_limit);
	//top os...
	}elseif($stat_type == "os"){
		$sql=sprintf("SELECT count(DISTINCT `wassup_id`) as top_count, `os` AS top_item, max(`timestamp`) AS visit_timestamp FROM `$wassup_table` WHERE %s AND `os`!='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition,$stat_limit);
	//top language/locale..
	}elseif($stat_type == "language" || $stat_type=="locale"){
		$sql=sprintf("SELECT count(DISTINCT `wassup_id`) as top_count, LOWER(`language`) as top_item, max(`timestamp`) AS visit_timestamp FROM `$wassup_table` WHERE %s AND `language`!='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition, $stat_limit);
	//top visitors...
	} elseif ($stat_type == "visitor" || $stat_type=="visitors"){
		$sql=sprintf("SELECT count(DISTINCT `wassup_id`) as top_count, `username` as top_item, '1loggedin_user' as visitor_type, max(`timestamp`) as visit_timestamp FROM `$wassup_table` WHERE %s AND `username`!='' AND `spam`='0' GROUP BY 2 UNION SELECT count(DISTINCT `wassup_id`) as top_count, `comment_author` as top_item, '2comment_author' as visitor_type, max(`timestamp`) as visit_timestamp FROM `$wassup_table` WHERE %s AND `username`='' AND `comment_author`!='' AND `spam`='0' GROUP BY 2 UNION SELECT count(DISTINCT `wassup_id`) as top_count, `hostname` as top_item, '3hostname' as visitor_type, max(`timestamp`) as visit_timestamp FROM `$wassup_table` WHERE %s AND `username`='' AND `comment_author`='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3, 2 LIMIT %d",$stat_condition,$stat_condition,$stat_condition,$stat_limit);
	//top postid (post|page)
	}elseif($stat_type == "postid" || $stat_type == "article" || $stat_type=="articles" || $stat_type=="url_wpid"){
		$exclude_frontpage="";
		if(!empty($top_ten['top_nofrontpage'])){
			$front_pageid=0;
			$show_on_front=get_option('show_on_front');
			if($show_on_front=="page") $front_pageid=get_option('page_on_front');
			if(!empty($front_pageid) && is_numeric($front_pageid)) $exclude_frontpage=sprintf("AND `url_wpid`!='%d'",$front_pageid);
		}
		$sql=sprintf("SELECT count(*) AS top_count, `url_wpid` AS top_group, max(`timestamp`) as visit_timestamp, `post_title` AS top_item, SUBSTRING_INDEX(`urlrequested`, '#', 1) AS top_link FROM `$wassup_table`, {$wpdb->prefix}posts WHERE %s AND `spam`='0' AND `url_wpid`!='' AND `url_wpid`>'0' %s AND `url_wpid`={$wpdb->prefix}posts.ID GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition,$exclude_frontpage,$stat_limit);
	//do stats on any column in wp_wassup table @since v1.9
	}elseif(!empty($stat_type)){
		$col=$wpdb->get_row(sprintf("SHOW COLUMNS FROM %s LIKE '%s'",$wtable_name,wassupDb::esc_like(esc_attr($stat_type))));
		if(!is_wp_error($col) && !empty($col)){
			$sql=sprintf("SELECT count(DISTINCT `wassup_id`) AS top_count, `$stat_type` AS top_item, max(`timestamp`) as visit_timestamp  FROM `$wassup_table` WHERE %s AND `$stat_type`!='' AND `spam`='0' GROUP BY 2 ORDER BY 1 DESC, 3 DESC LIMIT %d",$stat_condition,$stat_limit);
		}else{
			$error_msg=" column does not exist in table ".$stat_type;
		}
	}else{
		$error_msg=" missing table column name ";
	}
	if(!empty($sql)){
		if(!empty($return_sql)){
			return $sql;
		}else{
			$top_stats=$wpdb->get_results($sql);
			if(is_wp_error($top_stats)){
				$error_msg=" error# ".$top_stats->get_error_code().": ".$top_stats->get_error_message()."\nSQL=".$sql."\n";
			}elseif(!empty($top_stats[0]->top_count)){
				if($wdebug_mode){
					echo "\n<!-- top $stat_type query=$sql";
					echo "\n -->";
				}
				return $top_stats;
			}else{
				$error_msg=" invalid data from query SQL=".$sql;
			}
		}
	}
	if(!empty($error_msg)){
		if($wdebug_mode)echo "\n<!-- ".__FUNCTION__." ERROR: ".$error_msg." -->";
	}
	return false;
} //end function get_wassupstat

/**
 * Display the top 10 stats in table columns
 * @param string(4)
 * @return none
 */
function wassup_top10view ($from_date="",$to_date="",$res="",$top_limit=0,$title=false) {
	global $wpdb,$wp_version,$wassup_options,$wdebug_mode;
	if(!class_exists('wassupOptions')){
		if(!wassup_init()) return;	//nothing to do
		$wassup_options=new wassupOptions;
	}elseif(empty($wassup_options)){
		$wassup_options=new wassupOptions;
	}else{
		$wassup_options->loadSettings();
	}
	$wassup_table=$wassup_options->wassup_table;
	if(!is_array($wassup_options->wassup_top10)){
		$top_ten=maybe_unserialize(html_entity_decode($wassup_options->wassup_top10));
	}else{
		$top_ten=$wassup_options->wassup_top10;
	}
	if(empty($top_ten) || !is_array($top_ten)){
		$top_ten=$wassup_options->defaultSettings("top10");
	}
	$wassup_table=$wassup_options->wassup_table;
	$blogurl=wassupURI::get_sitehome();
	$url=parse_url($blogurl);
	$sitedomain=preg_replace('/^www?[0-9a-z]\./i','',$url['host']);

	//extend php script timeout length for large tables
	$stimeout=ini_get("max_execution_time");
	if(is_numeric($stimeout) && $stimeout >0 && $stimeout <180){
		$disabled_funcs=ini_get('disable_functions');
		if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
			@set_time_limit(3*60); 	//3 minutes timeout
		}
	}
	$col_count=array_sum($top_ten);
	//extend page width to make room for more than 5 columns
	if(empty($res)) $res=$wassup_options->wassup_screen_res;
	if($res < 640 && $col_count >3) $res=640;
	$char_len=(int)($res/$col_count);
	$min_width=(($char_len < 90)?90:$char_len);
	//Since v1.8.3: top_limit in top10 array
	if (empty($top_limit) || !is_numeric($top_limit)) {
		if (!empty($top_ten['toplimit'])) $top_limit = (int) $top_ten['toplimit'];
		else $top_limit = 10;	//default
	}
	//build mysql conditional query...
	$multisite_condition="";
	//for multisite/network activation
	if($wassup_options->network_activated_plugin()){
		if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)){
			$multisite_condition = sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
		}
	}
	if(empty($from_date)) $from_date=$wpdb->get_var(sprintf("SELECT MIN(`timestamp`) FROM %s WHERE `timestamp`>0 %s",$wassup_table,$multisite_condition));
	if(empty($to_date)) $to_date=current_time("timestamp");
	$top_condition = "`timestamp` BETWEEN '".$from_date."' AND '".$to_date."'";
	if(!empty($top_ten['top_nospider'])) $top_condition .= " AND spider=''";
	$top_condition .= $multisite_condition;
	//top stats header
	$table_class="";
	if(!empty($_GET['popup'])){
		$table_class=' class="popup"';
		$wdformat=get_option("date_format");
		if(($to_date-$from_date)>24*60*60){
			$stats_range=gmdate("$wdformat",$from_date)." - ".gmdate("$wdformat",$to_date);
		}else{
			$stats_range=gmdate("$wdformat H:00",$from_date)." - ".gmdate("$wdformat H:00",$to_date);
		}
		$statsheader='<span class="stats-print-btn"><a href="#" class="button" onclick="printstat();return false;">'.__("Print","wassup").'</a></span>'."\n";
		$statsheader .='<h4>'.get_option("blogname").'</h4>'."\n";
		$statsheader .='<span>'.sprintf(__('Top Stats for Period: %s','wassup'),$stats_range).'</span>';
	}
	echo "\n"; ?>
<div id="wassup-topstats">
	<table<?php echo $table_class;?>><?php
	if(!empty($statsheader)){
		echo "\n";?>
	<caption>
		<?php echo $statsheader;?>
	</caption><?php
	}elseif(!empty($title)){
		echo "\n";?>
	<caption>
		<?php echo esc_attr($title);?>
	</caption><?php
	}
	echo "\n";?>
	<tbody>
	<tr><?php
	$cwidth=0;
	$cols=0;
	//show a line# column for long data columns
	if ($top_limit > 10) wPrintRowNums($top_limit);

	//#output top 10 searches
	if ($top_ten['topsearch'] == 1) {
		$top_results=get_wassupstat("searches",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=2*$min_width;
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP QUERY", "wassup");?></li> <?php 
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) { 
			echo "\n"; ?>
		<li class="wassup-nowrap"><nobr><?php
			if ($top10->top_item=="_notprovided_") $top_string='('.__("not provided","wassup").')';
			else $top_string=stringShortener(preg_replace('/'.preg_quote($blogurl,'/').'/i','',$top10->top_item),$char_len);
			echo wPadNum($top10->top_count,$ndigits).' <a href="'.wassupURI::cleanURL($top10->top_link).'" target="_BLANK" title="'.substr($top10->top_item,0,$wassup_options->wassup_screen_res-100).'">'.$top_string.'</a>';?></nobr></li><?php
			$i++;
		}
		}
		//finish list with empty <li> for style consistency
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td> <?php
		$cols+=1;
	} // end if topsearch

	//#output top 10 referrers (not spam)
	$top_results=array();
	$cwidth=0;
	if ($top_ten['topreferrer'] == 1) {
		//to prevent browser timeouts, send <!--heartbeat--> output
		echo "\n<!--heartbeat-->";
		$top_results = get_wassupstat("referrers",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=(int)(2.5*$min_width);
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP REFERRER", "wassup"); ?></li><?php
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) {
			echo "\n"; ?>
			<li class="wassup-nowrap"><?php echo wPadNum($top10->top_count,$ndigits);
			//no link for possible spam/malware
			if(preg_match('/\/wp\-(?:admin|content|includes)\/|\/wp\-login\.php|["\'\<\>\{\}\(\)\*\\\\`]|&[lgr]t;|&#0?3[49];|&#0?4[01];|&#0?6[02];|&#0?9[26];|&#8217;|&#8221;|&quot;/i',$top10->top_item)>0 || wassupURI::is_xss($top10->top_item)){
				echo ' <span class="top10" title="'.wassupURI::cleanURL(substr($top10->top_item,0,$wassup_options->wassup_screen_res-100)).'">';
				echo preg_replace('#^https?\://(?:www\d?\.)?#i','',wassupURI::cleanURL($top10->top_item)).'</span>';
			}else{
				echo ' <a href="'.wassupURI::cleanURL($top10->top_link).'" title="'.wassupURI::cleanURL($top10->top_link).'" target="_BLANK">';
				echo preg_replace('#^https?\://(?:www\d?\.)?#i','',wassupURI::cleanURL($top10->top_item)).'</a>';
			}?></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td> <?php
		$cols+=1;
	} //end if topreferrer

	//#output top 10 url requests
	$cwidth=0;
	$top_results=array();
	if($top_ten['toprequest']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("urlrequested",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=(int)(2.5*$min_width);
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP REQUEST", "wassup"); ?></li><?php
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) {
			echo "\n"; ?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits);
			//no link for 404 and possible spam/malware
			if(strpos($top10->top_item,'[')===0 || preg_match('/\/wp\-(?:admin|content|includes)\/|\/wp\-login\.php|["\'\<\>\{\}\(\)\*\\\\`]|&[lgr]t;|&#0?3[49];|&#0?4[01];|&#0?6[02];|&#0?9[26];|&#8217;|&#8221;|&quot;/i',$top10->top_item)>0 || wassupURI::is_xss($top10->top_item)){
				echo ' <span class="top10" title="'.wassupURI::cleanURL(substr($top10->top_item,0,$wassup_options->wassup_screen_res-100)).'">'.preg_replace('/'.preg_quote($blogurl,'/').'/i','',wassupURI::cleanURL($top10->top_item)).'</span>';
			}else{
				//echo wassupURI::url_link($top10->top_link,false);
				echo ' <a href="'.wassupURI::add_siteurl($top10->top_link).'" target="_BLANK" title="'.wassupURI::cleanURL(substr($top10->top_item,0,$wassup_options->wassup_screen_res-100)).'">'.preg_replace('/'.preg_quote($blogurl,'/').'/i', '', wassupURI::cleanURL($top10->top_item)).'</a>';
			} ?></nobr></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	} //end if toprequest

	//#get top 10 browsers...
	$cwidth=0;
	$top_results=array();
	if($top_ten['topbrowser']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("browser",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		elseif($cols==$col_count-1) echo 'class="lastcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=$min_width+5;
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP BROWSER", "wassup") ?></li><?php
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) {
			echo "\n"; ?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits);
			echo ' <span class="top10" title="'.esc_attr($top10->top_item).'">'.stringShortener($top10->top_item, $char_len).'</span>'; ?></nobr></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	} //end if topbrowser

	//#output top 10 operating systems...
	$cwidth=0;
	$top_results=array();
	if($top_ten['topos']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("os",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		elseif($cols==$col_count-1) echo 'class="lastcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=$min_width+5;
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP OS", "wassup") ?></li><?php
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) {
			echo "\n"; ?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits); ?> <span class="top10" title="<?php echo esc_attr($top10->top_item);?>"><?php echo stringShortener($top10->top_item, $char_len); ?></span></nobr></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	} // end if topos

	//#output top 10 locales/geographic regions...
	$cwidth=0;
	$top_results=array();
	if($top_ten['toplocale']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("language",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		elseif($cols==$col_count-1) echo 'class="lastcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=$min_width+5;
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP LOCALE", "wassup"); ?></li><?php
		$i=0;
		$ndigits=1;
		if(count($top_results)>0){
			$ndigits=strlen("{$top_results[0]->top_count}");
		foreach($top_results as $top10){
			echo "\n";?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits);
			echo ' <img src="'.WASSUPURL.'/img/flags/'.strtolower(esc_attr($top10->top_item)).'.png" alt="" />';?>
			<span class="top10" title="<?php echo $top10->top_item;?>"><?php echo esc_attr($top10->top_item);?></span></nobr></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	}// end if toplocale
		
	//#output top visitors
	$cwidth=0;
	$top_results=array();
	if($top_ten['topvisitor']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("visitor",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		elseif($cols==$col_count-1) echo 'class="lastcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth= (int)(1.5*$min_width);
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP VISITOR", "wassup"); ?></li><?php 
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results)>0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) { 
			if ($top10->visitor_type == "1loggedin_user")
				$uclass=" userslogged";
			elseif ($top10->visitor_type == "2comment_author")
				$uclass=" users";
			else
				$uclass="";
			echo "\n"; ?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits).' <span class="top10'.$uclass.'" title="'.esc_attr($top10->top_item).'">'.stringShortener($top10->top_item, $char_len).'</span>'; ?></nobr></li><?php
			$i++;
		} //end loop
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	} // end if topvisitor

	//#output top article (post|page by id)
	$cwidth=0;
	$top_results=array();
	if($top_ten['toppostid']==1){
		echo "\n<!--heartbeat-->\n";
		$top_results=get_wassupstat("postid",$top_limit,$top_condition);
?>
	<td<?php
		if($cols==0) echo ' class="firstcol"';
		elseif($cols==$col_count-1) echo 'class="lastcol"';
		if(!empty($top_results) && count($top_results) >0){
			$cwidth=2*$min_width;
			echo ' style="min-width:'.$cwidth.'px"';
		}?>>
		<ul class="charts">
		<li class="chartsT"><?php _e("TOP ARTICLE", "wassup"); ?></li><?php
		$i=0;
		$ndigits=1;
		if (!empty($top_results) && count($top_results) >0) {
			$ndigits = strlen("{$top_results[0]->top_count}");
		foreach ($top_results as $top10) {
			echo "\n"; ?>
			<li class="wassup-nowrap"><nobr><?php echo wPadNum($top10->top_count,$ndigits);
			echo ' <a href="'.wassupURI::add_siteurl($top10->top_link).'" target="_BLANK" title="'.$top10->top_item.'">'.stringShortener($top10->top_item,$char_len).'</a>'; ?> </nobr></li><?php
			$i++;
		}
		}
		wListFiller($i,$top_limit,""); ?>
		</ul>
	</td><?php
		$cols+=1;
	}
?>
	</tr>
	</tbody></table>
	<span style="font-size:7pt;"> <?php 
	if ($wassup_options->wassup_spamcheck == 1 || !empty($top_ten['top_nospider'])) { ?><br/>*<?php
		if ($wassup_options->wassup_spamcheck == 1 && !empty($top_ten['top_nospider'])) {
			_e("This report excludes spam and spider records","wassup");
		} elseif (!empty($top_ten['top_nospider'])) {
			_e("This report excludes spider records","wassup");
		} else {
			_e("This report excludes spam records","wassup");
		}
	}?>
	</span><?php
	if(!empty($wdebug_mode)) echo "\n<br/> Res=$res &nbsp; char_len=$char_len \n";?>
	</div> <?php
} //end wassup_top10view

function wListFiller($li_count=0,$li_limit=10,$li_class="charts") {
	//finish a list with empty <li>'s for styling consistency 
	if ($li_count < $li_limit) {
		for ($i=$li_count; $i<$li_limit; $i++) { 
			echo "\n"; ?>
		<li class="<?php echo $li_class; ?>">&nbsp; &nbsp;</li><?php
		}
	}
} //end wListFiller
/*
 * print a table column with line number rows from 1 to "$top_limit"
 * @param integer
 * @output html
 * @return none
 */
function wPrintRowNums($top_limit=10) {
	$ndigits = strlen("{$top_limit}");
	echo "\n"; ?>
		<td style="min-width:8px;">
		<ul class="charts rownums">
		<li class="chartsT">&nbsp;</li><?php
	for ($i=1; $i<= $top_limit; $i++) {
		echo "\n"; ?>
		<li class="charts"><nobr><?php echo wPadNum($i, $ndigits); ?></nobr></li><?php
	} ?>
		</td><?php
} //end function

/**
 * return html code to pad an integer ($li_number) with spaces to match a
 * width of $li_width
 * @param integer (2)
 * @return string (html)
 */
function wPadNum($li_number, $li_width=1) {
	$numstr = (int)$li_number;
	$ndigits = strlen("$numstr");
	$padding = '';
	if ($ndigits < $li_width) {
		for ($i=$ndigits; $i < $li_width; $i++) $padding .= '&nbsp;';
	}
	$padhtml = '<span class="fixed">'."$padding{$numstr}</span>";
	return ($padhtml);
}

/** round the integer to the next near 10 */
function roundup($value) {
	//$dg = digit_count($value);
	$numstr = (int)$value;
	$dg = strlen("$numstr");
	if ($dg <= 2) {
		$dg = 1;
	} else {
		$dg = ($dg-2);
	}
	return (ceil(intval($value)/pow(10, $dg))*pow(10, $dg)+pow(10, $dg));
}

/**
 * Google line chart setup script
 *  - Port of JavaScript from http://code.google.com/apis/chart/ - http://james.cridland.net/code
 */
function Gchart_data($Wvisits, $pages=null, $atime=null, $type, $charttype=null, $axes=null, $chart_loc=null) {
	global $wdebug_mode;
	$chartAPIdata = false;
   // First, find the maximum value from the values given
   if ($axes == 1) {
	$maxValue = roundup(max(array_merge($Wvisits, $pages)));
	//$maxValue = roundup(max($Wvisits));
	$halfValue = ($maxValue/2); 
	$maxPage = $maxValue;
   } else {
	$maxValue = roundup(max($Wvisits));
	$halfValue = ($maxValue/2);
	$maxPage = roundup(max($pages));
	$halfPage = ($maxPage/2);
   }
   // A list of encoding characters to help later, as per Google's example
   $simpleEncoding = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
   $chartData = "s:";
	// Chart type has two datasets
	if ($charttype == "main") {
		$label_time = "";
		for ($i = 0; $i < count($Wvisits); $i++) {
			$currentValue = $Wvisits[$i];
			$currentTime = $atime[$i];
			$label_time.=str_replace(' ', '+', $currentTime)."|";
			if ($currentValue > -1) {
				$chartData.=substr($simpleEncoding,61*($currentValue/$maxValue),1);
			} else {
				$chartData.='_';
			}
		}
		//no x-axis labels in widgets
		if ($chart_loc == "dashboard" || $chart_loc == "widget"){
			$label_time="|";
		}
		// Add pageviews line to the chart
		if (count($pages) != 0) {
			$chartData.=",";
			for ($i = 0; $i < count($pages); $i++) {
				$currentPage = $pages[$i];
				$currentTime = $atime[$i];
				if ($currentPage > -1) {
					$chartData.=substr($simpleEncoding,61*($currentPage/$maxPage),1);
				} else {
					$chartData.='_';
				}
			}
		}
		// Return the chart data - and let the Y axis to show the maximum value
   		if ($axes == 1) {
			$chartAPIdata=$chartData."&chxt=x,y&chxl=0:|".$label_time."1:|0|".$halfValue."|".$maxValue."&chxs=0,6b6b6b,9";
		} else {
			$chartAPIdata=$chartData."&chxt=x,y,r&chxl=0:|".$label_time."1:|0|".$halfValue."|".$maxValue."|2:|0|".$halfPage."|".$maxPage."&chxs=0,6b6b6b,9";
		}
	// Chart type has one one dataset
	// It's unused now
	} else {
		for ($i = 0; $i < count($Wvisits); $i++) {
			$currentValue = $Wvisits[$i];
			$currentTime = $atime[$i];
			$label_time.=str_replace(' ', '+', $currentTime)."|";
			if ($currentValue > -1) {
				$chartData.=substr($simpleEncoding,61*($currentValue/$maxValue),1);
			} else {
				$chartData.='_';
			}
		}
		$chartAPIdata=$chartData."&chxt=x,y&chxl=0:|".$label_time."|1:|0|".$halfValue."|".$maxValue."&chxs=0,6b6b6b,9";
	}
	return $chartAPIdata;
} //end Gchart_data

/**
 * Class for main visitors details queries.
 * - Calculates views/visitors, extracts data for display, and outputs chart
 */
class WassupItems {
        var $tableName;
        var $from_date;
        var $to_date;
        var $searchString;
        var $_whereis;	//private,protected
        var $ItemsType;
        var $Limit;
        var $Last;
	var $WpUrl;
	var $totrecords=0;
	function wassupitems($table_name,$date_from,$date_to,$whereis=null,$limit=null) {
		global $wpdb,$wassup_options,$wdebug_mode;
		if (empty($wassup_options->wassup_table)) $wassup_options = new wassupOptions;
		$wassup_table = $wassup_options->wassup_table;
		$wassup_tmp_table = $wassup_table."_tmp";
		if(!empty($table_name) && wassupDb::table_exists($table_name)) $this->tableName=$table_name;
		else $this->tableName=$wassup_table;
		$wassup_user_settings=get_user_option('_wassup_settings');
		$datenow = current_time('timestamp');
		$to_date=0;
		$from_date=0;
		//use default range from wassup_settings
		if($date_from == "" || !is_numeric($date_from)){
			if(empty($whereis)){
			if(empty($date_to) || !is_numeric($date_to)){
				if($table_name == $wassup_tmp_table){
					$from_date =  $datenow - 3*60; //-3 minutes
				}else{
					if(!empty($wassup_user_settings['detail_time_period'])) $last=$wassup_user_settings['detail_time_period'];
					else $last = $wassup_options->wassup_time_period;
					$from_date = $datenow - (int)(($last*24)*3600);
				}
				$to_date=$datenow;
			}else{
				$to_date = $date_to;
			}
			}
		}else{
			$from_date = $date_from;
		}
		$this->tableName = $table_name;
		$this->from_date = $from_date;
		$this->to_date = $to_date;
		if(empty($limit)|| strpos($limit,"LIMIT")===false){
			if(!is_numeric($limit)){
				if(!empty($wassup_user_settings['detail_limit'])) $limit=$wassup_user_settings['detail_limit'];
				else $limit=$wassup_options->wassup_default_limit;
			}
			$this->Limit="LIMIT ".(int)$limit;
		}else{
			$this->Limit=esc_attr($limit);
		}
		//this->_whereis replaces to_date/from_date in where condition so multisite blog_id can be added to where condition @since v1.9
		if (!empty($whereis)){
			if (preg_match('/^\s*(AND|OR)/i',$whereis)>0){
				if(!empty($from_date)){
					if(empty($to_date) || ($datenow -$to_date)<10 || $from_date >= $to_date){
						$this->_whereis = sprintf("`timestamp`>='%d' %s",$from_date,$whereis);
					}else{
						$this->_whereis=sprintf("`timestamp` BETWEEN '%d' AND '%d' %s",$from_date,$to_date,$whereis);
					}
				}elseif(!empty($to_date)){
					$this->_whereis=sprintf("`timestamp` <= '%d' %s",$to_date,$whereis);
				}else{
					$this->_whereis=sprintf("`timestamp` >'0' %s",$whereis);
				}
			}else{
				$this->_whereis=$whereis;
			}
		}else{
			if(!empty($from_date)){
				if(empty($to_date) || ($datenow -$to_date)<10 || $from_date >= $to_date){
					$this->_whereis=sprintf("`timestamp`>='%d'",$from_date);
				}else{
					$this->_whereis=sprintf("`timestamp` BETWEEN '%d' AND '%d'",$from_date,$to_date);
				}
			}elseif(!empty($to_date)){
				$this->_whereis=sprintf("`timestamp` <= '%d'",$to_date);
			}else{
				$this->_whereis="`timestamp` >'0'";
			}
			//add multisite condition only when there is no 'whereis' parameter
			if($wassup_options->network_activated_plugin()){
				if(!is_network_admin() && !empty($GLOBALS['current_blog']->blog_id)){
					$this->_whereis .=sprintf(" AND `subsite_id`=%d",(int)$GLOBALS['current_blog']->blog_id);
				}
			}
		}
		$totrecords=$wpdb->get_var(sprintf("SELECT count(*) from %s WHERE %s",esc_attr($this->tableName),$this->_whereis));
		if(is_wp_error($totrecords)){
			$error_msg=" MySQL error#".$totrecords->get_error_code()." ".$totrecords->get_error_message();
		}elseif(is_numeric($totrecords)){
			$this->totrecords=$totrecords;
		}
		if($wdebug_mode){
			echo "\n<!-- ";
			echo "\n WassupItems: _whereis=$this->_whereis";
			echo "\n WassupItems: totrecords={$this->totrecords}";
			if(!empty($error_msg)) "\n ERROR: ".error_msg;
			echo "\n -->";
		}
	}
	// Function to show main query and count items
	function calc_tot($Type,$Search="",$specific_where_clause=null,$distinct_type=null){
		global $wpdb,$current_user,$wdebug_mode;
		//get/set user-specific wassup_settings
		if(!is_object($current_user) || empty($current_user->ID)) wp_get_current_user();
		$wassup_user_settings=get_user_option('_wassup_settings',$current_user->ID);
		$this->ItemsType=$Type;
		$this->searchString=$Search;
		$ss="";
		if(!empty($Search)|| !empty($specific_where_clause)){
			$ss=$this->buildSearch($Search,$specific_where_clause);
		}
		if(!empty($ss) && stristr($this->_whereis, ' OR ')!==false){
			$whereis= '('.$this->_whereis.')'.$ss;
		}else{
			$whereis= $this->_whereis . $ss;
		}
		//abort if there is nothing in totrecords var
		if(empty($this->totrecords) || !is_numeric($this->totrecords)){
			return;
		}
		// Switch by every (global) items type (visits, pageviews, spams, etc...)
		switch ($Type) {
		// This is the MAIN query to show the chronology
		case "main":
			//New in v1.9.4: use temporary table to help speed up retrieval of large datasets
			$bigdata=false;
			$totrecords=$wpdb->get_var("SELECT COUNT(*) FROM $this->tableName");
			if($totrecords >50000) $bigdata=true;
			//main query
			if($bigdata){
				//extend PHP and MySql timeouts to prevent script hangs
				$stimeout=ini_get("max_execution_time");
				if(is_numeric($stimeout) && $stimeout >0 && $stimeout <180){
					$disabled_funcs=ini_get('disable_functions');
					if((empty($disabled_funcs) || strpos($disabled_funcs,'set_time_limit')===false) && !ini_get('safe_mode')){
						@set_time_limit(3*60);
					}
				}
				$mtimeout=$wpdb->get_var("SELECT @@session.wait_timeout AS mtimeout FROM dual");
				if(is_numeric($mtimeout) && $mtimeout<160) $result=$wpdb->query("SET wait_timeout=160");
				//use a temporary table for large datasets 
				$tmptable='_wassup_'.$current_user->user_login.rand();
				//create temp table of records
				$qry1 = sprintf("CREATE TEMPORARY TABLE IF NOT EXISTS %s AS (SELECT `wassup_id`, max(`timestamp`) as max_timestamp, min(`timestamp`) as min_timestamp, count(`wassup_id`) as page_hits, GROUP_CONCAT(DISTINCT `username` ORDER BY `username` SEPARATOR '| ') AS login_name, max(`spam`) AS malware_type, max(`screen_res`) as resolution FROM %s WHERE %s GROUP BY `wassup_id` ORDER BY max_timestamp DESC %s); ",
					$tmptable,
					$this->tableName,
					$whereis,
					$this->Limit);
				$results = $wpdb->query($qry1);
				//get detail data using temp table
				if(!is_wp_error($results)){
					$qry2 = sprintf("SELECT a1.*, b1.id, b1.timestamp, b1.ip, b1.hostname, b1.referrer, b1.comment_author, b1.agent, b1.browser, b1.os, b1.spider, b1.feed, b1.language, b1.search, b1.searchengine, b1.searchpage, c1.urlrequested, c1.url_wpid FROM %1\$s a1, %2\$s b1, %2\$s c1 WHERE b1.wassup_id = a1.wassup_id AND b1.timestamp = (SELECT MIN(b2.timestamp) FROM %2\$s b2 WHERE b2.wassup_id = b1.wassup_id) AND c1.wassup_id = a1.wassup_id AND c1.timestamp = (SELECT MAX(c2.timestamp) FROM %2\$s c2 WHERE c2.wassup_id = c1.wassup_id); ",
						$tmptable,
						$this->tableName);
					$results = $wpdb->get_results($qry2);
				}
			}
			//old query fall back for small dataset or if error
			if(!$bigdata || is_wp_error($results) || empty($results) || !is_array($results)){
				$qry = sprintf("SELECT `wassup_id`, max(`timestamp`) as max_timestamp, min(`timestamp`) as min_timestamp, count(`wassup_id`) as page_hits, GROUP_CONCAT(DISTINCT `username` ORDER BY `username` SEPARATOR '| ') AS login_name, max(`spam`) AS malware_type, `id`, `ip`, `hostname`, `urlrequested`, `referrer`, `comment_author`, `agent`, `browser`, `os`, `spider`, `feed`, max(`screen_res`) as resolution, `language`, `search`, `searchengine`, `searchpage`, `url_wpid` FROM `%s` WHERE %s GROUP BY `wassup_id` ORDER BY max_timestamp DESC %s",
					$this->tableName,
					$whereis,
					$this->Limit);
				$results = $wpdb->get_results($qry);
			}
			break;
		case "count":
			// These are the queries to count the items hits/pages/spam
			$distinct="";
			if($distinct_type=="DISTINCT") $distinct="DISTINCT ";
			$qry=sprintf("SELECT COUNT(%s`wassup_id`) AS itemstot FROM %s WHERE %s", $distinct, $this->tableName, $whereis);
			$results = $wpdb->get_var($qry);
			break;
		case "main-ip":		//TODO
			// These are the queries to count the hits/pages/spam by ip
			$qry=sprintf("SELECT *, max(`timestamp`) as max_timestamp, min(`timestamp`) as min_timestamp, count(`ip`) AS page_hits, GROUP_CONCAT(DISTINCT `wassup_id` ORDER BY `wassup_id` SEPARATOR ',') AS visits FROM %s WHERE %s GROUP BY `ip` ORDER BY max_timestamp DESC %s",
					$this->tableName,
					$whereis,
					$this->Limit);
			$results = $wpdb->get_results($qry);
			break;
		case "count-ip":	//TODO
			// These are the queries to count the hits/pages/spam by ip
			$distinct="";
			if($distinct_type=="DISTINCT") $distinct="DISTINCT ";
			$qry = sprintf("SELECT COUNT(%s`ip`) AS itemstot FROM %s WHERE %s", $distinct, $this->tableName, $whereis);
			$results = $wpdb->get_var($qry);
			break;
		} //end switch
		if (is_wp_error($results)){
			$error_msg=" calc_tot MySQL error#".$results->get_error_code()." ".$results->get_error_message()."\n qry=".$qry."\n";
			$results=false;
		}elseif(empty($results)){
			$results=false;
		}
		if($wdebug_mode){
			if(!empty($error_msg)){
				echo "\n<!-- WassupItems ERROR: ".error_msg. " -->";
			}elseif(empty($results)){
				echo "\n<!-- WassupItems::calc_tot No results from query -->";
			}elseif(is_array($results)){
				echo "\n<!-- ";
				echo "\n WassupItems::calc_tot ".count($results).' results from query'."\n -->";
			}else {
				echo "\n<!-- WassupItems::calc_tot 1 result from query  results=$results -->";
			}
		}
		return $results;
	} //end function calc_tot

	//Build the "search" portion of a MySQL WHERE clause...for Visitor details' mark-ip search or general search
	function buildSearch($Search,$specific_where_clause=null) {
		global $wpdb;
		$ss="";
		if (!empty($Search)) {
			$wassup_user_settings=get_user_option('_wassup_settings');
			$searchString=wassupDb::esc_like(trim($Search));
			$searchParam=esc_sql($searchString);
			//do an IP-only search when Search == wassupOptions::wip
			$wip=(!empty($wassup_user_settings['wip'])? $wassup_user_settings['wip']:0);
			if(!empty($wip)&& $Search==$wip){
				//for IP-only search
				$ss=sprintf(" AND `ip`='%s'",$searchParam);
			//New in v1.9.4: separate url searches
			}elseif(strpos($Search,'/')!==FALSE){
				$ss = sprintf(" AND (`urlrequested` LIKE '%%%s%%' OR `agent` LIKE '%%%s%%' OR `referrer` LIKE '%%%s%%')",
				$searchParam,
				$searchParam,
				$searchParam);
			}else{	//for general search
				$ss = sprintf(" AND (`ip` LIKE '%%%s%%' OR `hostname` LIKE '%%%s%%' OR `urlrequested` LIKE '%%%s%%' OR `agent` LIKE '%%%s%%' OR `referrer` LIKE '%%%s%%' OR `username` LIKE '%s%%' OR `comment_author` LIKE '%s%%')",
				$searchParam,
				$searchParam,
				$searchParam,
				$searchParam,
				$searchParam,
				$searchParam,
				$searchParam);
			}
		}
		if (!empty($specific_where_clause)) {
			$ss .= " ".trim($specific_where_clause);
		}
		return $ss;
	} //end buildSearch

	// $Ctype = chart's type by time
	// $Res = resolution
	// $Search = string to add to where clause
	function TheChart($Ctype, $Res, $chart_height, $Search="", $axes_type, $chart_bg, $chart_loc="page", $chart_group="") {
		global $wpdb,$wassup_options,$wdebug_mode;
		if (is_numeric($Ctype)) $this->Last = $Ctype;
		else $Ctype=1;	// defaults to 24-hour chart
		$chart_points=0;
		$chart_url="";
		//First check for cached chart
		$chart_key="$chart_loc{$Res}{$axes_type}{$chart_group}{$Ctype}_".intval(date('i')/15).date('HdmY');
		if(!empty($Search)) $chart_key .="_s".esc_attr($Search);
		$chart_url=wassupDb::get_wassupmeta($chart_key,'_chart');
		if (!empty($chart_url)) {
			if ($wdebug_mode)
				echo "\n<!-- Cached chart found. chart_key=$chart_key -->\n";
		} else {
			$chart_key = "";
		}
		//Second..create new chart
		if (empty($chart_url)) {
			//Add Search variable to WHERE clause
			$ss="";
			if(!empty($Search)) $ss=$this->buildSearch($Search);
			$whereis= $this->_whereis . $ss;
			$hour_todate = $this->to_date;
		//`timestamp` is localized before insert into table, so datetime translation from MySQL with 'FROM_UNIXTIME' must be converted to UTC/GMT afterwards to get an accurate datetime value for Wordpress.
		$UTCoffset = wassupDb::get_db_setting("tzoffset");
		if (empty($UTCoffset)) $UTCoffset = "+0:00"; //GMT
		else $UTCoffset=wassupDb::format_tzoffset($UTCoffset);
		//set x-axis date format to Wordpress date format
		$USAdate = $wassup_options->is_USAdate();
		$hour_fromdate = $this->from_date;
		$point_label = array();
		$x_divisor=1;
		$x_increment = 3600;	//1 hour increments in timeline
		$x_grid=8.33;
		$x_groupformat = "%Y%m%d%H%i";
		$wp_groupformat = 'YmdHi';
		$cache_time=300; //5-minute cache
		$points_end = current_time('timestamp')+5;
		//variable x-axis timeframe for "All time"
		if($Ctype == "0") {
			$secs=floor(($points_end-$hour_fromdate)/300)*300;
			if($secs<3600){ //up to 1 hour
				$crange=".05";
			}elseif($secs<21600){ //up to 6 hours
				$crange=".25";
			}elseif($secs<86400){ //up to 1 day
				$crange="1";
			}elseif($secs<86400*7){ //up to 1 week
				$crange="7";
			}elseif($secs<86400*14){ //up to 2 weeks
				$crange="14";
			}elseif($secs<86400*31){ //up to 1 month
				$crange="30";
			}elseif($secs<86400*91){ //up to 3 months
				$crange="90";
			}elseif($secs<86400*182){ //up to 6 months
				$crange="180";
			}elseif($secs<86400*366){ //up to 1 year
				$crange="365";
			}else{
				$crange="0";
			}
		}else{
			$crange=$Ctype;
		}
		// Options by chart type
		switch ($crange) {
		case ".05":
		case ".1":
			$cTitle = __("Last 1 Hour", "wassup");
			$x_axes_label = "%H:%i";
			$wp_timeformat = 'H:i';
			$x_points = 12;		//no. of x-axis points
			$x_increment = 300;	//5 minute increments
			$x_divisor = $x_increment;
			$cache_time=90; //1.5-minute cache
			break;
		case ".25":
			$cTitle = __("Last 6 Hours", "wassup");
			$x_axes_label = "%H:%i";
			$wp_timeformat = 'H:i';
			$x_points = 12;
			$x_increment = 30*60;	//30 minute increments
			$x_divisor = $x_increment;
			$cache_time=180;	//3-minute cache
			break;
		case ".5":
			$cTitle = __("Last 12 Hours", "wassup");
			$x_axes_label="%d %H:00";
			$wp_timeformat='d H:00';
			$x_points = 12;
			$x_increment = 60*60;	//1 hour increments
			$x_divisor = $x_increment;
			$cache_time=180;	//3-minute cache
			break;
		case "7":
			$cTitle = __("Last 7 Days", "wassup");
			$x_groupformat = "%Y%m%d";
			$wp_groupformat = 'Ymd';
			if ($USAdate) { 
				$x_axes_label = "%a %b %d";
				$wp_timeformat = 'D M d';
			} else { 
				$x_axes_label = "%a %d %b";
				$wp_timeformat = 'D d M';
			}
			$x_points = 7;
			$x_increment = 24*60*60; //24-hour increments
			break;
		case "14":
			$cTitle = __("Last 2 Weeks", "wassup");
			$x_groupformat = "%Y%m%d";
			$wp_groupformat = 'Ymd';
			if ($USAdate) { 
				$x_axes_label = "%a %b %d";
				$wp_timeformat = 'D M d';
			} else { 
				$x_axes_label = "%a %d %b";
				$wp_timeformat = 'D d M';
			}
			if((int)$Res > 640){
				$x_points = 14;
				$x_increment = 24*60*60; //1-day increments
			}else{
				$x_points = 7;
				$x_increment = 48*60*60; //2-day increments
			}
			break;
		case "30":
			$cTitle = __("Last Month", "wassup");
			$x_groupformat = "%Y%m%d";
			$wp_groupformat = 'Ymd';
			if ($USAdate) { 
				$x_axes_label = " %b %d";
				$wp_timeformat = 'M d';
			} else { 
				$x_axes_label = "%d %b";
				$wp_timeformat = 'd M';
			}
			$x_points = 30; //30
			$x_increment = 24*60*60; //24-hour increments
			break;
		case "90":
			$cTitle = __("Last 3 Months", "wassup");
			$x_groupformat = "%Y%u";
			$wp_groupformat = 'YW';
			if ($USAdate) { 
				$x_axes_label = " %b %d";
				$wp_timeformat = 'M d';
			} else { 
				$x_axes_label = "%d %b";
				$wp_timeformat = 'd M';
			}
			$x_points = 12; //could be 13
			$x_increment = 24*3600*7; //1-week increments
			break;
		case "180":
			$cTitle = __("Last 6 Months", "wassup");
			$x_groupformat = "%Y%m";
			$wp_groupformat = 'Ym';
			$wp_timeformat='M Y';
			$x_axes_label = " %b %Y";
			$x_points=6;
			break;
		case "365":
			$cTitle = __("Last Year", "wassup");
			$x_groupformat = "%Y%m";
			$wp_groupformat = 'Ym';
			$wp_timeformat='M Y';
			$x_axes_label = "%b %Y";
			$x_points=12;
			break;
		case "0":
			$cTitle = __("All Time", "wassup");
			$x_groupformat = "%Y%m";
			$x_axes_label = "%b %Y";
			$x_points = 0; //unknown number of x-axis points
			break;
		case "1":
		default:
			$cTitle = __("Last 24 Hours", "wassup");
			$x_groupformat = "%Y%m%d%H";
			$wp_groupformat = 'YmdH';
			$x_axes_label = "%H:00";
			$wp_timeformat = 'H:00';
			$x_points = 12;		//no. of x-axis points
			$x_increment = 2*60*60;	//2-hour increments
			$x_divisor = $x_increment;
		}
		if($Ctype == "0") $cTitle=__("All Time","wassup");

		//create Wordpress labels to replace the MySQL x-axis labels which could be incorrect due to PHP/MySQL timezone mismatch issues
		if ($x_points >0 && $hour_fromdate >0) {
			//$points_end = current_time('timestamp')+60; 
			for ($i=0;$i<$x_points;$i++) {
				$x_timestamp=((int)(($hour_fromdate+(($i+1)*$x_increment))/$x_divisor))*$x_divisor;
				if ($x_timestamp < $points_end) {
					if ($x_divisor > 1) {
						$tgroup[] = $x_timestamp;
					} else {
						$tgroup[] = gmdate($wp_groupformat,$x_timestamp);
					}
					$tlabel[] = gmdate($wp_timeformat,$x_timestamp);
				}
			}
			if ($wdebug_mode) {
				echo "\n<!-- \$x-points= ".implode("|",$tlabel)."\n";
				echo " \$tgroup=".implode("|",$tgroup)."-->";
			}
		}
		if ($x_divisor > 1) {
		$qry = sprintf("SELECT COUNT( DISTINCT `wassup_id` ) AS items, COUNT(`wassup_id`) AS pages, CAST(`timestamp`/$x_divisor AS UNSIGNED)*$x_divisor AS xgroup, DATE_FORMAT(DATE_ADD('1970-01-01 00:00:00', INTERVAL CAST(`timestamp` AS UNSIGNED) SECOND), '%s') as thedate FROM %s WHERE %s GROUP BY 3 ORDER BY `timestamp`",
			$x_axes_label,
			$this->tableName,
			$whereis); 
		} else {
		$qry = sprintf("SELECT COUNT( DISTINCT `wassup_id` ) AS items, COUNT(`wassup_id`) AS pages, DATE_FORMAT(DATE_ADD('1970-01-01 00:00:00', INTERVAL CAST(`timestamp` AS UNSIGNED) SECOND), '%s') AS xgroup, DATE_FORMAT(DATE_ADD('1970-01-01 00:00:00', INTERVAL CAST(`timestamp` AS UNSIGNED) SECOND), '%s') as thedate FROM %s WHERE %s GROUP BY 3 ORDER BY `timestamp`",
			$x_groupformat,
			$x_axes_label,
			$this->tableName,
			$whereis); 
		}
		$qry_result = $wpdb->get_results($qry,ARRAY_A);
		if(is_wp_error($qry_result)){
			$error_msg=" theChart MySQL error#".$qry_result->get_error_code()." ".$qry_result->get_error_message()."\n qry=".esc_attr($qry)."\n";
		}else{
			$chart_points = count($qry_result);
		}
		if ($wdebug_mode) {
			if(!empty($error_msg)) echo "\n<!-- WassupItems ERROR: ".error_msg. " -->";

			else echo "\n<!-- \$query= $qry-->\n";
		}
		// Extract arrays for Visits, Pages and X_Axis_Label
		if ($chart_points > 0) {
			//MySQL results have sufficient data points
			if ($chart_points >= $x_points-1 || empty($tlabel)) {
				//use MySQL labels
				foreach ($qry_result as $bhits) {
					$y_hits[] = $bhits['items'];
					$y_pages[] = $bhits['pages'];
					$x_label[] = $bhits['thedate'];
					$x_group[] = $bhits['xgroup']; //debug
				}
			//MySQL results have missing data because of zero
			// hits in timeline...manually insert missing zeros
			} else {
				//combine Wordpress & MySQL labels
				$i=0;
				foreach ($qry_result as $bhits) {
					while ($i <= $x_points-1 && $bhits['xgroup'] > $tgroup[$i]) {
						//add 0-points to data
						$y_hits[] = 0;
						$y_pages[] = 0;
						$x_label[] = $tlabel[$i];
						$i=$i+1;
					}
					$y_hits[] = $bhits['items'];
					$y_pages[] = $bhits['pages'];
					$x_label[] = $bhits['thedate'];
					$x_group[] = $bhits['xgroup']; //debug
					$i = $i+1;
				}
			}
			if ($wdebug_mode) {
				echo "\n<!-- \$x-group= ".implode("|",$x_group);
				echo "\n \$x-labels= ".implode("|",$x_label)."-->\n";
			}
			//change chart grid if number of x-axis points!=12
			$lablcount = count($x_label)-1;
			if ($lablcount == 7 || $lablcount == 14) {
				$x_grid=7.15;
			} elseif ($lablcount == 6) {	//5?
				$x_grid=10;
			} elseif ($lablcount == 9) {
				$x_grid=9.1;	//1 year, 6 hours
			} elseif ($lablcount == 11) {
				$x_grid=9.1;
			} elseif ($lablcount == 13) {
				$x_grid=7.7;	//90 days
			} elseif ($lablcount == 23) {
				$x_grid=8.67;	//24 hours
			} elseif ($lablcount == 31) {
				$x_grid=6.45;
			}
			//TODO: Google image chart api deprecated as of 4/20/2012 - replace with Google interactive charts api
			// generate url for google chart image 
			$chart_url ="https://chart.googleapis.com/chart?cht=lc&chf=".$chart_bg."&chtt=".urlencode($cTitle)."&chls=4,1,0|2,6,2&chco=1111dd,FF6D06&chm=B,1111dd30,0,0,0&chg={$x_grid},25,1,5&chs={$Res}x{$chart_height}&chd=".Gchart_data($y_hits, $y_pages, $x_label, $x_groupformat, "main", $axes_type, $chart_loc);
			//cache chart url in wassup_meta table for up to 5 minutes
			$chart_key="$chart_loc{$Res}{$axes_type}{$chart_group}{$Ctype}_".intval(date('i')/15).date('HdmY');
			if(!empty($Search)) $chart_key .="_s".esc_attr($Search);
			$expire=(int)(time()+$cache_time);
			$cache_id=wassupDb::save_wassupmeta($chart_key,'_chart',"$chart_url",$expire);
		} //end if chart_points>0
		} //end if chart_url
		if (!empty($chart_url)) return $chart_url;
		else return false;
	} //end theChart
} //end class WassupItems

/** 
 * A class for wassup CURL operations.
 * @since v1.8
 */
class wcURL {
	var $data = array();
	function doRequest($method, $url, $vars) {
		if (function_exists('curl_init')) {
			$wassup_agent = apply_filters('http_headers_useragent',"WassUp/".WASSUPVERSION." - www.wpwp.org");
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false); //data only
			curl_setopt($ch, CURLOPT_USERAGENT, $wassup_agent);
  			curl_setopt($ch, CURLOPT_ENCODING, "");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 7); //don't wait for slow responses
			if (ini_get('open_basedir')=="") { //causes error
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			}
			if ($method == 'POST') {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
			}
			$data = curl_exec($ch);
			$this->data = curl_getinfo($ch);
			$this->data['content'] = $data;
			$this->data['error'] = curl_error($ch);
			curl_close($ch);
			if (($this->data['error'] == '') && ($this->data['http_code'] < 400)) return true;
			else return false;
		} else {
			return false;
		}
	} //end doRequest
	function get($url){return $this->doRequest('GET',$url,'NULL');}
	// vars is urlencoded string of field/value pairs, eg:field1=value1&field2=value2
	function post($url,$vars){return $this->doRequest('POST', $url, $vars);}
	function getInfo($field){
		if (isset($this->data[$field])) return $this->data[$field];
		else return null;
	}
	function getData(){return $this->data['content'];}
} //end class wcURL

/**
 * Retrieve data from a web service API via a url query
 * @access public
 * @param string
 * @return string
 * @since v1.8
 */
function wFetchAPIData($api_url) {
	global $wdebug_mode;
	$wassup_agent=apply_filters('http_headers_useragent',"WassUp/".WASSUPVERSION." - www.wpwp.org");
	$apidata=array();
	//timeout now set in http/curl settings, not via 'set_time_limit' which does not apply to remote requests @since v1.9.1
	//try Wordpress 'wp_remote_get' for api results
	if(function_exists('wp_remote_get')){
		$opts=array('user-agent'=>"$wassup_agent",'timeout'=>5);
		$api_remote=@wp_remote_get($api_url,$opts);
		if(!empty($api_remote) && is_array($api_remote)){
			if(!empty($api_remote['body'])) $apidata=$api_remote['body'];
			elseif(!empty($api_remote['response'])) $apidata="no data";
		}
		$api_method='wp_remote_get';	//debug
	}
	//try cURL extension to get api results
	if (empty($apidata)) {
		$curl = new wcURL;
		if ($curl->get($api_url)) {
			$apidata = $curl->getData();
		}
		$api_method='wcURL';	//debug
	} 
	// try 'file_get_contents' to get api results
	if(empty($apidata) && ini_get('allow_url_fopen')){
		// context stream compatible with PHP 5.0.0+
		if (version_compare(PHP_VERSION,"5.0.0",">=")) {
			$opts=array('http'=>array(
					'method'=>"GET",
					'user_agent'=>"$wassup_agent",
					'max_redirects'=>"0",
					'timeout'=>"5.0",
				   ));
			$context = stream_context_create($opts);
			// Open file using HTTP headers set above
			$apidata = @file_get_contents($api_url, false, $context);
		} else {
			$apidata = @file_get_contents($api_url, false);
		}
		$api_method='file_get_contents';	//debug
	}
	if ($wdebug_mode) {
		echo "\n<!-- <br>API Fetch using $api_method data: "; //debug
		print_r($apidata);
		echo "-->\n";
	}
	//if(!empty($stimeout)) @set_time_limit($stimeout); //no need to reset this
	return $apidata;
} //end wFetchAPIData
?>

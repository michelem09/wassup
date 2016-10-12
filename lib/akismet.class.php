<?php
/**
 * For Akismet spam check on Wassup visitor records.
 *
 * @package WassUp Real-time Analytics
 * @subpackage akismet.class.php module
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
//Classes and constants renamed for compatibility with Akismet v3.0 -Helene D. @since v1.9
/**
 * 08.11.2010 22:25:17est
 * 
 * Akismet PHP4 class
 * 
 * <b>Usage</b>
 * <code>
 *    $comment = array(
 *           'author'    => 'viagra-test-123',
 *           'email'     => 'test@example.com',
 *           'website'   => 'http://www.example.com/',
 *           'body'      => 'This is a test comment',
 *           'permalink' => 'http://yourdomain.com/yourblogpost.url',
 *        );
 *
 *    $akismet = new Akismet('http://www.yourdomain.com/', 'YOUR_WORDPRESS_API_KEY', $comment);
 *
 *    if($akismet->errorsExist()) {
 *        echo"Couldn't connected to Akismet server!";
 *    } else {
 *        if($akismet->isSpam()) {
 *            echo"Spam detected";
 *        } else {
 *            echo"yay, no spam!";
 *        }
 *    }
 * </code>
 * 
 * @author Bret Kuhns {@link www.bretkuhns.com}
 * @link http://code.google.com/p/akismet-php4
 * @version 0.3.5
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
// Error constants
define("WASSUP_AKISMET_SERVER_NOT_FOUND",	0);
define("WASSUP_AKISMET_RESPONSE_FAILED",	1);
define("WASSUP_AKISMET_INVALID_KEY",		2);

/** Base class to assist in error handling between Akismet classes */
class wassup_AkismetObject {
	var $errors = array();
	function setError($name,$message){$this->errors[$name]=$message;}
	function getError($name){
		if($this->isError($name)){return $this->errors[$name];}
		else {return false;}
	}
	function getErrors(){return (array)$this->errors;}
	function isError($name){return isset($this->errors[$name]);}
	function errorsExist(){return (count($this->errors)>0);}
	//remove timeout error @since v1.9
	function removeError($name,$message){
		if(!empty($this->errors[$name])&& $this->errors[$name]==$message)unset($this->errors[$name]);
	}
}
/** Class to communicate with Akismet service */
class wassup_AkismetHttpClient extends wassup_AkismetObject {
	var $akismetVersion='1.1';
	var $con;
	var $host;
	var $port;
	var $apiKey;
	var $blogUrl;
	var $errors=array();
	
	/** Constructor */
	function wassup_AkismetHttpClient($host,$blogUrl,$apiKey,$port=80){
		$this->host=$host;
		$this->port=$port;
		$this->blogUrl=$blogUrl;
		$this->apiKey=$apiKey;
	}
	/** Use the connection active in $con to get a response from the server and return that response */
	function getResponse($request,$path,$type="post",$responseLength=1160){
		$this->_connect();
		if($this->con && !$this->isError(WASSUP_AKISMET_SERVER_NOT_FOUND)){
			$request=strToUpper($type)." /{$this->akismetVersion}/$path HTTP/1.0\r\n" .
				"Host: ".((!empty($this->apiKey)) ? $this->apiKey."." : null)."{$this->host}\r\n" .
				"Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n" .
				"Content-Length: ".strlen($request)."\r\n" .
				"User-Agent: Wassup ".WASSUPVERSION." Akismet PHP4 Class\r\n" .
				"\r\n".$request;
			$response="";
			@fwrite($this->con,$request);
			//don't wait for slow server @since v1.9.1
			stream_set_timeout($this->con,5);
			$info=stream_get_meta_data($this->con);
			while(!feof($this->con) && !$info['timed_out']){
				$response .= @fgets($this->con,$responseLength);
				$info=stream_get_meta_data($this->con);
			}
			//timeout error message @since  v1.9.1
			if(!empty($response)){
				$response=explode("\r\n\r\n",$response,2);
				return $response[1];
			}elseif($info['timed_out']){
				$this->setError(WASSUP_AKISMET_RESPONSE_FAILED,__("Timed out waiting for server response.","wassup"));
			}else{
				$this->setError(WASSUP_AKISMET_RESPONSE_FAILED, __("The response could not be retrieved.","wassup"));
			}
		}else{
			$this->setError(WASSUP_AKISMET_RESPONSE_FAILED, __("The response could not be retrieved.","wassup"));
		}
		$this->_disconnect();
	}
	/** Connect to the Akismet server and store that connection in the instance variable $con */
	function _connect(){
		if(!($this->con=@fsockopen($this->host,$this->port))){
			$this->setError(WASSUP_AKISMET_SERVER_NOT_FOUND,__("Could not connect to Akismet server.","wassup"));
		}
	}
	/** Close the connection to the Akismet server */
	function _disconnect(){@fclose($this->con);}
} //end Class

/**
 * The controlling class.
 * This is the ONLY class the user should instantiate in order to use the Akismet service!
 */
class wassup_Akismet extends wassup_AkismetObject {
	var $apiPort=80;
	var $akismetServer='rest.akismet.com';
	var $akismetVersion='1.1';
	var $http;
	var $ignore=array(
			'HTTP_COOKIE',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED_HOST',
			'HTTP_MAX_FORWARDS',
			'HTTP_X_FORWARDED_SERVER',
			'REDIRECT_STATUS',
			'SERVER_PORT',
			'PATH',
			'DOCUMENT_ROOT',
			'SERVER_ADMIN',
			'QUERY_STRING',
			'PHP_SELF',
			'argv');
	var $blogUrl="";
	var $apiKey ="";
	var $comment=array();
	
	/**
	 * Constructor
	 * Set instance variables, connect to Akismet, check API key
	 * 
	 * @param String $blogUrl	- The URL to your own blog
	 * @param  String $apiKey	- Your wordpress API key
	 * @param  String[] $comment	- A formatted comment array to be examined by the Akismet service
	 * @return Akismet
	 */
	function wassup_Akismet($blogUrl,$apiKey,$comment=array()) {
		$this->blogUrl=$blogUrl;
		$this->apiKey =$apiKey;
		$this->setComment($comment);
		// Connect to the Akismet server and populate errors if they exist
		$this->http=new wassup_AkismetHttpClient($this->akismetServer,$blogUrl,$apiKey);
		if($this->http->errorsExist()) {
			$this->errors = array_merge($this->errors, $this->http->getErrors());
		}
		// Check if the API key is valid
		if(!$this->_isValidApiKey($apiKey)){
			$this->setError(WASSUP_AKISMET_INVALID_KEY,__("Your Akismet API key is not valid.","wassup"));
		}
	}
	/** Query Akismet server to check if comment is spam or not */
	function isSpam() {
		$response=$this->http->getResponse($this->_getQueryString(), 'comment-check');
		return ($response=="true");
	}
	/** Submit comment as an unchecked spam to Akismet server */
	function submitSpam(){
		$this->http->getResponse($this->_getQueryString(),'submit-spam');
	}
	/** Submit a false-positive comment as "ham" to Akismet server */
	function submitHam(){
		$this->http->getResponse($this->_getQueryString(),'submit-ham');
	}
	/** Manually set comment value of the instantiated object */
	function setComment($comment){
		$this->comment = $comment;
		if(!empty($comment)){
			$this->_formatCommentArray();
			$this->_fillCommentValues();
		}
	}
	/** Returns the current value of the object's comment array */
	function getComment(){return $this->comment;}
	/** Confirm valid API key on the Akismet server */
	function _isValidApiKey($key){
		$keyCheck=$this->http->getResponse("key=".$this->apiKey."&blog=".$this->blogUrl,'verify-key');
		return ($keyCheck=="valid");
	}
	/** Format the comment array to match the Akismet API */
	function _formatCommentArray(){
		$format=array(	'type'  =>'comment_type',
				'author'=>'comment_author',
				'email' =>'comment_author_email',
				'website'=>'comment_author_url',
				'body'  =>'comment_content');
		foreach($format as $short=>$long){
			if(isset($this->comment[$short])){
				$this->comment[$long]=$this->comment[$short];
				unset($this->comment[$short]);
			}
		}
	}
	/** Fill comment array field values when possible */
	function _fillCommentValues(){
		if(!isset($this->comment['user_ip'])){
			$this->comment['user_ip']=($_SERVER['REMOTE_ADDR']!=getenv('SERVER_ADDR')) ?$_SERVER['REMOTE_ADDR'] :getenv('HTTP_X_FORWARDED_FOR');
		}
		if(!isset($this->comment['user_agent'])){
			$this->comment['user_agent']=$_SERVER['HTTP_USER_AGENT'];
		}
		if(!isset($this->comment['referrer'])){
			$this->comment['referrer']=$_SERVER['HTTP_REFERER'];
		}
		if(!isset($this->comment['blog'])){
			$this->comment['blog']=$this->blogUrl;
		}
	}
	/** Build a query string for use with HTTP requests */
	function _getQueryString(){
		foreach($_SERVER as $key=>$value){
			if(!in_array($key,$this->ignore)){
				if($key=='REMOTE_ADDR'){
					$this->comment[$key]=$this->comment['user_ip'];
				}else{
					$this->comment[$key]=$value;
				}
			}
		}
		$query_string='';
		foreach($this->comment as $key=>$data){
			$query_string .=$key.'='.urlencode(stripslashes($data)).'&';
		}
		return $query_string;
	}
} //end Class
?>

<?php

// source http://files.directadmin.com/services/all/httpsocket/

namespace App\CustomClass;
/**
 * Socket communication class.
 *
 * Originally designed for use with DirectAdmin's API, this class will fill any HTTP socket need.
 *
 * Very, very basic usage:
 *   $Socket = new DirectAdmin;
 *   echo $Socket->get('http://user:pass@somesite.com/somedir/some.file?query=string&this=that');
 *
 * @author Phi1 'l0rdphi1' Stier <l0rdphi1@liquenox.net>
 * @package DirectAdmin
 * @version 3.0.2
 * 3.0.2
 * added longer curl timeouts
 * 3.0.1
 * support for tcp:// conversion to http://
 * 3.0.0
 * swapped to use curl to address ssl certificate issues with php 5.6
 * 2.7.2
 * added x-use-https header check
 * added max number of location redirects
 * added custom settable message if x-use-https is found, so users can be told where to set their scripts
 * if a redirect host is https, add ssl:// to remote_host
 * 2.7.1
 * added isset to headers['location'], line 306
 */

// Optional, you can pass these as arguments of the class constructor too

class DirectAdmin {

	var $version = '3.0.2';

	/* all vars are private except $error, $query_cache, and $doFollowLocationHeader */

	var $method = 'GET';

	var $remote_host;
	var $remote_port;
	var $remote_uname;
	var $remote_passwd;

	var $result;
	var $result_header;
	var $result_body;
	var $result_status_code;

	var $lastTransferSpeed;

	var $bind_host;

	var $error = array();
	var $warn = array();
	var $query_cache = array();

	var $doFollowLocationHeader = TRUE;
	var $redirectURL;
	var $max_redirects = 5;
	var $ssl_setting_message = 'DirectAdmin appears to be using SSL. Change your script to connect to ssl://';

	var $extra_headers = array();

	/**
	 * Create server "connection".
	 *
	 */
	function connect($host, $port = '' )
	{
		if (!is_numeric($port))
		{
			$port = 80;
		}

		$this->remote_host = $host;
		$this->remote_port = $port;
	}

	function bind( $ip = '' )
	{
		if ( $ip == '' )
		{
			$ip = $_SERVER['SERVER_ADDR'];
		}

		$this->bind_host = $ip;
	}

	/**
	 * Change the method being used to communicate.
	 *
	 * @param string|null request method. supports GET, POST, and HEAD. default is GET
	 */
	function set_method( $method = 'GET' )
	{
		$this->method = strtoupper($method);
	}

	/**
	 * Specify a username and password.
	 *
	 * @param string|null username. defualt is null
	 * @param string|null password. defualt is null
	 */
	function set_login( $uname = '', $passwd = '' )
	{
		if ( strlen($uname) > 0 )
		{
			$this->remote_uname = $uname;
		}

		if ( strlen($passwd) > 0 )
		{
			$this->remote_passwd = $passwd;
		}

	}

	/**
	 * Query the server
	 *
	 * @param string containing properly formatted server API. See DA API docs and examples. Http:// URLs O.K. too.
	 * @param string|array query to pass to url
	 * @param int if connection KB/s drops below value here, will drop connection
	 */
	function query( $request, $content = '', $doSpeedCheck = 0 )
	{
		$this->error = $this->warn = array();
		$this->result_status_code = NULL;

		$is_ssl = FALSE;

		// is our request a http:// ... ?
		if (preg_match('!^http://!i',$request) || preg_match('!^https://!i',$request))
		{
			$location = parse_url($request);
			if (preg_match('!^https://!i',$request))
			{
				$this->connect('https://'.$location['host'],$location['port']);
			}
			else
				$this->connect('http://'.$location['host'],$location['port']);

			$this->set_login($location['user'],$location['pass']);

			$request = $location['path'];
			$content = $location['query'];

			if ( strlen($request) < 1 )
			{
				$request = '/';
			}

		}

		if (preg_match('!^ssl://!i', $this->remote_host))
			$this->remote_host = 'https://'.substr($this->remote_host, 6);

		if (preg_match('!^tcp://!i', $this->remote_host))
			$this->remote_host = 'http://'.substr($this->remote_host, 6);

		if (preg_match('!^https://!i', $this->remote_host))
			$is_ssl = TRUE;

		$array_headers = array(
			'Host' => ( $this->remote_port == 80 ? $this->remote_host : "$this->remote_host:$this->remote_port" ),
			'Accept' => '*/*',
			'Connection' => 'Close' );

		foreach ( $this->extra_headers as $key => $value )
		{
			$array_headers[$key] = $value;
		}

		$this->result = $this->result_header = $this->result_body = '';

		// was content sent as an array? if so, turn it into a string
		if (is_array($content))
		{
			$pairs = array();

			foreach ( $content as $key => $value )
			{
				$pairs[] = "$key=".urlencode($value);
			}

			$content = join('&',$pairs);
			unset($pairs);
		}

		$OK = TRUE;

		if ($this->method == 'GET')
			$request .= '?'.$content;

		$ch = curl_init($this->remote_host.':'.$this->remote_port.$request);

		if ($is_ssl)
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //1
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //2
			//curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
		}

		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_USERAGENT, "DirectAdmin/$this->version");
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 100);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, 1);

		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 512);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 120);

		//if ($this->doFollowLocationHeader)
		//{
		//	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		//	curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
		//}

		// instance connection
		if ($this->bind_host)
		{
			curl_setopt($ch, CURLOPT_INTERFACE, $this->bind_host);
		}

		// if we have a username and password, add the header
		if ( isset($this->remote_uname) && isset($this->remote_passwd) )
		{
			curl_setopt($ch, CURLOPT_USERPWD, $this->remote_uname.':'.$this->remote_passwd);
		}

		// for DA skins: if $this->remote_passwd is NULL, try to use the login key system
		if ( isset($this->remote_uname) && $this->remote_passwd == NULL )
		{
			$array_headers['Cookie'] = "session={$_SERVER['SESSION_ID']}; key={$_SERVER['SESSION_KEY']}";
		}

		// if method is POST, add content length & type headers
		if ( $this->method == 'POST' )
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

			//$array_headers['Content-type'] = 'application/x-www-form-urlencoded';
			$array_headers['Content-length'] = strlen($content);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $array_headers);


		if( !($this->result = curl_exec($ch)) )
		{
			$this->error[] .= curl_error($ch);
			$OK = FALSE;
		}

		$header_size			= curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$this->result_header	= substr($this->result, 0, $header_size);
		$this->result_body		= substr($this->result, $header_size);
		$this->result_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$this->lastTransferSpeed = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD) / 1024;

		curl_close($ch);

		$this->query_cache[] = $this->remote_host.':'.$this->remote_port.$request;

		$headers = $this->fetch_header();

		// did we get the full file?
		if ( !empty($headers['content-length']) && $headers['content-length'] != strlen($this->result_body) )
		{
			$this->result_status_code = 206;
		}

		// now, if we're being passed a location header, should we follow it?
		if ($this->doFollowLocationHeader)
		{
			//dont bother if we didn't even setup the script correctly
			if (isset($headers['x-use-https']) && $headers['x-use-https']=='yes')
				die($this->ssl_setting_message);

			if (isset($headers['location']))
			{
				if ($this->max_redirects <= 0)
					die("Too many redirects on: ".$headers['location']);

				$this->max_redirects--;
				$this->redirectURL = $headers['location'];
				$this->query($headers['location']);
			}
		}

	}

	function getTransferSpeed()
	{
		return $this->lastTransferSpeed;
	}

	/**
	 * The quick way to get a URL's content :)
	 *
	 * @param string URL
	 * @param boolean return as array? (like PHP's file() command)
	 * @return string result body
	 */
	function get($location, $asArray = FALSE )
	{
		$this->query($location);

		if ( $this->get_status_code() == 200 )
		{
			if ($asArray)
			{
				return preg_split("/\n/",$this->fetch_body());
			}

			return $this->fetch_body();
		}

		return FALSE;
	}

	/**
	 * Returns the last status code.
	 * 200 = OK;
	 * 403 = FORBIDDEN;
	 * etc.
	 *
	 * @return int status code
	 */
	function get_status_code()
	{
		return $this->result_status_code;
	}

	/**
	 * Adds a header, sent with the next query.
	 *
	 * @param string header name
	 * @param string header value
	 */
	function add_header($key,$value)
	{
		$this->extra_headers[$key] = $value;
	}

	/**
	 * Clears any extra headers.
	 *
	 */
	function clear_headers()
	{
		$this->extra_headers = array();
	}

	/**
	 * Return the result of a query.
	 *
	 * @return string result
	 */
	function fetch_result()
	{
		return $this->result;
	}

	/**
	 * Return the header of result (stuff before body).
	 *
	 * @param string (optional) header to return
	 * @return array result header
	 */
	function fetch_header( $header = '' )
	{
		$array_headers = preg_split("/\r\n/",$this->result_header);

		$array_return = array( 0 => $array_headers[0] );
		unset($array_headers[0]);

		foreach ( $array_headers as $pair )
		{
			if ($pair == '' || $pair == "\r\n") continue;
			list($key,$value) = preg_split("/: /",$pair,2);
			$array_return[strtolower($key)] = $value;
		}

		if ( $header != '' )
		{
			return $array_return[strtolower($header)];
		}

		return $array_return;
	}

	/**
	 * Return the body of result (stuff after header).
	 *
	 * @return string result body
	 */
	function fetch_body()
	{
		return $this->result_body;
	}

	/**
	 * Return parsed body in array format.
	 *
	 * @return array result parsed
	 */
	function fetch_parsed_body()
	{
		parse_str($this->result_body,$x);
		return $x;
	}


	/**
	 * Set a specifc message on how to change the SSL setting, in the event that it's not set correctly.
	 */
	function set_ssl_setting_message($str)
	{
		$this->ssl_setting_message = $str;
	}


}


abstract class DA_Api {

	/**
	 * @var DirectAdmin
	 */
	protected $sock;

	/**
	 * The default domain
	 * @var string
	 */
	protected $domain;

	/**
	 * an instance of DirectAdmin which can be set to default, so you don't have to pass this into every DA_Api instance
	 * @var DirectAdmin
	 */
	static public $DEFAULT_SOCKET;

	/**
	 * The default domain
	 * @var string
	 */
	static public $DEFAULT_DOMAIN;

	/**
	 *
	 * @param DirectAdmin $sock
	 * @return
	 */
	public function __construct($sock = null, $domain = null){

		$this->sock = self::$DEFAULT_SOCKET;
		if ($sock instanceof DirectAdmin) $this->sock = $sock;
		if (!($this->sock instanceof DirectAdmin)){
			return 'The socket is not an instance of DirectAdmin, set the first argument or the DA_Api::$DEFAULT_SOCKET variables';
		}

		$this->domain = self::$DEFAULT_DOMAIN;
		if ($domain !== null) $this->domain = $domain;

	}

	public function setDomain($domain){
		$this->domain = $domain;
	}

	public function getDomain($domain = null){
		if (!$domain) $domain = $this->domain;
		if (empty($domain)){
			return 'No domain set, use the setDomain method to set one!';
		}
		return $domain;
	}

}

class DA_Emails extends DA_Api {

	/**
	 *
	 * @param string $domain
	 * @return array
	 */
	public function fetch($domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_POP', array(
			'action' => 'list',
			'domain' => $domain
		));
		$row = $this->sock->fetch_parsed_body();

		if (!empty($row['list']) && is_array($row['list'])){
			return $row['list'];
		}
		return array();
	}

	/**
	 * Get a list of the users and the quota and usage
	 * @param string $domain
	 * @return array for example array('user' => array(usage=>3412,quota=>123543))
	 */
	public function fetchQuotas($domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_POP', array(
			'action'	=> 'list',
			'type'		=> 'quota',
			'domain'	=> $domain
		));
		$row = $this->sock->fetch_parsed_body();
		if (is_array($row)){
			foreach ($row as &$item) parse_str($item, $item);
			if (empty($item) || !is_array($item) || !isset($item['quota'])){
				$row = array();
			}
		} else {
			$row = array();
		}

		return $row;
	}

	/**
	 * Get the quota and usage for a user
	 * @param string $user
	 * @param string $domain
	 * @return array for example array(usage=>3412,quota=>123543), both are in bytes
	 */
	public function fetchUserQuota($user, $domain = null){
		$quotas = $this->fetchQuotas($domain);
		return (isset($quotas[$user]) ? $quotas[$user] : array());
	}

	/**
	 * Create an Email Address
	 * @param string $user
	 * @param string $pass
	 * @param int $quota [optional] Integer in Megabytes. Zero for unlimited, > 0 for number of Megabytes.
	 * @param string $domain
	 * @return bool returns true if the email address was created succesfully
	 */
	public function create($user, $pass, $quota = 0, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_POP', array(
			'action' 	=> 'create',
			'domain' 	=> $domain,
			'quota'		=> $quota,
			'user'		=> $user,
			'passwd'	=> $pass
		));

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

	/**
	 * Set the password of an emailaddress
	 * @param string $user
	 * @param string $pass
	 * @param string $domain
	 * @return bool returns true if the email address was created succesfully
	 */
	public function modify($user, $pass = null, $quota = 0, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_POP', array(
			'action'	=> 'modify',
			'domain' 	=> $domain,
			'user'		=> $user,
			'passwd'	=> $pass,
			'passwd2'	=> $pass,
			'quota'		=> $quota
		));

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

	/**
	 * Delete an user
	 * @param string $user
	 * @param string $domain
	 * @return bool
	 */
	public function delete($user, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_POP',array(
			'action'	=> 'delete',
			'domain' 	=> $domain,
			'user'		=> $user
		));

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

}


class DA_Autoresponders extends DA_Api {

	/**
	 * Fetch all the Autoresponders
	 * @param string $domain
	 * @return array array(array('user' => 'destination email'))
	 */
	public function fetch($domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_AUTORESPONDER', array(
			'domain' => $domain
		));
		$rows = $this->sock->fetch_parsed_body();
		$keys = array_keys($rows);
		if (isset($keys[1]) && $keys[1] == '#95API'){
			$rows = array();
		}
		return $rows;
	}

	/**
	 * Fetch the destination url of a forwarder
	 * @param string $user
	 * @param string $domain
	 * @return string
	 */
	public function fetchUser($user, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_AUTORESPONDER_MODIFY', array(
			'domain'	=> $domain,
			'user'		=> $user
		));
		return $this->sock->fetch_parsed_body();
	}

	/**
	 * Create a forwarder
	 * @param string $user
	 * @param string $email
	 * @param string $domain
	 * @return bool
	 */
	public function create($user, $msg, $email = null, $domain = null){
		$domain = $this->getDomain($domain);

		$data = array(
			'action' 	=> 'create',
			'domain' 	=> $domain,
			'user'		=> $user,
			'text'		=> $msg,
			'cc'		=> empty($email) ? 'OFF' : 'ON',
			'email'		=> $email,
			'create'	=> 'Create'
		);
		$this->sock->query('/CMD_API_EMAIL_AUTORESPONDER', $data);

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

	/**
	 * Set the password of an emailaddress
	 * @param string $user
	 * @param string $pass
	 * @param string $domain
	 * @return bool
	 */
	public function modify($user, $msg, $email, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_AUTORESPONDER', array(
			'action'	=> 'modify',
			'domain' 	=> $domain,
			'user'		=> $user,
			'text'		=> $msg,
			'cc'		=> empty($email) ? 'OFF' : 'ON',
			'email'		=> $email,
			'create'	=> 'Create'
		));

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

	/**
	 * Delete an user
	 * @param string $user
	 * @param string $domain
	 * @return bool
	 */
	public function delete($user, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_AUTORESPONDER', array(
			'action'	=> 'delete',
			'domain' 	=> $domain,
			'user'		=> $user,
			'select0'	=> $user
		));

		$ret = $this->sock->fetch_parsed_body();
		return (isset($ret['error']) && $ret['error'] == 0);
	}

}


class DA_Forwarders extends DA_Api {

	/**
	 * Fetch all the forwarders
	 * @param string $domain
	 * @return array array(array('user' => 'destination email'))
	 */
	public function fetch($domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_FORWARDERS', array(
			'action' => 'list',
			'domain' => $domain
		));
		$rows = $this->sock->fetch_parsed_body();
		$keys = array_keys($rows);
		if (isset($keys[1]) && $keys[1] == '#95API'){
			$rows = array();
		}
		return $rows;
	}

	/**
	 * Fetch the destination url of a forwarder
	 * @param string $user
	 * @param string $domain
	 * @return string
	 */
	public function fetchUser($user, $domain = null){
		$users = $this->fetch($domain);
		return isset($users[$user]) ? $users[$user] : null;
	}

	/**
	 * Create a forwarder
	 * @param string $user
	 * @param string $email
	 * @param string $domain
	 * @return bool
	 */
	public function create($user, $email, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_FORWARDERS', array(
			'action' 	=> 'create',
			'domain' 	=> $domain,
			'user'		=> $user,
			'email'		=> $email,
		));

		$ret = $this->sock->fetch_parsed_body();
		return isset($ret['error']) && $ret['error'] == 0;
	}

	/**
	 * Set the password of an emailaddress
	 * @param string $user
	 * @param string $pass
	 * @param string $domain
	 * @return bool
	 */
	public function modify($user,  $email, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_FORWARDERS', array(
			'action'	=> 'modify',
			'domain' 	=> $domain,
			'user'		=> $user,
			'email'		=> $email,
		));

		$ret = $this->sock->fetch_parsed_body();
		return isset($ret['error']) && $ret['error'] == 0;
	}

	/**
	 * Delete an user
	 * @param string $user
	 * @param string $domain
	 * @return bool
	 */
	public function delete($user, $domain = null){
		$domain = $this->getDomain($domain);

		$this->sock->query('/CMD_API_EMAIL_FORWARDERS', array(
			'action'	=> 'delete',
			'domain' 	=> $domain,
			'user'		=> $user,
			'select0'	=> $user
		));

		$ret = $this->sock->fetch_parsed_body();
		return isset($ret['error']) && $ret['error'] == 0;
	}

}

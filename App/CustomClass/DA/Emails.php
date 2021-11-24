<?php

/**
 * http://www.directadmin.com/api.html#email
 */

namespace App\CustomClass\DA\DA_Emails;
use App\CustomClass\DA\DA_Api;
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

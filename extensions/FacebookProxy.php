<?php

namespace li3_facebook\extensions;

use lithium\core\Libraries;
use lithium\util\Set;
use Facebook;
use Jaxl;

class FacebookProxy extends \lithium\core\StaticObject {

	protected static $_options = array(
		'appId' => '',
		'secret' => '',
		'trustForwarded' => false,
		'fileUpload' => false
	);

	protected static $_facebook = null;
	protected static $_jaxl = array();
	protected static $_user = null;

	public static function __init() {
		$options = Libraries::get('li3_facebook', 'options');
 		static::$_options = Set::merge(static::$_options, $options);
		static::config();
	}

	public static function config(array $options = array()) {
		if ($options) {
			static::$_options = Set::merge(static::$_options, $options);
		}
		static::$_facebook = new Facebook(static::$_options);
		return static::$_options;
	}

	public static function __callStatic($method, $args = array()) {
		return static::run($method, $args);
	}

	public static function run($method, $params = array()) {
		if ($method === null) {
			return null;
		}

		$params = (array) $params;
		$params = compact('method', 'params');

		$facebook = static::$_facebook;

		$filter = function($self, $params) use ($facebook) {
			extract($params);
			if (!method_exists($facebook, $method)) {
				return false;
			}
			switch (count($params)) {
				case 0:
					return $facebook->{$method}();
				case 1:
					return $facebook->{$method}($params[0]);
				case 2:
					return $facebook->{$method}($params[0], $params[1]);
				case 3:
					return $facebook->{$method}($params[0], $params[1], $params[2]);
				default:
					return call_user_func_array(array($facebook, $method), $params);
			}
		};
		 return static::_filter(__FUNCTION__, $params, $filter);
	}

	public static function getUserProfile() {
		if (static::$_user) {
			return static::$_user;
		}
		return static::$_user = static::run('api', '/me');
	}

	public static function getJAXL ($fbid, $accessToken) {
		if (!isset(static::$_jaxl[$fbid])){
				static::$_jaxl[$fbid] = new JAXL(array(
					// (required) credentials
					'jid' => $fbid.'@chat.facebook.com',
					'fb_app_key' => static::$_options['appId'],
					'fb_access_token' => $accessToken,
		
					// force tls (facebook require this now)
					'force_tls' => true,
					// (required) force facebook oauth
					'auth_type' => 'X-FACEBOOK-PLATFORM',
		
					// (optional)
					//'resource' => 'resource',
		
					'log_level' => JAXL_INFO
				));
		}
		return static::$_jaxl[$fbid];
	}

	public static function xmppSend ($to_fbid, $body, $options = array()) { //$from_fbid = null, $accessToken = null, $onSuccess = null, $onError = null) {
			

		$from_fbid =  !empty($options['from']) ? $options['from'] : self::getUser();
		$accessToken = !empty($options['token']) ?  $options['token'] : self::getAccessToken();

		$client = self::getJAXL($from_fbid, $accessToken);
		$client->add_cb('on_auth_success', function() use ($client, $body, $to_fbid, $options) {
			//_info("got on_auth_success cb, jid ".$client->full_jid->to_string());
			//$client->set_status("available!", "dnd", 10);
			$client->send_chat_msg($to_fbid, $body);
			if (isset($options['onSuccess'])) {
				$options['onSuccess'](compact('client', 'to_fbid', 'from_fbid', 'body'));
			}
		});

		$client->add_cb('on_auth_failure', function($reason) use ($client, $body, $to_fbid, $options) {
			$client->send_end_stream();
			if (isset($options['onError'])) {
				$options['onError'](compact('client', 'to_fbid', 'from_fbid', 'body'));
			}
			//_info("got on_auth_failure cb with reason $reason");
		});
	}
}

?>
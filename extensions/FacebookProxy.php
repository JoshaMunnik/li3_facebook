<?php

namespace li3_facebook\extensions;

use Exception;
use lithium\core\ClassNotFoundException;
use lithium\core\ConfigException;
use lithium\core\Libraries;

/**
* The `FacebookProxy` class handles all Facebook related functionalities.
* The class is mainly a lithium wrapper for the existing Facebook API. It is oriented by
* the proxy-pattern which is using the original FB-API as a singleton.
* It has to be configured by an api key and the secret.
*/
class FacebookProxy extends \lithium\core\StaticObject {

	/**
	 * Holds the configuration Options
	 * @var array
	*/
	protected static $_config = array();

	/**
	 * These are the class `defaults`
	 * @var array
	 */
	protected static $_defaults = array(
		'appId' => '',
		'secret' => '',
		'cookie' => true,
		'domain' => false,
		'fileUpload' => false
	);

	/**
	 * If true, class will automatically fetch data from libraries settings
	 * Set this to false if you want to do configuration manually or in debug mode
	 * @var boolean
	 */
	public static $_autoConfigure = true;

	/**
	 * If false, given Config wont be validated
	 * @var boolean
	 */
	public static $_validateConfiguration = true;

	/**
	 * Holds the Facebook Api Version Strings. associated to the
	 * tested git hash (just for debug info)
	 * @var array of Version Strings
	 */
	public static $__compatibleApiVersions = array(
		'3.1.1' => 'f2a8588e4eccc16dac0c9a93fec347a9e2c97b9d'
	);

	/**
	 * Holds the FacebookAPI as singleton
	 * @var Facebbok
	 */
	public static $_facebookApiInstance = null;

	public function __construct($config = array()) {
		if ($config){
			static::config($config);
		}
	}

	/**
	 *
	 * @return void
	 */
	public static function __init() {
		if (static::$_autoConfigure){
			$libraryConfig = Libraries::get('li3_facebook');
			static::config($libraryConfig + static::$_defaults);
		}
	}

	/**
	 * Sets configurations for the FacebookApi.
	 * This Method is basically a copy and edit of the config in adaptable.
	 *
	 * @see lithium\core\adaptable
	 *
	 * @param array $config Configuratin.
	 * @return array|void `Collection` of configurations or true if setting configurations.
	 */
	public static function config($config = null) {
		//set if `config`is given
		if ($config && is_array($config)) {
			
			//filter only accepts configuration options
			foreach ($config as $key => $value){
				if (\array_key_exists($key, static::$_defaults)){
					static::$_config[$key] = $value;
				}
			};
			return true;
		}
		
		return static::$_config;
	}

	/**
	 * Clears all configurations.
	 *
	 * @return void
	 */
	public static function reset() {
		static::$_facebookApiInstance = null;
		static::$_config = array();
	}

	/**
	 * Proxies the method calls
	 * @param string $method
	 * @param mixed $arguments
	 */
	public static function __callStatic($method, $arguments) {
		return static::run($method,$arguments);
	}

	/**
	 * Calls should be rerouted to the facebookApiInstance of the proxy
	 * @todo insert a callable existance check
	 *
	 * @see lithium/core/StaticObject
	 *
	 * @throws FacebookApiException
	 *
	 * @param string $method
	 * @param mixed $params
	 * @return mixed Return value of the called api method
	 * @filter this Method is filterable
	 */
	public static function run($method, $params = array()) {
		$params = compact('method', 'params');
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);

			if (!$self::$_facebookApiInstance){
				$self::invokeMethod('instantiateFacebookApi');
			}

			// @todo: insert callable existance check here!
			if (!\is_callable(array($self::$_facebookApiInstance,$method))){
				throw new Exception(__CLASS__ . " Method `$method` is not callable");
			}
			switch (count($params)) {
				case 0:
					return $self::$_facebookApiInstance->$method();
				case 1:
					return $self::$_facebookApiInstance->$method($params[0]);
				case 2:
					return $self::$_facebookApiInstance->$method($params[0], $params[1]);
				case 3:
					return $self::$_facebookApiInstance->$method(
						$params[0], $params[1], $params[2]
					);
				case 4:
					return $self::$_facebookApiInstance->$method(
						$params[0], $params[1], $params[2], $params[3]
					);
				case 5:
					return $self::$_facebookApiInstance->$method(
						$params[0], $params[1], $params[2], $params[3], $params[4]
					);
				default:
					// Not sure if this is a good idea
					return call_user_func_array(array(get_called_class(), $method), $params);
			}
		});
	}

	/**
	 * Safely instantiates the Facebook Api.
	 * @throws Exception for various Errors.
	 *
	 * @param array $config
	 * @return Facebook $apiInstance
	 * @filter This method may be filtered.
	 */
	public static function instantiateFacebookApi($config = array()){
		$params = compact('config');
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);

			if ($self::$_validateConfiguration){
				$self::invokeMethod('checkConfiguration',$config);
			}
			if ($config){
				$self::config($config);
			}
			$self::invokeMethod('_checkApiAvailability');
			$self::invokeMethod('_requireFacebookApi');
			$self::invokeMethod('_checkApiCompatibility');
			$apiInstance = new \Facebook($self::config());
			if (!$apiInstance){
				throw new Exception('Facebook Api can\'t be instantiated!');
			}
			$self::$_facebookApiInstance = $apiInstance;
			return $apiInstance;
		});
	}

	/**
	 * checks the configuration against Problems (and unsupported features)
	 *
	 * @todo finish this!
	 *
	 * @throws Exceptions if there are problems
	 *
	 * @param array $config
	 * @return boolean
	 * @filter This method may be filtered.
	 */
	public static function checkConfiguration($config = array()){
		$params = compact('config');
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);

			if (!$config){
			$config = $self::invokeMethod('config');
			}
			if (empty($config['appId'])){
				throw new ConfigException('Configuration: `appId` should be set');
			}
			if (empty($config['secret'])){
				throw new ConfigException('Configuration: `secret` should be set');
			}
			if (!empty($config['domain'])){
				throw new ConfigException('Configuration: `domain` not yet supported');
			}
			if (!empty($config['fileUpload'])){
				throw new ConfigException('Configuration: `fileUpload` not yet supported');
			}
			return true;
		});
	}

	/**
	 * Fetches the ApiPath and checks if the Api is there
	 */
	protected static function _checkApiAvailability(){
		$fbLib = static::_getApiPath();
		if (!\file_exists($fbLib)){
			throw new ClassNotFoundException('Facebook Lib not there! Do git submoule init first!');
		}
	}

	/**
	 * constructs the Api Path by this file
	 * @return string full Path to the FacebookApi
	 * @filter This method may be filtered.
	 */
	protected static function _getApiPath(){
		$params = array();
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);
			$fbLib = __DIR__ . '/../libraries/facebook-sdk/src/facebook.php';
			return \realpath($fbLib);
		});
	}

	/**
	 * Requires the Facebok Api.
	 *
	 * @throws (rethrows) Exception if curl or json_decode not reachable!
	 * @return void
	 */
	protected static function _requireFacebookApi(){
		require_once static::_getApiPath();
	}

	/**
	 * Checks the Api version against this Proxy capabilities
	 *
	 * @throws Exception if the Library is not compatible
	 * @return void
	 */
	protected static function _checkApiCompatibility(){
		$versions = static::$__compatibleApiVersions;
		if (!\array_key_exists(\Facebook::VERSION, $versions)){
			throw new Exception('Facebook Library is not compatible to our library');
		}
	}

	/**
	 * Returns the instatiated Facebook Class for own usage.
	 *
	 * @return Facebook $facebookInstance
	 */
	public static function getApiInstance(){
		return static::$_facebookApiInstance;
	}
	
	
	/**
	 * Added by @mackstar to parse signed request sent by  
	 *
	 * @param string $signed_request Signed request string sent by facebook
	 * @return array parsed facebook data from signed request 
	 */
	public static function parseSignedRequest($signed_request) {
		$facebook_config = Libraries::get('li3_facebook');
		$secret = $facebook_config['secret'];
		
		list($encoded_sig, $payload) = explode('.', $signed_request, 2); 

		// decode the data
		$sig = self::_base64_url_decode($encoded_sig);
		$data = json_decode(self::_base64_url_decode($payload), true);

		if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
			error_log('Unknown algorithm. Expected HMAC-SHA256');
			return null;
		}

		// check sig
		$expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
		if ($sig !== $expected_sig) {
			error_log('Bad Signed JSON signature!');
			return null;
		}

		return $data;
	}
	
	
	/**
	 * Added by @mackstar part of facebook signed request decoding strategy
	 *
	 * @param string input string
	 * @return string based64 decoded string
	 */
	function _base64_url_decode($input) {
		return base64_decode(strtr($input, '-_', '+/'));
	}
}

?>
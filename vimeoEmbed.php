<?php

class VimeoEmbed {
	const API_REST_URL = 'http://vimeo.com/api/oembed';
	const _cache_dir = './cache';
	const CACHE_FILE = 'file';

	private $_cache_enabled = true;
	private $_cache_expire = 604800; // 1 week expiry
	private $_url = false;

	public function __construct() {
		if(!is_writeable(realpath(self::_cache_dir))) self::clearCache();
	}

	/**
	 * Cache a response.
	 * 
	 * @param array $params The parameters for the response.
	 * @param string $response The unserialized response data.
	 */
	private function _cache($params, $response) {
		$hash = md5(serialize($params));

		if($this->_cache_enabled == self::CACHE_FILE) {
			$file = realpath(self::_cache_dir) . DIRECTORY_SEPARATOR . $hash . '.cache';
			if (file_exists($file)) {
				unlink($file);
			}
			return file_put_contents($file, serialize($response));
		}
	}

	/**
	 * Get the unserialized contents of the cached request.
	 * 
	 * @param array $params The full list of API parameters for the request
	 */
	private function _getCached($params) {
		$hash = md5(serialize($params));

		if($this->_cache_enabled == self::CACHE_FILE) {
			$file = realpath(self::_cache_dir) . DIRECTORY_SEPARATOR . $hash . '.cache';

			if(file_exists($file)) {
				return unserialize(file_get_contents($file));
			}
		}
	}

	/**
	 *  Choose whether to use cache or request new data
	 */
	private function _useCache($params) {
		$hash = md5(serialize($params));

		if($this->_cache_enabled == self::CACHE_FILE) {
			$file = realpath(self::_cache_dir) . DIRECTORY_SEPARATOR . $hash . '.cache';

			// if there is a cached file and it hasn't expired...
			if(file_exists($file) && filemtime($file) + $this->_cache_expire >= time()) return true;
		}
		// if no valid cached file...
		return false;
	}

	/**
	 * Encode URL according to latest standard
	 */
	public static function url_encode_rfc3986($input) {
		if(is_array($input)) {
			return array_map(array('VimeoEmbed', 'url_encode_rfc3986'), $input);
		}
		else if (is_scalar($input)) {
			return str_replace(array('+', '%7E'), array(' ', '~'), rawurlencode($input));
		}
		else {
			return '';
		}
	}

	/**
	 * Create the API request
	 * 
	 * @param array $params The API arguments
	 */
	private function _createRequest($params, $format = 'json') {
		if(isset($params['format'])) unset($params['format']);

		$query = http_build_query($params, '', '&');

		$this->_url = self::API_REST_URL . $format . '?' . $query;
	}
	/**
	 * Main function of the class
	 * Makes the API call to Vimeo's oEmbed, caches, and displays the result.
	 *
	 * @param string $url Video URL (incl. protocol)
	 * @param array $params Additional styling parameters from oEmbed docs
	 */
	public function call($url = 'https://vimeo.com/7598473', $params = array()) {
		// Merging url into parameters
		$params['url'] = $url;

		// use cached content
		if ($this->_cache_enabled && $this->_useCache($params)) return $this->_getCached($params);

		// cached content wasn't valid, fetch a new copy
		$this->_createRequest($params);

		// curl interactions
		$curl_url = $this->_url;
		$curl_opts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30
		);
		$curl = curl_init($curl_url);
		curl_setopt_array($curl, $curl_opts);
		$response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);
		curl_close($curl);

		// We know it's JSON-encoded, so...
		$response = json_decode($response);

		// Cache it!
		if($this->_cache_enabled) $this->_cache($params, $response);

		return $response;
	}
	
	// Switches to enable and disable use of cache.
	public function disableCache() {
		$this->_cache_enabled = false;
	}
	public function enableCache() {
		$this->_cache_enabled = true;
	}

	private static function _rmdir($directory) {
		if(!is_writeable($directory) || !is_dir($directory)) return;
		$files = scandir($directory, 0);
		if(count($files) > 2) {
			foreach($files as $item) {
				if($item == '.' || $item == '..') continue;
				if(is_file($item)) unlink($directory . DIRECTORY_SEPARATOR . $item);
				if(is_dir($item)) self::_rmdir($directory . DIRECTORY_SEPARATOR . $item);
			}
		}
		rmdir($directory);
	}

	// Clear all cached files
	public static function clearCache($cache_dir = self::_cache_dir) {
		#if($cache_dir = '') $cache_dir = realpath(self::_cache_dir);
		self::_rmdir(realpath($cache_dir));
		mkdir($cache_dir, 0660); // make directory with RW access for most.
	}
}

class VimeoEmbedException extends Exception {}

?>

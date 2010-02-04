<?

class phpVimeo {
	
	const API_REST_URL = 'http://www.vimeo.com/api/rest/v2';
	const API_AUTH_URL = 'http://www.vimeo.com/oauth/authorize';
	const API_ACCESS_TOKEN_URL = 'http://www.vimeo.com/oauth/access_token';
	const API_REQUEST_TOKEN_URL = 'http://www.vimeo.com/oauth/request_token';
	
	const CACHE_FILE = 'file';
	
	private $_consumer_key = false;
	private $_consumer_secret = false;
	private $_cache_enabled = false;
	private $_cache_dir = false;
	private $_token = false;
	private $_token_secret = false;
	private $_upload_md5s = array();
	
	public function __construct($consumer_key, $consumer_secret, $token = null, $token_secret = null) {
		$this->_consumer_key = $consumer_key;
		$this->_consumer_secret = $consumer_secret;
		
		if ($token && $token_secret) {
			$this->setToken($token, $token_secret);
		}
	}
	
	/**
	 * Cache a response.
	 * 
	 * @param array $params The parameters for the response.
	 * @param string $response The serialized response data.
	 */
	private function _cache($params, $response) {
		// Remove some unique things
		unset($params['oauth_nonce']);
		unset($params['oauth_signature']);
		unset($params['oauth_timestamp']);
		
		$hash = md5(serialize($params));
		
		if ($this->_cache_enabled == self::CACHE_FILE) {
			$file = $this->_cache_dir.'/'.$hash.'.cache';
			if (file_exists($file)) {
				unlink($file);
			}
			return file_put_contents($file, $response);
		}
	}
	
	/**
	 * Create the authorization header for a set of params.
	 * 
	 * @param array $oauth_params The OAuth parameters for the call.
	 * @return string The OAuth Authorization header.
	 */
	private function _generateAuthHeader($oauth_params) {
		$auth_header = 'Authorization: OAuth realm=""';
		foreach ($oauth_params as $k => $v) {
			$auth_header .= ','.self::url_encode_rfc3986($k).'="'.self::url_encode_rfc3986($v).'"';
		}
		return $auth_header;
	}
	
	/**
	 * Generate a nonce for the call.
	 * 
	 * @return string The nonce
	 */
	private function _generateNonce() {
		return md5(uniqid(microtime()));
	}
	
	/**
	 * Generate the OAuth signature.
	 * 
	 * @param array $args The full list of args to generate the signature for.
	 * @param string $request_method The request method, either POST or GET.
	 * @param string $url The base URL to use.
	 * @return string The OAuth signature.
	 */
	private function _generateSignature($params, $request_method = 'GET', $url = self::API_REST_URL) {
		uksort($params, 'strcmp');
		$params = self::url_encode_rfc3986($params);
		
		// Make the base string
		$base_parts = array(
			strtoupper($request_method),
			$url,
			urldecode(http_build_query($params))
		);
		$base_parts = self::url_encode_rfc3986($base_parts);
		$base_string = implode('&', $base_parts);
		
		// Make the key
		$key_parts = array(
			$this->_consumer_secret,
			($this->_token_secret) ? $this->_token_secret : ''
		);
		$key_parts = self::url_encode_rfc3986($key_parts);
		$key = implode('&', $key_parts);
		
		// Generate signature
		return base64_encode(hash_hmac('sha1', $base_string, $key, true));
	}
	
	/**
	 * Get the unserialized contents of the cached request.
	 * 
	 * @param array $params The full list of api parameters for the request.
	 */
	private function _getCached($params) {
		// Remove some unique things
		unset($params['oauth_nonce']);
		unset($params['oauth_signature']);
		unset($params['oauth_timestamp']);
		
		$hash = md5(serialize($params));
		
		if ($this->_cache_enabled == self::CACHE_FILE) {
			$file = $this->_cache_dir.'/'.$hash.'.cache';
			if (file_exists($file)) {
				return unserialize(file_get_contents($file));
			}
		}
	}
	
	/**
	 * Call an API method.
	 * 
	 * @param string $method The method to call.
	 * @param array $call_params The parameters to pass to the method.
	 * @param string $request_method The HTTP request method to use.
	 * @param string $url The base URL to use.
	 * @param boolean $cache Whether or not to cache the response.
	 * @param boolean $use_auth_header Use the OAuth Authorization header to pass the OAuth params.
	 * @return string The response from the method call.
	 */
	private function _request($method, $call_params = array(), $request_method = 'GET', $url = self::API_REST_URL, $cache = true, $use_auth_header = true) {
		
		// Prepare oauth arguments
		$oauth_params = array(
			'oauth_consumer_key' => $this->_consumer_key,
			'oauth_version' => '1.0',
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => time(),
			'oauth_nonce' => $this->_generateNonce()
		);
		
		// If we have a token, include it
		if ($this->_token) {
			$oauth_params['oauth_token'] = $this->_token;
		}
		
		// Regular args
		$api_params = array('format' => 'php');
		if (!empty($method)) {
			$api_params['method'] = $method;
		}
		
		// Merge args
		foreach ($call_params as $k => $v) {
			if (strpos($k, 'oauth_') === 0) {
				$oauth_params[$k] = $v;
			}
			else {
				$api_params[$k] = $v;
			}
		}
		
		// Generate the signature
		$oauth_params['oauth_signature'] = $this->_generateSignature(array_merge($oauth_params, $api_params), $request_method, $url);
		
		// Merge all args
		$all_params = array_merge($oauth_params, $api_params);
		
		// Returned cached value
		if ($this->_cache_enabled && ($cache && $response = $this->_getCached($all_params))) {
			return $response;
		}
		
		// Curl options
		if ($use_auth_header) {
			$params = $api_params;
		}
		else {
			$params = $all_params;
		}
		
		if (strtoupper($request_method) == 'GET') {
			$curl_url = $url.'?'.http_build_query($params);
			$curl_opts = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30
			);
		}
		elseif (strtoupper($request_method) == 'POST') {
			$curl_url = $url;
			$curl_opts = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($params)
			);
		}
		
		// Authorization header
		if ($use_auth_header) {
			$curl_opts[CURLOPT_HTTPHEADER] = array($this->_generateAuthHeader($oauth_params));
		}
		
		// Call the API
		$curl = curl_init($curl_url);
		curl_setopt_array($curl, $curl_opts);
		$response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);
		curl_close($curl);
		
		// Cache the response
		if ($this->_cache_enabled && $cache) {
			$this->_cache($all_params, $response);
		}
		
		// Return
		if (!empty($method)) {
			$response = unserialize($response);
			if ($response->stat == 'ok') {
				return $response;
			}
			else if ($response->err) {
				throw new VimeoAPIException($response->err->msg, $response->err->code);
			}
			return false;
		}
		return $response;
	}
	
	/**
	 * Send the user to Vimeo to authorize your app.
	 * http://www.vimeo.com/api/docs/oauth
	 * 
	 * @param string $perms The level of permissions to request: read, write, or delete.
	 */
	public function auth($permission = 'read', $callback_url = 'oob') {
		$t = $this->getRequestToken($callback_url);
		$this->setToken($t['oauth_token'], $t['oauth_token_secret'], 'request', true);
		$url = $this->getAuthorizeUrl($this->_token, $permission);
		header("Location: {$url}");
	}
	
	/**
	 * Call a method.
	 * 
	 * @param string $method The name of the method to call.
	 * @param array $params The parameters to pass to the method.
	 * @param string $request_method The HTTP request method to use.
	 * @param string $url The base URL to use.
	 * @param boolean $cache Whether or not to cache the response.
	 * @return array The response from the API method
	 */
	public function call($method, $params = array(), $request_method = 'GET', $url = self::API_REST_URL, $cache = true) {
		$method = (substr($method, 0, 6) != 'vimeo.') ? "vimeo.{$method}" : $method;
		return $this->_request($method, $params, $request_method, $url, $cache);
	}
	
	/**
	 * Enable the cache.
	 * 
	 * @param string $type The type of cache to use (phpVimeo::CACHE_FILE is built in)
	 * @param string $path The path to the cache (the directory for CACHE_FILE)
	 * @param int $expire The amount of time to cache responses (default 10 minutes)
	 */
	public function enableCache($type, $path, $expire = 600) {
		$this->_cache_enabled = $type;
		if ($this->_cache_enabled == self::CACHE_FILE) {
			$this->_cache_dir = $path;
			$files = scandir($this->_cache_dir);
			foreach ($files as $file) {
				$last_modified = filemtime($this->_cache_dir.'/'.$file);
				if (substr($file, -6) == '.cache' && ($last_modified + $expire) < time()) {
					unlink($this->_cache_dir.'/'.$file);
				}
			}
		}
		return false;
	}
	
	/**
	 * Get an access token. Make sure to call setToken() with the
	 * request token before calling this function.
	 * 
	 * @param string $verifier The OAuth verifier returned from the authorization page or the user.
	 */
	public function getAccessToken($verifier) {
		$access_token = $this->_request(null, array('oauth_verifier' => $verifier), 'GET', self::API_ACCESS_TOKEN_URL, false, true);
		parse_str($access_token, $parsed);
		return $parsed;
	}
	
	/**
	 * Get the URL of the authorization page.
	 * 
	 * @param string $token The request token.
	 * @param string $permission The level of permissions to request: read, write, or delete.
	 * @param string $callback_url The URL to redirect the user back to, or oob for the default.
	 * @return string The Authorization URL.
	 */
	public function getAuthorizeUrl($token, $permission = 'read') {
		return self::API_AUTH_URL."?oauth_token={$token}&permission={$permission}";
	}
	
	/**
	 * Get a request token.
	 */
	public function getRequestToken($callback_url = 'oob') {
		$request_token = $this->_request(
			null,
			array('oauth_callback' => $callback_url),
			'GET',
			self::API_REQUEST_TOKEN_URL,
			false,
			false
		);
		
		parse_str($request_token, $parsed);
		return $parsed;
	}
	
	/**
	 * Get the stored auth token.
	 * 
	 * @return array An array with the token and token secret.
	 */
	public function getToken() {
		return array($this->_token, $this->_token_secret);
	}
	
	/**
	 * Set the OAuth token.
	 * 
	 * @param string $token The OAuth token
	 * @param string $token_secret The OAuth token secret
	 * @param string $type The type of token, either request or access
	 * @param boolean $session_store Store the token in a session variable
	 * @return boolean true
	 */
	public function setToken($token, $token_secret, $type = 'access', $session_store = false) {
		$this->_token = $token;
		$this->_token_secret = $token_secret;
		
		if ($session_store) {
			$_SESSION["{$type}_token"] = $token;
			$_SESSION["{$type}_token_secret"] = $token_secret;
		}
		
		return true;
	}
	
	/**
	 * Upload a video in one piece.
	 * 
	 * @param string $file_name The full path to the file
	 * @return int The video ID
	 */
	public function upload($file_name) {
		if (file_exists($file_name)) {
			
			// MD5 the file
			$hash = md5_file($file_name);

			// Get an upload ticket
			$rsp = $this->call('vimeo.videos.upload.getTicket');
			$ticket = $rsp->ticket->id;
			$endpoint = $rsp->ticket->endpoint;
			
			// Set up params for video post
			$params = array(
				'oauth_consumer_key'     => $this->_consumer_key,
				'oauth_token'            => $this->_token,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_nonce'            => $this->_generateNonce(),
				'oauth_version'          => '1.0',
				'ticket_id'              => $ticket,
			);
			$signature = $this->_generateSignature($params, 'POST', self::API_REST_URL);
			$params = array_merge($params, array(
				'oauth_signature' => $signature,
				'file_data'       => '@'.realpath($file_name) // don't include the file in the signature
			));

			// Post the video
			$curl = curl_init($endpoint);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
			$rsp = curl_exec($curl);
			curl_close($curl);

			// If the uploaded file's MD5 doesn't match
			if ($rsp !== $hash) {
				throw new VimeoAPIException(799, 'Uploaded file MD5 does not match');
			}
			
			// Figure out the filename
			$path_parts = pathinfo($file_name);
			$base_name = $path_parts['basename'];
			
			// Set up parameters for confirm call
			$params = array(
				'oauth_consumer_key'     => $this->_consumer_key,
				'oauth_token'            => $this->_token,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_nonce'            => $this->_generateNonce(),
				'oauth_version'          => '1.0',
				'ticket_id'              => $ticket,
				'method'                 => 'vimeo.videos.upload.confirm',
				'format'                 => 'php',
				'filename'               => $base_name
			);
			$signature = $this->_generateSignature($params, 'POST', self::API_REST_URL);
			$params = array_merge($params, array(
				'oauth_signature' => $signature
			));

			// Confirm the upload
			$curl = curl_init(self::API_REST_URL.'?'.http_build_query($params));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_POST, 1);
			$rsp = unserialize(curl_exec($curl));
			curl_close($curl);
			
			// Confirmation successful, return video id
			if ($rsp->stat == 'ok') {
				return $rsp->ticket->video_id;
			}
			else if ($rsp->err) {
				throw new VimeoAPIException($rsp->err->msg, $rsp->err->code);
			}
		}
		return false;
	}
	
	/**
	 * Upload a video in multiple pieces.
	 * 
	 * @param string $file_name The full path to the file
	 * @param int $size The size of each piece in bytes (1MB default)
	 * @return int The video ID
	 */
	public function uploadMulti($file_name, $size = 1048576) {
		if (file_exists($file_name)) {
			
			// MD5 the whole file
			$hash = md5_file($file_name);

			// Get an upload ticket
			$rsp = $this->call('vimeo.videos.upload.getTicket', array(), 'GET', self::API_REST_URL, false);
			$ticket = $rsp->ticket->id;
			$endpoint = $rsp->ticket->endpoint;
			
			// How many pieces?
			$pieces = ceil(filesize($file_name) / $size);
			
			// Create pieces and upload
			$chunks = array();
			for ($i = 0; $i < $pieces; $i++) {
				
				$piece_file_name = "{$file_name}.{$i}";
				
				// Break it up
				$piece = file_get_contents($file_name, FILE_BINARY, null, $i * $size, $size);
				file_put_contents($piece_file_name, $piece);
				
				// Get the md5 for the manifest
				$chunks[] = array('file' => $piece_file_name, 'md5' => md5_file($piece_file_name));
				
				// Set up params for video post
				$params = array(
					'oauth_consumer_key'     => $this->_consumer_key,
					'oauth_token'            => $this->_token,
					'oauth_signature_method' => 'HMAC-SHA1',
					'oauth_timestamp'        => time(),
					'oauth_nonce'            => $this->_generateNonce(),
					'oauth_version'          => '1.0',
					'ticket_id'              => $ticket,
				);
				$signature = $this->_generateSignature($params, 'POST', self::API_REST_URL);
				$params = array_merge($params, array(
					'oauth_signature' => $signature,
					'file_data'       => '@'.realpath($piece_file_name)
				));
				
				// Post the video
				$curl = curl_init($endpoint);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				$rsp = curl_exec($curl);
				curl_close($curl);
			}
			
			// Create the manifest
			$manifest = array();
			foreach ($chunks as $file) {
				$manifest['files'][] = array('md5' => $file['md5']);
			}
			$manifest = json_encode($manifest);
			
			// Verify the manifest
			$params = array(
				'oauth_consumer_key'     => $this->_consumer_key,
				'oauth_token'            => $this->_token,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_nonce'            => $this->_generateNonce(),
				'oauth_version'          => '1.0',
				'ticket_id'              => $ticket,
				'method'                 => 'vimeo.videos.upload.verifyManifest',
				'format'                 => 'php'
			);
			
			$signature = $this->_generateSignature($params, 'POST', self::API_REST_URL);
			$params = array_merge($params, array(
				'oauth_signature' => $signature
			));
			$curl = curl_init(self::API_REST_URL.'?'.http_build_query($params));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, array('json_manifest' => $manifest));
			$rsp = unserialize(curl_exec($curl));
			curl_close($curl);
			
			// Delete chunks
			foreach ($chunks as $file) {
				unlink($file['file']);
			}
			
			// Error
			if ($rsp->stat != 'ok') {
				throw new VimeoAPIException($rsp->err->msg, $rsp->err->code);
			}
			
			// If the uploaded file's MD5 doesn't match
			if ($rsp->ticket->md5 !== $hash) {
				throw new VimeoAPIException(799, 'Uploaded file MD5 does not match');
			}
			
			// Figure out the filename
			$path_parts = pathinfo($file_name);
			$base_name = $path_parts['basename'];
			
			// Confirm upload
			$params = array(
				'oauth_consumer_key'     => $this->_consumer_key,
				'oauth_token'            => $this->_token,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_nonce'            => $this->_generateNonce(),
				'oauth_version'          => '1.0',
				'ticket_id'              => $ticket,
				'method'                 => 'vimeo.videos.upload.confirm',
				'format'                 => 'php',
				'filename'               => $base_name
			);
			$signature = $this->_generateSignature($params, 'POST', self::API_REST_URL);
			$params = array_merge($params, array(
				'oauth_signature' => $signature
			));
			$curl = curl_init(self::API_REST_URL.'?'.http_build_query($params));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, array('json_manifest' => $manifest));
			$rsp = unserialize(curl_exec($curl));
			curl_close($curl);

			// Confirmation successful, return video id
			if ($rsp->stat == 'ok') {
				return $rsp->ticket->video_id;
			}
			else if ($rsp->err) {
				throw new VimeoAPIException($rsp->err->msg, $rsp->err->code);
			}
		}
		return false;
	}
	
	/**
	 * URL encode a parameter or array of parameters.
	 * 
	 * @param array/string $input A parameter or set of parameters to encode.
	 */
	public static function url_encode_rfc3986($input) {
		if (is_array($input)) {
			return array_map(array('phpVimeo', 'url_encode_rfc3986'), $input);
		}
		elseif (is_scalar($input)) {
			return str_replace(array('+', '%7E'), array(' ', '~'), rawurlencode($input));
		}
		else {
			return '';
		}
	}
	
}

class VimeoAPIException extends Exception {
	
}

?>
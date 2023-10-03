<?php
require_once ROOT_DIR . '/sys/CurlWrapper.php';
require_once ROOT_DIR . '/Action.php';

class KohaAPI {

	private $baseURL;
	private $apiKey;
	private $oauthToken;
	private $basicAuthToken;
	/** @var CurlWrapper */
	private $apiCurlWrapper;
	/**@var CurlWrapper */
	private $curlWrapper;
	private $oAuthClientId;
	private $oAuthClientSecret;

	function __construct($baseURL,$apiKey){
		$this->baseURL = $baseURL;
		$this->apiKey = $apiKey;
	}

	function launch() {
		// TODO: Implement launch() method.
	}

	function getOAuthToken() {
	}

	function getBasicAuthToken() {
	}

	function refreshToken() {
	}

	public function get($endpoint): string {
		return "";
	}

	public function post($endpoint,$data): string {
		return "";
	}

	public function put($endpoint,$data): string {
		return "";
	}

	public function delete($endpoint): string {
		return "";
	}



	



	function getBreadcrumbs(): array {
		// TODO: Implement getBreadcrumbs() method.
		return [];
	}
}
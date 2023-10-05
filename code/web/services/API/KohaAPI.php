<?php
require_once ROOT_DIR . '/sys/CurlWrapper.php';

class KohaAPI {

	public $accountProfile;
	private $apiKey;
	private $oauthToken = null;
	private $basicAuthToken = null;

	/** @var CurlWrapper */
	private $apiCurlWrapper;
	/**@var CurlWrapper */
	private $curlWrapper;

	function __construct($accountProfile){
		$this->accountProfile = $accountProfile;
	}

	function getOAuthToken() {
		if ($this->oauthToken == null) {
			$apiUrl = $this->getWebServiceUrl() . "/api/v1/oauth/token";
			$postParams = [
				'grant_type' => 'client_credentials',
				'client_id' => $this->accountProfile->oAuthClientId,
				'client_secret' => $this->accountProfile->oAuthClientSecret,
			];

			$this->curlWrapper->addCustomHeaders([
				'Accept: application/json',
				'Content-Type: application/x-www-form-urlencoded',
			], false);
			$response = $this->curlWrapper->curlPostPage($apiUrl, $postParams);
			$json_response = json_decode($response);
			ExternalRequestLogEntry::logRequest('koha.getOAuthToken', 'POST', $apiUrl, $this->curlWrapper->getHeaders(), json_encode($postParams), $this->curlWrapper->getResponseCode(), $response, ['client_secret' => $this->accountProfile->oAuthClientSecret]);
			if (!empty($json_response->access_token)) {
				$this->oauthToken = $json_response->access_token;
			} else {
				$this->oauthToken = false;
			}
		}
		return $this->oauthToken;
	}

	function getBasicAuthToken() {
		if ($this->basicAuthToken == null) {
			$client = UserAccount::getActiveUserObj();
			$client_id = $client->getBarcode();
			$client_secret = $client->getPasswordOrPin();
			$this->basicAuthToken = base64_encode($client_id . ":" . $client_secret);
		}
		return $this->basicAuthToken;
	}

	function refreshToken() {
	}

	public function getWebServiceURL(): string {
		$webServiceURL = null;
		if (!empty($this->accountProfile->patronApiUrl)) {
			$webServiceURL = trim($this->accountProfile->patronApiUrl);
		} else {
			global $logger;
			$logger->log('No Web Service URL defined in account profile', Logger::LOG_ALERT);
		}
		$webServiceURL = rtrim($webServiceURL, '/'); // remove any trailing slash because other functions will add it.

		return $webServiceURL;
	}

	public function get($endpoint,$useOAuth = true): mixed {
		$apiUrl = $this->getWebServiceURL() . $endpoint;
		$token = $useOAuth ? $this->getOAuthToken() : $this->getBasicAuthToken();
		$this->curlWrapper->addCustomHeaders([
			'Authorization: Bearer ' . $token,
			'User-Agent: Aspen Discovery',
			'Accept: */*',
			'Cache-Control: no-cache',
			'Content-Type: application/json',
			'Host: ' . preg_replace('~http[s]?://~', '', $this->getWebServiceURL()),
			'Accept-Encoding: gzip, deflate',
		],false);
		$response = json_decode($this->apiCurlWrapper->curlSendPage($apiUrl,'GET'));
		$responseCode = $this->apiCurlWrapper->getResponseCode();
		$headers = $this->apiCurlWrapper->getHeaders();
		if ($responseCode == 200){
			ExternalRequestLogEntry::logRequest('koha-api.get', 'GET', $apiUrl, $headers, "", $responseCode, $response, []);
			return $response;
		} else {
			return false;
		}
	}

	public function post($endpoint,$params,$useOAuth = true): mixed {
		$apiUrl = $this->getWebServiceURL() . $endpoint;
		$token  = $useOAuth ? $this->getOAuthToken() : $this->getBasicAuthToken();
		//Setting headers
		$this->curlWrapper->addCustomHeaders([
			'Authorization: Bearer ' . $token,
			'User-Agent: Aspen Discovery',
			'Accept: */*',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=UTF-8',
			'Host: ' . preg_replace('~http[s]?://~', '', $this->getWebServiceURL()),
		],false);
		//Getting headers
		$headers = $this->apiCurlWrapper->getHeaders();
		//Encoding params
		$postParams = json_encode($params);
		//Getting response body
		$response = json_decode($this->apiCurlWrapper->curlSendPage($apiUrl,'POST',$postParams));
		$responseCode = $this->apiCurlWrapper->getResponseCode();
		if ($responseCode == 201){
			//Saving log request
			ExternalRequestLogEntry::logRequest('koha-api.post', 'POST', $apiUrl, $headers, $postParams, $responseCode, $response, []);
			return $response;
		} else {
			return false;
		}
	}

	public function put($endpoint,$params,$useOAuth = true): mixed {
		$apiUrl = $this->getWebServiceURL() . $endpoint;
		$token  = $useOAuth ? $this->getOAuthToken() : $this->getBasicAuthToken();
		//Setting headers
		$this->apiCurlWrapper->addCustomHeaders([
			'Authorization: Bearer ' . $token,
			'User-Agent: Aspen Discovery',
			'Accept: */*',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=UTF-8',
			'Host: ' . preg_replace('~http[s]?://~', '', $this->getWebServiceURL()),
		], true);
		//Getting headers
		$headers = $this->apiCurlWrapper->getHeaders();
		//Encoding params
		$postParams = json_encode($params);
		//Getting response body
		$response = $this->apiCurlWrapper->curlSendPage($apiUrl, 'PUT', $postParams);
		$responseCode = $this->apiCurlWrapper->getResponseCode();
		if ($responseCode == 200){
			//Saving log request
			ExternalRequestLogEntry::logRequest('koha-api.put', 'PUT', $apiUrl, $headers, $postParams, $responseCode, $response, []);
			return $response;
		} else {
			return false;
		}
	}

	public function delete($endpoint,$useOAuth = true): mixed {
		$apiUrl = $this->getWebServiceURL() . $endpoint;
		$token  = $useOAuth ? $this->getOAuthToken() : $this->getBasicAuthToken();
		//Setting headers
		$this->apiCurlWrapper->addCustomHeaders([
			'Authorization: Bearer ' . $token,
			'User-Agent: Aspen Discovery',
			'Accept: */*',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=UTF-8',
			'Host: ' . preg_replace('~http[s]?://~', '', $this->getWebServiceURL()),
		], true);
		//Getting headers
		$headers = $this->apiCurlWrapper->getHeaders();
		//Getting response body
		$response = $this->apiCurlWrapper->curlSendPage($apiUrl, 'DELETE');
		$responseCode = $this->apiCurlWrapper->getResponseCode();
		if ($responseCode == 204){
			//Saving log request
			ExternalRequestLogEntry::logRequest('koha-api.delete', 'DELETE', $apiUrl, $headers, "", $responseCode, $response, []);
			return $response;
		} else {
			return false;
		}
	}

}


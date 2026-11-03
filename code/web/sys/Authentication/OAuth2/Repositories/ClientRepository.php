<?php

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

require_once ROOT_DIR . '/sys/Authentication/OAuth2/OAuth2Client.php';
require_once ROOT_DIR . '/sys/Authentication/OAuth2/OpenIDClient.php';
require_once ROOT_DIR . '/sys/Authentication/OAuth2/Entities/OAuth2ClientEntity.php';

class ClientRepository implements ClientRepositoryInterface {

	public function getClientEntity($clientIdentifier): ?ClientEntityInterface {
		global $logger;

		$client = $this->findOAuth2Client($clientIdentifier);
		$clientType = 'oauth2';

		if ($client === null) {
			$client = $this->findOpenIDClient($clientIdentifier);
			$clientType = 'openid';
		}

		if ($client === null) {
			$logger->log("[OAuth2] ClientRepository::getClientEntity() - CLIENT NOT FOUND for ID: " . $clientIdentifier, Logger::LOG_DEBUG);
			return null;
		}

		$logger->log("[OAuth2] ClientRepository::getClientEntity() - FOUND {$clientType} client: " . $client->getName(), Logger::LOG_DEBUG);

		$clientEntity = new OAuth2ClientEntity();
		$clientEntity->setIdentifier($client->getClientId());
		$clientEntity->setName($client->getName());
		$clientEntity->setRedirectUri($client->getRedirectUri());

		$clientType = ($client instanceof OpenIDClient) ? 'web_application' : $client->client_type;
		$isConfidential = ($clientType !== 'native_application');
		$clientEntity->setIsConfidential($isConfidential);

		return $clientEntity;
	}

	public function validateClient($clientIdentifier, $clientSecret, $grantType): bool {
		global $logger;

		$client = $this->findOAuth2Client($clientIdentifier);
		$clientType = 'oauth2';

		if ($client === null) {
			$client = $this->findOpenIDClient($clientIdentifier);
			$clientType = 'openid';
		}

		if ($client === null) {
			$logger->log("[OAuth2] ClientRepository::validateClient() - Client not found", Logger::LOG_DEBUG);
			return false;
		}
		$logger->log("[OAuth2] ClientRepository::validateClient() - Found {$clientType} client: " . $client->getName(), Logger::LOG_DEBUG);

		// Only public clients may use the password grant without their secret
		if ($grantType === 'password' && $client instanceof OAuth2Client && $client->client_type === 'native_application') {
			return true;
		}

		$storedSecret = $client->getClientSecret();
		if (!empty($storedSecret) && is_string($clientSecret) && hash_equals($storedSecret, $clientSecret)) {
			$logger->log("[OAuth2] ClientRepository::validateClient() - Client secret matched", Logger::LOG_DEBUG);
			return true;
		}

		$logger->log("[OAuth2] ClientRepository::validateClient() - Client secret mismatch", Logger::LOG_DEBUG);
		return false;
	}

	private function findOAuth2Client(string $clientIdentifier): ?OAuth2Client {
		$client = new OAuth2Client();
		$client->setClientId($clientIdentifier);
		$client->setIsActive(1);
		return $client->find(true) ? $client : null;
	}

	private function findOpenIDClient(string $clientIdentifier): ?OpenIDClient {
		$client = new OpenIDClient();
		$client->setClientId($clientIdentifier);
		$client->setIsActive(1);
		return $client->find(true) ? $client : null;
	}
}

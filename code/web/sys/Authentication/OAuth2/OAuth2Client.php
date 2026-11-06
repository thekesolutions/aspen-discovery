<?php
require_once ROOT_DIR . '/sys/DB/DataObject.php';
require_once ROOT_DIR . '/sys/UserAccount.php';

class OAuth2Client extends DataObject {
	public $__table = 'oauth2_clients';
	protected $id;
	protected $name;
	protected $client_id;
	protected $client_secret;
	protected $client_type;
	protected $scopes;
	protected $redirect_uri;
	protected $is_active;
	protected $rate_limit_requests;
	protected $rate_limit_window;
	protected $created_by;
	protected $created_date;
	protected $last_modified;
	
	static $_objectStructure = [];

	static function getObjectStructure(string $context = ''): array {
		if (isset(self::$_objectStructure[$context])) {
			return self::$_objectStructure[$context];
		}

		$scopesOptions = self::getScopeOptions();

		$structure = [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id within the database',
			],
			'name' => [
				'property' => 'name',
				'type' => 'text',
				'label' => 'Client Name',
				'description' => 'A unique descriptive name for this OAuth2 client',
				'maxLength' => 255,
				'required' => true,
				'readOnly' => ($context !== 'addNew'),
			],
			'client_id' => [
				'property' => 'client_id',
				'type' => 'text',
				'label' => 'Client ID',
				'description' => 'The OAuth2 client identifier',
				'maxLength' => 255,
				'required' => true,
				'readOnly' => ($context !== 'addNew'),
			],
			'client_secret' => [
				'property' => 'client_secret',
				'type' => 'password',
				'label' => 'Client Secret',
				'description' => 'The OAuth2 client secret',
				'maxLength' => 255,
				'hideInLists' => true,
				'readOnly' => ($context !== 'addNew'),
			],
			'client_type' => [
				'property' => 'client_type',
				'type' => 'enum',
				'label' => 'Client Type',
				'description' => 'The type of OAuth2 client',
				'values' => [
					'web_application' => 'Web Application (Authorization Code)',
					'native_application' => 'Native/Mobile App (Password Grant)',
					'service_application' => 'Service/API Client (Client Credentials)',
				],
				'default' => 'web_application',
				'readOnly' => ($context !== 'addNew'),
				'onchange' => 'AspenDiscovery.Admin.updateOAuth2GrantType();',
			],
			'scopes' => [
				'property' => 'scopes',
				'type' => 'multiSelect',
				'label' => 'Allowed Scopes',
				'description' => 'The scopes this client is allowed to request',
				'listStyle' => 'checkboxSimple',
				'note' => 'Select the minimum number of scopes required for this client',
				'values' => $scopesOptions,
				'onchange' => 'AspenDiscovery.Admin.updateOAuth2Scopes(this)',
			],
			'redirect_uri' => [
				'property' => 'redirect_uri',
				'type' => 'url',
				'label' => 'Redirect URI',
				'description' => 'Valid redirect URI for this client',
				'maxLength' => 2000,
				'note' => 'Required for Web Applications (Authorization Code flow)',
			],
			'is_active' => [
				'property' => 'is_active',
				'type' => 'checkbox',
				'label' => 'Active',
				'description' => 'Whether this client is currently active',
				'default' => true,
			],
			'rate_limit_requests' => [
				'property' => 'rate_limit_requests',
				'type' => 'integer',
				'label' => 'Rate Limit Requests',
				'description' => 'The number of API requests this client may make to each API per rate limit window',
				'note' => 'Leave as 0 to use the default limit of each API',
				'min' => 0,
				'default' => 0,
				'hideInLists' => true,
			],
			'rate_limit_window' => [
				'property' => 'rate_limit_window',
				'type' => 'integer',
				'label' => 'Rate Limit Window (seconds)',
				'description' => 'The length of the rate limit window in seconds',
				'note' => 'Leave as 0 to use the default limit of each API',
				'min' => 0,
				'default' => 0,
				'hideInLists' => true,
			],
			'created_by' => [
				'property' => 'created_by',
				'type' => 'label',
				'label' => 'Created By',
				'description' => 'The user who created this client',
				'default' => UserAccount::getActiveUserId(),
				'readonly' => true,
			],
			'created_date' => [
				'property' => 'created_date',
				'type' => 'date',
				'label' => 'Created',
				'description' => 'When this client was created',
				'readOnly' => ($context !== 'addNew'),
			],
			'last_modified' => [
				'property' => 'last_modified',
				'type' => 'date',
				'label' => 'Last Modified',
				'description' => 'When this client was last modified',
				'readOnly' => ($context !== 'addNew'),
			]
		];

		if ($context == 'addNew') {
			unset($structure['client_id']);
			unset($structure['client_secret']);
			unset($structure['created_by']);
			unset($structure['created_date']);
			unset($structure['last_modified']);
		} elseif ($context !== '') {
			// For existing clients, make client_secret read-only instead of password field
			$structure['client_id']['type'] = 'label';
			$structure['client_secret']['type'] = 'label';
			$structure['client_secret']['description'] = 'The OAuth2 client secret (hidden for security)';
		}

		self::$_objectStructure[$context] = $structure;
		return self::$_objectStructure[$context];
	}

	function getNumericColumnNames(): array {
		return [
			'id',
			'rate_limit_requests',
			'rate_limit_window',
			'created_by'
		];
	}

	/**
	 * The rate limit set for this client, or null when it uses the default limit of each API.
	 */
	public function getRateLimit(): ?array {
		if (empty($this->rate_limit_requests) || empty($this->rate_limit_window)) {
			return null;
		}
		return [
			'requests' => (int)$this->rate_limit_requests,
			'window' => (int)$this->rate_limit_window,
			'description' => 'Client rate limit',
		];
	}

	function getEncryptedFieldNames(): array {
		return ['client_secret'];
	}

	static function getScopeOptions(): array {
		return [
			'work:read' => 'Work API Read Access',
			'user:read' => 'User API Read Access',
			'user:write' => 'User API Write Access',
			'list:read' => 'List API Read Access',
			'list:write' => 'List API Write Access',
			'item:read' => 'Item API Read Access',
			'event:read' => 'Event API Read Access',
			'event:write' => 'Event API Write Access',
			'search:read' => 'Search API Read Access',
			'system:read' => 'System API Read Access',
			'fine:read' => 'Fine API Read Access',
			'community:read' => 'Community API Read Access',
			'community:write' => 'Community API Write Access',
			'storage:write' => 'Storage API Write Access',
		];
	}

	public function fetch(): bool|DataObject|null {
		$result = parent::fetch();
		if ($result && !empty($this->scopes)) {
			if (is_string($this->scopes)) {
				$scopesArray = array_filter(array_map('trim', explode(',', $this->scopes)));
				$validScopes = self::getScopeOptions();
				$selectedScopes = array_filter($validScopes, function ($scopeKey) use ($scopesArray) {
					return in_array($scopeKey, $scopesArray);
				}, ARRAY_FILTER_USE_KEY);
				$this->scopes = $selectedScopes;
			}
		}

		return $result;
	}

	public function insert($context = ''): bool|int {
		$this->created_date = date('Y-m-d H:i:s');
		$this->last_modified = date('Y-m-d H:i:s');
		$this->created_by = UserAccount::getActiveUserId();
		$this->processScopes();

		$this->generateClientSecret();
		$this->generateClientId();

		$ret = parent::insert();
		return $ret;
	}

	public function update($context = ''): bool|int {
		$this->last_modified = date('Y-m-d H:i:s');
		$this->processScopes();
		return parent::update($context);
	}

	public function processScopes(): void {
		if (is_array($this->scopes)) {
			$this->scopes = implode(',', $this->scopes);
		}
	}

	private function generateClientId(): void {
		$this->__set('client_id', 'aspen_' . bin2hex(random_bytes(16)));
	}

	private function generateClientSecret(): void {
		$this->__set('client_secret', bin2hex(random_bytes(32)));
	}

	/**
	 * Replaces the secret and revokes every token issued to the client, so the old secret and
	 * anything obtained with it stop working right away.
	 */
	public function regenerateClientSecret(): bool {
		if (empty($this->id)) {
			return false;
		}
		$this->generateClientSecret();
		if (!$this->update()) {
			return false;
		}
		$clientId = $this->escape($this->client_id);
		$this->query("UPDATE oauth2_refresh_tokens INNER JOIN oauth2_access_tokens ON oauth2_refresh_tokens.access_token_id = oauth2_access_tokens.token_id SET oauth2_refresh_tokens.revoked = 1 WHERE oauth2_access_tokens.client_id = $clientId");
		$this->query("UPDATE oauth2_access_tokens SET revoked = 1 WHERE client_id = $clientId");
		return true;
	}

	public function getScopesArray(): array {
		if (empty($this->scopes)) {
			return [];
		}
		if (is_string($this->scopes)) {
			return array_filter(array_map('trim', explode(',', $this->scopes)));
		}
		if (is_array($this->scopes)) {
			return array_filter(array_map('trim', $this->scopes));
		}
		return [];
	}

	/**
	 * Check if this client has a specific scope
	 */
	public function hasScope(string $scope): bool {
		return in_array($scope, $this->getScopesArray());
	}

	/**
	 * Check if this client has all the required scopes
	 */
	public function hasAllScopes(array $requiredScopes): bool {
		$clientScopes = $this->getScopesArray();
		foreach ($requiredScopes as $scope) {
			if (!in_array($scope, $clientScopes)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if this client has any of the provided scopes
	 */
	public function hasAnyScope(array $scopes): bool {
		$clientScopes = $this->getScopesArray();
		foreach ($scopes as $scope) {
			if (in_array($scope, $clientScopes)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get scope labels for display
	 */
	public function getScopeLabels(): array {
		$scopeOptions = self::getScopeOptions();
		$labels = [];
		foreach ($this->getScopesArray() as $scope) {
			if (isset($scopeOptions[$scope])) {
				$labels[$scope] = $scopeOptions[$scope];
			}
		}
		return $labels;
	}

	public function setClientId(string $clientId): void {
		$this->client_id = $clientId;
	}

	public function getClientId(): string {
		return $this->client_id;
	}

	public function getClientSecret(): string {
		return $this->client_secret;
	}

	public function setIsActive(bool $isActive): void {
		$this->is_active = $isActive;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getRedirectUri(): string {
		return $this->redirect_uri;
	}
}

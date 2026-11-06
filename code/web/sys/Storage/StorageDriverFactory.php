<?php

require_once ROOT_DIR . '/sys/Storage/LocalStorageDriver.php';

class StorageDriverFactory {
	private static ?StorageDriver $instance = null;
	private static ?StorageDriver $localInstance = null;
	private static array $instancesById = [];
	private static ?int $activeSettingId = null;
	private static ?int $localSettingId = null;

	public static function get(): StorageDriver {
		global $logger;
		if (self::$instance === null) {
			self::$instance = self::create();
		}
		$logger->log('StorageDriverFactory::get() resolved active driver ' . get_class(self::$instance), Logger::LOG_DEBUG);
		return self::$instance;
	}

	// Resolves the driver a specific file was actually written to, which may
	// differ from the currently active driver returned by get(). $id is a
	// storage_settings.id; null means Local Storage, since that was the only
	// backend before per-file tracking existed.
	public static function getById(?int $id): StorageDriver {
		global $logger;
		if ($id === null) {
			$logger->log('StorageDriverFactory::getById(null) resolved LocalStorageDriver', Logger::LOG_DEBUG);
			return self::getLocalDriver();
		}
		if (!isset(self::$instancesById[$id])) {
			self::$instancesById[$id] = self::createFromId($id);
		}
		$logger->log("StorageDriverFactory::getById($id) resolved " . get_class(self::$instancesById[$id]), Logger::LOG_DEBUG);
		return self::$instancesById[$id];
	}

	// Allows tests to inject a driver without touching config.
	public static function set(StorageDriver $driver): void {
		self::$instance = $driver;
	}

	public static function reset(): void {
		self::$instance = null;
		self::$localInstance = null;
		self::$instancesById = [];
		self::$activeSettingId = null;
		self::$localSettingId = null;
	}

	/**
	 * The storage_settings row new files are written to.
	 */
	public static function getActiveSettingId(): int {
		if (self::$activeSettingId === null) {
			global $aspen_db;
			$id = $aspen_db->query('SELECT id FROM storage_settings WHERE isActive = 1 ORDER BY id LIMIT 1')->fetchColumn();
			self::$activeSettingId = $id === false ? self::getLocalSettingId() : (int)$id;
		}
		return self::$activeSettingId;
	}

	/**
	 * The storage_settings row that legacy files belong to, since they were all written to local disk.
	 */
	public static function getLocalSettingId(): int {
		if (self::$localSettingId === null) {
			global $aspen_db;
			$id = $aspen_db->query("SELECT id FROM storage_settings WHERE driver = 'local' ORDER BY id LIMIT 1")->fetchColumn();
			self::$localSettingId = (int)$id;
		}
		return self::$localSettingId;
	}

	public static function resolveDataRoot(): string {
		global $configArray, $serverName;
		return $configArray['Site']['dataPath'] ?? '/data/aspen-discovery/' . $serverName;
	}

	// The docroot, base for LocalStorageDriver's files/images/fonts keys.
	public static function resolvePublicRoot(): string {
		global $configArray;
		return $configArray['Site']['local'];
	}

	public static function resolveGroupOwner(): ?string {
		global $configArray;
		// An empty value disables the chgrp
		return ($configArray['Site']['fileGroupOwner'] ?? '') ?: null;
	}

	private static function create(): StorageDriver {
		return self::getLocalDriver();
	}

	private static function createFromId(int $id): StorageDriver {
		return self::getLocalDriver();
	}

	private static function getLocalDriver(): StorageDriver {
		if (self::$localInstance === null) {
			self::$localInstance = new LocalStorageDriver(self::resolveDataRoot(), self::resolvePublicRoot(), self::resolveGroupOwner());
		}
		return self::$localInstance;
	}
}

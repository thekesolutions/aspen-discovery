<?php

require_once ROOT_DIR . '/sys/Storage/StorageDriverFactory.php';

/**
 * Builds keys for files written before the storage registry existed. Those files always live on
 * local disk, so the key is the path relative to the public or data root, using the same prefix
 * rules as LocalStorageDriver, or the absolute path when the file is outside both roots.
 */
class LegacyStorageKey {
	public static function forPath(string $absolutePath): string {
		$publicRoot = rtrim(StorageDriverFactory::resolvePublicRoot(), '/');
		$dataRoot = rtrim(StorageDriverFactory::resolveDataRoot(), '/');
		foreach ([$publicRoot, $dataRoot] as $root) {
			if (str_starts_with($absolutePath, $root . '/')) {
				$relativePath = substr($absolutePath, strlen($root) + 1);
				// The driver picks the root from the prefix, so the key must resolve back to the same root.
				if (($root == $publicRoot) == LocalStorageDriver::isPublicKey($relativePath)) {
					return StorageDriver::LEGACY_KEY_PREFIX . $relativePath;
				}
			}
		}
		return StorageDriver::LEGACY_KEY_PREFIX . $absolutePath;
	}
}

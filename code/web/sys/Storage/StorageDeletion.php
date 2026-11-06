<?php /** @noinspection PhpMissingFieldTypeInspection */

/**
 * Files released from the registry. Their bytes are deleted by purgeDue() once the storage
 * setting's delete delay has passed, so pages and caches that still point at the old URL keep
 * working for a while.
 */
class StorageDeletion extends DataObject {
	public $__table = 'storage_deletions';
	public $id;
	public $storageSettingId;
	public $storageKey;
	public $dateQueued;

	public function getNumericColumnNames(): array {
		return [
			'id',
			'storageSettingId',
			'dateQueued',
		];
	}

	public static function queue(int $storageSettingId, string $storageKey): void {
		$storageDeletion = new StorageDeletion();
		$storageDeletion->storageSettingId = $storageSettingId;
		$storageDeletion->storageKey = $storageKey;
		$storageDeletion->dateQueued = time();
		$storageDeletion->insert();
	}

	/**
	 * Deletes the bytes of every queued file whose delay has passed. A key that is registered again
	 * in the meantime (a legacy file claimed by another owner) is only removed from the queue.
	 *
	 * @return int[] number of files deleted and number of errors
	 */
	public static function purgeDue(): array {
		global $aspen_db;
		require_once ROOT_DIR . '/sys/Storage/StorageDriverFactory.php';
		$now = time();
		$dueRows = $aspen_db->query("SELECT d.id, d.storageSettingId, d.storageKey FROM storage_deletions d LEFT JOIN storage_settings s ON s.id = d.storageSettingId WHERE d.dateQueued <= $now - COALESCE(s.deleteDelayDays, 7) * 86400")->fetchAll(PDO::FETCH_ASSOC);
		$numDeleted = 0;
		$numErrors = 0;
		foreach ($dueRows as $dueRow) {
			$isStillRegistered = $aspen_db->query('SELECT COUNT(*) FROM storage_objects WHERE storageSettingId = ' . (int)$dueRow['storageSettingId'] . ' AND storageKey = ' . $aspen_db->quote($dueRow['storageKey']))->fetchColumn() > 0;
			if (!$isStillRegistered) {
				$driver = StorageDriverFactory::getById((int)$dueRow['storageSettingId']);
				if ($driver->exists($dueRow['storageKey']) && !$driver->delete($dueRow['storageKey'])) {
					$numErrors++;
					continue;
				}
				$numDeleted++;
			}
			$aspen_db->exec('DELETE FROM storage_deletions WHERE id = ' . (int)$dueRow['id']);
		}
		return [$numDeleted, $numErrors];
	}
}

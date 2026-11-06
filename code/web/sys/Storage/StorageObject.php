<?php /** @noinspection PhpMissingFieldTypeInspection */

require_once ROOT_DIR . '/sys/Storage/StorageDriverFactory.php';
require_once ROOT_DIR . '/sys/Storage/StorageDeletion.php';
require_once ROOT_DIR . '/sys/Storage/LegacyStorageKey.php';

/**
 * One row per stored file. The key is random and never reused, so a file's URL never changes while
 * it exists. Each row fills a slot of its owner: the owner row (ownerTable, ownerId), the property
 * (ownerField) and the variant of that property ('' for the file itself, or thumbnail, medium,
 * small, large). The fingerprint describes what the file was built from. Usually a slot holds one
 * file and a new fingerprint replaces the old one; a property whose files differ by what they were
 * built from (generated covers, one per look of the library's themes) keeps one file per
 * fingerprint instead. The unique index allows one file per slot and fingerprint.
 */
class StorageObject extends DataObject {
	public $__table = 'storage_objects';
	public $id;
	public $storageSettingId;
	public $storageKey;
	public $originalName;
	public $mimeType;
	public $size;
	public $sha256;
	public $ownerTable;
	public $ownerId;
	public $ownerField;
	public $variant;
	public $fingerprint;
	public $dateCreated;

	// Tables that own registry files and their primary key, used to find files whose owner is gone.
	const OWNER_TABLES = [
	];

	private const MIME_EXTENSIONS = [
		'image/png' => 'png',
		'image/jpeg' => 'jpg',
		'image/gif' => 'gif',
		'image/svg+xml' => 'svg',
		'image/webp' => 'webp',
		'image/x-icon' => 'ico',
		'image/vnd.microsoft.icon' => 'ico',
	];

	/** @var StorageObject[][][][] files already loaded in this request, by "ownerTable:ownerId", ownerField, variant and fingerprint */
	private static array $ownerCache = [];

	public function getNumericColumnNames(): array {
		return [
			'id',
			'storageSettingId',
			'size',
			'ownerId',
			'dateCreated',
		];
	}

	public static function newKey(string $extension = ''): string {
		$key = bin2hex(random_bytes(16));
		$extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension));
		if (!empty($extension) && strlen($extension) <= 8) {
			$key .= '.' . $extension;
		}
		return $key;
	}

	private static function extensionFor(string $originalName, string $mimeType): string {
		if (isset(self::MIME_EXTENSIONS[$mimeType])) {
			return self::MIME_EXTENSIONS[$mimeType];
		}
		return pathinfo($originalName, PATHINFO_EXTENSION);
	}

	/**
	 * Writes the file to the active backend under a new key and puts it in the owner's slot. The
	 * file the slot held before is queued for deletion; with $keepOtherFingerprints only the file
	 * with the same fingerprint is replaced.
	 */
	public static function store(string $sourcePath, string $ownerTable, int $ownerId, string $ownerField, string $variant = '', string $fingerprint = '', string $originalName = '', string $mimeType = '', bool $keepOtherFingerprints = false): ?StorageObject {
		global $logger;
		if (!is_readable($sourcePath)) {
			$logger->log("StorageObject::store() cannot read $sourcePath", Logger::LOG_ERROR);
			return null;
		}
		if (empty($mimeType)) {
			$mimeType = mime_content_type($sourcePath) ?: '';
		}
		$settingId = StorageDriverFactory::getActiveSettingId();
		$driver = StorageDriverFactory::getById($settingId);
		$key = self::newKey(self::extensionFor($originalName, $mimeType));
		if (!$driver->write($key, $sourcePath, $mimeType)) {
			$logger->log("StorageObject::store() could not write $key for $ownerTable $ownerId $ownerField $variant", Logger::LOG_ERROR);
			return null;
		}

		$storageObject = self::describe($sourcePath, $ownerTable, $ownerId, $ownerField, $variant, $fingerprint, $originalName, $mimeType);
		$storageObject->storageSettingId = $settingId;
		$storageObject->storageKey = $key;
		$storageObject->dateCreated = time();
		if (!self::putInSlot($storageObject, $keepOtherFingerprints)) {
			// Bytes before row: without the row nothing points at the file, so remove it right away.
			$driver->delete($key);
			return null;
		}
		return $storageObject;
	}

	/**
	 * Registers a file written before the registry existed, where it is, without moving it. If
	 * another slot already holds the same file (objects copied before the registry shared
	 * filenames), the bytes are copied to a new key so each owner can delete its own file.
	 */
	public static function registerLegacy(string $absolutePath, string $ownerTable, int $ownerId, string $ownerField, string $variant = ''): ?StorageObject {
		if (!is_readable($absolutePath)) {
			return null;
		}
		$settingId = StorageDriverFactory::getLocalSettingId();
		$key = LegacyStorageKey::forPath($absolutePath);

		$existing = new StorageObject();
		$existing->storageSettingId = $settingId;
		$existing->storageKey = $key;
		if ($existing->find(true)) {
			if ($existing->ownerTable == $ownerTable && $existing->ownerId == $ownerId && $existing->ownerField == $ownerField && $existing->variant == $variant && $existing->fingerprint === '') {
				return $existing;
			}
			return self::store($absolutePath, $ownerTable, $ownerId, $ownerField, $variant, '', basename($absolutePath));
		}

		$storageObject = self::describe($absolutePath, $ownerTable, $ownerId, $ownerField, $variant, '', basename($absolutePath), '');
		$storageObject->storageSettingId = $settingId;
		$storageObject->storageKey = $key;
		$storageObject->dateCreated = filemtime($absolutePath) ?: time();
		return self::putInSlot($storageObject, false) ? $storageObject : null;
	}

	private static function describe(string $path, string $ownerTable, int $ownerId, string $ownerField, string $variant, string $fingerprint, string $originalName, string $mimeType): StorageObject {
		$storageObject = new StorageObject();
		$storageObject->originalName = mb_substr($originalName, 0, 255);
		$storageObject->mimeType = empty($mimeType) ? (mime_content_type($path) ?: '') : $mimeType;
		$storageObject->size = filesize($path);
		$storageObject->sha256 = hash_file('sha256', $path);
		$storageObject->ownerTable = $ownerTable;
		$storageObject->ownerId = $ownerId;
		$storageObject->ownerField = $ownerField;
		$storageObject->variant = $variant;
		$storageObject->fingerprint = mb_substr($fingerprint, 0, 64);
		return $storageObject;
	}

	/**
	 * Makes the file the one in its slot. The row of the slot is updated in place, so its id stays
	 * the same, and the keys it replaces are queued for deletion in the same transaction. Two
	 * requests filling the same slot at once are serialized by the row lock; if both find the slot
	 * empty, the unique index rejects the second insert and it retries as an update.
	 */
	private static function putInSlot(StorageObject $storageObject, bool $keepOtherFingerprints): bool {
		global $aspen_db, $logger;
		$ownsTransaction = !$aspen_db->inTransaction();
		$maxAttempts = $ownsTransaction ? 2 : 1;
		for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
			try {
				if ($ownsTransaction) {
					$aspen_db->beginTransaction();
				}
				$select = $aspen_db->prepare('SELECT id, storageSettingId, storageKey, fingerprint FROM storage_objects WHERE ownerTable = ? AND ownerId = ? AND ownerField = ? AND variant = ? ORDER BY id FOR UPDATE');
				$select->execute([$storageObject->ownerTable, $storageObject->ownerId, $storageObject->ownerField, $storageObject->variant]);
				$rowToUpdate = null;
				$rowsToRelease = [];
				foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $slotRow) {
					if ($slotRow['fingerprint'] === $storageObject->fingerprint && $rowToUpdate == null) {
						$rowToUpdate = $slotRow;
					} elseif (!$keepOtherFingerprints) {
						$rowsToRelease[] = $slotRow;
					}
				}
				if ($rowToUpdate == null && !empty($rowsToRelease)) {
					$rowToUpdate = array_shift($rowsToRelease);
				}

				$columns = [
					'storageSettingId' => $storageObject->storageSettingId,
					'storageKey' => $storageObject->storageKey,
					'originalName' => $storageObject->originalName,
					'mimeType' => $storageObject->mimeType,
					'size' => $storageObject->size,
					'sha256' => $storageObject->sha256,
					'fingerprint' => $storageObject->fingerprint,
					'dateCreated' => $storageObject->dateCreated,
				];
				if ($rowToUpdate != null) {
					$update = $aspen_db->prepare('UPDATE storage_objects SET ' . implode(', ', array_map(fn($column) => "$column = ?", array_keys($columns))) . ' WHERE id = ?');
					$update->execute([...array_values($columns), $rowToUpdate['id']]);
					$storageObject->id = (int)$rowToUpdate['id'];
					if ($rowToUpdate['storageSettingId'] != $storageObject->storageSettingId || $rowToUpdate['storageKey'] !== $storageObject->storageKey) {
						StorageDeletion::queue((int)$rowToUpdate['storageSettingId'], $rowToUpdate['storageKey']);
					}
				} else {
					$columns['ownerTable'] = $storageObject->ownerTable;
					$columns['ownerId'] = $storageObject->ownerId;
					$columns['ownerField'] = $storageObject->ownerField;
					$columns['variant'] = $storageObject->variant;
					$insert = $aspen_db->prepare('INSERT INTO storage_objects (' . implode(', ', array_keys($columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
					$insert->execute(array_values($columns));
					$storageObject->id = (int)$aspen_db->lastInsertId();
				}
				foreach ($rowsToRelease as $releasedRow) {
					$aspen_db->prepare('DELETE FROM storage_objects WHERE id = ?')->execute([$releasedRow['id']]);
					StorageDeletion::queue((int)$releasedRow['storageSettingId'], $releasedRow['storageKey']);
				}
				if ($ownsTransaction) {
					$aspen_db->commit();
				}
				// An owner that was not loaded yet is left out, so its next lookup loads all of its files.
				$cacheKey = "$storageObject->ownerTable:$storageObject->ownerId";
				if (isset(self::$ownerCache[$cacheKey])) {
					if (!$keepOtherFingerprints) {
						self::$ownerCache[$cacheKey][$storageObject->ownerField][$storageObject->variant] = [];
					}
					self::$ownerCache[$cacheKey][$storageObject->ownerField][$storageObject->variant][$storageObject->fingerprint] = $storageObject;
				}
				return true;
			} catch (PDOException $e) {
				if ($ownsTransaction && $aspen_db->inTransaction()) {
					$aspen_db->rollBack();
				}
				$isDuplicate = $e->getCode() == '23000';
				if (!$isDuplicate || $attempt == $maxAttempts) {
					$logger->log("StorageObject could not fill $storageObject->ownerTable $storageObject->ownerId $storageObject->ownerField '$storageObject->variant' with $storageObject->storageKey: " . $e->getMessage(), Logger::LOG_ERROR);
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * Loads the files of several owners with one query.
	 *
	 * @return StorageObject[][][][] by ownerId, ownerField, variant and fingerprint
	 */
	public static function getForOwners(string $ownerTable, array $ownerIds): array {
		$ownerIds = array_unique(array_filter(array_map('intval', $ownerIds)));
		$toLoad = [];
		foreach ($ownerIds as $ownerId) {
			if (!isset(self::$ownerCache["$ownerTable:$ownerId"])) {
				$toLoad[] = $ownerId;
				self::$ownerCache["$ownerTable:$ownerId"] = [];
			}
		}
		if (!empty($toLoad)) {
			$storageObject = new StorageObject();
			$storageObject->ownerTable = $ownerTable;
			$storageObject->whereAddIn('ownerId', $toLoad, false);
			$storageObject->find();
			while ($storageObject->fetch()) {
				self::$ownerCache["$ownerTable:$storageObject->ownerId"][$storageObject->ownerField][$storageObject->variant][$storageObject->fingerprint] = clone $storageObject;
			}
		}
		$result = [];
		foreach ($ownerIds as $ownerId) {
			$result[$ownerId] = self::$ownerCache["$ownerTable:$ownerId"];
		}
		return $result;
	}

	/**
	 * @return StorageObject[][][] by ownerField, variant and fingerprint
	 */
	public static function getForOwner(string $ownerTable, int $ownerId): array {
		return self::getForOwners($ownerTable, [$ownerId])[$ownerId] ?? [];
	}

	/**
	 * The file in a slot. Without a fingerprint, the one file of the slot; with one, the file built
	 * from it.
	 */
	public static function getFile(string $ownerTable, int $ownerId, string $ownerField, string $variant = '', ?string $fingerprint = null): ?StorageObject {
		$filesInSlot = self::getForOwner($ownerTable, $ownerId)[$ownerField][$variant] ?? [];
		if ($fingerprint === null) {
			return reset($filesInSlot) ?: null;
		}
		return $filesInSlot[$fingerprint] ?? null;
	}

	/**
	 * Removes the owner's files from the registry, every variant included, and queues their bytes
	 * for deletion.
	 *
	 * @param string[]|null $ownerFields  null releases every file of the owner
	 */
	public static function release(string $ownerTable, int $ownerId, ?array $ownerFields = null): int {
		if ($ownerFields !== null && empty($ownerFields)) {
			return 0;
		}
		$storageObject = new StorageObject();
		$storageObject->ownerTable = $ownerTable;
		$storageObject->ownerId = $ownerId;
		if ($ownerFields !== null) {
			$storageObject->whereAddIn('ownerField', $ownerFields, true);
		}
		$toRelease = $storageObject->fetchAll();
		foreach ($toRelease as $releasedObject) {
			$releasedObject->releaseThis();
		}
		return count($toRelease);
	}

	/**
	 * Deletes this row and queues its bytes. The row goes first so a crash leaves at most an
	 * unreferenced file, never a row that points at nothing.
	 */
	public function releaseThis(): void {
		$settingId = (int)$this->storageSettingId;
		$key = $this->storageKey;
		$cacheKey = "$this->ownerTable:$this->ownerId";
		$cachedFile = self::$ownerCache[$cacheKey][$this->ownerField][$this->variant][$this->fingerprint] ?? null;
		if ($cachedFile != null && $cachedFile->id == $this->id) {
			unset(self::$ownerCache[$cacheKey][$this->ownerField][$this->variant][$this->fingerprint]);
		}
		if ($this->delete()) {
			StorageDeletion::queue($settingId, $key);
		}
	}

	/**
	 * Copies the owner's files, every variant included, to another owner with new keys, e.g. when
	 * an object is copied.
	 *
	 * @param string[]|null $ownerFields  null copies every file
	 */
	public static function copyFiles(string $ownerTable, int $fromOwnerId, int $toOwnerId, ?array $ownerFields = null): int {
		$numCopied = 0;
		foreach (self::getForOwner($ownerTable, $fromOwnerId) as $ownerField => $variants) {
			if ($ownerFields !== null && !in_array($ownerField, $ownerFields)) {
				continue;
			}
			foreach ($variants as $variant => $filesInSlot) {
				foreach ($filesInSlot as $source) {
					$bytes = $source->read();
					if ($bytes === false) {
						continue;
					}
					$tempFile = tempnam(sys_get_temp_dir(), 'aspen_storage_');
					if (file_put_contents($tempFile, $bytes) !== false && self::store($tempFile, $ownerTable, $toOwnerId, $ownerField, (string)$variant, $source->fingerprint, $source->originalName, $source->mimeType, count($filesInSlot) > 1) != null) {
						$numCopied++;
					}
					@unlink($tempFile);
				}
			}
		}
		return $numCopied;
	}

	/**
	 * Releases files whose owner row no longer exists, e.g. objects removed with a bulk delete.
	 */
	public static function sweepOrphans(): int {
		global $aspen_db;
		$numReleased = 0;
		foreach (self::OWNER_TABLES as $ownerTable => $primaryKey) {
			$orphanIds = $aspen_db->query("SELECT so.id FROM storage_objects so LEFT JOIN $ownerTable o ON o.$primaryKey = so.ownerId WHERE so.ownerTable = " . $aspen_db->quote($ownerTable) . " AND o.$primaryKey IS NULL")->fetchAll(PDO::FETCH_COLUMN);
			foreach ($orphanIds as $orphanId) {
				$storageObject = new StorageObject();
				$storageObject->id = $orphanId;
				if ($storageObject->find(true)) {
					$storageObject->releaseThis();
					$numReleased++;
				}
			}
		}
		return $numReleased;
	}

	public function getDriver(): StorageDriver {
		return StorageDriverFactory::getById((int)$this->storageSettingId);
	}

	/**
	 * Public URL of the file, or an empty string if the backend cannot serve it directly.
	 */
	public function getUrl(): string {
		return $this->getDriver()->url($this->storageKey);
	}

	public function read(): string|false {
		return $this->getDriver()->read($this->storageKey);
	}

	public function readStream() {
		return $this->getDriver()->readStream($this->storageKey);
	}
}

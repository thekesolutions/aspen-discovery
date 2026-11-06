<?php /** @noinspection PhpMissingFieldTypeInspection */


class BookCoverInfo extends DataObject {
	public $__table = 'bookcover_info';    // table name
	// The storage registry property that holds the record's uploaded cover ('') and cached sizes
	const STORAGE_FIELD = 'cover';
	// Generated covers of each size, one per look of the libraries' themes (the fingerprint)
	const DEFAULT_COVER_STORAGE_FIELD = 'default_cover';
	// Covers of these record types are uploaded to their own folders, outside the storage registry
	const NOT_UPLOADED_RECORD_TYPES = [
		'list',
		'series',
		'seriesMember',
		'unknown_isbn',
		'unknown_upc',
		'unknown_issn',
	];
	protected $id;
	protected $recordType;
	protected $recordId;
	protected $firstLoaded;
	/** @noinspection PhpUnused */
	protected $lastUsed;
	protected $imageSource;
	protected $sourceWidth;
	protected $sourceHeight;
	protected $thumbnailLoaded;
	protected $mediumLoaded;
	protected $largeLoaded;
	protected $uploadedImage;
	protected $disallowThirdPartyCover;
	protected $original_url;
	protected $original_url_small;
	protected $original_url_medium;
	protected $original_url_large;
	protected $last_url_validation;

	public function getNumericColumnNames(): array {
		return [
			'id',
			'sourceWidth',
			'sourceHeight',
			'thumbnailLoaded',
			'mediumLoaded',
			'largeLoaded',
			'uploadedImage',
			'disallowThirdPartyCover',
			'last_url_validation',
		];
	}

	private static $_allCoversReloadedThisSession = false;

	/**
	 * Reloads all default covers, will only reload them once per session to improve performance when updating all themes etc.
	 * @return void
	 */
	public function reloadAllDefaultCovers() : void {
		if (!self::$_allCoversReloadedThisSession) {
			$this->query("UPDATE " . $this->__table . " SET thumbnailLoaded = 0, mediumLoaded = 0, largeLoaded = 0 where imageSource = 'default'");
			self::$_allCoversReloadedThisSession = true;
		}
	}

	public function reloadOMDBCovers() : void {
		$this->query("UPDATE " . $this->__table . " SET thumbnailLoaded = 0, mediumLoaded = 0, largeLoaded = 0 where imageSource = 'omdb_title' OR imageSource = 'omdb_title_year'");
	}

	public function getImageSource() : ?string {
		return $this->imageSource;
	}

	public function getDisallowThirdPartyCover() {
		return $this->disallowThirdPartyCover;
	}

	/**
	 * @return mixed
	 */
	public function getRecordId() : string {
		return $this->recordId;
	}

	/**
	 * @return string
	 */
	public function getRecordType() : string {
		return $this->recordType;
	}

	/**
	 * @param string $recordType
	 */
	public function setRecordType(string $recordType): void {
		$this->__set('recordType', $recordType);
	}

	public function setRecordId(string $recordId): void {
		$this->__set('recordId', $recordId);
	}

	public function setImageSource(string $imageSource): void {
		$this->__set('imageSource', $imageSource);
	}

	/**
	 * Get the original URL of the cover image
	 * @return string|null
	 */
	//TODO: Keep original_url as a legacy fallback for one release, then remove the fallback and drop the column
	public function getOriginalUrl(string $size): ?string
	{
		$sizeProperty = $this->getOriginalUrlPropertyForSize($size);
		if ($sizeProperty !== null && !empty($this->$sizeProperty)) {
			return $this->$sizeProperty;
		}
		return $this->original_url;
	}

	/**
	 * Set the original URL of the cover image
	 * @param ?string $url
	 */
	public function setOriginalUrl(?string $url, string $size): void {
		$sizeProperty = $this->getOriginalUrlPropertyForSize($size);
		if ($sizeProperty !== null) {
			$this->__set($sizeProperty, $url);
			return;
		}
		$this->__set('original_url', $url);
	}

	public function clearOriginalUrls(): void {
		$this->__set('original_url', null);
		$this->__set('original_url_small', null);
		$this->__set('original_url_medium', null);
		$this->__set('original_url_large', null);
	}

	/**
	 * Get the timestamp when the URL was last validated
	 * @return int|null
	 */
	public function getLastUrlValidation(): ?int
	{
		return $this->last_url_validation;
	}

	/**
	 * Set the timestamp when the URL was last validated
	 * @param ?int $timestamp
	 */
	public function setLastUrlValidation(?int $timestamp): void {
		$this->__set('last_url_validation', $timestamp);
	}

	public function setThumbnailLoaded(int $loaded) : void {
		$this->__set('thumbnailLoaded', $loaded);
	}

	public function setMediumLoaded(int $loaded) : void {
		$this->__set('mediumLoaded', $loaded);
	}

	public function setLargeLoaded(int $loaded) : void {
		$this->__set('largeLoaded', $loaded);
	}

	public function isThumbnailLoaded() : bool {
		return $this->thumbnailLoaded;
	}

	public function isMediumLoaded() : bool {
		return $this->mediumLoaded;
	}

	public function isLargeLoaded() : bool {
		return $this->largeLoaded;
	}

	public function setLastUsed(int $lastUsed): void {
		$this->__set('lastUsed', $lastUsed);
	}

	public function getSourceWidth() : ?int {
		return $this->sourceWidth;
	}

	public function setSourceWidth(int $sourceWidth): void {
		$this->sourceWidth = $sourceWidth;
	}

	public function getSourceHeight() : ?int {
		return $this->sourceHeight;
	}

	public function setSourceHeight(int $sourceHeight): void {
		$this->sourceHeight = $sourceHeight;
	}

	public function getUploadedImage() : ?bool {
		return $this->uploadedImage;
	}

	public function setUploadedImage(bool $uploadedImage): void {
		$this->uploadedImage = $uploadedImage;
	}

	/** @noinspection PhpUnused */
	public function getFirstLoaded() : ?int {
		return $this->firstLoaded;
	}

	public function setFirstLoaded(int $firstLoaded): void {
		$this->firstLoaded = $firstLoaded;
	}

	public function getId() : ?int {
		return empty($this->id) ? null : (int)$this->id;
	}

	public static function findOrCreate(string $recordType, string $recordId): BookCoverInfo {
		$bookCoverInfo = new BookCoverInfo();
		$bookCoverInfo->setRecordType($recordType);
		$bookCoverInfo->setRecordId($recordId);
		if (!$bookCoverInfo->find(true)) {
			$bookCoverInfo->setFirstLoaded(time());
			$bookCoverInfo->setLastUsed(0);
			$bookCoverInfo->setImageSource('');
			$bookCoverInfo->insert();
		}
		return $bookCoverInfo;
	}

	/**
	 * The registry file of one cover slot of the record: the uploaded cover ('') or a size (small,
	 * medium, large) that was cached or generated.
	 */
	public function getStoredCover(string $variant): ?StorageObject {
		if (empty($this->id)) {
			return null;
		}
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		return StorageObject::getFile($this->__table, (int)$this->id, self::STORAGE_FIELD, $variant);
	}

	/**
	 * Stores the file in a cover slot of the record, replacing what the slot held. The fingerprint
	 * describes what the cover was built from (e.g. event dates), so a stored cover built from
	 * something else is generated again. Generated covers of that size are released, since the
	 * record now has a real cover.
	 */
	public function storeCover(string $path, string $variant, string $fingerprint = ''): ?StorageObject {
		if (empty($this->id)) {
			return null;
		}
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		$storedCover = StorageObject::store($path, $this->__table, (int)$this->id, self::STORAGE_FIELD, $variant, $fingerprint, $this->recordType . '_' . $this->recordId . '.png');
		if ($storedCover != null && $variant !== '') {
			foreach (StorageObject::getForOwner($this->__table, (int)$this->id)[self::DEFAULT_COVER_STORAGE_FIELD][$variant] ?? [] as $defaultCover) {
				$defaultCover->releaseThis();
			}
		}
		return $storedCover;
	}

	/**
	 * The generated cover of a size for one look of the libraries' themes.
	 */
	public function getStoredDefaultCover(string $size, string $lookFingerprint): ?StorageObject {
		if (empty($this->id)) {
			return null;
		}
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		return StorageObject::getFile($this->__table, (int)$this->id, self::DEFAULT_COVER_STORAGE_FIELD, $size, $lookFingerprint);
	}

	/**
	 * Stores a generated cover. Libraries whose themes look different see different generated
	 * covers of the same record, so one is kept per look instead of each replacing the other.
	 */
	public function storeDefaultCover(string $path, string $size, string $lookFingerprint): ?StorageObject {
		if (empty($this->id)) {
			return null;
		}
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		return StorageObject::store($path, $this->__table, (int)$this->id, self::DEFAULT_COVER_STORAGE_FIELD, $size, $lookFingerprint, $this->recordType . '_' . $this->recordId . '.png', '', true);
	}

	/**
	 * Makes $path the uploaded cover of the record and marks every size to be regenerated from it.
	 */
	public function storeUploadedCover(string $path): ?StorageObject {
		$storedCover = $this->storeCover($path, '');
		if ($storedCover != null) {
			$this->setImageSource('upload');
			$this->setThumbnailLoaded(0);
			$this->setMediumLoaded(0);
			$this->setLargeLoaded(0);
			$this->update();
		}
		return $storedCover;
	}

	/**
	 * Removes every stored file of the record: the uploaded cover and all cached and generated sizes.
	 */
	public function releaseAllCovers(): void {
		if (empty($this->id)) {
			return;
		}
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		StorageObject::release($this->__table, (int)$this->id);
	}

	/**
	 * Moves the covers uploaded before the storage registry existed (covers/original/<id>.png) to
	 * the registry, registered where they are. A file belongs to every record whose id is its name,
	 * as the cover processor read it that way. A record whose cover was never shown has no row yet,
	 * so the row is created for each record found with that id. A file used by several records is
	 * copied for each extra record, so each can later delete its own. Files of lists and series
	 * (covers/original/lists, series, seriesMember) are not uploaded record covers and stay where
	 * they are. A record marked as having an uploaded cover whose file is missing goes back to the
	 * normal cover sources.
	 *
	 * @return array{success: bool, numRegistered: int, notes: string[]}
	 */
	public static function migrateUploadedCoversToStorage(): array {
		global $configArray, $aspen_db;
		require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
		set_time_limit(0);
		$originalPath = $configArray['Site']['coverPath'] . '/original';
		$result = [
			'success' => true,
			'numRegistered' => 0,
			'notes' => [],
		];
		$excludedTypes = "'" . implode("', '", self::NOT_UPLOADED_RECORD_TYPES) . "'";

		$uploadedRecordIds = $aspen_db->query("SELECT recordId FROM bookcover_info WHERE imageSource = 'upload' AND recordType NOT IN ($excludedTypes)")->fetchAll(PDO::FETCH_COLUMN);
		if (!empty($uploadedRecordIds) && !self::anyUploadedCoverFileExists($uploadedRecordIds, $originalPath)) {
			// Not one cover found means storage is not mounted (Docker creates the folders empty), not that the covers are gone.
			$result['success'] = false;
			$result['notes'][] = "No uploaded cover found in $originalPath, check the data directory. Nothing was changed.";
			return $result;
		}

		// Record types are the indexed sources plus grouped works, so rows are found through the unique index.
		$recordTypes = $aspen_db->query('SELECT DISTINCT source FROM indexed_record_source')->fetchAll(PDO::FETCH_COLUMN);
		$recordTypes = array_values(array_diff(array_unique(array_merge($recordTypes, ['grouped_work'])), self::NOT_UPLOADED_RECORD_TYPES));
		$typePlaceholders = implode(', ', array_fill(0, count($recordTypes), '?'));
		$findRows = $aspen_db->prepare("SELECT id FROM bookcover_info WHERE recordType IN ($typePlaceholders) AND recordId = ?");
		// Grouped work covers were also read by the first 36 characters of a 40 character permanent id.
		$findLongGroupedWorks = $aspen_db->prepare("SELECT id FROM bookcover_info WHERE recordType = 'grouped_work' AND recordId LIKE ? AND LENGTH(recordId) = 40");
		$findRecordSources = $aspen_db->prepare('SELECT DISTINCT irs.source FROM grouped_work_records gwr INNER JOIN indexed_record_source irs ON irs.id = gwr.sourceId WHERE gwr.recordIdentifier = ?');
		$findGroupedWork = $aspen_db->prepare('SELECT permanent_id FROM grouped_work WHERE permanent_id = ? OR (LENGTH(?) = 36 AND permanent_id LIKE ?)');

		// Rows whose file exists, whether or not it could be registered: their cover is not missing.
		$rowIdsWithFile = [];
		$coverFiles = glob("$originalPath/*.png") ?: [];
		foreach ($coverFiles as $coverFile) {
			$recordId = basename($coverFile, '.png');
			$findRows->execute([...$recordTypes, $recordId]);
			$ownerIds = $findRows->fetchAll(PDO::FETCH_COLUMN);
			if (strlen($recordId) == 36) {
				$findLongGroupedWorks->execute([addcslashes($recordId, '%_') . '%']);
				$ownerIds = array_merge($ownerIds, $findLongGroupedWorks->fetchAll(PDO::FETCH_COLUMN));
			}
			// Records of other types with the same id may have no row yet, even when one record has a row.
			$findRecordSources->execute([$recordId]);
			foreach ($findRecordSources->fetchAll(PDO::FETCH_COLUMN) as $source) {
				if (!in_array($source, self::NOT_UPLOADED_RECORD_TYPES)) {
					$ownerIds[] = self::findOrCreate($source, $recordId)->getId();
				}
			}
			$findGroupedWork->execute([$recordId, $recordId, addcslashes($recordId, '%_') . '%']);
			foreach ($findGroupedWork->fetchAll(PDO::FETCH_COLUMN) as $permanentId) {
				$ownerIds[] = self::findOrCreate('grouped_work', $permanentId)->getId();
			}
			$ownerIds = array_unique(array_filter($ownerIds));
			if (empty($ownerIds)) {
				$result['notes'][] = 'Cover file ' . basename($coverFile) . ' is not linked to any record, left in place';
				continue;
			}
			foreach ($ownerIds as $ownerId) {
				$rowIdsWithFile[$ownerId] = true;
				if (StorageObject::getFile('bookcover_info', (int)$ownerId, self::STORAGE_FIELD, '') != null) {
					// A cover uploaded to the registry already, it is newer than the legacy file.
					continue;
				}
				if (StorageObject::registerLegacy($coverFile, 'bookcover_info', (int)$ownerId, self::STORAGE_FIELD) == null) {
					// Kept as it is, so running the update again picks it up.
					$result['success'] = false;
					$result['notes'][] = 'Could not register cover file ' . basename($coverFile) . " for bookcover_info $ownerId";
				} else {
					$result['numRegistered']++;
				}
			}
		}

		$uploadRows = $aspen_db->query("SELECT bi.id, bi.recordType, bi.recordId FROM bookcover_info bi LEFT JOIN storage_objects so ON so.ownerTable = 'bookcover_info' AND so.ownerId = bi.id AND so.ownerField = '" . self::STORAGE_FIELD . "' AND so.variant = '' WHERE bi.imageSource = 'upload' AND bi.recordType NOT IN ($excludedTypes) AND so.id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($uploadRows as $uploadRow) {
			if (isset($rowIdsWithFile[$uploadRow['id']])) {
				continue;
			}
			$aspen_db->exec("UPDATE bookcover_info SET imageSource = '', thumbnailLoaded = 0, mediumLoaded = 0, largeLoaded = 0 WHERE id = " . (int)$uploadRow['id']);
			$result['notes'][] = "Uploaded cover of {$uploadRow['recordType']} {$uploadRow['recordId']} not found, the record uses its other cover sources";
		}
		return $result;
	}

	/**
	 * @param string[] $recordIds
	 */
	private static function anyUploadedCoverFileExists(array $recordIds, string $originalPath): bool {
		foreach ($recordIds as $recordId) {
			if (is_readable("$originalPath/$recordId.png") || (strlen($recordId) == 40 && is_readable("$originalPath/" . substr($recordId, 0, 36) . '.png'))) {
				return true;
			}
		}
		return false;
	}

	private function getOriginalUrlPropertyForSize(string $size): ?string {
		return match ($size) {
			'small' => 'original_url_small',
			'medium' => 'original_url_medium',
			'large' => 'original_url_large',
			default => null,
		};
	}
}

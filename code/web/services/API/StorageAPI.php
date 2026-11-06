<?php

require_once ROOT_DIR . '/services/API/AbstractAPI.php';
require_once ROOT_DIR . '/sys/Covers/BookCoverInfo.php';
require_once ROOT_DIR . '/sys/Storage/StorageObject.php';

/**
 * Lets background processes (the Java exporters) store files through the storage registry, so PHP
 * stays the only implementation of it. Callers authenticate as an OAuth2 service client with the
 * storage:write scope. Each method writes one fixed kind of file, so a caller cannot choose which
 * object's files it changes.
 */
class StorageAPI extends AbstractAPI {
	function launch(): void {
		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';
		$this->handleAPIRequestAuto($method, 'storage_api');
	}

	/**
	 * Every response describes the current state of the registry or changes it, so none may be reused.
	 */
	protected function getCacheControl(): string {
		return 'no-store';
	}

	protected function getAPIScopePrefix(): string {
		return 'storage';
	}

	/**
	 * Stores a cover image from the ILS as the record's uploaded cover. The grouped work the record
	 * belongs to, when given, is reset so its cover is chosen again.
	 *
	 * POST multipart: cover (file), recordType, recordId, groupedWorkId (optional)
	 *
	 * @oauth true
	 * @token false
	 * @public false
	 * @scopes storage:write
	 */
	function storeCoverOriginal(): array {
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			return $this->error(405, 'storeCoverOriginal requires POST');
		}
		// PHP drops the whole body when it exceeds post_max_size, so check that before the fields.
		if (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
			return $this->error(413, 'The request is larger than post_max_size (' . ini_get('post_max_size') . ')');
		}
		$recordType = $_POST['recordType'] ?? '';
		$recordId = $_POST['recordId'] ?? '';
		if (!is_string($recordType) || !is_string($recordId) || $recordType === '' || $recordId === '' || !isset($_FILES['cover'])) {
			return $this->error(400, 'recordType, recordId and cover are required');
		}
		if (in_array($recordType, BookCoverInfo::NOT_UPLOADED_RECORD_TYPES)) {
			return $this->error(400, "Covers of $recordType records are not stored through this API");
		}
		$cover = $_FILES['cover'];
		if ($cover['error'] == UPLOAD_ERR_INI_SIZE || $cover['error'] == UPLOAD_ERR_FORM_SIZE) {
			return $this->error(413, 'The cover is larger than upload_max_filesize (' . ini_get('upload_max_filesize') . ')');
		} elseif ($cover['error'] != UPLOAD_ERR_OK) {
			return $this->error(400, 'Upload error ' . $cover['error']);
		}
		if (@getimagesize($cover['tmp_name']) === false) {
			return $this->error(400, 'The cover is not a valid image');
		}

		$bookCoverInfo = BookCoverInfo::findOrCreate($recordType, $recordId);
		$storedCover = $bookCoverInfo->storeUploadedCover($cover['tmp_name']);
		if ($storedCover == null) {
			return $this->error(500, 'Unable to store the cover');
		}

		$groupedWorkId = $_POST['groupedWorkId'] ?? '';
		if (is_string($groupedWorkId) && $groupedWorkId !== '') {
			$groupedWorkCoverInfo = new BookCoverInfo();
			$groupedWorkCoverInfo->setRecordType('grouped_work');
			$groupedWorkCoverInfo->setRecordId($groupedWorkId);
			if ($groupedWorkCoverInfo->find(true)) {
				$groupedWorkCoverInfo->setImageSource('');
				$groupedWorkCoverInfo->setThumbnailLoaded(0);
				$groupedWorkCoverInfo->setMediumLoaded(0);
				$groupedWorkCoverInfo->setLargeLoaded(0);
				$groupedWorkCoverInfo->update();
			}
		}

		return [
			'success' => true,
			'storageKey' => $storedCover->storageKey,
			'dateCreated' => (int)$storedCover->dateCreated,
		];
	}

	/**
	 * Lists when the uploaded cover of each record of a type was stored, so an exporter can tell
	 * which covers it still has to send. Covers that failed to store are missing and get sent again.
	 *
	 * GET: recordType
	 *
	 * @oauth true
	 * @token false
	 * @public false
	 * @scopes storage:write
	 */
	function listCoverOriginals(): array {
		global $aspen_db;
		$recordType = $_GET['recordType'] ?? '';
		if (!is_string($recordType) || $recordType === '') {
			return $this->error(400, 'recordType is required');
		}
		$statement = $aspen_db->prepare("SELECT bi.recordId, so.dateCreated FROM bookcover_info bi INNER JOIN storage_objects so ON so.ownerTable = 'bookcover_info' AND so.ownerId = bi.id AND so.ownerField = ? AND so.variant = '' WHERE bi.recordType = ?");
		$statement->execute([BookCoverInfo::STORAGE_FIELD, $recordType]);
		$originals = [];
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$originals[$row['recordId']] = (int)$row['dateCreated'];
		}
		return [
			'success' => true,
			'originals' => (object)$originals,
		];
	}

	private function error(int $httpStatus, string $message): array {
		http_response_code($httpStatus);
		return [
			'success' => false,
			'message' => $message,
		];
	}

	function getBreadcrumbs(): array {
		return [];
	}
}

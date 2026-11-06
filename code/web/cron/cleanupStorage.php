<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../bootstrap_aspen.php';

/**
 * Nightly storage registry maintenance:
 * - releases cached cover sizes that are old or unused, so they are generated again
 * - releases files whose owner was deleted
 * - deletes the bytes of released files once their delete delay has passed
 */

require_once ROOT_DIR . '/sys/CronLogEntry.php';
require_once ROOT_DIR . '/sys/Storage/StorageObject.php';
require_once ROOT_DIR . '/sys/Storage/StorageDeletion.php';

$cronLogEntry = new CronLogEntry();
$cronLogEntry->startTime = time();
$cronLogEntry->name = 'Storage Cleanup';
$cronLogEntry->insert();

$numCoversReleased = releaseStaleCachedCovers();
$numOrphansReleased = StorageObject::sweepOrphans();
[$numFilesDeleted, $numErrors] = StorageDeletion::purgeDue();

$cronLogEntry->notes .= "Released $numCoversReleased cached covers and $numOrphansReleased files without an owner. Deleted $numFilesDeleted files.";
if ($numErrors > 0) {
	$cronLogEntry->notes .= " Could not delete $numErrors files, they will be retried.";
	$cronLogEntry->numErrors = $numErrors;
}
$cronLogEntry->endTime = time();
$cronLogEntry->update();

/**
 * Cached sizes (not uploaded covers) created more than 4 weeks ago or whose record was not shown
 * in 2 weeks, the same rules the cover cache cleanup applied to files before the registry.
 */
function releaseStaleCachedCovers(): int {
	global $aspen_db;
	require_once ROOT_DIR . '/sys/Covers/BookCoverInfo.php';
	$createdCutoff = time() - 4 * 7 * 24 * 3600;
	$lastUsedCutoff = time() - 2 * 7 * 24 * 3600;
	$coverField = $aspen_db->quote(BookCoverInfo::STORAGE_FIELD);
	$defaultCoverField = $aspen_db->quote(BookCoverInfo::DEFAULT_COVER_STORAGE_FIELD);
	$batchSize = 1000;
	$numReleased = 0;
	do {
		$numReleasedBefore = $numReleased;
		// Every size is a cache; the uploaded cover (variant '') is source data and is kept.
		$staleRows = $aspen_db->query("SELECT so.id, so.ownerId, so.variant FROM storage_objects so INNER JOIN bookcover_info bi ON bi.id = so.ownerId WHERE so.ownerTable = 'bookcover_info' AND ((so.ownerField = $coverField AND so.variant != '') OR so.ownerField = $defaultCoverField) AND (so.dateCreated < $createdCutoff OR bi.lastUsed < $lastUsedCutoff) LIMIT $batchSize")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($staleRows as $staleRow) {
			$storageObject = new StorageObject();
			$storageObject->id = $staleRow['id'];
			if ($storageObject->find(true)) {
				$storageObject->releaseThis();
				$numReleased++;
			}
			// The size is generated again the next time it is shown
			$flagColumn = match ($staleRow['variant']) {
				'small' => 'thumbnailLoaded',
				'medium' => 'mediumLoaded',
				'large' => 'largeLoaded',
				default => null,
			};
			if ($flagColumn != null) {
				$aspen_db->exec("UPDATE bookcover_info SET $flagColumn = 0 WHERE id = " . (int)$staleRow['ownerId']);
			}
		}
		// Rows that could not be released would come back in every batch
	} while (count($staleRows) == $batchSize && $numReleased > $numReleasedBefore);
	return $numReleased;
}

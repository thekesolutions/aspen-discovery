<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../bootstrap_aspen.php';

/**
 * Nightly storage registry maintenance:
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

$numOrphansReleased = StorageObject::sweepOrphans();
[$numFilesDeleted, $numErrors] = StorageDeletion::purgeDue();

$cronLogEntry->notes .= "Released $numOrphansReleased files without an owner. Deleted $numFilesDeleted files.";
if ($numErrors > 0) {
	$cronLogEntry->notes .= " Could not delete $numErrors files, they will be retried.";
	$cronLogEntry->numErrors = $numErrors;
}
$cronLogEntry->endTime = time();
$cronLogEntry->update();


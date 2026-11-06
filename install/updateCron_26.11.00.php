<?php

if (count($_SERVER['argv']) > 1) {
	$serverName = $_SERVER['argv'][1];
	// Check to see if the update already exists properly.
	$fhnd = fopen('/usr/local/aspen-discovery/sites/' . $serverName . '/conf/crontab_settings.txt', 'r');
	if ($fhnd) {
		$lines = [];
		$insertPurgeDeletedILSPatrons = true;
		$purgeDeletedILSPatronsInserted = false;
		while (($line = fgets($fhnd)) !== false) {
			// Detect if the cron job is already present.
			if (str_contains($line, 'purgeDeletedILSPatrons.php')) {
				$insertPurgeDeletedILSPatrons = false;
				$line = "30 23 * * * aspen php /usr/local/aspen-discovery/code/web/cron/purgeDeletedILSPatrons.php $serverName\n";
			}
			// Insert before Debian end-of-file marker.
			if ($insertPurgeDeletedILSPatrons && str_contains($line, 'Debian needs a blank line at the end of cron')) {
				if (!empty($lines) && trim(end($lines)) !== '') {
					$lines[] = "\n";
				}
				$lines[] = "#######################################################\n";
				$lines[] = "# Purge patron records that were deleted from the ILS #\n";
				$lines[] = "#######################################################\n";
				$lines[] = "30 23 * * * aspen php /usr/local/aspen-discovery/code/web/cron/purgeDeletedILSPatrons.php $serverName\n";
				$lines[] = "\n";
				$purgeDeletedILSPatronsInserted = true;
			}
			$lines[] = $line;
		}
		fclose($fhnd);

		// Fallback: If marker was not found, add at the end.
		if ($insertPurgeDeletedILSPatrons && !$purgeDeletedILSPatronsInserted) {
			if (!empty($lines) && trim(end($lines)) !== '') {
				$lines[] = "\n";
			}
			$lines[] = "#######################################################\n";
			$lines[] = "# Purge patron records that were deleted from the ILS #\n";
			$lines[] = "#######################################################\n";
			$lines[] = "30 23 * * * aspen php /usr/local/aspen-discovery/code/web/cron//purgeDeletedILSPatrons.php $serverName\n";
			$lines[] = "\n";
			$purgeDeletedILSPatronsInserted = true;
		}

		// Write the file only if the new cron job was inserted.
		if ($purgeDeletedILSPatronsInserted) {
			$newContent = implode('', $lines);
			file_put_contents('/usr/local/aspen-discovery/sites/' . $serverName . '/conf/crontab_settings.txt', $newContent);
		}
	} else {
		echo '- Could not find cron settings file.' . PHP_EOL;
	}

	$cronSettingsFile = "/usr/local/aspen-discovery/sites/$serverName/conf/crontab_settings.txt";
	$cronSettings = @file_get_contents($cronSettingsFile);
	if ($cronSettings !== false && !str_contains($cronSettings, 'cleanupStorage.php')) {
		$storageCleanup = "#####################################\n";
		$storageCleanup .= "# Clean up the storage registry     #\n";
		$storageCleanup .= "#####################################\n";
		$storageCleanup .= "30 2 * * * aspen php /usr/local/aspen-discovery/code/web/cron/cleanupStorage.php $serverName\n\n";
		// Insert before the Debian end-of-file marker, or at the end when it is missing.
		$debianMarkerPosition = strpos($cronSettings, '# Debian needs a blank line at the end of cron');
		if ($debianMarkerPosition !== false) {
			$cronSettings = substr($cronSettings, 0, $debianMarkerPosition) . $storageCleanup . substr($cronSettings, $debianMarkerPosition);
		} else {
			$cronSettings = rtrim($cronSettings, "\n") . "\n\n" . $storageCleanup;
		}
		file_put_contents($cronSettingsFile, $cronSettings);
	}
} else {
	echo 'Must provide server name as first argument.' . PHP_EOL;
	exit();
}
<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';

class IlsExtractLogEntry extends DataObject
{
	public $__table = 'ils_extract_log';   // table name
	public $id;
	public $indexingProfile;
	public $startTime;
	public $lastUpdate;
	public $endTime;
	public $notes;
	public $numProducts;
	public $numErrors;
	public $numAdded;
	public $numDeleted;
	public $numUpdated;
	public $numSkipped;

	function keys()
	{
		return array('id');
	}

	function getElapsedTime()
	{
		if (!isset($this->endTime) || is_null($this->endTime)) {
			return "";
		} else {
			$elapsedTimeMin = ceil(($this->endTime - $this->startTime) / 60);
			if ($elapsedTimeMin < 60) {
				return $elapsedTimeMin . " min";
			} else {
				$hours = floor($elapsedTimeMin / 60);
				$minutes = $elapsedTimeMin - (60 * $hours);
				return "$hours hours, $minutes min";
			}
		}
	}
}

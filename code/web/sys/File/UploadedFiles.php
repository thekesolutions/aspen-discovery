<?php

class UploadedFiles extends DataObject {
	public $__table = 'uploaded_files';

	public $id;
	public $filename;
	public $objectType;
	public $objectId;
	public $propertyName;
	public $fileData;
}
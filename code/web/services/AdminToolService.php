<?php

class AdminToolService {

	public static function loadObjectEditor(mixed $moduleName, mixed $toolName): ?ObjectEditor {
		if (!self::isValidName($moduleName) || !self::isValidName($toolName)) {
			return null;
		}
		$toolFile = ROOT_DIR . "/services/$moduleName/$toolName.php";
		if (!is_file($toolFile)) {
			return null;
		}
		require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
		require_once $toolFile;
		$fullToolName = $moduleName . '_' . $toolName;
		if (!class_exists($fullToolName, false) || !is_subclass_of($fullToolName, 'ObjectEditor')) {
			return null;
		}
		return new $fullToolName();
	}

	private static function isValidName(mixed $name): bool {
		return is_string($name) && preg_match('/^\w+$/', $name) === 1;
	}
}

<?php
require_once ROOT_DIR . '/services/API/AbstractAPI.php';


class CronAPI extends AbstractAPI {

    function launch() {
		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';

		//Set Headers
		header('Content-type: application/json');
		//header('Content-type: text/html');
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		global $activeLanguage;
		if (isset($_GET['language'])) {
			$language = new Language();
			$language->code = $_GET['language'];
			if ($language->find(true)) {
				$activeLanguage = $language;
			}
		}

		if (isset($_SERVER['PHP_AUTH_USER'])) {
			if ($this->grantTokenAccess()) {
				if (in_array($method, [
					'checkBackgroundProcesses'
				])) {
					$result = [
						'result' => $this->$method(),
					];
					$output = json_encode($result);
					header("Cache-Control: max-age=10800");
					require_once ROOT_DIR . '/sys/SystemLogging/APIUsage.php';
					APIUsage::incrementStat('CronAPI', $method);
				} else {
					$output = json_encode(['error' => 'invalid_method']);
				}
			} else {
				header('HTTP/1.0 401 Unauthorized');
				$output = json_encode(['error' => 'unauthorized_access']);
			}
			ExternalRequestLogEntry::logRequest('CronAPI.' . $method, $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], getallheaders(), '', $_SERVER['REDIRECT_STATUS'], $output, []);
			echo $output;
		} elseif (IPAddress::allowAPIAccessForClientIP()) {
			if (!in_array($method, []) && method_exists($this, $method)) {
				$result = [
					'result' => $this->$method(),
				];
				$output = json_encode($result);
				require_once ROOT_DIR . '/sys/SystemLogging/APIUsage.php';
				APIUsage::incrementStat('CronAPI', $method);
			} else {
				$output = json_encode(['error' => 'invalid_method']);
			}
			echo $output;
		} else {
			$this->forbidAPIAccess();
		}

		return '';
	}

    public function checkBackgroundProcesses() : array {
        $dockerDir = "/usr/local/aspen-directory/docker";
        $checkerPath = "$dockerDir/files/cron/checkBackgroundProcessesDocker.php";
        $sitename = getenv('SITE_NAME') ?: "localhost";
        exec("sudo -u www-data php $checkerPath $sitename",$output);
		echo $output;
        $result = [
            'success' => true,
            'message' => "Check process successfully executed"
        ];

        return $result;
    }


    function getBreadcrumbs(): array {
		return [];
	}

}
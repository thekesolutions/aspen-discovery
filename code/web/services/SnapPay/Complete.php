<?php

class SnapPay_Complete extends Action {
	public function launch() {
		global $interface;
		global $logger;
		global $library;
		$error = true;
		require_once ROOT_DIR . '/sys/Account/UserPayment.php';
		require_once ROOT_DIR . '/sys/Utils/EncryptionUtils.php';
		$message = '';
		$emailNotifications = 0; // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
		require_once ROOT_DIR . '/sys/ECommerce/SnapPaySetting.php';
		$snapPaySetting = new SnapPaySetting();
		$snapPaySetting->id = $library->snapPaySettingId;
		if ($snapPaySetting->find(true)) {
			// determine whether SnapPay settings have email notifications enabled
			$emailNotifications = $snapPaySetting->emailNotifications;
		}
		if ($emailNotifications !== 0) {
			global $serverName;
			require_once ROOT_DIR . '/sys/Email/Mailer.php';
			$mailer = new Mailer();
			$emailNotificationsAddresses = $snapPaySetting->emailNotificationsAddresses;
		}

		$userPayment = new UserPayment();
		$userPayment->id = $_GET['u']; // Payment Reference ID from the query string
		// If the payment has already been completed, immediately redirect the user to /MyAccount/Fines . This should be effective in cases where user's browser is reloading SnapPay/Complete as well as "supportive" traffic like from Google-Read-Aloud
		if ($userPayment->find(true) && $userPayment->completed != 0) {
			// TO DO: add conditional that checks SnapPay for duplicate payments
			// Redirect the user to /MyAccount/Fines, perhaps requiring a login
			header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
			return;
		}
		// Eliminate requests NOT originating from SnapPay
		// This check is necessary to prevent session hijacking and replay attacks
		// But what James thinks is mostly going on is that users are restoring their browsers with a tab on the SnapPay/Complete page
		$referringUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Unknown';
		if ($snapPaySetting->sandboxMode == 1) {
			$snapPayURL = 'https://stage.snappayglobal.com/';
		} elseif ($snapPaySetting->sandboxMode == 0) {
			$snapPayURL = 'https://www.snappayglobal.com/';
		}
		if (!str_contains($referringUrl, $snapPayURL)) {
			$error = true;
			// NB: This particular error is ONLY written to Aspen's messages.log. It is not written to the user interface nor to the user_payments table.
			$message = 'Invalid referring URL. The request must originate from SnapPay. Payment Reference ID ' . $_GET['u'];
			$interface->assign('error', $message);
			$logger->log($message, Logger::LOG_ERROR);
			if ($emailNotifications > 0) { // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
				$mailer->send($emailNotificationsAddresses, "$serverName Error with SnapPay Payment", $message);
			}
			header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
			return;
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
			if (empty($_GET['u'])) { // Payment Reference ID from the query string
				$error = true;
				$message = 'No Payment Reference ID was provided in the URL.';
			}
			if (empty($_POST['udf1'])) {
				$error = true;
				$message = 'No Transaction ID was provided from SnapPay.';
			} else {
				if ($_GET['u'] !== $_POST['udf1']) {
					$error = true;
					$message = 'Payment Reference ID from SnapPay does not match Payment Reference ID in the URL. ' . $_GET['u'] . ' !== ' . $_POST['udf1'];
				}
				$params = explode(',', $_POST['hpphmacresponseparameters']);
				$hppHMACParamValue = '';
				foreach ($params as $param) {
					if ($param != 'nonce' && $param != 'timestamp') {
						$hppHMACParamValue .= $_POST[$param];
					}
				}
				$validated = $this->validateSnapPayHMAC($_POST['signature'], $hppHMACParamValue);
				if ($validated != 'Valid signature.') {
					$error = true;
					$message = "Invalid signature returned from SnapPay for Payment Reference ID" . $_GET['u'];
				} else {
					$result = UserPayment::completeSnapPayPayment();
					$message = $result['message'];
					if ($result['error'] === true) {
						$error = true;
					} else {
						$error = false;
					}
				}
			}
			if ($error === true && $result['cancelled'] === true) {
				$interface->assign('error', $message);
				$logger->log($message, Logger::LOG_ERROR);
				if ($emailNotifications === 2) { // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
					$mailer->send($emailNotificationsAddresses, "$serverName Error with SnapPay Payment", $message);
				}
				header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
			} elseif ($error === true) {
				$interface->assign('error', $message);
				$logger->log($message, Logger::LOG_ERROR);
				if ($emailNotifications > 0) { // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
					$mailer->send($emailNotificationsAddresses, "$serverName Error with SnapPay Payment", $message);
				}
				header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
			} else {
				if (empty($message)) {
					$message = "SnapPay Payment completed with no message for Payment Reference ID " . $_GET['u'];
				}
				$interface->assign('message', $message);
				$logger->log($message, Logger::LOG_DEBUG);
				if ($emailNotifications === 2) { // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
					$mailer->send($emailNotificationsAddresses, "$serverName SnapPay Payment", $message);
				}
				header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
			}
		} else {
			// NB: This particular error is ONLY written to Aspen's messages.log. It is not written to the user interface nor to the user_payments table.
			$error = true;
			$message = 'Invalid request method. Only POST requests are allowed. Payment Reference ID ' . $_GET['u'];
			$interface->assign('error', $message);
			$logger->log($message, Logger::LOG_ERROR);
			if ($emailNotifications > 0) { // emailNotifications 0 = Do not send email; 1 = Email errors; 2 = Email all transactions
				$mailer->send($emailNotificationsAddresses, "$serverName Error with SnapPay Payment", $message);
			}
			header('Location: /MyAccount/Fines?s=' . urlencode(EncryptionUtils::encryptField($_GET['u'])));
		}
	}

	function validateSnapPayHMAC(string $signatureFromSnapPay, $hppHMACParamValue): string {
		global $library;
		require_once ROOT_DIR . '/sys/ECommerce/SnapPaySetting.php';
		$snapPaySetting = new SnapPaySetting();
		$snapPaySetting->id = $library->snapPaySettingId;
		if ($snapPaySetting->find(true)) {
			$hmacHeader = '';
			try {
				$secretkey = $snapPaySetting->apiAuthenticationCode;
				// Retrieve the actual signature, nonce, and timestamp from the response
				$rawAuthzHeader = mb_convert_encoding(base64_decode($signatureFromSnapPay), 'ISO-8859-1');
				$authorizationHeaderArray = explode(':', $rawAuthzHeader);
				if ($authorizationHeaderArray) {
					$incomingBase64Signature = $authorizationHeaderArray[0];
					$nonce = $authorizationHeaderArray[1];
					$timestamp = $authorizationHeaderArray[2];
					// Concatenate hppHMACParamValue, nonce, and timestamp
					$data = sprintf("%s%s%s", $hppHMACParamValue, $nonce, $timestamp);
					$signature = mb_convert_encoding($data, 'UTF-8');
					// Decode the secret key from Base64
					$secretKeyByteArray = base64_decode($secretkey);
					// Compute the HMAC SHA-256 hash
					$hmac = hash_hmac('sha256', $signature, $secretKeyByteArray, true);
					$convertedInputString = base64_encode($hmac);
					// Compare the computed signature with the incoming signature
					$isValid = hash_equals($incomingBase64Signature, $convertedInputString);
					$hmacHeader = $isValid ? "Valid signature." : "Invalid signature.";
				}
			} catch (Exception $e) {
				$hmacHeader = $e->getMessage();
			}
			return $hmacHeader;
		}
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/MyAccount/Home', 'Your Account');
		$breadcrumbs[] = new Breadcrumb('/MyAccount/Fines', 'Your Fines');
		$breadcrumbs[] = new Breadcrumb('', 'Payment Completed');
		return $breadcrumbs;
	}
}

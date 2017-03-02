<?php
header('Content-Type: application/json; charset=utf-8; Content-Encoding: gzip; Content-Transfer-Encoding: binary');

if (isset($_POST['result'])) {
	if ($_POST['result'] != 'verification') {
		$fp = stream_socket_client('tcp://localhost:8000', $errno, $errstr, 30);
		if (!$fp) {
			echo "$errstr ($errno)\n";
		} else {
			fwrite($fp, '|r|?|' . $_POST['result'] . '|\r\n');
		}
		fclose($fp);
	}
}
?>
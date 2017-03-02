<?php
header('Content-Type: application/json; charset=utf-8; Content-Encoding: gzip; Content-Transfer-Encoding: binary');

$fp = stream_socket_client('tcp://localhost:8000', $errno, $errstr, 30);
if (!$fp) {
	die("$errstr ($errno)\n");
} else {
	fwrite($fp, '|p|' . $_POST['id'] . '||\r\n');
	$msg = '';
	while (!feof($fp)) {
		$msg = $msg . fread($fp, 1024);
		$tokens = explode('|', $msg);
		if (sizeof($tokens)==5) {
			echo json_encode( array (
				'msg' => $tokens[3],
				'done' => ($tokens[1] == 'd'),
			));
			break;
		}
	}
}
fclose($fp);
?>
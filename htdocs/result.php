<?php
require_once 'settings.php';
require_once 'base.php';

class Result extends Base
{
	protected $template_name="result.tpl";
}

$result = new Result();
if ((isset($_POST['query'])) && ($_POST['query']!='')) {
	$result->query = $_POST['query'];
	$result->response = 'Thinking...';

	$fp = stream_socket_client('tcp://localhost:8000', $errno, $errstr, 30);
	if (!$fp) {
		die("$errstr ($errno)\n");
	} else {
		fwrite($fp, '|a|?|' . $_POST['query'] . '|\r\n');
		$msg = '';
		while (!feof($fp)) {
			$msg = $msg . fread($fp, 1024);
			$tokens = explode('|', $msg);
			if (sizeof($tokens)==5) {
				$result->id = $tokens[2];
				break;
			}
		}
	}
	fclose($fp);
	$result->addJs('json2.js');
	$result->addJs('result.js');
}
else {
	$result->query = 'nothing asked';
	$result->response = 'ain\'t no mindreader :(';
	$result->id = 0;
}
$result->render();
?>
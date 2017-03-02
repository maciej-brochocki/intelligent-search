<?php
require_once 'settings.php';
require_once 'base.php';

class Query extends Base
{
	protected $template_name="query.tpl";
}

$query = new Query();
$query->render();
?>
<?php
require_once realpath($_SERVER['DOCUMENT_ROOT'].'/../library/TinyUrlServer.php');

$soapServer = new SoapServer(NULL, array('uri' => 'http://HTTP_HOST/tiny-server'));
$soapServer->setObject(new TinyUrlServer());
$soapServer->handle();

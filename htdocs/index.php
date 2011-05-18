<?php
require_once '../library/TinyUrlServer.php';
$tiny = new TinyUrlServer();

try {
    $url = $tiny->getFullUrl($_SERVER['QUERY_STRING'], 'generalcontext');
    header("Location: {$url}");exit;
} catch ( SoapFault $e ) {
    header("HTTP/1.0 Not Found");
    if ( file_exists('404.html') && is_readable('404.html') ) {
        print file_get_contents('404.html');exit;
    }
    exit;
} catch ( Exception $e ) {
    header("HTTP/1.0 Not Found");
    if ( file_exists('404.html') && is_readable('404.html') ) {
        print file_get_contents('404.html');exit;
    }
    exit;
}

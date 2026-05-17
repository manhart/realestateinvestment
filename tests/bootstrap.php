<?php
declare(strict_types=1);

$poolRoot = realpath(__DIR__.'/../../pool');
$appRoot = realpath(__DIR__.'/..');

if($poolRoot === false || $appRoot === false) {
    throw new RuntimeException('Failed to resolve test bootstrap paths.');
}

define('DIR_POOL_ROOT', $poolRoot);
define('DIR_APP_ROOT', $appRoot);
define('DIR_DOCUMENT_ROOT', dirname($appRoot));
define('DIR_DATA_ROOT', DIR_DOCUMENT_ROOT.'/data');

require_once DIR_POOL_ROOT.'/pool.lib.php';

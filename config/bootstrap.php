<?php
declare(strict_types=1);

define('POOL_START', microtime(true));
const DIR_DOCUMENT_ROOT = '/virtualweb/manhart';
define('DIR_APP_ROOT', dirname(__DIR__));
const DIR_POOL_ROOT = DIR_DOCUMENT_ROOT.'/pool';
require_once DIR_POOL_ROOT.'/pool.lib.php';

$_SERVER['SERVER_NAME'] ??= gethostname();
switch($_SERVER['SERVER_NAME']) {
    case 'g7system':
    case 'dev.local':
    case 'pofolio':
    case 'pofolio.local':
        $stage = 'develop';
        $browserRootPath = '..';
        $defaultSessionDuration = 14400;
        break;
}

$stage ??= $_SERVER['_Stage'] ?? 'production';
$browserRootPath ??= $_SERVER['_RelativeRoot'] ?? die('Missing Config Parameter _RelativeRoot in Server Environment');
$defaultSessionDuration ??= $_SERVER['_DefaultSessionDuration'] ?? 1800;

define('DIR_RELATIVE_DOCUMENT_ROOT', $browserRootPath);
define('IS_DEVELOP', $stage === 'develop' || $stage === 'dev');
define('IS_STAGING', $stage === 'staging' || $stage === 'stg');
define('IS_PRODUCTION', $stage === 'production' || $stage === 'prod');
const IS_TESTSERVER = (IS_DEVELOP || IS_STAGING);

define('DEFAULT_SESSION_LIFETIME', $defaultSessionDuration);
const DIR_DATA_ROOT = DIR_DOCUMENT_ROOT.'/data';
const DIR_RELATIVE_DATA_ROOT = DIR_RELATIVE_DOCUMENT_ROOT.'/data';
const DIR_LOGS_ROOT = DIR_DATA_ROOT.'/logs';
const DIR_COMMON_ROOT = DIR_DOCUMENT_ROOT;
const DIR_DAOS_ROOT = DIR_COMMON_ROOT.'/daos';
const DIR_RESOURCES_ROOT = DIR_COMMON_ROOT.'/resources';
const DIR_COMMON_ROOT_REL = DIR_RELATIVE_DOCUMENT_ROOT.'/commons';
const DIR_3RDPARTY_ROOT = DIR_DOCUMENT_ROOT.'/3rdParty';
const DIR_RELATIVE_3RDPARTY_ROOT = DIR_RELATIVE_DOCUMENT_ROOT.'/3rdParty';

if(file_exists(DIR_3RDPARTY_ROOT.'/composer/autoload.php')) {
    require_once DIR_3RDPARTY_ROOT.'/composer/autoload.php';
}
require_once DIR_3RDPARTY_ROOT.'/_index/_3rdPartyResources.php';

if(!is_dir(DIR_LOGS_ROOT)) {
    mkdirs(DIR_LOGS_ROOT);
}

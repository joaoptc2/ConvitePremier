<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('CONFIG_FILE', APP_DIR . '/config.php');

if (!is_file(CONFIG_FILE)) {
    $installUrl = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../install.php' : 'install.php';
    header('Location: ' . $installUrl);
    exit;
}

$GLOBALS['config'] = require CONFIG_FILE;
date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'America/Sao_Paulo');
mb_internal_encoding('UTF-8');

require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/event.php';

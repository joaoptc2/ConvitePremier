<?php
declare(strict_types=1);

define('ADMIN_PUBLIC', true);
require dirname(__DIR__) . '/app/admin.php';

$_SESSION = [];
session_destroy();
redirect('login.php');

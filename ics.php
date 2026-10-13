<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="convite-premier.ics"');
echo event_ics();

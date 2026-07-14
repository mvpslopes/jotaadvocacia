<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/php/helpers.php';

jota_admin_logout();
header('Location: login.php');
exit;

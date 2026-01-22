<?php
declare(strict_types=1);

$STAGING_BASE = '/tmp/mirrors/neoarch';
$MIRROR_BASE  = '/var/mirrors/neoarch';

$ENVIRONMENT = getenv('ENVIRONMENT');
$DOMAIN = getenv('DOMAIN');

$DB_HOST = getenv('DB_HOST');
$DB_PORT = getenv('DB_PORT');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');

date_default_timezone_set(getenv('TIMEZONE'));

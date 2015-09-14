<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(-1);
}
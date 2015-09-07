<?php

require __DIR__. '/config.php';
require __DIR__ . '/asana.php';

/**
 * set debug level
 */
switch (DEBUG) {
    case 1:
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        break;
}

/**
 * Create a client using Asana API key
 */

$asana = new Asana(array(
    'apiKey' => ASANA_API_KEY
));

$projects = json_decode($asana->getProjects());

foreach ($projects as $project) {
    var_dump($project);
}


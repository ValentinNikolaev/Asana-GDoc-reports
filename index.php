<?php

require __DIR__. '/config.php';
require __DIR__ . '/vendor/autoload.php';

/**
 * set debug level
 */
switch (DEBUG) {
    case 1:
        error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
        ini_set('display_errors', true);
        break;
}

/**
 * Create a client using Asana API key
 */

$client = Asana\Client::basicAuth(ASANA_API_KEY);

/**
 * Get all workspaces
 */
$currentWorkspaces = [];
$workspaces = $client->projects->findAll();

if (!$workspaces) {
    throw new Exception("No workspaces were found");
}

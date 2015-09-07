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
$workspacesTasks = [];

$workspaces = $client->workspaces->findAll();

if (!$workspaces) {
    throw new Exception("No workspaces were found");
}

/**
 * get workspaces tasks
 */
foreach ($workspaces as $workspace) {
    $workspacesTasks[] = $client->projects->findAll(['workspace' => $workspace->id]);
}

if (!$workspacesTasks) {
    throw new Exception("No tasks were found");
}




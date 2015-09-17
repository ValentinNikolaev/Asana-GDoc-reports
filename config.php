<?php

$scopes = array(
    Google_Service_Drive::DRIVE,
    Google_Service_Drive::DRIVE_APPDATA,
    Google_Service_Drive::DRIVE_FILE,
    Google_Service_Drive::DRIVE_METADATA,
    Google_Service_Gmail::GMAIL_INSERT,
    Google_Service_Gmail::GMAIL_MODIFY,
    Google_Service_Gmail::MAIL_GOOGLE_COM)
;

$humanTags = [
    'dev' => 'backend development',
    'bugs' => 'bug fixing ',
    'css' => 'slicing the pages',
    'frontend' => 'frontend work(javascript)',
    'research' => 'investigation, research',
    'docs' => 'documenting',
    'chats' => 'getting in touch with 3rd party',
    'server' => 'server setup',
    'deploy' => 'deployment',
    'unittests' => 'writing tests',
    'qa' => 'testing',
];

$emailConfig = array(
    'from' => 'noreply@reports-asana.com',
    'fromName' => 'Asana Reports Sender',
    'subject' => 'Asana Reports',
    'host' => 'smtp.sendgrid.net',
    'port' => 587   ,
    'username' => 'chris@ultimateblogsecurity.com',
    'password' => 'u1timate',
    'log' => true,
//        'timeout' => 1,
    'transport' => 'Smtp',
    'tls' => true,
);

/**
 * clients and projects
 */

define('NOT_DEFINED_CLIENT_FOLDER', 'Not defined client');
define('EMAIL_REPORT_SUBJECT', 'Report');
define('EMAIL_REPORT_TO', '');
define('EMAIL_REPORT_BODY', '');

$clientsProjects = [
    [
        'name' => 'Chris Davis',
        'send_to' => [
            'chris.davis@getnetset.com'
        ],
        'projects' => [
            [
                'name' => 'Customized3',
                'link' => 'https://app.asana.com/0/29736859250886/list',
                'id' => '29736859250886',
                'type' => 'asana',
            ]
        ]
    ],
    [
        'name' => 'Christopher Sleat',
        'send_to' => [
            'csleat@cwist.com'
        ],
        'projects' => [
            [
                'name' => 'CWIST',
                'link' => 'https://app.asana.com/0/17628567771163/list',
                'id' => '17628567771163',
                'type' => 'asana',
            ]
        ]
    ],
    [
        'name' => 'Ed Holloway',
        'send_to' => [
            'ed@edholloway.com'
        ],
        'projects' => [
            [
                'name' => 'School Collector',
                'link' => 'https://app.asana.com/0/41954123350759/list',
                'id' => '41954123350759',
                'type' => 'asana',
            ]
        ]
    ],
];


define('GAPI_SCOPES', implode(' ', $scopes));
define('DEBUG', 1);
define('ASANA_API_KEY', '3ivyLe5P.NK19uB8GNejRbxSigjLSW09');
define('TIME_CHECK_FROM', '19:30');
define('DATETIME_TIMEZONE_CURRENT', 'Europe/Kiev');
define('DATETIME_TIMEZONE_ASANA', 'HST');

define('DAILY_REPORT_TEMPLATE', '17mpulibwqYKlWLmBZx_sM6_nEHWfprrsgVCTNSQ6Gfk');
define('GDOC_SHEET_MIME', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
define('GDOC_SHEET_MIME_GET', 'application/vnd.google-apps.spreadsheet');
define('GDOC_FOLDER_MIME', 'application/vnd.google-apps.folder');
define('GDOC_PDF_MIME', 'application/pdf');

define('APPLICATION_NAME', 'Asana GDoc CLI');
define('CREDENTIALS_PATH', __DIR__.'/credentials/drive-api-asana-gdoc-cli.json');
define('CREDENTIALS_PATH_PHP',/* __DIR__.'/credentials/drive-api-asana-gdoc.json'*/CREDENTIALS_PATH);
define('TMP_PATH', __DIR__.'/tmp/');
define('REPORTS_PATH', __DIR__.'/reports/');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('RESPONSE_PERSON', 'Eugene Pyvovarov');

define('GDOC_REPORT_DIR_NAME', 'Reports');

define("DATE_FORMAT_FNAME", "m_d_Y");

define('LOG_SHOW_DATETIME', true);
define('LOG_DATETIME_FORMAT', 'H:i:s d.m.Y');

define('EMAIL_ADMIN', 'mailto:eugene@lifeisgoodlabs.com');
define('BASE_SERVER', '46.101.42.18/agdoc/');
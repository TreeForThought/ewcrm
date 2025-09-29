# app/config.php
<?php
// Adjust to your HostGator MySQL creds.
return [
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=efed_crm;charset=utf8mb4',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'app' => [
        'base_url' => null, // null = auto-detect
        'session_name' => 'efedcrm_sess',
        'csrf_key' => 'efedcrm_csrf',
    ],
];

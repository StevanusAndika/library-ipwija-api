<?php

// Ubah 'laravel_project' sesuai dengan nama folder Laravel kamu
$laravelPath = __DIR__ . '/public/index.php';

if (!file_exists($laravelPath)) {
    die("Laravel project not found. Please check the path.");
}

// Redirect request ke Laravel
require $laravelPath;

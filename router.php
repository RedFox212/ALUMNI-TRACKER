<?php
// router.php
// A simple router for LATS to handle clean URLs or direct file inclusions

function route($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $path = trim($path, '/');
    
    // Hardcoded routes for now
    switch ($path) {
        case '':
        case 'index':
            require 'index.php';
            break;
        case 'dashboard':
            require 'pages/dashboard.php';
            break;
        case 'alumni':
            require 'pages/alumni.php';
            break;
        case 'logout':
            require 'logout.php';
            break;
        default:
            if (file_exists("pages/$path.php")) {
                require "pages/$path.php";
            } else {
                http_response_code(404);
                echo "404 Not Found";
            }
            break;
    }
}

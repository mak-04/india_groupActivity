<?php
require 'config/config.php';
require 'api.php';
try {
    $response = call_gemini('A lesson about Pi', 'quiz', null);
    echo "SUCCESS\n";
    echo $response;
} catch (Exception $e) {
    echo "ERROR\n";
    echo $e->getMessage();
}

<?php
header('Content-Type: application/json');
// Simple endpoint that returns sample analytics JSON
$path = __DIR__ . '/../data/analytics.json';
if (file_exists($path)) {
    echo file_get_contents($path);
} else {
    echo json_encode(["error" => "analytics data not found"]);
}
exit;
?>

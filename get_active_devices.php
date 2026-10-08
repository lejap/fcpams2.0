<?php
/**
 * Fetch All Active App Users GPS Locations
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/active_devices.json';
$devices = [];

if (file_exists($dataFile)) {
    $devices = json_decode(file_get_contents($dataFile), true) ?: [];
}

$now = time();
$activeDevices = [];

foreach ($devices as $id => $dev) {
    // Keep devices seen within the last 15 minutes (900 seconds)
    if (isset($dev['last_seen']) && ($now - $dev['last_seen']) < 900) {
        $dev['seconds_ago'] = $now - $dev['last_seen'];
        $activeDevices[] = $dev;
    }
}

echo json_encode([
    "status" => "success",
    "timestamp" => $now,
    "active_count" => count($activeDevices),
    "devices" => array_values($activeDevices)
]);

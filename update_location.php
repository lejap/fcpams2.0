<?php
/**
 * Update Active Device GPS Location Webhook
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rawInput = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_GET, $_POST, $rawInput);

$deviceId = trim($input['device_id'] ?? '');
$deviceName = trim($input['device_name'] ?? 'Mobile User');
$fcmToken = trim($input['fcm_token'] ?? '');
$latitude = filter_var($input['latitude'] ?? 0.0, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($input['longitude'] ?? 0.0, FILTER_VALIDATE_FLOAT);

if ($latitude === false) $latitude = 0.0;
if ($longitude === false) $longitude = 0.0;

if (!$deviceId) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameter: device_id."
    ]);
    exit();
}

$dataFile = __DIR__ . '/active_devices.json';
$devices = [];

if (file_exists($dataFile)) {
    $devices = json_decode(file_get_contents($dataFile), true) ?: [];
}

$now = time();

// Keep devices updated in the last 15 minutes (900 seconds)
$activeDevices = [];
foreach ($devices as $id => $dev) {
    if (isset($dev['last_seen']) && ($now - $dev['last_seen']) < 900) {
        $activeDevices[$id] = $dev;
    }
}

// Preserve existing fcm_token or latitude if current post sends empty string
$existingToken = $activeDevices[$deviceId]['fcm_token'] ?? '';
$finalToken = !empty($fcmToken) ? $fcmToken : $existingToken;

$existingLat = $activeDevices[$deviceId]['latitude'] ?? 0.0;
$existingLng = $activeDevices[$deviceId]['longitude'] ?? 0.0;
$finalLat = ($latitude != 0.0) ? (float)$latitude : (float)$existingLat;
$finalLng = ($longitude != 0.0) ? (float)$longitude : (float)$existingLng;

// Update or insert device entry
$activeDevices[$deviceId] = [
    "device_id" => $deviceId,
    "device_name" => $deviceName,
    "fcm_token" => $finalToken,
    "latitude" => $finalLat,
    "longitude" => $finalLng,
    "last_seen" => $now,
    "last_seen_formatted" => date('Y-m-d H:i:s', $now)
];

@file_put_contents($dataFile, json_encode($activeDevices, JSON_PRETTY_PRINT));

http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Heartbeat updated successfully",
    "active_count" => count($activeDevices),
    "device" => $activeDevices[$deviceId]
]);

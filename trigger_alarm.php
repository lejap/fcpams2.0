<?php
/**
 * Advanced Emergency Alarm Trigger Webhook (FCM HTTP v1 Version)
 * Supporting GPS location tracking, severity matrix, rate limiting, and token caching.
 */

// Headers & CORS configuration
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Strictly enforce POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method Not Allowed. Emergency triggers must use HTTP POST."
    ]);
    exit();
}

// 1. Unified Request Extraction (Supports both application/x-www-form-urlencoded and application/json)
$rawInput = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_POST, $rawInput);

$trigger = $input['trigger'] ?? '';
if ($trigger !== 'EMERGENCY_TRIGGER') {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid trigger parameter. Request must include trigger=EMERGENCY_TRIGGER."
    ]);
    exit();
}

// 2. Extracted & Sanitized Alert Attributes
$senderId = trim($input['sender_id'] ?? '');
$customTitle = mb_substr(trim($input['title'] ?? 'EMERGENCY SIGNAL RECEIVED'), 0, 100);
$customMessage = mb_substr(trim($input['message'] ?? 'A critical alert was received from the push server.'), 0, 500);

// Advanced Parameters
$severity = strtolower(trim($input['severity'] ?? 'high')); // Options: panic, high, warning, info
if (!in_array($severity, ['panic', 'high', 'warning', 'info'])) {
    $severity = 'high';
}

$latitude = trim($input['latitude'] ?? '');
$longitude = trim($input['longitude'] ?? '');
$mapsUrl = (!empty($latitude) && !empty($longitude)) ? "https://maps.google.com/?q={$latitude},{$longitude}" : '';
$audioUrl = trim($input['audio_url'] ?? '');

$topic = trim($input['topic'] ?? 'emergency_broadcast');
$deviceToken = trim($input['device_token'] ?? '');

// 3. Cooldown / Rate-Limiting Protection (Prevents duplicate triggers within 15 seconds)
if (!empty($senderId)) {
    $cooldownKey = sys_get_temp_dir() . '/emergency_cooldown_' . md5($senderId) . '.tmp';
    if (file_exists($cooldownKey)) {
        $lastSent = (int) file_get_contents($cooldownKey);
        if ((time() - $lastSent) < 15) {
            http_response_code(429);
            echo json_encode([
                "status" => "error",
                "message" => "Rate limit exceeded. Please wait 15 seconds before broadcasting another emergency alert."
            ]);
            exit();
        }
    }
    @file_put_contents($cooldownKey, time());
}

// 4. JWT & Google Access Token Management with Temporary Caching
function base64UrlEncode($data)
{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function getGoogleAccessToken($jsonFilePath)
{
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Firebase service account JSON key file is missing at: " . $jsonFilePath);
    }

    // Check temp cache to eliminate ~300ms OAuth roundtrip delay on frequent alerts
    $cacheFile = sys_get_temp_dir() . '/fcm_access_token_v1.json';
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['access_token'], $cacheData['expires_at'], $cacheData['project_id'])) {
            if ($cacheData['expires_at'] > time() + 300) { // Valid for at least 5 more minutes
                return [
                    'access_token' => $cacheData['access_token'],
                    'project_id' => $cacheData['project_id']
                ];
            }
        }
    }

    $config = json_decode(file_get_contents($jsonFilePath), true);
    if (!$config || !isset($config['private_key'], $config['client_email'], $config['project_id'])) {
        throw new Exception("Invalid format or missing keys in firebase-key.json.");
    }

    $privateKey = $config['private_key'];
    $clientEmail = $config['client_email'];
    $now = time();

    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64UrlEncode(json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]));

    $signature = '';
    $signed = openssl_sign($header . '.' . $claims, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    if (!$signed) {
        throw new Exception("JWT Signing failed. Ensure OpenSSL PHP extension is enabled.");
    }

    $jwt = $header . '.' . $claims . '.' . base64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($httpCode !== 200 || !isset($decoded['access_token'])) {
        throw new Exception("Failed to retrieve Google OAuth access token. Response: " . $response);
    }

    // Write to temporary cache (valid for ~55 minutes)
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $decoded['access_token'],
        'project_id' => $config['project_id'],
        'expires_at' => $now + 3300
    ]));

    return [
        'access_token' => $decoded['access_token'],
        'project_id' => $config['project_id']
    ];
}

// 5. Build and Send FCM Push Message
try {
    $serviceAccountFile = __DIR__ . '/firebase-key.json';
    $auth = getGoogleAccessToken($serviceAccountFile);
    $accessToken = $auth['access_token'];
    $projectId = $auth['project_id'];

    // Construct Data-Only Payload
    $dataPayload = [
        "trigger" => "EMERGENCY_TRIGGER",
        "title" => $customTitle,
        "message" => $customMessage,
        "sender_id" => $senderId,
        "severity" => $severity,
        "timestamp" => (string) time()
    ];

    if (!empty($latitude) && !empty($longitude)) {
        $dataPayload["latitude"] = $latitude;
        $dataPayload["longitude"] = $longitude;
        $dataPayload["maps_url"] = $mapsUrl;
    }

    if (!empty($audioUrl)) {
        $dataPayload["audio_url"] = $audioUrl;
    }

    $messageObj = [
        "data" => $dataPayload,
        "android" => [
            "priority" => "high"
        ]
    ];

    // Determine target (Specific Token vs Topic Broadcast)
    if (!empty($deviceToken)) {
        $messageObj["token"] = $deviceToken;
    } else {
        $messageObj["topic"] = $topic;
    }

    $payload = ["message" => $messageObj];

    $apiUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("FCM Request failed: " . $curlError);
    }

    $resDecoded = json_decode($response, true);

    // 6. Audit Trail Logging with GPS tracking
    $gpsInfo = (!empty($latitude) && !empty($longitude)) ? "GPS: {$latitude},{$longitude} ({$mapsUrl})" : "GPS: Not Provided";
    $logEntry = sprintf(
        "[%s] Sender: %s | Severity: %s | Title: %s | Target: %s | Status: %d | %s\n",
        date('Y-m-d H:i:s'),
        $senderId ?: 'Unknown',
        strtoupper($severity),
        $customTitle,
        !empty($deviceToken) ? 'Token:' . $deviceToken : 'Topic:' . $topic,
        $httpCode,
        $gpsInfo
    );
    @file_put_contents(__DIR__ . '/emergency_audit.log', $logEntry, FILE_APPEND);

    if ($httpCode === 200) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Emergency alert broadcast sent successfully.",
            "severity" => $severity,
            "gps_location" => [
                "latitude" => $latitude,
                "longitude" => $longitude,
                "maps_url" => $mapsUrl
            ],
            "target" => !empty($deviceToken) ? "token" : "topic: {$topic}",
            "fcm_response" => $resDecoded
        ]);
    } else {
        http_response_code($httpCode);
        echo json_encode([
            "status" => "error",
            "message" => "FCM service returned HTTP status code " . $httpCode,
            "fcm_response" => $resDecoded
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}

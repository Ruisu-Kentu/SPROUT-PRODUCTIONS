<?php
header('Content-Type: application/json');
$path = __DIR__ . '/../data/settings.json';

// Ensure file exists with defaults
if (!file_exists($path)) {
    $default = [
        'storeName' => 'Sprout Productions',
        'storeEmail' => 'info@sproutproductions.example',
        'paymentMethods' => ['card' => true, 'cod' => true, 'paypal' => false],
        'shippingFee' => 49.99,
        'adminPasswordHash' => ''
    ];
    file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo file_get_contents($path);
    exit;
}

if ($method === 'POST') {
    // Expect JSON body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $current = json_decode(file_get_contents($path), true);
    // Update allowed fields
    if (isset($data['storeName'])) $current['storeName'] = $data['storeName'];
    if (isset($data['storeEmail'])) $current['storeEmail'] = $data['storeEmail'];
    if (isset($data['shippingFee'])) $current['shippingFee'] = floatval($data['shippingFee']);
    if (isset($data['paymentMethods']) && is_array($data['paymentMethods'])) {
        $pm = $data['paymentMethods'];
        foreach (['card','cod','paypal'] as $k) {
            if (isset($pm[$k])) $current['paymentMethods'][$k] = (bool)$pm[$k];
        }
    }

    // Handle admin password change — if provided, hash and store
    if (!empty($data['newAdminPassword'])) {
        // In a real app, validate current password and require authentication.
        $hash = password_hash($data['newAdminPassword'], PASSWORD_DEFAULT);
        $current['adminPasswordHash'] = $hash;
    }

    // Persist
    if (file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings']);
        exit;
    }

    echo json_encode(['success' => true, 'settings' => $current]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
?>

<?php
/**
 * Booh! Finance – HubRise OAuth Callback
 * Autor: Daniel Bueno Gil
 * Descripción: Intercambia el código OAuth de HubRise por un access_token válido
 * y muestra información básica del cliente conectado.
 */

header('Content-Type: application/json; charset=utf-8');

// CONFIGURACIÓN APP
$client_id     = '3555004761.clients.hubrise.com';       // ← pon tu client_id de HubRise
$client_secret = '3f48e87d7e7b823c471eaea87728f062581a6c066645f9b767b0f4ca3d719151';   // ← pon tu client_secret de HubRise
$redirect_uri  = 'https://booh-finance.app/api/hubrise/callback.php'; // tu URL real

// PASO 1: Validar que recibimos el "code" de HubRise
if (!isset($_GET['code'])) {
    echo json_encode(['error' => 'No se recibió ningún code desde HubRise']);
    exit;
}

$auth_code = $_GET['code'];

// PASO 2: Intercambiar el "code" por un "access_token"
$token_url = 'https://manager.hubrise.com/oauth2/token';

$data = [
    'grant_type'    => 'authorization_code',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'code'          => $auth_code
];

// Enviar POST a HubRise
$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($options);
$response = file_get_contents($token_url, false, $context);

if ($response === FALSE) {
    echo json_encode(['error' => 'No se pudo obtener token desde HubRise']);
    exit;
}

$token_data = json_decode($response, true);

if (isset($token_data['error'])) {
    echo json_encode(['error' => $token_data['error_description'] ?? 'Error desconocido']);
    exit;
}

// PASO 3: Mostrar o guardar los tokens
$access_token  = $token_data['access_token'];
$refresh_token = $token_data['refresh_token'];
$expires_in    = $token_data['expires_in'];

// (Opcional) Guardar en base de datos o archivo
// file_put_contents('tokens.json', json_encode($token_data, JSON_PRETTY_PRINT));

// PASO 4: Probar una llamada a la API de HubRise (para obtener datos básicos)
$api_url = 'https://api.hubrise.com/v1/accounts';

$opts = [
    'http' => [
        'header' => "Authorization: Bearer $access_token\r\n",
        'method' => 'GET'
    ]
];
$ctx = stream_context_create($opts);
$api_response = file_get_contents($api_url, false, $ctx);

$result = [
    'status' => 'success',
    'token_data' => $token_data,
    'sample_api_response' => json_decode($api_response, true)
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

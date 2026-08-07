<?php
/**
 * Entrega la carga de Google Maps JS solo a sesiones autenticadas.
 * La clave vive en private/maps_secrets.php (no versionado).
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['idusuario']) || !isset($_SESSION['idsede'])) {
    echo "console.warn('[maps_config] Sesión requerida para cargar Google Maps');\n";
    exit;
}

$secretsFile = __DIR__ . '/../private/maps_secrets.php';
if (!is_file($secretsFile)) {
    echo "console.warn('[maps_config] Falta private/maps_secrets.php');\n";
    exit;
}

require_once $secretsFile;

$key = defined('GOOGLE_MAPS_API_KEY') ? trim(GOOGLE_MAPS_API_KEY) : '';
if ($key === '' || $key === 'CAMBIAR_por_su_api_key_de_google_maps') {
    echo "console.warn('[maps_config] GOOGLE_MAPS_API_KEY no configurada');\n";
    exit;
}

$mapsUrl = 'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($key) . '&libraries=places';
$mapsUrlJson = json_encode($mapsUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

echo <<<JS
(function () {
    if (window.google && window.google.maps) { return; }
    if (document.querySelector('script[data-maps-config="1"]')) { return; }
    var s = document.createElement('script');
    s.async = true;
    s.setAttribute('data-maps-config', '1');
    s.src = {$mapsUrlJson};
    document.head.appendChild(s);
})();
JS;

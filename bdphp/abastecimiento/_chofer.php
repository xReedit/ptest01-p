<?php
/**
 * Acceso de los choferes (app del chofer). El enlace lleva una clave que NO se guarda: es el HMAC de
 * (idorg, idchofer, acceso_version) con LOGISTICA_CHOFER_KEY (private/logistica_secrets.php). Asi se puede reenviar
 * el mismo enlace en cada ruta, y para desactivarlo basta con subir acceso_version.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
@include_once __DIR__ . '/../../private/logistica_secrets.php';

function chofer_clave_servidor() {
    if (!defined('LOGISTICA_CHOFER_KEY') || strlen(LOGISTICA_CHOFER_KEY) < 32) {
        throw new ErrorUsuario('El acceso de choferes no está configurado en el servidor (LOGISTICA_CHOFER_KEY).');
    }
    return LOGISTICA_CHOFER_KEY;
}

/** "12.<64 hex>" */
function chofer_token($idorg, $idchofer, $version) {
    return (int)$idchofer . '.' . hash_hmac('sha256', (int)$idorg . '|' . (int)$idchofer . '|' . (int)$version, chofer_clave_servidor());
}

/** URL publica del modulo: SOLO de la config (LOGISTICA_APP_URL). Nunca del Host de la peticion: un Host falso mandaria
 *  por WhatsApp un enlace a otro dominio con la clave del chofer. */
function chofer_enlace($token) {
    if (!defined('LOGISTICA_APP_URL') || !preg_match('#^https?://[^\s]+$#', LOGISTICA_APP_URL)) {
        throw new ErrorUsuario('Falta configurar la dirección del sistema (LOGISTICA_APP_URL) para los enlaces de choferes.');
    }
    return rtrim(LOGISTICA_APP_URL, '/') . '/chofer.html#k=' . $token;
}

/** Chofer del token (activo) o null. Comparacion en tiempo constante. */
function chofer_por_token($bd, $token) {
    if (!preg_match('/^(\d{1,10})\.([a-f0-9]{64})$/', (string)$token, $m)) { return null; }
    $c = log_filas($bd, "SELECT idchofer, idorg, nombre, telefono, acceso_version FROM log_chofer WHERE idchofer = ? AND activo = 1", array((int)$m[1]));
    if (!$c) { return null; }
    $esperado = chofer_token($c[0]['idorg'], $c[0]['idchofer'], $c[0]['acceso_version']);
    return hash_equals($esperado, (string)$token) ? $c[0] : null;
}

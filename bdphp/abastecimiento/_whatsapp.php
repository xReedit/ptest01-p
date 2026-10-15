<?php
/**
 * Envio por el WhatsApp del POS: el mismo gateway por donde salen los comprobantes (app "Mensajeria WhatsApp" en la
 * PC de la sede, conectada a backend-pedidos). Se usa POST /chatbot/emitir con x-api-key.
 * Config: private/logistica_secrets.php (LOGISTICA_WSP_URL, LOGISTICA_WSP_KEY = CHATBOT_EMITIR_KEY de backend-pedidos).
 * La URL sale de la config del servidor, nunca de HTTP_HOST: la clave no debe poder ir a otra IP.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
@include_once __DIR__ . '/../../private/logistica_secrets.php';

/** Envia $texto a $numero desde el WhatsApp vinculado en ($idorg, $idsede). Devuelve null si salio, o el error en texto simple. */
function log_whatsapp($idorg, $idsede, $numero, $texto) {
    $num = preg_replace('/\D/', '', (string)$numero);
    if (strlen($num) < 9 || strlen($num) > 15) { return 'El número de WhatsApp no es válido.'; }
    if (strlen($num) === 9) { $num = '51' . $num; }   // celular de Peru sin codigo de pais
    if (!defined('LOGISTICA_WSP_KEY') || LOGISTICA_WSP_KEY === '') {
        error_log('logistica whatsapp: falta LOGISTICA_WSP_KEY en private/logistica_secrets.php');
        return 'El envío automático por WhatsApp no está configurado en el servidor.';
    }
    $url = defined('LOGISTICA_WSP_URL') ? LOGISTICA_WSP_URL : 'https://app.restobar.papaya.com.pe/api.pwa/chatbot/emitir';
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(array('idorg' => (int)$idorg, 'idsede' => (int)$idsede, 'numero' => $num,
            'mensaje' => mb_substr(trim($texto), 0, 1000, 'UTF-8')), JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-api-key: ' . LOGISTICA_WSP_KEY),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ));
    $rpt = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($rpt === false) {
        error_log('logistica whatsapp: ' . $err);
        return 'No se pudo conectar con el servicio de mensajería.';
    }
    if ($code === 200) { return null; }
    error_log('logistica whatsapp: HTTP ' . $code . ' ' . substr((string)$rpt, 0, 200));
    if ($code === 503 && strpos((string)$rpt, 'gateway') !== false) {
        return 'El WhatsApp de la sede no está conectado. Abre "Vincular WhatsApp" en la PC de la sede.';
    }
    return 'El servicio de mensajería no aceptó el envío (código ' . $code . ').';
}

<?php
/**
 * Logistica — envio automatico del resumen del dia. Sin sesion: lo llama el reloj de backend-pedidos
 * (service/logistica.resumen.job.js) cada hora con POST + x-api-key = LOGISTICA_CRON_KEY (private/logistica_secrets.php).
 * Manda a cada empresa con envio automatico activo cuya hora ya llego y que hoy aun no lo recibio.
 * Si el WhatsApp de la sede esta apagado, reintenta en las 3 horas siguientes y luego se rinde hasta manana.
 */
@include_once __DIR__ . '/../../private/logistica_secrets.php';
header('Content-Type: application/json;charset=utf-8');
$clave = isset($_SERVER['HTTP_X_API_KEY']) ? (string)$_SERVER['HTTP_X_API_KEY'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !defined('LOGISTICA_CRON_KEY') || strlen(LOGISTICA_CRON_KEY) < 32
        || !hash_equals(hash('sha256', LOGISTICA_CRON_KEY), hash('sha256', $clave))) {
    error_log('logistica resumen_auto: llamada no autorizada desde ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
    http_response_code(401);
    echo json_encode(array('success' => false, 'data' => null, 'error' => 'no autorizado'));
    exit;
}
ignore_user_abort(true);   // si el reloj corta la espera, igual se termina lo empezado
set_time_limit(300);
define('LOG_SIN_SESION', true);   // _bootstrap.php no pide sesion: ya se valido la clave
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_resumen.php';

log_responder(function () use ($bd) {
    $r = array();
    $orgs = log_filas($bd, "SELECT idorg, idsede_central, umbral_aprobacion_oc, dias_alerta_vencimiento, dias_cobertura, resumen_enviado
            FROM org_logistica WHERE resumen_auto = 1 AND telefono_resumen IS NOT NULL
              AND HOUR(NOW()) BETWEEN resumen_hora AND resumen_hora + 3
              AND (resumen_enviado IS NULL OR resumen_enviado < CURDATE())
            ORDER BY idorg LIMIT 5");   // ponytail: 5 por hora-llamada; si hay mas de 5 empresas a la misma hora, las demas salen en la siguiente hora
    foreach ($orgs as $org) {
        // se toma el turno antes de enviar: si el reloj corre dos veces a la vez, solo uno manda
        if (log_exec($bd, "UPDATE org_logistica SET resumen_enviado = CURDATE() WHERE idorg = ? AND (resumen_enviado IS NULL OR resumen_enviado < CURDATE())",
                array((int)$org['idorg'])) !== 1) { continue; }
        // si PHP muere a mitad (timeout), se devuelve el turno para que se reintente en la proxima hora
        $enCurso = true;
        register_shutdown_function(function () use ($bd, $org, &$enCurso) {
            if ($enCurso) { @log_exec($bd, "UPDATE org_logistica SET resumen_enviado = ? WHERE idorg = ?", array($org['resumen_enviado'], (int)$org['idorg'])); }
        });
        try {
            $err = resumen_enviar($bd, $org);
        } catch (Throwable $e) {
            error_log('logistica resumen_auto org ' . $org['idorg'] . ': ' . $e->getMessage());
            $err = 'error';
        }
        // fallo: se devuelve el turno para reintentar en la proxima hora
        if ($err !== null) { log_exec($bd, "UPDATE org_logistica SET resumen_enviado = ? WHERE idorg = ?", array($org['resumen_enviado'], (int)$org['idorg'])); }
        $enCurso = false;
        $r[] = array('idorg' => (int)$org['idorg'], 'enviado' => $err === null);
    }
    return $r;
});

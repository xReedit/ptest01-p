<?php
/**
 * Logistica — app del chofer (app/logistica/chofer.html). SIN sesion del POS: el chofer entra con su enlace personal;
 * la clave viaja en la cabecera X-Chofer-Token (no en la URL del pedido) y se valida con _chofer.php.
 * ?op=rutas | entregar {idtraslado, lat?, lng?, precision?, recibio?, obs?}
 * Solo ve y marca las rutas asignadas a EL (log_ruta.idchofer) de su empresa.
 */
define('LOG_SIN_SESION', true);   // _bootstrap.php no pide sesion: se valida la clave del chofer abajo
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_chofer.php';
require_once __DIR__ . '/_whatsapp.php';

header('Cache-Control: no-store');   // direcciones y telefonos de las sedes: que no queden guardados
try {
    $cho = chofer_por_token($bd, isset($_SERVER['HTTP_X_CHOFER_TOKEN']) ? $_SERVER['HTTP_X_CHOFER_TOKEN'] : '');
} catch (ErrorUsuario $e) {
    http_response_code(503);
    log_out(false, null, 'La app de choferes no está configurada. Avisa a la central.');
}
if (!$cho) {
    // ponytail: sin sleep (bloquearia a PHP). La clave es HMAC de 256 bits; si hiciera falta, limit_req por IP en nginx.
    error_log('logistica chofer_app: acceso no valido desde ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
    http_response_code(401);
    log_out(false, null, 'Tu enlace ya no sirve. Pide a la central que te mande uno nuevo.');
}
log_exec($bd, "UPDATE log_chofer SET ultimo_acceso = NOW() WHERE idchofer = ?", array((int)$cho['idchofer']));

switch ($op) {
case 'rutas':
    // rutas abiertas + las de los ultimos 2 dias (para ver lo ya entregado). Por indice ix_lr_chofer.
    log_responder(function () use ($bd, $cho) {
        $rutas = log_filas($bd, "SELECT r.idruta, r.estado, DATE_FORMAT(r.fecha_salida, '%d/%m/%Y %H:%i') AS salida, IFNULL(v.descripcion, '') AS vehiculo
                FROM log_ruta r LEFT JOIN log_vehiculo v ON v.idvehiculo = r.idvehiculo
                WHERE r.idchofer = ? AND r.idorg = ? AND (r.estado = 'en_ruta' OR r.fecha_salida >= DATE_SUB(NOW(), INTERVAL 2 DAY))
                ORDER BY r.idruta DESC LIMIT 15", array((int)$cho['idchofer'], (int)$cho['idorg']));
        foreach ($rutas as &$r) {
            $r['paradas'] = log_filas($bd, "SELECT t.idtraslado, t.orden_entrega, t.estado, so.nombre AS origen, sd.nombre AS destino,
                    IFNULL(sd.direccion, '') AS direccion, IFNULL(sd.ciudad, '') AS ciudad, sd.latitude AS lat, sd.longitude AS lng,
                    IFNULL(NULLIF(ls.telefono_whatsapp, ''), IFNULL(sd.telefono, '')) AS telefono,
                    DATE_FORMAT(t.fecha_entrega, '%H:%i') AS entregado, t.entrega_recibio
                    FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen JOIN sede sd ON sd.idsede = t.idsede_destino
                    LEFT JOIN log_sede ls ON ls.idsede = t.idsede_destino
                    WHERE t.idruta = ? AND t.estado <> 'anulado' ORDER BY t.orden_entrega", array((int)$r['idruta']));
            foreach ($r['paradas'] as &$p) {
                $p['items'] = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, 'und') AS unidad, i.cantidad_enviada + 0 AS cantidad
                        FROM log_traslado_item i JOIN producto p ON p.idproducto = i.idproducto_central
                        LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE i.idtraslado = ? ORDER BY p.descripcion",
                    array((int)$p['idtraslado']));
            }
            unset($p);
        }
        unset($r);
        return array('chofer' => $cho['nombre'], 'rutas' => $rutas);
    });

case 'entregar':
    log_responder(function () use ($bd, $cho) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new ErrorUsuario('Operación no válida.'); }
        $b = log_body();
        $id = isset($b['idtraslado']) ? (int)$b['idtraslado'] : 0;
        $t = log_filas($bd, "SELECT t.idtraslado, t.estado, t.fecha_entrega, t.idsede_destino, sd.nombre AS destino,
                IFNULL(ls.telefono_whatsapp, '') AS telefono, (SELECT COUNT(*) FROM log_traslado_item i WHERE i.idtraslado = t.idtraslado) AS productos
                FROM log_traslado t JOIN log_ruta r ON r.idruta = t.idruta AND r.idchofer = ? AND r.idorg = ?
                JOIN sede sd ON sd.idsede = t.idsede_destino LEFT JOIN log_sede ls ON ls.idsede = t.idsede_destino
                WHERE t.idtraslado = ?", array((int)$cho['idchofer'], (int)$cho['idorg'], $id));
        if (!$t) { throw new ErrorUsuario('Esa entrega no está en tus rutas.'); }
        $t = $t[0];
        if ($t['estado'] === 'anulado') { throw new ErrorUsuario('Ese envío fue anulado.'); }
        if ($t['fecha_entrega'] || $t['estado'] !== 'despachado') { return array('ya' => true); }   // ya entregado o la sede ya lo conto
        // GPS: opcional (si el celular no lo da, se registra igual la hora)
        $num = function ($k, $min, $max) use ($b) {
            return isset($b[$k]) && is_numeric($b[$k]) && (float)$b[$k] >= $min && (float)$b[$k] <= $max ? (float)$b[$k] : null;
        };
        $lat = $num('lat', -90, 90); $lng = $num('lng', -180, 180);
        if ($lat === null || $lng === null) { $lat = $lng = null; }
        $prec = $lat !== null ? $num('precision', 0, 100000) : null;
        $txt = function ($k, $max) use ($b) { return mb_substr(trim(isset($b[$k]) && is_string($b[$k]) ? $b[$k] : ''), 0, $max, 'UTF-8'); };
        $recibio = $txt('recibio', 80); $obs = $txt('obs', 255);
        // se toma la entrega antes de avisar: dos toques no mandan dos mensajes
        if (log_exec($bd, "UPDATE log_traslado SET fecha_entrega = NOW(), idchofer_entrega = ?, entrega_lat = ?, entrega_lng = ?, entrega_precision = ?,
                entrega_recibio = ?, entrega_obs = ? WHERE idtraslado = ? AND fecha_entrega IS NULL AND estado = 'despachado'",
                array((int)$cho['idchofer'], $lat, $lng, $prec === null ? null : (int)$prec, $recibio !== '' ? $recibio : null, $obs !== '' ? $obs : null, $id)) !== 1) {
            return array('ya' => true);
        }
        // aviso al encargado de la sede: que cuente y confirme (si falla, la entrega queda igual)
        if ($t['estado'] === 'despachado' && $t['telefono'] !== '') {
            $c = log_filas($bd, "SELECT idsede_central FROM org_logistica WHERE idorg = ?", array((int)$cho['idorg']));
            if ($c) {
                log_whatsapp($cho['idorg'], $c[0]['idsede_central'], $t['telefono'], 'Hola, ' . $cho['nombre'] . ' acaba de dejar en ' . $t['destino']
                    . ' el envío N° ' . $t['idtraslado'] . ' (' . $t['productos'] . ' producto(s)). Cuéntalo y confírmalo en Logística > Recibir mercadería.');
            }
        }
        return array('ok' => true, 'hora' => log_filas($bd, "SELECT DATE_FORMAT(NOW(), '%H:%i') AS h")[0]['h']);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

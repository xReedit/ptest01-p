<?php
/**
 * Logistica — linea de tiempo de cada proceso (quien hizo que y cuando), para equipos con varios usuarios.
 * Cada funcion devuelve [{tipo, titulo, quien, detalle, fecha 'dd/mm/yyyy HH:MM'}] ordenado por fecha.
 * Todo sale de columnas que ya existen + log_oc_evento (migracion logistica/023). Consultas por PK o indice:
 * log_bandeja_linea (ix_lbl_detalle, ix_lbl_orden, ix_lbl_traslado), solicitud_producto_detalle (idx_solicitud),
 * log_oc_evento (ix_loe_orden), recepcion (rec_idorden_IDX).
 */

const SQL_NOMBRE = "IFNULL(NULLIF(TRIM(%s.nombres), ''), %s.usuario)";

function h_nombre($alias) { return sprintf(SQL_NOMBRE, $alias, $alias); }

/** Acumulador: add() ignora pasos sin fecha; fin() ordena (misma hora: orden en que se agrego) y formatea. */
class Historia {
    private $h = array();
    function add($t, $tipo, $titulo, $quien = '', $detalle = '') {
        if ($t) { $this->h[] = array('i' => count($this->h), 't' => $t, 'tipo' => $tipo, 'titulo' => $titulo, 'quien' => (string)$quien, 'detalle' => (string)$detalle); }
    }
    function fin() {
        $h = $this->h;
        usort($h, function ($a, $b) { return strcmp($a['t'], $b['t']) ?: $a['i'] - $b['i']; });
        return array_map(function ($x) {
            return array('tipo' => $x['tipo'], 'titulo' => $x['titulo'], 'quien' => $x['quien'], 'detalle' => $x['detalle'],
                'fecha' => date('d/m/Y H:i', strtotime($x['t'])));
        }, $h);
    }
}

const OC_TITULOS = array('creada' => 'Orden generada', 'aprobada' => 'Aprobada', 'precio' => 'Cambió un precio', 'volvio' => 'Volvió a aprobación',
    'enviada' => 'Enviada al proveedor', 'anulada' => 'Anulada', 'cerrada' => 'Cerrada sin lo que faltaba');

/** Pasos de una orden de compra (sin su origen). $pre = "Compra N° 5: " cuando va dentro de otra historia; $todo = incluye precios. */
function h_pasos_oc($bd, Historia $h, $oc, $pre = '', $todo = true) {
    $id = (int)$oc['idorden'];
    $evs = log_filas($bd, "SELECT e.evento, e.fecha_hora, e.detalle, " . h_nombre('u') . " AS quien
            FROM log_oc_evento e LEFT JOIN usuario u ON u.idusuario = e.idusuario WHERE e.idorden = ? ORDER BY e.id", array($id));
    foreach ($evs as $e) {
        if (!$todo && in_array($e['evento'], array('precio', 'volvio'), true)) { continue; }
        $h->add($e['fecha_hora'], $e['evento'], $pre . (isset(OC_TITULOS[$e['evento']]) ? OC_TITULOS[$e['evento']] : $e['evento']),
            $e['quien'] ?: 'Sistema', $e['evento'] === 'creada' && $pre ? '' : $e['detalle']);
    }
    if (!$evs) {   // ordenes anteriores al historial: lo que dicen sus columnas
        $u = log_filas($bd, "SELECT " . h_nombre('c') . " AS crea, " . h_nombre('a') . " AS aprueba FROM orden_compra oc
                LEFT JOIN usuario c ON c.idusuario = oc.idusuario LEFT JOIN usuario a ON a.idusuario = oc.idusuario_aprueba WHERE oc.idorden = ?", array($id));
        $h->add($oc['fecha_hora'] ?: $oc['fecha'], 'creada', $pre . 'Orden generada', $u ? $u[0]['crea'] : '');
        $h->add($oc['fecha_aprueba'], 'aprobada', $pre . 'Aprobada', $u && $u[0]['aprueba'] ? $u[0]['aprueba'] : 'Sistema');
        $h->add($oc['fecha_envio'], 'enviada', $pre . 'Enviada al proveedor');
    }
    foreach (log_filas($bd, "SELECT r.fecha_hora, r.comprobante, r.nota, " . h_nombre('u') . " AS quien
            FROM recepcion r LEFT JOIN usuario u ON u.idusuario = r.idusuario WHERE r.idorden = ? ORDER BY r.idrecepcion", array($id)) as $r) {
        $h->add($r['fecha_hora'], 'recepcion', $pre . 'Llegó mercadería del proveedor', $r['quien'],
            trim(($r['comprobante'] ? 'Comprobante ' . $r['comprobante'] : '') . ($r['nota'] ? ' · ' . $r['nota'] : ''), ' ·'));
    }
}

/** Pedidos de las sedes que originaron un traslado u orden (columna de log_bandeja_linea con indice: idorden / idtraslado). */
function h_origen($bd, Historia $h, $col, $id) {
    $peds = log_filas($bd, "SELECT b.idsolicitud_producto AS n, sd.nombre AS sede, s.fecha_solicitud, s.nota, MIN(b.fecha_hora) AS acepto_t,
            " . h_nombre('us') . " AS pidio, " . h_nombre('ua') . " AS acepto, COUNT(*) AS productos
            FROM log_bandeja_linea b JOIN solicitud_producto s ON s.idsolicitud_producto = b.idsolicitud_producto
            JOIN sede sd ON sd.idsede = b.idsede
            LEFT JOIN usuario us ON us.idusuario = s.idusuario_solicita LEFT JOIN usuario ua ON ua.idusuario = b.idusuario
            WHERE b.$col = ? GROUP BY b.idsolicitud_producto, b.idusuario", array((int)$id));
    foreach ($peds as $p) {
        $h->add($p['fecha_solicitud'], 'pedido', 'Pedido N° ' . $p['n'] . ' de ' . $p['sede'], $p['pidio'] ?: 'Sistema (automático)', (string)$p['nota']);
        $h->add($p['acepto_t'], 'bandeja', 'Aceptado en la Bandeja central', $p['acepto'], $p['productos'] . ' producto(s) del pedido N° ' . $p['n']);
    }
}

/** Pasos de un traslado: despacho, salida del chofer, aviso, entrega y recepcion en la sede. */
function h_pasos_traslado($bd, Historia $h, $idt, $pre = '') {
    $t = log_filas($bd, "SELECT t.*, so.nombre AS origen, sd.nombre AS destino, " . h_nombre('ud') . " AS despacho, " . h_nombre('ur') . " AS recibio,
            r.fecha_salida, ch.nombre AS chofer, ve.descripcion AS vehiculo, ce.nombre AS chofer_entrega
            FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen JOIN sede sd ON sd.idsede = t.idsede_destino
            LEFT JOIN usuario ud ON ud.idusuario = t.idusuario_despacha LEFT JOIN usuario ur ON ur.idusuario = t.idusuario_recibe
            LEFT JOIN log_ruta r ON r.idruta = t.idruta LEFT JOIN log_chofer ch ON ch.idchofer = r.idchofer
            LEFT JOIN log_vehiculo ve ON ve.idvehiculo = r.idvehiculo LEFT JOIN log_chofer ce ON ce.idchofer = t.idchofer_entrega
            WHERE t.idtraslado = ?", array((int)$idt));
    if (!$t) { return null; }
    $t = $t[0];
    $h->add($t['fecha_despacho'], 'despacho', $pre . 'Despachado de ' . $t['origen'] . ' a ' . $t['destino'], $t['despacho'],
        trim(($t['guia_numero'] ? 'Guía ' . $t['guia_numero'] : '') . ($t['valor_enviado'] !== null ? ' · valor S/ ' . number_format((float)$t['valor_enviado'], 2) : '') . ($t['nota'] ? ' · ' . $t['nota'] : ''), ' ·'));
    if ($t['chofer']) {
        $h->add($t['fecha_salida'] ?: $t['fecha_despacho'], 'camino', $pre . 'Salió en camino', $t['chofer'], $t['vehiculo'] ? 'Vehículo ' . $t['vehiculo'] : '');
    }
    $h->add($t['avisado'], 'aviso', $pre . 'Se avisó a ' . $t['destino'] . ' por WhatsApp', 'Sistema');
    $h->add($t['fecha_entrega'], 'entrega', $pre . 'El chofer lo entregó', $t['chofer_entrega'] ?: $t['chofer'],
        trim(($t['entrega_recibio'] ? 'Lo recibió ' . $t['entrega_recibio'] : '') . ($t['entrega_lat'] ? ' · con ubicación GPS' : '') . ($t['entrega_obs'] ? ' · ' . $t['entrega_obs'] : ''), ' ·'));
    $dif = $t['valor_recibido'] !== null && $t['valor_enviado'] !== null ? (float)$t['valor_enviado'] - (float)$t['valor_recibido'] : 0;
    $h->add($t['fecha_recibe'], $t['estado'] === 'recibido_con_diferencia' ? 'diferencia' : 'recibido',
        $pre . ($t['estado'] === 'recibido_con_diferencia' ? 'Recibido en ' . $t['destino'] . ' con diferencia' : 'Recibido en ' . $t['destino']), $t['recibio'],
        trim(($dif > 0.009 ? 'Faltó S/ ' . number_format($dif, 2) : '') . ($t['nota_recibe'] ? ' · ' . $t['nota_recibe'] : ''), ' ·'));
    return $t;
}

function historia_oc($bd, $oc) {
    $h = new Historia();
    h_origen($bd, $h, 'idorden', (int)$oc['idorden']);
    h_pasos_oc($bd, $h, $oc);
    return $h->fin();
}

function historia_traslado($bd, $idt) {
    $h = new Historia();
    h_origen($bd, $h, 'idtraslado', $idt);
    h_pasos_traslado($bd, $h, $idt);
    return $h->fin();
}

/** Todo el camino de un pedido de sede: pedido, Bandeja, compras, envios y recepcion. $s = fila de solicitud_producto. */
function historia_pedido($bd, $s) {
    $h = new Historia();
    $id = (int)$s['idsolicitud_producto'];
    $u = log_filas($bd, "SELECT sd.nombre AS sede, " . h_nombre('us') . " AS pidio,
            (SELECT COUNT(*) FROM solicitud_producto_detalle d WHERE d.idsolicitud_producto = s.idsolicitud_producto) AS productos
            FROM solicitud_producto s JOIN sede sd ON sd.idsede = s.idsede_solicita LEFT JOIN usuario us ON us.idusuario = s.idusuario_solicita
            WHERE s.idsolicitud_producto = ?", array($id))[0];
    $h->add($s['fecha_solicitud'], 'pedido', 'Pedido N° ' . $id . ' de ' . $u['sede'], $u['pidio'] ?: 'Sistema (automático)',
        trim($u['productos'] . ' producto(s)' . ($s['nota'] ? ' · ' . $s['nota'] : '')));
    $h->add($s['fecha_visto'], 'visto', 'La central lo vio');
    $lin = log_filas($bd, "SELECT b.decision, b.idusuario, b.fecha_hora, b.idorden, b.idtraslado, so.nombre AS origen, " . h_nombre('u') . " AS quien
            FROM solicitud_producto_detalle d JOIN log_bandeja_linea b ON b.idsolicitud_detalle = d.idsolicitud_producto_detalle
            LEFT JOIN sede so ON so.idsede = b.idsede_origen LEFT JOIN usuario u ON u.idusuario = b.idusuario
            WHERE d.idsolicitud_producto = ? ORDER BY b.id", array($id));
    // aceptado: uno por usuario que decidio, con el resumen de donde sale cada cosa
    $grupos = array();
    foreach ($lin as $l) {
        $k = (int)$l['idusuario'];
        if (!isset($grupos[$k])) { $grupos[$k] = array('t' => $l['fecha_hora'], 'quien' => $l['quien'], 'c' => array()); }
        $txt = in_array($l['decision'], array('traslado', 'produccion'), true) ? $l['decision'] . ':' . $l['origen'] : $l['decision'];
        $grupos[$k]['c'][$txt] = (isset($grupos[$k]['c'][$txt]) ? $grupos[$k]['c'][$txt] : 0) + 1;
    }
    foreach ($grupos as $g) {
        $h->add($g['t'], 'bandeja', 'Aceptado en la Bandeja central', $g['quien'] ?: 'Sistema',
            implode(' · ', array_map('h_decision', array_keys($g['c']), $g['c'])));
    }
    foreach (array_unique(array_filter(array_map('intval', array_column($lin, 'idorden')))) as $idorden) {
        $oc = log_filas($bd, "SELECT * FROM orden_compra WHERE idorden = ?", array($idorden));
        if ($oc) { h_pasos_oc($bd, $h, $oc[0], 'Compra N° ' . $idorden . ': ', false); }
    }
    foreach (array_unique(array_filter(array_map('intval', array_column($lin, 'idtraslado')))) as $idt) {
        h_pasos_traslado($bd, $h, $idt, 'Envío N° ' . $idt . ': ');
    }
    $r = $h->fin();
    if ($s['estado'] === 'cancelado') { $r[] = array('tipo' => 'anulada', 'titulo' => 'Cancelado por la sede', 'quien' => '', 'detalle' => '', 'fecha' => ''); }
    return $r;
}

/** "3 salen del central", "1 se trae de EL BRASAS" (cuenta productos por decision). */
function h_decision($k, $n) {
    $pl = $n > 1;
    if (strpos($k, 'traslado:') === 0) { return $n . ($pl ? ' se traen de ' : ' se trae de ') . substr($k, 9); }
    if (strpos($k, 'produccion:') === 0) { return $n . ($pl ? ' se producen en ' : ' se produce en ') . substr($k, 11); }
    $t = array('central' => array('sale del central', 'salen del central'), 'compra' => array('se compra', 'se compran'),
        'rechazo' => array('no se envía', 'no se envían'));
    return $n . ' ' . $t[$k][$pl ? 1 : 0];
}

/** Conteo rapido de una sede: empezo, termino, y la central ajusto o descarto. */
function historia_conteo($bd, $c) {
    $u = log_filas($bd, "SELECT " . h_nombre('a') . " AS crea, " . h_nombre('b') . " AS cierra, " . h_nombre('p') . " AS aprueba, " . h_nombre('n') . " AS anula
            FROM inventario_conteo c LEFT JOIN usuario a ON a.idusuario = c.idusuario_crea LEFT JOIN usuario b ON b.idusuario = c.idusuario_cierre
            LEFT JOIN usuario p ON p.idusuario = c.idusuario_aprob LEFT JOIN usuario n ON n.idusuario = c.idusuario_anula
            WHERE c.idinventario_conteo = ?", array((int)$c['idinventario_conteo']))[0];
    $h = new Historia();
    $h->add($c['fecha_crea'], 'creada', 'Empezó a contar', $u['crea']);
    $h->add($c['fecha_cierre'], 'enviada', 'Terminó de contar', $u['cierra']);
    $h->add($c['fecha_aprob'], 'aprobada', 'La central ajustó el stock', $u['aprueba']);
    $h->add($c['fecha_anula'], 'anulada', 'La central descartó el conteo', $u['anula']);
    return $h->fin();
}

<?php
/**
 * Logistica F6 — Traslados y rutas.
 * Central: ?op=por_enviar | no_enviar | mover_almacen | despachar | avisar | listar | hoja&id=   Sede: ?op=por_recibir | recibir
 * Avisar: manda al chofer su ruta con su enlace (app del chofer) y a cada encargado, por el WhatsApp del POS de la central, lo que va en camino (una sola vez por envio).
 * Despachar: por cada grupo origen -> destino crea un traslado, saca el stock del origen (almacen_ie tipo '2',
 *   cantidad negativa, motivo "DESPACHO A TIENDA"), reparte lotes FEFO y marca las decisiones de la bandeja.
 * Recibir: la sede anota lo que llego; entra al destino (almacen_ie tipo '1', motivo "RECEPCION DE TRASLADO").
 * El trigger stock_insert_almacen_i suma la cantidad en producto_stock (camino legacy, igual que Inventario).
 * Doble clic: token en log_token (migracion 011).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';

/** Almacen principal de una sede participante de la org (o error claro). */
/**
 * Recibir un producto que en la sede se vende por porcion: suma porciones (cantidad x factor de la central) y deja el
 * kardex de la porcion. La primera vez enlaza la porcion elegida y crea/corrige su conversion con el factor de la central.
 */
function recibir_en_porciones($bd, $ido, $idsede, $us, $item, $rec, $factor, $elegida, $idtraslado) {
    $po = porcion_enlazada($bd, $idsede, $item['idproducto_destino']);
    if (!$po) {
        $po = $elegida ? log_filas($bd, "SELECT idporcion, descripcion FROM porcion WHERE idporcion = ? AND idsede = ? AND estado = 0",
            array((int)$elegida, (int)$idsede)) : null;
        if (!$po) { throw new ErrorUsuario('De ' . $item['descripcion'] . ' elige a qué porción de tu sede va.'); }
        $po = $po[0];
        log_exec($bd, "UPDATE porcion SET idproducto_de = ? WHERE idporcion = ?", array((int)$item['idproducto_destino'], (int)$po['idporcion']));
    }
    $id = (int)$po['idporcion'];
    $conv = log_filas($bd, "SELECT idporcion_unidad_conversion AS idc, factor_conversion + 0 AS f FROM porcion_unidad_conversion
            WHERE idporcion = ? AND activo = 1 LIMIT 1", array($id));
    if (!$conv) {
        log_exec($bd, "INSERT INTO porcion_unidad_conversion (idporcion, nombre_unidad_alternativa, factor_conversion, activo) VALUES (?, ?, ?, 1)",
            array($id, mb_substr($item['descripcion'], 0, 100, 'UTF-8'), $factor));
    } elseif (abs((float)$conv[0]['f'] - $factor) > 0.0001) {
        // el bote fisico es el mismo para todas las sedes: manda el numero de la central
        log_exec($bd, "UPDATE porcion_unidad_conversion SET factor_conversion = ? WHERE idporcion_unidad_conversion = ?", array($factor, (int)$conv[0]['idc']));
    }
    $cant = round($rec * $factor, 2);
    log_exec($bd, "UPDATE porcion SET stock = IFNULL(stock, 0) + ? WHERE idporcion = ?", array($cant, $id));
    log_exec($bd, "INSERT INTO porcion_historial (tipo_movimiento, idtipo_movimiento_stock, fecha_date, fecha, hora, cantidad, idusuario, idsede, idporcion, stock_total)
            SELECT ?, 1, CURDATE(), CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, stock FROM porcion WHERE idporcion = ?",
        array('TRASLADO #' . $idtraslado, txt_cant($cant), (int)$us, (int)$idsede, $id, $id));
    return array('idporcion' => $id, 'porcion' => $po['descripcion'], 'porciones' => $cant);
}

function almacen_de($bd, $idorg, $idsede) {
    $r = log_filas($bd, "SELECT ls.idalmacen_principal, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idsede = ? AND ls.idorg = ?", array((int)$idsede, (int)$idorg));
    if (!$r || (int)$r[0]['idalmacen_principal'] <= 0) {
        throw new ErrorUsuario('La sede ' . ($r ? $r[0]['nombre'] : $idsede) . ' no tiene almacén elegido. Elígelo en Configurar.');
    }
    return (int)$r[0]['idalmacen_principal'];
}

/**
 * Motivo de almacen por nombre (lo crean las migraciones 012/016). Sin respaldo con id fijo: el id de dev no es el
 * de prod y se registraria el movimiento con otro motivo. Si falta, se corta y queda en el log.
 */
function motivo_requerido($bd, $nombre, $tipo) {
    $id = motivo_id($bd, $nombre, $tipo, 0);
    if ($id <= 0) {
        error_log("logistica: falta el motivo de almacen '$nombre' tipo $tipo (migraciones 012/016)");
        throw new ErrorUsuario('Falta configurar el motivo de almacén "' . $nombre . '". Avisa a soporte.');
    }
    return $id;
}

/** Producto de la sede que corresponde al producto central (la central es el propio central). */
function producto_en_sede($bd, $org, $idcentral, $idsede) {
    if ((int)$idsede === (int)$org['idsede_central']) { return (int)$idcentral; }
    $r = log_filas($bd, "SELECT v.idproducto FROM log_producto_central v JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            WHERE v.idsede = ? AND v.idproducto_central = ?", array((int)$idsede, (int)$idcentral));
    return $r ? (int)$r[0]['idproducto'] : 0;
}

/** Decisiones pendientes de enviar (central -> sede y sede -> sede), por origen/destino/producto. */
function pendientes_envio($bd, $org) {
    return log_filas($bd, "SELECT IF(b.decision = 'central', ?, b.idsede_origen) AS origen, b.idsede AS destino,
            b.idproducto_central, SUM(b.cantidad) AS cantidad
            FROM log_bandeja_linea b
            WHERE b.idorg = ? AND b.decision IN ('central', 'traslado', 'produccion') AND b.estado = 'pendiente' AND b.idtraslado IS NULL
            GROUP BY origen, destino, b.idproducto_central HAVING origen <> destino", array((int)$org['idsede_central'], (int)$org['idorg']));
}

/** Saldo de lotes en un almacen (movimientos), del que vence antes al que vence despues. Lo VENCIDO no se despacha. */
function lotes_fefo($bd, $idalmacen, $idcentral) {
    return log_filas($bd, "SELECT l.idlote, l.lote, l.vencimiento, SUM(m.cantidad) AS saldo
            FROM log_lote l JOIN log_lote_mov m ON m.idlote = l.idlote AND m.idalmacen = ?
            WHERE l.idproducto_central = ? AND (l.vencimiento IS NULL OR l.vencimiento >= CURDATE())
            GROUP BY l.idlote HAVING saldo > 0
            ORDER BY l.vencimiento IS NULL, l.vencimiento, l.idlote", array((int)$idalmacen, (int)$idcentral));
}

/** Nombre por texto: vincula si existe en la org, crea si no (vehiculo / chofer). */
function catalogo_simple($bd, $tabla, $campo, $idorg, $texto) {
    $t = log_mayus($texto, $tabla === 'log_vehiculo' ? 60 : 80);
    if ($t === '') { return null; }
    $id = $tabla === 'log_vehiculo' ? 'idvehiculo' : 'idchofer';
    $r = log_filas($bd, "SELECT $id AS id FROM $tabla WHERE idorg = ? AND UPPER($campo) = ? LIMIT 1", array((int)$idorg, $t));
    if ($r) { return (int)$r[0]['id']; }
    log_exec($bd, "INSERT INTO $tabla (idorg, $campo) VALUES (?, ?)", array((int)$idorg, $t));
    return (int)$bd->lastInsertId();
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'por_enviar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $filas = pendientes_envio($bd, $org);
        $porcOrg = porciones_org($bd, $ido);
        $sedes = array();
        foreach (log_filas($bd, "SELECT ls.idsede, s.nombre, ls.idalmacen_principal, ls.telefono_whatsapp, IFNULL(a.descripcion, '') AS almacen FROM log_sede ls
                JOIN sede s ON s.idsede = ls.idsede LEFT JOIN almacen a ON a.idalmacen = ls.idalmacen_principal WHERE ls.idorg = ?", array((int)$ido)) as $s) { $sedes[(int)$s['idsede']] = $s; }
        $grupos = array();
        foreach ($filas as $f) {
            $o = (int)$f['origen']; $d = (int)$f['destino']; $c = (int)$f['idproducto_central'];
            $po = producto_en_sede($bd, $org, $c, $o);
            $alm = isset($sedes[$o]) ? (int)$sedes[$o]['idalmacen_principal'] : 0;
            $info = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad, p.precio_unitario + 0 AS costo,
                    IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = ? AND ps.idalmacen = ?), 0) AS stock
                    FROM producto p LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE p.idproducto = ?",
                array($po, $alm, $c));
            $k = $o . '-' . $d;
            if (!isset($grupos[$k])) {
                $grupos[$k] = array('origen' => $o, 'origen_nombre' => isset($sedes[$o]) ? $sedes[$o]['nombre'] : '?', 'destino' => $d,
                    'destino_nombre' => isset($sedes[$d]) ? $sedes[$d]['nombre'] : '?', 'almacen' => isset($sedes[$o]) ? $sedes[$o]['almacen'] : '',
                    'telefono' => isset($sedes[$d]) ? $sedes[$d]['telefono_whatsapp'] : null, 'items' => array());
            }
            $st = $info ? (float)$info[0]['stock'] : 0;
            $porc = $o !== (int)$org['idsede_central'] && $po && isset($porcOrg[$c]) ? porcion_sede($bd, $o, $po, $porcOrg[$c], 1) : null;
            if ($porc) { $st = $porc['stock']; }   // en esa sede el bote ya es porciones
            // no alcanza: ¿esta en otro almacen de la misma sede? (ej. PRODUCCION) -> que lo pasen al principal
            $otros = !$porc && $st < (float)$f['cantidad'] && $po
                ? log_filas($bd, "SELECT a.idalmacen, a.descripcion AS almacen, ps.stock + 0 AS stock FROM producto_stock ps
                        JOIN almacen a ON a.idalmacen = ps.idalmacen AND a.idsede = ? AND a.estado = 0
                        WHERE ps.idproducto = ? AND ps.idalmacen <> ? AND ps.stock > 0 ORDER BY ps.stock DESC", array($o, $po, $alm)) : array();
            $ped = log_filas($bd, "SELECT b.idsolicitud_producto AS idsol, d.cantidad_solicitada + 0 AS pidio, IFNULL(d.cantidad_despachada, 0) + 0 AS salio,
                    SUM(b.cantidad) AS falta, SUM(IF(b.decision = 'produccion', b.cantidad, 0)) AS producir
                    FROM log_bandeja_linea b JOIN solicitud_producto_detalle d ON d.idsolicitud_producto_detalle = b.idsolicitud_detalle
                    WHERE b.idorg = ? AND b.idproducto_central = ? AND b.idsede = ? AND b.decision IN ('central', 'traslado', 'produccion')
                      AND b.estado = 'pendiente' AND b.idtraslado IS NULL AND IF(b.decision = 'central', ?, b.idsede_origen) = ?
                    GROUP BY b.idsolicitud_detalle ORDER BY b.idsolicitud_producto", array((int)$ido, $c, $d, (int)$org['idsede_central'], $o));
            // repartos sin pedido (Repartir / Stock parado / Por vencer): no tienen detalle de solicitud
            $sinPed = (float)log_filas($bd, "SELECT IFNULL(SUM(cantidad), 0) AS q FROM log_bandeja_linea WHERE idorg = ? AND idproducto_central = ? AND idsede = ?
                    AND decision IN ('central', 'traslado') AND estado = 'pendiente' AND idtraslado IS NULL AND idsolicitud_producto = 0
                    AND IF(decision = 'central', ?, idsede_origen) = ?", array((int)$ido, $c, $d, (int)$org['idsede_central'], $o))[0]['q'];
            $grupos[$k]['items'][] = array('pedidos' => $ped, 'sin_pedido' => $sinPed, 'idproducto_central' => $c, 'descripcion' => $info ? $info[0]['descripcion'] : '?',
                'unidad' => $info ? $info[0]['unidad'] : '', 'cantidad' => (float)$f['cantidad'], 'stock' => $st, 'otros_almacenes' => $otros,
                'costo' => $info ? (float)$info[0]['costo'] : 0, 'producir' => array_sum(array_column($ped, 'producir')));
        }
        return array('grupos' => array_values($grupos), 'central' => (int)$org['idsede_central'],
            'vehiculos' => array_column(log_filas($bd, "SELECT descripcion FROM log_vehiculo WHERE idorg = ? AND activo = 1 ORDER BY descripcion", array((int)$ido)), 'descripcion'),
            'choferes' => log_filas($bd, "SELECT idchofer, nombre, IFNULL(telefono, '') AS telefono FROM log_chofer WHERE idorg = ? AND activo = 1 ORDER BY nombre", array((int)$ido)));
    });

case 'despachar':
    // grupos: [{origen, destino, items:[{idproducto_central, cantidad}]}], vehiculo, chofer, guia_tipo, guia_numero, token
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $grupos = isset($b['grupos']) && is_array($b['grupos']) ? array_slice($b['grupos'], 0, 100) : array();
        if (!$grupos) { throw new ErrorUsuario('Marca al menos un envío.'); }
        $guia = isset($b['guia_tipo']) && in_array($b['guia_tipo'], array('ninguna', 'interna', 'manual'), true) ? $b['guia_tipo'] : 'interna';
        $guiaNum = $guia === 'manual' ? log_mayus(isset($b['guia_numero']) ? $b['guia_numero'] : '', 30) : null;
        if ($guia === 'manual' && $guiaNum === '') { throw new ErrorUsuario('Escribe el número de la guía de remisión.'); }
        $token = log_token($b);
        $motSal = motivo_requerido($bd, 'DESPACHO A TIENDA', 2);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'despacho', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            // lo pendiente, bloqueado: dos despachos a la vez no envian lo mismo dos veces
            log_filas($bd, "SELECT id FROM log_bandeja_linea WHERE idorg = ? AND decision IN ('central','traslado','produccion') AND estado = 'pendiente' FOR UPDATE", array((int)$ido));
            $pend = array();
            foreach (pendientes_envio($bd, $org) as $p) { $pend[$p['origen'] . '-' . $p['destino'] . '-' . $p['idproducto_central']] = (float)$p['cantidad']; }
            $idveh = catalogo_simple($bd, 'log_vehiculo', 'descripcion', $ido, isset($b['vehiculo']) ? $b['vehiculo'] : '');
            // chofer registrado (lista) o, por compatibilidad, texto libre
            $idcho = null;
            if (!empty($b['idchofer'])) {
                $ch = log_filas($bd, "SELECT idchofer FROM log_chofer WHERE idchofer = ? AND idorg = ? AND activo = 1", array((int)$b['idchofer'], (int)$ido));
                if (!$ch) { throw new ErrorUsuario('Ese chofer ya no está activo. Elige otro.'); }
                $idcho = (int)$ch[0]['idchofer'];
            } elseif (isset($b['chofer']) && is_string($b['chofer']) && trim($b['chofer']) !== '') {
                $idcho = catalogo_simple($bd, 'log_chofer', 'nombre', $ido, $b['chofer']);
            }
            log_exec($bd, "INSERT INTO log_ruta (idorg, fecha, idvehiculo, idchofer, estado, idusuario, fecha_salida) VALUES (?, CURDATE(), ?, ?, 'en_ruta', ?, NOW())",
                array((int)$ido, $idveh, $idcho, (int)$us));
            $idruta = (int)$bd->lastInsertId();
            $traslados = array(); $orden = 0;
            $porcOrg = porciones_org($bd, $ido);
            foreach ($grupos as $g) {
                $o = is_array($g) && isset($g['origen']) ? (int)$g['origen'] : 0;
                $d = is_array($g) && isset($g['destino']) ? (int)$g['destino'] : 0;
                $items = is_array($g) && isset($g['items']) && is_array($g['items']) ? $g['items'] : array();
                if ($o === $d) { throw new ErrorUsuario('Origen y destino no pueden ser la misma sede.'); }
                $almO = almacen_de($bd, $ido, $o);
                $almD = almacen_de($bd, $ido, $d);
                $nomD = log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array($d))[0]['nombre'];
                log_exec($bd, "INSERT INTO log_traslado (idorg, idruta, orden_entrega, idsede_origen, idalmacen_origen, idsede_destino, idalmacen_destino,
                        estado, guia_tipo, guia_numero, idusuario_despacha, fecha_despacho) VALUES (?, ?, ?, ?, ?, ?, ?, 'despachado', ?, ?, ?, NOW())",
                    array((int)$ido, $idruta, ++$orden, $o, $almO, $d, $almD, $guia, $guiaNum, (int)$us));
                $idt = (int)$bd->lastInsertId();
                log_exec($bd, "INSERT INTO almacen_ie (idorg, idsede, idusuario, idalmacen, tipo, fecha, idproducto_motivo_es, motivo, fecha_hora, hora)
                        VALUES (?, ?, ?, ?, '2', DATE_FORMAT(NOW(), '%d/%m/%Y'), ?, ?, NOW(), DATE_FORMAT(NOW(), '%H:%i:%s'))",
                    array((int)$ido, $o, (int)$us, $almO, $motSal, 'TRASLADO #' . $idt . ' A ' . mb_substr($nomD, 0, 80, 'UTF-8')));
                $idie = (int)$bd->lastInsertId();
                log_exec($bd, "UPDATE log_traslado SET idalmacen_ie_salida = ? WHERE idtraslado = ?", array($idie, $idt));
                $n = 0; $valor = 0;
                foreach ($items as $it) {
                    $c = is_array($it) && isset($it['idproducto_central']) ? (int)$it['idproducto_central'] : 0;
                    $cant = is_array($it) && isset($it['cantidad']) ? round((float)$it['cantidad'], 3) : 0;
                    if ($cant <= 0) { continue; }
                    $k = $o . '-' . $d . '-' . $c;
                    if (!isset($pend[$k])) { throw new ErrorUsuario('Hay un producto que ya no está pendiente de enviar. Actualiza la página.'); }
                    $prodO = producto_en_sede($bd, $org, $c, $o);
                    $prodD = producto_en_sede($bd, $org, $c, $d);
                    $nom = log_filas($bd, "SELECT descripcion FROM producto WHERE idproducto = ?", array($c))[0]['descripcion'];
                    if (!$prodO || !$prodD) { throw new ErrorUsuario($nom . ' no está en el catálogo de una de las sedes. Envíalo a las sedes desde Catálogo.'); }
                    if ($cant > $pend[$k] + 0.0001) { throw new ErrorUsuario('De ' . $nom . ' solo hay ' . $pend[$k] . ' por enviar.'); }
                    if ($o !== (int)$org['idsede_central'] && isset($porcOrg[$c])
                            && ($po = porcion_enlazada($bd, $o, $prodO))) {
                        // en esa sede el bote ya es porciones (heladeria): salen porciones, no del almacen
                        $fac = $porcOrg[$c];
                        $hayP = (float)log_filas($bd, "SELECT IFNULL(stock, 0) AS s FROM porcion WHERE idporcion = ? FOR UPDATE", array((int)$po['idporcion']))[0]['s'];
                        if ($cant * $fac > $hayP + 0.0001) {
                            throw new ErrorUsuario('De ' . $nom . ' solo hay ' . txt_cant(floor($hayP / $fac * 100) / 100) . ' (' . txt_cant($hayP) . ' porciones) en la sede de origen.');
                        }
                        log_exec($bd, "UPDATE porcion SET stock = stock - ? WHERE idporcion = ?", array(round($cant * $fac, 2), (int)$po['idporcion']));
                        log_exec($bd, "INSERT INTO porcion_historial (tipo_movimiento, idtipo_movimiento_stock, fecha_date, fecha, hora, cantidad, idusuario, idsede, idporcion, stock_total)
                                SELECT ?, 2, CURDATE(), CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, stock FROM porcion WHERE idporcion = ?",
                            array('TRASLADO #' . $idt, txt_cant(round($cant * $fac, 2)), (int)$us, $o, (int)$po['idporcion'], (int)$po['idporcion']));
                        $lotes = array();
                    } else {
                    // stock del origen, bloqueado: no se manda lo que no hay
                    $st = log_filas($bd, "SELECT idproducto_stock, stock FROM producto_stock WHERE idproducto = ? AND idalmacen = ? ORDER BY idproducto_stock LIMIT 1 FOR UPDATE",
                        array($prodO, $almO));
                    $hay = $st ? (float)$st[0]['stock'] : 0;
                    if ($cant > $hay + 0.0001) { throw new ErrorUsuario('De ' . $nom . ' solo hay ' . $hay . ' en el almacén de origen.'); }
                    log_exec($bd, "INSERT INTO almacen_ie_detalle (idalmacen_ie, idp, procede, cantidad) VALUES (?, ?, 0, ?)", array($idie, $prodO, txt_cant(-$cant)));
                    log_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                            VALUES (?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ?)",
                        array('TRASLADO SALIDA #' . $idt, txt_cant($cant), (int)$us, $o, $prodO, $almO, txt_cant($hay - $cant)));
                    // lotes: primero lo que vence antes
                    $lotes = array(); $falta = $cant;
                    foreach (lotes_fefo($bd, $almO, $c) as $l) {
                        if ($falta <= 0) { break; }
                        $q = min($falta, (float)$l['saldo']);
                        log_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'traslado_sale', ?)",
                            array((int)$l['idlote'], $almO, -$q, $idt));
                        $lotes[] = array('idlote' => (int)$l['idlote'], 'lote' => $l['lote'], 'vence' => $l['vencimiento'], 'cantidad' => $q);
                        $falta -= $q;
                    }
                    }
                    // costo de este momento (ultimo costo de compra de la central): el valor del envio no cambia si despues sube el precio
                    $costo = (float)log_filas($bd, "SELECT precio_unitario + 0 AS c FROM producto WHERE idproducto = ?", array($c))[0]['c'];
                    $valor += $cant * $costo;
                    log_exec($bd, "INSERT INTO log_traslado_item (idtraslado, idproducto_central, idproducto_origen, idproducto_destino, cantidad_enviada, lotes, costo)
                            VALUES (?, ?, ?, ?, ?, ?, ?)", array($idt, $c, $prodO, $prodD, $cant, $lotes ? json_encode($lotes) : null, $costo));
                    // decisiones de la bandeja que viajan (por orden de llegada; si va menos, la linea se parte)
                    $resta = $cant;
                    foreach (log_filas($bd, "SELECT * FROM log_bandeja_linea WHERE idorg = ? AND decision IN ('central','traslado','produccion') AND estado = 'pendiente'
                            AND idtraslado IS NULL AND idsede = ? AND idproducto_central = ? AND IF(decision = 'central', ?, idsede_origen) = ? ORDER BY id",
                            array((int)$ido, $d, $c, (int)$org['idsede_central'], $o)) as $l) {
                        if ($resta <= 0) { break; }
                        $q = min($resta, (float)$l['cantidad']);
                        if ($q >= (float)$l['cantidad'] - 0.0001) {
                            log_exec($bd, "UPDATE log_bandeja_linea SET idtraslado = ?, estado = 'en_proceso' WHERE id = ?", array($idt, (int)$l['id']));
                        } else {
                            log_exec($bd, "UPDATE log_bandeja_linea SET cantidad = cantidad - ? WHERE id = ?", array($q, (int)$l['id']));
                            log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                                    idsede_origen, cantidad, estado, idusuario, idorden, idtraslado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_proceso', ?, ?, ?)",
                                array((int)$l['idorg'], (int)$l['idsolicitud_producto'], (int)$l['idsolicitud_detalle'], (int)$l['idsede'], $c, $l['decision'],
                                      $l['idsede_origen'], $q, $l['idusuario'], $l['idorden'], $idt));
                        }
                        log_exec($bd, "UPDATE solicitud_producto_detalle SET cantidad_despachada = IFNULL(cantidad_despachada, 0) + ? WHERE idsolicitud_producto_detalle = ?",
                            array($q, (int)$l['idsolicitud_detalle']));
                        log_exec($bd, "UPDATE solicitud_producto SET estado = 'despachado', fecha_despachado = NOW(), idusuario_despacha = ?
                                WHERE idsolicitud_producto = ? AND estado IN ('visto', 'atendido')", array((int)$us, (int)$l['idsolicitud_producto']));
                        $resta -= $q;
                    }
                    $pend[$k] -= $cant;
                    $n++;
                }
                if ($n === 0) { throw new ErrorUsuario('El envío a ' . $nomD . ' no tiene cantidades. Quítalo o pon cuánto va.'); }
                log_exec($bd, "UPDATE log_traslado SET valor_enviado = ? WHERE idtraslado = ?", array(round($valor, 2), $idt));
                $traslados[] = array('idtraslado' => $idt, 'destino' => $nomD, 'iddestino' => $d, 'idorigen' => $o, 'productos' => $n, 'valor' => round($valor, 2));
            }
            $res = array('idruta' => $idruta, 'traslados' => $traslados);
            log_token_cerrar($bd, 'despacho', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'no_enviar':
    // {origen, destino, idproducto_central}: lo que queda pendiente de ese producto para esa sede ya no se envia
    // (ej. pidieron 50, salieron 40 y se decidio no mandar los 10). Las lineas quedan como 'rechazo'.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $o = isset($b['origen']) ? (int)$b['origen'] : 0; $d = isset($b['destino']) ? (int)$b['destino'] : 0;
        $c = isset($b['idproducto_central']) ? (int)$b['idproducto_central'] : 0;
        $bd->bd->begin_transaction();
        try {
            $lin = log_filas($bd, "SELECT id, idsolicitud_producto FROM log_bandeja_linea WHERE idorg = ? AND idproducto_central = ? AND idsede = ?
                    AND decision IN ('central', 'traslado', 'produccion') AND estado = 'pendiente' AND idtraslado IS NULL
                    AND IF(decision = 'central', ?, idsede_origen) = ? FOR UPDATE", array((int)$ido, $c, $d, (int)$org['idsede_central'], $o));
            if (!$lin) { throw new ErrorUsuario('Ya no hay nada pendiente de ese producto. Actualiza la pantalla.'); }
            foreach ($lin as $l) {
                log_exec($bd, "UPDATE log_bandeja_linea SET decision = 'rechazo', estado = 'hecho' WHERE id = ?", array((int)$l['id']));
            }
            // si el pedido ya habia llegado completo en lo demas, queda recibido
            foreach (array_unique(array_column($lin, 'idsolicitud_producto')) as $sol) {
                log_exec($bd, "UPDATE solicitud_producto s SET s.estado = 'recibido', s.fecha_recibido = IFNULL(s.fecha_recibido, NOW())
                        WHERE s.idsolicitud_producto = ? AND s.estado = 'despachado' AND NOT EXISTS (SELECT 1 FROM log_bandeja_linea b
                        WHERE b.idsolicitud_producto = s.idsolicitud_producto AND b.decision <> 'rechazo' AND b.estado <> 'hecho')
                        AND NOT EXISTS (SELECT 1 FROM log_bandeja_linea b JOIN log_traslado t ON t.idtraslado = b.idtraslado
                        WHERE b.idsolicitud_producto = s.idsolicitud_producto AND t.estado = 'despachado')", array((int)$sol));
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('ok' => true);
    });

case 'mover_almacen':
    // {idsede, idproducto_central, desde (idalmacen), cantidad, token}: pasa mercaderia de otro almacen de la MISMA sede
    // (ej. PRODUCCION) a su almacen principal, para completar un envio. Camino legacy: almacen_ie salida (tipo '2',
    // cantidad negativa) + entrada (tipo '1'), producto_historial y los lotes se mueven con la mercaderia (FEFO).
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $s = isset($b['idsede']) ? (int)$b['idsede'] : 0;
        $c = isset($b['idproducto_central']) ? (int)$b['idproducto_central'] : 0;
        $desde = isset($b['desde']) ? (int)$b['desde'] : 0;
        $cant = isset($b['cantidad']) && is_numeric($b['cantidad']) ? round((float)$b['cantidad'], 3) : 0;
        if ($cant <= 0 || $cant > 999999) { throw new ErrorUsuario('Escribe cuánto vas a pasar.'); }
        $hacia = almacen_de($bd, $ido, $s);   // valida que la sede sea de la org y tenga almacen principal
        $alm = log_filas($bd, "SELECT a.descripcion FROM almacen a WHERE a.idalmacen = ? AND a.idsede = ? AND a.estado = 0", array($desde, $s));
        if (!$alm || $desde === $hacia) { throw new ErrorUsuario('Ese almacén no es de esta sede.'); }
        $prod = producto_en_sede($bd, $org, $c, $s);
        $nom = $prod ? log_filas($bd, "SELECT descripcion FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ? AND estado = 0",
            array($prod, (int)$ido, $s)) : array();
        if (!$nom) { throw new ErrorUsuario('Ese producto no está en el catálogo de la sede.'); }
        $nom = $nom[0]['descripcion'];
        $nomHacia = log_filas($bd, "SELECT descripcion FROM almacen WHERE idalmacen = ?", array($hacia))[0]['descripcion'];
        $token = log_token($b);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'mover_alm', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            $st = log_filas($bd, "SELECT stock FROM producto_stock WHERE idproducto = ? AND idalmacen = ? ORDER BY idproducto_stock LIMIT 1 FOR UPDATE",
                array($prod, $desde));
            $hay = $st ? (float)$st[0]['stock'] : 0;
            if ($cant > $hay + 0.0001) { throw new ErrorUsuario('En ' . $alm[0]['descripcion'] . ' solo hay ' . txt_cant($hay) . ' de ' . $nom . '.'); }
            $motS = motivo_requerido($bd, 'CAMBIO DE ALMACEN', 2);
            $motE = motivo_requerido($bd, 'CAMBIO DE ALMACEN', 1);
            $mov = function ($idalm, $tipo, $mot, $q, $txt, $hist) use ($bd, $ido, $s, $us, $prod) {
                log_exec($bd, "INSERT INTO almacen_ie (idorg, idsede, idusuario, idalmacen, tipo, fecha, idproducto_motivo_es, motivo, fecha_hora, hora)
                        VALUES (?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%d/%m/%Y'), ?, ?, NOW(), DATE_FORMAT(NOW(), '%H:%i:%s'))",
                    array((int)$ido, $s, (int)$us, $idalm, $tipo, $mot, mb_substr($txt, 0, 100, 'UTF-8')));
                $idie = (int)$bd->lastInsertId();
                // el trigger de almacen_ie_detalle solo hace UPDATE: la fila de stock debe existir
                log_exec($bd, "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado) SELECT ?, ?, 0, 0 FROM DUAL
                        WHERE NOT EXISTS (SELECT 1 FROM producto_stock WHERE idproducto = ? AND idalmacen = ?)", array($prod, $idalm, $prod, $idalm));
                log_exec($bd, "INSERT INTO almacen_ie_detalle (idalmacen_ie, idp, procede, cantidad) VALUES (?, ?, 0, ?)", array($idie, $prod, txt_cant($q)));
                log_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                        SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock FROM producto_stock ps
                        WHERE ps.idproducto = ? AND ps.idalmacen = ? ORDER BY ps.idproducto_stock LIMIT 1",
                    array($hist, txt_cant(abs($q)), (int)$us, $s, $prod, $idalm, $prod, $idalm));
                return $idie;
            };
            $sal = $mov($desde, '2', $motS, -$cant, 'A ' . $nomHacia, 'CAMBIO ALM SALIDA');   // producto_historial.tipo_movimiento: 20 caracteres
            $mov($hacia, '1', $motE, $cant, 'DESDE ' . $alm[0]['descripcion'], 'CAMBIO ALM ENTRADA');
            // lotes: lo que vence antes se mueve primero
            $falta = $cant;
            foreach (lotes_fefo($bd, $desde, $c) as $l) {
                if ($falta <= 0) { break; }
                $q = min($falta, (float)$l['saldo']);
                log_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'traslado_sale', ?), (?, ?, ?, 'traslado_entra', ?)",
                    array((int)$l['idlote'], $desde, -$q, $sal, (int)$l['idlote'], $hacia, $q, $sal));
                $falta -= $q;
            }
            $res = array('movido' => $cant, 'producto' => $nom, 'desde' => $alm[0]['descripcion'], 'hacia' => $nomHacia);
            log_token_cerrar($bd, 'mover_alm', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'avisar':
    // {idruta}: WhatsApp automatico a cada encargado de la ruta. El que ya fue avisado no se repite.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        require_once __DIR__ . '/_whatsapp.php';
        $b = log_body();
        $idruta = isset($b['idruta']) ? (int)$b['idruta'] : 0;
        // el link lo pone el servidor (config), no el navegador: el mensaje sale del WhatsApp de la empresa
        $link = defined('LOGISTICA_LINK_RECIBIR') ? (string)LOGISTICA_LINK_RECIBIR : '';
        set_time_limit(300);
        $ruta = log_filas($bd, "SELECT IFNULL(v.descripcion, '') AS vehiculo, IFNULL(c.nombre, '') AS chofer, c.idchofer, IFNULL(c.telefono, '') AS chofer_tel,
                c.acceso_version, c.activo AS chofer_activo, r.estado FROM log_ruta r
                LEFT JOIN log_vehiculo v ON v.idvehiculo = r.idvehiculo LEFT JOIN log_chofer c ON c.idchofer = r.idchofer
                WHERE r.idruta = ? AND r.idorg = ?", array($idruta, (int)$ido));
        if (!$ruta) { throw new ErrorUsuario('Ruta no encontrada.'); }
        $res = array();
        foreach (log_filas($bd, "SELECT t.idtraslado, t.avisado, sd.nombre AS destino, ls.telefono_whatsapp AS telefono FROM log_traslado t
                JOIN sede sd ON sd.idsede = t.idsede_destino LEFT JOIN log_sede ls ON ls.idsede = t.idsede_destino
                WHERE t.idruta = ? AND t.estado = 'despachado' ORDER BY t.orden_entrega", array($idruta)) as $t) {
            $r = array('idtraslado' => (int)$t['idtraslado'], 'destino' => $t['destino'], 'enviado' => false, 'error' => null);
            if ($t['avisado'] !== null) { $r['enviado'] = true; $res[] = $r; continue; }
            if (!$t['telefono']) { $r['error'] = 'La sede no tiene WhatsApp del encargado (ponlo en Configurar).'; $res[] = $r; continue; }
            // se toma el aviso antes de enviar: dos clics no mandan dos mensajes
            if (log_exec($bd, "UPDATE log_traslado SET avisado = NOW() WHERE idtraslado = ? AND avisado IS NULL", array($r['idtraslado'])) !== 1) {
                $r['enviado'] = true; $res[] = $r; continue;
            }
            $items = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, 'und') AS unidad, i.cantidad_enviada FROM log_traslado_item i
                    JOIN producto p ON p.idproducto = i.idproducto_central LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                    WHERE i.idtraslado = ? ORDER BY p.descripcion LIMIT 40", array($r['idtraslado']));
            $l = array('Hola, *va en camino* mercadería para ' . $t['destino'] . ' (envío N° ' . $r['idtraslado'] . '):', '');
            foreach ($items as $i) { $l[] = '- ' . txt_cant($i['cantidad_enviada']) . ' ' . $i['unidad'] . ' de ' . $i['descripcion']; }
            $l[] = '';
            if ($ruta[0]['chofer'] !== '') { $l[] = 'Chofer: ' . $ruta[0]['chofer'] . ($ruta[0]['vehiculo'] !== '' ? ' · ' . $ruta[0]['vehiculo'] : ''); }
            $l[] = 'Cuando llegue, cuéntalo y confírmalo en Logística > Recibir mercadería' . ($link !== '' ? ': ' . $link : '.');
            $r['error'] = log_whatsapp($ido, $org['idsede_central'], $t['telefono'], implode("\n", $l));
            if ($r['error'] === null) { $r['enviado'] = true; }
            else { log_exec($bd, "UPDATE log_traslado SET avisado = NULL WHERE idtraslado = ?", array($r['idtraslado'])); }
            $res[] = $r;
        }
        // al chofer: su ruta y su enlace para marcar las entregas
        $ch = null;
        $rc = $ruta[0];
        if ($rc['idchofer'] && (int)$rc['chofer_activo'] === 1 && $rc['estado'] === 'en_ruta') {
            require_once __DIR__ . '/_chofer.php';
            $ch = array('nombre' => $rc['chofer'], 'enviado' => false, 'error' => null, 'enlace' => null);
            try { $ch['enlace'] = chofer_enlace(chofer_token($ido, $rc['idchofer'], $rc['acceso_version'])); }
            catch (ErrorUsuario $e) { $ch['error'] = $e->getMessage(); }
            if ($ch['enlace'] && $rc['chofer_tel'] === '') { $ch['error'] = 'El chofer no tiene celular (ponlo en Configurar > Choferes).'; }
            elseif ($ch['enlace']) {
                $paradas = log_filas($bd, "SELECT sd.nombre FROM log_traslado t JOIN sede sd ON sd.idsede = t.idsede_destino
                        WHERE t.idruta = ? AND t.estado = 'despachado' ORDER BY t.orden_entrega", array($idruta));
                $txt = 'Hola ' . $rc['chofer'] . ', tienes la ruta N° ' . $idruta . ' con ' . count($paradas) . ' entrega(s): '
                    . implode(', ', array_map(function ($p, $i) { return ($i + 1) . ') ' . $p['nombre']; }, $paradas, array_keys($paradas)))
                    . ".\nÁbrela aquí y marca cada entrega al dejarla: " . $ch['enlace'];
                $ch['error'] = log_whatsapp($ido, $org['idsede_central'], $rc['chofer_tel'], $txt);
                $ch['enviado'] = $ch['error'] === null;
                if ($ch['enviado']) { $ch['enlace'] = null; }   // la clave no vuelve al navegador si ya salio por WhatsApp
            }
        }
        return array('sedes' => $res, 'chofer' => $ch);
    });

case 'listar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        return log_filas($bd, "SELECT t.idtraslado, t.idruta, t.estado, t.fecha_despacho, t.fecha_recibe, so.nombre AS origen, sd.nombre AS destino,
                DATE_FORMAT(t.fecha_entrega, '%d/%m/%Y %H:%i') AS entregado, t.entrega_recibio, t.entrega_lat, t.entrega_lng,
                ch.nombre AS chofer, ch.telefono AS chofer_tel, ve.descripcion AS vehiculo,
                IFNULL(NULLIF(TRIM(ur.nombres), ''), ur.usuario) AS recibio, DATE_FORMAT(t.fecha_recibe, '%d/%m/%Y %H:%i') AS recibido,
                TIMESTAMPDIFF(MINUTE, t.fecha_despacho, IFNULL(t.fecha_recibe, NOW())) AS minutos,
                (SELECT COUNT(*) FROM log_traslado_item i WHERE i.idtraslado = t.idtraslado) AS productos,
                (SELECT COUNT(*) FROM log_traslado_item i WHERE i.idtraslado = t.idtraslado AND i.cantidad_recibida < i.cantidad_enviada) AS con_diferencia,
                IFNULL(t.valor_enviado, (SELECT ROUND(SUM(i.cantidad_enviada * " . SQL_COSTO_ITEM . "), 2) FROM log_traslado_item i WHERE i.idtraslado = t.idtraslado)) AS valor,
                (SELECT ROUND(SUM(GREATEST(i.cantidad_enviada - i.cantidad_recibida, 0) * " . SQL_COSTO_ITEM . "), 2) FROM log_traslado_item i
                    WHERE i.idtraslado = t.idtraslado AND i.cantidad_recibida IS NOT NULL) AS valor_faltante,
                CONCAT_WS(' · ', t.nota_recibe, (SELECT GROUP_CONCAT(CONCAT(p.descripcion, ': ', i.observacion) SEPARATOR ' · ') FROM log_traslado_item i
                    JOIN producto p ON p.idproducto = i.idproducto_central WHERE i.idtraslado = t.idtraslado AND i.observacion <> '')) AS observaciones
                FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen JOIN sede sd ON sd.idsede = t.idsede_destino
                LEFT JOIN log_ruta ru ON ru.idruta = t.idruta
                LEFT JOIN log_chofer ch ON ch.idchofer = ru.idchofer
                LEFT JOIN log_vehiculo ve ON ve.idvehiculo = ru.idvehiculo
                LEFT JOIN usuario ur ON ur.idusuario = t.idusuario_recibe
                WHERE t.idorg = ? ORDER BY t.idtraslado DESC LIMIT 50", array((int)$ido));
    });

case 'hoja':
    // hoja de ruta (para imprimir) o detalle de un traslado
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $idruta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ruta = log_filas($bd, "SELECT r.idruta, r.fecha_salida, r.estado, IFNULL(v.descripcion, '') AS vehiculo, IFNULL(c.nombre, '') AS chofer
                FROM log_ruta r LEFT JOIN log_vehiculo v ON v.idvehiculo = r.idvehiculo LEFT JOIN log_chofer c ON c.idchofer = r.idchofer
                WHERE r.idruta = ? AND r.idorg = ?", array($idruta, (int)$ido));
        if (!$ruta) { throw new ErrorUsuario('Ruta no encontrada.'); }
        $tras = log_filas($bd, "SELECT t.idtraslado, t.orden_entrega, t.estado, t.guia_tipo, t.guia_numero, t.nota_recibe, so.nombre AS origen,
                DATE_FORMAT(t.fecha_entrega, '%d/%m/%Y %H:%i') AS entregado, t.entrega_recibio, t.entrega_obs,
                IFNULL(NULLIF(TRIM(ur.nombres), ''), ur.usuario) AS recibio, DATE_FORMAT(t.fecha_recibe, '%d/%m/%Y %H:%i') AS recibido,
                sd.nombre AS destino, sd.direccion FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen
                JOIN sede sd ON sd.idsede = t.idsede_destino LEFT JOIN usuario ur ON ur.idusuario = t.idusuario_recibe
                WHERE t.idruta = ? ORDER BY t.orden_entrega", array($idruta));
        foreach ($tras as &$t) {
            $t['items'] = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad, i.cantidad_enviada + 0 AS enviada,
                    i.cantidad_recibida, i.motivo_diferencia, i.observacion, i.lotes, ROUND(" . SQL_COSTO_ITEM . ", 4) AS costo,
                    ROUND(i.cantidad_enviada * " . SQL_COSTO_ITEM . ", 2) AS valor FROM log_traslado_item i JOIN producto p ON p.idproducto = i.idproducto_central
                    LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE i.idtraslado = ? ORDER BY p.descripcion",
                array((int)$t['idtraslado']));
        }
        return array('ruta' => $ruta[0], 'traslados' => $tras);
    });

case 'por_recibir':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_sede($bd, $ido, $idsede, $us);
        $tras = log_filas($bd, "SELECT t.idtraslado, t.fecha_despacho, t.guia_tipo, t.guia_numero, so.nombre AS origen,
                IFNULL(c.nombre, '') AS chofer, IFNULL(v.descripcion, '') AS vehiculo
                FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen
                LEFT JOIN log_ruta r ON r.idruta = t.idruta LEFT JOIN log_chofer c ON c.idchofer = r.idchofer
                LEFT JOIN log_vehiculo v ON v.idvehiculo = r.idvehiculo
                WHERE t.idsede_destino = ? AND t.idorg = ? AND t.estado = 'despachado' ORDER BY t.idtraslado", array((int)$idsede, (int)$ido));
        foreach ($tras as &$t) {
            $t['items'] = log_filas($bd, "SELECT i.iditem, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, i.cantidad_enviada + 0 AS enviada, i.lotes,
                    i.idproducto_destino, (SELECT po.porciones + 0 FROM log_producto_porcion po WHERE po.idproducto_central = i.idproducto_central) AS porciones
                    FROM log_traslado_item i JOIN producto p ON p.idproducto = i.idproducto_destino
                    LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE i.idtraslado = ? ORDER BY p.descripcion",
                array((int)$t['idtraslado']));
            foreach ($t['items'] as &$i) {
                $i['porciones'] = $i['porciones'] !== null ? (float)$i['porciones'] : null;
                $po = $i['porciones'] ? porcion_enlazada($bd, $idsede, $i['idproducto_destino']) : null;
                $i['idporcion'] = $po ? (int)$po['idporcion'] : null;
                $i['porcion'] = $po ? $po['descripcion'] : null;
                if ($i['porciones'] && !$po && !isset($sedePorciones)) {
                    $sedePorciones = log_filas($bd, "SELECT idporcion, descripcion FROM porcion WHERE idsede = ? AND estado = 0 ORDER BY descripcion",
                        array((int)$idsede));
                }
            }
            unset($i);
            // primera vez que llega un producto por porcion: lista para elegir a que porcion va
            $t['porciones_sede'] = isset($sedePorciones) ? $sedePorciones : array();
        }
        unset($t);
        return $tras;
    });

case 'recibir':
    // items: [{iditem, recibido, motivo}]; si llego menos, el motivo es obligatorio
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_sede($bd, $ido, $idsede, $us);
        $b = log_body();
        $token = log_token($b);
        $entrada = array();
        foreach ((isset($b['items']) && is_array($b['items']) ? $b['items'] : array()) as $it) {
            if (is_array($it) && isset($it['iditem'])) { $entrada[(int)$it['iditem']] = $it; }
        }
        $motEnt = motivo_requerido($bd, 'RECEPCION DE TRASLADO', 1);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'recibir', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            $t = log_filas($bd, "SELECT * FROM log_traslado WHERE idtraslado = ? AND idsede_destino = ? AND idorg = ? FOR UPDATE",
                array(isset($b['idtraslado']) ? (int)$b['idtraslado'] : 0, (int)$idsede, (int)$ido));
            if (!$t) { throw new ErrorUsuario('Ese envío no es para tu sede.'); }
            $t = $t[0];
            if ($t['estado'] !== 'despachado') { throw new ErrorUsuario('Este envío ya se recibió.'); }
            $alm = (int)$t['idalmacen_destino'];
            $items = log_filas($bd, "SELECT i.*, p.descripcion FROM log_traslado_item i JOIN producto p ON p.idproducto = i.idproducto_destino
                    WHERE i.idtraslado = ?", array((int)$t['idtraslado']));
            $idie = 0; $dif = false;
            foreach ($items as $i) {
                if (!isset($entrada[(int)$i['iditem']])) { throw new ErrorUsuario('Falta anotar cuánto llegó de ' . $i['descripcion'] . '.'); }
                $e = $entrada[(int)$i['iditem']];
                // "tres", "abc" o negativo se rechaza (antes (float) lo tomaba como 0 = no llego nada)
                $txt = isset($e['recibido']) && is_scalar($e['recibido']) ? str_replace(',', '.', trim((string)$e['recibido'])) : '';
                if ($txt === '') { throw new ErrorUsuario('Falta anotar cuánto llegó de ' . $i['descripcion'] . '.'); }
                if (!is_numeric($txt)) { throw new ErrorUsuario('De ' . $i['descripcion'] . ' escribe cuánto llegó solo con números (ej. 3 o 2.5).'); }
                $rec = round((float)$txt, 3);
                $env = (float)$i['cantidad_enviada'];
                if ($rec < 0) { throw new ErrorUsuario('De ' . $i['descripcion'] . ' lo que llegó no puede ser negativo.'); }
                if ($rec > $env + 0.0001) { throw new ErrorUsuario('De ' . $i['descripcion'] . ' se enviaron ' . $env . ': no pueden llegar ' . $rec . '. Revisa.'); }
                $motivo = mb_substr(trim(isset($e['motivo']) ? (string)$e['motivo'] : ''), 0, 120, 'UTF-8');
                if ($rec < $env - 0.0001) {
                    if ($motivo === '') { throw new ErrorUsuario('De ' . $i['descripcion'] . ' llegó menos: elige el motivo.'); }
                    $dif = true;
                }
                $factor = porciones_de($bd, $i['idproducto_central']);   // la central no recibe aqui (log_requiere_sede)
                if ($rec > 0 && $factor > 0) {
                    // se vende por porcion: suma porciones, el bote no entra al almacen (ya se convirtio)
                    recibir_en_porciones($bd, $ido, $idsede, $us, $i, $rec, $factor, isset($e['idporcion']) ? (int)$e['idporcion'] : 0, $t['idtraslado']);
                } elseif ($rec > 0) {
                    if (!$idie) {
                        log_exec($bd, "INSERT INTO almacen_ie (idorg, idsede, idusuario, idalmacen, tipo, fecha, idproducto_motivo_es, motivo, fecha_hora, hora)
                                VALUES (?, ?, ?, ?, '1', DATE_FORMAT(NOW(), '%d/%m/%Y'), ?, ?, NOW(), DATE_FORMAT(NOW(), '%H:%i:%s'))",
                            array((int)$ido, (int)$idsede, (int)$us, $alm, $motEnt, 'TRASLADO #' . $t['idtraslado']));
                        $idie = (int)$bd->lastInsertId();
                    }
                    // el trigger de almacen_ie_detalle solo hace UPDATE: la fila de stock debe existir
                    log_exec($bd, "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado) SELECT ?, ?, 0, 0 FROM DUAL
                            WHERE NOT EXISTS (SELECT 1 FROM producto_stock WHERE idproducto = ? AND idalmacen = ?)",
                        array((int)$i['idproducto_destino'], $alm, (int)$i['idproducto_destino'], $alm));
                    log_exec($bd, "INSERT INTO almacen_ie_detalle (idalmacen_ie, idp, procede, cantidad) VALUES (?, ?, 0, ?)",
                        array($idie, (int)$i['idproducto_destino'], txt_cant($rec)));
                    log_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                            SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock FROM producto_stock ps
                            WHERE ps.idproducto = ? AND ps.idalmacen = ? ORDER BY ps.idproducto_stock LIMIT 1",
                        array('TRASLADO ENTRADA #' . $t['idtraslado'], txt_cant($rec), (int)$us, (int)$idsede, (int)$i['idproducto_destino'], $alm,
                              (int)$i['idproducto_destino'], $alm));
                    // lotes: entran en la misma proporcion que llego
                    foreach (json_decode((string)$i['lotes'], true) ?: array() as $l) {
                        $q = round((float)$l['cantidad'] * $rec / $env, 3);
                        if ($q > 0) {
                            log_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'traslado_entra', ?)",
                                array((int)$l['idlote'], $alm, $q, (int)$t['idtraslado']));
                        }
                    }
                }
                $obs = mb_substr(trim(isset($e['observacion']) && is_string($e['observacion']) ? $e['observacion'] : ''), 0, 150, 'UTF-8');
                log_exec($bd, "UPDATE log_traslado_item SET cantidad_recibida = ?, motivo_diferencia = ?, observacion = ? WHERE iditem = ?",
                    array($rec, $rec < $env - 0.0001 ? $motivo : null, $obs !== '' ? $obs : null, (int)$i['iditem']));
            }
            log_exec($bd, "UPDATE log_traslado SET estado = ?, idusuario_recibe = ?, fecha_recibe = NOW(), idalmacen_ie_entrada = ? WHERE idtraslado = ?",
                array($dif ? 'recibido_con_diferencia' : 'recibido', (int)$us, $idie ?: null, (int)$t['idtraslado']));
            $notaRec = mb_substr(trim(isset($b['nota']) && is_string($b['nota']) ? $b['nota'] : ''), 0, 255, 'UTF-8');
            if ($notaRec !== '') { log_exec($bd, "UPDATE log_traslado SET nota_recibe = ? WHERE idtraslado = ?", array($notaRec, (int)$t['idtraslado'])); }
            log_exec($bd, "UPDATE log_traslado SET valor_recibido = (SELECT ROUND(SUM(IFNULL(i.cantidad_recibida, 0) * " . SQL_COSTO_ITEM . "), 2)
                    FROM log_traslado_item i WHERE i.idtraslado = ?) WHERE idtraslado = ?", array((int)$t['idtraslado'], (int)$t['idtraslado']));
            log_exec($bd, "UPDATE log_bandeja_linea SET estado = 'hecho' WHERE idtraslado = ?", array((int)$t['idtraslado']));
            // pedido: recibido cuando ya no le queda nada en camino ni por enviar
            foreach (log_filas($bd, "SELECT DISTINCT idsolicitud_producto AS id FROM log_bandeja_linea WHERE idtraslado = ?", array((int)$t['idtraslado'])) as $s) {
                log_exec($bd, "UPDATE solicitud_producto s SET s.estado = 'recibido', s.fecha_recibido = NOW(), s.idusuario_recibe = ?
                        WHERE s.idsolicitud_producto = ? AND s.estado = 'despachado' AND NOT EXISTS (SELECT 1 FROM log_bandeja_linea b
                        WHERE b.idsolicitud_producto = s.idsolicitud_producto AND b.decision <> 'rechazo' AND b.estado <> 'hecho')",
                    array((int)$us, (int)$s['id']));
            }
            // ruta cerrada cuando todas sus sedes recibieron
            if ($t['idruta']) {
                log_exec($bd, "UPDATE log_ruta r SET r.estado = 'cerrada', r.fecha_cierre = NOW() WHERE r.idruta = ? AND NOT EXISTS
                        (SELECT 1 FROM log_traslado t WHERE t.idruta = r.idruta AND t.estado = 'despachado')", array((int)$t['idruta']));
            }
            $res = array('idtraslado' => (int)$t['idtraslado'], 'con_diferencia' => $dif);
            log_token_cerrar($bd, 'recibir', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

default:
    log_out(false, null, 'Operación no válida.');
}

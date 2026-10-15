<?php
/**
 * Logistica F5 — Recepcion en la central contra la orden de compra.
 * ?op=pendientes | orden&id= | guardar | cerrar_faltante | llegadas&pagina= | llegada&id=
 * El stock entra por el MISMO camino que Compras (x-compras2 / log_compras.php guardarCompra):
 *   compra + compra_items (trigger stock_insert_add_compra suma el stock) + producto_historial_precio +
 *   producto.precio_unitario + producto_historial 'COMPRA #id' + compra_pago. Todo en UNA transaccion con:
 *   recepcion/recepcion_item, orden_compra(_item), lotes (migracion 010), ultimo precio del proveedor, y la
 *   conversion de las decisiones 'compra' de la bandeja en 'central' (F6 las envia a la sede que pidio).
 * NO usa el recepcion.php viejo (movia stock por SP y ademas por trigger = stock doble).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_precios.php';
require_once __DIR__ . '/../costeo_recalculo.php';

const TIPO_PAGO_CREDITO = 3;   // igual que COMPRA_TIPO_PAGO_CREDITO de log_compras.php

/** Numero para columnas varchar del legacy ('10', '2.5'), como numTxt de log_compras. */
function txt_num($n, $dec) {
    $t = rtrim(rtrim(number_format((float)$n, $dec, '.', ''), '0'), '.');
    return $t === '' || $t === '-0' ? '0' : $t;
}

function oc_central($bd, $org, $id, $bloquear = false) {
    $r = log_filas($bd, "SELECT oc.*, IFNULL(pv.descripcion, '') AS proveedor FROM orden_compra oc
            LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
            WHERE oc.idorden = ? AND oc.idorg = ? AND oc.idsede = ?" . ($bloquear ? " FOR UPDATE" : ""),
        array((int)$id, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Orden no encontrada.'); }
    return $r[0];
}

function items_oc($bd, $idorden) {
    return log_filas($bd, "SELECT i.idorden_item, i.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
            i.cantidad_pedida + 0 AS pedida, i.cantidad_recibida + 0 AS recibida, i.punitario + 0 AS precio, i.factor + 0 AS factor,
            IFNULL(lp.nombre, '') AS presentacion
            FROM orden_compra_item i JOIN producto p ON p.idproducto = i.idproducto
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            LEFT JOIN log_presentacion lp ON lp.idpresentacion = i.idpresentacion
            WHERE i.idorden = ? ORDER BY p.descripcion", array((int)$idorden));
}

/**
 * Lo recibido de un producto pasa de "comprar" a "sale del central" para las sedes que lo pidieron (por orden de
 * llegada). Si alcanza para toda la linea, la de compra queda 'hecho'; si no, se parte. Sobrante (redondeo a caja) queda
 * como stock del central.
 */
function compras_a_central($bd, $idorden, $idproducto, $recibido, $idllego = null) {
    $idllego = $idllego ? (int)$idllego : (int)$idproducto;   // cambio: a la sede se le despacha lo que de verdad llego
    $lineas = log_filas($bd, "SELECT * FROM log_bandeja_linea WHERE idorden = ? AND idproducto_central = ? AND decision = 'compra'
            AND estado = 'en_proceso' ORDER BY id FOR UPDATE", array((int)$idorden, (int)$idproducto));
    foreach ($lineas as $l) {
        if ($recibido <= 0) { break; }
        $tomar = min($recibido, (float)$l['cantidad']);
        // comprado para la propia central (ej. insumos de produccion): ya esta en su almacen, no hay nada que enviar
        $central = log_filas($bd, "SELECT idsede_central FROM org_logistica WHERE idorg = ?", array((int)$l['idorg']));
        if (!$central || (int)$central[0]['idsede_central'] !== (int)$l['idsede']) {
            log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central,
                    decision, cantidad, idusuario, idorden) VALUES (?, ?, ?, ?, ?, 'central', ?, ?, ?)",
                array((int)$l['idorg'], (int)$l['idsolicitud_producto'], (int)$l['idsolicitud_detalle'], (int)$l['idsede'],
                      $idllego, $tomar, $l['idusuario'], (int)$idorden));
        }
        if ($tomar >= (float)$l['cantidad'] - 0.0001) {
            log_exec($bd, "UPDATE log_bandeja_linea SET estado = 'hecho' WHERE id = ?", array((int)$l['id']));
        } else {
            log_exec($bd, "UPDATE log_bandeja_linea SET cantidad = cantidad - ? WHERE id = ?", array($tomar, (int)$l['id']));
        }
        $recibido -= $tomar;
    }
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'pendientes':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        return log_filas($bd, "SELECT oc.idorden, oc.fecha, oc.estado, oc.total, IFNULL(pv.descripcion, '') AS proveedor,
                (SELECT COUNT(*) FROM orden_compra_item i WHERE i.idorden = oc.idorden AND i.cantidad_recibida < i.cantidad_pedida) AS faltan
                FROM orden_compra oc LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
                WHERE oc.idorg = ? AND oc.idsede = ? AND oc.estado IN ('aprobada', 'enviada', 'parcial')
                ORDER BY oc.idorden", array((int)$ido, (int)$org['idsede_central']));
    });

case 'orden':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $oc = oc_central($bd, $org, isset($_GET['id']) ? $_GET['id'] : 0);
        $items = items_oc($bd, (int)$oc['idorden']);
        // alerta de precio: lo que se pago la ultima vez a este proveedor y si otro lo vende mas barato
        $hist = historial_precios($bd, array_column($items, 'idproducto'));
        foreach ($items as &$i) {
            $i['precio_ref'] = isset($hist[(int)$i['idproducto']]) ? lectura_precio($hist[(int)$i['idproducto']], (int)$oc['idproveedor']) : null;
        }
        unset($i);
        return array('orden' => $oc, 'items' => $items, 'alerta_pct' => PRECIO_ALERTA_PCT,
            'tipos_pago' => log_filas($bd, "SELECT idtipo_pago, descripcion FROM tipo_pago WHERE estado = 0 AND visible = '0' ORDER BY idtipo_pago"),
            'credito' => TIPO_PAGO_CREDITO,
            // para "llegó otro producto en su lugar" (catalogo de la central, por indice idorg+idsede)
            'catalogo' => log_filas($bd, "SELECT idproducto AS valor, descripcion AS texto FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0
                    ORDER BY descripcion LIMIT 5000", array((int)$ido, (int)$org['idsede_central'])));
    });

case 'guardar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $token = log_token($b);
        $idtp = isset($b['idtipo_pago']) ? (int)$b['idtipo_pago'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM tipo_pago WHERE idtipo_pago = ? AND estado = 0", array($idtp))) { throw new ErrorUsuario('Elige cómo se paga.'); }
        $fpago = null;
        if ($idtp === TIPO_PAGO_CREDITO) {
            $fp = explode('-', isset($b['fecha_pago']) ? (string)$b['fecha_pago'] : '');
            $fpago = count($fp) === 3 && checkdate((int)$fp[1], (int)$fp[2], (int)$fp[0]) ? sprintf('%04d-%02d-%02d', $fp[0], $fp[1], $fp[2]) : null;
            if (!$fpago) { throw new ErrorUsuario('Compra a crédito: indica una fecha de pago válida.'); }
        }
        $comprobante = log_mayus(isset($b['comprobante']) ? $b['comprobante'] : '', 40);
        $nota = mb_substr(trim(isset($b['nota']) ? (string)$b['nota'] : ''), 0, 150, 'UTF-8');
        $entrada = array();
        foreach ((isset($b['items']) && is_array($b['items']) ? $b['items'] : array()) as $it) {
            $id = is_array($it) && isset($it['idorden_item']) ? (int)$it['idorden_item'] : 0;
            if ($id > 0) { $entrada[$id] = $it; }
        }

        $res = (function () use ($bd, $org, $b, $entrada, $idtp, $fpago, $comprobante, $nota, $us, $token) {
            $bd->bd->begin_transaction();
            try {
                // doble clic: el segundo envio espera al primero y recibe su mismo resultado (no ingresa dos veces)
                if ($previo = log_token_reservar($bd, 'recepcion', $token, $org['idorg'])) { $bd->bd->rollback(); return $previo; }
                $oc = oc_central($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
                if (!in_array($oc['estado'], array('aprobada', 'enviada', 'parcial'), true)) { throw new ErrorUsuario('Esta orden no está esperando mercadería.'); }
                $alm = (int)$oc['idalmacen_destino'];
                if ($alm <= 0) { throw new ErrorUsuario('La central no tiene almacén donde recibir. Elígelo en Configurar.'); }
                $idsedeC = (int)$org['idsede_central'];
                $lineas = array(); $total = 0;
                $notas = array();   // observaciones de productos que NO llegaron (se guardan igual)
                foreach (items_oc($bd, (int)$oc['idorden']) as $i) {
                    if (!isset($entrada[(int)$i['idorden_item']])) { continue; }
                    $e = $entrada[(int)$i['idorden_item']];
                    $obs = mb_substr(trim(isset($e['nota']) && is_string($e['nota']) ? $e['nota'] : ''), 0, 150, 'UTF-8');
                    $cant = isset($e['recibido']) ? round((float)$e['recibido'], 4) : 0;
                    if ($cant <= 0) { if ($obs !== '') { $notas[] = array('item' => $i, 'nota' => $obs); } continue; }
                    // el proveedor mando OTRO producto del catalogo en lugar del pedido
                    $idLlego = (int)$i['idproducto']; $cambio = null;
                    $idc = isset($e['cambio']) ? (int)$e['cambio'] : 0;
                    if ($idc > 0 && $idc !== $idLlego) {
                        $pc = log_filas($bd, "SELECT idproducto, descripcion FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ? AND estado = 0",
                            array($idc, (int)$org['idorg'], (int)$org['idsede_central']));
                        if (!$pc) { throw new ErrorUsuario('Lo que llegó en lugar de ' . $i['descripcion'] . ' no está en el catálogo. Elígelo de la lista.'); }
                        $idLlego = $idc; $cambio = $pc[0]['descripcion'];
                        if ($obs === '') { $obs = 'Llegó ' . $cambio . ' en lugar de ' . $i['descripcion']; }
                    }
                    $pu = isset($e['precio']) ? round((float)$e['precio'], 4) : (float)$i['precio'];
                    if ($cant > 999999 || $pu < 0 || $pu > 999999) { throw new ErrorUsuario('Cantidad o precio no válido en ' . $i['descripcion'] . '.'); }
                    // error de tipeo tipico (120 en vez de 12): mucho mas de lo que faltaba llegar
                    $pendiente = max(0, (float)$i['pedida'] - (float)$i['recibida']);
                    if ($cant > $pendiente * 5 + 5) {
                        throw new ErrorUsuario('En ' . $i['descripcion'] . ' pusiste ' . $cant . ' y solo faltaban ' . $pendiente . '. Revisa la cantidad.');
                    }
                    $venc = null;
                    $vtxt = isset($e['vencimiento']) ? trim((string)$e['vencimiento']) : '';
                    if ($vtxt !== '') {
                        // formato estricto AAAA-MM-DD: "2026-9-1" pasaba checkdate y luego fallaba la comparacion de texto
                        $vd = DateTime::createFromFormat('!Y-m-d', $vtxt);
                        if (!$vd || $vd->format('Y-m-d') !== $vtxt) {
                            throw new ErrorUsuario('La fecha de vencimiento de ' . $i['descripcion'] . ' no es válida.');
                        }
                        // hoy segun MySQL (no depende de la zona horaria de PHP)
                        $hoyBd = log_filas($bd, "SELECT CURDATE() AS d")[0]['d'];
                        if ($vd < DateTime::createFromFormat('!Y-m-d', $hoyBd)) { throw new ErrorUsuario('El vencimiento de ' . $i['descripcion'] . ' ya pasó. Revisa la fecha (o no recibas producto vencido).'); }
                        $venc = $vtxt;
                    }
                    $lote = mb_substr(trim(isset($e['lote']) ? (string)$e['lote'] : ''), 0, 40, 'UTF-8');
                    $ptotal = round($cant * $pu, 2);
                    $lineas[] = array('item' => $i, 'cant' => $cant, 'pu' => $pu, 'ptotal' => $ptotal, 'lote' => $lote, 'venc' => $venc,
                        'idp' => $idLlego, 'cambio' => $cambio !== null, 'nota' => $obs);
                    $total += $ptotal;
                }
                if (!$lineas) { throw new ErrorUsuario('Pon cuánto llegó de al menos un producto.'); }
                $totalTxt = number_format($total, 2, '.', '');
                $fCompra = log_filas($bd, "SELECT DATE_FORMAT(CURDATE(), '%d/%m/%Y') AS f")[0]['f'];

                // 1) compra: mismo INSERT que log_compras.php guardarCompra
                log_exec($bd, "INSERT INTO compra (idorg, idsede, idtipo_pago, idalmacen, f_compra, f_registro, a_pagar, total, f_pago,
                        idproveedor, nota_de_compra, comprobante, pagado, estado, idusuario, fecha_hora)
                        VALUES (?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())",
                    array((int)$org['idorg'], $idsedeC, $idtp, $alm, $fCompra, $totalTxt, $totalTxt,
                          $fpago ? date('d/m/Y', strtotime($fpago)) : null, (int)$oc['idproveedor'],
                          $nota !== '' ? $nota : 'Orden de compra N° ' . $oc['idorden'], $comprobante !== '' ? $comprobante : null,
                          $idtp === TIPO_PAGO_CREDITO ? 0 : 1, (int)$us));
                $idcompra = (int)$bd->lastInsertId();
                log_exec($bd, "INSERT INTO recepcion (idorden, idorg, idsede, idalmacen, fecha, idusuario, comprobante, idcompra, nota, fecha_hora)
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW())",
                    array((int)$oc['idorden'], (int)$org['idorg'], $idsedeC, $alm, (int)$us, $comprobante, $idcompra, $nota));
                $idrec = (int)$bd->lastInsertId();

                foreach ($lineas as $l) {
                    $i = $l['item'];
                    $idp = (int)$l['idp'];   // = el pedido, o el que llego en su lugar
                    $cantTxt = txt_num($l['cant'], 4); $puTxt = txt_num($l['pu'], 4);
                    // el trigger stock_insert_add_compra suma el stock en el almacen de la compra
                    log_exec($bd, "INSERT INTO compra_items (idcompra, idproducto, cantidad, punitario, ptotal) VALUES (?, ?, ?, ?, ?)",
                        array($idcompra, $idp, $cantTxt, $puTxt, number_format($l['ptotal'], 2, '.', '')));
                    log_exec($bd, "INSERT INTO producto_historial_precio (idproducto, fecha, idorg, idsede, precio, idproveedor, idcompra)
                            VALUES (?, ?, ?, ?, ?, ?, ?)", array($idp, $fCompra, (int)$org['idorg'], $idsedeC, $puTxt, (int)$oc['idproveedor'], $idcompra));
                    log_exec($bd, "UPDATE producto SET precio_unitario = ? WHERE idproducto = ?", array($puTxt, $idp));
                    log_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                            SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock
                            FROM producto_stock ps WHERE ps.idproducto = ? AND ps.idalmacen = ? LIMIT 1",
                        array('COMPRA #' . $idcompra, $cantTxt, (int)$us, $idsedeC, $idp, $alm, $idp, $alm));
                    // 2) recepcion + orden
                    log_exec($bd, "INSERT INTO recepcion_item (idrecepcion, idorden_item, idproducto, cantidad_recibida, punitario, origen,
                            idorden_item_reemplazado, nota) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        array($idrec, (int)$i['idorden_item'], $idp, $l['cant'], $l['pu'], $l['cambio'] ? 'reemplazo' : 'pedido',
                              $l['cambio'] ? (int)$i['idorden_item'] : null, $l['nota'] !== '' ? $l['nota'] : null));
                    // MySQL aplica el SET de izquierda a derecha: en el IF cantidad_recibida YA tiene la suma
                    log_exec($bd, "UPDATE orden_compra_item SET cantidad_recibida = cantidad_recibida + ?, punitario = ?,
                            estado_item = IF(cantidad_recibida >= cantidad_pedida, 'completo', 'parcial') WHERE idorden_item = ?",
                        array($l['cant'], $l['pu'], (int)$i['idorden_item']));
                    // 3) lote (si lo escribieron o tiene vencimiento)
                    if ($l['lote'] !== '' || $l['venc']) {
                        log_exec($bd, "INSERT INTO log_lote (idorg, idproducto_central, lote, vencimiento, idrecepcion) VALUES (?, ?, ?, ?, ?)",
                            array((int)$org['idorg'], $idp, $l['lote'], $l['venc'], $idrec));
                        log_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'recepcion', ?)",
                            array((int)$bd->lastInsertId(), $alm, $l['cant'], $idrec));
                    }
                    // 4) ultimo precio del proveedor (lo usa la proxima orden)
                    log_exec($bd, "INSERT INTO log_proveedor_producto (idproducto_central, idproveedor, idorg, ultimo_precio, fecha_ultimo, preferido)
                            VALUES (?, ?, ?, ?, NOW(), 0) ON DUPLICATE KEY UPDATE ultimo_precio = VALUES(ultimo_precio), fecha_ultimo = NOW()",
                        array($idp, (int)$oc['idproveedor'], (int)$org['idorg'], $l['pu']));
                    // 5) lo que pidieron las sedes pasa a "sale del central" (F6 lo traslada)
                    compras_a_central($bd, (int)$oc['idorden'], (int)$i['idproducto'], $l['cant'], $idp);
                }
                foreach ($notas as $n) {   // no llego, pero el recepcionista dejo una observacion
                    log_exec($bd, "INSERT INTO recepcion_item (idrecepcion, idorden_item, idproducto, cantidad_recibida, punitario, origen, nota)
                            VALUES (?, ?, ?, 0, 0, 'pedido', ?)", array($idrec, (int)$n['item']['idorden_item'], (int)$n['item']['idproducto'], $n['nota']));
                }
                log_exec($bd, "INSERT INTO compra_pago (idcompra, idtipo_pago, importe) VALUES (?, ?, ?)", array($idcompra, $idtp, $totalTxt));
                $falta = (int)log_filas($bd, "SELECT COUNT(*) AS n FROM orden_compra_item WHERE idorden = ? AND cantidad_recibida < cantidad_pedida",
                    array((int)$oc['idorden']))[0]['n'];
                log_exec($bd, "UPDATE orden_compra SET estado = ? WHERE idorden = ?", array($falta ? 'parcial' : 'recibida', (int)$oc['idorden']));
                $res = array('idcompra' => $idcompra, 'idrecepcion' => $idrec, 'total' => round($total, 2), 'completa' => !$falta,
                    'idsede' => $idsedeC);
                log_token_cerrar($bd, 'recepcion', $token, $res);
                $bd->bd->commit();
                return $res;
            } catch (Throwable $e) {
                $bd->bd->rollback();
                throw $e;
            }
        })();
        costeoRecalcularSeguro($bd, $res['idsede'], 'compra');   // como x-compras2: cambio el costo de insumos
        return $res;
    });

case 'llegadas':
    // Lo que llego al almacen de la central (con o sin orden de compra), lo mas nuevo primero, con el resumen de lo
    // que anoto el recepcionista. Por indice idx_compra_sede (idsede, estado, idcompra): sin recorrer otras sedes.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $c = (int)$org['idsede_central'];
        $porPag = 20;
        // periodo por fecha de registro (reloj de MySQL) y estado de pago (como Cuentas por pagar: pagado / a_pagar)
        $periodos = array(
            'hoy' => "c.fecha_hora >= CURDATE()",
            '7' => "c.fecha_hora >= CURDATE() - INTERVAL 6 DAY",
            '15' => "c.fecha_hora >= CURDATE() - INTERVAL 14 DAY",
            'semana' => "c.fecha_hora >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)",
            'mes' => "c.fecha_hora >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'mes_pasado' => "c.fecha_hora >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND c.fecha_hora < DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'todo' => "1 = 1",
        );
        $per = isset($_GET['periodo']) && isset($periodos[$_GET['periodo']]) ? $_GET['periodo'] : 'mes';
        // debe = compra a CREDITO aun no pagada (mismo criterio que Cuentas por pagar); las de contado nacen pagadas
        $debe = "(c.idtipo_pago = " . TIPO_PAGO_CREDITO . " AND c.pagado = 0)";
        $pagos = array('todas' => "1 = 1", 'por_pagar' => $debe, 'pagadas' => "NOT " . $debe);
        $pg = isset($_GET['pago']) && isset($pagos[$_GET['pago']]) ? $_GET['pago'] : 'todas';
        $w = "c.idsede = ? AND c.estado = 0 AND c.idorg = ? AND " . $periodos[$per] . " AND " . $pagos[$pg];
        $res = log_filas($bd, "SELECT COUNT(*) AS n, IFNULL(ROUND(SUM(c.total + 0), 2), 0) AS comprado,
                IFNULL(ROUND(SUM(IF($debe, c.a_pagar + 0, 0)), 2), 0) AS por_pagar, IFNULL(SUM($debe), 0) AS n_por_pagar
                FROM compra c WHERE $w", array($c, (int)$ido))[0];
        // lo que se debe a proveedores de TODAS las fechas (lo mismo que Cuentas por pagar de la central)
        $deuda = (float)log_filas($bd, "SELECT IFNULL(ROUND(SUM(c.a_pagar + 0), 2), 0) AS d FROM compra c
                WHERE c.idsede = ? AND c.estado = 0 AND c.idorg = ? AND $debe", array($c, (int)$ido))[0]['d'];
        $total = (int)$res['n'];
        $paginas = max(1, (int)ceil($total / $porPag));
        $pag = isset($_GET['pagina']) ? max(1, min($paginas, (int)$_GET['pagina'])) : 1;
        $filas = log_filas($bd, "SELECT c.idcompra, IF(c.fecha_hora > '2000-01-01', DATE_FORMAT(c.fecha_hora, '%d/%m/%Y %H:%i'), c.f_registro) AS registrado,
                c.total + 0 AS total, IFNULL(pv.descripcion, 'Sin proveedor') AS proveedor, IFNULL(c.comprobante, '') AS comprobante,
                IFNULL(NULLIF(TRIM(u.nombres), ''), u.usuario) AS recibio, IFNULL(a.descripcion, '') AS almacen, r.idorden,
                $debe AS debe, c.a_pagar + 0 AS pendiente, IFNULL(c.f_pago, '') AS f_pago,
                (SELECT COUNT(*) FROM compra_items ci WHERE ci.idcompra = c.idcompra) AS productos,
                IF(r.idrecepcion IS NULL, 0, (SELECT COUNT(*) FROM recepcion_item ri WHERE ri.idrecepcion = r.idrecepcion AND ri.origen = 'reemplazo')) AS cambios,
                IF(r.idrecepcion IS NULL, 0, (SELECT COUNT(*) FROM recepcion_item ri WHERE ri.idrecepcion = r.idrecepcion AND ri.nota <> '')
                    + (IFNULL(r.nota, '') <> '')) AS notas,
                IF(r.idrecepcion IS NULL, 0, (SELECT COUNT(*) FROM orden_compra_item oi WHERE oi.idorden = r.idorden AND oi.cantidad_recibida < oi.cantidad_pedida)) AS faltan
                FROM compra c LEFT JOIN proveedor pv ON pv.idproveedor = c.idproveedor LEFT JOIN usuario u ON u.idusuario = c.idusuario
                LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
                LEFT JOIN recepcion r ON r.idcompra = c.idcompra AND r.idorg = c.idorg
                WHERE $w ORDER BY c.idcompra DESC LIMIT " . $porPag . " OFFSET " . (($pag - 1) * $porPag),
            array($c, (int)$ido));
        return array('filas' => $filas, 'meta' => array('pagina' => $pag, 'paginas' => $paginas, 'total' => $total),
            'periodo' => $per, 'pago' => $pg,
            'resumen' => array('comprado' => (float)$res['comprado'], 'por_pagar' => (float)$res['por_pagar'], 'n_por_pagar' => (int)$res['n_por_pagar'],
                'pagado' => round((float)$res['comprado'] - (float)$res['por_pagar'], 2), 'deuda_total' => $deuda));
    });

case 'llegada':
    // detalle: que llego, que se pidio, que falto, que llego cambiado, observaciones, precio, lote; quien recibio y donde
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $cab = log_filas($bd, "SELECT c.idcompra, IF(c.fecha_hora > '2000-01-01', DATE_FORMAT(c.fecha_hora, '%d/%m/%Y %H:%i'), c.f_registro) AS registrado,
                c.f_compra, c.total + 0 AS total, IFNULL(pv.descripcion, 'Sin proveedor') AS proveedor, IFNULL(c.comprobante, '') AS comprobante,
                IFNULL(c.nota_de_compra, '') AS nota, IFNULL(NULLIF(TRIM(u.nombres), ''), u.usuario) AS recibio, IFNULL(a.descripcion, '') AS almacen, c.pagado,
                (c.idtipo_pago = " . TIPO_PAGO_CREDITO . " AND c.pagado = 0) AS debe, c.a_pagar + 0 AS pendiente, IFNULL(c.f_pago, '') AS f_pago
                FROM compra c LEFT JOIN proveedor pv ON pv.idproveedor = c.idproveedor LEFT JOIN usuario u ON u.idusuario = c.idusuario
                LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
                WHERE c.idcompra = ? AND c.idorg = ? AND c.idsede = ?", array($id, (int)$ido, (int)$org['idsede_central']));
        if (!$cab) { throw new ErrorUsuario('No se encontró esa llegada.'); }
        $rec = log_filas($bd, "SELECT idrecepcion, idorden, IFNULL(nota, '') AS nota FROM recepcion WHERE idcompra = ? AND idorg = ? LIMIT 1", array($id, (int)$ido));
        if (!$rec) {
            // compra directa (x-compras2): solo lo que se registro
            $items = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad, ci.cantidad + 0 AS cantidad, ci.punitario + 0 AS punitario,
                    ci.ptotal + 0 AS ptotal, NULL AS pedido, NULL AS recibido_total, 'pedido' AS origen, NULL AS en_lugar_de, NULL AS nota
                    FROM compra_items ci JOIN producto p ON p.idproducto = ci.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                    WHERE ci.idcompra = ? ORDER BY p.descripcion", array($id));
            foreach ($items as &$i) { $i['lotes'] = array(); }
            unset($i);
            return array('cabecera' => $cab[0] + array('idorden' => null, 'observacion' => ''), 'items' => $items);
        }
        $r = $rec[0];
        // lo anotado en ESTA recepcion (incluye cambios y observaciones de lo que no llego)
        $items = log_filas($bd, "SELECT ri.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, ri.cantidad_recibida + 0 AS cantidad,
                ri.punitario + 0 AS punitario, ROUND(ri.cantidad_recibida * ri.punitario, 2) AS ptotal, oi.cantidad_pedida + 0 AS pedido,
                oi.cantidad_recibida + 0 AS recibido_total, ri.origen, IF(ri.origen = 'reemplazo', po.descripcion, NULL) AS en_lugar_de, ri.nota
                FROM recepcion_item ri JOIN producto p ON p.idproducto = ri.idproducto
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                LEFT JOIN orden_compra_item oi ON oi.idorden_item = ri.idorden_item LEFT JOIN producto po ON po.idproducto = oi.idproducto
                WHERE ri.idrecepcion = ? ORDER BY p.descripcion", array((int)$r['idrecepcion']));
        // de la orden, no anotado en esta recepcion y que sigue faltando
        foreach (log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad, oi.cantidad_pedida + 0 AS pedido, oi.cantidad_recibida + 0 AS recibido_total
                FROM orden_compra_item oi JOIN producto p ON p.idproducto = oi.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE oi.idorden = ? AND oi.cantidad_recibida < oi.cantidad_pedida
                  AND NOT EXISTS (SELECT 1 FROM recepcion_item ri WHERE ri.idrecepcion = ? AND ri.idorden_item = oi.idorden_item)",
                array((int)$r['idorden'], (int)$r['idrecepcion'])) as $f) {
            $items[] = $f + array('idproducto' => 0, 'cantidad' => 0, 'punitario' => 0, 'ptotal' => 0, 'origen' => 'no_llego', 'en_lugar_de' => null, 'nota' => null);
        }
        $lotes = array();
        foreach (log_filas($bd, "SELECT l.idproducto_central, l.lote, IFNULL(DATE_FORMAT(l.vencimiento, '%d/%m/%Y'), '') AS vence, SUM(m.cantidad) AS cantidad
                FROM log_lote l JOIN log_lote_mov m ON m.idlote = l.idlote AND m.origen = 'recepcion'
                WHERE l.idorg = ? AND l.idrecepcion = ? GROUP BY l.idlote", array((int)$ido, (int)$r['idrecepcion'])) as $l) {
            $lotes[(int)$l['idproducto_central']][] = array('lote' => $l['lote'], 'vence' => $l['vence'], 'cantidad' => (float)$l['cantidad']);
        }
        foreach ($items as &$i) {
            $i['lotes'] = isset($lotes[(int)$i['idproducto']]) && (float)$i['cantidad'] > 0 ? $lotes[(int)$i['idproducto']] : array();
            unset($i['idproducto']);
        }
        unset($i);
        $cab[0]['nota'] = '';   // la nota de la compra repite "Orden de compra N°"; la observacion real va aparte
        return array('cabecera' => $cab[0] + array('idorden' => (int)$r['idorden'], 'observacion' => $r['nota']), 'items' => $items);
    });

case 'cerrar_faltante':
    // "no llegara lo que falta": la orden se cierra y lo que faltaba de comprar vuelve a "Por comprar"
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $bd->bd->begin_transaction();
        try {
            $oc = oc_central($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
            if ($oc['estado'] !== 'parcial') { throw new ErrorUsuario('Solo se cierra una orden que llegó en parte.'); }
            log_exec($bd, "UPDATE orden_compra SET estado = 'cerrada' WHERE idorden = ?", array((int)$oc['idorden']));
            oc_evento($bd, (int)$oc['idorden'], 'cerrada', $us, 'No llegará lo que falta: volvió a Por comprar');
            log_exec($bd, "UPDATE log_bandeja_linea SET idorden = NULL, estado = 'pendiente'
                    WHERE idorden = ? AND decision = 'compra' AND estado = 'en_proceso'", array((int)$oc['idorden']));
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('ok' => true);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

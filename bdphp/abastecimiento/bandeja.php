<?php
/**
 * Logistica F3 — Bandeja central: pedidos abiertos de las sedes agrupados por producto, con una propuesta de
 * donde sale cada cosa: 1) almacen central  2) traslado desde una sede con stock parado  3) comprar.
 * ?op=abrir (POST: marca 'visto' y devuelve la bandeja) | aceptar (POST) | repo_cfg | repo_guardar {activo}
 * Las decisiones van a log_bandeja_linea (migracion 008); F4 (compras) y F6 (traslados) las ejecutan.
 * Consultas por indice: solicitud_producto(idorg, idsede_destino), producto por PK, log_producto_central
 * (ix_lpc_central), log_consumo_dia PK(idproducto, fecha), log_bandeja_linea(ix_lbl_central).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/_consumo.php';
require_once __DIR__ . '/_reposicion.php';
require_once __DIR__ . '/_produccion.php';

const ESTADOS_BANDEJA = "'enviado', 'visto', 'atendido'";

function in_marcas($ids) { return implode(',', array_fill(0, count($ids), '?')); }

/** Lineas de pedido abiertas y aun sin decision, con su producto central. */
function lineas_abiertas($bd, $org) {
    return log_filas($bd, "SELECT d.idsolicitud_producto_detalle AS iddet, s.idsolicitud_producto AS idsol, s.idsede_solicita AS idsede,
            sd.nombre AS sede, DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y %h:%i %p') AS fecha, s.nota, d.cantidad_solicitada + 0 AS cant, v.idproducto_central,
            IFNULL(NULLIF(TRIM(u.nombres), ''), IFNULL(u.usuario, '')) AS usuario,
            TIMESTAMPDIFF(MINUTE, s.fecha_solicitud, NOW()) AS minutos
            FROM solicitud_producto s
            LEFT JOIN usuario u ON u.idusuario = s.idusuario_solicita
            JOIN solicitud_producto_detalle d ON d.idsolicitud_producto = s.idsolicitud_producto AND d.tipo_producto = 'almacen'
            JOIN log_producto_central v ON v.idproducto = d.idproducto
            JOIN sede sd ON sd.idsede = s.idsede_solicita
            WHERE s.idorg = ? AND s.idsede_destino = ? AND s.estado IN (" . ESTADOS_BANDEJA . ")
              AND NOT EXISTS (SELECT 1 FROM log_bandeja_linea b WHERE b.idsolicitud_detalle = d.idsolicitud_producto_detalle)
            ORDER BY s.idsolicitud_producto, d.idsolicitud_producto_detalle LIMIT 3000",
        array((int)$org['idorg'], (int)$org['idsede_central']));
}

/** Datos del producto central + stock libre del almacen PRINCIPAL de la central (de ahi se despacha) y lo que hay en otros almacenes. */
function productos_centrales($bd, $org, $ids) {
    $alm = alm_principal($bd, $org['idsede_central']);
    $r = array();
    foreach (log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia, IFNULL(u.abreviatura, '') AS unidad,
            p.idunidad_kardex,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto AND ps.idalmacen = ?), 0) AS stock,
            " . SQL_OTROS_ALMACENES . " AS otros_almacenes,
            IFNULL((SELECT SUM(b.cantidad) FROM log_bandeja_linea b WHERE b.idproducto_central = p.idproducto
                     AND (b.decision = 'central' OR (b.decision = 'produccion' AND b.idsede_origen = ?)) AND b.estado <> 'hecho'), 0) AS comprometido,
            (SELECT pv.descripcion FROM log_proveedor_producto lpp JOIN proveedor pv ON pv.idproveedor = lpp.idproveedor
              WHERE lpp.idproducto_central = p.idproducto AND lpp.preferido = 1 LIMIT 1) AS proveedor,
            (SELECT CONCAT(lp.nombre, '|', lp.factor) FROM log_presentacion lp
              WHERE lp.idproducto_central = p.idproducto AND lp.activo = 1 ORDER BY lp.factor DESC LIMIT 1) AS presentacion
            FROM producto p
            LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE p.idproducto IN (" . in_marcas($ids) . ")", array_merge(array($alm, $alm, (int)$org['idsede_central']), $ids)) as $f) {
        $r[(int)$f['idproducto']] = $f;
    }
    return $r;
}

/**
 * Reparte cada linea: primero central; si la planta sabe producirlo, el resto se produce alli (y sale de la planta);
 * si no, sedes con stock parado (no la misma sede) y el resto se compra. $planta = {idsede, nombre, ids:[idcentral=>true]}.
 */
function proponer($lineas, $prods, $parado, $planta = null) {
    $poolCentral = array();
    foreach ($prods as $id => $p) { $poolCentral[$id] = max(0, (float)$p['stock'] - (float)$p['comprometido']); }
    $porProd = array();
    foreach ($lineas as $l) {
        $id = (int)$l['idproducto_central'];
        if (!isset($prods[$id])) { continue; }
        $pieza = (int)$prods[$id]['idunidad_kardex'] === 0;
        $falta = (float)$l['cant'];
        $partes = array();
        $c = min($falta, $poolCentral[$id]);
        if ($pieza) { $c = floor($c); }
        if ($c > 0) { $partes[] = array('decision' => 'central', 'cantidad' => $c); $poolCentral[$id] -= $c; $falta -= $c; }
        if ($falta > 0 && $planta && isset($planta['ids'][$id]) && $planta['idsede'] !== (int)$l['idsede']) {
            $partes[] = array('decision' => 'produccion', 'cantidad' => round($falta, 3), 'idsede_origen' => $planta['idsede'], 'sede_origen' => $planta['nombre']);
            $falta = 0;
        }
        if ($falta > 0 && isset($parado[$id])) {
            foreach ($parado[$id] as $k => $o) {
                if ($falta <= 0) { break; }
                if ($o['idsede'] === (int)$l['idsede'] || $o['libre'] <= 0) { continue; }
                $t = min($falta, $o['libre']);
                if ($pieza) { $t = floor($t); }
                if ($t <= 0) { continue; }
                $partes[] = array('decision' => 'traslado', 'cantidad' => $t, 'idsede_origen' => $o['idsede'], 'sede_origen' => $o['sede'],
                    'dias_stock' => $o['dias_stock']);
                $parado[$id][$k]['libre'] -= $t;
                $falta -= $t;
            }
        }
        if ($falta > 0) { $partes[] = array('decision' => 'compra', 'cantidad' => round($falta, 2)); }
        $porProd[$id][] = array('iddet' => (int)$l['iddet'], 'idsol' => (int)$l['idsol'], 'idsede' => (int)$l['idsede'],
            'sede' => $l['sede'], 'fecha' => $l['fecha'], 'nota' => $l['nota'], 'usuario' => $l['usuario'], 'minutos' => (int)$l['minutos'],
            'cant' => (float)$l['cant'], 'partes' => $partes);
    }
    $res = array();
    foreach ($porProd as $id => $ls) {
        $p = $prods[$id];
        $res[] = array('idproducto_central' => $id, 'descripcion' => $p['descripcion'], 'familia' => $p['familia'],
            'unidad' => $p['unidad'], 'stock_central' => (float)$p['stock'], 'otros_almacenes' => $p['otros_almacenes'], 'libre_central' => max(0, (float)$p['stock'] - (float)$p['comprometido']),
            'proveedor' => $p['proveedor'], 'presentacion' => $p['presentacion'], 'produce' => $planta && isset($planta['ids'][$id]),
            'total' => array_sum(array_column($ls, 'cant')), 'lineas' => $ls);
    }
    usort($res, function ($a, $b) { return strcmp($a['familia'] . $a['descripcion'], $b['familia'] . $b['descripcion']); });
    return $res;
}

/** Ordenes de compra abiertas de la central con saldo por llegar, por producto (indice oc_org_sede_estado_IDX). */
function compras_abiertas($bd, $org, $ids) {
    $res = array();
    if (!$ids) { return $res; }
    $filas = log_filas($bd, "SELECT i.idproducto, oc.idorden, oc.estado, IFNULL(pv.descripcion, '') AS proveedor,
            DATE_FORMAT(IFNULL(oc.fecha_envio, oc.fecha), '%d/%m/%Y') AS fecha, i.cantidad_pedida - i.cantidad_recibida AS falta
            FROM orden_compra oc JOIN orden_compra_item i ON i.idorden = oc.idorden
            LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
            WHERE oc.idorg = ? AND oc.idsede = ? AND oc.estado IN ('por_aprobar', 'aprobada', 'enviada', 'parcial')
              AND i.idproducto IN (" . in_marcas($ids) . ") AND i.cantidad_pedida > i.cantidad_recibida
            ORDER BY oc.idorden", array_merge(array((int)$org['idorg'], (int)$org['idsede_central']), $ids));
    foreach ($filas as $f) {
        $res[(int)$f['idproducto']][] = array('idorden' => (int)$f['idorden'], 'estado' => $f['estado'], 'proveedor' => $f['proveedor'],
            'fecha' => $f['fecha'], 'falta' => (float)$f['falta']);
    }
    return $res;
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'abrir':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        try { $auto = reponer_auto($bd, $org); }
        catch (Throwable $e) { $auto = 0; error_log('logistica reposicion: ' . $e->getMessage()); }   // la bandeja abre igual
        // al abrir la bandeja la central "vio" los pedidos: la sede ya no puede cancelarlos
        log_exec($bd, "UPDATE solicitud_producto SET estado = 'visto', fecha_visto = NOW(), idusuario_atiende = ?
                WHERE idorg = ? AND idsede_destino = ? AND estado = 'enviado'", array((int)$us, (int)$ido, (int)$org['idsede_central']));
        $lineas = lineas_abiertas($bd, $org);
        if (!$lineas) { return array('productos' => array(), 'pedidos' => 0, 'sedes' => 0, 'automaticos' => $auto); }
        $ids = array_values(array_unique(array_map('intval', array_column($lineas, 'idproducto_central'))));
        $prods = productos_centrales($bd, $org, $ids);
        $pc = prod_cfg($bd, $org);
        $planta = $pc['activa'] ? array('idsede' => $pc['idsede_planta'], 'ids' => prod_centrales_con_receta($bd, $org, $ids),
            'nombre' => log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array($pc['idsede_planta']))[0]['nombre']) : null;
        $res = proponer($lineas, $prods, stock_parado($bd, $org, $ids), $planta);
        $alm = alm_principal($bd, $org['idsede_central']);
        $ocs = compras_abiertas($bd, $org, $ids);
        foreach ($res as &$p) {
            $p['otros'] = $p['otros_almacenes'] ? otros_almacenes_lista($bd, $p['idproducto_central'], $org['idsede_central'], $alm) : array();
            $p['compras'] = isset($ocs[$p['idproducto_central']]) ? $ocs[$p['idproducto_central']] : array();
        }
        unset($p);
        return array('productos' => $res, 'idsede_central' => (int)$org['idsede_central'],
            'planta' => $planta ? array('idsede' => $planta['idsede'], 'nombre' => $planta['nombre']) : null,
            'pedidos' => count(array_unique(array_column($lineas, 'idsol'))),
            'sedes' => count(array_unique(array_column($lineas, 'idsede'))), 'automaticos' => $auto);
    });

case 'aceptar':
    // lineas: [{iddet, partes:[{decision, cantidad, idsede_origen?}]}]; la suma de partes = lo pedido
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $lineas = isset($b['lineas']) && is_array($b['lineas']) ? array_slice($b['lineas'], 0, 3000) : array();
        if (!$lineas) { throw new ErrorUsuario('Elige al menos un producto.'); }
        $sedesOrg = array();
        foreach (log_filas($bd, "SELECT idsede FROM log_sede WHERE idorg = ? AND activo = 1", array((int)$ido)) as $s) { $sedesOrg[(int)$s['idsede']] = true; }
        $pc = prod_cfg($bd, $org);
        $hechas = 0; $sols = array();
        $bd->bd->begin_transaction();
        try {
            foreach ($lineas as $l) {
                $iddet = is_array($l) && isset($l['iddet']) ? (int)$l['iddet'] : 0;
                // la linea debe ser de un pedido abierto a esta central y sin decision (FOR UPDATE: doble clic no duplica)
                $d = log_filas($bd, "SELECT d.idsolicitud_producto_detalle, d.cantidad_solicitada + 0 AS cant, s.idsolicitud_producto, s.idsede_solicita,
                        v.idproducto_central
                        FROM solicitud_producto_detalle d
                        JOIN solicitud_producto s ON s.idsolicitud_producto = d.idsolicitud_producto
                        JOIN log_producto_central v ON v.idproducto = d.idproducto
                        WHERE d.idsolicitud_producto_detalle = ? AND s.idorg = ? AND s.idsede_destino = ? AND s.estado IN (" . ESTADOS_BANDEJA . ")
                        FOR UPDATE", array($iddet, (int)$ido, (int)$org['idsede_central']));
                // check con lectura bloqueante: lee lo ultimo confirmado, no la foto de la tx (REPEATABLE READ). Si no, con
                // lineas en otro orden el 2.o usuario ya tenia la foto tomada y duplicaba la decision al soltarse el candado
                if (!$d || log_filas($bd, "SELECT 1 FROM log_bandeja_linea WHERE idsolicitud_detalle = ? FOR UPDATE", array($iddet))) { continue; }
                $d = $d[0];
                $suma = 0;
                $partes = isset($l['partes']) && is_array($l['partes']) ? $l['partes'] : array();
                foreach ($partes as $p) {
                    $dec = is_array($p) && isset($p['decision']) ? $p['decision'] : '';
                    $cant = is_array($p) && isset($p['cantidad']) ? round((float)$p['cantidad'], 4) : 0;
                    $orig = $dec === 'traslado' && isset($p['idsede_origen']) ? (int)$p['idsede_origen'] : ($dec === 'produccion' ? $pc['idsede_planta'] : null);
                    if (!in_array($dec, array('central', 'traslado', 'compra', 'rechazo', 'produccion'), true) || $cant <= 0) { throw new ErrorUsuario('Hay una cantidad no válida.'); }
                    if ($dec === 'produccion' && (!$pc['activa'] || !prod_centrales_con_receta($bd, $org, array((int)$d['idproducto_central'])))) {
                        throw new ErrorUsuario('Ese producto no tiene receta en la planta de producción.');
                    }
                    if ($dec === 'produccion' && $orig === (int)$d['idsede_solicita']) { throw new ErrorUsuario('La planta no puede producir para su propio pedido: que lo produzca en Producción.'); }
                    if ($dec === 'traslado' && (!isset($sedesOrg[$orig]) || $orig === (int)$d['idsede_solicita'])) { throw new ErrorUsuario('Sede de origen no válida.'); }
                    log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central,
                            decision, idsede_origen, cantidad, idusuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        array((int)$ido, (int)$d['idsolicitud_producto'], $iddet, (int)$d['idsede_solicita'], (int)$d['idproducto_central'],
                              $dec, $orig, $cant, (int)$us));
                    $suma += $cant;
                }
                if (abs($suma - (float)$d['cant']) > 0.001) { throw new ErrorUsuario('Las partes no suman lo que pidió la sede.'); }
                $hechas++;
                $sols[(int)$d['idsolicitud_producto']] = true;
            }
            // pedido con todas sus lineas decididas -> 'atendido' (la sede lo ve como "Preparando")
            foreach (array_keys($sols) as $idsol) {
                log_exec($bd, "UPDATE solicitud_producto s SET s.estado = 'atendido', s.fecha_atendido = NOW(), s.idusuario_atiende = ?
                        WHERE s.idsolicitud_producto = ? AND s.estado IN ('enviado', 'visto')
                          AND NOT EXISTS (SELECT 1 FROM solicitud_producto_detalle d
                                LEFT JOIN log_bandeja_linea b ON b.idsolicitud_detalle = d.idsolicitud_producto_detalle
                                WHERE d.idsolicitud_producto = s.idsolicitud_producto AND d.tipo_producto = 'almacen' AND b.id IS NULL)",
                    array((int)$us, $idsol));
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('aceptadas' => $hechas);
    });

case 'repo_cfg':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        return array('activo' => (bool)log_filas($bd, "SELECT 1 FROM org_logistica WHERE idorg = ? AND reposicion_auto = 1", array((int)$ido)));
    });

case 'repo_guardar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        // reposicion_ultima = NULL: al encender, la proxima apertura de la Bandeja revisa enseguida
        log_exec($bd, "UPDATE org_logistica SET reposicion_auto = ?, reposicion_ultima = NULL WHERE idorg = ?", array(empty($b['activo']) ? 0 : 1, (int)$ido));
        return array('activo' => !empty($b['activo']));
    });

default:
    log_out(false, null, 'Operación no válida.');
}

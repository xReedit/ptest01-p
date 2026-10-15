<?php
/**
 * Logistica — "Repartir a las sedes": la central manda productos de su almacen (o de la planta de produccion) a las
 * sedes sin que lo pidan. ?op=inicio&origen= | info&origen=&c= | guardar | guardar_lote
 * Lo repartido sale por Envios; cada sede lo cuenta en Recibir y sube su stock (_repartir.php).
 * Consultas por indice: producto_stock(idalmacen), log_producto_central(uq_lpc_sede_central / PK).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/_produccion.php';
require_once __DIR__ . '/_repartir.php';

/** De donde puede salir: la central y, si hay produccion con planta aparte, la planta. */
function rep_origenes($bd, $org) {
    $ids = array((int)$org['idsede_central']);
    $pc = prod_cfg($bd, $org);
    if ($pc['activa'] && $pc['idsede_planta'] !== (int)$org['idsede_central']) { $ids[] = $pc['idsede_planta']; }
    return log_filas($bd, "SELECT ls.idsede, s.nombre, ls.idalmacen_principal AS alm FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            ORDER BY ls.idsede <> ?", array_merge(array((int)$org['idorg']), $ids, array((int)$org['idsede_central'])));
}

/** Origen elegido (o la central), validado. */
function rep_origen($bd, $org, $pedido) {
    foreach (rep_origenes($bd, $org) as $o) {
        if (!$pedido || (int)$o['idsede'] === (int)$pedido) {
            if ((int)$o['alm'] <= 0) { throw new ErrorUsuario($o['nombre'] . ' no tiene almacén elegido. Elígelo en Configurar.'); }
            return array('idsede' => (int)$o['idsede'], 'nombre' => $o['nombre'], 'alm' => (int)$o['alm']);
        }
    }
    throw new ErrorUsuario('Desde esa sede no se reparte.');
}

/** Producto del origen que corresponde al central (o error). [idproducto origen, descripcion, unidad] */
function rep_producto($bd, $org, $s, $c) {
    $r = log_filas($bd, "SELECT p.idproducto, pc.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto pc
            LEFT JOIN log_producto_central v ON v.idproducto_central = pc.idproducto AND v.idsede = ?
            JOIN producto p ON p.idproducto = IF(? , pc.idproducto, v.idproducto) AND p.estado = 0
            LEFT JOIN unidad_medida u ON u.idunidad_medida = pc.idunidad_kardex
            WHERE pc.idproducto = ? AND pc.idorg = ? AND pc.idsede = ? AND pc.estado = 0",
        array($s['idsede'], $s['idsede'] === (int)$org['idsede_central'] ? 1 : 0, (int)$c, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Ese producto no está en el catálogo de ' . $s['nombre'] . '.'); }
    return $r[0];
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'inicio':
    // origenes + productos con stock en el origen elegido (para elegir de la lista)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $s = rep_origen($bd, $org, isset($_GET['origen']) ? (int)$_GET['origen'] : 0);
        $esCentral = $s['idsede'] === (int)$org['idsede_central'];
        $prods = log_filas($bd, $esCentral
            ? "SELECT p.idproducto AS c, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, SUM(ps.stock) + 0 AS stock
                FROM producto_stock ps JOIN producto p ON p.idproducto = ps.idproducto AND p.estado = 0 AND p.idsede = ?
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE ps.idalmacen = ? AND ps.stock > 0 GROUP BY p.idproducto ORDER BY p.descripcion LIMIT 5000"
            : "SELECT v.idproducto_central AS c, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, SUM(ps.stock) + 0 AS stock
                FROM producto_stock ps JOIN producto p ON p.idproducto = ps.idproducto AND p.estado = 0 AND p.idsede = ?
                JOIN log_producto_central v ON v.idproducto = p.idproducto
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE ps.idalmacen = ? AND ps.stock > 0 GROUP BY p.idproducto ORDER BY p.descripcion LIMIT 5000",
            array($s['idsede'], $s['alm']));
        return array('origen' => $s['idsede'], 'sedes' => log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre", array((int)$ido, $s['idsede'])), 'origenes' => array_map(function ($o) { return array('idsede' => (int)$o['idsede'], 'nombre' => $o['nombre']); },
            rep_origenes($bd, $org)), 'productos' => $prods);
    });

case 'info':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $s = rep_origen($bd, $org, isset($_GET['origen']) ? (int)$_GET['origen'] : 0);
        $c = isset($_GET['c']) ? (int)$_GET['c'] : 0;
        $p = rep_producto($bd, $org, $s, $c);
        return rep_libre($bd, $org, $s, $p['idproducto'], $c)
            + array('producto' => $p['descripcion'], 'unidad' => $p['unidad'], 'sedes' => rep_sedes($bd, $org, $s, $c));
    });

case 'guardar':
    // {origen, idproducto_central, items:[{idsede, cantidad}], token}
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $s = rep_origen($bd, $org, isset($b['origen']) ? (int)$b['origen'] : 0);
        $c = isset($b['idproducto_central']) ? (int)$b['idproducto_central'] : 0;
        $p = rep_producto($bd, $org, $s, $c);
        return rep_guardar($bd, $org, $s, $c, $p['idproducto'], $p['descripcion'], $p['unidad'], isset($b['items']) ? $b['items'] : array(), $us, log_token($b));
    });

case 'guardar_lote':
    // {origen, productos:[{idproducto_central, items:[{idsede, cantidad}]}], token}: toda la tabla de una vez (todo o nada)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $s = rep_origen($bd, $org, isset($b['origen']) ? (int)$b['origen'] : 0);
        $lote = array();
        foreach ((isset($b['productos']) && is_array($b['productos']) ? array_slice($b['productos'], 0, 300) : array()) as $pr) {
            $c = is_array($pr) && isset($pr['idproducto_central']) ? (int)$pr['idproducto_central'] : 0;
            $p = rep_producto($bd, $org, $s, $c);
            $items = rep_validar($bd, $org, $s, $c, $p['descripcion'], isset($pr['items']) ? $pr['items'] : array());
            if ($items) { $lote[$c] = array('p' => $p, 'items' => $items); }
        }
        if (!$lote) { throw new ErrorUsuario('Pon al menos una cantidad.'); }
        $token = log_token($b);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'repartir', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            rep_bloquear($bd, $org, $s);
            $sedes = array(); $n = 0;
            foreach ($lote as $c => $x) {
                rep_insertar($bd, $org, $s, $c, $x['p']['idproducto'], $x['p']['descripcion'], $x['p']['unidad'], $x['items'], $us);
                $sedes += array_fill_keys(array_keys($x['items']), true);
                $n++;
            }
            $res = array('productos' => $n, 'sedes' => count($sedes));
            log_token_cerrar($bd, 'repartir', $token, $res);
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

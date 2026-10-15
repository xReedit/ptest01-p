<?php
/**
 * Logistica — repartir a las sedes sin que lo pidan (lo usan repartir.php y Produccion).
 * Crea decisiones sin pedido en log_bandeja_linea (idsolicitud 0, como Stock parado): origen la central -> 'central';
 * otra sede (la planta) -> 'traslado' con idsede_origen. Aparecen en Envios; la sede lo cuenta en Recibir y sube su stock.
 * $s = {idsede, nombre, alm} del origen. Consultas por indice: producto_stock(uq prod+alm), log_bandeja_linea(ix_lbl_central),
 * log_producto_central(uq_lpc_sede_central).
 */

/** Stock del origen menos lo que ya espera salir de ahi en Envios (pedidos, repartos). */
function rep_libre($bd, $org, $s, $idprod, $c) {
    $st = (float)log_filas($bd, "SELECT IFNULL(SUM(stock), 0) AS q FROM producto_stock WHERE idproducto = ? AND idalmacen = ?", array((int)$idprod, $s['alm']))[0]['q'];
    $esCentral = $s['idsede'] === (int)$org['idsede_central'];
    $fac = $esCentral ? 0 : porciones_de($bd, $c);
    if ($fac && ($p = porcion_sede($bd, $s['idsede'], $idprod, $fac, 1))) { $st = $p['stock']; }   // la planta lo tiene en porciones
    $sale = (float)log_filas($bd, "SELECT IFNULL(SUM(cantidad), 0) AS q FROM log_bandeja_linea
            WHERE idproducto_central = ? AND estado = 'pendiente' AND idtraslado IS NULL AND idorg = ?
              AND (decision IN ('traslado', 'produccion') AND idsede_origen = ? OR (? AND decision = 'central' AND idsede <> ?))",
        array((int)$c, (int)$org['idorg'], $s['idsede'], $esCentral ? 1 : 0, $s['idsede']))[0]['q'];
    return array('stock' => $st, 'comprometido' => $sale, 'libre' => max(0, round($st - $sale, 3)));
}

/** Sedes a las que se puede mandar (todas menos el origen) con lo que tienen hoy de ese producto. */
function rep_sedes($bd, $org, $s, $c) {
    $cen = (int)$org['idsede_central'];
    $r = log_filas($bd, "SELECT ls.idsede, s.nombre, IF(ls.idsede = ?, ?, v.idproducto) AS idproducto,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = IF(ls.idsede = ?, ?, v.idproducto) AND ps.idalmacen = ls.idalmacen_principal), 0) + 0 AS stock
            FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            LEFT JOIN log_producto_central v ON v.idsede = ls.idsede AND v.idproducto_central = ?
            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre",
        array($cen, (int)$c, $cen, (int)$c, (int)$c, (int)$org['idorg'], $s['idsede']));
    $fac = porciones_de($bd, $c);
    foreach ($r as &$z) {
        // sede que lo vende por porcion: lo que tiene, en botes
        if ($fac && $z['idproducto'] && (int)$z['idsede'] !== $cen && ($p = porcion_sede($bd, (int)$z['idsede'], (int)$z['idproducto'], $fac, 1))) { $z['stock'] = $p['stock']; }
        $z['tiene'] = (bool)$z['idproducto']; unset($z['idproducto']);
    }
    unset($z);
    return $r;
}

/** Cantidades por sede del navegador, validadas: [idsede => cantidad]. */
function rep_validar($bd, $org, $s, $c, $nombre, $itemsIn) {
    $ido = (int)$org['idorg'];
    $items = array();
    foreach (is_array($itemsIn) ? array_slice($itemsIn, 0, 200) : array() as $it) {
        $d = is_array($it) && isset($it['idsede']) ? (int)$it['idsede'] : 0;
        $t = is_array($it) && isset($it['cantidad']) && is_scalar($it['cantidad']) ? str_replace(',', '.', trim((string)$it['cantidad'])) : '';
        if ($t === '' || (is_numeric($t) && (float)$t == 0)) { continue; }
        if ($d === $s['idsede']) { throw new ErrorUsuario($s['nombre'] . ' no se envía a sí misma.'); }
        $nom = log_filas($bd, "SELECT s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede WHERE ls.idsede = ? AND ls.idorg = ? AND ls.activo = 1", array($d, $ido));
        if (!$nom) { throw new ErrorUsuario('Hay una sede que no participa en Logística. Actualiza la página.'); }
        if (!is_numeric($t) || (float)$t < 0 || (float)$t > 999999) { throw new ErrorUsuario('Revisa la cantidad de ' . $nombre . ' para ' . $nom[0]['nombre'] . '.'); }
        if ($d !== (int)$org['idsede_central']
                && !log_filas($bd, "SELECT 1 FROM log_producto_central WHERE idsede = ? AND idproducto_central = ?", array($d, (int)$c))) {
            throw new ErrorUsuario($nom[0]['nombre'] . ' no tiene ' . $nombre . ' en su catálogo. Envíaselo desde Catálogo.');
        }
        $items[$d] = (isset($items[$d]) ? $items[$d] : 0) + round((float)$t, 3);
    }
    return $items;
}

/** Dentro de la transaccion y con el origen bloqueado: revisa lo libre e inserta las decisiones. */
function rep_insertar($bd, $org, $s, $c, $idprod, $nombre, $unidad, $items, $us) {
    $l = rep_libre($bd, $org, $s, $idprod, $c);
    $total = round(array_sum($items), 3);
    if ($total > $l['libre'] + 0.0001) {
        throw new ErrorUsuario('Solo hay ' . txt_cant($l['libre']) . ' ' . $unidad . ' disponibles de ' . $nombre . ' en ' . $s['nombre'] . ' y quieres repartir ' . txt_cant($total) . '. Baja las cantidades.');
    }
    $esCentral = $s['idsede'] === (int)$org['idsede_central'];
    foreach ($items as $d => $q) {
        log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                idsede_origen, cantidad, idusuario) VALUES (?, 0, 0, ?, ?, ?, ?, ?, ?)",
            array((int)$org['idorg'], $d, (int)$c, $esCentral ? 'central' : 'traslado', $esCentral ? null : $s['idsede'], $q, (int)$us));
    }
    return $total;
}

/** Bloquea el origen: dos pestanas a la vez no reparten lo mismo (la segunda espera y ve lo ya repartido). */
function rep_bloquear($bd, $org, $s) {
    log_filas($bd, "SELECT idsede FROM log_sede WHERE idsede = ? AND idorg = ? FOR UPDATE", array($s['idsede'], (int)$org['idorg']));
}

/** Valida y guarda el reparto de UN producto. $items = [{idsede, cantidad}] del navegador. Abre y cierra su transaccion. */
function rep_guardar($bd, $org, $s, $c, $idprod, $nombre, $unidad, $itemsIn, $us, $token) {
    $items = rep_validar($bd, $org, $s, $c, $nombre, $itemsIn);
    if (!$items) { throw new ErrorUsuario('Pon cuánto va a cada sede.'); }
    $bd->bd->begin_transaction();
    try {
        if ($previo = log_token_reservar($bd, 'repartir', $token, (int)$org['idorg'])) { $bd->bd->rollback(); return $previo; }
        rep_bloquear($bd, $org, $s);
        $total = rep_insertar($bd, $org, $s, $c, $idprod, $nombre, $unidad, $items, $us);
        $res = array('sedes' => count($items), 'total' => $total, 'producto' => $nombre);
        log_token_cerrar($bd, 'repartir', $token, $res);
        $bd->bd->commit();
    } catch (Throwable $e) {
        $bd->bd->rollback();
        throw $e;
    }
    return $res;
}

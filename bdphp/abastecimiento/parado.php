<?php
/**
 * Logistica — Stock parado en las sedes (central). Solo productos que se descuentan con ventas (ver _stock.php
 * se_venden): los abarrotes sin recetas nunca bajan de stock y no se muestran.
 * ?op=lista&idsede= | trasladar {idproducto_central, idsede_origen, idsede_destino, cantidad, token} | vigilar {idproducto_central, modo}
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/_consumo.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'lista':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        try { actualizar_consumo($bd, $org); } catch (Throwable $e) { error_log('logistica parado consumo: ' . $e->getMessage()); }
        $s = isset($_GET['idsede']) ? (int)$_GET['idsede'] : 0;
        $sedes = log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre", array((int)$ido, (int)$org['idsede_central']));
        $ocultos = log_filas($bd, "SELECT lv.idproducto_central, p.descripcion, lv.modo FROM log_vigilar_parado lv
                JOIN producto p ON p.idproducto = lv.idproducto_central WHERE lv.idorg = ? ORDER BY p.descripcion", array((int)$ido));
        return array('productos' => parado_org($bd, $org, $s ?: null), 'sedes' => $sedes, 'excepciones' => $ocultos,
            'reglas' => array('dias_sin_venta' => DIAS_SIN_VENTA, 'dias_parado' => DIAS_PARADO, 'dias_prueba' => DIAS_PRUEBA_VENTA));
    });

case 'trasladar':
    // lo parado de una sede va a otra que si lo vende: decision 'traslado' sin pedido (aparece en Envios a las sedes)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $c = isset($b['idproducto_central']) ? (int)$b['idproducto_central'] : 0;
        $o = isset($b['idsede_origen']) ? (int)$b['idsede_origen'] : 0;
        $d = isset($b['idsede_destino']) ? (int)$b['idsede_destino'] : 0;
        $cant = isset($b['cantidad']) && is_numeric($b['cantidad']) ? round((float)$b['cantidad'], 2) : 0;
        if ($cant <= 0) { throw new ErrorUsuario('Pon cuánto llevar.'); }
        if ($o === $d) { throw new ErrorUsuario('Elige otra sede de destino.'); }
        $token = log_token($b);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'parado', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            // dos pestanas a la vez: se bloquea la sede de origen ANTES de la primera lectura (que fija la foto de la tx),
            // asi la segunda espera y luego ve lo ya comprometido por la primera en log_bandeja_linea
            log_filas($bd, "SELECT idsede FROM log_sede WHERE idsede = ? AND idorg = ? FOR UPDATE", array($o, (int)$ido));
            // dos pestanas a la vez: se bloquea la sede de origen ANTES de la primera lectura (que fija la foto de la tx),
            // asi la segunda espera y luego ve lo ya comprometido por la primera en log_bandeja_linea
            log_filas($bd, "SELECT idsede FROM log_sede WHERE idsede = ? AND idorg = ? FOR UPDATE", array($o, (int)$ido));
            // se recalcula en el servidor: no se confia en lo que muestra la pantalla
            $fila = null;
            foreach (parado_org($bd, $org, $o) as $f) { if ($f['idproducto_central'] === $c) { $fila = $f; break; } }
            if (!$fila) { throw new ErrorUsuario('Ese producto ya no está parado en esa sede. Actualiza la página.'); }
            if ($cant > $fila['sobra'] + 0.0001) { throw new ErrorUsuario('De ' . $fila['descripcion'] . ' se pueden llevar hasta ' . $fila['sobra'] . '.'); }
            $dest = log_filas($bd, "SELECT ls.idsede FROM log_sede ls JOIN log_producto_central v ON v.idsede = ls.idsede AND v.idproducto_central = ?
                    WHERE ls.idsede = ? AND ls.idorg = ? AND ls.activo = 1", array($c, $d, (int)$ido));
            if (!$dest && $d !== (int)$org['idsede_central']) { throw new ErrorUsuario('Esa sede no tiene este producto o no participa.'); }
            log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                    idsede_origen, cantidad, idusuario) VALUES (?, 0, 0, ?, ?, 'traslado', ?, ?, ?)",
                array((int)$ido, $d, $c, $o, $cant, (int)$us));
            $res = array('ok' => true, 'producto' => $fila['descripcion']);
            log_token_cerrar($bd, 'parado', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'vigilar':
    // excepcion por producto del catalogo: auto (sin fila) | si | no
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $c = isset($b['idproducto_central']) ? (int)$b['idproducto_central'] : 0;
        $modo = isset($b['modo']) && in_array($b['modo'], array('auto', 'si', 'no'), true) ? $b['modo'] : '';
        if ($modo === '') { throw new ErrorUsuario('Opción no válida.'); }
        $org = log_org($bd, $ido);
        if (!log_filas($bd, "SELECT 1 FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ?", array($c, (int)$ido, (int)$org['idsede_central']))) {
            throw new ErrorUsuario('Producto no encontrado en el catálogo.');
        }
        if ($modo === 'auto') {
            log_exec($bd, "DELETE FROM log_vigilar_parado WHERE idproducto_central = ? AND idorg = ?", array($c, (int)$ido));
        } else {
            log_exec($bd, "INSERT INTO log_vigilar_parado (idproducto_central, idorg, modo, idusuario) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE modo = VALUES(modo), idusuario = VALUES(idusuario), fecha_hora = NOW()", array($c, (int)$ido, $modo, (int)$us));
        }
        return array('modo' => $modo);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

<?php
/**
 * Logistica F6b — Por vencer.
 * ?op=lista (central: todas las sedes; sede: solo la suya) | trasladar (central)
 * Saldo por lote en cada almacen = movimientos de lote (recepcion/traslados), pero el POS vende sin lote: el stock real
 * del almacen (producto_stock) se reparte desde el lote que vence MAS TARDE hacia atras (FEFO: lo vendido salio de lo que
 * vencia antes). Si un lote no se vendera a tiempo en su sede, se sugiere llevarlo a la sede que mas lo vende y alcanza.
 * "trasladar" crea una decision 'traslado' (sin pedido, idsolicitud 0) que aparece en Envios.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_vencimientos.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'lista':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_org($bd, $ido);
        if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
        $esCentral = (int)$org['idsede_central'] === (int)$idsede;
        if ($esCentral) { log_requiere_central($bd, $ido, $idsede, $us); } else { log_requiere_sede($bd, $ido, $idsede, $us); }
        return array('es_central' => $esCentral, 'dias_alerta' => (int)$org['dias_alerta_vencimiento'],
            'lotes' => por_vencer($bd, $org, $esCentral ? null : $idsede));
    });

case 'trasladar':
    // {idlote, idsede_origen, idsede_destino, cantidad, token} -> decision 'traslado' sin pedido (aparece en Envios)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $o = isset($b['idsede_origen']) ? (int)$b['idsede_origen'] : 0;
        $d = isset($b['idsede_destino']) ? (int)$b['idsede_destino'] : 0;
        $cant = isset($b['cantidad']) ? round((float)$b['cantidad'], 2) : 0;
        if ($cant <= 0) { throw new ErrorUsuario('Pon cuánto llevar.'); }
        if ($o === $d) { throw new ErrorUsuario('Elige otra sede de destino.'); }
        $token = log_token($b);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'vencer', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            // dos pestanas a la vez: se bloquea la sede de origen ANTES de la primera lectura (que fija la foto de la tx),
            // asi la segunda espera y luego ve lo ya comprometido por la primera en log_bandeja_linea
            log_filas($bd, "SELECT idsede FROM log_sede WHERE idsede = ? AND idorg = ? FOR UPDATE", array($o, (int)$ido));
            // se recalcula en el servidor: no se confia en lo que muestra la pantalla
            $fila = null;
            foreach (por_vencer($bd, $org, $o) as $f) { if ($f['idlote'] === (isset($b['idlote']) ? (int)$b['idlote'] : 0)) { $fila = $f; break; } }
            if (!$fila) { throw new ErrorUsuario('Ese lote ya no está por vencer en esa sede. Actualiza la página.'); }
            if ($fila['en_envios'] > 0) { throw new ErrorUsuario('Ya hay un traslado de ' . $fila['producto'] . ' desde ' . $fila['sede'] . ' en Envíos.'); }
            if ($fila['dias'] <= 0) { throw new ErrorUsuario('Ese lote ya venció: no se traslada.'); }
            if ($cant > $fila['cantidad'] + 0.0001) { throw new ErrorUsuario('De ese lote solo hay ' . $fila['cantidad'] . '.'); }
            $dest = log_filas($bd, "SELECT ls.idsede FROM log_sede ls JOIN log_producto_central v ON v.idsede = ls.idsede AND v.idproducto_central = ?
                    WHERE ls.idsede = ? AND ls.idorg = ? AND ls.activo = 1", array($fila['idproducto_central'], $d, (int)$ido));
            if (!$dest && $d !== (int)$org['idsede_central']) { throw new ErrorUsuario('Esa sede no tiene este producto o no participa.'); }
            log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                    idsede_origen, cantidad, idusuario) VALUES (?, 0, 0, ?, ?, 'traslado', ?, ?, ?)",
                array((int)$ido, $d, $fila['idproducto_central'], $o, $cant, (int)$us));
            $res = array('ok' => true, 'producto' => $fila['producto']);
            log_token_cerrar($bd, 'vencer', $token, $res);
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

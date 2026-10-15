<?php
/**
 * Logistica F2 — "Pedir a la central" (lo usa la SEDE).
 * ?op=sugerido | enviar | mis_pedidos | detalle&id= | cancelar
 * El pedido se guarda en las tablas legacy solicitud_producto / solicitud_producto_detalle (idsede_destino = central).
 * Cantidad sugerida = vende por dia x dias que cubre + stock minimo - stock actual - lo ya pedido sin recibir.
 * Todas las consultas van por indice: log_producto_central(idsede,..), producto_stock(idproducto),
 * log_consumo_dia PK(idproducto, fecha), solicitud_producto(idorg, idsede_solicita).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_consumo.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'sugerido':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_sede($bd, $ido, $idsede, $us);
        try { actualizar_consumo($bd, $org); }
        catch (Throwable $e) { error_log('logistica consumo: ' . $e->getMessage()); }   // sin resumen nuevo igual se sugiere
        $dias = (int)$org['dias_cobertura_sede'];
        return array('dias_cobertura' => $dias) + sugerido_sede($bd, $ido, $idsede, $dias, false);
    });

case 'enviar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_sede($bd, $ido, $idsede, $us);
        $b = log_body();
        $token = log_token($b);
        $items = array();
        $malas = 0;
        foreach ((isset($b['items']) && is_array($b['items']) ? $b['items'] : array()) as $it) {
            $id = is_array($it) && isset($it['idproducto']) ? (int)$it['idproducto'] : 0;
            // "1,5" = 1.5; vacio o 0 = no se pide; texto, negativo o enorme = error (antes se descartaba en silencio)
            $txt = is_array($it) && isset($it['cantidad']) && is_scalar($it['cantidad']) ? str_replace(',', '.', trim((string)$it['cantidad'])) : '';
            if ($txt === '' || (is_numeric($txt) && (float)$txt == 0)) { continue; }
            if (!is_numeric($txt) || (float)$txt < 0) { $malas++; continue; }
            $cant = round((float)$txt, 2);
            if ($cant > 99999999) { throw new ErrorUsuario('Hay una cantidad demasiado grande. Revisa el pedido.'); }
            if ($id > 0 && $cant > 0) { $items[$id] = $cant; }
        }
        if ($malas) { throw new ErrorUsuario('Hay ' . $malas . ' cantidad(es) que no son un número válido. Escríbelas solo con números (ej. 2 o 1.5).'); }
        if (!$items) { throw new ErrorUsuario('Pon la cantidad en al menos un producto.'); }
        if (count($items) > 500) { throw new ErrorUsuario('Máximo 500 productos por pedido.'); }
        // solo productos de ESTA sede que vienen del catalogo (por PK)
        $ids = array_keys($items);
        $prods = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
                IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto), 0) AS stock
                FROM producto p JOIN log_producto_central v ON v.idproducto = p.idproducto AND v.idsede = ?
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE p.idproducto IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND p.estado = 0",
            array_merge(array((int)$idsede), $ids));
        if (count($prods) !== count($ids)) { throw new ErrorUsuario('Algún producto ya no está disponible. Actualiza la página.'); }
        $nota = mb_substr(trim(isset($b['nota']) ? (string)$b['nota'] : ''), 0, 500, 'UTF-8');
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'pedido', $token, $ido)) { $bd->bd->rollback(); return $previo; }   // doble clic
            log_exec($bd, "INSERT INTO solicitud_producto (idorg, idsede_solicita, idsede_destino, fecha_solicitud, estado, nota, idusuario_solicita)
                    VALUES (?, ?, ?, NOW(), 'enviado', ?, ?)", array((int)$ido, (int)$idsede, (int)$org['idsede_central'], $nota, (int)$us));
            $idsol = (int)$bd->lastInsertId();
            foreach ($prods as $p) {
                log_exec($bd, "INSERT INTO solicitud_producto_detalle (idsolicitud_producto, tipo_producto, idproducto, descripcion,
                        cantidad_solicitada, cantidad_despachada, stock_actual, unidad_medida) VALUES (?, 'almacen', ?, ?, ?, 0, ?, ?)",
                    array($idsol, (int)$p['idproducto'], $p['descripcion'], (float)$items[(int)$p['idproducto']], round((float)$p['stock'], 2), $p['unidad']));
            }
            $res = array('idsolicitud' => $idsol, 'productos' => count($prods));
            log_token_cerrar($bd, 'pedido', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'mis_pedidos':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_sede($bd, $ido, $idsede, $us);
        return log_filas($bd, "SELECT s.idsolicitud_producto AS id, s.fecha_solicitud AS fecha, s.estado, s.nota,
                (SELECT COUNT(*) FROM solicitud_producto_detalle d WHERE d.idsolicitud_producto = s.idsolicitud_producto) AS productos
                FROM solicitud_producto s
                WHERE s.idorg = ? AND s.idsede_solicita = ?
                ORDER BY s.idsolicitud_producto DESC LIMIT 30", array((int)$ido, (int)$idsede));
    });

case 'detalle':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_sede($bd, $ido, $idsede, $us);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM solicitud_producto WHERE idsolicitud_producto = ? AND idorg = ? AND idsede_solicita = ?",
                array($id, (int)$ido, (int)$idsede))) {
            throw new ErrorUsuario('Pedido no encontrado.');
        }
        return log_filas($bd, "SELECT descripcion, cantidad_solicitada, cantidad_despachada, unidad_medida, stock_actual
                FROM solicitud_producto_detalle WHERE idsolicitud_producto = ? ORDER BY descripcion", array($id));
    });

case 'cancelar':
    // solo mientras la central no lo haya visto
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_sede($bd, $ido, $idsede, $us);
        $b = log_body();
        $n = log_exec($bd, "UPDATE solicitud_producto SET estado = 'cancelado'
                WHERE idsolicitud_producto = ? AND idorg = ? AND idsede_solicita = ? AND estado = 'enviado'",
            array(isset($b['id']) ? (int)$b['id'] : 0, (int)$ido, (int)$idsede));
        if ($n !== 1) { throw new ErrorUsuario('Ya no se puede cancelar: la central ya lo está atendiendo.'); }
        return array('cancelado' => true);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

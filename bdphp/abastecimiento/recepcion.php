<?php
/**
 * Recepción de mercadería (ABASTECIMIENTO).
 * Registra lo que REALMENTE llegó (puede diferir de la OC: cantidad distinta,
 * no llegó, reemplazo de marca, producto extra). El stock entra AQUÍ vía
 * sp_abast_aplicar_movimiento (atómico). Opcionalmente liga una `compra` para
 * cuentas por pagar por el monto realmente recibido.
 *
 * URL: ../../bdphp/abastecimiento/recepcion.php?op=N
 */

require __DIR__ . '/_bootstrap.php';

/** Recalcula estado de la OC a partir de sus ítems, sin degradar cerrada/anulada. */
function oc_recalcular_estado($bd, $idorden) {
    $idorden = intval($idorden);
    if ($idorden <= 0) { return; }
    $r = json_decode($bd->xConsulta3(
        "SELECT COUNT(*) AS n,
                SUM(estado_item IN ('completo','no_llego','reemplazado')) AS cerrados,
                SUM(cantidad_recibida > 0) AS con_recibo
         FROM orden_compra_item WHERE idorden=$idorden"), true);
    if (!$r || intval($r[0]['n']) === 0) { return; }
    $n = intval($r[0]['n']); $cerrados = intval($r[0]['cerrados']); $conRecibo = intval($r[0]['con_recibo']);
    if ($cerrados === $n)   { $estado = 'recibida'; }
    elseif ($conRecibo > 0) { $estado = 'parcial'; }
    else                    { $estado = 'enviada'; }
    $bd->bd->query("UPDATE orden_compra SET estado='$estado'
                    WHERE idorden=$idorden AND estado IN ('enviada','parcial','recibida')");
}

switch ($op) {

    case '1': // OCs pendientes de recibir (enviada / parcial)
        abast_requiere_migracion($bd);
        $sql = "SELECT oc.idorden, oc.fecha, oc.estado, oc.total,
                       pr.descripcion AS proveedor, a.descripcion AS almacen, oc.idalmacen_destino
                FROM orden_compra oc
                LEFT JOIN proveedor pr ON pr.idproveedor = oc.idproveedor
                LEFT JOIN almacen a ON a.idalmacen = oc.idalmacen_destino
                WHERE oc.idorg=$g_ido AND oc.idsede=$g_idsede
                  AND oc.estado IN ('enviada','parcial')
                ORDER BY oc.idorden DESC LIMIT 200";
        $bd->xConsulta($sql);
        break;

    case '2': // ítems de una OC con lo pendiente por recibir (para armar la recepción)
        abast_requiere_migracion($bd);
        $d = abast_body();
        $id = intval($d->idorden);
        $sql = "SELECT i.idorden_item, i.idproducto, i.idproducto_maestro, i.cantidad_pedida,
                       i.cantidad_recibida, i.punitario, i.estado_item,
                       (i.cantidad_pedida - i.cantidad_recibida) AS pendiente,
                       p.descripcion AS producto
                FROM orden_compra_item i
                LEFT JOIN producto p ON p.idproducto = i.idproducto
                WHERE i.idorden=$id
                ORDER BY i.idorden_item";
        $bd->xConsulta($sql);
        break;

    case '3': // GUARDAR recepción: entra stock por lo recibido, actualiza OC, liga compra opcional
        abast_requiere_migracion($bd);
        $d = abast_body();
        $idorden     = isset($d->idorden) && $d->idorden ? intval($d->idorden) : 0;
        $idalmacen   = intval($d->idalmacen);
        $comprobante = abast_esc($bd, isset($d->comprobante) ? $d->comprobante : '');
        $nota        = abast_esc($bd, isset($d->nota) ? $d->nota : '');
        $items       = isset($d->items) ? $d->items : array();
        $crearCompra = !empty($d->crear_compra);
        $idtipoPago  = isset($d->idtipo_pago) ? intval($d->idtipo_pago) : 0;
        $idproveedor = isset($d->idproveedor) ? intval($d->idproveedor) : 0;

        if ($idalmacen <= 0) {
            abast_json(array('success' => false, 'error' => 'Indique el almacén de recepción'));
            break;
        }
        if (empty($items)) {
            abast_json(array('success' => false, 'error' => 'No hay ítems recibidos'));
            break;
        }

        $ordenSql = $idorden > 0 ? $idorden : 'NULL';

        $bd->bd->begin_transaction();
        try {
            // cabecera de recepción
            $bd->bd->query("INSERT INTO recepcion
                    (idorden, idorg, idsede, idalmacen, fecha, idusuario, comprobante, nota, estado, fecha_hora)
                    VALUES ($ordenSql, $g_ido, $g_idsede, $idalmacen, NOW(), $g_us,
                            '$comprobante', '$nota', 0, NOW())");
            if ($bd->bd->error) { throw new Exception($bd->bd->error); }
            $idrecepcion = $bd->bd->insert_id;

            $totalRecibido = 0;

            foreach ($items as $it) {
                $idp    = intval($it->idproducto);
                $cant   = floatval($it->cantidad_recibida);
                if ($idp <= 0 || $cant <= 0) { continue; }   // 0 recibido = no ingresa stock
                $pu     = floatval($it->punitario);
                $idm    = isset($it->idproducto_maestro) && $it->idproducto_maestro ? intval($it->idproducto_maestro) : 'NULL';
                $origen = isset($it->origen) ? $it->origen : 'pedido';
                if (!in_array($origen, array('pedido','extra','reemplazo'), true)) { $origen = 'pedido'; }
                $ordenItem = isset($it->idorden_item) && $it->idorden_item ? intval($it->idorden_item) : 0;
                $reempl    = isset($it->idorden_item_reemplazado) && $it->idorden_item_reemplazado ? intval($it->idorden_item_reemplazado) : 0;

                // línea de recepción
                $oiSql = $ordenItem > 0 ? $ordenItem : 'NULL';
                $reSql = $reempl > 0 ? $reempl : 'NULL';
                $bd->bd->query("INSERT INTO recepcion_item
                        (idrecepcion, idorden_item, idproducto, idproducto_maestro,
                         cantidad_recibida, punitario, origen, idorden_item_reemplazado, nota)
                        VALUES ($idrecepcion, $oiSql, $idp, $idm, $cant, $pu, '$origen', $reSql, '')");
                if ($bd->bd->error) { throw new Exception($bd->bd->error); }

                // stock atómico (entrada)
                $mov = abast_stock_mov($bd, $g_ido, $g_idsede, $idalmacen, $idp, $cant, $pu,
                                       'R' . $idrecepcion, $comprobante, $g_us);
                if (!$mov['ok']) { throw new Exception($mov['error']); }

                $totalRecibido += $cant * $pu;

                // acumular recibido en el ítem pedido y recalcular su estado
                if ($ordenItem > 0) {
                    // estado_item PRIMERO (usa el valor viejo), luego acumula: en MySQL
                    // una asignación posterior ve el valor ya actualizado de la anterior.
                    $bd->bd->query("UPDATE orden_compra_item
                            SET estado_item = IF(cantidad_recibida + $cant >= cantidad_pedida,
                                                 'completo', 'parcial'),
                                cantidad_recibida = cantidad_recibida + $cant
                            WHERE idorden_item = $ordenItem");
                    if ($bd->bd->error) { throw new Exception($bd->bd->error); }
                }
                // marcar el ítem original como reemplazado
                if ($origen === 'reemplazo' && $reempl > 0) {
                    $bd->bd->query("UPDATE orden_compra_item SET estado_item='reemplazado'
                                    WHERE idorden_item=$reempl");
                }
            }

            // ítems 'no_llego': marcados explícitamente por el receptor (cantidad 0)
            foreach ($items as $it) {
                $cant = floatval($it->cantidad_recibida);
                $ordenItem = isset($it->idorden_item) && $it->idorden_item ? intval($it->idorden_item) : 0;
                if ($cant <= 0 && $ordenItem > 0 && !empty($it->no_llego)) {
                    $bd->bd->query("UPDATE orden_compra_item SET estado_item='no_llego'
                                    WHERE idorden_item=$ordenItem AND cantidad_recibida=0");
                }
            }

            if ($idorden > 0) { oc_recalcular_estado($bd, $idorden); }

            // liga comprobante a cuentas por pagar (compra) — opcional
            $idcompra = 0;
            if ($crearCompra && $totalRecibido > 0) {
                $tp = $idtipoPago > 0 ? $idtipoPago : 'NULL';
                $prov = $idproveedor > 0 ? $idproveedor : 'NULL';
                $totalStr = number_format($totalRecibido, 2, '.', '');
                $bd->bd->query("INSERT INTO compra
                        (idorg, idsede, idtipo_pago, idalmacen, f_compra, f_registro,
                         a_pagar, total, idproveedor, nota_de_compra, pagado, estado, idusuario, fecha_hora)
                        VALUES ($g_ido, $g_idsede, $tp, $idalmacen,
                         DATE_FORMAT(NOW(),'%d/%m/%Y'), DATE_FORMAT(NOW(),'%d/%m/%Y %H:%i'),
                         '$totalStr', '$totalStr', $prov, '$comprobante', 0, 0, $g_us, NOW())");
                if ($bd->bd->error) { throw new Exception($bd->bd->error); }
                $idcompra = $bd->bd->insert_id;

                foreach ($items as $it) {
                    $idp  = intval($it->idproducto);
                    $cant = floatval($it->cantidad_recibida);
                    if ($idp <= 0 || $cant <= 0) { continue; }
                    $pu = floatval($it->punitario);
                    $pt = number_format($cant * $pu, 2, '.', '');
                    $bd->bd->query("INSERT INTO compra_items
                            (idcompra, idproducto, cantidad, punitario, ptotal, estado)
                            VALUES ($idcompra, $idp, $cant, $pu, '$pt', 0)");
                    if ($bd->bd->error) { throw new Exception($bd->bd->error); }
                }
                $bd->bd->query("UPDATE recepcion SET idcompra=$idcompra WHERE idrecepcion=$idrecepcion");
            }

            $bd->bd->commit();
            abast_json(array('success' => true, 'idrecepcion' => $idrecepcion,
                             'total_recibido' => $totalRecibido, 'idcompra' => $idcompra));
        } catch (Exception $e) {
            $bd->bd->rollback();
            abast_json(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    case '4': // historial de recepciones de la sede
        abast_requiere_migracion($bd);
        $sql = "SELECT r.idrecepcion, r.idorden, r.fecha, r.comprobante, r.idcompra, r.nota,
                       a.descripcion AS almacen, u.usuario,
                       (SELECT COUNT(*) FROM recepcion_item ri WHERE ri.idrecepcion=r.idrecepcion) AS items
                FROM recepcion r
                LEFT JOIN almacen a ON a.idalmacen = r.idalmacen
                LEFT JOIN usuario u ON u.idusuario = r.idusuario
                WHERE r.idorg=$g_ido AND r.idsede=$g_idsede
                ORDER BY r.idrecepcion DESC LIMIT 200";
        $bd->xConsulta($sql);
        break;

    case '5': // detalle de una recepción
        abast_requiere_migracion($bd);
        $d = abast_body();
        $id = intval($d->idrecepcion);
        $cab = json_decode($bd->xConsulta3(
            "SELECT r.*, a.descripcion AS almacen, u.usuario
             FROM recepcion r
             LEFT JOIN almacen a ON a.idalmacen = r.idalmacen
             LEFT JOIN usuario u ON u.idusuario = r.idusuario
             WHERE r.idrecepcion=$id AND r.idorg=$g_ido AND r.idsede=$g_idsede LIMIT 1"), true);
        $items = json_decode($bd->xConsulta3(
            "SELECT ri.*, p.descripcion AS producto
             FROM recepcion_item ri
             LEFT JOIN producto p ON p.idproducto = ri.idproducto
             WHERE ri.idrecepcion=$id"), true);
        abast_json(array('success' => true, 'recepcion' => $cab ? $cab[0] : null,
                         'items' => $items ? $items : array()));
        break;

    default:
        abast_json(array('success' => false, 'error' => 'Operación no válida'));
        break;
}

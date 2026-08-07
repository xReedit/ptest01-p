<?php
/**
 * Órdenes de Compra (ABASTECIMIENTO).
 * La OC es la INTENCIÓN enviada al proveedor. El stock NO entra aquí (entra por
 * recepcion.php). Estados: borrador -> enviada -> parcial -> recibida -> cerrada
 * | anulada. Solo se edita en 'borrador'.
 *
 * URL: ../../bdphp/abastecimiento/orden_compra.php?op=N
 */

require __DIR__ . '/_bootstrap.php';

switch ($op) {

    case '1': // listar OCs de la sede (filtro opcional por estado)
        abast_requiere_migracion($bd);
        $estado = isset($_GET['estado']) ? abast_esc($bd, $_GET['estado']) : '';
        $where = "oc.idorg=$g_ido AND oc.idsede=$g_idsede";
        if ($estado !== '') { $where .= " AND oc.estado='$estado'"; }
        $sql = "SELECT oc.idorden, oc.fecha, oc.estado, oc.total, oc.nota,
                       pr.descripcion AS proveedor, a.descripcion AS almacen,
                       (SELECT COUNT(*) FROM orden_compra_item i WHERE i.idorden=oc.idorden) AS items
                FROM orden_compra oc
                LEFT JOIN proveedor pr ON pr.idproveedor = oc.idproveedor
                LEFT JOIN almacen a ON a.idalmacen = oc.idalmacen_destino
                WHERE $where
                ORDER BY oc.idorden DESC LIMIT 200";
        $bd->xConsulta($sql);
        break;

    case '2': // detalle: cabecera + ítems
        abast_requiere_migracion($bd);
        $d = abast_body();
        $id = intval($d->idorden);
        $sql = "SELECT oc.*, pr.descripcion AS proveedor, a.descripcion AS almacen
                FROM orden_compra oc
                LEFT JOIN proveedor pr ON pr.idproveedor = oc.idproveedor
                LEFT JOIN almacen a ON a.idalmacen = oc.idalmacen_destino
                WHERE oc.idorden=$id AND oc.idorg=$g_ido AND oc.idsede=$g_idsede LIMIT 1";
        $cab = json_decode($bd->xConsulta3($sql), true);
        $sql2 = "SELECT i.*, p.descripcion AS producto
                 FROM orden_compra_item i
                 LEFT JOIN producto p ON p.idproducto = i.idproducto
                 WHERE i.idorden=$id";
        $items = json_decode($bd->xConsulta3($sql2), true);
        abast_json(array('success' => true, 'orden' => $cab ? $cab[0] : null,
                         'items' => $items ? $items : array()));
        break;

    case '3': // guardar (insert nueva, o update si sigue en borrador)
        abast_requiere_migracion($bd);
        $d = abast_body();
        $id          = isset($d->idorden) ? intval($d->idorden) : 0;
        $idproveedor = isset($d->idproveedor) ? intval($d->idproveedor) : 0;
        $idalmacen   = isset($d->idalmacen_destino) ? intval($d->idalmacen_destino) : 0;
        $nota        = abast_esc($bd, isset($d->nota) ? $d->nota : '');
        $items       = isset($d->items) ? $d->items : array();

        if ($idalmacen <= 0) {
            abast_json(array('success' => false, 'error' => 'Seleccione almacén destino'));
            break;
        }
        if (empty($items)) {
            abast_json(array('success' => false, 'error' => 'Agregue al menos un ítem'));
            break;
        }

        $total = 0;
        foreach ($items as $it) {
            $total += floatval($it->cantidad_pedida) * floatval($it->punitario);
        }
        $provSql = $idproveedor > 0 ? $idproveedor : 'NULL';

        $bd->bd->begin_transaction();
        try {
            if ($id > 0) {
                // solo editable en borrador
                $est = $bd->xDevolverUnDato("SELECT estado FROM orden_compra
                        WHERE idorden=$id AND idorg=$g_ido AND idsede=$g_idsede LIMIT 1");
                if ($est !== 'borrador') {
                    throw new Exception('Solo se puede editar una OC en borrador');
                }
                $bd->bd->query("UPDATE orden_compra SET idproveedor=$provSql,
                        idalmacen_destino=$idalmacen, total=$total, nota='$nota'
                        WHERE idorden=$id");
                $bd->bd->query("DELETE FROM orden_compra_item WHERE idorden=$id");
            } else {
                $sql = "INSERT INTO orden_compra
                        (idorg, idsede, idproveedor, idalmacen_destino, fecha, estado,
                         total, idusuario, nota, fecha_hora)
                        VALUES ($g_ido, $g_idsede, $provSql, $idalmacen, NOW(), 'borrador',
                         $total, $g_us, '$nota', NOW())";
                if (!$bd->bd->query($sql)) { throw new Exception($bd->bd->error); }
                $id = $bd->bd->insert_id;
            }

            foreach ($items as $it) {
                $idp   = intval($it->idproducto);
                $idm   = isset($it->idproducto_maestro) && $it->idproducto_maestro ? intval($it->idproducto_maestro) : 'NULL';
                $cant  = floatval($it->cantidad_pedida);
                $pu    = floatval($it->punitario);
                if ($idp <= 0 || $cant <= 0) { continue; }
                $bd->bd->query("INSERT INTO orden_compra_item
                        (idorden, idproducto, idproducto_maestro, cantidad_pedida,
                         cantidad_recibida, punitario, estado_item)
                        VALUES ($id, $idp, $idm, $cant, 0, $pu, 'pendiente')");
                if ($bd->bd->error) { throw new Exception($bd->bd->error); }
            }

            $bd->bd->commit();
            abast_json(array('success' => true, 'idorden' => $id, 'total' => $total));
        } catch (Exception $e) {
            $bd->bd->rollback();
            abast_json(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    case '4': // cambiar estado: enviar / cerrar / anular
        abast_requiere_migracion($bd);
        $d = abast_body();
        $id     = intval($d->idorden);
        $nuevo  = abast_esc($bd, isset($d->estado) ? $d->estado : '');
        $validos = array('enviada', 'cerrada', 'anulada');
        if (!in_array($nuevo, $validos, true)) {
            abast_json(array('success' => false, 'error' => 'Estado no permitido'));
            break;
        }
        $actual = $bd->xDevolverUnDato("SELECT estado FROM orden_compra
                    WHERE idorden=$id AND idorg=$g_ido AND idsede=$g_idsede LIMIT 1");
        if ($actual === null) {
            abast_json(array('success' => false, 'error' => 'OC no encontrada'));
            break;
        }
        // transiciones permitidas
        $ok = ($nuevo === 'enviada'  && $actual === 'borrador')
           || ($nuevo === 'anulada'  && in_array($actual, array('borrador','enviada','parcial'), true))
           || ($nuevo === 'cerrada'  && in_array($actual, array('parcial','recibida'), true));
        if (!$ok) {
            abast_json(array('success' => false, 'error' => "No se puede pasar de '$actual' a '$nuevo'"));
            break;
        }
        $bd->bd->query("UPDATE orden_compra SET estado='$nuevo' WHERE idorden=$id");
        abast_json(array('success' => true, 'estado' => $nuevo));
        break;

    default:
        abast_json(array('success' => false, 'error' => 'Operación no válida'));
        break;
}

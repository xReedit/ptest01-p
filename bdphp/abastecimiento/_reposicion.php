<?php
/**
 * Reposicion automatica (migracion logistica/022, opcional por empresa). Lo llama bandeja.php al abrir.
 * Por cada sede activa: productos con stock minimo puesto, stock <= minimo y sin pedido abierto -> un pedido
 * 'enviado' sin usuario y con nota REPO_NOTA. Mismo calculo que "Pedir a la central" (sugerido_sede).
 * Incluido despues de _logistica.php y _consumo.php.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo

const REPO_NOTA = 'Automático: quedó en su stock mínimo';

/** Devuelve cuantos pedidos creo. Revisa como maximo cada 30 min por empresa (el UPDATE toma el turno: 2 pestañas no duplican). */
function reponer_auto($bd, $org) {
    $ido = (int)$org['idorg'];
    if (log_exec($bd, "UPDATE org_logistica SET reposicion_ultima = NOW() WHERE idorg = ? AND reposicion_auto = 1
            AND (reposicion_ultima IS NULL OR reposicion_ultima < NOW() - INTERVAL 30 MINUTE)", array($ido)) !== 1) {
        return 0;
    }
    actualizar_consumo($bd, $org);
    $n = 0;
    foreach (log_filas($bd, "SELECT idsede, IFNULL(dias_cobertura, ?) AS dias FROM log_sede WHERE idorg = ? AND activo = 1 AND idsede <> ?",
            array((int)$org['dias_cobertura'], $ido, (int)$org['idsede_central'])) as $s) {
        $items = array_filter(sugerido_sede($bd, $ido, $s['idsede'], (int)$s['dias'], true)['productos'],
            function ($p) { return $p['en_camino'] == 0 && $p['sugerido'] > 0; });
        if (!$items) { continue; }
        $bd->bd->begin_transaction();
        try {
            log_exec($bd, "INSERT INTO solicitud_producto (idorg, idsede_solicita, idsede_destino, fecha_solicitud, estado, nota)
                    VALUES (?, ?, ?, NOW(), 'enviado', ?)", array($ido, (int)$s['idsede'], (int)$org['idsede_central'], REPO_NOTA));
            $idsol = (int)$bd->lastInsertId();
            foreach ($items as $p) {
                log_exec($bd, "INSERT INTO solicitud_producto_detalle (idsolicitud_producto, tipo_producto, idproducto, descripcion,
                        cantidad_solicitada, cantidad_despachada, stock_actual, unidad_medida) VALUES (?, 'almacen', ?, ?, ?, 0, ?, ?)",
                    array($idsol, $p['idproducto'], $p['descripcion'], $p['sugerido'], $p['stock'], $p['unidad']));
            }
            $bd->bd->commit();
            $n++;
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
    }
    return $n;
}

<?php
/**
 * Logistica — linea de tiempo de cualquier proceso: ?op=pedido|traslado|oc|conteo&id=
 * La central ve todo lo de su empresa; una sede solo lo suyo (su pedido, envios que salen o llegan a ella, sus conteos).
 * Por PK: solicitud_producto, log_traslado, orden_compra, inventario_conteo (el resto en _historia.php).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_historia.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

log_responder(function () use ($bd, $ido, $idsede, $us, $op) {
    $org = log_org($bd, $ido);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    log_requiere_permiso($bd, $us);
    $central = (int)$org['idsede_central'] === (int)$idsede;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $no = function () { throw new ErrorUsuario('No encontrado.'); };
    switch ($op) {
    case 'pedido':
        $s = log_filas($bd, "SELECT * FROM solicitud_producto WHERE idsolicitud_producto = ? AND idorg = ?", array($id, (int)$ido));
        if (!$s || ($central ? (int)$s[0]['idsede_destino'] !== (int)$idsede : (int)$s[0]['idsede_solicita'] !== (int)$idsede)) { $no(); }
        return historia_pedido($bd, $s[0]);
    case 'traslado':
        $t = log_filas($bd, "SELECT idsede_origen, idsede_destino FROM log_traslado WHERE idtraslado = ? AND idorg = ?", array($id, (int)$ido));
        if (!$t || (!$central && !in_array((int)$idsede, array((int)$t[0]['idsede_origen'], (int)$t[0]['idsede_destino']), true))) { $no(); }
        return historia_traslado($bd, $id);
    case 'oc':
        $oc = log_filas($bd, "SELECT * FROM orden_compra WHERE idorden = ? AND idorg = ? AND idsede = ?", array($id, (int)$ido, (int)$org['idsede_central']));
        if (!$oc || !$central) { $no(); }
        return historia_oc($bd, $oc[0]);
    case 'conteo':
        $c = log_filas($bd, "SELECT * FROM inventario_conteo WHERE idinventario_conteo = ? AND idorg = ?", array($id, (int)$ido));
        if (!$c || (!$central && (int)$c[0]['idsede'] !== (int)$idsede)) { $no(); }
        return historia_conteo($bd, $c[0]);
    default:
        throw new ErrorUsuario('Operación no válida.');
    }
});

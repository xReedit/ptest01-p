<?php
/**
 * Bootstrap del módulo ABASTECIMIENTO — incluido por orden_compra.php / recepcion.php.
 * NO toca ningún log_*.php. Mismo patrón que bdphp/produccion/api.php.
 *
 * Expone: $bd (xManejoBD), $op, $g_ido/$g_idsede/$g_us, helpers abast_*.
 */

require_once __DIR__ . '/../SecurityGuard.php';
SecurityGuard::verificarAcceso();

header('Content-Type: application/json;charset=utf-8');

include __DIR__ . '/../ManejoBD.php';
$bd = new xManejoBD('restobar');

if (isset($_POST['op'])) {
    $op = $_POST['op'];
} elseif (isset($_GET['op'])) {
    $op = $_GET['op'];
} else {
    $postBody = json_decode(file_get_contents('php://input'));
    $op = isset($postBody->op) ? $postBody->op : null;
}

$g_ido    = isset($_SESSION['ido']) ? intval($_SESSION['ido']) : 0;
$g_idsede = isset($_SESSION['idsede']) ? intval($_SESSION['idsede']) : 0;
$g_us     = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : 0;

function abast_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function abast_body() {
    $obj = json_decode(file_get_contents('php://input'));
    return $obj ? $obj : new stdClass();
}

function abast_esc($bd, $str) {
    return $bd->bd->real_escape_string($str);
}

/** ¿Las migraciones 011-014 (tablas + SP) están aplicadas? */
function abast_listo($bd) {
    $sql = "SELECT
              (SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema=DATABASE() AND table_name IN
                 ('orden_compra','orden_compra_item','recepcion','recepcion_item')) AS t,
              (SELECT COUNT(*) FROM information_schema.routines
                 WHERE routine_schema=DATABASE()
                   AND routine_name='sp_abast_aplicar_movimiento') AS sp";
    $r = json_decode($bd->xConsulta3($sql), true);
    return $r && intval($r[0]['t']) === 4 && intval($r[0]['sp']) === 1;
}

/** Corta la petición si el módulo no está migrado. */
function abast_requiere_migracion($bd) {
    if (!abast_listo($bd)) {
        abast_json(array('success' => false,
            'error' => 'Ejecute migraciones/aplicar_abastecimiento.php?confirmar=1 (011-014)'));
        exit;
    }
}

/**
 * Único punto de mutación de stock: delega en sp_abast_aplicar_movimiento
 * (FOR UPDATE + valida negativo + costo promedio + kardex, atómico).
 * Signo: cantidad > 0 entrada, < 0 salida. El caller controla la transacción.
 */
function abast_stock_mov($bd, $idorg, $idsede, $idalmacen, $idproducto, $cantidad, $costo, $idoperacion, $documento, $idusuario) {
    $idorg      = intval($idorg);
    $idsede     = intval($idsede);
    $idalmacen  = intval($idalmacen);
    $idproducto = intval($idproducto);
    $cantidad   = floatval($cantidad);
    $costo      = floatval($costo);
    $idusuario  = intval($idusuario);
    $idoperacion = "'" . abast_esc($bd, $idoperacion) . "'";
    $documento   = "'" . abast_esc($bd, $documento) . "'";

    $sql = "CALL sp_abast_aplicar_movimiento($idorg,$idsede,$idalmacen,$idproducto,"
         . "$cantidad,$costo,$idoperacion,$documento,$idusuario)";
    if (!$bd->bd->query($sql)) {
        return array('ok' => false, 'error' => $bd->bd->error);
    }
    // El SP no devuelve result sets, pero CALL puede dejar el status pendiente.
    while ($bd->bd->more_results() && $bd->bd->next_result()) { /* drain */ }
    return array('ok' => true);
}

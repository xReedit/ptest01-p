<?php
/**
 * log_pos_op.php
 *
 * Proxy del POS multi-sede. Valida que el usuario tenga el permiso A27
 * ("Permitir ventas en otra sede") y que la sede destino pertenezca a la
 * misma organizacion. Aplica el override de sede EN MEMORIA (no se persiste
 * en la sesion) y delega al archivo PHP existente (log.php / log_001.php /
 * log_componentes.php / log_010.php) sin duplicar codigo.
 *
 * Frontend: usa app/view/op_sede.service.js (posAjax) que enruta aqui
 * cuando hay override activo en la pestaña.
 *
 * Parametros esperados (POST):
 *   _op_dest  : 'log' | 'log_001' | 'log_componentes' | 'log_010'
 *   _op_org   : id de organizacion (debe ser la del usuario)
 *   _op_sede  : id de sede destino (debe ser de la misma org y estar activa)
 *   op        : (opcional) si el archivo destino usa $_GET['op'], se copia
 *   ...       : payload normal del endpoint destino
 *
 * Garantia: si algun include hace exit/die, el destructor de
 * OpSedeSessionRestore restaura $_SESSION antes de que PHP cierre la sesion.
 */

require_once __DIR__ . '/SecurityGuard.php';
SecurityGuard::verificarAcceso();

include __DIR__ . '/ManejoBD.php';
$bd = new xManejoBD("restobar");

require_once __DIR__ . '/_sede_op.php';

// Peticiones JSON (axios/httpFecht): php://input solo se lee una vez.
// Guardamos el body y extraemos _op_* para el proxy antes del include destino.
$GLOBALS['__op_sede_raw_body'] = null;
if (empty($_POST['_op_dest'])) {
    $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $GLOBALS['__op_sede_raw_body'] = $raw;
            $j = json_decode($raw, true);
            if (is_array($j) && !empty($j['_op_dest'])) {
                $_POST['_op_dest'] = $j['_op_dest'];
                $_POST['_op_org']  = isset($j['_op_org'])  ? $j['_op_org']  : 0;
                $_POST['_op_sede'] = isset($j['_op_sede']) ? $j['_op_sede'] : 0;
                if (isset($j['op']) && !isset($_GET['op'])) {
                    $_GET['op'] = $j['op'];
                }
            }
        }
    }
}

// Validar override y obtener sede destino.
list($op_org, $op_sede) = require_op_sede();

// Snapshot de $_SESSION ANTES del override. La restauracion corre en shutdown
// (garantizada antes del cierre automatico de la sesion por PHP), de modo que
// el override no se persiste en la cookie de sesion ni afecta otras pestañas.
$GLOBALS['__op_sede_snapshot'] = $_SESSION;
$GLOBALS['__op_sede_done']     = false;
register_shutdown_function(function () {
    if (!empty($GLOBALS['__op_sede_done'])) { return; }
    $GLOBALS['__op_sede_done'] = true;
    if (session_status() === PHP_SESSION_ACTIVE && isset($GLOBALS['__op_sede_snapshot'])) {
        $_SESSION = $GLOBALS['__op_sede_snapshot'];
    }
});

// Auditoria (idempotente por dia).
log_venta_otra_sede_si_corresponde($op_org, $op_sede);

// Override en memoria. Sera revertido por el shutdown function de arriba.
$_SESSION['ido']    = $op_org;
$_SESSION['idsede'] = $op_sede;

// Destino: archivo PHP existente que se reutiliza tal cual.
$dest = isset($_POST['_op_dest']) ? $_POST['_op_dest']
       : (isset($_GET['_op_dest']) ? $_GET['_op_dest'] : '');
$destinos_validos = array('log', 'log_001', 'log_componentes', 'log_010');

if (!in_array($dest, $destinos_validos, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'error'   => 'ERR_OP_SEDE: destino invalido'
    ));
    exit;
}

// Algunos destinos usan $_GET['op'], otros leen $_POST. Si el cliente envia
// el op en POST, lo copiamos a $_GET para no perder ramificacion del switch.
if (!isset($_GET['op'])) {
    if (isset($_POST['op']))      { $_GET['op'] = $_POST['op']; }
}

include __DIR__ . '/' . $dest . '.php';

// Camino feliz: restaurar inmediatamente para que la sesion no quede con el
// override en memoria si algo posterior la lee. El shutdown function evita
// fugas en caso de exit/die dentro del include.
if (empty($GLOBALS['__op_sede_done']) && isset($GLOBALS['__op_sede_snapshot'])) {
    $GLOBALS['__op_sede_done'] = true;
    $_SESSION = $GLOBALS['__op_sede_snapshot'];
}

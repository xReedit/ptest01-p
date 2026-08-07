<?php
/**
 * _sede_op.php
 *
 * Helper de "sede operativa" para el POS multi-sede.
 *
 * Un usuario con el permiso A27 ("Permitir ventas en otra sede") puede operar
 * desde una pestaña del POS en una sede distinta a su sede de pertenencia,
 * dentro de la misma organizacion. El override viaja como parametros POST
 * (_op_org, _op_sede) en cada request del POS — no muta la sesion persistida,
 * por lo que distintas pestañas pueden tener distintas sedes a la vez.
 *
 * Uso: include desde log_pos_op.php (no se incluye desde otros archivos para
 * no afectar flujos existentes).
 */

if (!defined('SEDE_OP_HELPER_LOADED')) {
    define('SEDE_OP_HELPER_LOADED', true);

    /**
     * Resuelve [idorg, idsede, is_override] desde la sesion + parametros POST.
     * Si el override es invalido o no aplica, devuelve la sede de la sesion.
     */
    function get_op_sede() {
        $sess_org  = isset($_SESSION['ido'])    ? (int)$_SESSION['ido']    : 0;
        $sess_sede = isset($_SESSION['idsede']) ? (int)$_SESSION['idsede'] : 0;

        // Aceptamos override desde POST o GET (el prefilter inyecta donde corresponda).
        $req_org   = isset($_POST['_op_org'])   ? (int)$_POST['_op_org']
                   : (isset($_GET['_op_org'])   ? (int)$_GET['_op_org']   : 0);
        $req_sede  = isset($_POST['_op_sede'])  ? (int)$_POST['_op_sede']
                   : (isset($_GET['_op_sede'])  ? (int)$_GET['_op_sede']  : 0);

        // Sin override solicitado o coincide con la sede actual: nada que hacer.
        if ($req_sede <= 0 || $req_sede === $sess_sede) {
            return array($sess_org, $sess_sede, false);
        }

        // Sin permiso: se ignora el override (no se filtra por seguridad,
        // el caller decide si exigirlo via require_op_sede()).
        // Permiso = tiene A27  O  es admin (rol==1). Esto alinea el backend con el
        // frontend (opSede.tienePermiso / x-comp-pos-sede), que ya trata rol==1 como
        // habilitado. Sin este bypass, un admin sin A27 podia clickear pero recibia 403.
        $rol = isset($_SESSION['rol']) ? (int)$_SESSION['rol'] : 0;
        $acc = isset($_SESSION['acc']) ? $_SESSION['acc'] : '';
        $tiene_a27 = (strpos($acc, 'A27,') !== false || strpos($acc, ',A27') !== false || $acc === 'A27');
        if ($rol !== 1 && !$tiene_a27) {
            return array($sess_org, $sess_sede, false);
        }

        // La sede solicitada debe pertenecer a la MISMA organizacion del usuario.
        // No se permite saltar entre orgs.
        global $bd;
        $ok = $bd->xDevolverUnDato(
            "SELECT 1 AS d1 FROM sede WHERE idsede=" . $req_sede .
            " AND idorg=" . $sess_org . " AND estado=0 LIMIT 1"
        );
        if (!$ok) {
            return array($sess_org, $sess_sede, false);
        }

        return array($sess_org, $req_sede, true);
    }

    /**
     * Exige override valido y termina la request con 403 si no lo hay.
     * Devuelve [idorg, idsede].
     */
    function require_op_sede() {
        $resolved = get_op_sede();
        if (!$resolved[2]) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'error'   => 'ERR_OP_SEDE: override de sede invalido o sin permiso'
            ));
            exit;
        }
        return array($resolved[0], $resolved[1]);
    }

    /**
     * Inserta una fila en log_venta_otra_sede si todavia no hay una para el
     * par (idusuario, idsede_op, fecha). Idempotente por dia.
     */
    function log_venta_otra_sede_si_corresponde($idorg, $idsede_op) {
        global $bd;
        $idu     = isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : 0;
        $orig    = isset($_SESSION['idsede'])    ? (int)$_SESSION['idsede']    : 0;
        $fecha   = date('Y-m-d');
        if ($idu <= 0 || $orig <= 0 || $idsede_op <= 0) {
            return;
        }
        $existe = $bd->xDevolverUnDato(
            "SELECT 1 AS d1 FROM log_venta_otra_sede " .
            "WHERE idusuario=" . $idu . " AND idsede_op=" . $idsede_op .
            " AND fecha='" . $fecha . "' LIMIT 1"
        );
        if ($existe) {
            return;
        }
        $bd->xConsulta(
            "INSERT INTO log_venta_otra_sede " .
            "(idusuario, idorg, idsede_origen, idsede_op, fecha_hora, fecha) " .
            "VALUES (" . $idu . ", " . (int)$idorg . ", " . $orig . ", " . $idsede_op .
            ", NOW(), '" . $fecha . "')"
        );
    }

    /**
     * Restaurador de $_SESSION via destructor. Garantiza que, aunque el include
     * incluido haga exit/die, los valores originales se restauren antes de que
     * PHP cierre la sesion. Si la restauracion no fuese posible (sesion ya
     * cerrada), el aborto se hace via session_abort.
     */
    class OpSedeSessionRestore {
        public $orig_org;
        public $orig_sede;
        public function __destruct() {
            if (session_status() === PHP_SESSION_ACTIVE) {
                if ($this->orig_org !== null)  { $_SESSION['ido']    = $this->orig_org;  }
                if ($this->orig_sede !== null) { $_SESSION['idsede'] = $this->orig_sede; }
            }
        }
    }
}

<?php
// Sedes que atiende un usuario call center (A27) o un repartidor. Plan: plan/REPARTIDOR-MULTISEDE-PLAN.md
// Regla: sede de casa ∪ tabla (usuario_sede_atiende / repartidor_sede_atiende), solo sedes operables de la org.
// La casa no se guarda en la tabla. Se lee siempre de la BD: una sede que se bloquea deja de contar al instante.
// Sin salida: se incluye con require_once desde los log_*.php.
if (!function_exists('xSedesOperablesOrg')) {

// Operable = misma regla que log_componentes.php op=1 solo_operables (estado=0, ni bloqueada ni de baja).
function xSedesOperablesOrg($bd, $idorg) {
	$bd->prepare("SELECT s.idsede, s.nombre, s.ciudad FROM sede s
		WHERE s.idorg = ? AND s.estado = 0
		  AND NOT EXISTS (SELECT 1 FROM sede_estado se
				WHERE se.idsede_estado = (SELECT MAX(x.idsede_estado) FROM sede_estado x WHERE x.idsede = s.idsede)
				  AND (IFNULL(se.is_bloqueado, '0') <> '0' OR IFNULL(se.is_baja, '0') <> '0'))
		ORDER BY s.ciudad, s.nombre");
	$bd->execute(array((int)$idorg));
	$rows = $bd->fetchAll();
	foreach ($rows as &$r) { $r['idsede'] = (int)$r['idsede']; }
	unset($r);
	return $rows;
}

// casa ∪ filas de la tabla, ∩ operables de la org. Orden: el de xSedesOperablesOrg.
function _xSedesAtiendeLista($bd, $sqlTabla, $id, $casa, $idorg) {
	$bd->prepare($sqlTabla);
	$bd->execute(array((int)$id));
	$marcadas = array((int)$casa => true);
	foreach ($bd->fetchAll() as $r) { $marcadas[(int)$r['idsede']] = true; }
	$out = array();
	foreach (xSedesOperablesOrg($bd, $idorg) as $s) {
		if (isset($marcadas[$s['idsede']])) { $out[] = $s['idsede']; }
	}
	return $out;
}

// Admin (rol 1): todas las operables de su org, como hoy.
function xSedesAtiendeUsuario($bd, $idusuario) {
	$bd->prepare("SELECT idorg, idsede, rol FROM usuario WHERE idusuario = ?");
	$bd->execute(array((int)$idusuario));
	$u = $bd->fetchAll();
	if (!$u) { return array(); }
	$u = $u[0];
	if ((int)$u['rol'] === 1) {
		return array_map(function ($s) { return $s['idsede']; }, xSedesOperablesOrg($bd, $u['idorg']));
	}
	return _xSedesAtiendeLista($bd, "SELECT idsede FROM usuario_sede_atiende WHERE idusuario = ?",
		$idusuario, $u['idsede'], $u['idorg']);
}

// Repartidor global (sin sede de casa): [] (no es de ninguna sede).
function xSedesAtiendeRepartidor($bd, $idrepartidor) {
	$bd->prepare("SELECT r.idsede_suscrito, s.idorg FROM repartidor r JOIN sede s ON s.idsede = r.idsede_suscrito
		WHERE r.idrepartidor = ?");
	$bd->execute(array((int)$idrepartidor));
	$r = $bd->fetchAll();
	if (!$r) { return array(); }
	return _xSedesAtiendeLista($bd, "SELECT idsede FROM repartidor_sede_atiende WHERE idrepartidor = ?",
		$idrepartidor, $r[0]['idsede_suscrito'], $r[0]['idorg']);
}

function xRepartidorAtiendeSede($bd, $idrepartidor, $idsede) {
	return in_array((int)$idsede, xSedesAtiendeRepartidor($bd, $idrepartidor), true);
}

// Misma regla que tieneAccesoModulo() de log_tracker.php (acc a veces trae comas dobles o sin coma final).
function xEsCallCenter() {
	$acc = isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '';
	return (bool)preg_match('/(^|,)\s*A27\s*(,|$)/', $acc);
}

}

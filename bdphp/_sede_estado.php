<?php
// Sede bloqueada o dada de baja = NADIE entra (ni admin ni contador).
// Regla única (la misma que backend-pedidos service/sede-estado.service.js y restobar-consola):
// la sede se puede usar solo si sede.estado=0, org.estado=0 y su ÚLTIMA fila de sede_estado
// no está bloqueada ni dada de baja (sin fila = activa).
if (!function_exists('xSedeHabilitada')) {

define('SEDE_BLOQUEADA_MSJ', 'Servicio suspendido para esta sede. Comunícate con Papaya.');
define('SEDE_BLOQUEADA_TTL', 60); // segundos que se recuerda el resultado en la sesión

// $mysqli: conexión mysqli ya abierta (p. ej. $bd->bd). Sin conexión se abre la de siempre (ManejoBD).
function xSedeHabilitadaBD($mysqli, $idsede) {
	$id = (int)$idsede;
	if ($id <= 0) { return true; } // contador sin sede elegida, etc.
	if (!$mysqli) {
		require_once __DIR__ . '/ManejoBD.php';
		$tmp = new xManejoBD('restobar');
		$mysqli = $tmp->bd;
	}
	$st = $mysqli->prepare("SELECT 1 FROM sede s JOIN org o ON o.idorg = s.idorg
		LEFT JOIN sede_estado se ON se.idsede_estado = (SELECT MAX(x.idsede_estado) FROM sede_estado x WHERE x.idsede = s.idsede)
		WHERE s.idsede = ? AND s.estado = 0 AND o.estado = 0
		  AND IFNULL(se.is_bloqueado, '0') = '0' AND IFNULL(se.is_baja, '0') = '0'");
	if (!$st) { return true; } // ante un error de BD no se bloquea a nadie
	$st->bind_param('i', $id);
	if (!$st->execute()) { return true; }
	$r = $st->get_result();
	return $r && $r->fetch_row() ? true : false;
}

// Con caché en la sesión (la sesión ya debe estar iniciada).
function xSedeHabilitada($idsede, $mysqli = null) {
	$id = (int)$idsede;
	if ($id <= 0) { return true; }
	$c = isset($_SESSION['_sede_ok']) ? $_SESSION['_sede_ok'] : null;
	if (is_array($c) && (int)$c['id'] === $id && (time() - (int)$c['ts']) < SEDE_BLOQUEADA_TTL) {
		return (bool)$c['ok'];
	}
	$ok = xSedeHabilitadaBD($mysqli, $id);
	$_SESSION['_sede_ok'] = array('id' => $id, 'ok' => $ok, 'ts' => time());
	return $ok;
}

// Corta la sesión y responde 423 (el cliente lleva al login con el mensaje).
function xSedeBloqueadaSalir() {
	if (session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION = array();
		session_destroy();
	}
	http_response_code(423);
	header('Content-Type: application/json');
	die(json_encode(array('success' => false, 'error' => 'ERR_SEDE_BLOQUEADA', 'code' => 423, 'mensaje' => SEDE_BLOQUEADA_MSJ)));
}

}

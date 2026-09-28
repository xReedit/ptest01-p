<?php
// "Usuario autorizado" (x-pass) validado en el servidor.
// Antes el navegador decidia si la clave tenia el permiso y mandaba solo el idusuario del supervisor:
// cualquiera podia mandar el id de un admin. Ahora log.php op=-102 anota en la sesion cada
// autorizacion correcta y las operaciones (3042, 5011, cambio de forma de pago, cierre de caja)
// aceptan al supervisor solo si:
//   1. se autorizo con usuario+clave en esta sesion y tiene el permiso, o
//   2. es el mismo usuario logueado y tiene el permiso (o hubo una autorizacion hace poco:
//      control de mesas manda el id propio durante ~30 s despues de un x-pass), o
//   3. tiene un permiso remoto atendido en esta sede (el admin aprueba desde su celular).
// Tambien limpia los valores que esas operaciones pegan al SQL (ids, listas, textos).

if (!function_exists('xSupRegistrar')) {

	function xSupRegistrar($idusuario, $per) {
		if (!isset($_SESSION['xpass_aut']) || !is_array($_SESSION['xpass_aut'])) { $_SESSION['xpass_aut'] = array(); }
		$_SESSION['xpass_aut'][] = array('us' => (string)$idusuario, 'per' => (string)$per, 't' => time());
		$_SESSION['xpass_aut'] = array_slice($_SESSION['xpass_aut'], -20);
	}

	// 'Pe1' no debe coincidir con un futuro 'Pe10' (ni 'Rol1' con 'Rol10')
	function xSupTienePermiso($per, $perm) {
		return (bool)preg_match('/' . preg_quote($perm, '/') . '(?!\d)/', (string)$per);
	}

	function xSupValido($bd, $u, $perm, $idsede) {
		$u = is_scalar($u) ? trim((string)$u) : '';
		if (!preg_match('/^\d+$/', $u) || (int)$u === 0) { return false; }
		$ahora = time();
		$lista = isset($_SESSION['xpass_aut']) && is_array($_SESSION['xpass_aut']) ? $_SESSION['xpass_aut'] : array();

		// 1. autorizado con usuario + clave en esta sesion (12 h: la caja reusa el supervisor mientras la pagina esta abierta)
		foreach ($lista as $a) {
			if ($a['us'] === $u && xSupTienePermiso($a['per'], $perm) && $ahora - $a['t'] <= 12 * 3600) { return true; }
		}

		// 2. el mismo usuario logueado
		if (isset($_SESSION['idusuario']) && $u === (string)$_SESSION['idusuario']) {
			$propio = $perm === 'Rol1'
				? (isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1)
				: xSupTienePermiso(isset($_SESSION['per']) ? $_SESSION['per'] : '', $perm);
			if ($propio) { return true; }
			foreach ($lista as $a) {   // ventana de control de mesas despues de un x-pass
				if (xSupTienePermiso($a['per'], $perm) && $ahora - $a['t'] <= 600) { return true; }
			}
		}

		// 3. permiso remoto atendido en esta sede
		$st = $bd->bd->prepare("SELECT 1 FROM permiso_remoto WHERE idsede = ? AND idusuario_admin = ? AND atendido = '1'
			AND fecha >= CURDATE() - INTERVAL 2 DAY LIMIT 1");
		if ($st) {
			$s = (int)$idsede; $ui = (int)$u;
			$st->bind_param('ii', $s, $ui);
			$st->execute();
			$r = $st->get_result();
			if ($r && $r->fetch_row()) { return true; }
		}
		return false;
	}

	// Numero tal cual lo mando el navegador (mismo texto en el SQL) o null si no es un numero
	function xSqlNum($v) {
		$v = is_scalar($v) ? trim((string)$v) : '';
		return preg_match('/^-?\d+(\.\d+)?$/', $v) ? $v : null;
	}

	// Lista de ids "1,2,3" o null si trae algo que no es un id
	function xSqlIdList($v) {
		if (!is_scalar($v)) { return null; }
		$out = array();
		foreach (explode(',', (string)$v) as $x) {
			$x = trim($x);
			if ($x === '') { continue; }
			if (!preg_match('/^\d+$/', $x)) { return null; }
			$out[] = $x;
		}
		return $out ? implode(',', $out) : null;
	}

	// Texto para ir entre comillas simples
	function xSqlTxt($bd, $v) {
		return $bd->bd->real_escape_string(is_scalar($v) ? (string)$v : '');
	}
}

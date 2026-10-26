<?php
	// Sedes que atiende (call center A27 y repartidores): pantalla de Usuarios. Plan: plan/REPARTIDOR-MULTISEDE-PLAN.md
	// op=sedes-org -> {sedes:[{idsede,nombre,ciudad}], total}
	// op=leer      POST {tipo:'usuario'|'repartidor', id} -> {casa, sedes} (sedes sin la casa)
	// op=guardar   POST {tipo, id, sedes} o {tipo:'repartidor', usuario, sedes} -> {casa, sedes}
	// Body JSON o form (sedes como array o "26,30"). Org y sede salen de la sesion, nunca del cliente.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	require_once __DIR__ . '/_sedes_atiende.php';
	$bd = new xManejoBD("restobar");

	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$op = isset($_GET['op']) ? $_GET['op'] : '';

	function jsonOut($ok, $datos = null, $error = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error));
		exit;
	}
	function uno($bd, $sql, $params) {
		$bd->prepare($sql);
		$bd->execute($params);
		$r = $bd->fetchAll();
		return $r ? $r[0] : null;
	}

	// persona de la org de la sesion -> array(tabla, columna, id, casa) o null
	function persona($bd, $tipo, $id, $idorg) {
		if ($tipo === 'usuario') {
			$p = uno($bd, "SELECT idsede AS casa, idorg FROM usuario WHERE idusuario = ?", array($id));
			$t = array('usuario_sede_atiende', 'idusuario');
		} elseif ($tipo === 'repartidor') {
			$p = uno($bd, "SELECT r.idsede_suscrito AS casa, s.idorg FROM repartidor r JOIN sede s ON s.idsede = r.idsede_suscrito
				WHERE r.idrepartidor = ?", array($id));
			$t = array('repartidor_sede_atiende', 'idrepartidor');
		} else {
			return null;
		}
		if (!$p || (int)$p['idorg'] !== $idorg) { return null; }
		return array($t[0], $t[1], $id, (int)$p['casa']);
	}

	// lista guardada ∩ operables, sin la casa (para marcar casillas; el admin no se expande aqui)
	function leerLista($bd, $per, $idorg) {
		$todas = _xSedesAtiendeLista($bd, "SELECT idsede FROM {$per[0]} WHERE {$per[1]} = ?", $per[2], $per[3], $idorg);
		return array('casa' => $per[3], 'sedes' => array_values(array_diff($todas, array($per[3]))));
	}

	$b = json_decode(file_get_contents('php://input'), true);
	if (!is_array($b)) { $b = $_POST; }
	$tipo = isset($b['tipo']) ? (string)$b['tipo'] : '';
	$id = isset($b['id']) ? (int)$b['id'] : 0;

	try {
		switch ($op) {
			case 'sedes-org':
				$sedes = xSedesOperablesOrg($bd, $g_ido);
				jsonOut(true, array('sedes' => $sedes, 'total' => count($sedes)));

			case 'leer':
				$per = persona($bd, $tipo, $id, $g_ido);
				if (!$per) { jsonOut(false, null, 'No se encontro a la persona en tu organizacion.'); }
				jsonOut(true, leerLista($bd, $per, $g_ido));

			case 'guardar':
				$acc = isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '';
				$rol = (int)(isset($_SESSION['rol']) ? $_SESSION['rol'] : 0);
				if ($rol !== 1 && !preg_match('/(^|,)\s*C2\s*(,|$)/', $acc)) {
					jsonOut(false, null, 'No tienes permiso para editar usuarios.');
				}
				// repartidor recien creado (log_005 op=11 no devuelve el id): el mas reciente con ese usuario en la sede
				if ($tipo === 'repartidor' && $id <= 0 && !empty($b['usuario'])) {
					$r = uno($bd, "SELECT idrepartidor FROM repartidor WHERE usuario = ? AND idsede_suscrito = ?
						ORDER BY idrepartidor DESC LIMIT 1", array((string)$b['usuario'], $g_idsede));
					$id = $r ? (int)$r['idrepartidor'] : 0;
				}
				$per = persona($bd, $tipo, $id, $g_ido);
				if (!$per) { jsonOut(false, null, 'No se encontro a la persona en tu organizacion.'); }

				$pedidas = isset($b['sedes']) ? $b['sedes'] : array();
				if (!is_array($pedidas)) { $pedidas = explode(',', (string)$pedidas); }
				$pedidas = array_map('intval', $pedidas);
				// solo operables de la org y nunca la casa (siempre cuenta, no se guarda)
				$validas = array();
				foreach (xSedesOperablesOrg($bd, $g_ido) as $s) {
					if ($s['idsede'] !== $per[3] && in_array($s['idsede'], $pedidas, true)) { $validas[] = $s['idsede']; }
				}

				$bd->bd->begin_transaction();
				try {
					$bd->prepare("DELETE FROM {$per[0]} WHERE {$per[1]} = ?");
					$bd->execute(array($per[2]));
					if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
					foreach ($validas as $s) {
						$bd->prepare("INSERT INTO {$per[0]} ({$per[1]}, idsede) VALUES (?, ?)");
						$bd->execute(array($per[2], $s));
						if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
					}
					$bd->bd->commit();
				} catch (Exception $e) {
					$bd->bd->rollback();
					throw $e;
				}
				jsonOut(true, leerLista($bd, $per, $g_ido));

			default:
				jsonOut(false, null, 'Operacion no valida.');
		}
	} catch (Exception $e) {
		error_log('log_sedes_atiende ' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'No se pudo completar la operacion.');
	}

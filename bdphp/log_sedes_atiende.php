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

			// Call center: cliente ya registrado en alguna de las sedes que atiende, con sus direcciones ubicadas.
			case 'buscar-cliente':
				if (!xEsCallCenter()) { jsonOut(false, null, 'No tienes permiso.'); }
				$q = trim(isset($b['q']) ? (string)$b['q'] : '');
				$tel = preg_replace('/[\s+\-]/', '', $q);
				$esTel = $tel !== '' && ctype_digit($tel);
				$txt = $esTel ? $tel : $q;
				$sedes = xSedesAtiendeUsuario($bd, isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : 0);
				if (strlen($txt) < 3 || !$sedes) { jsonOut(true, array('clientes' => array())); }
				$like = '%' . addcslashes($txt, '%_\\') . '%';
				// STRAIGHT_JOIN: parte de las sedes (idx_cs_sede_cliente), no del total de clientes
				$bd->prepare("SELECT c.idcliente, c.nombres, COALESCE(NULLIF(MAX(cs.telefono), ''), c.telefono) AS telefono
					FROM cliente_sede cs STRAIGHT_JOIN cliente c ON c.idcliente = cs.idcliente
					WHERE cs.idsede IN (" . implode(',', array_fill(0, count($sedes), '?')) . ") AND c.estado = 0 AND c.nombres <> ''
					  AND " . ($esTel ? "(cs.telefono LIKE ? OR c.telefono LIKE ?)" : "c.nombres LIKE ?") . "
					GROUP BY c.idcliente ORDER BY c.nombres LIMIT 10");
				$bd->execute(array_merge($sedes, $esTel ? array($like, $like) : array($like)));
				$clientes = $bd->fetchAll();
				if (!$clientes) { jsonOut(true, array('clientes' => array())); }

				$ids = array_map(function ($c) { return (int)$c['idcliente']; }, $clientes);
				$bd->prepare("SELECT idcliente_pwa_direccion, idcliente, titulo, direccion, latitude, longitude FROM cliente_pwa_direccion
					WHERE idcliente IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
					  AND estado = 0 AND latitude <> 0 AND longitude <> 0 ORDER BY idcliente_pwa_direccion DESC");
				$bd->execute($ids);
				$porCliente = array();
				foreach ($bd->fetchAll() as $d) {
					// cada pedido puede repetir el mismo punto: se deja el mas reciente
					$clave = round($d['latitude'], 4) . ',' . round($d['longitude'], 4);
					if (!isset($porCliente[(int)$d['idcliente']][$clave])) { $porCliente[(int)$d['idcliente']][$clave] = $d; }
				}
				foreach ($clientes as &$c) {
					$dirs = isset($porCliente[(int)$c['idcliente']]) ? array_values($porCliente[(int)$c['idcliente']]) : array();
					$c['direcciones'] = array_slice($dirs, 0, 50);
				}
				unset($c);
				jsonOut(true, array('clientes' => $clientes));

			// Call center: direccion nueva de un cliente de las sedes que atiende (mismas columnas que procedure_registra_nueva_direccion_cliente).
			case 'agregar-direccion-cliente':
				if (!xEsCallCenter()) { jsonOut(false, null, 'No tienes permiso.'); }
				$idcli = isset($b['idcliente']) ? (int)$b['idcliente'] : 0;
				$lat = isset($b['latitude']) ? (float)$b['latitude'] : 0;
				$lng = isset($b['longitude']) ? (float)$b['longitude'] : 0;
				$dirTxt = trim(isset($b['direccion']) ? (string)$b['direccion'] : '');
				if ($idcli <= 0 || $dirTxt === '' || ($lat == 0 && $lng == 0) || abs($lat) > 90 || abs($lng) > 180) {
					jsonOut(false, null, 'Esa direccion no es valida.');
				}
				$sedes = xSedesAtiendeUsuario($bd, isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : 0);
				$esDeSus = $sedes ? uno($bd, "SELECT 1 AS ok FROM cliente_sede WHERE idcliente = ? AND idsede IN ("
					. implode(',', array_fill(0, count($sedes), '?')) . ") LIMIT 1", array_merge(array($idcli), $sedes)) : null;
				if (!$esDeSus) { jsonOut(false, null, 'Ese cliente no es de las sedes que atiendes.'); }

				$campos = "idcliente_pwa_direccion, titulo, direccion, latitude, longitude";
				$ya = uno($bd, "SELECT $campos FROM cliente_pwa_direccion
					WHERE idcliente = ? AND estado = 0 AND ABS(latitude - ?) < 0.0001 AND ABS(longitude - ?) < 0.0001 LIMIT 1", array($idcli, $lat, $lng));
				if ($ya) { jsonOut(true, $ya); }

				$txt200 = function ($k) use ($b) { return mb_substr(trim(isset($b[$k]) ? (string)$b[$k] : ''), 0, 150, 'UTF-8'); };
				$bd->prepare("INSERT INTO cliente_pwa_direccion
					(idcliente, direccion, referencia, is_georreferencial, is_from_cliente, ciudad, departamento, pais, titulo, latitude, longitude, codigo, provincia)
					VALUES (?, ?, '', 1, 0, ?, ?, ?, 'Otro', ?, ?, ?, ?)");
				$bd->execute(array($idcli, mb_substr($dirTxt, 0, 200, 'UTF-8'), $txt200('ciudad'), $txt200('departamento'), $txt200('pais'),
					$lat, $lng, mb_substr($txt200('codigo'), 0, 50, 'UTF-8'), $txt200('provincia')));
				if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
				jsonOut(true, uno($bd, "SELECT $campos FROM cliente_pwa_direccion WHERE idcliente_pwa_direccion = ?", array((int)$bd->lastInsertId())));

			default:
				jsonOut(false, null, 'Operacion no valida.');
		}
	} catch (Exception $e) {
		error_log('log_sedes_atiende ' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'No se pudo completar la operacion.');
	}

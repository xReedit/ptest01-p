<?php
	// Contador de la org, desde la pantalla de Usuarios (antes solo desde Configuracion general / x-admin).
	// op=listar   -> {sedes:[{idsede,nombre,ciudad,mes_inicio,emite,idus_cpc}], contadores:[{idus_cpc,nombre,telefono,usuario,propio,sedes}]}
	// op=guardar  POST {idus_cpc?, nombre, telefono, sedes:[idsede]} -> {usuario, pass?}  (pass solo al crear: 123456)
	// op=resetear POST {idus_cpc} -> {usuario, pass}  (vuelve a 123456 y le pide cambiarla al entrar)
	// Org sale de la sesion. Un contador es "propio" si todas sus sedes son de esta org: solo a esos se les
	// cambia sedes o clave (un contador que atiende otros negocios no se toca desde aqui).
	// us_cpc_sedes legacy (x-admin) se guarda con idorg=idsede=0 y se enlaza por userid = sede.id_api_comprobante + serie.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");
	date_default_timezone_set('America/Lima');

	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$op = isset($_GET['op']) ? $_GET['op'] : '';

	function jsonOut($ok, $datos = null, $error = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error));
		exit;
	}
	function filas($bd, $sql, $params = null) {
		$bd->prepare($sql);
		$bd->execute($params);
		return $bd->fetchAll();
	}
	function ejecutar($bd, $sql, $params) {
		$bd->prepare($sql);
		$bd->execute($params);
		if ($bd->bd->errno) { throw new Exception($bd->bd->error); } // por si mysqli no lanza excepciones
	}

	// sede.finicio viene como dd/mm/yyyy o yyyy-mm-dd (o vacio) -> MM/YYYY, que es lo que lee el panel del contador
	function mesInicio($finicio) {
		$f = trim((string)$finicio);
		if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $f, $m)) { return sprintf('%02d/%s', $m[2], $m[3]); }
		if (preg_match('/^(\d{4})-(\d{1,2})/', $f, $m)) { return sprintf('%02d/%s', $m[2], $m[1]); }
		return date('m/Y'); // ponytail: sin fecha de inicio, el mes actual
	}

	// sedes activas de la org con lo que necesita us_cpc_sedes. Series: boleta/factura activas.
	function sedesOrg($bd, $idorg) {
		$rows = filas($bd, "SELECT s.idsede, s.nombre, s.ciudad, s.finicio, IFNULL(s.id_api_comprobante, '') AS api,
				IF(TRIM(IFNULL(s.ruc_cpe, '')) <> '', TRIM(s.ruc_cpe), o.ruc) AS ruc,
				IF(TRIM(IFNULL(s.razonsocial_cpe, '')) <> '', s.razonsocial_cpe, o.nombre) AS razonsocial,
				(SELECT GROUP_CONCAT(DISTINCT t.serie) FROM tipo_comprobante_serie t
					WHERE t.idsede = s.idsede AND t.estado = 0 AND t.idtipo_comprobante IN (2, 3) AND t.serie NOT IN ('', '0')) AS series
			FROM sede s JOIN org o ON o.idorg = s.idorg
			WHERE s.idorg = ? AND s.estado = 0 ORDER BY s.ciudad, s.nombre", array($idorg));
		$out = array();
		foreach ($rows as $r) {
			$r['idsede'] = (int)$r['idsede'];
			$r['series'] = $r['series'] ? explode(',', $r['series']) : array();
			$r['mes_inicio'] = mesInicio($r['finicio']);
			$r['emite'] = trim($r['api']) !== '' && count($r['series']) > 0;
			$out[$r['idsede']] = $r;
		}
		return $out;
	}

	// sedes de la org a las que apunta una fila de us_cpc_sedes
	function sedesDeFila($fila, $sedes) {
		if ((int)$fila['idsede'] > 0) { return isset($sedes[(int)$fila['idsede']]) ? array((int)$fila['idsede']) : array(); }
		$out = array();
		foreach ($sedes as $s) {
			if ($s['api'] !== '' && (string)$fila['userid'] === (string)$s['api'] && in_array((string)$fila['serie'], $s['series'], true)) {
				$out[] = $s['idsede'];
			}
		}
		return $out;
	}

	// contadores con al menos una sede de la org -> [idus_cpc => {..., sedes:[idsede], filas:[{id, sedes}]}]
	function contadoresOrg($bd, $idorg, $sedes) {
		$apis = array_values(array_unique(array_filter(array_map(function ($s) { return $s['api']; }, $sedes), 'strlen')));
		$inApis = $apis ? implode(',', array_fill(0, count($apis), '?')) : "''";
		$rows = filas($bd, "SELECT cs.idus_cpc_sedes, cs.idus_cpc, cs.idorg, cs.idsede, cs.userid, cs.serie,
				c.nombre_cpc, c.telefono, c.idusuario, u.usuario
			FROM us_cpc_sedes cs JOIN us_cpc c ON c.idus_cpc = cs.idus_cpc LEFT JOIN usuario u ON u.idusuario = c.idusuario
			WHERE cs.estado = 0 AND c.estado = 0 AND (cs.idorg = ? OR (cs.idorg = 0 AND cs.userid IN ($inApis)))",
			array_merge(array($idorg), $apis));
		$out = array();
		foreach ($rows as $r) {
			$id = (int)$r['idus_cpc'];
			$ss = sedesDeFila($r, $sedes);
			if (!$ss) { continue; }
			if (!isset($out[$id])) {
				$out[$id] = array('idus_cpc' => $id, 'nombre' => $r['nombre_cpc'], 'telefono' => (string)$r['telefono'],
					'usuario' => (string)$r['usuario'], 'idusuario' => (int)$r['idusuario'], 'sedes' => array(), 'filas' => array());
			}
			$out[$id]['sedes'] = array_values(array_unique(array_merge($out[$id]['sedes'], $ss)));
			$out[$id]['filas'][] = array('id' => (int)$r['idus_cpc_sedes'], 'sedes' => $ss);
		}
		foreach ($out as $id => $c) { $out[$id]['propio'] = esPropio($bd, $id, $idorg, $sedes); }
		return $out;
	}

	// todas las filas activas del contador apuntan a sedes de esta org
	function esPropio($bd, $idus_cpc, $idorg, $sedes) {
		foreach (filas($bd, "SELECT idorg, idsede, userid, serie FROM us_cpc_sedes WHERE idus_cpc = ? AND estado = 0", array($idus_cpc)) as $f) {
			if ((int)$f['idorg'] === $idorg) { continue; }
			if ((int)$f['idorg'] === 0 && sedesDeFila($f, $sedes)) { continue; }
			return false;
		}
		return true;
	}

	// clave por defecto del sistema: con nuevo=0 el login le pide cambiarla al primer ingreso
	function claveNueva() { return '123456'; }

	// CONTA + siguiente numero libre (mismo formato que x-admin)
	function usuarioNuevo($bd) {
		$r = filas($bd, "SELECT IFNULL(MAX(CAST(SUBSTRING(usuario, 6) AS UNSIGNED)), 0) AS n FROM usuario WHERE usuario REGEXP '^CONTA[0-9]+$'");
		$n = (int)$r[0]['n'];
		do {
			$n++;
			$us = 'CONTA' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
			$ya = filas($bd, "SELECT 1 FROM usuario WHERE usuario = ? LIMIT 1", array($us));
		} while ($ya);
		return $us;
	}

	function asignarSede($bd, $idus_cpc, $idorg, $s) {
		foreach ($s['series'] as $serie) {
			ejecutar($bd, "INSERT INTO us_cpc_sedes (idus_cpc, idorg, idsede, estado, serie, nomsede, userid, ciudad, razonsocial, ruc, mes_inicio)
				VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)",
				array($idus_cpc, $idorg, $s['idsede'], (string)$serie, (string)$s['nombre'], (string)$s['api'], (string)$s['ciudad'],
					(string)$s['razonsocial'], (string)$s['ruc'], $s['mes_inicio']));
		}
	}

	$b = json_decode(file_get_contents('php://input'), true);
	if (!is_array($b)) { $b = $_POST; }

	if ($g_ido <= 0) { jsonOut(false, null, 'Sesion sin organizacion.'); }
	$rol = (int)(isset($_SESSION['rol']) ? $_SESSION['rol'] : 0);
	$acc = isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '';
	if ($op !== 'listar' && $rol !== 1 && !preg_match('/(^|,)\s*C2\s*(,|$)/', $acc)) {
		jsonOut(false, null, 'No tienes permiso para editar usuarios.');
	}

	try {
		$sedes = sedesOrg($bd, $g_ido);
		$conts = contadoresOrg($bd, $g_ido, $sedes);

		switch ($op) {
			case 'listar':
				$deSede = array();
				foreach ($conts as $c) { foreach ($c['sedes'] as $ids) { $deSede[$ids] = $c['idus_cpc']; } }
				$outSedes = array();
				foreach ($sedes as $s) {
					$outSedes[] = array('idsede' => $s['idsede'], 'nombre' => $s['nombre'], 'ciudad' => $s['ciudad'],
						'mes_inicio' => $s['mes_inicio'], 'emite' => $s['emite'],
						'idus_cpc' => isset($deSede[$s['idsede']]) ? $deSede[$s['idsede']] : null);
				}
				$outConts = array();
				foreach ($conts as $c) {
					unset($c['filas'], $c['idusuario']);
					$outConts[] = $c;
				}
				jsonOut(true, array('sedes' => $outSedes, 'contadores' => $outConts));

			case 'guardar':
				$id = isset($b['idus_cpc']) ? (int)$b['idus_cpc'] : 0;
				$nombre = mb_strtoupper(trim(isset($b['nombre']) ? (string)$b['nombre'] : ''), 'UTF-8');
				$telefono = preg_replace('/[^0-9+ ]/', '', isset($b['telefono']) ? (string)$b['telefono'] : '');
				$pedidas = isset($b['sedes']) && is_array($b['sedes']) ? array_values(array_unique(array_map('intval', $b['sedes']))) : array();
				if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 250) { jsonOut(false, null, 'Escribe el nombre del contador.'); }
				if (strlen($telefono) > 25) { jsonOut(false, null, 'El telefono es muy largo.'); }
				if (!$pedidas) { jsonOut(false, null, 'Marca al menos una sede.'); }
				if ($id > 0 && (!isset($conts[$id]) || !$conts[$id]['propio'])) {
					jsonOut(false, null, 'Este contador atiende otros negocios; no se puede cambiar desde aqui.');
				}
				foreach ($pedidas as $ids) {
					if (!isset($sedes[$ids])) { jsonOut(false, null, 'Una de las sedes no es de tu organizacion.'); }
					if (!$sedes[$ids]['emite']) { jsonOut(false, null, $sedes[$ids]['nombre'] . ' no emite comprobantes electronicos.'); }
					foreach ($conts as $c) {
						if ($c['idus_cpc'] !== $id && in_array($ids, $c['sedes'], true)) {
							jsonOut(false, null, $sedes[$ids]['nombre'] . ' ya tiene contador (' . $c['usuario'] . ').');
						}
					}
				}

				$bd->beginTransaction();
				$pass = null;
				if ($id > 0) {
					$usuario = $conts[$id]['usuario'];
					ejecutar($bd, "UPDATE us_cpc SET nombre_cpc = ?, telefono = ? WHERE idus_cpc = ?", array($nombre, $telefono, $id));
					ejecutar($bd, "UPDATE usuario SET nombres = ? WHERE idusuario = ?", array($nombre, $conts[$id]['idusuario']));
					$tiene = $conts[$id]['sedes'];
					// quitar: filas cuyas sedes ya no estan todas marcadas
					foreach ($conts[$id]['filas'] as $f) {
						if (array_diff($f['sedes'], $pedidas)) {
							ejecutar($bd, "UPDATE us_cpc_sedes SET estado = 1 WHERE idus_cpc_sedes = ?", array($f['id']));
							$tiene = array_diff($tiene, $f['sedes']);
						}
					}
					foreach (array_diff($pedidas, $tiene) as $ids) { asignarSede($bd, $id, $g_ido, $sedes[$ids]); }
				} else {
					$usuario = usuarioNuevo($bd);
					$pass = claveNueva();
					ejecutar($bd, "INSERT INTO usuario (nombres, cargo, usuario, pass, acc, rol, nuevo) VALUES (?, 'CONTADOR', ?, ?, 'D6,', 2, 0)",
						array($nombre, $usuario, $pass));
					$idus = (int)$bd->lastInsertId();
					$primera = $sedes[$pedidas[0]];
					ejecutar($bd, "INSERT INTO us_cpc (idusuario, nombre_cpc, telefono, ciudad, f_registro, estado) VALUES (?, ?, ?, ?, ?, 0)",
						array($idus, $nombre, $telefono, (string)$primera['ciudad'], date('d/m/Y')));
					$id = (int)$bd->lastInsertId();
					foreach ($pedidas as $ids) { asignarSede($bd, $id, $g_ido, $sedes[$ids]); }
				}
				$bd->xCommit();
				jsonOut(true, array('idus_cpc' => $id, 'usuario' => $usuario, 'pass' => $pass));

			case 'resetear':
				$id = isset($b['idus_cpc']) ? (int)$b['idus_cpc'] : 0;
				if (!isset($conts[$id])) { jsonOut(false, null, 'No se encontro al contador en tu organizacion.'); }
				if (!$conts[$id]['propio']) {
					jsonOut(false, null, 'Este contador atiende otros negocios; pide el cambio de clave a soporte.');
				}
				$pass = claveNueva();
				ejecutar($bd, "UPDATE usuario SET pass = ?, nuevo = 0 WHERE idusuario = ?", array($pass, $conts[$id]['idusuario']));
				jsonOut(true, array('usuario' => $conts[$id]['usuario'], 'pass' => $pass));

			default:
				jsonOut(false, null, 'Operacion no valida.');
		}
	} catch (Throwable $e) {
		@$bd->bd->rollback();
		error_log('log_contador_sede op=' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'No se pudo completar. Intenta de nuevo.');
	}
?>

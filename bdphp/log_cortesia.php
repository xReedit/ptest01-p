<?php
	// Cortesias por item (la casa invita una linea de la cuenta). Plan: .superpowers/sdd/CORTESIAS-PLAN/
	// Migracion 078: sede_opciones.permite_cortesia, tabla pedido_cortesia y sp_cortesia_aplicar.
	// Migracion 079: sp_cortesia_quitar (deshacer; la auditoria queda con activa=NULL).
	// Permiso Pe10 "Dar cortesias"; quien no lo tiene usa la clave de un supervisor (x-pass + xSupValido).
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	require_once __DIR__ . '/_supervisor.php';
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): estas pantallas siempre mandan JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode(array('success' => false, 'datos' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_us = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

	const CORT_MAX_ITEMS = 50;
	const CORT_MAX_MOTIVO = 100;
	const CORT_MAX_DIAS = 92;

	function jsonOut($ok, $datos = null, $error = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error));
		exit;
	}
	function leerBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	function filas($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		return $bd->fetchAll();
	}
	function fechaIso($s) {
		if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return null; }
		$d = DateTime::createFromFormat('!Y-m-d', $s);
		return ($d && $d->format('Y-m-d') === $s) ? $d : null;
	}
	class ErrorUsuario extends Exception {}

	// SIGNAL 45000 de sp_cortesia_aplicar -> mensaje para el cajero
	function cortesiaMensaje($sqlMsg) {
		$txt = array(
			'DESACTIVADO' => 'Las cortesías no están activadas en este local.',
			'FALTA_MOTIVO' => 'Escribe el motivo de la cortesía.',
			'NO_DISPONIBLE' => 'Uno de los productos ya no está en la cuenta (cobrado o borrado). Vuelve a abrir la cuenta.',
			'YA_ES_CORTESIA' => 'Uno de los productos ya es cortesía.',
			'SIN_IMPORTE' => 'Uno de los productos ya está en S/ 0.',
			'NO_ES_CORTESIA' => 'Uno de los productos ya no es cortesía. Vuelve a abrir la cuenta.',
			'LINEA_CAMBIO' => 'La cantidad de un producto cambió desde la cortesía; no se puede quitar.'
		);
		$k = trim((string)$sqlMsg);
		return isset($txt[$k]) ? $txt[$k] : null;
	}

	// ids de pedido_detalle: enteros > 0, sin repetidos, 1..50. Ordenados: varias cajas a la vez toman
	// los candados en el mismo orden (menos deadlocks).
	function cortesiaItems($v) {
		if (!is_array($v) || count($v) < 1) { throw new ErrorUsuario('Elige al menos un producto.'); }
		if (count($v) > CORT_MAX_ITEMS) { throw new ErrorUsuario('Máximo ' . CORT_MAX_ITEMS . ' productos por cortesía.'); }
		$ids = array();
		foreach ($v as $x) {
			$s = is_int($x) ? (string)$x : (is_string($x) ? trim($x) : '');
			if (!preg_match('/^\d{1,10}$/', $s) || (int)$s <= 0) { throw new ErrorUsuario('Producto no válido. Vuelve a abrir la cuenta.'); }
			$ids[] = (int)$s;
		}
		if (count(array_unique($ids)) !== count($ids)) { throw new ErrorUsuario('Hay un producto repetido. Vuelve a abrir la cuenta.'); }
		sort($ids);
		return $ids;
	}

	// Supervisor solo si puso usuario+clave en x-pass en esta sesion y tiene Pe10 (paso 1 de xSupValido).
	// No se usa xSupValido entero: su paso 3 acepta cualquier permiso remoto atendido (de otra cosa) por 2 dias,
	// y su paso 2 la ventana de 600 s de un x-pass ajeno. La pantalla de cortesia siempre pasa por x-pass.
	function cortesiaSupervisorConClave($u) {
		$lista = isset($_SESSION['xpass_aut']) && is_array($_SESSION['xpass_aut']) ? $_SESSION['xpass_aut'] : array();
		foreach ($lista as $a) {
			if ((int)$a['us'] === (int)$u && xSupTienePermiso($a['per'], 'Pe10') && time() - $a['t'] <= 12 * 3600) { return true; }
		}
		return false;
	}

	// Un CALL con resultado {importe}. Se consume todo (resultado + estado del CALL) antes del siguiente:
	// si no, MySQL responde "Commands out of sync".
	function cortesiaLlamar($bd, $sql, $tipos, $args) {
		$st = $bd->bd->prepare($sql);
		if (!$st) { throw new Exception('prepare: ' . $bd->bd->error); }
		try {
			$st->bind_param($tipos, ...$args);
			if (!$st->execute()) { throw new Exception($st->error); }
			$res = $st->get_result();
			if (!$res) { throw new Exception('sin resultado: ' . $st->error); }
			$fila = $res->fetch_assoc();
			$res->free();
			while ($st->more_results() && $st->next_result()) {
				$extra = $st->get_result();
				if ($extra) { $extra->free(); }
			}
			if (!$fila || !isset($fila['importe'])) { throw new Exception('sin importe'); }
			return (float)$fila['importe'];
		} finally {
			$st->close();
		}
	}

	if ($g_idsede <= 0 || $g_ido <= 0 || $g_us <= 0) { jsonOut(false, null, 'Sesion sin sede'); }

	try {
		switch ($op) {
			// Para mostrar el boton Cortesia y saber si hace falta la clave de supervisor
			case 'config': {
				$r = filas($bd, "SELECT permite_cortesia FROM sede_opciones WHERE idsede = ?", array($g_idsede));
				jsonOut(true, array(
					'activo' => $r && (string)$r[0]['permite_cortesia'] === '1',
					'puede' => xSupTienePermiso(isset($_SESSION['per']) ? $_SESSION['per'] : '', 'Pe10')
				));
			}

			// items = lineas a regalar, quitar = lineas cortesia a devolver a su precio. Ambas en un solo Confirmar.
			case 'aplicar': {
				$b = leerBody();
				$vacio = function ($k) use ($b) { return !isset($b[$k]) || $b[$k] === array(); };
				if ($vacio('items') && $vacio('quitar')) { throw new ErrorUsuario('Elige al menos un producto.'); }
				$ids = $vacio('items') ? array() : cortesiaItems($b['items']);
				$quitar = $vacio('quitar') ? array() : cortesiaItems($b['quitar']);
				if (count($ids) + count($quitar) > CORT_MAX_ITEMS) { throw new ErrorUsuario('Máximo ' . CORT_MAX_ITEMS . ' productos por cortesía.'); }
				if (array_intersect($ids, $quitar)) { throw new ErrorUsuario('Hay un producto repetido. Vuelve a abrir la cuenta.'); }
				$motivo = isset($b['motivo']) && is_string($b['motivo']) ? trim($b['motivo']) : '';
				if ($ids && $motivo === '') { throw new ErrorUsuario('Escribe el motivo de la cortesía.'); }
				if (mb_strlen($motivo, 'UTF-8') > CORT_MAX_MOTIVO) { throw new ErrorUsuario('El motivo es muy largo (máximo ' . CORT_MAX_MOTIVO . ' letras).'); }
				// u = 0: el usuario de la sesion; u > 0: el supervisor que puso su clave en x-pass
				$u = isset($b['u']) && is_scalar($b['u']) ? (int)$b['u'] : 0;
				if ($u <= 0) {
					// sin clave de supervisor: el usuario de la sesion debe tener Pe10 el mismo
					if (!xSupTienePermiso(isset($_SESSION['per']) ? $_SESSION['per'] : '', 'Pe10')) { throw new ErrorUsuario('Autorizacion no valida'); }
					$u = $g_us;
				} elseif (!cortesiaSupervisorConClave($u)) {
					throw new ErrorUsuario('Autorizacion no valida');
				}

				// Una sola transaccion: o se regalan todas las lineas o ninguna.
				// El SP no abre ni cierra transaccion; cualquier error (no solo SIGNAL) -> rollback de todo.
				// Sin transaccion no se aplica nada (cada CALL quedaria con autocommit): error generico.
				if (!$bd->bd->begin_transaction()) { throw new Exception('begin_transaction: ' . $bd->bd->error); }
				try {
					// todas las lineas en orden de id (mismo orden de candados en todas las cajas)
					$todas = array();
					foreach ($ids as $id) { $todas[$id] = true; }
					foreach ($quitar as $id) { $todas[$id] = false; }
					ksort($todas);
					$total = 0.0; $totalQuitado = 0.0;
					foreach ($todas as $id => $regalar) {
						if ($regalar) {
							$total += cortesiaLlamar($bd, "CALL sp_cortesia_aplicar(?, ?, ?, ?, ?)", 'iiiis', array($id, $g_idsede, $g_us, $u, $motivo));
						} else {
							$totalQuitado += cortesiaLlamar($bd, "CALL sp_cortesia_quitar(?, ?, ?, ?)", 'iiii', array($id, $g_idsede, $g_us, $u));
						}
					}
					if (!$bd->bd->commit()) { throw new Exception('commit: ' . $bd->bd->error); }
				} catch (Throwable $e) {
					try { $bd->bd->rollback(); } catch (Throwable $e2) { error_log('log_cortesia.php aplicar rollback: ' . $e2->getMessage()); }
					$m = cortesiaMensaje($e->getMessage());
					if ($m !== null) { throw new ErrorUsuario($m); }
					error_log('log_cortesia.php aplicar sede ' . $g_idsede . ': ' . $e->getMessage());
					throw new ErrorUsuario('No se pudo aplicar la cortesía.');
				}
				jsonOut(true, array('cantidad' => count($ids), 'importe_total' => round($total, 2),
					'quitadas' => count($quitar), 'importe_quitado' => round($totalQuitado, 2)));
			}

			case 'reporte': {
				$b = leerBody();
				$desde = fechaIso(isset($b['desde']) ? $b['desde'] : null);
				$hasta = fechaIso(isset($b['hasta']) ? $b['hasta'] : null);
				if (!$desde || !$hasta) { throw new ErrorUsuario('Fecha no valida'); }
				if ($desde > $hasta) { throw new ErrorUsuario('La fecha inicial es mayor que la final'); }
				if ((int)$desde->diff($hasta)->days > CORT_MAX_DIAS) { throw new ErrorUsuario('Elige un rango de hasta ' . CORT_MAX_DIAS . ' dias'); }
				$hastaSig = clone $hasta;
				$hastaSig->modify('+1 day');
				// pd.estado = 0: una linea cortesia borrada luego con la papelera no cuenta; p.estado <> 3: mesa anulada
				$r = filas($bd, "SELECT DATE_FORMAT(pc.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_hora, p.nummesa,
						pc.descripcion, pc.cantidad, (pc.importe + 0) AS importe, pc.motivo,
						IFNULL(us.nombres, '') AS solicita, IFNULL(ua.nombres, '') AS autoriza
					FROM pedido_cortesia pc
					JOIN pedido p ON p.idpedido = pc.idpedido
					JOIN pedido_detalle pd ON pd.idpedido_detalle = pc.idpedido_detalle
					LEFT JOIN usuario us ON us.idusuario = pc.idusuario_solicita
					LEFT JOIN usuario ua ON ua.idusuario = pc.idusuario_autoriza
					WHERE pc.idsede = ? AND pc.fecha_hora >= ? AND pc.fecha_hora < ?
					  AND p.estado <> 3 AND pd.estado = 0 AND pc.activa = 1
					ORDER BY pc.fecha_hora DESC
					LIMIT 2000", array($g_idsede, $desde->format('Y-m-d'), $hastaSig->format('Y-m-d')));
				foreach ($r as $i => $f) { $r[$i]['importe'] = (float)$f['importe']; }
				jsonOut(true, $r);
			}

			default:
				jsonOut(false, null, 'Operacion no valida');
		}
	} catch (ErrorUsuario $e) {
		jsonOut(false, null, $e->getMessage());
	} catch (Throwable $e) {
		error_log('log_cortesia.php op=' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'No se pudo completar la operacion');
	}

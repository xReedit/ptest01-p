<?php
	// Cobros de meseros (rendicion / arqueo de mozos). Migracion: migraciones/pendientes-prod/2026-10-08_070_cobros_meseros.sql
	// Contexto: plan/PEDIDO-POR-CONFIRMAR-PLAN.md. Cuando el mozo confirma un pedido de la carta QR y cobra,
	// procedure_save_pedido_holding deja el pago con registro_pago.idusuario = mozo: el cierre del cajero
	// (log.php 7001/700101/70012, que suma por el usuario de la sesion) nunca lo ve y el mozo no cierra caja.
	// Aqui el cajero RECIBE ese dinero: el EFECTIVO entregado entra a SU caja como ingreso (ie_caja tipo 1)
	// y los pagos digitales (Yape, tarjeta...) quedan anotados en la rendicion, sin ie_caja.
	// Front: app/page/x-cobros-meseros.html + app/shared/cobros.meseros.js (opcion del menu de Control de Pedidos).
	// Sede, organizacion y cajero salen SOLO de la sesion; los totales SIEMPRE se recalculan aqui.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): el POS siempre manda JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		echo json_encode(array('success' => false, 'data' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_idus = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '') . ',';
	// Solo se lee la sesion: soltar el candado para no frenar las demas pestanas.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	// Ventana de busqueda de cobros pendientes: los ultimos 7 dias (por pedido_por_confirmar.fecha_registro).
	// Mantiene la consulta barata (indice idx_ppc_sede_estado_fecha) aunque la tabla crezca. Un cobro de mozo
	// que no se rinda en 7 dias deja de aparecer aqui: subir este tope si algun local lo necesita.
	const CM_DIAS_PENDIENTE = 7;
	const CM_TIPO_PAGO_EFECTIVO = 1;
	// Mismo permiso que la opcion CAJA: el efectivo recibido entra a la caja (y al cierre) de quien recibe.
	const CM_PERMISO_CAJA = 'A3';
	const CM_MAX_COBROS = 300;

	class CmErrorUsuario extends Exception {}

	function cmOut($ok, $data = null, $error = '') {
		echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error));
		exit;
	}
	function cmBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	function cmFilas($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error, $bd->stmt->errno); }
		return $bd->fetchAll();
	}
	function cmEjecutar($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error, $bd->stmt->errno); }
		return $bd->stmt->affected_rows;
	}
	function cmDinero($n) { return number_format(round((float)$n, 2), 2, '.', ''); }
	function cmCorto($s, $max) {
		$s = trim((string)$s);
		return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
	}
	function cmLargo($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
	function cmPlaceholders($n) { return implode(',', array_fill(0, $n, '?')); }

	/**
	 * Cobros de mozos de la sede que aun no se rindieron (ultimos CM_DIAS_PENDIENTE dias).
	 * $idmozo > 0 filtra un mozo; $ids filtra pagos puntuales. Se excluyen los cobros del propio usuario
	 * de la sesion: esos ya estan en su cierre (rp.idusuario = el) y rendirlos los contaria dos veces.
	 * Devuelve filas por pago (sin metodos), una por idregistro_pago.
	 */
	function cmCobrosPendientes($bd, $idsede, $idusSesion, $idmozo = 0, $ids = array()) {
		$sql = "SELECT rp.idregistro_pago, ppc.idpedido, ppc.mesa, ppc.nombre_cliente, rp.idusuario,
				IFNULL(NULLIF(TRIM(u.nombres), ''), IFNULL(u.usuario, CONCAT('Usuario ', rp.idusuario))) AS nombre,
				DATE_FORMAT(IFNULL(rp.fecha_hora, ppc.fecha_accion), '%Y-%m-%d %H:%i:%s') AS fecha_hora
			FROM pedido_por_confirmar ppc
			JOIN pedido p ON p.idpedido = ppc.idpedido
			JOIN registro_pago rp ON rp.idregistro_pago = p.idregistro_pago
			LEFT JOIN usuario u ON u.idusuario = rp.idusuario
			LEFT JOIN mozo_rendicion_detalle d ON d.idregistro_pago = rp.idregistro_pago
			WHERE ppc.idsede = ? AND ppc.estado = '1' AND ppc.fecha_registro >= NOW() - INTERVAL " . (int)CM_DIAS_PENDIENTE . " DAY
			  AND ppc.origen_accion = 'MOZO' AND ppc.pagado = '1'
			  AND rp.idsede = ppc.idsede AND rp.estado = 0 AND rp.cierre = 0 AND rp.idusuario <> ?
			  AND d.idregistro_pago IS NULL";
		$params = array((int)$idsede, (int)$idusSesion);
		if ($idmozo > 0) { $sql .= " AND rp.idusuario = ?"; $params[] = (int)$idmozo; }
		if ($ids) { $sql .= " AND rp.idregistro_pago IN (" . cmPlaceholders(count($ids)) . ")"; $params = array_merge($params, $ids); }
		$sql .= " ORDER BY nombre, rp.idregistro_pago LIMIT 1000";
		$porPago = array();
		foreach (cmFilas($bd, $sql, $params) as $f) {
			$id = (int)$f['idregistro_pago'];
			if (isset($porPago[$id])) { continue; } // un pago = un cobro, aunque lo apunten dos pedidos
			$porPago[$id] = array(
				'idregistro_pago' => $id, 'idpedido' => (int)$f['idpedido'], 'mesa' => (string)$f['mesa'],
				'nombre_cliente' => (string)$f['nombre_cliente'], 'idusuario' => (int)$f['idusuario'],
				'nombre' => (string)$f['nombre'], 'fecha_hora' => (string)$f['fecha_hora'],
				'metodos' => array(), 'total' => 0.0, 'efectivo' => 0.0
			);
		}
		if (!$porPago) { return array(); }
		// metodos de pago de esos cobros
		$idsPago = array_keys($porPago);
		$met = cmFilas($bd, "SELECT rpd.idregistro_pago, rpd.idtipo_pago, IFNULL(tp.descripcion, '') AS descripcion, IFNULL(tp.img, '') AS img,
				SUM(rpd.importe + 0) AS importe
			FROM registro_pago_detalle rpd
			LEFT JOIN tipo_pago tp ON tp.idtipo_pago = rpd.idtipo_pago
			WHERE rpd.idregistro_pago IN (" . cmPlaceholders(count($idsPago)) . ") AND IFNULL(rpd.estado, 0) = 0
			GROUP BY rpd.idregistro_pago, rpd.idtipo_pago, tp.descripcion, tp.img
			ORDER BY rpd.idregistro_pago, rpd.idtipo_pago", $idsPago);
		foreach ($met as $m) {
			$id = (int)$m['idregistro_pago'];
			$imp = round((float)$m['importe'], 2);
			$porPago[$id]['metodos'][] = array('idtipo_pago' => (int)$m['idtipo_pago'], 'descripcion' => (string)$m['descripcion'],
				'img' => (string)$m['img'], 'importe' => $imp);
			$porPago[$id]['total'] = round($porPago[$id]['total'] + $imp, 2);
			if ((int)$m['idtipo_pago'] === CM_TIPO_PAGO_EFECTIVO) { $porPago[$id]['efectivo'] = round($porPago[$id]['efectivo'] + $imp, 2); }
		}
		return array_values($porPago);
	}

	/** Suma por metodo de pago: [{idtipo_pago, descripcion, img, cantidad, importe}] */
	function cmPorMetodo($cobros) {
		$r = array();
		foreach ($cobros as $c) {
			foreach ($c['metodos'] as $m) {
				$k = $m['idtipo_pago'];
				if (!isset($r[$k])) { $r[$k] = array('idtipo_pago' => $k, 'descripcion' => $m['descripcion'], 'img' => $m['img'], 'cantidad' => 0, 'importe' => 0.0); }
				$r[$k]['cantidad']++;
				$r[$k]['importe'] = round($r[$k]['importe'] + $m['importe'], 2);
			}
		}
		ksort($r);
		return array_values($r);
	}

	/** Agrupa cobros por mozo: [{idusuario, nombre, cobros, por_metodo, total, efectivo}] */
	function cmAgruparPorMozo($cobros) {
		$g = array();
		foreach ($cobros as $c) {
			$k = $c['idusuario'];
			if (!isset($g[$k])) { $g[$k] = array('idusuario' => $k, 'nombre' => $c['nombre'], 'cobros' => array(), 'total' => 0.0, 'efectivo' => 0.0); }
			$g[$k]['cobros'][] = $c;
			$g[$k]['total'] = round($g[$k]['total'] + $c['total'], 2);
			$g[$k]['efectivo'] = round($g[$k]['efectivo'] + $c['efectivo'], 2);
		}
		foreach ($g as $k => $v) { $g[$k]['por_metodo'] = cmPorMetodo($v['cobros']); }
		return array_values($g);
	}

	/** Recibe la rendicion de un mozo en UNA transaccion. Devuelve el resumen de lo grabado. */
	function cmRendir($bd, $b, $ido, $idsede, $idus) {
		$idmozo = isset($b['idusuario_mozo']) ? (int)$b['idusuario_mozo'] : 0;
		$ids = isset($b['idsregistro_pago']) && is_array($b['idsregistro_pago'])
			? array_values(array_unique(array_filter(array_map('intval', $b['idsregistro_pago']), function ($x) { return $x > 0; }))) : array();
		$entregadoRaw = isset($b['efectivo_entregado']) ? $b['efectivo_entregado'] : 0;
		$obs = isset($b['observacion']) && is_string($b['observacion']) ? cmCorto($b['observacion'], 250) : '';
		if ($idmozo <= 0) { throw new CmErrorUsuario('Elige el mozo que entrega el dinero.'); }
		if ($idmozo === $idus) { throw new CmErrorUsuario('Esos cobros ya estan en tu propia caja: no hace falta recibirlos.'); }
		if (!$ids) { throw new CmErrorUsuario('Marca al menos un cobro para recibir.'); }
		if (count($ids) > CM_MAX_COBROS) { throw new CmErrorUsuario('Demasiados cobros a la vez. Recibe por partes (maximo ' . CM_MAX_COBROS . ').'); }
		if (!is_numeric($entregadoRaw) || (float)$entregadoRaw < 0 || (float)$entregadoRaw >= 1000000) { throw new CmErrorUsuario('Escribe cuanto efectivo te entrego el mozo (0 si no entrego efectivo).'); }
		$entregado = round((float)$entregadoRaw, 2);

		$bd->bd->begin_transaction();
		try {
			// Se recalcula todo desde la BD: solo cobros de ESTE mozo, de esta sede, cobrados por mozo y sin rendir.
			$cobros = cmCobrosPendientes($bd, $idsede, $idus, $idmozo, $ids);
			if (count($cobros) !== count($ids)) {
				throw new CmErrorUsuario('Alguno de estos cobros ya fue rendido o ya no esta pendiente. Actualice la lista.');
			}
			$nombreMozo = $cobros[0]['nombre'];
			$total = 0.0; $efectivo = 0.0;
			foreach ($cobros as $c) { $total = round($total + $c['total'], 2); $efectivo = round($efectivo + $c['efectivo'], 2); }
			$diferencia = round($entregado - $efectivo, 2);
			if (abs($diferencia) >= 0.01 && cmLargo($obs) < 3) {
				throw new CmErrorUsuario('El efectivo entregado no cuadra (diferencia S/ ' . cmDinero($diferencia) . '). Escribe una observacion (minimo 3 letras).');
			}
			$porMetodo = cmPorMetodo($cobros);
			$detalleMetodos = array();
			foreach ($porMetodo as $m) {
				$detalleMetodos[] = array('idtipo_pago' => $m['idtipo_pago'], 'descripcion' => $m['descripcion'],
					'cantidad' => $m['cantidad'], 'importe' => $m['importe']);
			}

			cmEjecutar($bd, "INSERT INTO mozo_rendicion (idorg, idsede, idusuario_mozo, idusuario_caja, fecha_hora, cantidad_cobros,
					total_esperado, efectivo_esperado, efectivo_entregado, diferencia, detalle_metodos, observacion)
				VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, NULLIF(?, ''))",
				array((int)$ido, (int)$idsede, $idmozo, (int)$idus, count($cobros), cmDinero($total), cmDinero($efectivo),
					cmDinero($entregado), cmDinero($diferencia), json_encode($detalleMetodos, JSON_UNESCAPED_UNICODE), $obs));
			$idr = (int)$bd->bd->insert_id;
			if ($idr <= 0) { throw new Exception('No se obtuvo el id de la rendicion'); }

			// La PK de mozo_rendicion_detalle (idregistro_pago) impide rendir dos veces el mismo pago, aun con dos cajas a la vez.
			foreach ($cobros as $c) {
				try {
					cmEjecutar($bd, "INSERT INTO mozo_rendicion_detalle (idregistro_pago, idmozo_rendicion, idpedido) VALUES (?, ?, ?)",
						array((int)$c['idregistro_pago'], $idr, (int)$c['idpedido']));
				} catch (Exception $e) {
					if ((int)$e->getCode() === 1062) { throw new CmErrorUsuario('Alguno de estos cobros ya fue rendido. Actualice la lista.'); }
					throw $e;
				}
			}

			$idie = null;
			if ($entregado > 0) {
				// Solo el efectivo entra a caja: ie_caja no tiene forma de pago y el cierre suma sus ingresos al efectivo esperado.
				$digital = array();
				foreach ($porMetodo as $m) {
					if ($m['idtipo_pago'] !== CM_TIPO_PAGO_EFECTIVO) { $digital[] = $m['descripcion'] . ' S/ ' . cmDinero($m['importe']); }
				}
				$motivo = 'RENDICION MOZO ' . (function_exists('mb_strtoupper') ? mb_strtoupper($nombreMozo, 'UTF-8') : strtoupper($nombreMozo)) . ' #' . $idr . ': ' . count($cobros) . ' cobro' . (count($cobros) === 1 ? '' : 's')
					. ', efectivo S/ ' . cmDinero($entregado)
					. (abs($diferencia) >= 0.01 ? ' (esperado S/ ' . cmDinero($efectivo) . ')' : '')
					. ($digital ? '. Sin efectivo: ' . implode(', ', $digital) : '');
				cmEjecutar($bd, "INSERT INTO ie_caja (idorg, idsede, idusuario, tipo, motivo, fecha, monto, fecha_cierre, fecha_hora)
					VALUES (?, ?, ?, 1, ?, ?, ?, '', NOW())",
					array((int)$ido, (int)$idsede, (int)$idus, cmCorto($motivo, 255), date('d/m/Y H:i:s'), cmDinero($entregado)));
				$idie = (int)$bd->bd->insert_id;
				cmEjecutar($bd, "UPDATE mozo_rendicion SET idie_caja = ? WHERE idmozo_rendicion = ?", array($idie, $idr));
			}
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
		return array('idmozo_rendicion' => $idr, 'cantidad_cobros' => count($cobros), 'total_esperado' => $total,
			'efectivo_esperado' => $efectivo, 'efectivo_entregado' => $entregado, 'diferencia' => $diferencia, 'idie_caja' => $idie);
	}

	if ($g_idsede <= 0 || $g_ido <= 0 || $g_idus <= 0) { cmOut(false, null, 'Sesion sin sede o usuario. Vuelve a iniciar sesion.'); }
	$tienePermiso = strpos($g_acc, ',' . CM_PERMISO_CAJA . ',') !== false;

	try {
		switch ($op) {
			case 'hay': {
				// Para mostrar/ocultar la opcion del menu. Sin permiso de caja: no se muestra (no es error).
				if (!$tienePermiso) { cmOut(true, array('mostrar' => false, 'pendientes' => 0)); }
				$n = cmFilas($bd, "SELECT COUNT(DISTINCT rp.idregistro_pago) AS n
					FROM pedido_por_confirmar ppc
					JOIN pedido p ON p.idpedido = ppc.idpedido
					JOIN registro_pago rp ON rp.idregistro_pago = p.idregistro_pago
					LEFT JOIN mozo_rendicion_detalle d ON d.idregistro_pago = rp.idregistro_pago
					WHERE ppc.idsede = ? AND ppc.estado = '1' AND ppc.fecha_registro >= NOW() - INTERVAL " . (int)CM_DIAS_PENDIENTE . " DAY
					  AND ppc.origen_accion = 'MOZO' AND ppc.pagado = '1'
					  AND rp.idsede = ppc.idsede AND rp.estado = 0 AND rp.cierre = 0 AND rp.idusuario <> ?
					  AND d.idregistro_pago IS NULL", array($g_idsede, $g_idus));
				$pend = $n ? (int)$n[0]['n'] : 0;
				$mostrar = $pend > 0;
				if (!$mostrar) {
					$hoy = cmFilas($bd, "SELECT 1 AS x FROM mozo_rendicion WHERE idsede = ? AND fecha_hora >= CURDATE() LIMIT 1", array($g_idsede));
					$mostrar = !empty($hoy);
				}
				cmOut(true, array('mostrar' => $mostrar, 'pendientes' => $pend));
			}
		}

		if (!$tienePermiso) { cmOut(false, null, 'No tienes permiso de Caja para recibir cobros de meseros. Solicitalo al administrador.'); }

		switch ($op) {
			case 'pendientes': {
				$cobros = cmCobrosPendientes($bd, $g_idsede, $g_idus);
				cmOut(true, array('mozos' => cmAgruparPorMozo($cobros), 'dias' => CM_DIAS_PENDIENTE));
			}
			case 'rendir': {
				cmOut(true, cmRendir($bd, cmBody(), $g_ido, $g_idsede, $g_idus));
			}
			case 'historial': {
				$b = cmBody();
				$f = isset($b['fecha']) && is_string($b['fecha']) ? $b['fecha'] : '';
				$d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? DateTime::createFromFormat('!Y-m-d', $f) : false;
				if (!$d || $d->format('Y-m-d') !== $f) { $d = new DateTime('today'); }
				$dia = $d->format('Y-m-d');
				$desde = $dia . ' 00:00:00';
				$hasta = $d->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
				$rows = cmFilas($bd, "SELECT r.idmozo_rendicion, DATE_FORMAT(r.fecha_hora, '%Y-%m-%d %H:%i:%s') AS fecha_hora,
						r.idusuario_mozo, IFNULL(NULLIF(TRIM(um.nombres), ''), IFNULL(um.usuario, '')) AS mozo,
						r.idusuario_caja, IFNULL(NULLIF(TRIM(uc.nombres), ''), IFNULL(uc.usuario, '')) AS caja,
						r.cantidad_cobros, (r.total_esperado + 0) AS total_esperado, (r.efectivo_esperado + 0) AS efectivo_esperado,
						(r.efectivo_entregado + 0) AS efectivo_entregado, (r.diferencia + 0) AS diferencia,
						r.detalle_metodos, IFNULL(r.observacion, '') AS observacion, r.idie_caja
					FROM mozo_rendicion r
					LEFT JOIN usuario um ON um.idusuario = r.idusuario_mozo
					LEFT JOIN usuario uc ON uc.idusuario = r.idusuario_caja
					WHERE r.idsede = ? AND r.fecha_hora >= ? AND r.fecha_hora < ?
					ORDER BY r.idmozo_rendicion DESC LIMIT 300", array($g_idsede, $desde, $hasta));
				foreach ($rows as $i => $r) {
					$dm = json_decode((string)$r['detalle_metodos'], true);
					$rows[$i]['detalle_metodos'] = is_array($dm) ? $dm : array();
					foreach (array('idmozo_rendicion', 'idusuario_mozo', 'idusuario_caja', 'cantidad_cobros') as $k) { $rows[$i][$k] = (int)$r[$k]; }
					foreach (array('total_esperado', 'efectivo_esperado', 'efectivo_entregado', 'diferencia') as $k) { $rows[$i][$k] = round((float)$r[$k], 2); }
				}
				cmOut(true, array('fecha' => $dia, 'rendiciones' => $rows));
			}
			default:
				cmOut(false, null, 'Operacion no valida.');
		}
	} catch (CmErrorUsuario $e) {
		cmOut(false, null, $e->getMessage());
	} catch (Exception $e) {
		error_log('log_cobros_meseros.php op=' . $op . ' sede ' . $g_idsede . ': ' . $e->getMessage());
		cmOut(false, null, 'No se pudo completar la operacion. Intenta de nuevo.');
	}

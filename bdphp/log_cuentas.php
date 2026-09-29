<?php
	// Cuentas por pagar (compras a credito) y por cobrar (ventas a credito). Plan: plan/CUENTAS-PAGAR-COBRAR-PLAN.md
	// Archivo NUEVO: la pantalla vieja sigue usando log_005.php / log_009.php sin cambios.
	// Mismas tablas de siempre (compra, compra_pago_cuenta, registro_pago_detalle, cliente_paga_credito*, ingreso_varios)
	// para que el cierre de caja y los reportes existentes sigan funcionando.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
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

	const CTA_TIPO_PAGO_CREDITO = 3;

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
	function ejecutar($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		return $bd->stmt->affected_rows;
	}
	function dinero($n) { return number_format(round((float)$n, 2), 2, '.', ''); }
	function fechaIso($s) {
		if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return null; }
		$d = DateTime::createFromFormat('!Y-m-d', $s);
		return ($d && $d->format('Y-m-d') === $s) ? $d : null;
	}
	class ErrorUsuario extends Exception {}

	if ($g_idsede <= 0 || $g_ido <= 0) { jsonOut(false, null, 'Sesion sin sede'); }

	// Aviso de limite al vender a credito (lo llama el cobro del POS: x-pago / x-pago2).
	// Va ANTES del control de permiso D1: el cajero que vende no necesita acceso a Cuentas.
	// Solo devuelve deuda y limite del cliente de la sede; si algo falla, el POS vende igual.
	if ($op === 'limite-check') {
		try {
			$b = json_decode(file_get_contents('php://input'), true);
			$idc = is_array($b) && isset($b['idcliente']) ? (int)$b['idcliente'] : 0;
			$lim = filas($bd, "SELECT limite FROM cliente_credito_limite WHERE idsede = ? AND idcliente = ?", array($g_idsede, $idc));
			if (!$lim) { jsonOut(true, array('limite' => null)); }
			$d = filas($bd, "SELECT ROUND(SUM(GREATEST(0, (rpd.importe + 0) - (rpd.pagado + 0))), 2) AS debe
				FROM registro_pago rp JOIN registro_pago_detalle rpd ON rpd.idregistro_pago = rp.idregistro_pago
				WHERE rp.idsede = ? AND rp.idcliente = ? AND rp.estado = 0 AND rpd.idtipo_pago = ? AND IFNULL(rpd.estado, 0) = 0",
				array($g_idsede, $idc, CTA_TIPO_PAGO_CREDITO));
			jsonOut(true, array('limite' => (float)$lim[0]['limite'], 'debe' => $d ? (float)$d[0]['debe'] : 0));
		} catch (Exception $e) {
			error_log('log_cuentas.php limite-check: ' . $e->getMessage());
			jsonOut(false, null, 'sin datos');
		}
	}

	// Mismo permiso que la opcion "Cuentas por pagar o cobrar" (D1)
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? $_SESSION['acc'] : '') . ',';
	if (strpos($g_acc, ',D1,') === false) { jsonOut(false, null, 'No tienes permiso de Cuentas por pagar o cobrar. Solicitalo al administrador.'); }

	try {
		switch ($op) {
			case 'init': {
				jsonOut(true, array(
					'tipos_pago' => filas($bd, "SELECT idtipo_pago, descripcion FROM tipo_pago WHERE estado = 0 AND visible = '0' AND idtipo_pago <> ? ORDER BY idtipo_pago", array(CTA_TIPO_PAGO_CREDITO)),
					'proveedores' => filas($bd, "SELECT idproveedor, descripcion FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion", array($g_ido, $g_idsede)),
					'hoy' => date('Y-m-d')
				));
			}

			// ===================== POR PAGAR =====================
			case 'pagar-listar': {
				// a_pagar es el pendiente (el legacy lo va descontando con cada pago)
				$b = leerBody();
				$ver = isset($b['ver']) && in_array($b['ver'], array('pendientes', 'pagadas'), true) ? $b['ver'] : 'pendientes';
				$idprov = isset($b['idproveedor']) ? (int)$b['idproveedor'] : 0;
				$r = filas($bd, "SELECT c.idcompra, c.f_compra, c.f_pago, c.f_ultimo_pago, c.comprobante, c.idproveedor,
						IFNULL(p.descripcion, '') AS proveedor, IFNULL(p.dni, '') AS ruc,
						(c.total + 0) AS total, (c.a_pagar + 0) AS pendiente, c.pagado,
						DATEDIFF(STR_TO_DATE(c.f_pago, '%d/%m/%Y'), CURDATE()) AS dias,
						(SELECT COUNT(*) FROM compra_pago_cuenta pc WHERE pc.idcompra = c.idcompra AND IFNULL(pc.estado, 0) = 0) AS n_pagos
					FROM compra c
					LEFT JOIN proveedor p ON p.idproveedor = c.idproveedor
					WHERE c.idorg = ? AND c.idsede = ? AND c.idtipo_pago = ? AND c.estado = 0 AND c.pagado = ?
					  AND (? = 0 OR c.idproveedor = ?)
					ORDER BY " . ($ver === 'pendientes'
						? "STR_TO_DATE(c.f_pago, '%d/%m/%Y') IS NULL, STR_TO_DATE(c.f_pago, '%d/%m/%Y'), c.idcompra"
						: "c.idcompra DESC") . "
					LIMIT 500", array($g_ido, $g_idsede, CTA_TIPO_PAGO_CREDITO, $ver === 'pendientes' ? 0 : 1, $idprov, $idprov));
				jsonOut(true, $r);
			}
			// Historial de pagos hechos a proveedores (todas las compras), el mas reciente primero
			case 'pagar-pagos': {
				$b = leerBody();
				$idprov = isset($b['idproveedor']) ? (int)$b['idproveedor'] : 0;
				$r = filas($bd, "SELECT pc.idcompra_pago_cuenta, pc.idcompra, pc.fecha, DATE_FORMAT(pc.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_hora,
						(pc.importe + 0) AS importe, IFNULL(tp.descripcion, '') AS tipo_pago, IFNULL(u.usuario, '') AS usuario, IFNULL(pc.nota, '') AS nota,
						c.f_compra, c.comprobante, c.idproveedor, IFNULL(p.descripcion, '') AS proveedor, IFNULL(p.dni, '') AS ruc
					FROM compra_pago_cuenta pc
					JOIN compra c ON c.idcompra = pc.idcompra
					LEFT JOIN proveedor p ON p.idproveedor = c.idproveedor
					LEFT JOIN tipo_pago tp ON tp.idtipo_pago = pc.idtipo_pago
					LEFT JOIN usuario u ON u.idusuario = pc.idusuario
					WHERE c.idorg = ? AND c.idsede = ? AND c.idtipo_pago = ? AND c.estado = 0 AND IFNULL(pc.estado, 0) = 0
					  AND (? = 0 OR c.idproveedor = ?)
					ORDER BY pc.idcompra_pago_cuenta DESC
					LIMIT 500", array($g_ido, $g_idsede, CTA_TIPO_PAGO_CREDITO, $idprov, $idprov));
				jsonOut(true, $r);
			}
			case 'pagar-detalle': {
				$b = leerBody();
				$c = compraCredito($bd, isset($b['idcompra']) ? (int)$b['idcompra'] : 0, $g_idsede);
				$pagos = filas($bd, "SELECT pc.idcompra_pago_cuenta, pc.fecha, DATE_FORMAT(pc.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_hora,
						(pc.importe + 0) AS importe, IFNULL(pc.estado, 0) AS estado, IFNULL(tp.descripcion, '') AS tipo_pago,
						IFNULL(u.usuario, '') AS usuario, IFNULL(pc.nota, '') AS nota,
						DATE_FORMAT(pc.fecha_anula, '%d/%m/%Y %H:%i') AS fecha_anula, IFNULL(ua.usuario, '') AS usuario_anula
					FROM compra_pago_cuenta pc
					LEFT JOIN tipo_pago tp ON tp.idtipo_pago = pc.idtipo_pago
					LEFT JOIN usuario u ON u.idusuario = pc.idusuario
					LEFT JOIN usuario ua ON ua.idusuario = pc.idusuario_anula
					WHERE pc.idcompra = ?
					ORDER BY pc.idcompra_pago_cuenta", array((int)$c['idcompra']));
				jsonOut(true, array('compra' => $c, 'pagos' => $pagos));
			}
			case 'pagar-registrar': {
				$b = leerBody();
				jsonOut(true, conToken($b, function () use ($bd, $b, $g_idsede, $g_us) { return pagarRegistrar($bd, $b, $g_idsede, $g_us); }));
			}
			case 'pagar-anular': {
				$b = leerBody();
				jsonOut(true, pagarAnular($bd, $b, $g_idsede, usuarioAutoriza($bd, $b, $g_ido)));
			}

			// ===================== POR COBRAR =====================
			case 'cobrar-listar': {
				// Deuda por consumo (importe - pagado) de ventas a credito NO anuladas; datos viejos pueden tener
				// cliente_paga_credito descuadrado: la fuente es registro_pago_detalle.
				$b = leerBody();
				$ver = isset($b['ver']) && $b['ver'] === 'todos' ? 'todos' : 'deben';
				$r = filas($bd, "SELECT rp.idcliente, IFNULL(c.nombres, '(cliente eliminado)') AS cliente, IFNULL(c.ruc, '') AS doc, IFNULL(c.telefono, '') AS telefono,
						COUNT(*) AS n_consumos,
						ROUND(SUM(rpd.importe + 0), 2) AS total,
						ROUND(SUM(LEAST(rpd.pagado + 0, rpd.importe + 0)), 2) AS pagado,
						ROUND(SUM(GREATEST(0, (rpd.importe + 0) - (rpd.pagado + 0))), 2) AS debe,
						SUM((rpd.importe + 0) - (rpd.pagado + 0) > 0.009) AS n_impagos,
						DATEDIFF(CURDATE(), MIN(IF((rpd.importe + 0) - (rpd.pagado + 0) > 0.009, STR_TO_DATE(SUBSTRING_INDEX(rp.fecha, ' ', 1), '%d/%m/%Y'), NULL))) AS dias,
						cl.limite
					FROM registro_pago rp
					JOIN registro_pago_detalle rpd ON rpd.idregistro_pago = rp.idregistro_pago
					LEFT JOIN cliente c ON c.idcliente = rp.idcliente
					LEFT JOIN cliente_credito_limite cl ON cl.idsede = rp.idsede AND cl.idcliente = rp.idcliente
					WHERE rp.idsede = ? AND rp.estado = 0 AND rpd.idtipo_pago = ? AND IFNULL(rpd.estado, 0) = 0 AND rp.idcliente > 0
					GROUP BY rp.idcliente" . ($ver === 'deben' ? " HAVING debe > 0.009" : "") . "
					ORDER BY debe DESC, cliente", array($g_idsede, CTA_TIPO_PAGO_CREDITO));
				jsonOut(true, $r);
			}
			// Historial de cobros a clientes de los ultimos 2 meses, el mas reciente primero
			case 'cobrar-cobros': {
				$r = filas($bd, "SELECT d.idcliente_paga_credito_detalle AS idcobro, cc.idcliente, d.fecha_hora, (d.importe + 0) AS importe,
						IFNULL(tp.descripcion, '') AS tipo_pago, IFNULL(u.usuario, '') AS usuario,
						IFNULL(c.nombres, '(cliente eliminado)') AS cliente, IFNULL(c.ruc, '') AS doc, IFNULL(c.telefono, '') AS telefono
					FROM cliente_paga_credito_detalle d
					JOIN cliente_paga_credito cc ON cc.idcliente_paga_credito = d.idcliente_paga_credito
					LEFT JOIN cliente c ON c.idcliente = cc.idcliente
					LEFT JOIN tipo_pago tp ON tp.idtipo_pago = d.idtipo_pago
					LEFT JOIN usuario u ON u.idusuario = d.idusuario
					WHERE cc.idsede = ? AND IFNULL(d.estado, '0') = '0'
					  AND STR_TO_DATE(LEFT(d.fecha_hora, 10), '%Y-%m-%d') >= CURDATE() - INTERVAL 2 MONTH
					ORDER BY d.idcliente_paga_credito_detalle DESC
					LIMIT 500", array($g_idsede));
				jsonOut(true, $r);
			}
			case 'cobrar-detalle': {
				$b = leerBody();
				$idc = isset($b['idcliente']) ? (int)$b['idcliente'] : 0;
				clienteDeLaSede($bd, $idc, $g_ido, $g_idsede);
				jsonOut(true, cobrarDetalle($bd, $idc, $g_idsede));
			}
			case 'cobrar-registrar': {
				$b = leerBody();
				clienteDeLaSede($bd, isset($b['idcliente']) ? (int)$b['idcliente'] : 0, $g_ido, $g_idsede);
				jsonOut(true, conToken($b, function () use ($bd, $b, $g_idsede, $g_us) { return cobrarRegistrar($bd, $b, $g_idsede, $g_us); }));
			}
			case 'cobrar-anular': {
				$b = leerBody();
				jsonOut(true, cobrarAnular($bd, $b, $g_idsede, usuarioAutoriza($bd, $b, $g_ido)));
			}
			case 'cobrar-limite': {
				$b = leerBody();
				$idc = isset($b['idcliente']) ? (int)$b['idcliente'] : 0;
				clienteDeLaSede($bd, $idc, $g_ido, $g_idsede);
				$lim = isset($b['limite']) && $b['limite'] !== null && $b['limite'] !== '' ? round((float)$b['limite'], 2) : null;
				if ($lim === null) {
					ejecutar($bd, "DELETE FROM cliente_credito_limite WHERE idsede = ? AND idcliente = ?", array($g_idsede, $idc));
				} else {
					if ($lim < 0 || $lim > 1000000) { throw new ErrorUsuario('Limite no valido'); }
					ejecutar($bd, "INSERT INTO cliente_credito_limite (idsede, idcliente, limite, idusuario) VALUES (?, ?, ?, ?)
						ON DUPLICATE KEY UPDATE limite = VALUES(limite), idusuario = VALUES(idusuario)", array($g_idsede, $idc, $lim, $g_us));
				}
				jsonOut(true, array('idcliente' => $idc, 'limite' => $lim));
			}

			default:
				jsonOut(false, null, 'Operacion no valida');
		}
	} catch (ErrorUsuario $e) {
		jsonOut(false, null, $e->getMessage());
	} catch (Exception $e) {
		error_log('log_cuentas.php op=' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'Error al procesar la solicitud');
	}

	// Doble clic / reintento: el mismo token devuelve lo que ya se registro (la sesion atiende una peticion a la vez)
	function conToken($b, $registrar) {
		$token = isset($b['token']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$b['token']) : '';
		if ($token !== '' && isset($_SESSION['cta_tokens'][$token])) { return $_SESSION['cta_tokens'][$token]; }
		$r = $registrar();
		if ($token !== '') {
			if (!isset($_SESSION['cta_tokens']) || count($_SESSION['cta_tokens']) > 50) { $_SESSION['cta_tokens'] = array(); }
			$_SESSION['cta_tokens'][$token] = $r;
		}
		return $r;
	}

	// Un cliente se ve si es de la empresa, esta registrado en esta sede o tiene ventas en esta sede
	// (hay clientes legacy con otra idorg que igual compran aqui)
	function clienteDeLaSede($bd, $idc, $ido, $idsede) {
		$ok = filas($bd, "SELECT c.idcliente FROM cliente c WHERE c.idcliente = ? AND (c.idorg = ?
				OR EXISTS (SELECT 1 FROM cliente_sede cs WHERE cs.idcliente = c.idcliente AND cs.idsede = ?)
				OR EXISTS (SELECT 1 FROM registro_pago rp WHERE rp.idcliente = c.idcliente AND rp.idsede = ?))",
			array($idc, $ido, $idsede, $idsede));
		if (!$ok) { throw new ErrorUsuario('Cliente no encontrado'); }
	}

	// ---------------- por cobrar ----------------
	function cobrarDetalle($bd, $idc, $idsede) {
		$cli = filas($bd, "SELECT c.idcliente, c.nombres AS cliente, IFNULL(c.ruc, '') AS doc, IFNULL(c.telefono, '') AS telefono,
				IFNULL(c.direccion, '') AS direccion, cl.limite
			FROM cliente c LEFT JOIN cliente_credito_limite cl ON cl.idsede = ? AND cl.idcliente = c.idcliente
			WHERE c.idcliente = ?", array($idsede, $idc));
		if (!$cli) { throw new ErrorUsuario('Cliente no encontrado'); }
		$consumos = filas($bd, "SELECT rpd.idregistro_pago_detalle, rp.idregistro_pago, SUBSTRING_INDEX(rp.fecha, ' ', 1) AS fecha,
				(rpd.importe + 0) AS importe, LEAST(rpd.pagado + 0, rpd.importe + 0) AS pagado,
				GREATEST(0, (rpd.importe + 0) - (rpd.pagado + 0)) AS debe,
				DATEDIFF(CURDATE(), STR_TO_DATE(SUBSTRING_INDEX(rp.fecha, ' ', 1), '%d/%m/%Y')) AS dias,
				IFNULL(ce.external_id, '') AS external_id, IFNULL(rp.correlativo, '') AS correlativo
			FROM registro_pago rp
			JOIN registro_pago_detalle rpd ON rpd.idregistro_pago = rp.idregistro_pago
			LEFT JOIN ce ON ce.idce = rp.idce
			WHERE rp.idsede = ? AND rp.idcliente = ? AND rp.estado = 0 AND rpd.idtipo_pago = ? AND IFNULL(rpd.estado, 0) = 0
			ORDER BY (rpd.importe + 0) - (rpd.pagado + 0) <= 0.009, STR_TO_DATE(SUBSTRING_INDEX(rp.fecha, ' ', 1), '%d/%m/%Y'), rpd.idregistro_pago_detalle
			LIMIT 500", array($idsede, $idc, CTA_TIPO_PAGO_CREDITO));
		$cobros = filas($bd, "SELECT d.idcliente_paga_credito_detalle AS idcobro, d.fecha_hora, (d.importe + 0) AS importe, IFNULL(d.estado, '0') AS estado,
				IFNULL(tp.descripcion, '') AS tipo_pago, IFNULL(u.usuario, '') AS usuario, d.idingreso_varios,
				(SELECT COUNT(*) FROM cliente_cobro_aplicacion a WHERE a.idcliente_paga_credito_detalle = d.idcliente_paga_credito_detalle) AS n_aplic,
				iv.cierre AS caja_cerrada, DATE_FORMAT(d.fecha_anula, '%d/%m/%Y %H:%i') AS fecha_anula, IFNULL(ua.usuario, '') AS usuario_anula
			FROM cliente_paga_credito cc
			JOIN cliente_paga_credito_detalle d ON d.idcliente_paga_credito = cc.idcliente_paga_credito
			LEFT JOIN tipo_pago tp ON tp.idtipo_pago = d.idtipo_pago
			LEFT JOIN usuario u ON u.idusuario = d.idusuario
			LEFT JOIN usuario ua ON ua.idusuario = d.idusuario_anula
			LEFT JOIN ingreso_varios iv ON iv.idingreso_varios = d.idingreso_varios
			WHERE cc.idcliente = ? AND cc.idsede = ?
			ORDER BY d.idcliente_paga_credito_detalle DESC LIMIT 200", array($idc, $idsede));
		return array('cliente' => $cli[0], 'consumos' => $consumos, 'cobros' => $cobros);
	}

	// Cobro a un cliente: se aplica a lo mas antiguo (o a los consumos elegidos) y entra a caja como "otro ingreso",
	// igual que la pantalla vieja (ingreso_varios), pero todo en UNA transaccion.
	function cobrarRegistrar($bd, $b, $idsede, $us) {
		$idc = isset($b['idcliente']) ? (int)$b['idcliente'] : 0;
		$importe = round(isset($b['importe']) ? (float)$b['importe'] : 0, 2);
		$idtp = isset($b['idtipo_pago']) ? (int)$b['idtipo_pago'] : 0;
		$elegidos = isset($b['ids']) && is_array($b['ids']) ? array_map('intval', $b['ids']) : array();
		if ($importe <= 0) { throw new ErrorUsuario('Escribe cuanto te pagaron'); }
		if ($idtp === CTA_TIPO_PAGO_CREDITO || !filas($bd, "SELECT idtipo_pago FROM tipo_pago WHERE idtipo_pago = ? AND estado = 0", array($idtp))) {
			throw new ErrorUsuario('Elige con que te pagaron');
		}
		$cli = filas($bd, "SELECT nombres FROM cliente WHERE idcliente = ?", array($idc));
		if (!$cli) { throw new ErrorUsuario('Cliente no encontrado'); }
		$nombre = mb_substr((string)$cli[0]['nombres'], 0, 100, 'UTF-8');

		$bd->bd->begin_transaction();
		try {
			// consumos impagos, del mas antiguo al mas nuevo; FOR UPDATE: dos cobros a la vez no se pisan
			$rows = filas($bd, "SELECT rpd.idregistro_pago_detalle AS id, (rpd.importe + 0) AS importe, (rpd.pagado + 0) AS pagado
				FROM registro_pago rp JOIN registro_pago_detalle rpd ON rpd.idregistro_pago = rp.idregistro_pago
				WHERE rp.idsede = ? AND rp.idcliente = ? AND rp.estado = 0 AND rpd.idtipo_pago = ? AND IFNULL(rpd.estado, 0) = 0
				  AND (rpd.importe + 0) - (rpd.pagado + 0) > 0.009
				ORDER BY STR_TO_DATE(SUBSTRING_INDEX(rp.fecha, ' ', 1), '%d/%m/%Y'), rpd.idregistro_pago_detalle
				FOR UPDATE", array($idsede, $idc, CTA_TIPO_PAGO_CREDITO));
			if ($elegidos) { $rows = array_values(array_filter($rows, function ($r) use ($elegidos) { return in_array((int)$r['id'], $elegidos, true); })); }
			$deuda = round(array_sum(array_map(function ($r) { return max(0, $r['importe'] - $r['pagado']); }, $rows)), 2);
			if (!$rows || $deuda <= 0) { throw new ErrorUsuario('Este cliente no tiene deuda pendiente' . ($elegidos ? ' en los consumos elegidos' : '')); }
			if ($importe > $deuda + 0.009) { throw new ErrorUsuario('El cobro (S/ ' . dinero($importe) . ') es mayor a lo que debe (S/ ' . dinero($deuda) . ')'); }

			// cabecera legacy por cliente+sede (importe = ultimo cobro, debe = saldo), como la pantalla vieja
			$cab = filas($bd, "SELECT idcliente_paga_credito FROM cliente_paga_credito WHERE idcliente = ? AND idsede = ? ORDER BY idcliente_paga_credito LIMIT 1 FOR UPDATE", array($idc, $idsede));
			$deudaTotal = round(array_sum(array_map(function ($r) { return max(0, $r['importe'] - $r['pagado']); },
				filas($bd, "SELECT (rpd.importe + 0) AS importe, (rpd.pagado + 0) AS pagado FROM registro_pago rp
					JOIN registro_pago_detalle rpd ON rpd.idregistro_pago = rp.idregistro_pago
					WHERE rp.idsede = ? AND rp.idcliente = ? AND rp.estado = 0 AND rpd.idtipo_pago = ? AND IFNULL(rpd.estado, 0) = 0",
					array($idsede, $idc, CTA_TIPO_PAGO_CREDITO)))), 2);
			$saldo = max(0, round($deudaTotal - $importe, 2));
			if ($cab) {
				$idcab = (int)$cab[0]['idcliente_paga_credito'];
				ejecutar($bd, "UPDATE cliente_paga_credito SET importe = ?, debe = ?, fecha = CURDATE() WHERE idcliente_paga_credito = ?", array(dinero($importe), dinero($saldo), $idcab));
			} else {
				ejecutar($bd, "INSERT INTO cliente_paga_credito (idcliente, importe, debe, fecha, idsede) VALUES (?, ?, ?, CURDATE(), ?)", array($idc, dinero($importe), dinero($saldo), $idsede));
				$idcab = (int)$bd->bd->insert_id;
			}

			// ingreso a caja (la cierra el cierre de caja del usuario que cobra)
			ejecutar($bd, "INSERT INTO ingreso_varios (idsede, idusuario, idcliente, idtipo_pago, fecha, concepto, importe, nom_cliente)
				VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)", array($idsede, $us, $idc, $idtp, mb_substr('PAGO DE CUENTA - ' . $nombre, 0, 255, 'UTF-8'), dinero($importe), $nombre));
			$idiv = (int)$bd->bd->insert_id;

			ejecutar($bd, "INSERT INTO cliente_paga_credito_detalle (idcliente_paga_credito, importe, fecha_hora, idusuario, idtipo_pago, estado, idingreso_varios)
				VALUES (?, ?, NOW(), ?, ?, '0', ?)", array($idcab, dinero($importe), $us, $idtp, $idiv));
			$idcobro = (int)$bd->bd->insert_id;

			// aplicar a cada consumo
			$resto = $importe;
			$aplic = array();
			foreach ($rows as $r) {
				if ($resto <= 0.004) { break; }
				$debe = round(max(0, $r['importe'] - $r['pagado']), 2);
				$a = round(min($debe, $resto), 2);
				if ($a <= 0) { continue; }
				$nuevoPagado = round($r['pagado'] + $a, 2);
				ejecutar($bd, "UPDATE registro_pago_detalle SET pagado = ?, flag_pagado = ? WHERE idregistro_pago_detalle = ?",
					array(dinero($nuevoPagado), ($r['importe'] - $nuevoPagado) <= 0.009 ? '1' : '0', (int)$r['id']));
				ejecutar($bd, "INSERT INTO cliente_cobro_aplicacion (idcliente_paga_credito_detalle, idregistro_pago_detalle, importe) VALUES (?, ?, ?)",
					array($idcobro, (int)$r['id'], dinero($a)));
				$aplic[] = array('idregistro_pago_detalle' => (int)$r['id'], 'importe' => $a);
				$resto = round($resto - $a, 2);
			}
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
		return array('idcobro' => $idcobro, 'aplicado' => $aplic, 'saldo' => $saldo, 'idingreso_varios' => $idiv);
	}

	// Quien autoriza una anulacion: usuario + clave de la misma org con permiso Pe1 ("Eliminar pedidos"),
	// el mismo que pide historial de ventas para anular (x-pass). Se valida aqui, no solo en el navegador.
	function usuarioAutoriza($bd, $b, $ido) {
		$u = trim(isset($b['u']) ? (string)$b['u'] : '');
		$p = isset($b['p']) ? (string)$b['p'] : '';
		if ($u === '' || $p === '') { throw new ErrorUsuario('Escribe el usuario y la clave de quien autoriza.'); }
		$r = filas($bd, "SELECT idusuario, CONCAT(IFNULL(per, ''), 'Rol', IFNULL(rol, '')) AS per FROM usuario
			WHERE idorg = ? AND usuario = ? AND pass = ? AND estado = 0 LIMIT 1", array($ido, $u, $p));
		if (!$r) { throw new ErrorUsuario('Usuario o clave incorrectos.'); }
		if (!preg_match('/Pe1(?!\d)/', (string)$r[0]['per'])) { throw new ErrorUsuario('Ese usuario no está autorizado para anular (le falta el permiso "Eliminar pedidos").'); }
		return (int)$r[0]['idusuario'];
	}

	// Anula un cobro hecho con esta pantalla: devuelve la deuda a cada consumo y saca el ingreso de caja.
	// Solo si esa caja sigue abierta (ingreso_varios.cierre = 0).
	function cobrarAnular($bd, $b, $idsede, $us) {
		$idcobro = isset($b['idcobro']) ? (int)$b['idcobro'] : 0;
		$bd->bd->begin_transaction();
		try {
			$d = filas($bd, "SELECT d.idcliente_paga_credito_detalle, d.idcliente_paga_credito, IFNULL(d.estado, '0') AS estado, d.idingreso_varios,
					(d.importe + 0) AS importe, cc.idcliente
				FROM cliente_paga_credito_detalle d JOIN cliente_paga_credito cc ON cc.idcliente_paga_credito = d.idcliente_paga_credito AND cc.idsede = ?
				WHERE d.idcliente_paga_credito_detalle = ? FOR UPDATE", array($idsede, $idcobro));
			if (!$d) { throw new ErrorUsuario('Cobro no encontrado'); }
			$d = $d[0];
			if ((string)$d['estado'] !== '0') { throw new ErrorUsuario('Este cobro ya esta anulado'); }
			$ap = filas($bd, "SELECT idregistro_pago_detalle, (importe + 0) AS importe FROM cliente_cobro_aplicacion WHERE idcliente_paga_credito_detalle = ?", array($idcobro));
			if (!$ap) { throw new ErrorUsuario('Este cobro se hizo con la pantalla anterior y no se puede anular aqui (no se sabe a que consumos se aplico).'); }
			if ($d['idingreso_varios']) {
				$iv = filas($bd, "SELECT cierre, estado FROM ingreso_varios WHERE idingreso_varios = ? FOR UPDATE", array((int)$d['idingreso_varios']));
				if ($iv && (int)$iv[0]['cierre'] === 1) { throw new ErrorUsuario('No se puede anular: la caja donde entró este cobro ya se cerró.'); }
				ejecutar($bd, "UPDATE ingreso_varios SET estado = 1 WHERE idingreso_varios = ? AND cierre = 0", array((int)$d['idingreso_varios']));
			}
			foreach ($ap as $a) {
				ejecutar($bd, "UPDATE registro_pago_detalle SET pagado = GREATEST(0, ROUND((pagado + 0) - ?, 2)), flag_pagado = '0' WHERE idregistro_pago_detalle = ?",
					array((float)$a['importe'], (int)$a['idregistro_pago_detalle']));
			}
			ejecutar($bd, "UPDATE cliente_paga_credito_detalle SET estado = '1', idusuario_anula = ?, fecha_anula = NOW() WHERE idcliente_paga_credito_detalle = ?", array($us, $idcobro));
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
		return array('idcobro' => $idcobro, 'idcliente' => (int)$d['idcliente']);
	}

	// ---------------- por pagar ----------------
	function compraCredito($bd, $id, $idsede, $bloquear = false) {
		$r = filas($bd, "SELECT c.idcompra, c.f_compra, c.f_pago, c.comprobante, c.idproveedor, IFNULL(p.descripcion, '') AS proveedor,
				(c.total + 0) AS total, (c.a_pagar + 0) AS pendiente, c.pagado, c.estado
			FROM compra c LEFT JOIN proveedor p ON p.idproveedor = c.idproveedor
			WHERE c.idcompra = ? AND c.idsede = ? AND c.idtipo_pago = ?" . ($bloquear ? " FOR UPDATE" : ""),
			array($id, $idsede, CTA_TIPO_PAGO_CREDITO));
		if (!$r) { throw new ErrorUsuario('Compra a credito no encontrada'); }
		return $r[0];
	}

	// Pago (parcial o total) a un proveedor. No sale de caja (decision 2026-09-27).
	function pagarRegistrar($bd, $b, $idsede, $us) {
		$id = isset($b['idcompra']) ? (int)$b['idcompra'] : 0;
		$importe = round(isset($b['importe']) ? (float)$b['importe'] : 0, 2);
		$idtp = isset($b['idtipo_pago']) ? (int)$b['idtipo_pago'] : 0;
		$fecha = fechaIso(isset($b['fecha']) ? $b['fecha'] : '');
		$nota = mb_substr(trim(isset($b['nota']) ? (string)$b['nota'] : ''), 0, 150, 'UTF-8');
		if ($importe <= 0) { throw new ErrorUsuario('Escribe cuanto pagaste'); }
		if (!$fecha) { throw new ErrorUsuario('Fecha de pago no valida'); }
		if ($fecha > new DateTime('tomorrow')) { throw new ErrorUsuario('La fecha de pago no puede ser futura'); }
		if ($idtp === CTA_TIPO_PAGO_CREDITO || !filas($bd, "SELECT idtipo_pago FROM tipo_pago WHERE idtipo_pago = ? AND estado = 0", array($idtp))) {
			throw new ErrorUsuario('Elige con que pagaste');
		}
		$bd->bd->begin_transaction();
		try {
			$c = compraCredito($bd, $id, $idsede, true); // bloquea la fila: dos pagos a la vez no se pisan
			if ((int)$c['estado'] !== 0) { throw new ErrorUsuario('La compra esta anulada'); }
			if ((int)$c['pagado'] === 1) { throw new ErrorUsuario('Esta compra ya esta pagada'); }
			$pend = round((float)$c['pendiente'], 2);
			if ($importe > $pend + 0.009) { throw new ErrorUsuario('El pago (S/ ' . dinero($importe) . ') es mayor a lo que se debe (S/ ' . dinero($pend) . ')'); }
			$nuevo = max(0, round($pend - $importe, 2));
			$pagada = $nuevo < 0.01;
			ejecutar($bd, "INSERT INTO compra_pago_cuenta (idcompra, fecha, importe, estado, idtipo_pago, idusuario, fecha_hora, nota)
				VALUES (?, ?, ?, 0, ?, ?, NOW(), ?)", array($id, $fecha->format('d/m/Y'), dinero($importe), $idtp, $us, $nota));
			ejecutar($bd, "UPDATE compra SET a_pagar = ?, pagado = ?, f_ultimo_pago = ? WHERE idcompra = ?",
				array(dinero($nuevo), $pagada ? 1 : 0, $fecha->format('d/m/Y'), $id));
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
		return array('idcompra' => $id, 'pendiente' => $nuevo, 'pagada' => $pagada);
	}

	// Anula un pago a proveedor: el importe vuelve a quedar pendiente.
	function pagarAnular($bd, $b, $idsede, $us) {
		$idp = isset($b['idcompra_pago_cuenta']) ? (int)$b['idcompra_pago_cuenta'] : 0;
		$bd->bd->begin_transaction();
		try {
			$p = filas($bd, "SELECT pc.idcompra, (pc.importe + 0) AS importe, IFNULL(pc.estado, 0) AS estado
				FROM compra_pago_cuenta pc JOIN compra c ON c.idcompra = pc.idcompra AND c.idsede = ?
				WHERE pc.idcompra_pago_cuenta = ? FOR UPDATE", array($idsede, $idp));
			if (!$p) { throw new ErrorUsuario('Pago no encontrado'); }
			if ((int)$p[0]['estado'] !== 0) { throw new ErrorUsuario('Este pago ya esta anulado'); }
			$c = compraCredito($bd, (int)$p[0]['idcompra'], $idsede, true);
			$nuevo = min((float)$c['total'], round((float)$c['pendiente'] + (float)$p[0]['importe'], 2));
			ejecutar($bd, "UPDATE compra_pago_cuenta SET estado = 1, idusuario_anula = ?, fecha_anula = NOW() WHERE idcompra_pago_cuenta = ?", array($us, $idp));
			ejecutar($bd, "UPDATE compra SET a_pagar = ?, pagado = 0 WHERE idcompra = ?", array(dinero($nuevo), (int)$c['idcompra']));
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
		return array('idcompra' => (int)$c['idcompra'], 'pendiente' => $nuevo);
	}

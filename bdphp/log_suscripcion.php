<?php
	// Aviso de pago de la suscripcion (F9 de restobar-consola). Solo para el ADMINISTRADOR (rol = 1):
	// mozos y cajeros no ven nada. Lee lo que gestiona la consola en la misma BD (sus_*, con_config).
	// Si la consola no esta desplegada (faltan tablas) responde "inactivo" y el POS sigue como antes.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : null;
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);

	function jsonOut($ok, $datos = null, $error = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error));
		exit;
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
	function esAdmin() {
		return (int)(isset($_SESSION['rol']) ? $_SESSION['rol'] : 0) === 1;
	}
	function config($bd) {
		$c = array();
		foreach (filas($bd, "SELECT clave, valor FROM con_config WHERE clave IN ('cobranza_consola_activa','consola_url','job_dias_aviso','job_bloqueo','job_dias_bloqueo','reconexion_monto')") as $f) {
			$c[$f['clave']] = $f['valor'];
		}
		return $c;
	}
	/** Token del link de pago de la org (lo crea si no existe; mismo formato que la consola: 32 hex). */
	function tokenPortal($bd, $idorg) {
		$f = filas($bd, "SELECT token_portal FROM sus_cuenta WHERE idorg = ?", array($idorg));
		if (count($f) && $f[0]['token_portal']) { return $f[0]['token_portal']; }
		$token = bin2hex(random_bytes(16));
		ejecutar($bd, "INSERT INTO sus_cuenta (idorg, token_portal) VALUES (?, ?)
			ON DUPLICATE KEY UPDATE token_portal = IFNULL(token_portal, VALUES(token_portal))", array($idorg, $token));
		$f = filas($bd, "SELECT token_portal FROM sus_cuenta WHERE idorg = ?", array($idorg));
		return $f[0]['token_portal'];
	}

	switch ($op) {
		case 'estado':
			$inactivo = array('activa' => false, 'esAdmin' => esAdmin());
			if (!esAdmin() || $g_ido <= 0) { jsonOut(true, $inactivo); }
			try {
				$cfg = config($bd);
				if (!isset($cfg['cobranza_consola_activa']) || $cfg['cobranza_consola_activa'] !== '1') { jsonOut(true, $inactivo); }
				$urlBase = isset($cfg['consola_url']) ? rtrim($cfg['consola_url'], '/') : '';
				if (!preg_match('#^https?://#', $urlBase)) { jsonOut(true, $inactivo); }

				// "Pagado hasta" de la org = el menor de sus sedes activas (misma regla que la consola).
				// Solo orgs con al menos una sede en PRODUCCION (las de PRUEBAS no se cobran).
				$s = filas($bd, "
					SELECT MIN(IF(ss.ultimo_pago >= '2000-01-01', ss.ultimo_pago, NULL)) AS pagado_hasta,
					       SUM(s.tipo = 'PRODUCCION') AS en_produccion
					FROM sede s
					LEFT JOIN sede_estado se ON se.idsede = s.idsede
					LEFT JOIN sede_suscripcion ss ON ss.idsede_suscripcion = (SELECT MAX(y.idsede_suscripcion) FROM sede_suscripcion y WHERE y.idsede = s.idsede)
					WHERE s.idorg = ? AND s.estado = 0 AND IFNULL(se.is_baja, '0') = '0'", array($g_ido));
				$link = $urlBase . '/p/' . tokenPortal($bd, $g_ido);
				$base = array('activa' => true, 'esAdmin' => true, 'link' => $link, 'nivel' => 'ninguno');
				if (!count($s) || !$s[0]['pagado_hasta'] || (int)$s[0]['en_produccion'] === 0) { jsonOut(true, $base); }

				$pagadoHasta = $s[0]['pagado_hasta'];
				$dias = (int)filas($bd, "SELECT DATEDIFF(?, CURDATE()) AS d", array($pagadoHasta))[0]['d']; // >0 faltan, <0 vencido
				$diasAviso = isset($cfg['job_dias_aviso']) ? (int)$cfg['job_dias_aviso'] : 5;
				$diasBloqueo = isset($cfg['job_dias_bloqueo']) ? (int)$cfg['job_dias_bloqueo'] : 10;

				$cobro = filas($bd, "SELECT monto, tipo, intentos_auto FROM sus_cobro WHERE idorg = ? AND estado = 'pendiente' ORDER BY idcobro DESC LIMIT 1", array($g_ido));
				$cuenta = filas($bd, "SELECT auto_renovar, card_token IS NOT NULL AS tarjeta,
				                             IFNULL(card_vence < DATE_FORMAT(CURDATE(), '%Y-%m'), 0) AS vencida
				                      FROM sus_cuenta WHERE idorg = ?", array($g_ido));
				$auto = count($cuenta) && (int)$cuenta[0]['auto_renovar'] === 1 && (int)$cuenta[0]['tarjeta'] === 1;

				// Problema con la renovacion automatica: el cobro a la tarjeta ya se intento y fallo, o la tarjeta vencio.
				$problemaTarjeta = null;
				if ($auto && count($cuenta) && (int)$cuenta[0]['vencida'] === 1) { $problemaTarjeta = 'vencida'; }
				elseif ($auto && count($cobro) && (int)$cobro[0]['intentos_auto'] > 0) { $problemaTarjeta = 'rechazada'; }

				// El aviso solo aparece cerca del vencimiento: quien pago 3/6/12 meses no ve nada hasta los
				// ultimos dias de su periodo. Con renovacion automatica por tarjeta NO se avisa (se cobra solo),
				// salvo que la tarjeta haya sido rechazada o este vencida.
				if ($dias > $diasAviso) { $nivel = 'ninguno'; }
				elseif ($auto && !$problemaTarjeta) { $nivel = 'ninguno'; }
				elseif ($dias >= 0) { $nivel = 'aviso'; }
				elseif ($dias >= -5) { $nivel = 'vencido'; }
				else { $nivel = 'urgente'; }

				$fechaBloqueo = null;
				if (isset($cfg['job_bloqueo']) && $cfg['job_bloqueo'] === '1') {
					$fechaBloqueo = filas($bd, "SELECT DATE_ADD(?, INTERVAL ? DAY) AS f", array($pagadoHasta, $diasBloqueo + 1))[0]['f'];
				}

				jsonOut(true, array_merge($base, array(
					'nivel' => $nivel,
					'pagadoHasta' => $pagadoHasta,
					'dias' => $dias,
					'monto' => count($cobro) ? $cobro[0]['monto'] : null,
					'autoRenovar' => $auto,
					'problemaTarjeta' => $problemaTarjeta,
					'fechaBloqueo' => $fechaBloqueo,
					// Cargo por reconexion si el servicio llega a suspenderse (0 = sin cargo): se avisa ANTES.
					'reconexion' => isset($cfg['reconexion_monto']) ? (float)$cfg['reconexion_monto'] : 0
				)));
			} catch (Exception $e) {
				// Consola no desplegada (tablas sus_*/con_config ausentes) u otro error: el POS sigue como antes.
				jsonOut(true, $inactivo);
			}
			break;

		case 'modo':
			// Para cualquier usuario: si el aviso nuevo esta activo, x-info-status NO muestra el aviso viejo
			// (el modal con cuenta regresiva que veia todo el personal).
			try {
				$cfg = config($bd);
				jsonOut(true, array('nuevo' => isset($cfg['cobranza_consola_activa']) && $cfg['cobranza_consola_activa'] === '1'));
			} catch (Exception $e) {
				jsonOut(true, array('nuevo' => false));
			}
			break;

		default:
			jsonOut(false, null, 'Operacion no valida');
	}

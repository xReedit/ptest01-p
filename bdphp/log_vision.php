<?php
	// Camaras IA (papaya-vision): lo que el modulo app/camaras necesita del legacy. El servidor de camaras tiene su
	// propia API; aqui solo lo que vive en la MySQL del POS.
	// Patron de log_pedido_confirmar.php: SecurityGuard (sin sesion → 401 en JSON), sede y usuario SOLO de la sesion
	// PHP (nunca de $_POST/$_GET), session_write_close, consultas preparadas y solo pedidos JSON (paginas falsas).
	// Respuesta: {"success": bool, "data": ..., "error": codigo|null}. op desconocido → 400 op_invalida.
	// Ops (?op=): horario. Cada op nueva va como un case del switch de abajo, con su funcion vis<Nombre>().
	// T21 (Control de pedidos y Anfitrion, app/shared/vision.mesas.js): estado, silenciar, resolver. Son un proxy al
	// servidor Go (/interno/v1, clave "legacy" VISION_INTERNO_KEY de private/vision_secrets.php); la sede y el usuario
	// salen de la sesion PHP. Sin org_modulo camaras_ia activo no se llama al servidor Go. Sus errores van con HTTP 200
	// ({"success":false,"error":"<codigo>"}) para que el JS de las pantallas de cobro los maneje sin excepcion.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');

	function visOut($ok, $data = null, $error = null, $http = 200) {
		http_response_code($http);
		echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error));
		exit;
	}

	// Paginas falsas (CSRF): el modulo siempre manda JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		visOut(false, null, 'pedido_invalido', 415);
	}
	$op = isset($_GET['op']) ? (string)$_GET['op'] : '';
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_idus = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);
	$g_idorg = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	// acc es "A1,C6,E2," (a veces con ",," o espacios): con comas a los lados E2 no casa con un E20
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '') . ',';
	// Solo se lee la sesion: soltar el candado para no frenar las otras pestanas del POS.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	if ($g_idsede <= 0 || $g_idus <= 0) { visOut(false, null, 'no_session', 401); }
	// Mismo permiso que el SSO de Camaras IA (log.php op=-4003): opcion "Cámaras IA", codigo E2. Solo para la
	// pantalla del modulo (horario): estado/silenciar/resolver son de Control de pedidos y Anfitrion, que usan
	// cajeros y anfitriones sin E2 (decision D1: cualquier usuario con sesion en la sede).
	if ($op === 'horario' && strpos($g_acc, ',E2,') === false) { visOut(false, null, 'sin_permiso', 403); }

	/**
	 * Horario de trabajo de la sede (sede_horario_trabajo con estado=0, el activo) tal cual lo guarda el POS:
	 * numdia "2,3,4," (1 = domingo … 7 = sabado), de y a. El JS (app/camaras/js/parametros.js) lo convierte.
	 */
	function visHorario($bd, $idsede) {
		$bd->prepare('SELECT numdia, de, a FROM sede_horario_trabajo WHERE idsede = ? AND estado = 0 ORDER BY idsede_horario_trabajo');
		$bd->execute(array($idsede));
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		$filas = $bd->fetchAll();
		if (!$filas) { $filas = array(); }
		$r = array();
		foreach ($filas as $f) {
			$r[] = array('numdia' => (string)$f['numdia'], 'de' => (string)$f['de'], 'a' => (string)$f['a']);
		}
		return $r;
	}

	/** Camaras IA activo en la organizacion (org_modulo, el mismo gate que el SSO de log.php op=-4003). */
	function visModuloActivo($bd, $idorg) {
		if ($idorg <= 0) { return false; }
		$bd->prepare("SELECT 1 FROM org_modulo WHERE idorg = ? AND modulo = 'camaras_ia' AND activo = 1 LIMIT 1");
		$bd->execute(array($idorg));
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		return $bd->fetchColumn() !== null;
	}

	/**
	 * URL y clave de /interno/v1 (private/vision_secrets.php; private/ es publico en nginx: solo define constantes).
	 * VISION_INTERNO_URL es opcional (otra ruta al servidor Go desde este PHP); si no, VISION_URL, la del SSO.
	 * null si falta algo: no se llama a nada.
	 */
	function visInternoConfig() {
		@include_once __DIR__ . '/../private/vision_secrets.php';
		$clave = defined('VISION_INTERNO_KEY') ? (string)VISION_INTERNO_KEY : '';
		$url = defined('VISION_INTERNO_URL') ? (string)VISION_INTERNO_URL : (defined('VISION_URL') ? (string)VISION_URL : '');
		if ($clave === '' || !preg_match('#^https?://#i', $url)) { return null; }
		return array('clave' => $clave, 'url' => rtrim($url, '/'));
	}

	/**
	 * Llama al servidor Go: $ruta es lo que va despues de /interno/v1/ y la sede va en la query. Con $cuerpo, POST JSON.
	 * Devuelve array('ok' => bool, 'data' => ..., 'error' => codigo). Nunca deja la clave en el log.
	 */
	function visInterno($cfg, $ruta, $idsede, $cuerpo = null) {
		$headers = array('x-api-key: ' . $cfg['clave'], 'Accept: application/json');
		$ch = curl_init($cfg['url'] . '/interno/v1/' . $ruta . '?idsede=' . (int)$idsede);
		if ($cuerpo !== null) {
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);   // el Go responde en <= 2 s: con el Go colgado no retener un worker que atiende cobros
		$rpt = curl_exec($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errCurl = $rpt === false ? curl_error($ch) : '';
		curl_close($ch);
		if ($rpt === false) {
			error_log('[log_vision] ' . $ruta . ' sede ' . $idsede . ': ' . $errCurl);
			return array('ok' => false, 'data' => null, 'error' => 'sin_conexion');
		}
		$j = json_decode($rpt, true);
		if (!is_array($j)) {
			error_log('[log_vision] ' . $ruta . ' sede ' . $idsede . ': HTTP ' . $http . ' respuesta no JSON');
			return array('ok' => false, 'data' => null, 'error' => 'respuesta_invalida');
		}
		if (!empty($j['success']) && $http >= 200 && $http < 300) {
			return array('ok' => true, 'data' => isset($j['data']) ? $j['data'] : null, 'error' => null);
		}
		$codigo = isset($j['error']) && is_string($j['error']) && preg_match('/^[a-z0-9_]{1,64}$/', $j['error']) ? $j['error'] : 'error_servidor';
		// 404/409 son normales (la alerta ya se cerro, la mesa ya se vacio); lo demas es configuracion o caida
		if ($http !== 404 && $http !== 409) { error_log('[log_vision] ' . $ruta . ' sede ' . $idsede . ': HTTP ' . $http . ' ' . $codigo); }
		return array('ok' => false, 'data' => null, 'error' => $codigo);
	}

	/** {"id": N} del cuerpo (entero positivo, numero o texto de digitos); 0 si no. */
	function visIdDelCuerpo() {
		$b = json_decode(file_get_contents('php://input'), true);
		$v = is_array($b) && isset($b['id']) ? $b['id'] : null;
		if (!is_int($v) && !is_string($v)) { return 0; }
		$id = filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		return $id === false ? 0 : (int)$id;
	}

	/**
	 * estado / silenciar / resolver. Orden: org_modulo (sin el modulo, el estado es {"activo": false} y nada mas),
	 * configuracion (sin la clave, sin_configurar) y recien el servidor Go. Responde y termina.
	 */
	function visCamaras($op, $idorg, $idsede, $idus) {
		include_once __DIR__ . '/ManejoBD.php';
		try {
			$activo = visModuloActivo(new xManejoBD('restobar'), $idorg);
		} catch (Exception $e) {
			error_log('[log_vision] ' . $op . ' org ' . $idorg . ': ' . $e->getMessage());
			visOut(false, null, 'error_interno');
		}
		if (!$activo) {
			if ($op === 'estado') { visOut(true, array('activo' => false)); }
			visOut(false, null, 'modulo_no_activo');
		}
		$cfg = visInternoConfig();
		if ($cfg === null) { visOut(false, null, 'sin_configurar'); }
		if ($op === 'estado') {
			$r = visInterno($cfg, 'mesas/estado', $idsede);
			if (!$r['ok']) { visOut(false, null, $r['error']); }
			if (!is_array($r['data'])) { visOut(false, null, 'respuesta_invalida'); }
			$data = $r['data'];
			// F2-R43: Control de pedidos y Anfitrion no muestran el pedido; los totales por mesa no salen de aqui
			unset($data['con_pedidos']);
			if (isset($data['mesas']) && is_array($data['mesas'])) {
				foreach ($data['mesas'] as $i => $m) { if (is_array($m)) { unset($data['mesas'][$i]['cuenta']); } }
			}
			$data['activo'] = true;
			visOut(true, $data);
		}
		$id = visIdDelCuerpo();
		if ($id <= 0) { visOut(false, null, 'pedido_invalido'); }
		$ruta = $op === 'silenciar' ? 'sesiones/' . $id . '/silenciar' : 'alertas/' . $id . '/resolver';
		// idusuario como entero JSON (nunca "7"): el servidor exige exactamente {"idusuario": N}
		$r = visInterno($cfg, $ruta, $idsede, array('idusuario' => (int)$idus));
		if (!$r['ok']) { visOut(false, null, $r['error']); }
		visOut(true, $r['data']);
	}

	switch ($op) {
		case 'horario':
			include_once __DIR__ . '/ManejoBD.php';
			try {
				$data = visHorario(new xManejoBD('restobar'), $g_idsede);
			} catch (Exception $e) {
				error_log('[log_vision] horario sede ' . $g_idsede . ': ' . $e->getMessage());
				visOut(false, null, 'error_interno', 500);
			}
			visOut(true, $data);
			break;
		case 'estado':
		case 'silenciar':
		case 'resolver':
			visCamaras($op, $g_idorg, $g_idsede, $g_idus);
			break;
		default:
			visOut(false, null, 'op_invalida', 400);
	}

<?php
	// Pedido por confirmar (carta QR, sede unica): proxy de caja hacia backend-pedidos /v3/pedido-por-confirmar.
	// Plan: plan/PEDIDO-POR-CONFIRMAR-PLAN.md. Front: app/shared/pedido.por.confirmar.js
	// El secreto y los datos de usuario/sede salen de la SESION y de este servidor; nunca del navegador.
	// Secreto: private/pedido_confirmar_secrets.php -> define('PEDIDO_CONFIRMAR_SECRET', '...');
	//          (o variable de entorno PEDIDO_CONFIRMAR_SECRET). Debe ser igual al del .env de backend-pedidos.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	// URL del backend: constante PEDIDO_CONFIRMAR_URL en private/pedido_confirmar_secrets.php (dev).
	// No se usa pushMozoUrlBackend(): decide por HTTP_HOST, que lo manda el navegador, y el secreto podria salir a otra IP.
	@include_once __DIR__ . '/../private/pedido_confirmar_secrets.php';

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): el POS siempre manda JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		echo json_encode(array('success' => false, 'data' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_idus = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);
	// Solo se lee la sesion: soltar el candado para que una llamada lenta al backend no frene otras pestanas.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	function ppcOut($ok, $data = null, $error = '') {
		echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error));
		exit;
	}
	function ppcBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	function ppcSecreto() {
		if (defined('PEDIDO_CONFIRMAR_SECRET') && PEDIDO_CONFIRMAR_SECRET !== '') { return PEDIDO_CONFIRMAR_SECRET; }
		$e = getenv('PEDIDO_CONFIRMAR_SECRET');
		return $e ? $e : '';
	}
	function ppcUrlBackend() {
		return defined('PEDIDO_CONFIRMAR_URL') ? PEDIDO_CONFIRMAR_URL : 'https://app.restobar.papaya.com.pe/api.pwa/v3';
	}
	function ppcEsMonto($n) { return is_numeric($n) && (float)$n >= 0 && (float)$n < 1000000; }

	/** Llama al backend. Devuelve array('success'=>bool, 'data'=>..., 'error'=>string). */
	function ppcBackend($metodo, $ruta, $body, $conAuth, $idsede, $idus) {
		$headers = array('Content-Type: application/json');
		if ($conAuth) {
			$secreto = ppcSecreto();
			if ($secreto === '') {
				error_log('[pedido-por-confirmar] falta PEDIDO_CONFIRMAR_SECRET');
				return array('success' => false, 'data' => null, 'error' => 'Falta configurar el secreto de pedidos por confirmar en el servidor.');
			}
			$headers[] = 'x-ppc-secret: ' . $secreto;
			$headers[] = 'x-ppc-idsede: ' . (int)$idsede;
			$headers[] = 'x-ppc-idusuario: ' . (int)$idus;
		}
		$ch = curl_init(ppcUrlBackend() . '/pedido-por-confirmar/' . $ruta);
		if ($metodo === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 25);
		$rpt = curl_exec($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errCurl = $rpt === false ? curl_error($ch) : '';
		curl_close($ch);
		if ($rpt === false) {
			error_log('[pedido-por-confirmar] ' . $ruta . ' sede ' . $idsede . ': ' . $errCurl);
			return array('success' => false, 'data' => null, 'error' => 'No hay conexion con el servidor de pedidos. Intenta de nuevo.');
		}
		$j = json_decode($rpt, true);
		if (!is_array($j)) {
			error_log('[pedido-por-confirmar] ' . $ruta . ' HTTP ' . $http . ' respuesta no JSON');
			return array('success' => false, 'data' => null, 'error' => 'Respuesta no valida del servidor de pedidos.');
		}
		$ok = !empty($j['success']) && $http < 400;
		return array(
			'success' => $ok,
			'data' => isset($j['data']) ? $j['data'] : (isset($j['idpedido']) ? array('idpedido' => $j['idpedido'], 'aviso' => isset($j['aviso']) ? $j['aviso'] : null) : null),
			'error' => $ok ? '' : (isset($j['error']) && is_string($j['error']) ? $j['error'] : 'No se pudo completar la operacion.')
		);
	}

	/** Valida y limpia {methods:[{id,name,amount,amount_real}]}; null si no viene; false si es invalido. */
	function ppcPagosLimpios($pagos) {
		if ($pagos === null) { return null; }
		if (!is_array($pagos) || !isset($pagos['methods']) || !is_array($pagos['methods'])) { return false; }
		$n = count($pagos['methods']);
		if ($n < 1 || $n > 4) { return false; }
		$methods = array();
		foreach ($pagos['methods'] as $m) {
			if (!is_array($m)) { return false; }
			$id = isset($m['id']) ? (int)$m['id'] : 0;
			$name = isset($m['name']) && is_string($m['name']) ? mb_substr(trim($m['name']), 0, 60) : '';
			$amount = isset($m['amount']) ? $m['amount'] : null;
			$real = isset($m['amount_real']) ? $m['amount_real'] : null;
			if ($id <= 0 || !ppcEsMonto($amount) || !ppcEsMonto($real) || (float)$real > (float)$amount) { return false; }
			$methods[] = array('id' => $id, 'name' => $name, 'amount' => round((float)$amount, 2),
				'amount_real' => round((float)$real, 2), 'isActive' => true);
		}
		return array('methods' => $methods, 'isPaymentSuccess' => true);
	}

	if ($g_idsede <= 0 || $g_idus <= 0) { ppcOut(false, null, 'Sesion sin sede o usuario. Vuelve a iniciar sesion.'); }

	switch ($op) {
		case 'config':
			$r = ppcBackend('GET', 'config/' . $g_idsede, null, false, $g_idsede, $g_idus);
			break;
		case 'pendientes':
			$r = ppcBackend('GET', 'pendientes', null, true, $g_idsede, $g_idus);
			break;
		case 'confirmados': // quien confirmo cada pedido (detalle de la mesa)
			$b = ppcBody();
			$ids = isset($b['ids']) && is_array($b['ids']) ? array_slice(array_values(array_filter(array_map('intval', $b['ids']))), 0, 100) : array();
			if (!$ids) { ppcOut(true, array()); }
			$r = ppcBackend('POST', 'confirmados', array('ids' => $ids), true, $g_idsede, $g_idus);
			break;
		case 'confirmar':
			$b = ppcBody();
			$id = isset($b['id']) ? (int)$b['id'] : 0;
			if ($id <= 0) { ppcOut(false, null, 'Pedido no valido.'); }
			$pagos = ppcPagosLimpios(isset($b['pagos']) ? $b['pagos'] : null);
			if ($pagos === false) { ppcOut(false, null, 'Los datos del pago no son validos.'); }
			$send = array('id' => $id);
			if ($pagos !== null) { $send['pagos'] = $pagos; }
			$r = ppcBackend('POST', 'confirmar', $send, true, $g_idsede, $g_idus);
			break;
		case 'anular':
			$b = ppcBody();
			$id = isset($b['id']) ? (int)$b['id'] : 0;
			$motivo = isset($b['motivo']) && is_string($b['motivo']) ? trim($b['motivo']) : '';
			if ($id <= 0) { ppcOut(false, null, 'Pedido no valido.'); }
			if (mb_strlen($motivo) < 3) { ppcOut(false, null, 'Indica el motivo de la anulacion (minimo 3 letras).'); }
			$r = ppcBackend('POST', 'anular', array('id' => $id, 'motivo' => mb_substr($motivo, 0, 200)), true, $g_idsede, $g_idus);
			break;
		default:
			ppcOut(false, null, 'Operacion no valida.');
	}
	ppcOut($r['success'], $r['data'], $r['error']);

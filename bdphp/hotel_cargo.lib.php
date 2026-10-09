<?php
// Cargo a habitacion (forma de pago 17): llamadas server-to-server a la API del sistema de hoteles.
// Contrato: restobar-2026/docs/plans/cargo-habitacion-pos.md ("Contrato de la API del hotel").
// La API key se lee de sede.hotel_api_key (la guarda la consola al vincular) y NUNCA se devuelve al navegador.
// Lo usan log_hotel.php (proxy del POS) y log.php op 5011 (anular un pago cobrado con cargo a habitacion).
if (!function_exists('hotelCredenciales')) {

	define('HOTEL_TIPO_PAGO_CARGO', 17);
	define('HOTEL_NO_RESPONDE', 'El sistema del hotel no responde. Intenta de nuevo en unos segundos.');

	function hotelCorto($s, $max) {
		$s = trim((string)$s);
		return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
	}

	/** ['url' => base sin /api/pos, 'key' => api key, 'nombre' => hotel] o null si la sede no esta vinculada
	 *  (o la migracion 019 aun no se aplico: columnas inexistentes). */
	function hotelCredenciales($bd, $idsede) {
		try {
			$bd->prepare("SELECT hotel_api_url, hotel_api_key, hotel_nombre FROM sede WHERE idsede = ?");
			$bd->execute(array((int)$idsede));
			if ($bd->stmt->errno) { return null; }
			$f = $bd->fetchAll();
		} catch (Exception $e) {
			return null;
		}
		if (!$f || empty($f[0]['hotel_api_key']) || empty($f[0]['hotel_api_url'])) { return null; }
		$url = rtrim(trim($f[0]['hotel_api_url']), '/');
		if (!preg_match('#^https?://#i', $url)) { return null; }
		return array('url' => $url, 'key' => trim($f[0]['hotel_api_key']), 'nombre' => $f[0]['hotel_nombre'] ? $f[0]['hotel_nombre'] : 'Hotel');
	}

	/** Llama a {url}/api/pos{ruta}. Devuelve ['ok'=>bool, 'status'=>int, 'data'=>array|null, 'error'=>string, 'code'=>string]. */
	function hotelLlamar($cred, $metodo, $ruta, $cuerpo = null) {
		$ch = curl_init($cred['url'] . '/api/pos' . $ruta);
		$headers = array('Accept: application/json', 'Authorization: Bearer ' . $cred['key']);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
		if ($cuerpo !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
			$headers[] = 'Content-Type: application/json';
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$rpt = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errCurl = $rpt === false ? curl_error($ch) : '';
		curl_close($ch);

		if ($rpt === false) {
			error_log('[hotel] ' . $metodo . ' ' . $ruta . ': ' . $errCurl);
			return array('ok' => false, 'status' => 0, 'data' => null, 'error' => HOTEL_NO_RESPONDE, 'code' => 'SIN_CONEXION');
		}
		$j = json_decode($rpt, true);
		if ($status >= 200 && $status < 300 && is_array($j) && isset($j['ok']) && $j['ok'] === true) {
			return array('ok' => true, 'status' => $status, 'data' => $j, 'error' => '', 'code' => '');
		}
		// Sobre de error del hotel: { ok:false, error:{ code, message } }
		$code = is_array($j) && isset($j['error']['code']) && is_string($j['error']['code']) ? $j['error']['code'] : ($status >= 500 ? 'SIN_CONEXION' : 'ERROR');
		$msg = is_array($j) && isset($j['error']['message']) && is_string($j['error']['message']) && trim($j['error']['message']) !== ''
			? trim($j['error']['message'])
			: ($status >= 500 ? HOTEL_NO_RESPONDE : 'El hotel respondio con un error (HTTP ' . $status . ').');
		if ($code === 'ERROR' || $code === 'SIN_CONEXION') { error_log('[hotel] ' . $metodo . ' ' . $ruta . ' HTTP ' . $status); }
		return array('ok' => false, 'status' => $status, 'data' => $j, 'error' => $msg, 'code' => $code);
	}

	/** Anula el cargo `ref` en el hotel y lo marca ANULADO en registro_pago_hotel.
	 *  CARGO_NO_ENCONTRADO (404) se toma como ya anulado (el cargo nunca llego al hotel). */
	function hotelAnularRef($bd, $cred, $idsede, $ref, $motivo) {
		$r = hotelLlamar($cred, 'POST', '/cargos/' . rawurlencode($ref) . '/anular', array('motivo' => hotelCorto($motivo, 180)));
		if ($r['ok'] || $r['code'] === 'CARGO_NO_ENCONTRADO') {
			try {
				$bd->prepare("UPDATE registro_pago_hotel SET estado = 'ANULADO', anulado_at = NOW() WHERE ref = ? AND idsede = ?");
				$bd->execute(array((string)$ref, (int)$idsede));
			} catch (Exception $e) {
				error_log('[hotel] anular ' . $ref . ': ' . $e->getMessage());
			}
			return array('ok' => true, 'error' => '', 'code' => '');
		}
		return array('ok' => false, 'error' => $r['error'], 'code' => $r['code']);
	}

	/** Para anular un pago del POS: si se cobro con cargo a habitacion, anula el cargo en el hotel (best-effort).
	 *  Devuelve null si el pago no tenia cargo; si no, ['ok', 'error', 'code', 'habitacion']. */
	function hotelAnularPorRegistroPago($bd, $idsede, $idregistro_pago, $motivo) {
		$idregistro_pago = (int)$idregistro_pago;
		if ($idregistro_pago <= 0) { return null; }
		try {
			$bd->prepare("SELECT ref, habitacion FROM registro_pago_hotel WHERE idsede = ? AND idregistro_pago = ? AND estado = 'CARGADO'");
			$bd->execute(array((int)$idsede, $idregistro_pago));
			if ($bd->stmt->errno) { return null; }
			$filas = $bd->fetchAll();
		} catch (Exception $e) {
			return null; // tabla aun no creada (migracion 020 pendiente)
		}
		if (!$filas) { return null; }
		$cred = hotelCredenciales($bd, $idsede);
		if (!$cred) {
			return array('ok' => false, 'error' => 'La sede ya no esta vinculada al hotel: anula el cargo en recepcion.', 'code' => 'SIN_VINCULO', 'habitacion' => $filas[0]['habitacion']);
		}
		$rpt = array('ok' => true, 'error' => '', 'code' => '', 'habitacion' => $filas[0]['habitacion']);
		foreach ($filas as $f) {
			$r = hotelAnularRef($bd, $cred, $idsede, $f['ref'], $motivo);
			if (!$r['ok']) {
				error_log('[hotel] no se anulo el cargo ' . $f['ref'] . ' del pago ' . $idregistro_pago . ': ' . $r['code']);
				$rpt = array('ok' => false, 'error' => $r['error'], 'code' => $r['code'], 'habitacion' => $f['habitacion']);
			}
		}
		return $rpt;
	}
}

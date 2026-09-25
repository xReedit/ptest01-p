<?php
	// Utilidades de las paginas publicas de asistencia (enrolar, marcar).
	//
	// Estas paginas las abre el CELULAR del trabajador, no el POS. No tienen
	// sesion, y por eso NO pueden pasar por SecurityGuard::verificarAcceso(),
	// que exige Referer del host propio y sesion iniciada.
	//
	// Lo que autoriza cada operacion es el codigo que viaja en la URL o el body
	// (la invitacion, el codigo del kiosko): de un solo uso y con caducidad
	// corta. El token que se firma aqui solo prueba que la llamada sale de un
	// servidor del POS, y no lleva empresa ni sede: esas se deducen del codigo.

	date_default_timezone_set('America/Lima');

	$rutaSecrets = __DIR__ . '/../private/asistencia_secrets.php';
	if (!file_exists($rutaSecrets)) {
		http_response_code(500);
		exit('El modulo de asistencia no esta configurado en este servidor.');
	}
	require_once $rutaSecrets;
	require_once __DIR__ . '/../bdphp/JWT.php';

	/** Token publico: prueba el origen, no la identidad. Vida corta. */
	function asisTokenPublico() {
		$ahora = time();
		return \Firebase\JWT\JWT::encode(
			array('pub' => 1, 'iat' => $ahora, 'exp' => $ahora + 120),
			ASISTENCIA_API_SECRET, 'HS256'
		);
	}

	/**
	 * Llama a la API central. Devuelve array('ok'=>bool, 'datos'=>..., 'error'=>string).
	 * No lanza excepciones: estas paginas las ve un trabajador apurado en la
	 * puerta del local, asi que todo camino termina en un mensaje legible.
	 */
	function asisApiPublica($ruta, $cuerpo) {
		$ch = curl_init(ASISTENCIA_API_URL . '/asistencia/publico' . $ruta);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($cuerpo),
			CURLOPT_TIMEOUT        => ASISTENCIA_API_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . asisTokenPublico()
			)
		));
		$resp = curl_exec($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($resp === false) {
			return array('ok' => false, 'datos' => null,
				'error' => 'No hay conexion con el servidor. Avisa al administrador.');
		}
		$json = json_decode($resp, true);
		if (!is_array($json)) {
			return array('ok' => false, 'datos' => null, 'error' => 'Respuesta inesperada del servidor.');
		}
		if ($http >= 400 || empty($json['success'])) {
			// Los datos viajan tambien en el rechazo: un "no" puede traer que
			// hacer al respecto (por ejemplo, que este dia se puede habilitar).
			return array('ok' => false, 'datos' => isset($json['datos']) ? $json['datos'] : null,
				'error' => !empty($json['error']) ? $json['error'] : 'No se pudo completar la operacion.');
		}
		return array('ok' => true, 'datos' => isset($json['datos']) ? $json['datos'] : null, 'error' => '');
	}


	/**
	 * Ticket de tramite: reemplaza a la clave entre pasos de un formulario.
	 *
	 * Las pantallas que exigen credenciales trabajan en dos pasos. Reenviar la
	 * clave en un campo oculto la deja en el HTML de una pantalla que esta en
	 * la puerta del local, en el historial y en cualquier cache del camino.
	 *
	 * El ticket dice QUIEN es y PARA QUE sirve, y vence en minutos. Si se
	 * filtra no sirve para entrar a ningun lado: solo para terminar ese tramite.
	 */
	function asisTicketCrear($us, $proposito, $minutos = 10) {
		$ahora = time();
		return \Firebase\JWT\JWT::encode(array(
			'tkt'       => 1,
			'prop'      => $proposito,
			'idusuario' => (int)$us['idusuario'],
			'idorg'     => (int)$us['idorg'],
			'idsede'    => (int)$us['idsede'],
			'nombres'   => $us['nombres'],
			'iat'       => $ahora,
			'exp'       => $ahora + ($minutos * 60)
		), ASISTENCIA_API_SECRET, 'HS256');
	}

	/**
	 * Devuelve los datos del ticket, o null si no sirve.
	 *
	 * Se comprueba tambien el PROPOSITO: un ticket para marcar a mano no puede
	 * usarse para habilitar un dia. Sin eso, el permiso mas facil de conseguir
	 * abriria la puerta del mas dificil.
	 */
	function asisTicketLeer($ticket, $proposito) {
		if (!is_string($ticket) || $ticket === '') { return null; }
		try {
			// Firma vieja de la libreria: esta version no tiene la clase Key.
			// El tercer parametro con el algoritmo NO es opcional: sin el,
			// la libreria aceptaria un token que diga alg:none.
			$d = \Firebase\JWT\JWT::decode(
				$ticket, ASISTENCIA_API_SECRET, array('HS256'));
		} catch (Exception $e) {
			// Vencido, alterado o firmado con otra llave: los tres casos se
			// tratan igual, y el detalle no se le dice al cliente.
			return null;
		}
		if (empty($d->tkt) || !isset($d->prop) || $d->prop !== $proposito) { return null; }

		return array(
			'idusuario' => (int)$d->idusuario,
			'idorg'     => (int)$d->idorg,
			'idsede'    => (int)$d->idsede,
			'nombres'   => isset($d->nombres) ? $d->nombres : ''
		);
	}

	/** true si la pagina se esta sirviendo por HTTPS (mirando tambien el proxy). */
	function asisEsHttps() {
		if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') { return true; }
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') { return true; }
		return false;
	}

	/**
	 * Guarda el token del celular como cookie.
	 * HttpOnly: el JavaScript de la pagina no puede leerla, asi que un XSS en
	 * cualquier parte del POS no se lleva la identidad del trabajador.
	 */
	function asisGuardarCookie($token, $dias) {
		$opciones = array(
			'expires'  => time() + ($dias * 86400),
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
			'secure'   => asisEsHttps()
		);
		if (PHP_VERSION_ID >= 70300) {
			setcookie('asis_dev', $token, $opciones);
		} else {
			// PHP < 7.3 no acepta el array; el samesite se cuela por el path
			setcookie('asis_dev', $token, $opciones['expires'], '/; SameSite=Lax', '', $opciones['secure'], true);
		}
	}

	function asisEsc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

<?php
	// Control de Asistencia: panel del POS.
	//
	// Este archivo NO toca la BD `rrhh`. Es un proxy firmado hacia la API central
	// de Recursos Humanos: 30 de las 45 sedes corren servidor local y no tienen
	// acceso al MySQL de rrhh (ni deben tenerlo).
	//
	// Reparto de responsabilidades:
	//   - el POS lee `restobar` (que es SU base): ficha de la empresa y usuarios
	//   - la API lee y escribe `rrhh`: colaboradores, horarios, marcas
	//
	// El navegador nunca ve el token ni la URL de la API: siempre pasa por aqui.
	// La empresa y la sede salen de $_SESSION y viajan DENTRO de la firma, asi que
	// un usuario avanzado del POS no puede pedir el padron de otra sede.

	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-store');
	date_default_timezone_set('America/Lima');

	include "ManejoBD.php";
	require_once __DIR__ . '/JWT.php';

	$rutaSecrets = __DIR__ . '/../private/asistencia_secrets.php';
	if (!file_exists($rutaSecrets)) {
		echo json_encode(array('success' => false, 'datos' => null,
			'error' => 'Falta private/asistencia_secrets.php en el servidor. Copiar el .example y completarlo.'));
		exit;
	}
	require_once $rutaSecrets;

	$bd = new xManejoBD("restobar");

	// De la sesion, nunca del cliente
	$g_ido       = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede    = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_idusuario = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

	function jsonOut($ok, $datos = null, $error = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error));
		exit;
	}

	function filas($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		$r = $bd->fetchAll();
		return is_array($r) ? $r : array();
	}

	// -----------------------------------------------------------------------
	// Llamada a la API
	// -----------------------------------------------------------------------

	/**
	 * Token corto que prueba desde que empresa y sede se esta llamando.
	 * JWT.php (firebase/php-jwt) vive en el namespace Firebase\JWT, por eso el
	 * nombre completo: un `JWT::encode` pelado da "Class 'JWT' not found".
	 */
	function asisToken($ido, $idsede, $idusuario) {
		$ahora = time();
		return \Firebase\JWT\JWT::encode(array(
			'ido'       => $ido,
			'idsede'    => $idsede,
			'idusuario' => $idusuario,
			'iat'       => $ahora,
			'exp'       => $ahora + 120   // vida corta: no sirve de nada si se filtra
		), ASISTENCIA_API_SECRET, 'HS256');
	}

	function asisApi($ruta, $cuerpo, $metodo = 'POST') {
		global $g_ido, $g_idsede, $g_idusuario;

		$ch = curl_init(ASISTENCIA_API_URL . '/asistencia' . $ruta);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => $metodo,
			CURLOPT_POSTFIELDS     => json_encode($cuerpo),
			CURLOPT_TIMEOUT        => ASISTENCIA_API_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . asisToken($g_ido, $g_idsede, $g_idusuario)
			)
		));

		$cuerpoResp = curl_exec($ch);
		$http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errCurl = curl_error($ch);
		curl_close($ch);

		if ($cuerpoResp === false) {
			// Sin internet o API caida. Mensaje accionable, no "error 0".
			throw new Exception('No se pudo contactar al servidor de Recursos Humanos. Revisa la conexion a internet de la sede. (' . $errCurl . ')');
		}

		$json = json_decode($cuerpoResp, true);
		if (!is_array($json)) {
			throw new Exception('Respuesta inesperada del servidor de RRHH (HTTP ' . $http . ')');
		}
		if ($http >= 400 || empty($json['success'])) {
			throw new Exception(isset($json['error']) && $json['error'] ? $json['error'] : ('Error del servidor de RRHH (HTTP ' . $http . ')'));
		}

		return isset($json['datos']) ? $json['datos'] : null;
	}

	/**
	 * Raiz publica de ESTE servidor, para armar el enlace que abre el celular.
	 * SCRIPT_NAME es /<lo-que-sea>/bdphp/log_asistencia.php, asi que subir dos
	 * niveles da la raiz del POS sin hardcodear ninguna ruta ni dominio.
	 */
	function asisUrlBase() {
		$esHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
			|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
		$raiz = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
		if ($raiz === '/' || $raiz === '.') { $raiz = ''; }

		return ($esHttps ? 'https://' : 'http://') . $host . rtrim($raiz, '/');
	}

	/**
	 * Autorizacion para cambiar horarios o calendario desde el POS.
	 *
	 * El horario y el calendario deciden si un dia cuenta como falta y si se
	 * paga con recargo: deciden cuanto cobra alguien. Por eso no alcanza con
	 * estar logueado.
	 *
	 * Si el que opera YA es administrador, no se le pide nada: seria pedirle la
	 * clave a la misma persona que la acaba de poner para entrar. Si no lo es,
	 * tiene que pasar usuario y clave de un administrador, y la bitacora guarda
	 * a los DOS: quien lo hizo y quien lo autorizo.
	 *
	 * Devuelve el bloque 'autorizado' que se manda a la API (vacio si el propio
	 * usuario era administrador).
	 */
	function asisAutorizar($bd, $entrada) {
		global $g_idusuario, $g_ido;

		$yo = filas($bd, "SELECT rol, super, nombres FROM usuario WHERE idusuario = ?", array($g_idusuario));
		$soyAdmin = count($yo) && ((int)$yo[0]['rol'] === 1 || (int)$yo[0]['super'] === 1);
		if ($soyAdmin) { return array(); }

		$u = isset($entrada['auth_usuario']) ? trim($entrada['auth_usuario']) : '';
		$p = isset($entrada['auth_clave']) ? $entrada['auth_clave'] : '';
		if ($u === '' || $p === '') {
			jsonOut(false, null, 'REQUIERE_AUTORIZACION');
		}

		$adm = filas($bd,
			"SELECT idusuario, nombres, rol, super FROM usuario
			  WHERE usuario = ? AND pass = ? AND estado = 0 AND idorg = ? LIMIT 1",
			array($u, $p, $g_ido));

		if (!count($adm)) { jsonOut(false, null, 'Usuario o clave incorrectos.'); }
		if ((int)$adm[0]['rol'] !== 1 && (int)$adm[0]['super'] !== 1) {
			jsonOut(false, null, 'Ese usuario no es administrador.');
		}

		return array('autorizado' => array(
			'idusuario' => (int)$adm[0]['idusuario'],
			'nombre'    => $adm[0]['nombres']
		));
	}

	/** Quien opera, para la bitacora. Sale de la sesion, nunca del cliente. */
	function asisQuienSoy() {
		return array('usuario' => array(
			'idusuario' => (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0),
			'nombre'    => isset($_SESSION['nomUs']) ? $_SESSION['nomUs'] : (isset($_SESSION['nomU']) ? $_SESSION['nomU'] : '')
		));
	}

	/**
	 * Mantiene restobar.sede.asistencia_activa al dia.
	 *
	 * Es un espejo del "esta sede tiene marcadores" que vive en la BD `rrhh`.
	 * Existe solo para que el menu del home pueda decidir si muestra la opcion
	 * D9 sin salir a la red: el menu se arma con los datos de sede que ya se
	 * piden al entrar, y agregarle una llamada a la API central dejaria el menu
	 * incompleto cada vez que esa llamada falle.
	 *
	 * Solo escribe cuando el valor cambia: esto corre en cada listado.
	 */
	function asisSyncActiva($bd, $idsede, $activa) {
		$nuevo = $activa ? 1 : 0;

		$actual = filas($bd, "SELECT asistencia_activa FROM sede WHERE idsede = ?", array((int)$idsede));
		if (!count($actual) || (int)$actual[0]['asistencia_activa'] === $nuevo) { return; }

		$bd->prepare("UPDATE sede SET asistencia_activa = ? WHERE idsede = ?");
		$bd->execute(array($nuevo, (int)$idsede));
	}

	// -----------------------------------------------------------------------
	// Ficha local: lo que la API no puede leer porque vive en `restobar`
	// -----------------------------------------------------------------------

	function asisFicha($bd, $ido, $idsede) {
		$o = filas($bd, "SELECT nombre, direccion, ruc, telefono FROM org WHERE idorg = ?", array($ido));
		$s = filas($bd, "SELECT nombre, ciudad, direccion, telefono FROM sede WHERE idsede = ?", array($idsede));
		if (!count($o)) { throw new Exception('la empresa de la sesion no existe'); }
		if (!count($s)) { throw new Exception('la sede de la sesion no existe'); }

		return array(
			'org'  => $o[0],
			'sede' => array(
				'nombre'       => $s[0]['nombre'],
				'ciudad'       => $s[0]['ciudad'],
				'direccion'    => $s[0]['direccion'],
				'telefono'     => $s[0]['telefono'],
				'ruc'          => $o[0]['ruc'],
				'razon_social' => $o[0]['nombre']
			)
		);
	}

	// -----------------------------------------------------------------------

	$op = isset($_GET['op']) ? $_GET['op'] : (isset($_POST['op']) ? $_POST['op'] : '');
	$entrada = json_decode(file_get_contents('php://input'), true);
	if (!is_array($entrada)) { $entrada = array(); }

	try {
		if ($g_ido <= 0 || $g_idsede <= 0) { jsonOut(false, null, 'Sesion sin empresa o sede. Vuelve a entrar al sistema.'); }

		$ficha = asisFicha($bd, $g_ido, $g_idsede);

		switch ($op) {

			// Padron de la sede, con horarios
			case 'listar':
				jsonOut(true, asisApi('/personal/listar', $ficha));
				break;

			// Previsualizar el import: se mandan los usuarios del POS de ESTA sede
			// y la API dice cuales ya existen. No inserta nada.
			case 'importar-preview':
				$usuarios = filas($bd,
					"SELECT idusuario, nombres, cargo FROM usuario
					  WHERE idsede = ? AND estado = 0 AND (isbot IS NULL OR isbot <> '1')
					  ORDER BY nombres", array($g_idsede));

				$lista = array();
				foreach ($usuarios as $u) {
					$lista[] = array(
						'idusuario' => (int)$u['idusuario'],
						'nombres'   => $u['nombres'],
						'cargo'     => $u['cargo'],
						'dni'       => ''   // restobar.usuario no guarda DNI; se completa despues en la ficha
					);
				}
				if (!count($lista)) { jsonOut(false, null, 'Esta sede no tiene usuarios activos en el POS. Crea el personal a mano con "+ Nuevo".'); }

				$r = asisApi('/personal/importar/preview', array_merge($ficha, array('usuarios' => $lista)));
				// el cargo no viaja a rrhh, pero sirve para que el admin decida a quien destildar
				$porId = array();
				foreach ($lista as $u) { $porId[$u['idusuario']] = $u['cargo']; }
				foreach ($r['filas'] as $i => $f) {
					$r['filas'][$i]['cargo'] = isset($porId[$f['idusuario']]) ? $porId[$f['idusuario']] : '';
				}
				jsonOut(true, $r);
				break;

			// Confirmar: solo los que el admin dejo tildados
			case 'importar-confirmar':
				$sel = isset($entrada['usuarios']) && is_array($entrada['usuarios']) ? $entrada['usuarios'] : array();
				if (!count($sel)) { jsonOut(false, null, 'No se selecciono a nadie.'); }

				// Se re-leen del POS: el cliente solo manda ids, nunca nombres.
				// Asi no se puede inyectar un colaborador inventado desde el navegador.
				$ids = array();
				foreach ($sel as $u) { $id = (int)(is_array($u) ? $u['idusuario'] : $u); if ($id > 0) { $ids[$id] = true; } }
				if (!count($ids)) { jsonOut(false, null, 'Seleccion invalida.'); }

				$marcas = implode(',', array_fill(0, count($ids), '?'));
				$usuarios = filas($bd,
					"SELECT idusuario, nombres FROM usuario
					  WHERE idsede = ? AND estado = 0 AND idusuario IN ($marcas)",
					array_merge(array($g_idsede), array_keys($ids)));

				$lista = array();
				foreach ($usuarios as $u) {
					$lista[] = array('idusuario' => (int)$u['idusuario'], 'nombres' => $u['nombres'], 'dni' => '');
				}
				if (!count($lista)) { jsonOut(false, null, 'Ninguno de los seleccionados pertenece a esta sede.'); }

				jsonOut(true, asisApi('/personal/importar/confirmar', array_merge($ficha, array('usuarios' => $lista))));
				break;

			// Alta manual: para quien trabaja en la sede pero no usa el POS
			case 'crear':
				jsonOut(true, asisApi('/personal/crear', array_merge($ficha, array(
					'nombres'   => isset($entrada['nombres']) ? $entrada['nombres'] : '',
					'apellidos' => isset($entrada['apellidos']) ? $entrada['apellidos'] : '',
					'dni'       => isset($entrada['dni']) ? $entrada['dni'] : ''
				))));
				break;

			// QR de invitacion para enrolar el celular del trabajador.
			// La URL la arma el POS, no la API: solo este servidor sabe con que
			// dominio lo alcanza el celular (cada sede tiene el suyo).
			case 'invitacion':
				$id = (int)(isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el colaborador.'); }

				$r = asisApi('/personal/' . $id . '/invitacion', $ficha);
				$r['url'] = asisUrlBase() . '/asistencia/enrolar.php?i=' . $r['invitacion'];
				unset($r['invitacion']);   // el uuid va dentro de la URL, no suelto
				jsonOut(true, $r);
				break;

			// Desactivar el celular enrolado (robo, cambio de equipo, renuncia)
			case 'revocar-dispositivo':
				$id = (int)(isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el colaborador.'); }

				jsonOut(true, asisApi('/personal/' . $id . '/dispositivo/revocar', $ficha));
				break;

			// --- Pantallas (kioskos) ---------------------------------------
			// El token de la pantalla se muestra UNA sola vez, dentro del enlace
			// de vinculacion: la API solo guarda su hash.
			case 'kiosko-crear':
				$k = asisApi('/kiosko/crear', array_merge($ficha, array(
					'nombre' => isset($entrada['nombre']) ? $entrada['nombre'] : ''
				)));
				$k['url'] = asisUrlBase() . '/asistencia/kiosko.html?t=' . $k['token'];
				unset($k['token']);
				// Con el primer marcador la sede "activa" el modulo: recien ahi
				// aparece Marcadores de Asistencia en el menu.
				asisSyncActiva($bd, $g_idsede, true);
				jsonOut(true, $k);
				break;

			case 'kiosko-listar':
				$r = asisApi('/kiosko/listar', $ficha);
				// Espejo de "esta sede ya usa asistencia", que es lo que decide si
				// la opcion D9 aparece en el menu. Se sincroniza aqui porque esta
				// es la unica operacion que conoce el total real; si alguna vez
				// quedara desfasada, la siguiente visita a Marcadores la corrige.
				asisSyncActiva($bd, $g_idsede, count(isset($r['marcadores']) ? $r['marcadores'] : array()) > 0);
				jsonOut(true, $r);
				break;

			// "Activar en esta pantalla": rota el token y devuelve el enlace.
			// Es lo que usa la pagina Marcadores de Asistencia, para que nadie
			// tenga que guardar un favorito ni volver a escanear un QR.
			case 'kiosko-activar':
				$id = (int)(isset($entrada['idkiosko']) ? $entrada['idkiosko'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el marcador.'); }

				$k = asisApi('/kiosko/' . $id . '/activar', $ficha);
				$k['url'] = asisUrlBase() . '/asistencia/kiosko.html?t=' . $k['token'];
				unset($k['token']);
				jsonOut(true, $k);
				break;

			// Habilitar / deshabilitar el boton de marca manual del marcador
			case 'kiosko-manual':
				$id = (int)(isset($entrada['idkiosko']) ? $entrada['idkiosko'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el marcador.'); }
				jsonOut(true, asisApi('/kiosko/' . $id . '/manual', array_merge($ficha, array(
					'permite_manual' => !empty($entrada['permite_manual'])
				))));
				break;

			case 'kiosko-revocar':
				$id = (int)(isset($entrada['idkiosko']) ? $entrada['idkiosko'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el marcador.'); }

				$r = asisApi('/kiosko/' . $id . '/revocar', $ficha);
				// Al revocar el ultimo, la sede vuelve a quedar sin asistencia y
				// la opcion D9 desaparece del menu en el proximo ingreso.
				$quedan = asisApi('/kiosko/listar', $ficha);
				asisSyncActiva($bd, $g_idsede, count(isset($quedan['marcadores']) ? $quedan['marcadores'] : array()) > 0);
				jsonOut(true, $r);
				break;

			// --- Ubicacion del local (GPS) ---------------------------------
			case 'gps-estado':
				$g = asisApi('/gps/estado', $ficha);
				// Si la sede ya tiene punto en `restobar` (lo puso Tracker) pero
				// todavia no se espejo aca, se ofrece como sugerencia: el usuario
				// no deberia tener que volver a marcar en el mapa lo mismo.
				if ($g['lat'] === null) {
					$s = filas($bd, "SELECT latitude, longitude FROM sede WHERE idsede = ?", array($g_idsede));
					if (count($s) && $s[0]['latitude'] !== null && (float)$s[0]['latitude'] != 0) {
						$g['sugerido_lat'] = (float)$s[0]['latitude'];
						$g['sugerido_lng'] = (float)$s[0]['longitude'];
					}
				}
				jsonOut(true, $g);
				break;

			// Guarda el punto en las DOS bases: `restobar.sede` para que Tracker
			// Delivery lo siga usando, y la central para poder validar marcas.
			case 'gps-guardar':
				$lat = isset($entrada['lat']) ? (float)$entrada['lat'] : null;
				$lng = isset($entrada['lng']) ? (float)$entrada['lng'] : null;
				$hayPunto = ($lat !== null && $lng !== null && is_finite($lat) && is_finite($lng)
					&& $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && !($lat == 0 && $lng == 0));

				$r = asisApi('/gps/guardar', array_merge($ficha, array(
					'activo'  => !empty($entrada['activo']),
					'lat'     => $hayPunto ? $lat : null,
					'lng'     => $hayPunto ? $lng : null,
					'radio_m' => isset($entrada['radio_m']) ? $entrada['radio_m'] : 150
				)));

				// Solo despues de que la central lo acepto, para no dejar las dos
				// bases con puntos distintos si la validacion rechaza algo.
				if ($hayPunto) {
					$bd->prepare("UPDATE sede SET latitude = ?, longitude = ? WHERE idsede = ?");
					$bd->execute(array($lat, $lng, $g_idsede));
				}
				jsonOut(true, $r);
				break;

			// Apagar la exigencia de GPS pide un administrador: encenderla protege,
			// apagarla desprotege, y no deberia poder hacerlo cualquiera que pase
			// por una sesion abierta.
			case 'gps-desactivar':
				$u = isset($entrada['usuario']) ? trim($entrada['usuario']) : '';
				$p = isset($entrada['clave']) ? $entrada['clave'] : '';
				if ($u === '' || $p === '') { jsonOut(false, null, 'Escribe usuario y clave de un administrador.'); }

				$adm = filas($bd,
					"SELECT idusuario, nombres, rol, super FROM usuario
					  WHERE usuario = ? AND pass = ? AND estado = 0 AND idorg = ? LIMIT 1",
					array($u, $p, $g_ido));

				if (!count($adm)) { jsonOut(false, null, 'Usuario o clave incorrectos.'); }
				if ((int)$adm[0]['rol'] !== 1 && (int)$adm[0]['super'] !== 1) {
					jsonOut(false, null, 'Ese usuario no es administrador.');
				}

				$g = asisApi('/gps/estado', $ficha);
				jsonOut(true, asisApi('/gps/guardar', array_merge($ficha, array(
					'activo'  => false,
					'lat'     => $g['lat'],
					'lng'     => $g['lng'],
					'radio_m' => $g['radio_m']
				))));
				break;

			// --- Calendario: cierres, feriados y excepciones ----------------
			case 'cal-mes':
				jsonOut(true, asisApi('/calendario/mes', array_merge($ficha, array(
					'mes' => isset($entrada['mes']) ? $entrada['mes'] : ''
				))));
				break;

			case 'cal-dia':
				jsonOut(true, asisApi('/calendario/dia', array_merge($ficha, array(
					'fecha' => isset($entrada['fecha']) ? $entrada['fecha'] : ''
				))));
				break;

			// Upsert por (sede, persona, fecha). idcolaborador 0 = toda la sede.
			case 'cal-excepcion':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/calendario/excepcion', array_merge($ficha, array(
					'fecha'           => isset($entrada['fecha']) ? $entrada['fecha'] : '',
					'idcolaborador'   => isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0,
					'tipo'            => isset($entrada['tipo']) ? $entrada['tipo'] : '',
					'motivo'          => isset($entrada['motivo']) ? $entrada['motivo'] : '',
					'compensacion'    => isset($entrada['compensacion']) ? $entrada['compensacion'] : '',
					'fecha_sustituto' => isset($entrada['fecha_sustituto']) ? $entrada['fecha_sustituto'] : '',
					'recargo_pct'     => isset($entrada['recargo_pct']) ? $entrada['recargo_pct'] : 100
				))));
				break;

			case 'cal-excepcion-eliminar':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				$id = (int)(isset($entrada['idexcepcion']) ? $entrada['idexcepcion'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta la excepcion.'); }
				jsonOut(true, asisApi('/calendario/excepcion/' . $id . '/eliminar', $ficha));
				break;

			// --- Ausencias: por que alguien no esta marcando -----------------
			case 'ausencias-alertas':
				jsonOut(true, asisApi('/ausencias/alertas', $ficha));
				break;

			// Registrar vacaciones cambia lo que se le paga (esos dias dejan de
			// descontarse), asi que pasa por la compuerta de administrador.
			case 'ausencias-registrar':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/ausencias/registrar', array_merge($ficha, array(
					'idcolaborador' => isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0,
					'desde'         => isset($entrada['desde']) ? $entrada['desde'] : '',
					'hasta'         => isset($entrada['hasta']) ? $entrada['hasta'] : '',
					'categoria'     => isset($entrada['categoria']) ? $entrada['categoria'] : '',
					'motivo'        => isset($entrada['motivo']) ? $entrada['motivo'] : ''
				))));
				break;

			case 'ausencias-baja':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/ausencias/baja', array_merge($ficha, array(
					'idcolaborador' => isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0,
					'fecha'         => isset($entrada['fecha']) ? $entrada['fecha'] : '',
					'motivo'        => isset($entrada['motivo']) ? $entrada['motivo'] : ''
				))));
				break;

			// --- Configuracion: las tres preguntas del local -----------------
			case 'cal-configuracion':
				jsonOut(true, asisApi('/configuracion', array_merge($ficha, array(
					'anio' => isset($entrada['anio']) ? $entrada['anio'] : ''
				))));
				break;

			// Da por terminado el asistente inicial. No cambia ningun dato del
			// negocio, asi que no pasa por la compuerta de administrador.
			case 'cal-configuracion-listo':
				jsonOut(true, asisApi('/configuracion/listo', $ficha));
				break;

			// Cambia cuanto cobra la gente sin tocar ninguna ficha: pasar a
			// RECARGO hace que todo descanso trabajado se pague doble desde ese
			// momento. Por eso pasa por la compuerta de administrador.
			case 'cal-configuracion-guardar':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/configuracion/guardar', array_merge($ficha, array(
					'anio'                 => isset($entrada['anio']) ? $entrada['anio'] : '',
					'dias_cierre'          => isset($entrada['dias_cierre']) ? $entrada['dias_cierre'] : null,
					'feriados_labora'      => isset($entrada['feriados_labora']) ? $entrada['feriados_labora'] : null,
					'feriados_total'       => isset($entrada['feriados_total']) ? $entrada['feriados_total'] : 0,
					'feriado_abre'         => !empty($entrada['feriado_abre']),
					'feriado_recargo_pct'  => isset($entrada['feriado_recargo_pct']) ? $entrada['feriado_recargo_pct'] : 0,
					'descanso_trabajado'   => isset($entrada['descanso_trabajado']) ? $entrada['descanso_trabajado'] : 'PERMISO',
					'descanso_recargo_pct' => isset($entrada['descanso_recargo_pct']) ? $entrada['descanso_recargo_pct'] : 100
				))));
				break;

			case 'cal-dias-cierre':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/calendario/dias-cierre', array_merge($ficha, array(
					'dias' => isset($entrada['dias']) ? $entrada['dias'] : array()
				))));
				break;

			case 'cal-feriados':
				jsonOut(true, asisApi('/calendario/feriados', array_merge($ficha, array(
					'anio' => isset($entrada['anio']) ? $entrada['anio'] : ''
				))));
				break;

			case 'cal-feriado-guardar':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/calendario/feriado/guardar', array_merge($ficha, array(
					'fecha'       => isset($entrada['fecha']) ? $entrada['fecha'] : '',
					'descripcion' => isset($entrada['descripcion']) ? $entrada['descripcion'] : ''
				))));
				break;

			case 'cal-feriado-eliminar':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				$id = (int)(isset($entrada['idferiado']) ? $entrada['idferiado'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el feriado.'); }
				jsonOut(true, asisApi('/calendario/feriado/' . $id . '/eliminar', $ficha));
				break;

			// Historial de cambios. Es de LECTURA, asi que no pasa por la
			// compuerta de administrador: ver quien cambio que no hace dano,
			// y esconderlo solo dificultaria resolver un reclamo.
			case 'bitacora':
				jsonOut(true, asisApi('/bitacora', array_merge($ficha, array(
					'idcolaborador' => isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0,
					'limite'        => isset($entrada['limite']) ? $entrada['limite'] : 50
				))));
				break;

			// --- Vista del dia ---------------------------------------------
			case 'dia':
				jsonOut(true, asisApi('/dia', array_merge($ficha, array(
					'fecha' => isset($entrada['fecha']) ? $entrada['fecha'] : ''
				))));
				break;

			// Marca puesta a mano: para quien no tiene smartphone, para el que
			// se olvido de marcar, o para corregir un error. Queda auditada con
			// el usuario del POS que la hizo (sale de la sesion, no del cliente).
			case 'marca-manual':
				jsonOut(true, asisApi('/marca/manual', array_merge($ficha, array(
					'idcolaborador' => isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0,
					'tipo'          => isset($entrada['tipo']) ? $entrada['tipo'] : '',
					'hora'          => isset($entrada['hora']) ? $entrada['hora'] : '',
					'fecha'         => isset($entrada['fecha']) ? $entrada['fecha'] : '',
					'motivo'        => isset($entrada['motivo']) ? $entrada['motivo'] : ''
				))));
				break;

			case 'marca-eliminar':
				$id = (int)(isset($entrada['idmarca']) ? $entrada['idmarca'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta la marca.'); }
				jsonOut(true, asisApi('/marca/' . $id . '/eliminar', array_merge($ficha, array(
					'motivo' => isset($entrada['motivo']) ? $entrada['motivo'] : ''
				))));
				break;

			// --- Areas -----------------------------------------------------
			case 'area-listar':
				jsonOut(true, asisApi('/area/listar', $ficha));
				break;

			case 'area-crear':
				jsonOut(true, asisApi('/area/crear', array_merge($ficha, array(
					'descripcion' => isset($entrada['descripcion']) ? $entrada['descripcion'] : ''
				))));
				break;

			// Asignar area a uno o varios a la vez
			case 'personal-area':
				jsonOut(true, asisApi('/personal/area', array_merge($ficha, array(
					'ids'    => isset($entrada['ids']) ? $entrada['ids'] : array(),
					'idarea' => isset($entrada['idarea']) ? $entrada['idarea'] : null
				))));
				break;

			// El mismo horario para todo un grupo, en vez de uno por uno
			case 'horario-masivo':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				jsonOut(true, asisApi('/personal/horario-masivo', array_merge($ficha, array(
					'alcance'         => isset($entrada['alcance']) ? $entrada['alcance'] : '',
					'idarea'          => isset($entrada['idarea']) ? $entrada['idarea'] : null,
					'ids'             => isset($entrada['ids']) ? $entrada['ids'] : array(),
					'horario_semanal' => isset($entrada['horario_semanal']) ? $entrada['horario_semanal'] : null,
					'tolerancia_min'  => isset($entrada['tolerancia_min']) ? $entrada['tolerancia_min'] : 10,
					'solo_contar'     => !empty($entrada['solo_contar'])
				))));
				break;

			// Horario semanal + tolerancia
			case 'horario':
				$ficha = array_merge($ficha, asisQuienSoy(), asisAutorizar($bd, $entrada));
				$id = (int)(isset($entrada['idcolaborador']) ? $entrada['idcolaborador'] : 0);
				if ($id <= 0) { jsonOut(false, null, 'Falta el colaborador.'); }

				jsonOut(true, asisApi('/personal/' . $id . '/horario', array_merge($ficha, array(
					'horario_semanal' => isset($entrada['horario_semanal']) ? $entrada['horario_semanal'] : null,
					'tolerancia_min'  => isset($entrada['tolerancia_min']) ? $entrada['tolerancia_min'] : 10
				)), 'PUT'));
				break;

			default:
				jsonOut(false, null, 'Operacion no reconocida: ' . $op);
		}

	} catch (Exception $e) {
		jsonOut(false, null, $e->getMessage());
	}

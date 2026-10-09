<?php
	// Cargo a habitacion (forma de pago 17): proxy del POS hacia la API del sistema de hoteles.
	// Plan y contrato: restobar-2026/docs/plans/cargo-habitacion-pos.md. Front: app/shared/hotel.cargo.js
	// La API key vive en sede.hotel_api_key y nunca sale de aqui: el navegador solo ve estadias y resultados.
	// Orden en el POS: cargar (POST /cargos) -> guardar pago (log_001.php) -> comprobante (vincula el pago y PATCH si hubo comprobante).
	// Si guardar el pago falla: anular (POST /cargos/{ref}/anular).
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	require_once __DIR__ . '/hotel_cargo.lib.php';
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): el POS siempre manda JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		echo json_encode(array('success' => false, 'datos' => null, 'error' => 'Solicitud no valida', 'code' => 'SOLICITUD'));
		exit;
	}
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_nomus = isset($_SESSION['nomU']) ? (string)$_SESSION['nomU'] : '';
	// Solo se lee la sesion: soltar el candado para que una llamada lenta al hotel no frene las demas pestanas.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	function jsonOut($ok, $datos = null, $error = '', $code = '') {
		echo json_encode(array('success' => $ok, 'datos' => $datos, 'error' => $error, 'code' => $code));
		exit;
	}
	function leerBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	// Mensajes propios para los codigos conocidos del hotel; el resto usa el `message` que manda el hotel.
	function mensajeHotel($r) {
		$m = array(
			'ESTADIA_NO_ENCONTRADA' => 'El hotel no encuentra esa estadia. Vuelve a elegir la habitacion.',
			'ESTADIA_CERRADA' => 'El huesped ya hizo checkout: no se puede cargar a la habitacion.',
			'CARGOS_BLOQUEADOS' => 'Recepcion bloqueo los cargos a esta habitacion. Cobra con otra forma de pago.',
			'INCLUIDO_AGOTADO' => 'Otro punto de venta ya uso el consumo incluido de hoy. Vuelve a elegir la habitacion.',
			'MONTOS_INVALIDOS' => 'El hotel rechazo los montos del cargo. Vuelve a elegir la habitacion.',
			'REF_ANULADA' => 'Ese cargo ya estaba anulado en el hotel. Intenta de nuevo.',
			'REF_CONFLICTO' => 'El hotel ya tiene un cargo distinto con esa referencia. Intenta de nuevo.',
			'FOLIO_CERRADO' => 'El huesped ya hizo checkout: el hotel factura este consumo; resuelvelo en recepcion.',
			'CARGO_ANULADO' => 'Ese cargo ya fue anulado en el hotel.',
			'CARGO_NO_ENCONTRADO' => 'El hotel no encuentra ese cargo.'
		);
		return isset($m[$r['code']]) ? $m[$r['code']] : $r['error'];
	}
	function dinero($n) { return round((float)$n, 2); }
	function esMonto($n) { return is_numeric($n) && (float)$n >= 0 && (float)$n < 1000000; }
	function uuid4() {
		$b = random_bytes(16);
		$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
		$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
	}
	function refValida($ref) { return is_string($ref) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $ref); }
	/** Fila del cargo de esta sede (o null). */
	function cargoDeSede($bd, $idsede, $ref) {
		$bd->prepare("SELECT idregistro_pago_hotel, idregistro_pago, estado, habitacion, huesped FROM registro_pago_hotel WHERE ref = ? AND idsede = ?");
		$bd->execute(array($ref, (int)$idsede));
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		$f = $bd->fetchAll();
		return $f ? $f[0] : null;
	}

	if ($g_idsede <= 0 || $g_ido <= 0) { jsonOut(false, null, 'Sesion sin sede', 'SESION'); }

	$cred = hotelCredenciales($bd, $g_idsede);

	// Si la sede esta vinculada (el POS muestra la forma de pago 17 solo en ese caso).
	if ($op === 'estado') {
		jsonOut(true, array('vinculado' => $cred !== null, 'hotel' => $cred ? $cred['nombre'] : null));
	}

	if (!$cred) { jsonOut(false, null, 'Esta sede no esta vinculada a un hotel. Vinculala desde la Consola Papaya.', 'SIN_VINCULO'); }

	try {
		switch ($op) {
			case 'estadias': {
				$r = hotelLlamar($cred, 'GET', '/estadias');
				if (!$r['ok']) { jsonOut(false, null, mensajeHotel($r), $r['code']); }
				$lista = isset($r['data']['estadias']) && is_array($r['data']['estadias']) ? $r['data']['estadias'] : array();
				jsonOut(true, array('hotel' => $cred['nombre'], 'estadias' => array_values($lista)));
			}

			case 'cargar': {
				$b = leerBody();
				$estadia = isset($b['estadia_id']) ? (int)$b['estadia_id'] : 0;
				foreach (array('bruto', 'descuento', 'cubierto_incluido', 'total') as $k) {
					if (!isset($b[$k]) || !esMonto($b[$k])) { jsonOut(false, null, 'Montos del cargo no validos.', 'MONTOS_INVALIDOS'); }
				}
				$bruto = dinero($b['bruto']); $desc = dinero($b['descuento']); $cub = dinero($b['cubierto_incluido']); $total = dinero($b['total']);
				if ($estadia <= 0) { jsonOut(false, null, 'Elige la habitacion.', 'DATOS_INVALIDOS'); }
				if ((int)round(($bruto - $desc - $cub) * 100) !== (int)round($total * 100)) { /* debe cuadrar al centimo */ jsonOut(false, null, 'Los montos del cargo no cuadran.', 'MONTOS_INVALIDOS'); }

				$detalle = array();
				$lineas = isset($b['detalle']) && is_array($b['detalle']) ? array_slice($b['detalle'], 0, 200) : array();
				foreach ($lineas as $l) {
					if (!is_array($l) || !isset($l['descripcion'])) { continue; }
					$detalle[] = array(
						'descripcion' => hotelCorto($l['descripcion'], 120),
						'cantidad' => isset($l['cantidad']) && is_numeric($l['cantidad']) ? (float)$l['cantidad'] : 1,
						'precio_unit' => isset($l['precio_unit']) && esMonto($l['precio_unit']) ? dinero($l['precio_unit']) : 0,
						'subtotal' => isset($l['subtotal']) && esMonto($l['subtotal']) ? dinero($l['subtotal']) : 0
					);
				}
				if (!$detalle) { $detalle[] = array('descripcion' => 'Consumo restaurante', 'cantidad' => 1, 'precio_unit' => $bruto, 'subtotal' => $bruto); }

				$ref = uuid4();
				$habitacion = hotelCorto(isset($b['habitacion']) ? $b['habitacion'] : '', 10);
				$huesped = hotelCorto(isset($b['huesped']) ? $b['huesped'] : '', 160);
				$r = hotelLlamar($cred, 'POST', '/cargos', array(
					'ref' => $ref, 'estadia_id' => $estadia, 'bruto' => $bruto, 'descuento' => $desc,
					'cubierto_incluido' => $cub, 'total' => $total, 'comprobante' => null,
					'detalle' => $detalle, 'usuario' => $g_nomus
				));
				if (!$r['ok']) {
					// Sin respuesta: el cargo pudo quedar creado. Se anula para que un reintento (con otro ref) no lo duplique.
					if ($r['code'] === 'SIN_CONEXION') { hotelLlamar($cred, 'POST', '/cargos/' . $ref . '/anular', array('motivo' => 'El POS no recibio respuesta al cargar')); }
					jsonOut(false, null, mensajeHotel($r), $r['code']);
				}

				try {
					$bd->prepare("INSERT INTO registro_pago_hotel (idorg, idsede, ref, estadia_id, habitacion, huesped, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
					$bd->execute(array($g_ido, $g_idsede, $ref, $estadia, $habitacion, $huesped, (string)$total));
					if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
				} catch (Exception $e) {
					// Sin registro local no se podria anular despues: se deshace el cargo en el hotel.
					error_log('log_hotel.php cargar insert: ' . $e->getMessage());
					hotelLlamar($cred, 'POST', '/cargos/' . $ref . '/anular', array('motivo' => 'El POS no pudo registrar el cargo'));
					jsonOut(false, null, 'No se pudo registrar el cargo en el POS. Intenta de nuevo.', 'ERROR_POS');
				}
				jsonOut(true, array(
					'ref' => $ref,
					'cargo_id' => isset($r['data']['cargo_id']) ? $r['data']['cargo_id'] : null,
					'duplicado' => !empty($r['data']['duplicado'])
				));
			}

			case 'comprobante': {
				// Despues de guardar el pago: vincula el cargo con registro_pago y, si el POS emitio comprobante, lo informa al hotel.
				$b = leerBody();
				$ref = isset($b['ref']) ? $b['ref'] : '';
				$idrp = isset($b['idregistro_pago']) ? (int)$b['idregistro_pago'] : 0;
				$comp = isset($b['comprobante']) && is_string($b['comprobante']) ? strtoupper(trim($b['comprobante'])) : '';
				if (!refValida($ref) || $idrp <= 0) { jsonOut(false, null, 'Datos no validos.', 'DATOS_INVALIDOS'); }
				if ($comp !== '' && !preg_match('/^[A-Z0-9]{1,8}-[0-9]{1,10}$/', $comp)) { jsonOut(false, null, 'Comprobante no valido.', 'DATOS_INVALIDOS'); }
				$fila = cargoDeSede($bd, $g_idsede, $ref);
				if (!$fila) { jsonOut(false, null, 'Cargo no encontrado.', 'CARGO_NO_ENCONTRADO'); }

				$bd->prepare("UPDATE registro_pago_hotel SET idregistro_pago = ?, comprobante = ? WHERE idregistro_pago_hotel = ?");
				$bd->execute(array($idrp, $comp === '' ? null : $comp, (int)$fila['idregistro_pago_hotel']));
				if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }

				// El registro de pagos lee cliente y referencia del PEDIDO: se deja el huesped como
				// cliente (si el pedido no tenia) y "HAB. 101" como referencia (una sola vez).
				// Solo la habitacion: el nombre del huesped ya se muestra como cliente.
				$refHab = substr('HAB. ' . $fila['habitacion'], 0, 50);
				$bd->prepare("UPDATE pedido p
					INNER JOIN registro_pago_pedido rpp ON rpp.idpedido = p.idpedido
					INNER JOIN registro_pago rp ON rp.idregistro_pago = rpp.idregistro_pago
					SET p.idcliente = IF(COALESCE(p.idcliente, 0) = 0, rp.idcliente, p.idcliente),
					    p.referencia = IF(COALESCE(p.referencia, '') LIKE 'HAB. %', p.referencia, ?)
					WHERE rpp.idregistro_pago = ? AND p.idsede = ?");
				$bd->execute(array($refHab, $idrp, (int)$g_idsede));
				if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }

				if ($comp !== '') {
					$r = hotelLlamar($cred, 'PATCH', '/cargos/' . $ref, array('comprobante' => $comp));
					if (!$r['ok']) { jsonOut(false, null, mensajeHotel($r), $r['code']); }
				}
				jsonOut(true, array('ref' => $ref));
			}

			case 'anular': {
				$b = leerBody();
				$ref = isset($b['ref']) ? $b['ref'] : '';
				$motivo = isset($b['motivo']) ? $b['motivo'] : 'Anulado en el POS';
				if (!refValida($ref)) { jsonOut(false, null, 'Datos no validos.', 'DATOS_INVALIDOS'); }
				$fila = cargoDeSede($bd, $g_idsede, $ref);
				if (!$fila) { jsonOut(false, null, 'Cargo no encontrado.', 'CARGO_NO_ENCONTRADO'); }
				if ($fila['estado'] === 'ANULADO') { jsonOut(true, array('ref' => $ref)); }
				$r = hotelAnularRef($bd, $cred, $g_idsede, $ref, $motivo);
				if (!$r['ok']) { jsonOut(false, null, mensajeHotel($r), $r['code']); }
				jsonOut(true, array('ref' => $ref));
			}

			default:
				jsonOut(false, null, 'Operacion no valida', 'SOLICITUD');
		}
	} catch (Exception $e) {
		error_log('log_hotel.php ' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'No se pudo completar la operacion con el hotel.', 'ERROR_POS');
	}

<?php
	// Tracker Delivery: posicion y carga de los repartidores con pedidos de la sede activa.
	// Mismo patron que log_encuesta.php: ops string, JSON in/out, todo filtrado por la sesion.
	// Archivo aparte a proposito: no puede romper ninguna opcion de los log_*.php existentes.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : (isset($_POST['op']) ? $_POST['op'] : null);
	// la sede sale de la sesion, nunca del cliente: recibirla por parametro dejaria leer otras sedes
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);

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
	function leerBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	// El guard del router (log.php op=-108) corre desde el navegador: el endpoint verifica el permiso
	// por su cuenta. usuario.acc es "A1,A2,A17,..." (a veces con comas dobles o sin coma final).
	function tieneAccesoModulo() {
		$acc = isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '';
		return (bool)preg_match('/(^|,)\s*A17\s*(,|$)/', $acc);
	}

	if ($g_idsede === 0) { jsonOut(false, null, 'Tu sesion expiro, vuelve a entrar.'); }
	if (!tieneAccesoModulo()) { jsonOut(false, null, 'No tienes permiso para ver el tracker de delivery.'); }

	try {
		switch ($op) {

			case 'estado':
				$sede = filas($bd, "SELECT idsede, nombre, latitude, longitude, pwa_habilitar_busqueda_mapa
									  FROM sede WHERE idsede = ?", array($g_idsede));

				$rows = filas($bd, "
					SELECT
						p.idpedido, p.numpedido, p.correlativo_dia, p.fecha_hora, p.total_r,
						p.pwa_delivery_status, p.pwa_estado, p.idrepartidor,
						r.nombre, r.apellido, r.telefono,
						r.online, r.ocupado, r.socketid, r.idsede_suscrito,
						r.position_now, r.position_now_fecha,
						(r.idsede_suscrito = p.idsede) AS es_propio,
						(COALESCE(r.fcm_token,'') <> '' OR COALESCE(r.pwa_code_verification,'') <> ''
						   OR r.position_now IS NOT NULL) AS tiene_app,
						(SELECT MIN(e.fecha) FROM repartidor_pedido_entregado e
						  WHERE e.idpedido = p.idpedido) AS fecha_entrega,
						-- lo que el repartidor declaro al entregar: metodo de pago e importe. Es una
						-- referencia para dar cuenta a caja, no el registro contable definitivo.
						(SELECT e2.operacion FROM repartidor_pedido_entregado e2
						  WHERE e2.idpedido = p.idpedido ORDER BY e2.fecha LIMIT 1) AS operacion,
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.direccionEnvioSelected.latitude')) END AS dest_lat,
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.direccionEnvioSelected.longitude')) END AS dest_lng,
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.direccionEnvioSelected.direccion')) END AS dest_direccion,
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.direccionEnvioSelected.referencia')) END AS dest_referencia,
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.nombre')) END AS cliente
					FROM pedido p
					LEFT JOIN repartidor r ON r.idrepartidor = p.idrepartidor
					WHERE p.idsede = ?
					  AND p.pwa_is_delivery = 1
					  AND p.estado != 3
					  AND p.fecha_hora >= CURDATE()
					  AND p.fecha_hora < CURDATE() + INTERVAL 1 DAY
					ORDER BY p.fecha_hora", array($g_idsede));

				// Los propios de la sede van aparte: la consulta de arriba parte de `pedido`, asi que un
				// repartidor sin pedidos hoy no apareceria, y es justo el que esta libre para asignarle uno.
				$propios = filas($bd, "
					SELECT r.idrepartidor, r.nombre, r.apellido, r.telefono,
						   r.online, r.ocupado, r.socketid, r.idsede_suscrito,
						   1 AS es_propio, r.position_now, r.position_now_fecha,
						   (COALESCE(r.fcm_token,'') <> '' OR COALESCE(r.pwa_code_verification,'') <> ''
						      OR r.position_now IS NOT NULL) AS tiene_app
					  FROM repartidor r
					 WHERE r.idsede_suscrito = ? AND r.estado = 0
					 ORDER BY r.nombre", array($g_idsede));

				jsonOut(true, array(
					'sede'    => isset($sede[0]) ? $sede[0] : null,
					'filas'   => $rows ? $rows : array(),
					'propios' => $propios ? $propios : array()
				));
				break;

			// Sin coordenadas en la direccion no hay nada que dibujar: el pedido existe pero el mapa no
			// puede ubicarlo. El ajuste vive en la configuracion de la sede; se expone aqui porque es
			// donde el encargado descubre el problema.
			case 'set-busqueda-mapa':
				$body = leerBody();
				$valor = !empty($body['habilitar']) ? 1 : 0;
				ejecutar($bd, "UPDATE sede SET pwa_habilitar_busqueda_mapa = ? WHERE idsede = ?",
					array($valor, $g_idsede));
				jsonOut(true, array('habilitar' => $valor));
				break;

			// El local es el origen de todas las distancias y tiempos de esta pantalla, asi que si esta
			// mal ubicado todo lo demas miente. Se corrige arrastrando el marcador, no escribiendo.
			case 'set-ubicacion-sede':
				$body = leerBody();
				$lat = isset($body['latitude']) ? (float)$body['latitude'] : null;
				$lng = isset($body['longitude']) ? (float)$body['longitude'] : null;
				if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)
					|| $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180
					|| ($lat == 0 && $lng == 0)) {
					jsonOut(false, null, 'Esas coordenadas no son validas.');
				}
				ejecutar($bd, "UPDATE sede SET latitude = ?, longitude = ? WHERE idsede = ?",
					array($lat, $lng, $g_idsede));
				jsonOut(true, array('latitude' => $lat, 'longitude' => $lng));
				break;

			case 'asignar':
				$body = leerBody();
				$idpedido = (int)(isset($body['idpedido']) ? $body['idpedido'] : 0);
				$idrepartidor = (int)(isset($body['idrepartidor']) ? $body['idrepartidor'] : 0);
				if (!$idpedido || !$idrepartidor) { jsonOut(false, null, 'Faltan datos para asignar.'); }

				// el pedido tiene que ser de ESTA sede: si no, cualquiera podria asignar pedidos ajenos
				$ped = filas($bd, "SELECT idpedido, COALESCE(idrepartidor,0) AS idrep
									 FROM pedido
									WHERE idpedido = ? AND idsede = ? AND pwa_is_delivery = 1 AND estado != 3",
					array($idpedido, $g_idsede));
				if (!$ped) { jsonOut(false, null, 'Ese pedido no existe o no es de esta sede.'); }
				if ((int)$ped[0]['idrep'] > 0) { jsonOut(false, null, 'Ese pedido ya tiene repartidor.'); }

				// propio de la sede, o de la red (global). Nunca el propio de otro local.
				$rep = filas($bd, "SELECT idrepartidor, nombre, apellido, telefono
									 FROM repartidor
									WHERE idrepartidor = ? AND estado = 0
									  AND (idsede_suscrito = ? OR COALESCE(idsede_suscrito,0) = 0)",
					array($idrepartidor, $g_idsede));
				if (!$rep) { jsonOut(false, null, 'Ese repartidor no esta disponible para esta sede.'); }

				// la condicion del WHERE es la que evita que dos terminales asignen el mismo pedido:
				// el segundo no afecta ninguna fila y se entera aqui, no cuando el repartidor reclame
				$n = ejecutar($bd, "UPDATE pedido SET idrepartidor = ?, pwa_estado = 'R'
									 WHERE idpedido = ? AND COALESCE(idrepartidor,0) = 0",
					array($idrepartidor, $idpedido));
				if ($n === 0) { jsonOut(false, null, 'Otro terminal acaba de asignar ese pedido.'); }

				jsonOut(true, array(
					'idpedido' => $idpedido,
					'idrepartidor' => $idrepartidor,
					'nombre' => trim($rep[0]['nombre'] . ' ' . $rep[0]['apellido']),
					'telefono' => $rep[0]['telefono']
				));
				break;

			default:
				jsonOut(false, null, 'Operacion desconocida.');
		}
	} catch (Exception $ex) {
		// sin esto, una falla de la consulta en produccion no deja ningun rastro que seguir
		error_log('[tracker] op ' . $op . ': ' . $ex->getMessage());
		jsonOut(false, null, 'No se pudo leer el estado del delivery.');
	}
?>

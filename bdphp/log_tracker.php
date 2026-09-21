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
	// a la caja de quien entra lo que rinde el repartidor: la del usuario que lo recibe
	$g_idusuario = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

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
	/**
	 * Cobra un pedido con el MISMO procedimiento que usa la pantalla de pago del POS,
	 * procedure_registrar_pago_restobar. Asi el cobro queda igual que si se hubiera hecho desde
	 * Control de pedidos: mismos inserts, mismo trigger, mismos subtotales, mismo detalle de items.
	 *
	 * Se llama con is_pago_parcial = 1 y la lista de items explicita. La rama de pago total del
	 * procedimiento arma los items con LOCATE(idpedido, id_pedido), que es una coincidencia por
	 * subcadena: cobrando el pedido 26114 arrastraria cualquier pedido abierto cuyo id sea parte de
	 * ese texto, como el 114 o el 611. Pasando los items a mano se evita por completo.
	 *
	 * $lineasPago: array de array(idtipo_pago, importe) - $monto: lo que entrega por este pedido.
	 */
	function crearCobro($bd, $idsede, $idusuario, $fila, $monto, $lineasPago) {
		$idpedido = (int)$fila['idpedido'];

		$items = filas($bd, "SELECT idpedido_detalle, cantidad, ptotal
							   FROM pedido_detalle
							  WHERE idpedido = ? AND estado = 0 AND pagado = 0",
			array($idpedido));

		$seleccionados = array();
		$sumaItems = 0;
		foreach ($items as $it) {
			$imp = round((float)str_replace(',', '', $it['ptotal']), 2);
			if ($imp <= 0) { continue; }
			$seleccionados[] = array(
				'idpedido' => $idpedido,
				'idpedido_detalle' => (int)$it['idpedido_detalle'],
				'cantidad' => (string)$it['cantidad'],
				'ptotal' => number_format($imp, 2, '.', '')
			);
			$sumaItems += $imp;
		}
		$sumaItems = round($sumaItems, 2);

		// El costo de entrega no es un item del pedido: va en su propia linea, sin idpedido_detalle,
		// para que el cobro cierre por el monto que realmente se recibe. Los pedidos viejos del PWA
		// no tienen pedido_detalle y para ellos esta linea es la unica.
		$envio = round($monto - $sumaItems, 2);
		if ($envio > 0.004) {
			$seleccionados[] = array(
				'idpedido' => $idpedido, 'idpedido_detalle' => 0,
				'cantidad' => '1', 'ptotal' => number_format($envio, 2, '.', '')
			);
		}

		$subtotales = array();
		if ($sumaItems > 0.004) {
			$subtotales[] = array('descripcion' => 'Sub Total', 'importe' => number_format($sumaItems, 2, '.', ''));
		}
		if ($envio > 0.004) {
			$subtotales[] = array('descripcion' => 'Costo de entrega', 'importe' => number_format($envio, 2, '.', ''));
		}
		// el procedimiento toma el ULTIMO subtotal como el total del registro de pago
		$subtotales[] = array('descripcion' => 'Total', 'importe' => number_format($monto, 2, '.', ''));

		$tipoPago = array();
		foreach ($lineasPago as $l) {
			$tipoPago[] = array('id' => (int)$l[0], 'importe' => number_format($l[1], 2, '.', ''));
		}

		$dt = array(
			'p_header' => array('tipo_consumo' => (int)$fila['idtipo_consumo']),
			'p_subtotales' => $subtotales,
			'p_tipo_pago' => $tipoPago,
			'p_comprobante' => array('idtipo_comprobante' => 0),
			'id_pedido' => (string)$idpedido,
			'idorg' => (int)$fila['idorg'],
			'idsede' => $idsede,
			'idusuario' => $idusuario,
			'idcliente' => (int)$fila['idcliente'],
			'is_pago_parcial' => 1,
			'p_item_seleccionados' => $seleccionados
		);

		$json = $bd->bd->real_escape_string(json_encode($dt));
		$rpt = $bd->xDevolverUnDatoSP("call procedure_registrar_pago_restobar('" . $json . "')");
		$dec = json_decode($rpt, true);
		$idrp = isset($dec['idregistro_pago']) ? (int)$dec['idregistro_pago'] : 0;
		if (!$idrp) { throw new Exception('el cobro del pedido ' . $idpedido . ' no se registro'); }

		// El procedimiento no llena fecha_hora: solo escribe `fecha`, que es texto. Los inserts
		// directos de log_001.php si la llenan. Y sin ella el cobro es invisible: tanto el listado
		// de Registro de Pagos como los indicadores acotan por
		// `rp.fecha_hora BETWEEN inicio AND fin`, y un NULL nunca cae dentro del rango.
		ejecutar($bd, "UPDATE registro_pago SET fecha_hora = NOW() WHERE idregistro_pago = ?",
			array($idrp));

		return $idrp;
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
						p.idpedido, p.numpedido, p.correlativo_dia, p.fecha_hora, p.total_r, p.total,
						-- el costo de delivery del pedido; si no viene, la diferencia con el total del pedido
						CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(p.json_datos_delivery,
							'$.p_header.arrDatosDelivery.costoTotalDelivery')) END AS costo_delivery,
						-- ya rendido a caja: no puede volver a rendirse
						EXISTS(SELECT 1 FROM pedido_repartidor_pagado rp
						        WHERE rp.idpedido = p.idpedido AND rp.estado = '0') AS rendido,
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

				// Los metodos que acepta la sede, para que el cajero pueda corregir el que declaro el
				// repartidor: es normal que cobre en efectivo y termine yapeando el total del turno.
				// Mismo criterio que x-comp-find-tipo-pago-option, que es el que usa la pantalla de cobro:
				// los que la sede acepta, fuera APLICACION (4), que es solo para pagos desde la app.
				$metodos = filas($bd, "
					SELECT tp.idtipo_pago, tp.descripcion
					  FROM tipo_pago tp
					  JOIN sede s ON s.idsede = ?
					 WHERE tp.estado = 0 AND tp.idtipo_pago <> 4
					   AND (COALESCE(s.metodo_pago_aceptados,'') = ''
							OR FIND_IN_SET(tp.idtipo_pago, s.metodo_pago_aceptados))
					 ORDER BY tp.orden, tp.idtipo_pago", array($g_idsede));

				jsonOut(true, array(
					'sede'     => isset($sede[0]) ? $sede[0] : null,
					'filas'    => $rows ? $rows : array(),
					'propios'  => $propios ? $propios : array(),
					'metodos'  => $metodos ? $metodos : array()
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

			// "Nunca llego". El recorrido guardado responde eso con el trazo real en el mapa, en vez
			// de la palabra del repartidor contra la del cliente.
			//
			// El dato que zanja el reclamo es `metros_final`: la distancia entre el ultimo punto
			// grabado y la direccion del pedido. Se calcula aqui y no en el navegador para que la
			// cifra que se le muestra al cliente sea siempre la misma.
			//
			// OJO: ese ultimo punto NO siempre es donde estaba al entregar. La grabacion se corta al
			// marcar la entrega, pero tambien se corta sola si el telefono se queda sin señal o
			// Android mata la app. Por eso se devuelven tambien `fin` y `fecha_entrega`: quien pinta
			// esto compara las dos y, si no coinciden, no emite veredicto. Un recorrido cortado que
			// se lee como "no llego" acusa en falso justo cuando se lo usa para decidir.
			case 'recorrido':
				$body = leerBody();
				$idpedido = (int)(isset($body['idpedido']) ? $body['idpedido'] : 0);
				if ($idpedido <= 0) { jsonOut(false, null, 'Falta el pedido.'); }

				// el idsede del WHERE es la autorizacion: una sede no puede pedir el recorrido de otra
				$fila = filas($bd, "
					SELECT p.idpedido, p.correlativo_dia, p.numpedido, p.fecha_hora,
						   r.nombre, r.apellido,
						   CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(
							   p.json_datos_delivery, '$.p_header.arrDatosDelivery.direccionEnvioSelected.latitude')) END AS dest_lat,
						   CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(
							   p.json_datos_delivery, '$.p_header.arrDatosDelivery.direccionEnvioSelected.longitude')) END AS dest_lng,
						   CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(
							   p.json_datos_delivery, '$.p_header.arrDatosDelivery.direccionEnvioSelected.direccion')) END AS dest_direccion,
						   (SELECT MIN(e.fecha) FROM repartidor_pedido_entregado e
							 WHERE e.idpedido = p.idpedido) AS fecha_entrega,
						   -- el recorrido guarda a quien lo empezo; el pedido, a quien lo tiene ahora.
						   -- Si el pedido se reasigno a mitad de camino son personas distintas y el
						   -- trazo trae tramos de las dos: hay que decirlo, no rotularlo con un nombre.
						   p.idrepartidor AS idrep_pedido, rr.idrepartidor AS idrep_recorrido,
						   rr.inicio, rr.fin, rr.puntos
					  FROM pedido p
					  LEFT JOIN repartidor r ON r.idrepartidor = p.idrepartidor
					  LEFT JOIN repartidor_recorrido rr ON rr.idpedido = p.idpedido
					 WHERE p.idpedido = ? AND p.idsede = ?", array($idpedido, $g_idsede));

				if (!$fila) { jsonOut(false, null, 'Ese pedido no es de esta sede.'); }
				$f = $fila[0];

				$puntos = $f['puntos'] ? json_decode($f['puntos'], true) : array();
				if (!is_array($puntos)) { $puntos = array(); }

				// Metros entre el final del recorrido y la direccion. Null si falta cualquiera de
				// los dos: mejor no mostrar nada que mostrar una distancia inventada.
				$metrosFinal = null;
				$dLat = ($f['dest_lat'] === null || $f['dest_lat'] === '') ? null : (float)$f['dest_lat'];
				$dLng = ($f['dest_lng'] === null || $f['dest_lng'] === '') ? null : (float)$f['dest_lng'];
				if ($puntos && $dLat !== null && $dLng !== null) {
					$ultimo = $puntos[count($puntos) - 1];
					if (is_array($ultimo) && count($ultimo) >= 2) {
						$R = 6371000;
						$la1 = deg2rad((float)$ultimo[0]); $la2 = deg2rad($dLat);
						$dla = $la2 - $la1; $dlo = deg2rad($dLng - (float)$ultimo[1]);
						$a = sin($dla / 2) * sin($dla / 2)
						   + cos($la1) * cos($la2) * sin($dlo / 2) * sin($dlo / 2);
						$metrosFinal = (int)round(2 * $R * asin(min(1, sqrt($a))));
					}
				}

				$sedeR = filas($bd, "SELECT nombre, latitude, longitude FROM sede WHERE idsede = ?",
					array($g_idsede));

				jsonOut(true, array(
					'idpedido'      => (int)$f['idpedido'],
					'correlativo'   => $f['correlativo_dia'],
					'numpedido'     => $f['numpedido'],
					'fecha_pedido'  => $f['fecha_hora'],
					'fecha_entrega' => $f['fecha_entrega'],
					'repartidor'    => trim($f['nombre'] . ' ' . $f['apellido']),
					'dest_lat'      => $dLat,
					'dest_lng'      => $dLng,
					'dest_direccion' => $f['dest_direccion'],
					'sede'          => $sedeR ? $sedeR[0] : null,
					'inicio'        => $f['inicio'],
					'fin'           => $f['fin'],
					'puntos'        => $puntos,
					'reasignado'    => ($f['idrep_recorrido'] !== null
										&& (int)$f['idrep_recorrido'] !== (int)$f['idrep_pedido']),
					'metros_final'  => $metrosFinal
				));
				break;

			// El repartidor rinde y el cajero recibe. Se cobra como lo haria la pantalla de pago del POS.
			//
			// Dos formas, porque son dos situaciones distintas:
			//  - modo 'pedido': cada pedido se cobra por separado, con su metodo y su cliente. Es un
			//    registro_pago por pedido, igual que si se cobrara uno por uno desde Control de pedidos.
			//  - modo 'mano': el repartidor junta todo y entrega, por ejemplo, 100 en efectivo y 32 por
			//    Yape, sin seguir la linea de los pedidos. Eso es UN cobro que cubre varios pedidos, que
			//    es lo mismo que hace el POS al cobrar varias mesas juntas.
			//
			// Los montos los calcula el servidor: del cliente solo se aceptan los pedidos y los metodos.
			case 'rendir':
				$body = leerBody();
				$idrepartidor = (int)(isset($body['idrepartidor']) ? $body['idrepartidor'] : 0);
				$modo = (isset($body['modo']) && $body['modo'] === 'mano') ? 'mano' : 'pedido';

				// modo pedido: items[{idpedido, idtipo_pago}] · modo mano: idpedidos[] + pagos[{...}]
				$porPedido = array();
				if ($modo === 'pedido') {
					$items = isset($body['items']) && is_array($body['items']) ? $body['items'] : array();
					foreach ($items as $it) {
						$idp = (int)(isset($it['idpedido']) ? $it['idpedido'] : 0);
						$tp = (int)(isset($it['idtipo_pago']) ? $it['idtipo_pago'] : 0);
						if ($idp > 0 && $tp > 0) { $porPedido[$idp] = $tp; }
					}
					$ids = array_keys($porPedido);
				} else {
					$pedidos = isset($body['idpedidos']) && is_array($body['idpedidos']) ? $body['idpedidos'] : array();
					$tmp = array();
					foreach ($pedidos as $x) { $n = (int)$x; if ($n > 0) { $tmp[$n] = $n; } }
					$ids = array_values($tmp);
				}
				if (!$idrepartidor || !$ids) { jsonOut(false, null, 'No hay pedidos que rendir.'); }

				$lineas = array();
				$suma = 0;
				if ($modo === 'mano') {
					$pagos = isset($body['pagos']) && is_array($body['pagos']) ? $body['pagos'] : array();
					foreach ($pagos as $pg) {
						$tp = (int)(isset($pg['idtipo_pago']) ? $pg['idtipo_pago'] : 0);
						$imp = round((float)(isset($pg['importe']) ? $pg['importe'] : 0), 2);
						if ($tp > 0 && $imp > 0) { $lineas[] = array($tp, $imp); $suma += $imp; }
					}
					if (!$lineas) { jsonOut(false, null, 'Indica con que metodos te esta pagando.'); }
				}

				$marca = str_repeat('?,', count($ids) - 1) . '?';
				$filas = filas($bd, "
					SELECT p.idpedido, p.idorg, p.idcliente, p.idtipo_consumo,
						   CAST(REPLACE(p.total_r, ',', '') AS DECIMAL(12,2)) AS cobro,
						   CAST(REPLACE(p.total, ',', '') AS DECIMAL(12,2)) AS comida,
						   (r.idsede_suscrito = p.idsede) AS es_propio,
						   CASE WHEN JSON_VALID(p.json_datos_delivery) THEN JSON_UNQUOTE(JSON_EXTRACT(
							   p.json_datos_delivery, '$.p_header.arrDatosDelivery.costoTotalDelivery')) END AS costo_delivery
					  FROM pedido p
					  LEFT JOIN repartidor r ON r.idrepartidor = p.idrepartidor
					 WHERE p.idpedido IN ($marca) AND p.idsede = ? AND p.idrepartidor = ?
					   AND COALESCE(p.pwa_delivery_status,'0') = '4' AND p.estado <> 3
					   AND COALESCE(p.idregistro_pago,0) = 0
					   AND NOT EXISTS (SELECT 1 FROM pedido_repartidor_pagado rp
										WHERE rp.idpedido = p.idpedido AND rp.estado = '0')",
					array_merge($ids, array($g_idsede, $idrepartidor)));

				if (count($filas) !== count($ids)) {
					jsonOut(false, null, 'Algun pedido ya fue cobrado o rendido. Actualiza la pantalla.');
				}

				// cuanto entrega por cada pedido: el propio todo, el global sin su costo de envio
				$montos = array();
				$total = 0;
				foreach ($filas as $f) {
					$envio = ($f['costo_delivery'] === null || $f['costo_delivery'] === '')
						? max(0, (float)$f['cobro'] - (float)$f['comida'])
						: (float)$f['costo_delivery'];
					$m = ((int)$f['es_propio'] === 1) ? (float)$f['cobro'] : ((float)$f['cobro'] - $envio);
					$montos[(int)$f['idpedido']] = round(max(0, $m), 2);
					$total += $montos[(int)$f['idpedido']];
				}
				$total = round($total, 2);

				if ($modo === 'mano' && abs($suma - $total) > 0.01) {
					jsonOut(false, null, 'El reparto por metodo suma S/ ' . number_format($suma, 2)
						. ' y deberia sumar S/ ' . number_format($total, 2) . '.');
				}

				$creados = 0;

				$bd->bd->begin_transaction();
				try {
					// el candado va primero: si otro cajero rindio alguno, no se cobra nada
					foreach ($ids as $idp) {
						$ok = ejecutar($bd, "INSERT INTO pedido_repartidor_pagado
												(idrepartidor, idsede, idusuario, idpedido, fecha, estado)
											 SELECT ?, ?, ?, ?, NOW(), '0' FROM DUAL WHERE NOT EXISTS (
												 SELECT 1 FROM pedido_repartidor_pagado rp
												  WHERE rp.idpedido = ? AND rp.estado = '0')",
							array($idrepartidor, $g_idsede, $g_idusuario, $idp, $idp));
						if ($ok !== 1) { throw new Exception('el pedido ' . $idp . ' ya fue rendido'); }
					}

					// Siempre un cobro por pedido, en los dos modos: si un solo cobro cubriera varios
					// pedidos, los reportes por pedido quedarian inconsistentes y el cliente del cobro
					// seria el del primero de la lista.
					//
					// En modo 'mano' el reparto que entrego el repartidor se distribuye en cascada: cada
					// pedido va tomando de las lineas de pago hasta cubrirse. Con 100 en efectivo y 33 por
					// Yape para pedidos de 90 y 43, el primero queda todo en efectivo y el segundo mixto,
					// 10 en efectivo y 33 por Yape.
					$pool = $lineas;   // vacio en modo 'pedido'
					$iPool = 0;

					foreach ($filas as $f) {
						$idp = (int)$f['idpedido'];
						$monto = $montos[$idp];
						if ($monto <= 0) { continue; }

						if ($modo === 'pedido') {
							$lineasPedido = array(array($porPedido[$idp], $monto));
						} else {
							$lineasPedido = array();
							$restante = $monto;
							while ($restante > 0.004 && $iPool < count($pool)) {
								$disp = $pool[$iPool][1];
								$toma = round(min($disp, $restante), 2);
								if ($toma > 0.004) { $lineasPedido[] = array($pool[$iPool][0], $toma); }
								$pool[$iPool][1] = round($disp - $toma, 2);
								$restante = round($restante - $toma, 2);
								if ($pool[$iPool][1] <= 0.004) { $iPool++; }
							}
							// el redondeo puede dejar centavos sueltos: van a la ultima linea usada
							if ($restante > 0.004 && $lineasPedido) {
								$u = count($lineasPedido) - 1;
								$lineasPedido[$u][1] = round($lineasPedido[$u][1] + $restante, 2);
							} elseif ($restante > 0.004) {
								throw new Exception('el reparto no alcanza a cubrir el pedido ' . $idp);
							}
						}

						crearCobro($bd, $g_idsede, $g_idusuario, $f, $monto, $lineasPedido);
						$creados++;
					}

					$bd->bd->commit();
				} catch (Exception $ex) {
					$bd->bd->rollback();
					jsonOut(false, null, 'No se pudo registrar la rendicion: ' . $ex->getMessage());
				}

				jsonOut(true, array('pedidos' => count($filas), 'cobros' => $creados, 'total' => $total));
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

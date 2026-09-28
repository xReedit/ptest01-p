<?php
	// Modulo Compras (rediseño). Plan: plan/COMPRAS-REDISENO-PLAN.md
	// El stock lo suma el trigger stock_insert_add_compra (AFTER INSERT compra_items) y el kardex el
	// trigger kardex_ingreso (AFTER INSERT compra): aqui solo se escriben las mismas tablas de siempre,
	// ahora en una transaccion y con consultas preparadas.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	require_once __DIR__ . '/costeo_recalculo.php';
	require_once __DIR__ . '/factura_ia.php';
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): estas pantallas siempre mandan JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0 && $op !== 'factura-leer') {
		http_response_code(415);
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode(array('success' => false, 'datos' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_us = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

	const COMPRA_TIPO_PAGO_CREDITO = 3;

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
	// Numero para columnas varchar legacy: sin ceros de mas ('10', '2.5', '18.4567')
	function numTxt($n, $dec) {
		$t = rtrim(rtrim(number_format((float)$n, $dec, '.', ''), '0'), '.');
		return $t === '' || $t === '-0' ? '0' : $t;
	}
	// 'YYYY-MM-DD' valida -> DateTime; si no, null
	function fechaIso($s) {
		if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return null; }
		$d = DateTime::createFromFormat('!Y-m-d', $s);
		return ($d && $d->format('Y-m-d') === $s) ? $d : null;
	}
	function mayus($s, $max) {
		return mb_substr(mb_strtoupper(trim((string)$s), 'UTF-8'), 0, $max, 'UTF-8');
	}
	class ErrorUsuario extends Exception {}

	if ($g_idsede <= 0 || $g_ido <= 0) { jsonOut(false, null, 'Sesion sin sede'); }
	// Mismo permiso que la opcion Compras (B1)
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? $_SESSION['acc'] : '') . ',';
	if (strpos($g_acc, ',B1,') === false) { jsonOut(false, null, 'No tienes permiso de Compras. Solicitalo al administrador.'); }

	try {
		switch ($op) {
			case 'init': {
				jsonOut(true, array(
					'almacenes' => filas($bd, "SELECT idalmacen, descripcion FROM almacen WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion", array($g_ido, $g_idsede)),
					'tipos_pago' => filas($bd, "SELECT idtipo_pago, descripcion FROM tipo_pago WHERE estado = 0 AND visible = '0' ORDER BY idtipo_pago"),
					'proveedores' => filas($bd, "SELECT idproveedor, descripcion, dni, telefono FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion", array($g_ido, $g_idsede)),
					'familias' => filas($bd, "SELECT idproducto_familia, descripcion FROM producto_familia WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion", array($g_ido, $g_idsede)),
					'unidades' => filas($bd, "SELECT idunidad_medida, unidad, abreviatura FROM unidad_medida WHERE estado = 0 ORDER BY unidad"),
					'credito' => COMPRA_TIPO_PAGO_CREDITO
				));
			}
			case 'productos': {
				// Ultimo precio de compra real (producto_historial_precio vigente); si no hay, el precio_unitario guardado
				$r = filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia,
						IFNULL(uk.abreviatura, '') AS und,
						(SELECT CAST(h.precio AS DECIMAL(14,4)) FROM producto_historial_precio h
						  WHERE h.idproducto = p.idproducto AND h.estado = 0 ORDER BY h.idproducto_historial_precio DESC LIMIT 1) AS ultimo_precio,
						(SELECT h.fecha FROM producto_historial_precio h
						  WHERE h.idproducto = p.idproducto AND h.estado = 0 ORDER BY h.idproducto_historial_precio DESC LIMIT 1) AS ultima_fecha,
						(p.precio_unitario + 0) AS precio_unitario
					FROM producto p
					LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0
					ORDER BY p.descripcion", array($g_ido, $g_idsede));
				jsonOut(true, $r);
			}
			case 'proveedor-crear': {
				$b = leerBody();
				$nombre = mayus(isset($b['descripcion']) ? $b['descripcion'] : '', 150);
				$dni = mayus(isset($b['dni']) ? $b['dni'] : '', 20);
				$tel = mayus(isset($b['telefono']) ? $b['telefono'] : '', 50);
				if ($nombre === '') { jsonOut(false, null, 'Escribe el nombre del proveedor'); }
				// Evita duplicados: mismo RUC/DNI o mismo nombre en la sede -> devuelve el existente
				$ex = filas($bd, "SELECT idproveedor, descripcion, dni, telefono FROM proveedor
					WHERE idorg = ? AND idsede = ? AND estado = 0 AND ((? <> '' AND dni = ?) OR UPPER(descripcion) = ?)
					ORDER BY idproveedor LIMIT 1", array($g_ido, $g_idsede, $dni, $dni, $nombre));
				if ($ex) { jsonOut(true, array_merge($ex[0], array('existente' => 1))); }
				ejecutar($bd, "INSERT INTO proveedor (idorg, idsede, descripcion, dni, direccion, telefono, estado) VALUES (?, ?, ?, ?, '', ?, 0)",
					array($g_ido, $g_idsede, $nombre, $dni, $tel));
				jsonOut(true, array('idproveedor' => $bd->bd->insert_id, 'descripcion' => $nombre, 'dni' => $dni, 'telefono' => $tel, 'existente' => 0));
			}
			case 'producto-crear': {
				$b = leerBody();
				$nombre = mayus(isset($b['descripcion']) ? $b['descripcion'] : '', 150);
				// La familia llega por nombre: si no existe en la sede se crea (como la pantalla vieja, op 1602)
				$famNombre = mayus(isset($b['familia']) ? $b['familia'] : '', 80);
				$und = isset($b['idunidad_kardex']) ? (int)$b['idunidad_kardex'] : 0;
				if ($nombre === '') { jsonOut(false, null, 'Escribe el nombre del producto'); }
				if ($famNombre === '') { jsonOut(false, null, 'Escribe la familia del producto'); }
				if ($und > 0 && !filas($bd, "SELECT idunidad_medida FROM unidad_medida WHERE idunidad_medida = ? AND estado = 0", array($und))) {
					jsonOut(false, null, 'Unidad no valida');
				}
				$ex = filas($bd, "SELECT idproducto FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ? LIMIT 1",
					array($g_ido, $g_idsede, $nombre));
				if ($ex) { jsonOut(false, null, 'Ya existe un producto con ese nombre: buscalo en la lista.'); }
				$famNueva = 0;
				$bd->bd->begin_transaction();
				try {
					$f = filas($bd, "SELECT idproducto_familia FROM producto_familia WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ?
						ORDER BY idproducto_familia LIMIT 1", array($g_ido, $g_idsede, $famNombre));
					if (!$f) {
						// el trigger setid asigna el id ('f' + correlativo)
						ejecutar($bd, "INSERT INTO producto_familia (idproducto_familia, idorg, idsede, descripcion, estado) VALUES ('0', ?, ?, ?, 0)",
							array($g_ido, $g_idsede, $famNombre));
						$f = filas($bd, "SELECT idproducto_familia FROM producto_familia WHERE idorg = ? AND idsede = ? AND estado = 0 AND descripcion = ?
							ORDER BY idproducto_familia DESC LIMIT 1", array($g_ido, $g_idsede, $famNombre));
						if (!$f) { throw new Exception('familia creada sin id'); }
						$famNueva = 1;
					}
					$fam = $f[0]['idproducto_familia'];
					ejecutar($bd, "INSERT INTO producto (idorg, idsede, descripcion, codigo_barra, precio, precio_unitario, precio_venta,
							idproducto_familia, stock_minimo, img, estado, idunidad_kardex)
						VALUES (?, ?, ?, '', '0', '0', '0', ?, '0', '', 0, ?)",
						array($g_ido, $g_idsede, $nombre, $fam, $und > 0 ? $und : null));
					$id = $bd->bd->insert_id;
					$bd->bd->commit();
				} catch (Exception $e) {
					$bd->bd->rollback();
					throw $e;
				}
				$r = filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia, IFNULL(uk.abreviatura, '') AS und,
						NULL AS ultimo_precio, NULL AS ultima_fecha, 0 AS precio_unitario
					FROM producto p
					LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					WHERE p.idproducto = ?", array($id));
				jsonOut(true, array_merge($r[0], array('idproducto_familia' => $fam, 'familia_nueva' => $famNueva)));
			}
			case 'guardar': {
				$b = leerBody();
				$res = guardarCompra($bd, $b, $g_ido, $g_idsede, $g_us);
				jsonOut(true, $res);
			}
			case 'listar': {
				// Compras del mes (f_compra es varchar dd/mm/yyyy en el legacy)
				$b = leerBody();
				$mes = isset($b['mes']) && preg_match('/^\d{4}-\d{2}$/', $b['mes']) ? $b['mes'] : date('Y-m');
				$desde = $mes . '-01';
				$hasta = date('Y-m-t', strtotime($desde));
				$idprov = isset($b['idproveedor']) ? (int)$b['idproveedor'] : 0;
				$r = filas($bd, "SELECT c.idcompra, c.f_compra, c.f_pago, c.total, c.pagado, c.estado, c.idtipo_pago, c.comprobante,
						IFNULL(p.descripcion, '') AS proveedor, IFNULL(a.descripcion, '') AS almacen, IFNULL(tp.descripcion, '') AS tipo_pago,
						(SELECT COUNT(*) FROM compra_items ci WHERE ci.idcompra = c.idcompra) AS n_items
					FROM compra c
					LEFT JOIN proveedor p ON p.idproveedor = c.idproveedor
					LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
					LEFT JOIN tipo_pago tp ON tp.idtipo_pago = c.idtipo_pago
					WHERE c.idsede = ? AND STR_TO_DATE(c.f_compra, '%d/%m/%Y') BETWEEN ? AND ?
					  AND (? = 0 OR c.idproveedor = ?)
					ORDER BY STR_TO_DATE(c.f_compra, '%d/%m/%Y') DESC, c.idcompra DESC
					LIMIT 1000", array($g_idsede, $desde, $hasta, $idprov, $idprov));
				jsonOut(true, array('mes' => $mes, 'compras' => $r));
			}
			case 'detalle': {
				$b = leerBody();
				$id = isset($b['idcompra']) ? (int)$b['idcompra'] : 0;
				$c = filas($bd, "SELECT c.idcompra, c.f_compra, c.f_registro, c.f_pago, c.total, c.pagado, c.estado, c.idtipo_pago,
						c.comprobante, c.nota_de_compra, DATE_FORMAT(c.fecha_anula, '%d/%m/%Y %H:%i') AS fecha_anula,
						IFNULL(p.descripcion, '') AS proveedor, IFNULL(p.dni, '') AS proveedor_dni,
						IFNULL(a.descripcion, '') AS almacen, IFNULL(tp.descripcion, '') AS tipo_pago,
						IFNULL(u.usuario, '') AS usuario, IFNULL(ua.usuario, '') AS usuario_anula
					FROM compra c
					LEFT JOIN proveedor p ON p.idproveedor = c.idproveedor
					LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
					LEFT JOIN tipo_pago tp ON tp.idtipo_pago = c.idtipo_pago
					LEFT JOIN usuario u ON u.idusuario = c.idusuario
					LEFT JOIN usuario ua ON ua.idusuario = c.idusuario_anula
					WHERE c.idcompra = ? AND c.idsede = ?", array($id, $g_idsede));
				if (!$c) { throw new ErrorUsuario('Compra no encontrada'); }
				$items = filas($bd, "SELECT ci.idproducto, IFNULL(p.descripcion, '(producto eliminado)') AS descripcion,
						IFNULL(uk.abreviatura, '') AS und, ci.cantidad, ci.punitario, ci.ptotal
					FROM compra_items ci
					LEFT JOIN producto p ON p.idproducto = ci.idproducto
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					WHERE ci.idcompra = ?
					ORDER BY ci.idcompra_items", array($id));
				$doc = filas($bd, "SELECT datos_json, proveedor_ia FROM compra_documento WHERE idcompra = ? AND idsede = ? ORDER BY iddocumento DESC LIMIT 1", array($id, $g_idsede));
				$factura = null;
				if ($doc) {
					$dj = json_decode($doc[0]['datos_json'], true);
					if (is_array($dj) && isset($dj['resumen'])) { $factura = array('resumen' => $dj['resumen'], 'moneda' => $dj['factura']['comprobante']['moneda']); }
				}
				jsonOut(true, array('compra' => $c[0], 'items' => $items, 'factura' => $factura));
			}
			case 'config': {
				// ¿La sede recupera IGV? (define si el costo va con o sin IGV/percepcion)
				$b = leerBody();
				if (isset($b['recupera_igv'])) {
					ejecutar($bd, "INSERT INTO compras_config (idsede, recupera_igv) VALUES (?, ?) ON DUPLICATE KEY UPDATE recupera_igv = VALUES(recupera_igv)",
						array($g_idsede, (int)$b['recupera_igv'] ? 1 : 0));
				}
				$r = filas($bd, "SELECT recupera_igv FROM compras_config WHERE idsede = ?", array($g_idsede));
				jsonOut(true, array('recupera_igv' => $r ? (int)$r[0]['recupera_igv'] : 0));
			}
			case 'factura-leer': {
				jsonOut(true, leerFactura($bd, $g_ido, $g_idsede, $g_us));
			}
			case 'anular-preview': {
				$b = leerBody();
				$id = isset($b['idcompra']) ? (int)$b['idcompra'] : 0;
				$c = compraDeSede($bd, $id, $g_idsede);
				$items = filas($bd, "SELECT ci.idproducto, p.descripcion, IFNULL(uk.abreviatura, '') AS und,
						(ci.cantidad + 0) AS cantidad, IFNULL(ps.stock, 0) AS stock_actual,
						IFNULL(ps.stock, 0) - (ci.cantidad + 0) AS stock_final
					FROM compra_items ci
					JOIN producto p ON p.idproducto = ci.idproducto
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					LEFT JOIN producto_stock ps ON ps.idproducto = ci.idproducto AND ps.idalmacen = ?
					WHERE ci.idcompra = ?", array((int)$c['idalmacen'], $id));
				jsonOut(true, array('compra' => $c, 'items' => $items));
			}
			case 'anular': {
				$b = leerBody();
				$id = isset($b['idcompra']) ? (int)$b['idcompra'] : 0;
				anularCompra($bd, $id, $g_idsede, $g_us);
				costeoRecalcularSeguro($bd, $g_idsede, 'compra');
				jsonOut(true, array('idcompra' => $id));
			}
			default:
				jsonOut(false, null, 'Operacion no valida');
		}
	} catch (ErrorUsuario $e) {
		jsonOut(false, null, $e->getMessage());
	} catch (Exception $e) {
		error_log('log_compras.php op=' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'Error al procesar la solicitud');
	}

	function compraDeSede($bd, $id, $idsede) {
		$r = filas($bd, "SELECT c.idcompra, c.idalmacen, c.estado, c.total, c.f_compra, c.comprobante, a.descripcion AS almacen
			FROM compra c LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
			WHERE c.idcompra = ? AND c.idsede = ?", array($id, $idsede));
		if (!$r) { throw new ErrorUsuario('Compra no encontrada'); }
		return $r[0];
	}

	// Valida todo antes de tocar la base; el total se recalcula aqui (no se confia en el del navegador)
	function validarCompra($bd, $b, $ido, $idsede) {
		$idalmacen = isset($b['idalmacen']) ? (int)$b['idalmacen'] : 0;
		if (!filas($bd, "SELECT idalmacen FROM almacen WHERE idalmacen = ? AND idorg = ? AND idsede = ? AND estado = 0", array($idalmacen, $ido, $idsede))) {
			throw new ErrorUsuario('Elige a que almacen ingresa la compra');
		}
		$idprov = isset($b['idproveedor']) ? (int)$b['idproveedor'] : 0;
		if ($idprov > 0 && !filas($bd, "SELECT idproveedor FROM proveedor WHERE idproveedor = ? AND idorg = ? AND idsede = ?", array($idprov, $ido, $idsede))) {
			throw new ErrorUsuario('Proveedor no encontrado');
		}
		$idtp = isset($b['idtipo_pago']) ? (int)$b['idtipo_pago'] : 0;
		if (!filas($bd, "SELECT idtipo_pago FROM tipo_pago WHERE idtipo_pago = ? AND estado = 0", array($idtp))) {
			throw new ErrorUsuario('Elige la forma de pago');
		}
		$fecha = fechaIso(isset($b['fecha']) ? $b['fecha'] : '');
		if (!$fecha) { throw new ErrorUsuario('Fecha de compra no valida'); }
		if ($fecha > new DateTime('tomorrow')) { throw new ErrorUsuario('La fecha de compra no puede ser futura'); }
		$fpago = null;
		if ($idtp === COMPRA_TIPO_PAGO_CREDITO) {
			$fpago = fechaIso(isset($b['fecha_pago']) ? $b['fecha_pago'] : '');
			if (!$fpago) { throw new ErrorUsuario('Compra a credito: indica la fecha de pago'); }
			// sin proveedor la deuda no se puede cobrar ni aparece en Cuentas por pagar
			if ($idprov <= 0) { throw new ErrorUsuario('Compra a credito: elige el proveedor (a quien le debes)'); }
		}
		$items = isset($b['items']) && is_array($b['items']) ? $b['items'] : array();
		if (!$items) { throw new ErrorUsuario('Agrega al menos un producto'); }
		if (count($items) > 300) { throw new ErrorUsuario('Demasiados productos en una sola compra'); }

		$lineas = array();
		$total = 0;
		foreach ($items as $i => $it) {
			$idp = isset($it['idproducto']) ? (int)$it['idproducto'] : 0;
			$cant = isset($it['cantidad']) ? (float)$it['cantidad'] : 0;
			$pu = isset($it['precio_unitario']) ? (float)$it['precio_unitario'] : -1;
			$p = filas($bd, "SELECT descripcion FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ? AND estado = 0", array($idp, $ido, $idsede));
			if (!$p) { throw new ErrorUsuario('Producto no encontrado (linea ' . ($i + 1) . ')'); }
			$nom = $p[0]['descripcion'];
			if ($cant <= 0 || $cant > 999999) { throw new ErrorUsuario('Cantidad no valida en ' . $nom); }
			if ($pu < 0 || $pu > 999999) { throw new ErrorUsuario('Precio no valido en ' . $nom); }
			$ptotal = round($cant * $pu, 2);
			// columnas varchar(10) del legacy
			if (strlen(number_format($ptotal, 2, '.', '')) > 10 || strlen(numTxt($cant, 4)) > 10) { throw new ErrorUsuario('Monto demasiado grande en ' . $nom); }
			$lineas[] = array('idproducto' => $idp, 'cantidad' => numTxt($cant, 4), 'punitario' => numTxt($pu, 4), 'ptotal' => number_format($ptotal, 2, '.', ''));
			$total += $ptotal;
		}
		$total = round($total, 2);
		if (strlen(number_format($total, 2, '.', '')) > 10) { throw new ErrorUsuario('El total de la compra es demasiado grande'); }
		return array(
			'idalmacen' => $idalmacen, 'idproveedor' => $idprov, 'idtipo_pago' => $idtp,
			'fecha' => $fecha, 'fecha_pago' => $fpago, 'lineas' => $lineas, 'total' => $total,
			'comprobante' => mayus(isset($b['comprobante']) ? $b['comprobante'] : '', 40),
			'nota' => mb_substr(trim(isset($b['nota']) ? (string)$b['nota'] : ''), 0, 150, 'UTF-8')
		);
	}

	function guardarCompra($bd, $b, $ido, $idsede, $us) {
		// Doble clic / reintento: el mismo token devuelve la compra ya creada (la sesion serializa las peticiones)
		$token = isset($b['token']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$b['token']) : '';
		if ($token !== '' && isset($_SESSION['compra_tokens'][$token])) {
			return array('idcompra' => $_SESSION['compra_tokens'][$token], 'repetida' => 1);
		}
		$v = validarCompra($bd, $b, $ido, $idsede);
		$fCompra = $v['fecha']->format('d/m/Y');
		$credito = $v['idtipo_pago'] === COMPRA_TIPO_PAGO_CREDITO;
		$totalTxt = number_format($v['total'], 2, '.', ''); // como el legacy: '180.00'

		$bd->bd->begin_transaction();
		try {
			ejecutar($bd, "INSERT INTO compra (idorg, idsede, idtipo_pago, idalmacen, f_compra, f_registro, a_pagar, total, f_pago,
					idproveedor, nota_de_compra, comprobante, pagado, estado, idusuario, fecha_hora)
				VALUES (?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())",
				array($ido, $idsede, $v['idtipo_pago'], $v['idalmacen'], $fCompra, $totalTxt, $totalTxt,
					$v['fecha_pago'] ? $v['fecha_pago']->format('d/m/Y') : null,
					$v['idproveedor'], $v['nota'], $v['comprobante'] !== '' ? $v['comprobante'] : null, $credito ? 0 : 1, $us));
			$idcompra = (int)$bd->bd->insert_id;
			if ($idcompra <= 0) { throw new Exception('insert compra sin id'); }

			foreach ($v['lineas'] as $l) {
				// el trigger stock_insert_add_compra suma el stock en el almacen de la compra
				ejecutar($bd, "INSERT INTO compra_items (idcompra, idproducto, cantidad, punitario, ptotal) VALUES (?, ?, ?, ?, ?)",
					array($idcompra, $l['idproducto'], $l['cantidad'], $l['punitario'], $l['ptotal']));
				ejecutar($bd, "INSERT INTO producto_historial_precio (idproducto, fecha, idorg, idsede, precio, idproveedor, idcompra)
					VALUES (?, ?, ?, ?, ?, ?, ?)",
					array($l['idproducto'], $fCompra, $ido, $idsede, $l['punitario'], $v['idproveedor'], $idcompra));
				ejecutar($bd, "UPDATE producto SET precio_unitario = ? WHERE idproducto = ?", array($l['punitario'], $l['idproducto']));
				ejecutar($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
					SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock
					FROM producto_stock ps WHERE ps.idproducto = ? AND ps.idalmacen = ?",
					array('COMPRA #' . $idcompra, $l['cantidad'], $us, $idsede, $l['idproducto'], $v['idalmacen'], $l['idproducto'], $v['idalmacen']));
			}
			ejecutar($bd, "INSERT INTO compra_pago (idcompra, idtipo_pago, importe) VALUES (?, ?, ?)", array($idcompra, $v['idtipo_pago'], $totalTxt));
			guardarDatosFactura($bd, $b, $v, $idcompra, $idsede);
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}

		if ($token !== '') {
			if (!isset($_SESSION['compra_tokens']) || count($_SESSION['compra_tokens']) > 50) { $_SESSION['compra_tokens'] = array(); }
			$_SESSION['compra_tokens'][$token] = $idcompra;
		}

		// Costeo de recetas: el precio de los insumos cambio
		$alertas = costeoRecalcularSeguro($bd, $idsede, 'compra');
		$ids = array_map(function ($l) { return (int)$l['idproducto']; }, $v['lineas']);
		$platos = 0;
		try {
			$r = filas($bd, "SELECT COUNT(DISTINCT c.iditem) AS n FROM v_costeo_ingrediente c
				JOIN item i ON i.iditem = c.iditem AND i.idsede = ? AND i.estado = 0
				WHERE c.idproducto_insumo IN (" . implode(',', $ids) . ")", array($idsede));
			$platos = (int)$r[0]['n'];
		} catch (Exception $e) {
			error_log('log_compras platos afectados: ' . $e->getMessage()); // sin vistas de costeo: no bloquea la compra
		}
		$alm = filas($bd, "SELECT descripcion FROM almacen WHERE idalmacen = ?", array($v['idalmacen']));
		return array('idcompra' => $idcompra, 'total' => $v['total'], 'almacen' => $alm ? $alm[0]['descripcion'] : '',
			'platos_afectados' => $platos, 'alertas' => $alertas, 'repetida' => 0);
	}

	// Documento leido -> compra, y "texto de factura -> producto" aprendido para este proveedor
	function guardarDatosFactura($bd, $b, $v, $idcompra, $idsede) {
		$iddoc = isset($b['iddocumento']) ? (int)$b['iddocumento'] : 0;
		if ($iddoc > 0) {
			ejecutar($bd, "UPDATE compra_documento SET idcompra = ? WHERE iddocumento = ? AND idsede = ? AND idcompra IS NULL", array($idcompra, $iddoc, $idsede));
		}
		$alias = isset($b['alias']) && is_array($b['alias']) ? $b['alias'] : array();
		if ($v['idproveedor'] <= 0 || !$alias) { return; }
		$ids = array_map(function ($l) { return (int)$l['idproducto']; }, $v['lineas']);
		foreach (array_slice($alias, 0, 200) as $a) {
			$texto = facturaTextoNormal(isset($a['texto']) ? $a['texto'] : '');
			$idp = isset($a['idproducto']) ? (int)$a['idproducto'] : 0;
			$factor = isset($a['factor']) ? (float)$a['factor'] : 1;
			if ($texto === '' || !in_array($idp, $ids, true) || $factor <= 0 || $factor > 100000) { continue; }
			ejecutar($bd, "INSERT INTO compra_producto_alias (idsede, idproveedor, texto, idproducto, factor_unidad, usos)
				VALUES (?, ?, ?, ?, ?, 1)
				ON DUPLICATE KEY UPDATE idproducto = VALUES(idproducto), factor_unidad = VALUES(factor_unidad), usos = usos + 1",
				array($idsede, $v['idproveedor'], $texto, $idp, $factor));
		}
	}

	// Lee la foto/PDF subida y arma la propuesta de compra. El archivo NO se guarda: se lee desde el temporal de PHP.
	function leerFactura($bd, $ido, $idsede, $us) {
		$cfg = null;
		try { $cfg = facturaIaConfig(); } catch (FacturaIaError $e) { throw new ErrorUsuario($e->getMessage()); }
		@set_time_limit($cfg['timeout'] + 20);

		$f = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;
		if (!$f || !isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
			throw new ErrorUsuario('No llegó el archivo. Vuelve a elegir la foto o el PDF.');
		}
		if ($f['size'] > 10 * 1024 * 1024) { throw new ErrorUsuario('El archivo pesa más de 10 MB. Toma una foto más liviana o reduce el PDF.'); }
		$fi = new finfo(FILEINFO_MIME_TYPE);
		$mime = $fi->file($f['tmp_name']);
		if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'application/pdf'), true)) {
			throw new ErrorUsuario('Solo se pueden leer fotos (JPG, PNG) o PDF.');
		}
		$sha = hash_file('sha256', $f['tmp_name']);

		// misma factura ya registrada
		$dup = filas($bd, "SELECT idcompra FROM compra_documento WHERE idsede = ? AND sha256 = ? AND idcompra IS NOT NULL
			AND idcompra IN (SELECT idcompra FROM compra WHERE estado = 0) ORDER BY iddocumento DESC LIMIT 1", array($idsede, $sha));
		if ($dup) { throw new ErrorUsuario('Esta factura ya se registró en la compra #' . $dup[0]['idcompra'] . '.'); }

		// limite diario por sede (se cuenta el intento: cada lectura cuesta)
		$uso = filas($bd, "SELECT lecturas FROM compra_ia_uso WHERE idsede = ? AND fecha = CURDATE()", array($idsede));
		if ($uso && (int)$uso[0]['lecturas'] >= $cfg['limite']) {
			throw new ErrorUsuario('Se alcanzó el límite de ' . $cfg['limite'] . ' lecturas de hoy. Registra la compra a mano.');
		}
		ejecutar($bd, "INSERT INTO compra_ia_uso (idsede, fecha, lecturas) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE lecturas = lecturas + 1", array($idsede));

		try {
			list($factura, $seg) = facturaIaLeer($cfg, $f['tmp_name'], $mime);
		} catch (FacturaIaError $e) {
			throw new ErrorUsuario($e->getMessage());
		}
		list($lineas, $avisos, $resumen) = facturaCostos($factura);

		// proveedor por RUC
		$prov = null;
		$ruc = $factura['emisor']['ruc'];
		if ($ruc) {
			$pr = filas($bd, "SELECT idproveedor, descripcion, dni FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 AND dni = ? ORDER BY idproveedor LIMIT 1",
				array($ido, $idsede, $ruc));
			if ($pr) { $prov = $pr[0]; }
		}
		$idprov = $prov ? (int)$prov['idproveedor'] : 0;

		// mismo comprobante del mismo proveedor ya registrado
		$cp = $factura['comprobante'];
		if ($idprov && $cp['serie'] && $cp['numero']) {
			$num = $cp['serie'] . '-' . $cp['numero'];
			$d2 = filas($bd, "SELECT idcompra FROM compra WHERE idsede = ? AND idproveedor = ? AND estado = 0 AND comprobante LIKE ? LIMIT 1",
				array($idsede, $idprov, '%' . $num));
			if ($d2) { $avisos[] = array('tipo' => 'error', 'texto' => 'El comprobante ' . $num . ' de este proveedor ya está en la compra #' . $d2[0]['idcompra'] . '.'); }
		}

		// relacionar cada linea con un producto: 1) lo aprendido de este proveedor, 2) mismo nombre, 3) sugerencias
		$prods = filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(uk.abreviatura, '') AS und
			FROM producto p LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
			WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0", array($ido, $idsede));
		$porNombre = array();
		foreach ($prods as $i => $p) { $prods[$i]['n'] = facturaTextoNormal($p['descripcion']); $porNombre[$prods[$i]['n']] = $prods[$i]; }
		$alias = array();
		if ($idprov) {
			foreach (filas($bd, "SELECT a.texto, a.idproducto, a.factor_unidad FROM compra_producto_alias a
				JOIN producto p ON p.idproducto = a.idproducto AND p.estado = 0
				WHERE a.idsede = ? AND a.idproveedor = ?", array($idsede, $idprov)) as $a) { $alias[$a['texto']] = $a; }
		}
		$porId = array();
		foreach ($prods as $p) { $porId[(int)$p['idproducto']] = $p; }
		foreach ($lineas as $i => $l) {
			$t = facturaTextoNormal($l['descripcion']);
			$m = null;
			if (isset($alias[$t]) && isset($porId[(int)$alias[$t]['idproducto']])) {
				$pp = $porId[(int)$alias[$t]['idproducto']];
				$m = array('tipo' => 'aprendido', 'idproducto' => (int)$pp['idproducto'], 'descripcion' => $pp['descripcion'], 'und' => $pp['und'], 'factor' => (float)$alias[$t]['factor_unidad']);
			} elseif (isset($porNombre[$t])) {
				$pp = $porNombre[$t];
				$m = array('tipo' => 'nombre', 'idproducto' => (int)$pp['idproducto'], 'descripcion' => $pp['descripcion'], 'und' => $pp['und'], 'factor' => 1);
			}
			$sug = array();
			if (!$m) {
				foreach ($prods as $p) {
					$sc = facturaParecido($t, $p['n']);
					if ($sc > 0) { $sug[] = array('idproducto' => (int)$p['idproducto'], 'descripcion' => $p['descripcion'], 'und' => $p['und'], 'pct' => (int)round($sc * 100)); }
				}
				usort($sug, function ($a, $b) { return $b['pct'] - $a['pct']; });
				$sug = array_slice($sug, 0, 3);
			}
			$lineas[$i]['texto'] = $t;
			$lineas[$i]['match'] = $m;
			$lineas[$i]['sugerencias'] = $sug;
		}

		$cfgR = filas($bd, "SELECT recupera_igv FROM compras_config WHERE idsede = ?", array($idsede));
		$datos = array('factura' => $factura, 'resumen' => $resumen, 'lineas' => $lineas);
		ejecutar($bd, "INSERT INTO compra_documento (idsede, mime, bytes, sha256, proveedor_ia, modelo_ia, datos_json, avisos_json, segundos, idusuario, fecha)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
			array($idsede, $mime, (int)$f['size'], $sha, $cfg['proveedor'], mb_substr($cfg['modelo'], 0, 80, 'UTF-8'),
				json_encode($datos, JSON_UNESCAPED_UNICODE), json_encode($avisos, JSON_UNESCAPED_UNICODE), $seg, $us));
		$iddoc = (int)$bd->bd->insert_id;

		return array(
			'iddocumento' => $iddoc,
			'comprobante' => $cp,
			'emisor' => $factura['emisor'],
			'proveedor' => $prov,
			'lineas' => $lineas,
			'resumen' => $resumen,
			'avisos' => $avisos,
			'confianza' => $factura['confianza'],
			'observaciones' => $factura['observaciones'],
			'recupera_igv' => $cfgR ? (int)$cfgR[0]['recupera_igv'] : 0,
			'segundos' => $seg
		);
	}

	// Revierte stock, precios e historial de una compra. Todo o nada.
	function anularCompra($bd, $id, $idsede, $us) {
		$c = compraDeSede($bd, $id, $idsede);
		if ((int)$c['estado'] !== 0) { throw new ErrorUsuario('La compra #' . $id . ' ya esta anulada'); }
		$idalm = (int)$c['idalmacen'];
		$items = filas($bd, "SELECT idproducto, (cantidad + 0) AS cantidad FROM compra_items WHERE idcompra = ? AND IFNULL(estado, 0) = 0", array($id));

		$bd->bd->begin_transaction();
		try {
			// el WHERE estado = 0 evita la doble anulacion si dos personas anulan a la vez
			if (ejecutar($bd, "UPDATE compra SET estado = 1, idusuario_anula = ?, fecha_anula = NOW() WHERE idcompra = ? AND idsede = ? AND estado = 0",
				array($us, $id, $idsede)) !== 1) {
				throw new ErrorUsuario('La compra #' . $id . ' ya esta anulada');
			}
			foreach ($items as $it) {
				$idp = (int)$it['idproducto'];
				ejecutar($bd, "UPDATE producto_stock SET stock = stock - ? WHERE idproducto = ? AND idalmacen = ?",
					array((float)$it['cantidad'], $idp, $idalm));
				ejecutar($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
					SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock
					FROM producto_stock ps WHERE ps.idproducto = ? AND ps.idalmacen = ?",
					array('ANULA COMPRA #' . $id, numTxt($it['cantidad'], 4), $us, $idsede, $idp, $idalm, $idp, $idalm));
			}
			// precios de esta compra fuera; el producto vuelve a su ultima compra vigente (si no hay otra, se deja igual)
			ejecutar($bd, "UPDATE producto_historial_precio SET estado = 1 WHERE idcompra = ?", array($id));
			foreach ($items as $it) {
				$idp = (int)$it['idproducto'];
				$prev = filas($bd, "SELECT precio FROM producto_historial_precio WHERE idproducto = ? AND estado = 0
					ORDER BY idproducto_historial_precio DESC LIMIT 1", array($idp));
				if ($prev) { ejecutar($bd, "UPDATE producto SET precio_unitario = ? WHERE idproducto = ?", array($prev[0]['precio'], $idp)); }
			}
			ejecutar($bd, "UPDATE kardex SET estado = 1 WHERE idoperacion = ? AND idsede = ? AND idalmacen = ?", array('C' . $id, $idsede, $idalm));
			$bd->bd->commit();
		} catch (Exception $e) {
			$bd->bd->rollback();
			throw $e;
		}
	}

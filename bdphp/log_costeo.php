<?php
	// Modulo Costeo de recetas: costo vivo por plato y rendimiento de porciones.
	// Plan: plan/COSTEO-RECETAS-PLAN.md. Las formulas viven en las vistas v_costeo_* (migraciones/costeo-recetas/001).
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	require_once __DIR__ . '/costeo_recalculo.php';
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	// Paginas falsas (CSRF): estas pantallas siempre mandan JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode(array('success' => false, 'datos' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_us = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

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
	// El producto_stock debe estar en un almacen de la sede de la sesion
	function insumoDeSede($bd, $idps, $idsede) {
		$r = filas($bd, "SELECT ps.idproducto_stock FROM producto_stock ps JOIN almacen a ON a.idalmacen = ps.idalmacen
			WHERE ps.idproducto_stock = ? AND a.idsede = ?", array($idps, $idsede));
		return count($r) > 0;
	}

	function alertasNuevas($bd, $idsede) {
		return filas($bd, "SELECT a.idcosteo_alerta, a.iditem, i.descripcion, a.fecha, a.costo_antes, a.costo_despues,
				a.precio, a.fc_antes, a.fc_despues, a.causa, a.motivo
			FROM costeo_alerta a JOIN item i ON i.iditem = a.iditem
			WHERE a.idsede = ? AND a.estado = 'nueva'
			ORDER BY a.fecha DESC LIMIT 50", array($idsede));
	}

	if ($g_idsede <= 0) { jsonOut(false, null, 'Sesion sin sede'); }
	// Mismo permiso que "Recetas y costos" (B4): la ruta /rentabilidad_carta no tiene opcion propia en el home
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? $_SESSION['acc'] : '') . ',';
	if (strpos($g_acc, ',B4,') === false) { jsonOut(false, null, 'No tienes permiso de Recetas y costos. Solicitalo al administrador.'); }

	try {
		switch ($op) {
			case 'listar': {
				// Abrir la pantalla recalcula: los costos pueden haber cambiado por la recepcion del ERP
				costeoRecalcularSede($bd, $g_idsede, 'consulta');
				// Solo platos con receta. El costo vivo sale de v_costeo_ingrediente; se agrega aqui filtrando
				// por sede (una vista con GROUP BY calcularia todas las sedes).
				$platos = filas($bd, "SELECT i.iditem, i.descripcion, (i.precio + 0) AS precio,
						(SELECT s.descripcion FROM carta_lista cl JOIN seccion s ON s.idseccion = cl.idseccion
						  WHERE cl.iditem = i.iditem ORDER BY cl.idcarta_lista DESC LIMIT 1) AS seccion,
						COUNT(*) AS n_ing,
						ROUND(SUM(c.costo), 4) AS costo,
						ROUND(SUM(c.costo_snapshot), 4) AS costo_receta,
						SUM(c.costo <= 0 AND c.costo_origen NOT IN ('fijo', 'subreceta_parcial')) AS n_sin_costo,
						SUM(c.costo_origen IN ('fijo', 'subreceta_parcial')) AS n_fijos,
						SUM(c.costo_origen = 'manual') AS n_manual,
						SUM(c.inconsistente) AS n_incons
					FROM item i
					JOIN v_costeo_ingrediente c ON c.iditem = i.iditem
					WHERE i.idsede = ? AND i.estado = 0
					GROUP BY i.iditem", array($g_idsede));
				$sinReceta = filas($bd, "SELECT COUNT(*) AS n FROM item i
					WHERE i.idsede = ? AND i.estado = 0
					  AND NOT EXISTS (SELECT 1 FROM item_ingrediente ii WHERE ii.iditem = i.iditem AND ii.estado = 0)", array($g_idsede));
				// Costo hace 30 dias (ultima foto del historial anterior a esa fecha)
				$hace30 = array();
				foreach (filas($bd, "SELECT h.iditem, h.costo FROM costeo_historial h
					JOIN (SELECT iditem, MAX(idcosteo_historial) AS mx FROM costeo_historial
						  WHERE idsede = ? AND fecha <= NOW() - INTERVAL 30 DAY GROUP BY iditem) u
					  ON u.mx = h.idcosteo_historial", array($g_idsede)) as $h) {
					$hace30[$h['iditem']] = (float)$h['costo'];
				}
				foreach ($platos as $i => $pl) {
					$platos[$i]['costo_30d'] = isset($hace30[$pl['iditem']]) ? $hace30[$pl['iditem']] : null;
				}
				jsonOut(true, array('platos' => $platos, 'sin_receta' => (int)$sinReceta[0]['n'],
					'umbrales' => costeoUmbrales($bd, $g_idsede), 'alertas' => alertasNuevas($bd, $g_idsede)));
			}
			case 'detalle': {
				$b = leerBody();
				$iditem = isset($b['iditem']) ? (int)$b['iditem'] : 0;
				$ings = filas($bd, "SELECT c.*, po.descripcion AS porcion,
						r.idproducto_stock AS rend_idps, r.cantidad_por_porcion AS rend_cantidad, IFNULL(mm.merma_pct, 0) AS merma_pct,
						ci.descripcion AS insumo, ci.factor AS insumo_factor,
						uk.abreviatura AS und_kardex, uc.abreviatura AS und_receta
					FROM v_costeo_ingrediente c
					JOIN item i ON i.iditem = c.iditem AND i.idsede = ?
					LEFT JOIN porcion po ON po.idporcion = c.idporcion
					LEFT JOIN costeo_porcion_rendimiento r ON r.idporcion = c.idporcion AND c.viene_de = '1'
					LEFT JOIN v_costeo_insumo ci ON ci.idproducto_stock = IF(c.viene_de = '1', r.idproducto_stock, c.idproducto_stock)
					LEFT JOIN producto p ON p.idproducto = ci.idproducto
					LEFT JOIN costeo_insumo_merma mm ON mm.idproducto = ci.idproducto
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					LEFT JOIN unidad_medida uc ON uc.idunidad_medida = p.idunidad_conversion
					WHERE c.iditem = ?
					ORDER BY costo DESC", array($g_idsede, $iditem));
				jsonOut(true, $ings);
			}
			case 'insumos': {
				// Productos de los almacenes de la sede, para vincular porciones
				$r = filas($bd, "SELECT ci.idproducto_stock, CONCAT(a.descripcion, ' | ', ci.descripcion) AS label,
						ci.factor, ci.costo_kardex, ci.costo_origen, ci.idproducto, IFNULL(mm.merma_pct, 0) AS merma_pct,
						uk.abreviatura AS und_kardex, IF(p.factor_conversion > 0, uc.abreviatura, NULL) AS und_receta
					FROM v_costeo_insumo ci
					JOIN almacen a ON a.idalmacen = ci.idalmacen AND a.idsede = ? AND a.estado = 0
					JOIN producto p ON p.idproducto = ci.idproducto AND p.estado = 0
					LEFT JOIN costeo_insumo_merma mm ON mm.idproducto = ci.idproducto
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					LEFT JOIN unidad_medida uc ON uc.idunidad_medida = p.idunidad_conversion
					ORDER BY a.descripcion, ci.descripcion", array($g_idsede));
				jsonOut(true, $r);
			}
			case 'rendimiento-guardar': {
				$b = leerBody();
				$idporcion = isset($b['idporcion']) ? (int)$b['idporcion'] : 0;
				$idps = isset($b['idproducto_stock']) ? (int)$b['idproducto_stock'] : 0;
				$cant = isset($b['cantidad_por_porcion']) ? (float)$b['cantidad_por_porcion'] : 0;
				$merma = isset($b['merma_pct']) ? (float)$b['merma_pct'] : 0;
				if ($idporcion <= 0 || $idps <= 0) { jsonOut(false, null, 'Elige la porcion y el insumo'); }
				if ($cant <= 0 || $cant > 1000000) { jsonOut(false, null, 'El peso de la porcion debe ser mayor a 0'); }
				if ($merma < 0 || $merma > 90) { jsonOut(false, null, 'La merma debe estar entre 0 y 90 %'); }
				$po = filas($bd, "SELECT idporcion FROM porcion WHERE idporcion = ? AND idsede = ?", array($idporcion, $g_idsede));
				if (!$po) { jsonOut(false, null, 'Porcion no encontrada en esta sede'); }
				if (!insumoDeSede($bd, $idps, $g_idsede)) { jsonOut(false, null, 'Insumo no encontrado en los almacenes de esta sede'); }
				ejecutar($bd, "INSERT INTO costeo_porcion_rendimiento (idporcion, idproducto_stock, cantidad_por_porcion, idsede, idusuario)
					VALUES (?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE idproducto_stock = VALUES(idproducto_stock),
						cantidad_por_porcion = VALUES(cantidad_por_porcion), idusuario = VALUES(idusuario)",
					array($idporcion, $idps, $cant, $g_idsede, $g_us));
				// La merma es del insumo (producto): aplica a todos sus cortes/porciones
				ejecutar($bd, "INSERT INTO costeo_insumo_merma (idproducto, merma_pct, idsede, idusuario)
					SELECT ps.idproducto, ?, ?, ? FROM producto_stock ps WHERE ps.idproducto_stock = ?
					ON DUPLICATE KEY UPDATE merma_pct = VALUES(merma_pct), idusuario = VALUES(idusuario)",
					array($merma, $g_idsede, $g_us, $idps));
				jsonOut(true, array('idporcion' => $idporcion));
			}
			case 'rendimiento-quitar': {
				$b = leerBody();
				$idporcion = isset($b['idporcion']) ? (int)$b['idporcion'] : 0;
				ejecutar($bd, "DELETE FROM costeo_porcion_rendimiento WHERE idporcion = ? AND idsede = ?", array($idporcion, $g_idsede));
				jsonOut(true, array('idporcion' => $idporcion));
			}
			case 'porciones': {
				// Porciones usadas en recetas de la sede, con su vinculo a insumo (si lo tienen).
				// Primero las que faltan vincular y, entre ellas, las que se usan en mas platos.
				$r = filas($bd, "SELECT po.idporcion, po.descripcion, COUNT(DISTINCT ii.iditem) AS n_platos,
						r.idproducto_stock, r.cantidad_por_porcion,
						ci.descripcion AS insumo, ci.factor, ci.costo_kardex, ci.costo_origen,
						uk.abreviatura AS und_kardex, IF(p.factor_conversion > 0, uc.abreviatura, NULL) AS und_receta,
						vp.merma_pct, ROUND(vp.costo_porcion, 4) AS costo_porcion
					FROM porcion po
					JOIN item_ingrediente ii ON ii.idporcion = po.idporcion AND ii.estado = 0 AND ii.viene_de = '1'
					JOIN item i ON i.iditem = ii.iditem AND i.estado = 0 AND i.idsede = ?
					LEFT JOIN costeo_porcion_rendimiento r ON r.idporcion = po.idporcion
					LEFT JOIN v_costeo_insumo ci ON ci.idproducto_stock = r.idproducto_stock
					LEFT JOIN v_costeo_porcion vp ON vp.idporcion = po.idporcion
					LEFT JOIN producto p ON p.idproducto = ci.idproducto
					LEFT JOIN unidad_medida uk ON uk.idunidad_medida = p.idunidad_kardex
					LEFT JOIN unidad_medida uc ON uc.idunidad_medida = p.idunidad_conversion
					WHERE po.idsede = ? AND po.estado = 0
					GROUP BY po.idporcion
					ORDER BY (r.idporcion IS NOT NULL), n_platos DESC, po.descripcion", array($g_idsede, $g_idsede));
				jsonOut(true, $r);
			}
			case 'simular-base': {
				// Costo actual de cada insumo dentro de cada plato: el simulador aplica el % en el navegador.
				// ponytail: los insumos dentro de subrecetas no se simulan (la subreceta se ve como un solo costo).
				$r = filas($bd, "SELECT c.iditem, c.idproducto_insumo AS idproducto, p.descripcion, ROUND(SUM(c.costo), 4) AS costo
					FROM v_costeo_ingrediente c
					JOIN item i ON i.iditem = c.iditem AND i.idsede = ? AND i.estado = 0
					JOIN producto p ON p.idproducto = c.idproducto_insumo
					WHERE c.costo > 0
					GROUP BY c.iditem, c.idproducto_insumo", array($g_idsede));
				jsonOut(true, $r);
			}
			case 'alertas-count': {
				// Campana del panel: recalcula como maximo 1 vez por hora y cuenta alertas sin ver
				costeoRecalcularSiVencido($bd, $g_idsede, 3600);
				$r = filas($bd, "SELECT COUNT(*) AS n FROM costeo_alerta WHERE idsede = ? AND estado = 'nueva'", array($g_idsede));
				jsonOut(true, array('n' => (int)$r[0]['n']));
			}
			case 'alertas-vistas': {
				ejecutar($bd, "UPDATE costeo_alerta SET estado = 'vista', idusuario_visto = ?, fecha_visto = NOW()
					WHERE idsede = ? AND estado = 'nueva'", array($g_us, $g_idsede));
				jsonOut(true, array());
			}
			case 'config-guardar': {
				$b = leerBody();
				$am = isset($b['umbral_amarillo']) ? (float)$b['umbral_amarillo'] : 0;
				$ro = isset($b['umbral_rojo']) ? (float)$b['umbral_rojo'] : 0;
				if ($am <= 0 || $ro <= $am || $ro > 100) { jsonOut(false, null, 'El umbral rojo debe ser mayor que el amarillo, y ambos entre 1 y 100.'); }
				ejecutar($bd, "INSERT INTO costeo_config (idsede, umbral_amarillo, umbral_rojo) VALUES (?, ?, ?)
					ON DUPLICATE KEY UPDATE umbral_amarillo = VALUES(umbral_amarillo), umbral_rojo = VALUES(umbral_rojo)",
					array($g_idsede, $am, $ro));
				jsonOut(true, costeoUmbrales($bd, $g_idsede));
			}
			default:
				jsonOut(false, null, 'Operacion no valida');
		}
	} catch (Exception $e) {
		error_log('log_costeo.php op=' . $op . ': ' . $e->getMessage());
		jsonOut(false, null, 'Error al procesar la solicitud');
	}

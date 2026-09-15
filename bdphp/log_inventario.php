<?php
	// Modulo Inventario: conteos fisicos vs sistema. Spec: docs/superpowers/specs/2026-09-10-inventario-conteos-design.md
	// La logica que toca stock vive en los SP sp_inventario_* (migracion 028). Aqui solo validacion + consultas.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : (isset($_POST['op']) ? $_POST['op'] : null);
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
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
	// CALL a SP: query directo (los parametros ya vienen validados como int / escapados)
	function llamarSP($bd, $sql) {
		if (!$bd->bd->query($sql)) { throw new Exception($bd->bd->error); }
		while ($bd->bd->more_results() && $bd->bd->next_result()) { }
		// next_result() devuelve false tanto al terminar como al fallar: hay que mirar errno
		if ($bd->bd->errno) { throw new Exception($bd->bd->error); }
	}
	function esAdmin() {
		return (int)(isset($_SESSION['rol']) ? $_SESSION['rol'] : 0) === 1;
	}
	function conteoDeSede($bd, $id, $idsede) {
		$r = filas($bd, "SELECT * FROM inventario_conteo WHERE idinventario_conteo = ? AND idsede = ?", array($id, $idsede));
		if (!$r) { jsonOut(false, null, 'Conteo no encontrado'); }
		return $r[0];
	}
	function mensajeError($e, $op) {
		$m = $e->getMessage();
		$mapa = array(
			'CONTEO_ABIERTO'  => 'Ya existe un conteo abierto (borrador o cerrado) para este almacen. Apruebalo o anulalo primero.',
			'ESTADO_INVALIDO' => 'El conteo no esta en el estado requerido para esta accion.',
			'NO_ENCONTRADO'   => 'Conteo no encontrado.',
			'SIN_MOTIVO'      => 'Falta el motivo AJUSTE INVENTARIO en almacen_motivo_es (migracion 028).',
			'SIN_ALMACEN'     => 'La sede no tiene un almacen activo para registrar el ajuste.',
			// el indice unico gana la carrera que el chequeo del SP no puede cubrir: mismo mensaje
			'ux_conteo_abierto' => 'Ya existe un conteo abierto (borrador o cerrado) para este almacen. Apruebalo o anulalo primero.'
		);
		foreach ($mapa as $clave => $texto) { if (strpos($m, $clave) !== false) { return $texto; } }
		error_log('[inventario] op ' . $op . ': ' . $m);
		return 'Error interno al procesar la operacion.';
	}
	// $tope: maximo que admite el almacenamiento final del stock (producto_stock.stock es
	// DECIMAL(10,5) -> 99999; porcion.stock es DECIMAL(10,2) -> 99999999). Por encima, MySQL
	// guardaria el valor saturado sin avisar (el sql_mode del servidor no es STRICT).
	function numeroONull($v, $tope = 1000000000) {
		if ($v === null || $v === '') { return null; }
		if (!is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $tope) { throw new Exception('CANTIDAD_INVALIDA|' . $tope); }
		return (float)$v;
	}

	try {
		switch ($op) {
			case 'listar': {
				$b = leerBody();
				$estado = (isset($b['estado']) && is_scalar($b['estado'])) ? (string)$b['estado'] : '';
				$limit = isset($b['limit']) ? max(1, min(200, (int)$b['limit'])) : 50;
				$sql = "SELECT c.idinventario_conteo, c.tipo, c.idalmacen, IFNULL(a.descripcion, 'PORCIONES') AS almacen,
						IFNULL(pf.descripcion, 'TODAS') AS familia, c.ciego, c.estado, c.obs,
						c.fecha_crea, uc.nombres AS crea, c.fecha_cierre, c.fecha_aprob, ua.nombres AS aprob,
						(SELECT COUNT(*) FROM inventario_conteo_detalle d WHERE d.idinventario_conteo = c.idinventario_conteo) AS n_items,
						(SELECT COUNT(*) FROM inventario_conteo_detalle d WHERE d.idinventario_conteo = c.idinventario_conteo AND d.contado IS NOT NULL) AS n_contados,
						IF(c.ciego = 1 AND c.estado = 'borrador', NULL,
							(SELECT COUNT(*) FROM inventario_conteo_detalle d WHERE d.idinventario_conteo = c.idinventario_conteo AND d.contado IS NOT NULL AND d.contado <> IFNULL(d.stock_cierre, d.stock_sistema))) AS n_dif
					FROM inventario_conteo c
					LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
					LEFT JOIN producto_familia pf ON pf.idproducto_familia = c.idproducto_familia
					LEFT JOIN usuario uc ON uc.idusuario = c.idusuario_crea
					LEFT JOIN usuario ua ON ua.idusuario = c.idusuario_aprob
					WHERE c.idsede = ? " . ($estado !== '' ? "AND c.estado = ? " : "") . "
					ORDER BY c.idinventario_conteo DESC LIMIT " . $limit;
				$params = $estado !== '' ? array($g_idsede, (string)$estado) : array($g_idsede);
				jsonOut(true, filas($bd, $sql, $params));
			}
			break;
			case 'crear': {
				$b = leerBody();
				// negativo = porciones, igual que el SP (GREATEST(IFNULL(p_idalmacen,0),0)): sin esto un -1
				// pasaria el IF(p_idalmacen > 0) como porciones pero con otra clave en abierto_key
				$idalmacen = isset($b['idalmacen']) ? max(0, (int)$b['idalmacen']) : 0;
				// idfamilia es un char(10) del legacy ('f1','f2',...); '' o '0' = todas las familias
				$idfamilia = (isset($b['idfamilia']) && is_scalar($b['idfamilia'])) ? mb_substr(trim((string)$b['idfamilia']), 0, 10) : '';
				if ($idfamilia === '0' || $idalmacen === 0) { $idfamilia = ''; } // '0' = todas; en porciones la familia no aplica
				$ciego = (isset($b['ciego']) && (int)$b['ciego'] === 0) ? 0 : 1;
				$obs = (isset($b['obs']) && is_scalar($b['obs'])) ? mb_substr(trim((string)$b['obs']), 0, 250) : '';
				if ($idalmacen > 0) {
					$ok = filas($bd, "SELECT idalmacen FROM almacen WHERE idalmacen = ? AND idsede = ? AND estado = 0", array($idalmacen, $g_idsede));
					if (!$ok) { jsonOut(false, null, 'Almacen no valido para esta sede'); }
					if ($idfamilia !== '') {
						$okf = filas($bd, "SELECT idproducto_familia FROM producto_familia WHERE idproducto_familia = ? AND idsede = ? AND estado = 0", array($idfamilia, $g_idsede));
						if (!$okf) { jsonOut(false, null, 'Familia no valida para esta sede'); }
					}
				}
				$obsEsc = $bd->bd->real_escape_string($obs);
				$idfamiliaEsc = $bd->bd->real_escape_string($idfamilia);
				// el SP hace 2 INSERT (cabecera + detalle): sin transaccion un fallo del segundo deja
				// una cabecera 'borrador' huerfana que bloquea el almacen con CONTEO_ABIERTO
				$bd->bd->begin_transaction();
				try {
					llamarSP($bd, "CALL sp_inventario_crear($g_ido, $g_idsede, $idalmacen, '$idfamiliaEsc', $ciego, '$obsEsc', $g_us, @id_conteo)");
					$r = filas($bd, "SELECT @id_conteo AS id");
					if (!$bd->bd->commit()) { throw new Exception($bd->bd->error); }
				} catch (Exception $e) { $bd->bd->rollback(); throw $e; }
				jsonOut(true, array('id' => (int)$r[0]['id']));
			}
			break;
			case 'obtener': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				$c = conteoDeSede($bd, $id, $g_idsede);
				$cab = filas($bd, "SELECT c.*, IFNULL(a.descripcion, 'PORCIONES') AS almacen, IFNULL(pf.descripcion, 'TODAS') AS familia,
						uc.nombres AS crea, ux.nombres AS cierre, ua.nombres AS aprob
					FROM inventario_conteo c
					LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
					LEFT JOIN producto_familia pf ON pf.idproducto_familia = c.idproducto_familia
					LEFT JOIN usuario uc ON uc.idusuario = c.idusuario_crea
					LEFT JOIN usuario ux ON ux.idusuario = c.idusuario_cierre
					LEFT JOIN usuario ua ON ua.idusuario = c.idusuario_aprob
					WHERE c.idinventario_conteo = ?", array($id));
				$cabecera = $cab[0];
				$cabecera['es_admin'] = esAdmin() ? 1 : 0;
				$ocultar = ((int)$c['ciego'] === 1 && $c['estado'] === 'borrador');
				$colsStock = $ocultar ? "" : ", d.stock_sistema, d.stock_cierre";
				$det = filas($bd, "SELECT d.idinventario_conteo_detalle, d.idp, d.procede, d.descripcion, d.familia, d.unidad,
						d.contado, d.obs, d.aplicar, d.costo_unit, d.costo_origen, u.nombres AS cuenta, d.fecha_hora" . $colsStock . "
					FROM inventario_conteo_detalle d
					LEFT JOIN usuario u ON u.idusuario = d.idusuario_cuenta
					WHERE d.idinventario_conteo = ?
					ORDER BY d.familia, d.descripcion", array($id));
				jsonOut(true, array('cabecera' => $cabecera, 'detalle' => $det));
			}
			break;
			case 'guardar-detalle': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				$c = conteoDeSede($bd, $id, $g_idsede);
				if ($c['estado'] !== 'borrador') { jsonOut(false, null, 'El conteo ya no esta en borrador; no se puede modificar.'); }
				$filasIn = isset($b['filas']) && is_array($b['filas']) ? array_slice($b['filas'], 0, 200) : array();
				// el tope depende de donde acaba el stock: producto_stock.stock DECIMAL(10,5) vs porcion.stock DECIMAL(10,2)
				$tope = ($c['tipo'] === 'porcion') ? 99999999 : 99999;
				// Paso 1: validar TODO el lote ANTES de escribir. numeroONull lanza CANTIDAD_INVALIDA
				// y no debe dejar a medias las filas anteriores del mismo lote.
				$lote = array();
				foreach ($filasIn as $f) {
					if (!is_array($f)) { continue; }
					$lote[] = array(
						'iddet'   => (isset($f['iddet']) && is_scalar($f['iddet'])) ? (int)$f['iddet'] : 0,
						// sin is_scalar aqui a proposito: un array/objeto es CANTIDAD_INVALIDA, no un borrado a NULL
						'contado' => numeroONull(isset($f['contado']) ? $f['contado'] : null, $tope),
						'obs'     => (isset($f['obs']) && is_scalar($f['obs'])) ? mb_substr(trim((string)$f['obs']), 0, 250) : ''
					);
				}
				// Paso 2: escribir el lote completo en una sola transaccion
				$n = 0;
				$bd->bd->begin_transaction();
				try {
					foreach ($lote as $f) {
						if ($f['contado'] === null) {
							$n += ejecutar($bd, "UPDATE inventario_conteo_detalle SET contado = NULL, obs = ?, idusuario_cuenta = ?, fecha_hora = NOW()
								WHERE idinventario_conteo_detalle = ? AND idinventario_conteo = ?", array($f['obs'], $g_us, $f['iddet'], $id));
						} else {
							$n += ejecutar($bd, "UPDATE inventario_conteo_detalle SET contado = ?, obs = ?, idusuario_cuenta = ?, fecha_hora = NOW()
								WHERE idinventario_conteo_detalle = ? AND idinventario_conteo = ?", array($f['contado'], $f['obs'], $g_us, $f['iddet'], $id));
						}
					}
					if (!$bd->bd->commit()) { throw new Exception($bd->bd->error); }
				} catch (Exception $e) { $bd->bd->rollback(); throw $e; }
				// affected_rows cuenta solo las filas que CAMBIARON: guardadas < filas.length NO es error
				// (la UI de Task 4 no debe tratarlo como fallo)
				jsonOut(true, array('guardadas' => $n));
			}
			break;
			case 'cerrar': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				conteoDeSede($bd, $id, $g_idsede);
				$bd->bd->begin_transaction();
				try {
					llamarSP($bd, "CALL sp_inventario_cerrar($id, $g_idsede, $g_us)");
					if (!$bd->bd->commit()) { throw new Exception($bd->bd->error); }
				} catch (Exception $e) { $bd->bd->rollback(); throw $e; }
				jsonOut(true, array('estado' => 'cerrado'));
			}
			break;
			case 'anular': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				$c = conteoDeSede($bd, $id, $g_idsede);
				if (!in_array($c['estado'], array('borrador', 'cerrado'))) { jsonOut(false, null, 'Solo se anulan conteos en borrador o cerrados.'); }
				ejecutar($bd, "UPDATE inventario_conteo SET estado = 'anulado', fecha_anula = NOW(), idusuario_anula = ? WHERE idinventario_conteo = ?", array($g_us, $id));
				jsonOut(true, array('estado' => 'anulado'));
			}
			break;
			case 'set-aplicar': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				$iddet = isset($b['iddet']) ? (int)$b['iddet'] : 0;
				$aplicar = (isset($b['aplicar']) && (int)$b['aplicar'] === 1) ? 1 : 0;
				$c = conteoDeSede($bd, $id, $g_idsede);
				if ($c['estado'] !== 'cerrado') { jsonOut(false, null, 'Solo se puede marcar "aplicar" en un conteo cerrado.'); }
				ejecutar($bd, "UPDATE inventario_conteo_detalle SET aplicar = ? WHERE idinventario_conteo_detalle = ? AND idinventario_conteo = ?", array($aplicar, $iddet, $id));
				jsonOut(true, array('aplicar' => $aplicar));
			}
			break;
			case 'aprobar': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				if (!esAdmin()) { jsonOut(false, null, 'Solo un administrador puede aprobar un conteo.'); }
				$c = conteoDeSede($bd, $id, $g_idsede);
				if ($c['estado'] === 'aprobado') {
					jsonOut(true, array('estado' => 'aprobado', 'idalmacen_ie_entrada' => $c['idalmacen_ie_entrada'], 'idalmacen_ie_salida' => $c['idalmacen_ie_salida']));
				}
				$bd->bd->begin_transaction();
				try {
					llamarSP($bd, "CALL sp_inventario_aprobar($id, $g_idsede, $g_us)");
					if (!$bd->bd->commit()) { throw new Exception($bd->bd->error); }
				} catch (Exception $e) { $bd->bd->rollback(); throw $e; }
				$c = conteoDeSede($bd, $id, $g_idsede);
				jsonOut(true, array('estado' => $c['estado'], 'idalmacen_ie_entrada' => $c['idalmacen_ie_entrada'], 'idalmacen_ie_salida' => $c['idalmacen_ie_salida']));
			}
			break;
			case 'rep-resultado': {
				$b = leerBody();
				$id = isset($b['id']) ? (int)$b['id'] : 0;
				$c = conteoDeSede($bd, $id, $g_idsede);
				if (!in_array($c['estado'], array('cerrado', 'aprobado'))) { jsonOut(false, null, 'El resultado solo esta disponible para conteos cerrados o aprobados.'); }
				$filasR = filas($bd, "SELECT d.idinventario_conteo_detalle, d.descripcion, d.familia, d.unidad, d.stock_cierre, d.contado,
						(d.contado - d.stock_cierre) AS diferencia, d.costo_unit, d.costo_origen,
						ROUND((d.contado - d.stock_cierre) * d.costo_unit, 2) AS dif_soles, d.aplicar, u.nombres AS cuenta, d.obs
					FROM inventario_conteo_detalle d
					LEFT JOIN usuario u ON u.idusuario = d.idusuario_cuenta
					WHERE d.idinventario_conteo = ?
					ORDER BY ABS(d.contado - d.stock_cierre) * d.costo_unit DESC, d.familia, d.descripcion", array($id));
				$kpi = array('items' => count($filasR), 'contados' => 0, 'con_dif' => 0, 'faltante_soles' => 0, 'sobrante_soles' => 0, 'sin_costo' => 0);
				foreach ($filasR as $f) {
					if ($f['contado'] === null) { continue; }
					$kpi['contados']++;
					if ((float)$f['costo_unit'] <= 0) { $kpi['sin_costo']++; }
					$dif = (float)$f['diferencia'];
					if (abs($dif) > 0.00001) { $kpi['con_dif']++; }
					if ($dif < 0) { $kpi['faltante_soles'] += abs((float)$f['dif_soles']); } else { $kpi['sobrante_soles'] += (float)$f['dif_soles']; }
				}
				$kpi['exactitud_pct'] = $kpi['contados'] > 0 ? round(100 * ($kpi['contados'] - $kpi['con_dif']) / $kpi['contados'], 1) : 0;
				$kpi['neto_soles'] = round($kpi['sobrante_soles'] - $kpi['faltante_soles'], 2);
				$kpi['faltante_soles'] = round($kpi['faltante_soles'], 2);
				$kpi['sobrante_soles'] = round($kpi['sobrante_soles'], 2);
				jsonOut(true, array('kpi' => $kpi, 'filas' => $filasR));
			}
			break;
			case 'rep-stock-valorizado': {
				$b = leerBody();
				$idalmacen = isset($b['idalmacen']) ? (int)$b['idalmacen'] : 0;
				if ($idalmacen > 0) {
					$filasR = filas($bd, "SELECT p.idproducto AS idp, p.descripcion, pf.descripcion AS familia, um.abreviatura AS unidad,
							ps.stock, IFNULL(CAST(p.stock_minimo AS DECIMAL(14,4)), 0) AS stock_minimo,
							CASE WHEN IFNULL(ps.costo_promedio,0) > 0 THEN ps.costo_promedio WHEN IFNULL(c.compra,0) > 0 THEN c.compra ELSE IFNULL(CAST(p.precio AS DECIMAL(14,4)), 0) END AS costo_unit,
							CASE WHEN IFNULL(ps.costo_promedio,0) > 0 THEN 'promedio' WHEN IFNULL(c.compra,0) > 0 THEN 'compra' WHEN IFNULL(CAST(p.precio AS DECIMAL(14,4)), 0) > 0 THEN 'precio' ELSE 'ninguno' END AS costo_origen
						FROM producto p
						JOIN producto_stock ps ON ps.idproducto = p.idproducto AND ps.idalmacen = ? AND ps.estado = 0
						JOIN almacen a ON a.idalmacen = ps.idalmacen AND a.idsede = ?
						LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
						LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex
						LEFT JOIN (SELECT php.idproducto, CAST(php.precio AS DECIMAL(14,4)) AS compra FROM producto_historial_precio php
									JOIN (SELECT idproducto, MAX(idproducto_historial_precio) AS mx FROM producto_historial_precio WHERE estado = 0 GROUP BY idproducto) u ON u.mx = php.idproducto_historial_precio
									WHERE php.estado = 0) c ON c.idproducto = p.idproducto
						WHERE p.idsede = ? AND p.estado = 0
						ORDER BY pf.descripcion, p.descripcion", array($idalmacen, $g_idsede, $g_idsede));
				} else {
					$filasR = filas($bd, "SELECT po.idporcion AS idp, po.descripcion, 'PORCIONES' AS familia, NULL AS unidad, IFNULL(po.stock,0) AS stock, 0 AS stock_minimo,
							ROUND(IFNULL(CAST(po.peso AS DECIMAL(14,4)),0) * IFNULL(NULLIF((SELECT MAX(ps.costo_promedio) FROM producto_stock ps WHERE ps.idproducto = po.idproducto_de AND ps.estado = 0), 0),
								IFNULL((SELECT CAST(pr.precio AS DECIMAL(14,4)) FROM producto pr WHERE pr.idproducto = po.idproducto_de), 0)), 4) AS costo_unit,
							'porcion' AS costo_origen
						FROM porcion po WHERE po.idsede = ? AND po.estado = 0 ORDER BY po.descripcion", array($g_idsede));
				}
				$total = 0; $sinCosto = 0; $porFamilia = array();
				foreach ($filasR as $i => $f) {
					$costo = (float)$f['costo_unit'];
					if ($costo <= 0) { $sinCosto++; $filasR[$i]['costo_origen'] = 'ninguno'; }
					$filasR[$i]['valorizado'] = round((float)$f['stock'] * $costo, 2);
					$filasR[$i]['bajo_minimo'] = ((float)$f['stock_minimo'] > 0 && (float)$f['stock'] <= (float)$f['stock_minimo']) ? 1 : 0;
					$total += $filasR[$i]['valorizado'];
					// subtotales por familia (spec 8.2); los productos sin familia caen en una clave vacia
					$fam = ($f['familia'] === null) ? '' : (string)$f['familia'];
					if (!isset($porFamilia[$fam])) { $porFamilia[$fam] = array('familia' => $fam, 'items' => 0, 'valorizado' => 0); }
					$porFamilia[$fam]['items']++;
					$porFamilia[$fam]['valorizado'] += $filasR[$i]['valorizado'];
				}
				ksort($porFamilia);
				$familias = array();
				foreach ($porFamilia as $fam => $d) { $d['valorizado'] = round($d['valorizado'], 2); $familias[] = $d; }
				jsonOut(true, array('filas' => $filasR, 'total' => round($total, 2), 'sin_costo' => $sinCosto, 'familias' => $familias));
			}
			break;
			// Lista para el autocomplete del kardex. NO se usa log.php op=1805 porque ese agrupa por
			// idproducto y en el legacy el mismo articulo tiene una fila de 'producto' por almacen:
			// salian dos "GASEOSAS Y BEBIDAS | FANTA 500ML" identicos y al elegir el equivocado el
			// kardex quedaba vacio. Aqui cada opcion es un par producto+almacen y trae su idalmacen,
			// asi el front fija el combo de almacen solo.
			case 'rep-items': {
				$prod = filas($bd, "SELECT p.idproducto AS value,
						CONCAT(pf.descripcion, ' | ', p.descripcion, '  ·  ', a.descripcion) AS label,
						0 AS procede, ps.idalmacen
					FROM producto p
					JOIN producto_stock ps ON ps.idproducto = p.idproducto AND ps.estado = 0
					JOIN almacen a ON a.idalmacen = ps.idalmacen AND a.estado = 0 -- mismo filtro que el combo (log.php op=1604)
					JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
					WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0
					ORDER BY pf.descripcion, p.descripcion, a.descripcion", array($g_ido, $g_idsede));
				$porc = filas($bd, "SELECT idporcion AS value, CONCAT('PORCION | ', descripcion) AS label,
						1 AS procede, 0 AS idalmacen
					FROM porcion WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion", array($g_ido, $g_idsede));
				jsonOut(true, array_merge($porc, $prod));
			}
			break;
			case 'rep-kardex': {
				$b = leerBody();
				$idp = isset($b['idp']) ? (int)$b['idp'] : 0;
				$procede = (isset($b['procede']) && (int)$b['procede'] === 1) ? 1 : 0;
				$idalmacen = isset($b['idalmacen']) ? (int)$b['idalmacen'] : 0;
				$desde = (isset($b['desde']) && is_scalar($b['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$b['desde'])) ? (string)$b['desde'] : date('Y-m-d', strtotime('-30 days'));
				$hasta = (isset($b['hasta']) && is_scalar($b['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$b['hasta'])) ? (string)$b['hasta'] : date('Y-m-d');
				if ($idp <= 0) { jsonOut(false, null, 'Selecciona un producto o porcion.'); }
				// ponytail: fecha es char en el legacy con dos formatos ('Y-m-d' y 'Y-m-d H:i:s'); LEFT(fecha,10) cubre ambos.
				// Clave de orden intradia: hora es texto libre ('11:59 pm', '13:07:28', 'POR DIA'), asi que ordenar
				// por la cadena pone 12:09 am encima de 11:59 pm. Se normaliza a TIME probando 12h, luego 24h y,
				// si no, la hora embebida en fecha; lo que no parsea cae a 00:00:00 (ultimo del dia en DESC).
				// El CONCAT con una fecha dummy es obligatorio: en MySQL 5.7 STR_TO_DATE con formato solo-hora devuelve NULL.
				// almacen_ie_detalle.idp es char(10): bindeado como int, MySQL convierte la COLUMNA a numero
				// en cada fila y no puede usar ix_aied_item. Como string si lo usa. En los historiales
				// (producto_historial.idproducto, porcion_historial.idporcion) la columna es int: va int.
				$sqlOrden = "COALESCE(TIME(STR_TO_DATE(CONCAT('2000-01-01 ', h.hora), '%Y-%m-%d %h:%i %p')),
							TIME(STR_TO_DATE(CONCAT('2000-01-01 ', h.hora), '%Y-%m-%d %H:%i:%s')),
							TIME(STR_TO_DATE(h.fecha, '%Y-%m-%d %H:%i:%s'))) AS orden";
				if ($procede === 0) {
					$act = filas($bd, "SELECT ps.stock FROM producto_stock ps
						JOIN almacen a ON a.idalmacen = ps.idalmacen AND a.idsede = ?
						WHERE ps.idproducto = ? AND ps.idalmacen = ? AND ps.estado = 0 LIMIT 1", array($g_idsede, $idp, $idalmacen));
					$mov = filas($bd, "SELECT LEFT(h.fecha,10) AS fecha, h.hora, h.tipo_movimiento AS tipo, CAST(h.cantidad AS DECIMAL(14,4)) AS cantidad,
							u.nombres AS usuario, IF(h.idpedido > 0, CONCAT('pedido ', h.idpedido), '') AS referencia,
							CAST(h.stock_total AS DECIMAL(14,4)) AS stock_total, " . $sqlOrden . "
						FROM producto_historial h LEFT JOIN usuario u ON u.idusuario = h.idusuario
						WHERE h.idproducto = ? AND h.idsede = ? AND (h.idalmacen = ? OR h.idalmacen IS NULL OR h.idalmacen = 0)
						  AND IFNULL(h.estado,'0') <> '1' AND LEFT(h.fecha,10) BETWEEN ? AND ?
						UNION ALL
						SELECT LEFT(ie.fecha_hora,10), ie.hora, CONCAT(IF(ie.tipo='1','ENTRADA ','SALIDA '), IFNULL(m.motivo,'')), ABS(CAST(d.cantidad AS DECIMAL(14,4))),
							u.nombres, CONCAT('almacen_ie ', ie.idalmacen_ie, ' ', IFNULL(ie.motivo,'')),
							NULL, TIME(ie.fecha_hora)
						FROM almacen_ie_detalle d JOIN almacen_ie ie ON ie.idalmacen_ie = d.idalmacen_ie
						LEFT JOIN almacen_motivo_es m ON m.idalmacen_motivo_es = ie.idproducto_motivo_es
						LEFT JOIN usuario u ON u.idusuario = ie.idusuario
						WHERE d.procede = 0 AND d.idp = ? AND ie.idalmacen = ? AND ie.idsede = ? AND LEFT(ie.fecha_hora,10) BETWEEN ? AND ?
						  AND ie.motivo NOT LIKE 'AJUSTE INVENTARIO #%' -- ya estan en producto_historial; evitar doble conteo
						UNION ALL
						-- Las compras NO pasan por producto_historial ni por almacen_ie: el stock lo sube
						-- el registro de compra. Sin esta rama el kardex salia vacio para productos cuyo
						-- unico movimiento era una compra. f_compra es varchar 'dd/mm/YYYY'.
						SELECT DATE_FORMAT(STR_TO_DATE(c.f_compra, '%d/%m/%Y'), '%Y-%m-%d'), '', 'COMPRA',
							CAST(ci.cantidad AS DECIMAL(14,4)), u.nombres, CONCAT('compra ', c.idcompra),
							NULL, TIME('00:00:00')
						FROM compra_items ci JOIN compra c ON c.idcompra = ci.idcompra
						LEFT JOIN usuario u ON u.idusuario = c.idusuario
						WHERE ci.idproducto = ? AND c.idalmacen = ?
						  AND DATE_FORMAT(STR_TO_DATE(c.f_compra, '%d/%m/%Y'), '%Y-%m-%d') BETWEEN ? AND ?
						UNION ALL
						-- Idem las distribuiciones entre almacenes. fecha es char 'dd/mm/YYYY HH:ii:ss'.
						SELECT DATE_FORMAT(STR_TO_DATE(di.fecha, '%d/%m/%Y %H:%i:%s'), '%Y-%m-%d'),
							DATE_FORMAT(STR_TO_DATE(di.fecha, '%d/%m/%Y %H:%i:%s'), '%h:%i %p'),
							IF(di.idalmacen_a = ?, 'INGRESO X DISTRIBUCION', 'SALIDA X DISTRIBUCION'),
							CAST(dd.cantidad AS DECIMAL(14,4)), u.nombres,
							CONCAT('distribuicion ', di.iddistribuicion, ' ', IFNULL(a1.descripcion,''), ' -> ', IFNULL(a2.descripcion,'')),
							NULL, TIME(STR_TO_DATE(di.fecha, '%d/%m/%Y %H:%i:%s'))
						FROM distribuicion_detalle dd JOIN distribuicion di ON di.iddistribuicion = dd.iddistribuicion
						LEFT JOIN usuario u ON u.idusuario = di.idusuario
						LEFT JOIN almacen a1 ON a1.idalmacen = di.idalmacen_de
						LEFT JOIN almacen a2 ON a2.idalmacen = di.idalmacen_a
						WHERE dd.idproducto = ? AND (di.idalmacen_a = ? OR di.idalmacen_de = ?)
						  AND DATE_FORMAT(STR_TO_DATE(di.fecha, '%d/%m/%Y %H:%i:%s'), '%Y-%m-%d') BETWEEN ? AND ?
						ORDER BY fecha DESC, orden DESC", array($idp, $g_idsede, $idalmacen, $desde, $hasta, (string)$idp, $idalmacen, $g_idsede, $desde, $hasta, $idp, $idalmacen, $desde, $hasta, $idalmacen, $idp, $idalmacen, $idalmacen, $desde, $hasta));
				} else {
					$act = filas($bd, "SELECT stock FROM porcion WHERE idporcion = ? AND idsede = ? LIMIT 1", array($idp, $g_idsede));
					$mov = filas($bd, "SELECT LEFT(h.fecha,10) AS fecha, h.hora, h.tipo_movimiento AS tipo, CAST(h.cantidad AS DECIMAL(14,4)) AS cantidad,
							u.nombres AS usuario, IF(h.idpedido > 0, CONCAT('pedido ', h.idpedido), '') AS referencia,
							CAST(h.stock_total AS DECIMAL(14,4)) AS stock_total, " . $sqlOrden . "
						FROM porcion_historial h LEFT JOIN usuario u ON u.idusuario = h.idusuario
						WHERE h.idporcion = ? AND h.idsede = ? AND IFNULL(h.estado,'0') <> '1' AND LEFT(h.fecha,10) BETWEEN ? AND ?
						UNION ALL
						SELECT LEFT(ie.fecha_hora,10), ie.hora, CONCAT(IF(ie.tipo='1','ENTRADA ','SALIDA '), IFNULL(m.motivo,'')), ABS(CAST(d.cantidad AS DECIMAL(14,4))),
							u.nombres, CONCAT('almacen_ie ', ie.idalmacen_ie, ' ', IFNULL(ie.motivo,'')),
							NULL, TIME(ie.fecha_hora)
						FROM almacen_ie_detalle d JOIN almacen_ie ie ON ie.idalmacen_ie = d.idalmacen_ie
						LEFT JOIN almacen_motivo_es m ON m.idalmacen_motivo_es = ie.idproducto_motivo_es
						LEFT JOIN usuario u ON u.idusuario = ie.idusuario
						WHERE d.procede = 1 AND d.idp = ? AND ie.idsede = ? AND LEFT(ie.fecha_hora,10) BETWEEN ? AND ?
						  AND ie.motivo NOT LIKE 'AJUSTE INVENTARIO #%' -- ya estan en porcion_historial; evitar doble conteo
						ORDER BY fecha DESC, orden DESC", array($idp, $g_idsede, $desde, $hasta, (string)$idp, $g_idsede, $desde, $hasta));
				}
				// signo por nombre del movimiento (el legacy guarda cantidades sin signo)
				$entradas = array('aumenta', 'entrada', 'compra', 'recupera', 'venta devolucion', 'prod_entrada', 'recepcion solicitud', 'ajuste inventario +', 'ingreso x distribucion');
				$salidas  = array('venta', 'disminuye', 'salida', 'prod_salida', 'despacho solicitud', 'venta disminuye', 'ajuste inventario -');
				foreach ($mov as $i => $m) {
					$t = strtolower(trim($m['tipo']));
					$signo = 0;
					foreach ($entradas as $e) { if (strpos($t, $e) === 0) { $signo = 1; } }
					foreach ($salidas as $s) { if (strpos($t, $s) === 0 && strpos($t, 'venta devolucion') !== 0) { $signo = -1; } }
					$mov[$i]['signo'] = $signo; // 0 = no se puede inferir (AJUSTE CIERRE, MODIFICACION MONITOR): no afecta el saldo mostrado
				}
				jsonOut(true, array('stock_actual' => $act ? (float)$act[0]['stock'] : 0, 'movimientos' => $mov));
			}
			break;
			case 'rep-tendencia': {
				$b = leerBody();
				$idalmacen = isset($b['idalmacen']) ? (int)$b['idalmacen'] : 0;
				$n = isset($b['n']) ? max(1, min(24, (int)$b['n'])) : 6;
				$ids = filas($bd, "SELECT idinventario_conteo FROM inventario_conteo
					WHERE idsede = ? AND IFNULL(idalmacen, 0) = ? AND estado = 'aprobado'
					ORDER BY fecha_aprob DESC, idinventario_conteo DESC LIMIT " . $n, array($g_idsede, $idalmacen));
				if (!$ids) { jsonOut(true, array('conteos' => array(), 'ranking' => array())); }
				// $lista se arma solo con enteros ya casteados; no entra texto del usuario
				$lista = implode(',', array_map(function ($r) { return (int)$r['idinventario_conteo']; }, $ids));
				$conteos = filas($bd, "SELECT c.idinventario_conteo, c.fecha_aprob,
						SUM(d.contado IS NOT NULL) AS items,
						SUM(d.contado IS NOT NULL AND d.contado <> d.stock_cierre) AS con_dif,
						ROUND(100 * SUM(d.contado IS NOT NULL AND d.contado = d.stock_cierre) / NULLIF(SUM(d.contado IS NOT NULL), 0), 1) AS exactitud_pct,
						ROUND(SUM(IF(d.contado < d.stock_cierre, (d.stock_cierre - d.contado) * d.costo_unit, 0)), 2) AS faltante_soles,
						ROUND(SUM(IF(d.contado > d.stock_cierre, (d.contado - d.stock_cierre) * d.costo_unit, 0)), 2) AS sobrante_soles
					FROM inventario_conteo c JOIN inventario_conteo_detalle d ON d.idinventario_conteo = c.idinventario_conteo
					WHERE c.idinventario_conteo IN ($lista)
					GROUP BY c.idinventario_conteo, c.fecha_aprob ORDER BY c.fecha_aprob ASC");
				$ranking = filas($bd, "SELECT d.idp, MAX(d.descripcion) AS descripcion, MAX(d.familia) AS familia,
						COUNT(*) AS conteos,
						SUM(d.contado < d.stock_cierre) AS veces_faltante,
						SUM(d.contado > d.stock_cierre) AS veces_sobrante,
						ROUND(SUM(d.stock_cierre - d.contado), 2) AS merma_unid,
						ROUND(SUM((d.stock_cierre - d.contado) * d.costo_unit), 2) AS merma_soles,
						ROUND(AVG(d.stock_cierre - d.contado), 2) AS promedio_unid
					FROM inventario_conteo_detalle d
					WHERE d.idinventario_conteo IN ($lista) AND d.contado IS NOT NULL
					GROUP BY d.idp
					HAVING SUM(d.contado <> d.stock_cierre) > 0
					ORDER BY merma_soles DESC, merma_unid DESC
					LIMIT 200");
				jsonOut(true, array('conteos' => $conteos, 'ranking' => $ranking));
			}
			break;
			default:
				jsonOut(false, null, 'Operacion no reconocida');
				break;
		}
	} catch (Exception $e) {
		if (strpos($e->getMessage(), 'CANTIDAD_INVALIDA') !== false) {
			$partes = explode('|', $e->getMessage());
			jsonOut(false, null, 'Cantidad invalida: debe ser un numero entre 0 y ' . (isset($partes[1]) ? $partes[1] : '1000000000') . '.');
		}
		jsonOut(false, null, mensajeError($e, $op));
	}
?>

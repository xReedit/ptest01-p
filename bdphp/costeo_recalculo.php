<?php
	// Costeo de recetas: recalculo de costo por plato, historial y alertas (migracion costeo-recetas/002).
	// Se incluye desde log_costeo.php y log_100.php (al guardar compra / receta). No imprime nada:
	// log_100.php responde a sus propios clientes y no debe ensuciarse la salida.

	function costeoFilas($bd, $sql, $params) {
		$bd->prepare($sql);
		$bd->execute($params);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		return $bd->fetchAll();
	}
	function costeoEjecutar($bd, $sql, $params) {
		$bd->prepare($sql);
		$bd->execute($params);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
	}

	function costeoUmbrales($bd, $idsede) {
		$r = costeoFilas($bd, "SELECT umbral_amarillo, umbral_rojo, ultimo_recalculo FROM costeo_config WHERE idsede = ?", array($idsede));
		return $r ? array('amarillo' => (float)$r[0]['umbral_amarillo'], 'rojo' => (float)$r[0]['umbral_rojo'], 'ultimo' => $r[0]['ultimo_recalculo'])
			: array('amarillo' => 30.0, 'rojo' => 38.0, 'ultimo' => null);
	}

	// Mismo criterio que la pantalla (xCosEstado en x-rentabilidad-carta.html)
	function costeoEstado($costo, $precio, $u) {
		if ($costo <= 0) { return 'incompleto'; }
		$fc = $precio > 0 ? $costo * 100 / $precio : null;
		if ($precio - $costo <= 0 || ($fc !== null && $fc > $u['rojo'])) { return 'rojo'; }
		if ($fc !== null && $fc >= $u['amarillo']) { return 'amarillo'; }
		return 'verde';
	}

	// Ingrediente que mas pesa hoy en el costo del plato: se muestra como causa probable
	function costeoCausa($bd, $iditem) {
		$r = costeoFilas($bd, "SELECT c.descripcion, c.costo, c.costo_kardex_insumo, p.descripcion AS insumo, um.abreviatura AS und
			FROM v_costeo_ingrediente c
			LEFT JOIN producto p ON p.idproducto = c.idproducto_insumo
			LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex
			WHERE c.iditem = ?
			ORDER BY costo DESC LIMIT 1", array($iditem));
		if (!$r) { return ''; }
		$f = $r[0];
		$txt = 'Mayor costo: ' . ($f['insumo'] ? $f['insumo'] : $f['descripcion']) . ' S/ ' . number_format((float)$f['costo'], 2);
		if ($f['costo_kardex_insumo'] !== null) {
			$txt .= ' (insumo a S/ ' . number_format((float)$f['costo_kardex_insumo'], 2) . ($f['und'] ? ' / ' . $f['und'] : '') . ')';
		}
		return mb_substr($txt, 0, 255);
	}

	// Recalcula todos los platos con receta de la sede. Guarda historial solo si cambio costo o precio,
	// y crea alerta cuando un plato pasa a rojo. Devuelve cuantas alertas nuevas genero.
	// ponytail: recalcula la sede completa (~cientos de platos, < 1 s). Si crece, filtrar por insumos comprados.
	function costeoRecalcularSede($bd, $idsede, $motivo) {
		// log_100 op 1 usa multi_query: hay que consumir sus resultados antes de preparar otra consulta
		while ($bd->bd->more_results() && $bd->bd->next_result()) { if ($res = $bd->bd->store_result()) { $res->free(); } }

		$u = costeoUmbrales($bd, $idsede);
		$actual = costeoFilas($bd, "SELECT i.iditem, (i.precio + 0) AS precio, ROUND(SUM(c.costo), 4) AS costo
			FROM item i JOIN v_costeo_ingrediente c ON c.iditem = i.iditem
			WHERE i.idsede = ? AND i.estado = 0
			GROUP BY i.iditem", array($idsede));
		$previo = array();
		foreach (costeoFilas($bd, "SELECT h.iditem, h.costo, h.precio FROM costeo_historial h
			JOIN (SELECT iditem, MAX(idcosteo_historial) AS mx FROM costeo_historial WHERE idsede = ? GROUP BY iditem) u
			  ON u.mx = h.idcosteo_historial", array($idsede)) as $h) {
			$previo[(int)$h['iditem']] = $h;
		}

		$alertas = 0;
		$noRojos = array();
		foreach ($actual as $a) {
			$iditem = (int)$a['iditem'];
			$costo = (float)$a['costo'];
			$precio = (float)$a['precio'];
			if (costeoEstado($costo, $precio, $u) !== 'rojo') { $noRojos[] = $iditem; }
			if ($costo <= 0) { continue; }
			$p = isset($previo[$iditem]) ? $previo[$iditem] : null;
			if ($p && abs($costo - (float)$p['costo']) < 0.005 && abs($precio - (float)$p['precio']) < 0.005) { continue; }

			$fc = $precio > 0 ? round($costo * 100 / $precio, 1) : null;
			costeoEjecutar($bd, "INSERT INTO costeo_historial (idsede, iditem, fecha, costo, precio, food_cost, motivo)
				VALUES (?, ?, NOW(), ?, ?, ?, ?)", array($idsede, $iditem, $costo, $precio, $fc, $motivo));

			// Primera foto del plato: es la linea base, no hay "antes" contra que alertar
			if (!$p) { continue; }
			$antes = costeoEstado((float)$p['costo'], (float)$p['precio'], $u);
			if (costeoEstado($costo, $precio, $u) !== 'rojo' || $antes === 'rojo') { continue; }

			$fcAntes = (float)$p['precio'] > 0 ? round((float)$p['costo'] * 100 / (float)$p['precio'], 1) : null;
			// Una sola alerta abierta por plato: si ya habia una sin ver, se actualiza
			costeoEjecutar($bd, "DELETE FROM costeo_alerta WHERE idsede = ? AND iditem = ? AND estado = 'nueva'", array($idsede, $iditem));
			costeoEjecutar($bd, "INSERT INTO costeo_alerta (idsede, iditem, fecha, costo_antes, costo_despues, precio, fc_antes, fc_despues, causa, motivo)
				VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)",
				array($idsede, $iditem, (float)$p['costo'], $costo, $precio, $fcAntes, $fc, costeoCausa($bd, $iditem), $motivo));
			$alertas++;
		}

		// Plato que volvio a ser rentable (bajo el insumo o subio el precio): su alerta abierta ya no aplica
		if ($noRojos) {
			costeoEjecutar($bd, "UPDATE costeo_alerta SET estado = 'resuelta', fecha_visto = NOW()
				WHERE idsede = ? AND estado = 'nueva' AND iditem IN (" . implode(',', array_map('intval', $noRojos)) . ")", array($idsede));
		}

		costeoEjecutar($bd, "INSERT INTO costeo_config (idsede, ultimo_recalculo) VALUES (?, NOW())
			ON DUPLICATE KEY UPDATE ultimo_recalculo = NOW()", array($idsede));
		return $alertas;
	}

	// Respaldo para costos que cambian fuera del legacy (recepcion del ERP -> costo_promedio)
	function costeoRecalcularSiVencido($bd, $idsede, $segundos) {
		$u = costeoUmbrales($bd, $idsede);
		if ($u['ultimo'] && (time() - strtotime($u['ultimo'])) < $segundos) { return 0; }
		return costeoRecalcularSede($bd, $idsede, 'panel');
	}

	// Llamada desde flujos existentes (log_100.php): nunca debe romper la compra ni la receta
	function costeoRecalcularSeguro($bd, $idsede, $motivo) {
		try {
			return costeoRecalcularSede($bd, (int)$idsede, $motivo);
		} catch (Exception $e) {
			error_log('costeo_recalculo ' . $motivo . ' sede ' . $idsede . ': ' . $e->getMessage());
			return 0;
		}
	}

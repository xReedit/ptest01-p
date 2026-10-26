<?php
	// Opciones (subitems) de varios productos en un solo pedido: el POS las precarga con la carta
	// para que el clic en un producto no espere log.php?op=20051 (internet lento = clic que no responde).
	// Devuelve por iditem la misma estructura que procedure_loadcarta_205_subitem.
	// ponytail: copia del SELECT del SP; si el SP cambia, cambiar aqui tambien.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$ids = array();
	foreach (explode(',', isset($_POST['iditems']) ? (string)$_POST['iditems'] : '') as $v) {
		$n = (int)$v;
		if ($n > 0) { $ids[$n] = $n; }
	}
	$ids = array_slice(array_values($ids), 0, 300);
	if (!$ids) {
		echo json_encode(array('success' => true, 'datos' => array()));
		exit;
	}

	$sql = "SELECT itcd.iditem,
			CAST(CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
				'iditem_subitem_content', ic.iditem_subitem_content,
				'iditem', ic.iditem,
				'des', ic.titulo,
				'subitem_required_select', itcd.subitem_required_select,
				'subitem_cant_select', itcd.subitem_cant_select,
				'subitem_cant_select_ini', itcd.subitem_cant_select,
				'show_cant_item', itcd.show_cant_item,
				'iscontrolable', itcd.controlable,
				'opciones', (
					SELECT CAST(CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
						'iditem_subitem', isub.iditem_subitem,
						'des', isub.descripcion,
						'cantidad', IF(isub.idporcion > 0,
							IFNULL((SELECT p1.stock FROM porcion p1 WHERE p1.idporcion = isub.idporcion), 0),
							IF(isub.idproducto > 0 OR isub.idproducto_stock IS NOT NULL,
								IFNULL(IF(isub.idproducto_stock IS NOT NULL,
									(SELECT ps.stock FROM producto_stock ps WHERE ps.idproducto_stock = isub.idproducto_stock),
									(SELECT ps.stock FROM producto_stock ps WHERE ps.idproducto = isub.idproducto ORDER BY ps.idproducto_stock LIMIT 1)), 0),
								isub.cantidad)),
						'precio', isub.precio,
						'idporcion', isub.idporcion,
						'idproducto', IFNULL(isub.idproducto_stock, isub.idproducto),
						'idsubreceta', isub.idsubreceta,
						'cantidad_porcion', isub.cantidad,
						'descuenta', isub.descuenta
					) ORDER BY isub.descripcion), ']') AS json)
					FROM item_subitem isub
					WHERE isub.iditem_subitem_content = ic.iditem_subitem_content AND isub.estado = 0
				)
			) ORDER BY ic.iditem_subitem_content), ']') AS json) AS subitems
		FROM item_subitem_content ic
			INNER JOIN item_subitem_content_detalle itcd ON ic.iditem_subitem_content = itcd.iditem_subitem_content AND itcd.estado = 0
		WHERE itcd.iditem IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND ic.estado = 0
		GROUP BY itcd.iditem";

	try {
		$bd->bd->query('SET SESSION group_concat_max_len = 100000');
		$bd->prepare($sql);
		$bd->execute($ids);
		echo json_encode(array('success' => true, 'datos' => $bd->fetchAll()));
	} catch (Exception $e) {
		error_log('log_subitems_lote: ' . $e->getMessage());
		echo json_encode(array('success' => false, 'datos' => array(), 'error' => 'No se pudieron cargar las opciones'));
	}

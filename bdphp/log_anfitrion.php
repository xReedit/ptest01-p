<?php
	// Vista de anfitrion (solo lectura): app/page/x-anfitrion.html, ruta /anfitrion, permiso E1 en us_home_opciones.
	// Las mesas ocupadas salen de log.php?op=501 (procedure_refresh_mesas_501, lo mismo que Control de Mesas);
	// aqui solo: 'config' (areas y numero de mesas) y 'detalle' (lo pedido en una mesa abierta).
	// Nada se escribe. Sede y organizacion salen SOLO de la sesion.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : '';
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		http_response_code(415);
		echo json_encode(array('success' => false, 'data' => null, 'error' => 'Solicitud no valida'));
		exit;
	}
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	// Solo se lee la sesion: soltar el candado para no frenar las demas pestanas.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	// Tope de filas del detalle: una mesa normal no pasa de unas decenas de items.
	const ANF_MAX_ITEMS = 300;
	// Sin bandera de cierre (sede que nunca cerro caja) se miran los pedidos de los ultimos dias.
	const ANF_DIAS_SIN_CIERRE = 7;

	function anfOut($ok, $data = null, $error = '') {
		echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error));
		exit;
	}
	function anfBody() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}
	function anfFilas($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error, $bd->stmt->errno); }
		return $bd->fetchAll();
	}

	// Areas de la sede y forma de numerar (igual que x-control-mesas xPintarMesas + log_009.php op=5001:
	// solo las areas del tipo de mesa actual; al cambiar de numerica a alfanumerica las viejas quedan activas)
	function anfConfig($bd, $idsede) {
		$sede = anfFilas($bd, "SELECT mesas, mesas_alfanumerica FROM sede WHERE idsede = ? LIMIT 1", array($idsede));
		$alfa = $sede ? (string)$sede[0]['mesas_alfanumerica'] === '1' : false;
		$filtroTipo = $alfa ? "tipo_mesa = 'alfanumerica'" : "(tipo_mesa IS NULL OR tipo_mesa = '' OR tipo_mesa = 'numerica')";
		$areas = anfFilas($bd,
			"SELECT idarea_mesa, TRIM(titulo) AS titulo, num_mesa_ini AS desde, num_mesa_fin AS hasta, IFNULL(prefijo_mesa, '') AS prefijo
			 FROM area_mesa WHERE idsede = ? AND estado = '0' AND $filtroTipo ORDER BY idarea_mesa", array($idsede));
		return array(
			'mesas' => $sede ? (int)$sede[0]['mesas'] : 0,
			'alfanumerica' => $alfa,
			'areas' => $areas
		);
	}

	/**
	 * Lo pedido y aun no pagado en una mesa. Acotado por sede + bandera de cierre (idsede + idpedido usa el
	 * indice pedido_idsede_IDX, que en InnoDB incluye la PK): no recorre la historia de la tabla pedido.
	 */
	function anfDetalle($bd, $idsede, $nummesa) {
		$corr = anfFilas($bd, "SELECT IFNULL(last_id_pedido_cierre, 0) AS id FROM pedido_correlativos WHERE idsede = ? LIMIT 1", array($idsede));
		$desde = $corr ? (int)$corr[0]['id'] : 0;

		$filtroDesde = $desde > 0 ? "p.idpedido >= ?" : "p.fecha_hora >= DATE_SUB(NOW(), INTERVAL " . ANF_DIAS_SIN_CIERRE . " DAY)";
		// mesas numericas: '01' y '1' son la misma mesa (el procedure 501 compara con CAST)
		$esNumero = ctype_digit($nummesa);
		$filtroMesa = $esNumero ? "CAST(p.nummesa AS UNSIGNED) = ?" : "p.nummesa = ?";

		$sql = "SELECT p.idpedido, p.hora, IFNULL(p.referencia, '') AS referencia,
				IFNULL(LOWER(SUBSTRING_INDEX(u.nombres, ' ', 1)), '') AS mozo,
				pd.descripcion, CAST(pd.cantidad AS DECIMAL(10,2)) AS cantidad,
				CAST(IFNULL(NULLIF(pd.ptotal_r, ''), 0) AS DECIMAL(10,2)) AS importe,
				IF(pd.despachado = 1 OR p.despachado = 1, 1, 0) AS despachado
			FROM pedido p
				INNER JOIN pedido_detalle pd ON pd.idpedido = p.idpedido
				LEFT JOIN usuario u ON u.idusuario = p.idusuario
			WHERE p.idsede = ? AND $filtroDesde AND p.estado = 0 AND $filtroMesa
				AND pd.pagado = 0 AND pd.estado = 0
			ORDER BY p.idpedido, pd.idpedido_detalle
			LIMIT " . ANF_MAX_ITEMS;

		$params = array($idsede);
		if ($desde > 0) { $params[] = $desde; }
		$params[] = $esNumero ? (int)$nummesa : $nummesa;
		$filas = anfFilas($bd, $sql, $params);

		// agrupar por pedido (cada vez que el mozo mando a cocina)
		$pedidos = array();
		foreach ($filas as $f) {
			$id = (int)$f['idpedido'];
			if (!isset($pedidos[$id])) {
				$pedidos[$id] = array('idpedido' => $id, 'hora' => $f['hora'], 'mozo' => $f['mozo'], 'referencia' => $f['referencia'], 'items' => array());
			}
			$pedidos[$id]['items'][] = array(
				'descripcion' => $f['descripcion'],
				'cantidad' => (float)$f['cantidad'],
				'importe' => (float)$f['importe'], // mismo importe que Control de Mesas (ptotal_r)
				'despachado' => (int)$f['despachado'] === 1
			);
		}
		return array('nummesa' => $nummesa, 'pedidos' => array_values($pedidos));
	}

	try {
		if ($g_idsede <= 0) { anfOut(false, null, 'Tu sesion expiro, vuelve a entrar.'); }
		switch ($op) {
			case 'config':
				anfOut(true, anfConfig($bd, $g_idsede));
				break;
			case 'detalle':
				$b = anfBody();
				$nummesa = trim(isset($b['nummesa']) ? (string)$b['nummesa'] : '');
				if ($nummesa === '' || strlen($nummesa) > 50) { anfOut(false, null, 'Mesa no valida.'); }
				anfOut(true, anfDetalle($bd, $g_idsede, $nummesa));
				break;
			default:
				anfOut(false, null, 'Operacion no valida.');
		}
	} catch (Exception $e) {
		error_log('log_anfitrion.php op=' . $op . ' sede=' . $g_idsede . ': ' . $e->getMessage());
		anfOut(false, null, 'No se pudo cargar. Intenta de nuevo.');
	}

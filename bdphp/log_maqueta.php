<?php
	// Maqueta 3D del salon (spec docs/superpowers/specs/2026-10-31-maqueta-salon-3d-design.md §7-§8): el plano de cada
	// area de mesas (tabla plano_area, migracion 083) y el interruptor sede_opciones.maqueta_3d. Lo usan el editor
	// (app/maqueta/) y la vista en vivo de Control de pedidos.
	// Patron de log_vision.php: SecurityGuard (sin sesion → 401 en JSON), sede y usuario SOLO de la sesion PHP (nunca
	// de $_POST/$_GET), session_write_close, consultas preparadas y solo pedidos JSON (paginas falsas).
	// Respuesta: {"success": bool, "data": ..., "error": codigo|null}. Errores de negocio (conflicto, plano_invalido,
	// sin_plano) con HTTP 200 para que el JS los maneje sin excepcion. op desconocido → 400 op_invalida.
	// Ops (?op=): planos (cualquiera con sesion: la ve el cajero), guardar y activar (opcion C1 "Datos del sistema",
	// la misma que hoy configura las areas de mesas) y platos (cualquiera con sesion: cantidad y precio de lo pedido en
	// las mesas ocupadas, para dibujar cuantas personas hay cuando la camara no las cuenta).
	// El area se enlaza por su titulo sin espacios ("BAR VIP" → "BARVIP"), nunca por idarea_mesa: log_009.php
	// re-inserta las areas al guardarlas y el id cambia. '' = sede sin areas.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');

	function maqOut($ok, $data = null, $error = null, $http = 200) {
		http_response_code($http);
		echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error));
		exit;
	}

	// Paginas falsas (CSRF): el editor y la vista siempre mandan JSON; un formulario de otro sitio no puede.
	if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
		maqOut(false, null, 'pedido_invalido', 415);
	}
	$op = isset($_GET['op']) ? (string)$_GET['op'] : '';
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_idus = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);
	$g_idorg = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	// acc es "A1,C1,E2," (a veces con ",," o espacios): con comas a los lados C1 no casa con un C10
	$g_acc = ',' . str_replace(' ', '', isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '') . ',';
	// Solo se lee la sesion: soltar el candado para no frenar las otras pestanas del POS.
	if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

	if ($g_idsede <= 0 || $g_idus <= 0) { maqOut(false, null, 'no_session', 401); }
	// C1 "Datos del sistema" (Configuraciones) o C7 "Diseña tu Salón 3D" (opcion del home para administradores)
	$g_puedeEditar = strpos($g_acc, ',C1,') !== false || strpos($g_acc, ',C7,') !== false;
	if (($op === 'guardar' || $op === 'activar') && !$g_puedeEditar) { maqOut(false, null, 'sin_permiso', 403); }

	const MAQ_MAX_PIEZAS = 500;
	const MAQ_MAX_PEDIDOS = 200;   // op=platos: mesas ocupadas de un area
	const MAQ_TIPOS = array('mesa', 'barra', 'planta', 'biombo', 'pared', 'columna', 'entrada', 'caja', 'sofa', 'jardinera', 'alfombra');
	const MAQ_FORMAS = array('cuadrada', 'cuadrada2', 'redonda', 'larga', 'alta');
	const MAQ_GIROS = array(0, 90, 180, 270);
	const MAQ_TALLAS = array('normal', 'alta');
	const MAQ_CON_LARGO = array('barra', 'biombo', 'pared', 'sofa', 'jardinera', 'alfombra');
	const MAQ_MODELOS = array('sillon', 'clasica', 'bistro', 'tapizada');
	const MAQ_COLORES = array('piso', 'mesas', 'sillas', 'cojin', 'patas', 'paredes');
	// Ajustes por defecto (Global Constraints del plan): op=planos rellena con estos lo que el plano no trae
	const MAQ_AJUSTES_DEFAULT = array(
		'piso' => '#bababa', 'mesas' => '#fbfaf8', 'sillas' => '#f3f1ed', 'cojin' => '#e9e6e0', 'patas' => '#d9d5ce',
		'paredes' => '#fbfbfc', 'modelo' => 'sillon', 'sombraNitida' => 62, 'sombraLargo' => 35, 'muchoTiempoMin' => 90
	);

	/** Entero JSON (no "5" ni 5.5) entre $min y $max. */
	function maqEntero($v, $min, $max) {
		return is_int($v) && $v >= $min && $v <= $max;
	}

	/** Lista JSON ([...]), no objeto ({"a": ...}). */
	function maqEsLista($v) {
		return is_array($v) && ($v === array() || array_keys($v) === range(0, count($v) - 1));
	}

	// Lo que no vale en codigo ni area (los mismos patrones que app/maqueta/js/plano.js): caracteres de control (C0,
	// DEL, C1), < > " ' & \ ` y lo que no esta en el plano basico de Unicode (emojis). El prefijo del area es texto
	// libre (x-areas-mesas-alfanumericas.html): "TERRAZA ALTA-05", "Ñ-01" o "S1.05" son codigos validos.
	// /u: UTF-8 invalido no casa; /D: el $ no deja pasar un "\n" final.
	const MAQ_PROHIBIDOS = '\x00-\x1f\x7f-\x{9f}<>"\'&\\\\`\x{10000}-\x{10ffff}';

	/** Titulo del area sin espacios: hasta 40 caracteres, sin espacios ni MAQ_PROHIBIDOS. */
	function maqAreaValida($a) {
		return is_string($a) && preg_match('/^[^\x20' . MAQ_PROHIBIDOS . ']{0,40}$/uD', $a) === 1;
	}

	/** Codigo de mesa como lo pinta la tarjeta: de 1 a 30 caracteres, espacios incluidos, sin MAQ_PROHIBIDOS. */
	function maqCodigoValido($c) {
		return is_string($c) && preg_match('/^[^' . MAQ_PROHIBIDOS . ']{1,30}$/uD', $c) === 1;
	}

	/**
	 * Clave de la mesa, igual que claveMesa de app/maqueta/js/plano.js: sin espacios, en mayusculas y sin ceros a la
	 * izquierda en la ultima corrida de digitos ("05" → "5", "piso1-05" → "PISO1-5"). Solo ASCII en espacios,
	 * mayusculas y digitos (el JS hace lo mismo) para que las dos den lo mismo con cualquier texto.
	 */
	function maqClaveMesa($codigo) {
		$c = strtr(preg_replace('/[\x09-\x0d\x20]+/', '', (string)$codigo), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
		return preg_replace_callback('/[0-9]+(?=[^0-9]*$)/D', function ($m) {
			return preg_replace('/^0+(?=[0-9])/', '', $m[0]);
		}, $c, 1);
	}

	/** true si dos mesas tienen la misma clave ("5" y "05"): una tarjeta no sabria cual de las dos es. */
	function maqCodigoRepetido(array $piezas) {
		$claves = array();
		foreach ($piezas as $pz) {
			if ($pz['t'] !== 'mesa') { continue; }
			$k = maqClaveMesa($pz['codigo']);
			if (isset($claves[$k])) { return true; }
			$claves[$k] = true;
		}
		return false;
	}

	/**
	 * Una pieza normalizada: solo los campos de su tipo (lo demas se descarta), giro 0 si no viene. x, y = esquina
	 * superior izquierda en cuadros, dentro del area. Mesa: forma y codigo obligatorios. null si algo no vale.
	 */
	function maqValidarPieza($pz, $ancho, $fondo) {
		if (!is_array($pz) || !isset($pz['t']) || !in_array($pz['t'], MAQ_TIPOS, true)) { return null; }
		$giro = isset($pz['giro']) ? $pz['giro'] : 0;
		if (!isset($pz['x'], $pz['y']) || !maqEntero($pz['x'], 0, $ancho - 1) || !maqEntero($pz['y'], 0, $fondo - 1)
			|| !in_array($giro, MAQ_GIROS, true)) { return null; }
		$r = array('t' => $pz['t'], 'x' => $pz['x'], 'y' => $pz['y'], 'giro' => $giro);
		if ($pz['t'] === 'mesa') {
			if (!isset($pz['forma'], $pz['codigo']) || !in_array($pz['forma'], MAQ_FORMAS, true)
				|| !maqCodigoValido($pz['codigo'])) { return null; }
			$r['forma'] = $pz['forma'];
			$r['codigo'] = $pz['codigo'];
			if (isset($pz['sillas'])) {
				if (!maqEntero($pz['sillas'], 0, 12)) { return null; }
				$r['sillas'] = $pz['sillas'];
			}
		}
		if (in_array($pz['t'], MAQ_CON_LARGO, true) && isset($pz['largo'])) {
			if (!maqEntero($pz['largo'], 1, 60)) { return null; }
			$r['largo'] = $pz['largo'];
		}
		if ($pz['t'] === 'planta' && isset($pz['talla'])) {
			if (!in_array($pz['talla'], MAQ_TALLAS, true)) { return null; }
			$r['talla'] = $pz['talla'];
		}
		if ($pz['t'] === 'alfombra') {
			if (isset($pz['fondo'])) {
				if (!maqEntero($pz['fondo'], 1, 60)) { return null; }
				$r['fondo'] = $pz['fondo'];
			}
			if (isset($pz['color'])) {
				if (!is_string($pz['color']) || !preg_match('/^#[0-9a-f]{6}$/D', $pz['color'])) { return null; }
				$r['color'] = $pz['color'];
			}
		}
		return $r;
	}

	/** Ajustes normalizados: solo las llaves conocidas que vengan (las que falten las pone op=planos). null si alguna no vale. */
	function maqValidarAjustes($a) {
		if (!is_array($a)) { return null; }
		$r = array();
		foreach (MAQ_COLORES as $k) {
			if (!isset($a[$k])) { continue; }
			if (!is_string($a[$k]) || !preg_match('/^#[0-9a-f]{6}$/D', $a[$k])) { return null; }
			$r[$k] = $a[$k];
		}
		if (isset($a['modelo'])) {
			if (!in_array($a['modelo'], MAQ_MODELOS, true)) { return null; }
			$r['modelo'] = $a['modelo'];
		}
		$rangos = array('sombraNitida' => array(0, 100), 'sombraLargo' => array(0, 100), 'muchoTiempoMin' => array(15, 600));
		foreach ($rangos as $k => $rango) {
			if (!isset($a[$k])) { continue; }
			if (!maqEntero($a[$k], $rango[0], $rango[1])) { return null; }
			$r[$k] = $a[$k];
		}
		return $r;
	}

	/**
	 * Plano que manda el editor (frontera de confianza: las mismas reglas que validarPlano de app/maqueta/js/plano.js,
	 * incluida la de dos mesas con la misma clave; el editor la ve antes como codigo_repetido, aqui sale plano_invalido).
	 * @return array|null el plano normalizado {area, ancho, fondo, piezas, ajustes, version} o null si algo no vale
	 */
	function maqValidarPlano(array $p) {
		if (!isset($p['area'], $p['ancho'], $p['fondo'], $p['version']) || !maqAreaValida($p['area'])
			|| !maqEntero($p['ancho'], 20, 200) || !maqEntero($p['fondo'], 20, 200) || !maqEntero($p['version'], 0, PHP_INT_MAX)) {
			return null;
		}
		$lista = isset($p['piezas']) ? $p['piezas'] : null;
		if (!maqEsLista($lista) || count($lista) > MAQ_MAX_PIEZAS) { return null; }
		$piezas = array();
		foreach ($lista as $pz) {
			$n = maqValidarPieza($pz, $p['ancho'], $p['fondo']);
			if ($n === null) { return null; }
			$piezas[] = $n;
		}
		if (maqCodigoRepetido($piezas)) { return null; }
		$ajustes = maqValidarAjustes(isset($p['ajustes']) ? $p['ajustes'] : array());
		if ($ajustes === null) { return null; }
		return array('area' => $p['area'], 'ancho' => $p['ancho'], 'fondo' => $p['fondo'], 'piezas' => $piezas,
			'ajustes' => $ajustes, 'version' => $p['version']);
	}

	/** Ejecuta una consulta preparada; lanza Exception si MySQL falla. */
	function maqEjecutar($bd, $sql, array $params) {
		$bd->prepare($sql);
		$bd->execute($params);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
	}

	/** Version guardada del area (0 si el area no tiene plano). */
	function maqVersionActual($bd, $idsede, $area) {
		maqEjecutar($bd, 'SELECT version FROM plano_area WHERE idsede = ? AND area = ?', array($idsede, $area));
		$v = $bd->fetchColumn();
		return $v === null ? 0 : (int)$v;
	}

	/**
	 * Todos los planos de la sede + si la maqueta esta prendida + si este usuario puede armarla.
	 * Los ajustes salen completos: lo que el plano no guardo, con MAQ_AJUSTES_DEFAULT.
	 */
	function maqPlanos($bd, $idsede, $puedeEditar) {
		maqEjecutar($bd, 'SELECT maqueta_3d FROM sede_opciones WHERE idsede = ?', array($idsede));
		$activo = $bd->fetchColumn() === '1';
		maqEjecutar($bd, 'SELECT area, ancho, fondo, piezas, ajustes, version FROM plano_area WHERE idsede = ? ORDER BY area', array($idsede));
		$planos = array();
		foreach ($bd->fetchAll() as $f) {
			$piezas = json_decode($f['piezas'], true);
			$ajustes = json_decode($f['ajustes'], true);
			if (!is_array($piezas) || !is_array($ajustes)) {
				error_log('[log_maqueta] planos sede ' . $idsede . ' area "' . $f['area'] . '": JSON guardado no valido');
				continue;
			}
			$planos[] = array(
				'area' => (string)$f['area'], 'ancho' => (int)$f['ancho'], 'fondo' => (int)$f['fondo'], 'piezas' => $piezas,
				'ajustes' => array_merge(MAQ_AJUSTES_DEFAULT, array_intersect_key($ajustes, MAQ_AJUSTES_DEFAULT)),
				'version' => (int)$f['version']
			);
		}
		return array('activo' => $activo, 'puedeEditar' => $puedeEditar, 'planos' => $planos);
	}

	/**
	 * Guarda un plano ya validado si nadie lo cambio desde que el editor lo cargo (la version enviada es la guardada;
	 * version 0 = el area aun no tiene plano). Cada camino es una sola sentencia atomica: dos PCs que guardan a la
	 * vez no se pisan. Devuelve array('ok' => bool, 'version' => nueva | la actual si hubo conflicto).
	 */
	function maqGuardar($bd, array $plano, $idorg, $idsede, $idus) {
		$piezas = json_encode($plano['piezas']);
		$ajustes = json_encode((object)$plano['ajustes']);
		if ($plano['version'] === 0) {
			// Area nueva: si otra PC la creo antes, la fila ya existe y el UPDATE no cambia nada (0 filas) → conflicto
			maqEjecutar($bd, 'INSERT INTO plano_area (idorg, idsede, area, ancho, fondo, piezas, ajustes, version, idusuario, fecha_modifica)'
				. ' VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE version = version',
				array($idorg, $idsede, $plano['area'], $plano['ancho'], $plano['fondo'], $piezas, $ajustes, $idus));
		} else {
			maqEjecutar($bd, 'UPDATE plano_area SET ancho = ?, fondo = ?, piezas = ?, ajustes = ?, idusuario = ?, fecha_modifica = NOW(),'
				. ' version = version + 1 WHERE idsede = ? AND area = ? AND version = ?',
				array($plano['ancho'], $plano['fondo'], $piezas, $ajustes, $idus, $idsede, $plano['area'], $plano['version']));
		}
		if ($bd->stmt->affected_rows === 1) { return array('ok' => true, 'version' => $plano['version'] + 1); }
		return array('ok' => false, 'version' => maqVersionActual($bd, $idsede, $plano['area']));
	}

	/**
	 * Prende o apaga la maqueta en Control de pedidos. Prender exige al menos un area con plano (false si no hay).
	 * Casi ninguna sede tiene fila en sede_opciones: se crea con hora_cierre_dia '00:00', lo mismo que asumen
	 * log_005/log_010/api.php sin fila (el default de la columna, '0', no es una hora).
	 */
	function maqActivar($bd, $idsede, $activo) {
		if (!$activo) {
			maqEjecutar($bd, "UPDATE sede_opciones SET maqueta_3d = '0' WHERE idsede = ?", array($idsede));
			return true;
		}
		maqEjecutar($bd, 'SELECT 1 FROM plano_area WHERE idsede = ? LIMIT 1', array($idsede));
		if ($bd->fetchColumn() === null) { return false; }
		maqEjecutar($bd, "INSERT INTO sede_opciones (idsede, hora_cierre_dia, maqueta_3d) VALUES (?, '00:00', '1')"
			. " ON DUPLICATE KEY UPDATE maqueta_3d = '1'", array($idsede));
		return true;
	}

	/**
	 * op=platos: por cada idpedido pedido (el data-p de la tarjeta), [[cantidad, precio unitario], ...] de todo lo
	 * pedido en su mesa (sin lo borrado): una mesa puede tener varios pedidos abiertos y la tarjeta trae uno solo.
	 * Solo la sede de la sesion. Tres consultas con indice: el pedido por su llave, su detalle por idpedido y los otros
	 * pedidos abiertos de esas mesas de las ultimas 2 semanas por (idsede, fecha_hora): una mesa abierta mas tiempo es un olvido.
	 */
	function maqPlatos($bd, $idsede, array $ids) {
		if (!$ids) { return new stdClass(); }
		$marcas = implode(',', array_fill(0, count($ids), '?'));
		maqEjecutar($bd, "SELECT idpedido, nummesa FROM pedido WHERE idpedido IN ($marcas) AND idsede = ?", array_merge($ids, array($idsede)));
		$mesaDe = array();
		foreach ($bd->fetchAll() as $f) { $mesaDe[(int)$f['idpedido']] = (string)$f['nummesa']; }
		if (!$mesaDe) { return new stdClass(); }
		$porMesa = array();
		$juntar = function ($filas) use (&$porMesa) {
			foreach ($filas as $f) { $porMesa[(string)$f['nummesa']][(int)$f['idpedido_detalle']] = array((float)$f['cantidad'], (float)$f['punitario']); }
		};
		$ids = array_keys($mesaDe);
		$marcas = implode(',', array_fill(0, count($ids), '?'));
		maqEjecutar($bd, "SELECT d.idpedido_detalle, p.nummesa, d.cantidad, d.punitario FROM pedido p INNER JOIN pedido_detalle d ON d.idpedido = p.idpedido"
			. " WHERE p.idpedido IN ($marcas) AND d.borrado = 0", $ids);
		$juntar($bd->fetchAll());
		$mesas = array_values(array_unique(array_values($mesaDe)));
		$marcas = implode(',', array_fill(0, count($mesas), '?'));
		maqEjecutar($bd, "SELECT d.idpedido_detalle, p.nummesa, d.cantidad, d.punitario FROM pedido p FORCE INDEX (idx_idsede_fecha_hora)"
			. " INNER JOIN pedido_detalle d ON d.idpedido = p.idpedido"
			. " WHERE p.idsede = ? AND p.fecha_hora >= NOW() - INTERVAL 15 DAY AND p.estado = 0 AND p.nummesa IN ($marcas) AND d.borrado = 0",
			array_merge(array($idsede), $mesas));
		$juntar($bd->fetchAll());
		$r = array();
		foreach ($mesaDe as $id => $mesa) { $r[(string)$id] = isset($porMesa[$mesa]) ? array_values($porMesa[$mesa]) : array(); }
		return $r;
	}

	/** Cuerpo JSON del pedido como array (vacio si no es un objeto JSON). */
	function maqCuerpo() {
		$b = json_decode(file_get_contents('php://input'), true);
		return is_array($b) ? $b : array();
	}

	if (!in_array($op, array('planos', 'guardar', 'activar', 'platos'), true)) { maqOut(false, null, 'op_invalida', 400); }
	$cuerpo = maqCuerpo();
	if ($op === 'guardar') {
		$plano = isset($cuerpo['plano']) && is_array($cuerpo['plano']) ? maqValidarPlano($cuerpo['plano']) : null;
		if ($plano === null) { maqOut(false, null, 'plano_invalido'); }
	}
	if ($op === 'activar' && (!isset($cuerpo['activo']) || !is_bool($cuerpo['activo']))) { maqOut(false, null, 'pedido_invalido'); }
	if ($op === 'platos') {
		$pedidos = isset($cuerpo['pedidos']) && is_array($cuerpo['pedidos']) ? $cuerpo['pedidos'] : null;
		if ($pedidos === null || count($pedidos) > MAQ_MAX_PEDIDOS) { maqOut(false, null, 'pedido_invalido'); }
		$pedidos = array_values(array_unique(array_filter($pedidos, function ($v) { return is_int($v) && $v > 0; })));
	}

	include_once __DIR__ . '/ManejoBD.php';
	try {
		$bd = new xManejoBD('restobar');
		switch ($op) {
			case 'planos':
				$data = maqPlanos($bd, $g_idsede, $g_puedeEditar);
				break;
			case 'guardar':
				$r = maqGuardar($bd, $plano, $g_idorg, $g_idsede, $g_idus);
				if (!$r['ok']) { maqOut(false, array('version' => $r['version']), 'conflicto'); }
				$data = array('version' => $r['version']);
				break;
			case 'platos':
				$data = maqPlatos($bd, $g_idsede, $pedidos);
				break;
			default: // activar
				if (!maqActivar($bd, $g_idsede, $cuerpo['activo'])) { maqOut(false, null, 'sin_plano'); }
				$data = null;
		}
	} catch (Exception $e) {
		error_log('[log_maqueta] ' . $op . ' sede ' . $g_idsede . ': ' . $e->getMessage());
		maqOut(false, null, 'error_interno', 500);
	}
	maqOut(true, $data);

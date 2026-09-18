<?php
	// Modulo Encuestas de satisfaccion, fase 1: configuracion (crear, versionar, publicar).
	// Esquema: migracion 031 (enc_*). Plantillas: encuesta_plantillas.php.
	// Mismo patron que log_inventario.php: ops string, JSON in/out, todo filtrado por la sesion.
	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	date_default_timezone_set('America/Lima');
	include "ManejoBD.php";
	require_once __DIR__ . '/encuesta_plantillas.php';
	// Base del link que ve el cliente (app encuesta-restobar + restobar-api /encuesta-publica). Los tokens de enc_canal_sede no dependen de ella:
	// si la app cambia de dominio se cambia aqui y solo hay que reimprimir los QR ya impresos.
	define('ENC_URL_BASE', 'https://encuesta.papaya.com.pe/');
	$bd = new xManejoBD("restobar");

	$op = isset($_GET['op']) ? $_GET['op'] : (isset($_POST['op']) ? $_POST['op'] : null);
	$g_ido = (int)(isset($_SESSION['ido']) ? $_SESSION['ido'] : 0);
	$g_idsede = (int)(isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0);
	$g_us = (int)(isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0);

	$ENC_TIPOS = array('csat', 'nps', 'ces', 'opcion', 'texto');
	$ENC_CANALES = array('kiosko', 'qr_local', 'ticket', 'whatsapp');

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
	function esAdmin() {
		return (int)(isset($_SESSION['rol']) ? $_SESSION['rol'] : 0) === 1;
	}
	// El guard del router (log.php op=-108) corre desde el navegador: el endpoint verifica el permiso
	// por su cuenta. usuario.acc es "A1,A2,A13,..." (a veces con comas dobles o sin coma final).
	function tieneAccesoModulo() {
		$acc = isset($_SESSION['acc']) ? (string)$_SESSION['acc'] : '';
		return (bool)preg_match('/(^|,)\s*(C6|A13)\s*(,|$)/', $acc);
	}
	// Un no-admin no puede afectar lo que corre en otras sedes (editar, versionar o archivar una
	// encuesta publicada fuera de su sede cambia lo que ven esas sedes).
	function exigirSoloMiSede($bd, $idEnc, $idsede) {
		if (esAdmin()) { return; }
		$r = filas($bd, "SELECT COUNT(*) AS n FROM enc_publicacion WHERE idenc_encuesta = ? AND activa = 1 AND idsede <> ?", array((int)$idEnc, $idsede));
		if ((int)$r[0]['n'] > 0) { jsonOut(false, null, 'Esta encuesta corre en otras sedes: solo un administrador puede modificarla.'); }
	}
	// Relee la encuesta con bloqueo DENTRO de la transaccion: lo leido antes puede estar viejo si
	// otra persona guardo (y versiono) en el medio.
	function bloquearVigente($bd, $idEnc) {
		$r = filas($bd, "SELECT estado FROM enc_encuesta WHERE idenc_encuesta = ? FOR UPDATE", array((int)$idEnc));
		if (!$r || $r[0]['estado'] === 'archivada') { throw new Exception('ENC_CAMBIO_CONCURRENTE'); }
		return $r[0]['estado'];
	}
	function texto($b, $clave, $max) {
		return (isset($b[$clave]) && is_scalar($b[$clave])) ? mb_substr(trim((string)$b[$clave]), 0, $max) : '';
	}
	// ManejoBD enlaza un null como blob: los opcionales viajan como '' y el SQL los vuelve NULL con NULLIF
	function ahora() { return date('Y-m-d H:i:s'); }

	function encuestaDeOrg($bd, $id, $ido) {
		$r = filas($bd, "SELECT * FROM enc_encuesta WHERE idenc_encuesta = ? AND idorg = ?", array((int)$id, $ido));
		if (!$r) { jsonOut(false, null, 'Encuesta no encontrada'); }
		return $r[0];
	}
	function contarRespuestas($bd, $id) {
		$r = filas($bd, "SELECT COUNT(*) AS n FROM enc_respuesta WHERE idenc_encuesta = ?", array((int)$id));
		return (int)$r[0]['n'];
	}
	function sedesPermitidas($bd, $ido, $idsede) {
		// un no-admin solo opera sobre su propia sede
		if (esAdmin()) {
			return filas($bd, "SELECT idsede, nombre FROM sede WHERE idorg = ? AND estado = 0 ORDER BY nombre", array($ido));
		}
		return filas($bd, "SELECT idsede, nombre FROM sede WHERE idorg = ? AND idsede = ? AND estado = 0", array($ido, $idsede));
	}
	// estado visible: activa si esta publicada en algun canal; borrador si no. archivada no se toca.
	function recalcularEstado($bd, $id) {
		ejecutar($bd, "UPDATE enc_encuesta e
				SET e.estado = IF(EXISTS(SELECT 1 FROM enc_publicacion p WHERE p.idenc_encuesta = e.idenc_encuesta AND p.activa = 1), 'activa', 'borrador')
			WHERE e.idenc_encuesta = ? AND e.estado <> 'archivada'", array((int)$id));
	}

	// Valida y normaliza el bloque de preguntas. Corta con jsonOut ante el primer error, con un
	// mensaje que dice que pregunta corregir (el numero es el que ve el usuario en el editor).
	function validarPreguntas($lista, $tipos) {
		if (!is_array($lista) || !count($lista)) { jsonOut(false, null, 'La encuesta necesita al menos una pregunta.'); }
		if (count($lista) > 20) { jsonOut(false, null, 'Maximo 20 preguntas por encuesta.'); }
		$out = array();
		foreach (array_values($lista) as $i => $p) {
			$n = $i + 1;
			if (!is_array($p)) { jsonOut(false, null, 'Pregunta ' . $n . ': formato invalido.'); }
			$tipo = (isset($p['tipo']) && is_scalar($p['tipo'])) ? (string)$p['tipo'] : '';
			if (!in_array($tipo, $tipos, true)) { jsonOut(false, null, 'Pregunta ' . $n . ': tipo no valido.'); }
			$txt = texto($p, 'texto', 200);
			if ($txt === '') { jsonOut(false, null, 'Pregunta ' . $n . ': escribe el texto de la pregunta.'); }
			$opciones = '';
			if ($tipo === 'opcion') {
				$ops = (isset($p['opciones']) && is_array($p['opciones'])) ? $p['opciones'] : array();
				$limpias = array();
				foreach ($ops as $o) {
					$o = is_scalar($o) ? mb_substr(trim((string)$o), 0, 60) : '';
					if ($o !== '' && !in_array($o, $limpias, true)) { $limpias[] = $o; }
				}
				if (count($limpias) < 2 || count($limpias) > 10) { jsonOut(false, null, 'Pregunta ' . $n . ': la opcion multiple necesita entre 2 y 10 opciones distintas.'); }
				$opciones = json_encode($limpias, JSON_UNESCAPED_UNICODE);
			}
			$out[] = array('orden' => $n, 'tipo' => $tipo, 'texto' => $txt,
				'obligatorio' => (isset($p['obligatorio']) && (int)$p['obligatorio'] === 1) ? 1 : 0, 'opciones' => $opciones);
		}
		return $out;
	}
	function insertarPreguntas($bd, $idEnc, $preguntas) {
		foreach ($preguntas as $p) {
			ejecutar($bd, "INSERT INTO enc_pregunta (idenc_encuesta, orden, tipo, texto, obligatorio, opciones)
				VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))",
				array((int)$idEnc, (int)$p['orden'], $p['tipo'], $p['texto'], (int)$p['obligatorio'], (string)$p['opciones']));
		}
	}
	// Crea una encuesta v1 (su propia raiz). Llamar dentro de una transaccion.
	function insertarEncuesta($bd, $ido, $us, $nombre, $inicio, $fin, $preguntas) {
		ejecutar($bd, "INSERT INTO enc_encuesta (idorg, idenc_raiz, version, nombre, texto_inicio, texto_fin, estado, idusuario_crea, creado_en)
			VALUES (?, NULL, 1, ?, NULLIF(?, ''), NULLIF(?, ''), 'borrador', ?, ?)",
			array($ido, $nombre, $inicio, $fin, $us, ahora()));
		$id = (int)$bd->bd->insert_id;
		ejecutar($bd, "UPDATE enc_encuesta SET idenc_raiz = idenc_encuesta WHERE idenc_encuesta = ?", array($id));
		insertarPreguntas($bd, $id, $preguntas);
		return $id;
	}
	function transaccion($bd, $fn) {
		$bd->bd->begin_transaction();
		try {
			$r = $fn();
			if (!$bd->bd->commit()) { throw new Exception($bd->bd->error); }
			return $r;
		} catch (Exception $e) { $bd->bd->rollback(); throw $e; }
	}
	function mensajeError($e, $op) {
		$m = $e->getMessage();
		$mapa = array(
			// dos personas publicando a la vez en la misma sede y canal: el indice unico gana la carrera
			'ux_pub_activa' => 'Otra persona acaba de publicar una encuesta en ese canal. Recarga e intenta de nuevo.',
			'ux_enc_version' => 'Otra persona acaba de guardar esta encuesta. Recarga e intenta de nuevo.',
			'ENC_CAMBIO_CONCURRENTE' => 'Otra persona acaba de modificar esta encuesta. Recarga e intenta de nuevo.',
			// con REPEATABLE READ dos publicaciones simultaneas chocan por gap locks antes que por el UNIQUE
			'Deadlock' => 'Otra persona estaba guardando lo mismo en este momento. Intenta de nuevo.'
		);
		foreach ($mapa as $clave => $txt) { if (strpos($m, $clave) !== false) { return $txt; } }
		error_log('[encuesta] op ' . $op . ': ' . $m);
		return 'Error interno al procesar la operacion.';
	}
	function preguntasDe($bd, $id) {
		$ps = filas($bd, "SELECT idenc_pregunta, orden, tipo, texto, obligatorio, opciones FROM enc_pregunta WHERE idenc_encuesta = ? ORDER BY orden", array((int)$id));
		foreach ($ps as &$p) { $p['opciones'] = $p['opciones'] ? json_decode($p['opciones'], true) : array(); }
		unset($p);
		return $ps;
	}

	// Link firmado de la encuesta para el comprobante de una venta (QR impreso, WhatsApp). Lo pide la CAJA al
	// imprimir, asi que NO depende del permiso del modulo (C6/A13): solo de una sesion valida (SecurityGuard) y
	// de que la venta sea de su sede. Sin publicacion en "QR en el comprobante" o sin secreto devuelve url vacia
	// y el comprobante se imprime igual, sin QR. La firma es la misma que valida restobar-api /encuesta-publica.
	if ($op === 'url-venta') {
		try {
			$b = leerBody();
			$idpago = isset($b['idregistro_pago']) ? (int)$b['idregistro_pago'] : 0;
			// ticket = QR impreso en el comprobante, whatsapp = link del mensaje; cada canal tiene su token
			$canalPedido = isset($b['canal']) && $b['canal'] === 'whatsapp' ? 'whatsapp' : 'ticket';
			@include_once __DIR__ . '/../private/encuesta_secrets.php';
			if ($idpago <= 0 || !defined('ENCUESTA_SECRET') || strlen(ENCUESTA_SECRET) < 32) { jsonOut(true, array('url' => '')); }
			$venta = filas($bd, "SELECT idregistro_pago FROM registro_pago WHERE idregistro_pago = ? AND idsede = ?", array($idpago, $g_idsede));
			if (!$venta) { jsonOut(true, array('url' => '')); }
			$canal = filas($bd, "SELECT cs.token FROM enc_canal_sede cs
				JOIN enc_publicacion p ON p.idsede = cs.idsede AND p.canal = cs.canal AND p.activa = 1
				WHERE cs.idsede = ? AND cs.canal = ? LIMIT 1", array($g_idsede, $canalPedido));
			if (!$canal) { jsonOut(true, array('url' => '')); }
			$token = $canal[0]['token'];
			$firma = substr(hash_hmac('sha256', 'venta.' . $token . '.' . $idpago, ENCUESTA_SECRET), 0, 16);
			jsonOut(true, array('url' => ENC_URL_BASE . $token . '?v=' . $idpago . '.' . $firma));
		} catch (Exception $e) {
			error_log('[encuesta] url-venta: ' . $e->getMessage());
			jsonOut(true, array('url' => ''));
		}
	}

	if (!tieneAccesoModulo()) { jsonOut(false, null, 'No tienes permiso para el modulo de encuestas. Solicitalo al administrador.'); }

	try {
		switch ($op) {
			case 'ctx': {
				$n = filas($bd, "SELECT COUNT(*) AS n FROM enc_encuesta WHERE idorg = ?", array($g_ido));
				jsonOut(true, array('idsede' => $g_idsede, 'esAdmin' => esAdmin(),
					'sedes' => sedesPermitidas($bd, $g_ido, $g_idsede), 'n_encuestas' => (int)$n[0]['n']));
			}
			break;
			case 'plantillas': {
				jsonOut(true, encPlantillas());
			}
			break;
			case 'listar': {
				$b = leerBody();
				$archivadas = isset($b['archivadas']) && (int)$b['archivadas'] === 1;
				// una fila por encuesta: solo la ultima version de cada raiz
				$sql = "SELECT e.idenc_encuesta, e.idenc_raiz, e.version, e.nombre, e.estado, e.creado_en, u.nombres AS crea,
						(SELECT COUNT(*) FROM enc_pregunta p WHERE p.idenc_encuesta = e.idenc_encuesta) AS n_preguntas,
						(SELECT COUNT(*) FROM enc_respuesta r JOIN enc_encuesta v ON v.idenc_encuesta = r.idenc_encuesta WHERE v.idenc_raiz = e.idenc_raiz) AS n_respuestas,
						(SELECT AVG(r.csat_prom) FROM enc_respuesta r JOIN enc_encuesta v ON v.idenc_encuesta = r.idenc_encuesta WHERE v.idenc_raiz = e.idenc_raiz) AS csat_prom,
						(SELECT AVG(r.nps_valor) FROM enc_respuesta r JOIN enc_encuesta v ON v.idenc_encuesta = r.idenc_encuesta WHERE v.idenc_raiz = e.idenc_raiz) AS nps_prom,
						(SELECT GROUP_CONCAT(DISTINCT p.canal ORDER BY p.canal) FROM enc_publicacion p WHERE p.idenc_encuesta = e.idenc_encuesta AND p.activa = 1) AS canales,
						(SELECT COUNT(DISTINCT p.idsede) FROM enc_publicacion p WHERE p.idenc_encuesta = e.idenc_encuesta AND p.activa = 1) AS n_sedes
					FROM enc_encuesta e
					LEFT JOIN usuario u ON u.idusuario = e.idusuario_crea
					WHERE e.idorg = ?
					  AND e.version = (SELECT MAX(x.version) FROM enc_encuesta x WHERE x.idenc_raiz = e.idenc_raiz)
					  AND e.estado " . ($archivadas ? "= 'archivada'" : "<> 'archivada'") . "
					ORDER BY e.idenc_encuesta DESC LIMIT 200";
				jsonOut(true, filas($bd, $sql, array($g_ido)));
			}
			break;
			case 'crear': {
				$b = leerBody();
				$clave = texto($b, 'plantilla', 20);
				$tpl = null;
				foreach (encPlantillas() as $t) { if ($t['clave'] === $clave) { $tpl = $t; } }
				if ($clave !== '' && !$tpl) { jsonOut(false, null, 'Plantilla no encontrada'); }
				if ($tpl) {
					$nombre = $tpl['nombre']; $inicio = $tpl['texto_inicio']; $fin = $tpl['texto_fin'];
					$preguntas = validarPreguntas($tpl['preguntas'], $ENC_TIPOS);
				} else {
					// en blanco: nunca vacia, arranca con una pregunta para que la vista previa muestre algo
					$nombre = 'Nueva encuesta'; $inicio = ''; $fin = 'Gracias por tu tiempo.';
					$preguntas = validarPreguntas(array(array('tipo' => 'csat', 'texto' => '¿Cómo fue tu experiencia?', 'obligatorio' => 1)), $ENC_TIPOS);
				}
				$id = transaccion($bd, function () use ($bd, $g_ido, $g_us, $nombre, $inicio, $fin, $preguntas) {
					return insertarEncuesta($bd, $g_ido, $g_us, $nombre, $inicio, $fin, $preguntas);
				});
				jsonOut(true, array('id' => $id));
			}
			break;
			case 'obtener': {
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				$e['preguntas'] = preguntasDe($bd, $e['idenc_encuesta']);
				$e['n_respuestas'] = contarRespuestas($bd, $e['idenc_encuesta']);
				// satisfaccion de la encuesta completa (todas sus versiones): el detalle se ve en indicadores
				$sat = filas($bd, "SELECT COUNT(*) AS n, AVG(r.csat_prom) AS csat_prom, AVG(r.nps_valor) AS nps_prom
						FROM enc_respuesta r JOIN enc_encuesta v ON v.idenc_encuesta = r.idenc_encuesta
						WHERE v.idenc_raiz = ?", array((int)$e['idenc_raiz']));
				$e['n_respuestas_total'] = (int)$sat[0]['n'];
				$e['csat_prom'] = $sat[0]['csat_prom'];
				$e['nps_prom'] = $sat[0]['nps_prom'];
				$pub = filas($bd, "SELECT COUNT(*) AS n FROM enc_publicacion WHERE idenc_encuesta = ? AND activa = 1", array((int)$e['idenc_encuesta']));
				$e['n_publicaciones'] = (int)$pub[0]['n'];
				jsonOut(true, $e);
			}
			break;
			case 'guardar': {
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				if ($e['estado'] === 'archivada') { jsonOut(false, null, 'Esta encuesta esta archivada: duplicala para seguir editando.'); }
				$nombre = texto($b, 'nombre', 120);
				if ($nombre === '') { jsonOut(false, null, 'Ponle un nombre a la encuesta.'); }
				$inicio = texto($b, 'texto_inicio', 300);
				$fin = texto($b, 'texto_fin', 300);
				$preguntas = validarPreguntas(isset($b['preguntas']) ? $b['preguntas'] : null, $ENC_TIPOS);
				$id = (int)$e['idenc_encuesta'];
				exigirSoloMiSede($bd, $id, $g_idsede);

				$res = transaccion($bd, function () use ($bd, $e, $id, $nombre, $inicio, $fin, $preguntas, $g_us) {
					$estado = bloquearVigente($bd, $id);
					// Sin respuestas: se edita en sitio. Con respuestas: version nueva y la anterior se congela,
					// asi los reportes nunca mezclan respuestas a preguntas distintas bajo el mismo id.
					if (contarRespuestas($bd, $id) === 0) {
						ejecutar($bd, "UPDATE enc_encuesta SET nombre = ?, texto_inicio = NULLIF(?, ''), texto_fin = NULLIF(?, '') WHERE idenc_encuesta = ?",
							array($nombre, $inicio, $fin, $id));
						ejecutar($bd, "DELETE FROM enc_pregunta WHERE idenc_encuesta = ?", array($id));
						insertarPreguntas($bd, $id, $preguntas);
						return array('id' => $id, 'versiono' => false, 'version' => (int)$e['version']);
					}
					$raiz = (int)$e['idenc_raiz'];
					$v = filas($bd, "SELECT MAX(version) + 1 AS v FROM enc_encuesta WHERE idenc_raiz = ? FOR UPDATE", array($raiz));
					$version = (int)$v[0]['v'];
					ejecutar($bd, "INSERT INTO enc_encuesta (idorg, idenc_raiz, version, nombre, texto_inicio, texto_fin, estado, idusuario_crea, creado_en)
						VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)",
						array((int)$e['idorg'], $raiz, $version, $nombre, $inicio, $fin, $estado, $g_us, ahora()));
					$nuevo = (int)$bd->bd->insert_id;
					insertarPreguntas($bd, $nuevo, $preguntas);
					// las publicaciones activas pasan a servir la version nueva
					ejecutar($bd, "UPDATE enc_publicacion SET idenc_encuesta = ? WHERE idenc_encuesta = ? AND activa = 1", array($nuevo, $id));
					ejecutar($bd, "UPDATE enc_encuesta SET estado = 'archivada' WHERE idenc_encuesta = ?", array($id));
					return array('id' => $nuevo, 'versiono' => true, 'version' => $version);
				});
				jsonOut(true, $res);
			}
			break;
			case 'duplicar': {
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				$preguntas = validarPreguntas(preguntasDe($bd, $e['idenc_encuesta']), $ENC_TIPOS);
				$nombre = mb_substr('Copia de ' . $e['nombre'], 0, 120);
				$id = transaccion($bd, function () use ($bd, $g_ido, $g_us, $nombre, $e, $preguntas) {
					return insertarEncuesta($bd, $g_ido, $g_us, $nombre, (string)$e['texto_inicio'], (string)$e['texto_fin'], $preguntas);
				});
				jsonOut(true, array('id' => $id));
			}
			break;
			case 'archivar': {
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				$id = (int)$e['idenc_encuesta'];
				exigirSoloMiSede($bd, $id, $g_idsede);
				transaccion($bd, function () use ($bd, $id) {
					bloquearVigente($bd, $id);
					ejecutar($bd, "UPDATE enc_publicacion SET activa = 0 WHERE idenc_encuesta = ? AND activa = 1", array($id));
					ejecutar($bd, "UPDATE enc_encuesta SET estado = 'archivada' WHERE idenc_encuesta = ?", array($id));
				});
				jsonOut(true, array('id' => $id));
			}
			break;
			case 'publicacion': {
				// Donde corre ESTA encuesta y que ocupa cada (sede, canal) permitido, con el link fijo de cada par.
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				$sedes = sedesPermitidas($bd, $g_ido, $g_idsede);
				$ids = array_map(function ($s) { return (int)$s['idsede']; }, $sedes);
				$ocupados = array(); $links = array();
				if ($ids) {
					// $lista se arma solo con enteros ya casteados; no entra texto del usuario
					$lista = implode(',', $ids);
					$ocupados = filas($bd, "SELECT p.idsede, p.canal, p.idenc_encuesta, e.idenc_raiz, e.nombre, e.version
						FROM enc_publicacion p JOIN enc_encuesta e ON e.idenc_encuesta = p.idenc_encuesta
						WHERE p.activa = 1 AND p.idorg = ? AND p.idsede IN ($lista)", array($g_ido));
					// publicaciones anteriores a la migracion 032 no tienen link: se crea al verlas
					foreach ($ocupados as $o) {
						ejecutar($bd, "INSERT IGNORE INTO enc_canal_sede (idsede, canal, token, idorg, creado_en) VALUES (?, ?, ?, ?, ?)",
							array((int)$o['idsede'], $o['canal'], bin2hex(random_bytes(12)), $g_ido, ahora()));
					}
					$links = filas($bd, "SELECT idsede, canal, CONCAT(?, token) AS url FROM enc_canal_sede
						WHERE idorg = ? AND idsede IN ($lista)", array(ENC_URL_BASE, $g_ido));
				}
				$org = filas($bd, "SELECT nombre FROM org WHERE idorg = ?", array($g_ido));
				jsonOut(true, array('idenc_encuesta' => (int)$e['idenc_encuesta'], 'estado' => $e['estado'],
					'sedes' => $sedes, 'ocupados' => $ocupados, 'links' => $links,
					'org' => $org ? $org[0]['nombre'] : ''));
			}
			break;
			case 'publicacion-guardar': {
				// Recibe el estado deseado: la lista completa de (sede, canal) donde debe correr esta encuesta.
				// Lo que falta se enciende (reemplazando a la encuesta que ocupaba el par); lo que sobra se apaga.
				// Solo se tocan las sedes permitidas: un no-admin no puede apagar nada de otra sede.
				$b = leerBody();
				$e = encuestaDeOrg($bd, isset($b['id']) ? $b['id'] : 0, $g_ido);
				if ($e['estado'] === 'archivada') { jsonOut(false, null, 'No se puede publicar una encuesta archivada.'); }
				if (!preguntasDe($bd, $e['idenc_encuesta'])) { jsonOut(false, null, 'La encuesta no tiene preguntas.'); }
				$permitidas = array_map(function ($s) { return (int)$s['idsede']; }, sedesPermitidas($bd, $g_ido, $g_idsede));
				$deseado = array();
				$pares = (isset($b['asignaciones']) && is_array($b['asignaciones'])) ? $b['asignaciones'] : array();
				if (count($pares) > 400) { jsonOut(false, null, 'Demasiadas asignaciones.'); }
				foreach ($pares as $p) {
					$s = (is_array($p) && isset($p['idsede'])) ? (int)$p['idsede'] : 0;
					$c = (is_array($p) && isset($p['canal']) && is_scalar($p['canal'])) ? (string)$p['canal'] : '';
					if (!in_array($c, $ENC_CANALES, true)) { jsonOut(false, null, 'Canal no valido'); }
					if (!in_array($s, $permitidas, true)) { jsonOut(false, null, 'No tienes permiso para publicar en una de las sedes elegidas.'); }
					$deseado[$s . '|' . $c] = array($s, $c);
				}
				$idEnc = (int)$e['idenc_encuesta'];
				$lista = implode(',', $permitidas ? $permitidas : array(0));

				$res = transaccion($bd, function () use ($bd, $idEnc, $deseado, $lista, $g_ido, $g_us) {
					bloquearVigente($bd, $idEnc); // si la archivaron o versionaron mientras tanto, no publicar la vieja
					$actuales = filas($bd, "SELECT idenc_publicacion, idsede, canal FROM enc_publicacion
						WHERE idenc_encuesta = ? AND activa = 1 AND idsede IN ($lista) FOR UPDATE", array($idEnc));
					$tocadas = array($idEnc => true);
					$apagadas = 0; $encendidas = 0;
					foreach ($actuales as $a) {
						if (!isset($deseado[$a['idsede'] . '|' . $a['canal']])) {
							ejecutar($bd, "UPDATE enc_publicacion SET activa = 0 WHERE idenc_publicacion = ?", array((int)$a['idenc_publicacion']));
							$apagadas++;
						} else {
							unset($deseado[$a['idsede'] . '|' . $a['canal']]); // ya corre ahi: nada que hacer
						}
					}
					foreach ($deseado as $par) {
						list($s, $c) = $par;
						// la que ocupaba ese par se apaga primero (libera activa_key) y se recalcula su estado
						$prev = filas($bd, "SELECT idenc_encuesta FROM enc_publicacion WHERE idsede = ? AND canal = ? AND activa = 1 FOR UPDATE", array($s, $c));
						foreach ($prev as $pv) { $tocadas[(int)$pv['idenc_encuesta']] = true; }
						ejecutar($bd, "UPDATE enc_publicacion SET activa = 0 WHERE idsede = ? AND canal = ? AND activa = 1", array($s, $c));
						ejecutar($bd, "INSERT INTO enc_publicacion (idenc_encuesta, idorg, idsede, canal, activa, creado_en, idusuario_crea)
							VALUES (?, ?, ?, ?, 1, ?, ?)", array($idEnc, $g_ido, $s, $c, ahora(), $g_us));
						// link fijo del par: se crea la primera vez y no cambia nunca (los QR impresos siguen sirviendo)
						ejecutar($bd, "INSERT IGNORE INTO enc_canal_sede (idsede, canal, token, idorg, creado_en) VALUES (?, ?, ?, ?, ?)",
							array($s, $c, bin2hex(random_bytes(12)), $g_ido, ahora()));
						$encendidas++;
					}
					foreach (array_keys($tocadas) as $x) { recalcularEstado($bd, $x); }
					return array('encendidas' => $encendidas, 'apagadas' => $apagadas);
				});
				jsonOut(true, $res);
			}
			break;
			default:
				jsonOut(false, null, 'Operacion no reconocida');
				break;
		}
	} catch (Exception $e) {
		jsonOut(false, null, mensajeError($e, $op));
	}
?>

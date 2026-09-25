<?php
	// Marca manual desde el marcador.
	//
	// Para el que no tiene smartphone y para el que se olvido de marcar. Vive
	// aca y no en el panel porque el problema ocurre en la puerta, con el
	// trabajador delante, no en la oficina al final del dia.
	//
	// SEGURIDAD: el boton se muestra solo si el marcador lo tiene habilitado,
	// pero eso no autoriza nada por si solo: cualquiera parado frente a la
	// pantalla podria marcar por otro. Por eso esta pagina EXIGE usuario y clave
	// de un usuario del POS con permiso D8 (Control de Asistencia), y la marca
	// queda auditada con ese idusuario y el motivo.
	//
	// La empresa y la sede salen del usuario que se autentica, nunca del
	// cliente: asi nadie puede marcar en una sede que no es la suya.

	require_once __DIR__ . '/_api.php';
	require_once __DIR__ . '/../bdphp/ManejoBD.php';

	header('Content-Type: text/html;charset=utf-8');
	header('Cache-Control: no-store');
	header('Referrer-Policy: no-referrer');
	header('X-Frame-Options: DENY');

	$bd = new xManejoBD("restobar");

	function filasL($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		$r = $bd->fetchAll();
		return is_array($r) ? $r : array();
	}

	/**
	 * Valida usuario/clave contra el POS local y exige el permiso D10.
	 *
	 * D10 ("Marcar asistencia a mano") es un permiso APARTE de D8 (Control de
	 * Asistencia) a proposito: D8 es configuracion -- padron, horarios,
	 * marcadores -- y el dueno quiere poder delegar el marcaje por otro a su
	 * persona de confianza sin darle acceso a todo eso.
	 *
	 * Nace cerrado y solo los administradores lo traen por defecto: marcar
	 * sustituyendo al trabajador es justamente lo que el D.S. 004-2006-TR
	 * tipifica como infraccion cuando se hace sin control.
	 */
	function autenticar($bd, $usuario, $clave) {
		if ($usuario === '' || $clave === '') { return null; }

		// Consulta preparada: el login viejo del sistema concatena, aca no.
		$r = filasL($bd,
			"SELECT idusuario, idorg, idsede, nombres, acc
			   FROM usuario
			  WHERE usuario = ? AND pass = ? AND estado = 0
			  LIMIT 1", array($usuario, $clave));

		if (!count($r)) { return null; }
		$u = $r[0];

		// El permiso especifico, no basta con ser usuario del POS.
		// Las comas envuelven el codigo para que ',D1,' no matchee ',D10,'.
		if (strpos(',' . $u['acc'] . ',', ',D10,') === false) { return 'sin_permiso'; }
		return $u;
	}

	$paso = 'login';
	$error = '';
	$usuario = isset($_POST['u']) ? trim($_POST['u']) : '';
	$clave = isset($_POST['p']) ? $_POST['p'] : '';
	$us = null;
	$ticket = '';

	date_default_timezone_set('America/Lima');
	$horaAhora = date('H:i');

	try {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Si viene un ticket del paso anterior, se usa ese. La clave solo
			// viaja en el PRIMER envio, nunca entre pasos.
			$tk = asisTicketLeer(isset($_POST['t']) ? $_POST['t'] : '', 'marca_manual');
			$us = $tk ? $tk : autenticar($bd, $usuario, $clave);

			if ($us === null) {
				$error = 'Usuario o clave incorrectos.';
			} else if ($us === 'sin_permiso') {
				$error = 'Ese usuario no tiene permiso para marcar por otro. Habilitalo en Usuarios.';
				$us = null;
			} else {
				$paso = 'elegir';
				$ticket = $tk ? $_POST['t'] : asisTicketCrear($us, 'marca_manual');


				// Ficha local, que la API no puede leer
				$o = filasL($bd, "SELECT nombre, direccion, ruc, telefono FROM org WHERE idorg = ?", array((int)$us['idorg']));
				$s = filasL($bd, "SELECT nombre, ciudad, direccion, telefono FROM sede WHERE idsede = ?", array((int)$us['idsede']));
				$ficha = array(
					'org'  => count($o) ? $o[0] : array('nombre' => ''),
					'sede' => array(
						'nombre'       => count($s) ? $s[0]['nombre'] : '',
						'ciudad'       => count($s) ? $s[0]['ciudad'] : '',
						'direccion'    => count($s) ? $s[0]['direccion'] : '',
						'telefono'     => count($s) ? $s[0]['telefono'] : '',
						'ruc'          => count($o) ? $o[0]['ruc'] : '',
						'razon_social' => count($o) ? $o[0]['nombre'] : ''
					)
				);

				// Token de panel firmado con los datos del usuario autenticado
				$ahora = time();
				$token = \Firebase\JWT\JWT::encode(array(
					'ido' => (int)$us['idorg'], 'idsede' => (int)$us['idsede'],
					'idusuario' => (int)$us['idusuario'], 'iat' => $ahora, 'exp' => $ahora + 120
				), ASISTENCIA_API_SECRET, 'HS256');

				$llamar = function ($ruta, $cuerpo) use ($token) {
					$ch = curl_init(ASISTENCIA_API_URL . '/asistencia' . $ruta);
					curl_setopt_array($ch, array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_POST => true,
						CURLOPT_POSTFIELDS => json_encode($cuerpo),
						CURLOPT_TIMEOUT => ASISTENCIA_API_TIMEOUT,
						CURLOPT_CONNECTTIMEOUT => 5,
						CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $token)
					));
					$resp = curl_exec($ch); curl_close($ch);
					$j = json_decode($resp, true);
					return (is_array($j) && !empty($j['success'])) ? $j['datos'] : null;
				};

				// Si ya vino con todo, se registra y se muestra el resultado
				if (isset($_POST['idcolaborador'])) {
					$d = $llamar('/marca/manual', array_merge($ficha, array(
						'idcolaborador' => (int)$_POST['idcolaborador'],
						'tipo'          => isset($_POST['tipo']) ? $_POST['tipo'] : '',
						'hora'          => isset($_POST['hora']) ? $_POST['hora'] : '',
						'fecha'         => isset($_POST['fecha']) ? $_POST['fecha'] : '',
						'motivo'        => isset($_POST['motivo']) ? $_POST['motivo'] : ''
					)));
					if ($d) {
						$paso = 'listo';
						$hecho = $d;
					} else {
						$error = 'No se pudo registrar la marca. Revisa la hora y el motivo.';
					}
				}

				if ($paso === 'elegir') {
					$dia = $llamar('/dia', $ficha);
					if (!$dia) { $error = 'No se pudo leer el personal de la sede.'; $paso = 'login'; }
				}
			}
		}
	} catch (Exception $e) {
		$error = 'Error del servidor: ' . $e->getMessage();
		$paso = 'login';
	}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Marcar a mano</title>
<style>
	:root { color-scheme: light; }
	* { box-sizing: border-box; }
	body {
		margin: 0;
		padding: max(20px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom));
		min-height: 100vh; display: flex; align-items: flex-start; justify-content: center;
		background: #f4f6f8; color: #212529;
		font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
	}
	.tarjeta {
		width: 100%; max-width: 520px; margin-top: 4vh;
		background: #fff; border: 1px solid #edf0f2; border-radius: 14px; overflow: hidden;
		box-shadow: 0 1px 2px rgba(16,24,40,.04);
	}
	.cab { padding: 16px 20px; background: lavenderblush; }
	.cab h1 { margin: 0; font-size: 17px; font-weight: 600; color: #424242; }
	.cab p { margin: 4px 0 0; font-size: 12px; color: #6c757d; }
	.cuerpo { padding: 18px 20px; }

	label { display: block; margin-bottom: 4px; font-size: 11px; font-weight: 600; color: #6c757d; text-transform: uppercase; }
	input, select, textarea {
		width: 100%; padding: 10px 12px; margin-bottom: 14px;
		border: 1px solid #ced4da; border-radius: 8px; font-size: 15px; font-family: inherit;
	}
	.fila { display: flex; gap: 10px; }
	.fila > div { flex: 1 1 0; }

	button {
		width: 100%; padding: 13px; border: 0; border-radius: 10px;
		background: #28a745; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer;
	}
	button:active { background: #1e7e34; }
	.volver { display: block; margin-top: 14px; text-align: center; font-size: 13px; color: #6c757d; }

	.error { margin: 0 0 14px; padding: 10px 12px; background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px; }
	.nota { margin: 12px 0 0; font-size: 12px; color: #6c757d; }
	.ok { text-align: center; padding: 10px 0; }
	.ok .tic { font-size: 54px; color: #28a745; line-height: 1; }
	.ok .quien { font-size: 19px; font-weight: 600; margin-top: 10px; }
</style>
</head>
<body>
<div class="tarjeta">
	<div class="cab">
		<h1>Marcar a mano</h1>
		<p>Para quien no tiene celular activado o se olvido de marcar. Queda registrada como manual, con tu usuario y el motivo.</p>
	</div>
	<div class="cuerpo">

<?php if ($error !== '') { ?>
	<p class="error"><?php echo asisEsc($error); ?></p>
<?php } ?>

<?php if ($paso === 'login') { ?>
	<form method="post">
		<label for="u">Usuario del sistema</label>
		<input type="text" id="u" name="u" autocomplete="off" autocapitalize="none" required value="<?php echo asisEsc($usuario); ?>">
		<label for="p">Clave</label>
		<input type="password" id="p" name="p" autocomplete="off" required>
		<button type="submit">Continuar</button>
		<p class="nota">Hace falta un usuario con permiso de <b>Marcar asistencia a mano</b>.</p>
		<a class="volver" href="kiosko.html">Volver a la pantalla</a>
	</form>

<?php } else if ($paso === 'listo') { ?>
	<div class="ok">
		<div class="tic">&#10004;</div>
		<div class="quien"><?php echo asisEsc($hecho['accion'] === 'CORREGIDA' ? 'Marca corregida' : 'Marca registrada'); ?></div>
		<p><?php echo asisEsc($hecho['hora']); ?></p>
	</div>
	<a class="volver" href="manual.php">Marcar a otra persona</a>
	<a class="volver" href="kiosko.html">Volver a la pantalla</a>

<?php } else { ?>
	<form method="post">
		<!-- La sesion no persiste entre pasos a proposito: cada marca se
		     autentica por separado, asi nadie queda "logueado" en la tablet -->
		<!-- El ticket reemplaza a usuario y clave: vence en minutos y solo
		     sirve para terminar este tramite. -->
		<input type="hidden" name="t" value="<?php echo asisEsc($ticket); ?>">
		<input type="hidden" name="fecha" value="<?php echo asisEsc($dia['fecha']); ?>">

		<label for="c">Persona</label>
		<select id="c" name="idcolaborador" required>
			<option value="">Elegir...</option>
<?php foreach ($dia['filas'] as $f) {
		$det = array();
		if ($f['entrada']) { $det[] = 'entrada ' . $f['entrada']['hora']; }
		if ($f['salida'])  { $det[] = 'salida ' . $f['salida']['hora']; }
?>
			<option value="<?php echo (int)$f['idcolaborador']; ?>">
				<?php echo asisEsc($f['nombres']); ?><?php echo count($det) ? ' (' . asisEsc(implode(', ', $det)) . ')' : ''; ?>
			</option>
<?php } ?>
		</select>

		<div class="fila">
			<div>
				<label for="t">Que marca</label>
				<select id="t" name="tipo" required>
					<option value="ENTRADA">Entrada</option>
					<option value="SALIDA">Salida</option>
				</select>
			</div>
			<div>
				<label for="h">Hora</label>
				<input type="time" id="h" name="hora" value="<?php echo asisEsc($horaAhora); ?>" required>
			</div>
		</div>

		<label for="m">Motivo</label>
		<input type="text" id="m" name="motivo" maxlength="200" required
			placeholder="Ej: no tiene celular, se olvido de marcar">

		<button type="submit">Registrar marca</button>
		<p class="nota">Dia <?php echo asisEsc($dia['fecha']); ?>. Si ya existe una marca de ese tipo, se corrige.</p>
		<a class="volver" href="kiosko.html">Volver a la pantalla</a>
	</form>
<?php } ?>

	</div>
</div>
</body>
</html>

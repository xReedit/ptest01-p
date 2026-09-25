<?php
	// Habilitar el dia desde el marcador.
	//
	// Es el momento en que el problema realmente ocurre: son las 11 de la
	// manana de un lunes que el local no abre, es el Dia del Pollo a la Brasa,
	// el personal esta en la puerta y el marcador les dice que no. El
	// administrador no va a ir hasta la computadora del POS a configurar un
	// calendario: lo resuelve aqui, con su clave, en treinta segundos.
	//
	// SEGURIDAD: exige usuario y clave de un usuario del POS con permiso D8
	// (Control de Asistencia). La empresa y la sede salen de ese usuario, nunca
	// del cliente, y todo queda en la bitacora con su nombre.
	//
	// SIMPLIFICACION DELIBERADA: la compensacion se elige UNA VEZ para todos los
	// que vienen, no persona por persona. En la practica vienen todos por el
	// mismo motivo y con el mismo acuerdo; preguntarlo N veces convertiria un
	// tramite de treinta segundos en uno de cinco minutos. Si despues hay que
	// dejar a alguien distinto, se corrige desde el calendario del POS.

	require_once __DIR__ . '/_api.php';
	require_once __DIR__ . '/../bdphp/ManejoBD.php';

	header('Content-Type: text/html;charset=utf-8');
	header('Cache-Control: no-store');
	header('Referrer-Policy: no-referrer');
	header('X-Frame-Options: DENY');

	$bd = new xManejoBD("restobar");

	function filasH($bd, $sql, $params = array()) {
		$bd->prepare($sql);
		$bd->execute($params ? $params : null);
		if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
		$r = $bd->fetchAll();
		return is_array($r) ? $r : array();
	}

	/**
	 * Valida usuario/clave contra el POS local y exige D8.
	 *
	 * Se pide D8 (Control de Asistencia) y no D10 (marcar a mano) porque esto
	 * NO es marcar por otro: es cambiar la configuracion del dia. Son dos
	 * permisos distintos justamente para poder delegar uno sin el otro.
	 */
	function autenticarH($bd, $usuario, $clave) {
		if ($usuario === '' || $clave === '') { return null; }

		$r = filasH($bd,
			"SELECT idusuario, idorg, idsede, nombres, acc
			   FROM usuario
			  WHERE usuario = ? AND pass = ? AND estado = 0
			  LIMIT 1", array($usuario, $clave));

		if (!count($r)) { return null; }
		$u = $r[0];

		// Las comas envuelven el codigo para que ',D1,' no matchee ',D10,'
		if (strpos(',' . $u['acc'] . ',', ',D8,') === false) { return 'sin_permiso'; }
		return $u;
	}

	$paso = 'login';
	$error = '';
	$usuario = isset($_POST['u']) ? trim($_POST['u']) : '';
	$clave = isset($_POST['p']) ? $_POST['p'] : '';
	$dia = null;
	$ticket = '';
	$hechos = 0;

	date_default_timezone_set('America/Lima');

	try {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Si viene un ticket del paso anterior, se usa ese. La clave solo
			// viaja en el PRIMER envio, nunca entre pasos.
			$tk = asisTicketLeer(isset($_POST['t']) ? $_POST['t'] : '', 'habilitar_dia');
			$us = $tk ? $tk : autenticarH($bd, $usuario, $clave);

			if ($us === null) {
				$error = 'Usuario o clave incorrectos.';
			} else if ($us === 'sin_permiso') {
				$error = 'Ese usuario no tiene acceso al Control de Asistencia.';
			} else {
				$paso = 'elegir';
				$ticket = $tk ? $_POST['t'] : asisTicketCrear($us, 'habilitar_dia');


				// Ficha local, que la API no puede leer
				$o = filasH($bd, "SELECT nombre, direccion, ruc, telefono FROM org WHERE idorg = ?", array((int)$us['idorg']));
				$s = filasH($bd, "SELECT nombre, ciudad, direccion, telefono FROM sede WHERE idsede = ?", array((int)$us['idsede']));
				$ficha = array(
					'org'  => count($o) ? $o[0] : array('nombre' => ''),
					'sede' => array(
						'nombre'       => count($s) ? $s[0]['nombre'] : '',
						'ciudad'       => count($s) ? $s[0]['ciudad'] : '',
						'direccion'    => count($s) ? $s[0]['direccion'] : '',
						'telefono'     => count($s) ? $s[0]['telefono'] : '',
						'ruc'          => count($o) ? $o[0]['ruc'] : '',
						'razon_social' => count($o) ? $o[0]['nombre'] : ''
					),
					'usuario' => array('idusuario' => (int)$us['idusuario'], 'nombre' => $us['nombres'])
				);

				$ahora = time();
				$token = \Firebase\JWT\JWT::encode(array(
					'ido' => (int)$us['idorg'], 'idsede' => (int)$us['idsede'],
					'idusuario' => (int)$us['idusuario'], 'iat' => $ahora, 'exp' => $ahora + 180
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

				$fecha = date('Y-m-d');

				// --- confirmacion: se escriben las excepciones ---
				if (isset($_POST['confirmar'])) {
					$motivo = trim(isset($_POST['motivo']) ? $_POST['motivo'] : '');
					$quienes = isset($_POST['q']) && is_array($_POST['q']) ? $_POST['q'] : array();
					$comp = isset($_POST['comp']) ? $_POST['comp'] : '';
					$sustituto = isset($_POST['sustituto']) ? trim($_POST['sustituto']) : '';

					if ($motivo === '') {
						$error = 'Escribe por que se abre hoy. Es lo que explica el cambio despues.';
					} else if (!count($quienes)) {
						$error = 'Marca al menos a una persona.';
					} else if ($comp === 'SUSTITUTORIO' && $sustituto === '') {
						$error = 'Elige que dia descansan a cambio.';
					} else {
						// Primero el local: sin esto, habilitar a una persona en
						// un dia cerrado no alcanza, la regla de sede le gana.
						$llamar('/calendario/excepcion', array_merge($ficha, array(
							'fecha' => $fecha, 'idcolaborador' => 0,
							'tipo' => 'LABORABLE', 'motivo' => $motivo
						)));

						foreach ($quienes as $id) {
							$r = $llamar('/calendario/excepcion', array_merge($ficha, array(
								'fecha'           => $fecha,
								'idcolaborador'   => (int)$id,
								'tipo'            => 'LABORABLE',
								'motivo'          => $motivo,
								'compensacion'    => $comp,
								'fecha_sustituto' => $comp === 'SUSTITUTORIO' ? $sustituto : ''
							)));
							if ($r) { $hechos++; }
						}

						if ($hechos > 0) {
							$paso = 'listo';
						} else {
							$error = 'No se pudo habilitar el dia. Vuelve a intentar.';
						}
					}
				}

				if ($paso === 'elegir') {
					$dia = $llamar('/calendario/dia', array_merge($ficha, array('fecha' => $fecha)));
					if (!$dia) { $error = 'No se pudo leer el dia de la sede.'; $paso = 'login'; }
				}
			}
		}
	} catch (Exception $e) {
		$error = 'Error del servidor: ' . $e->getMessage();
		$paso = 'login';
	}

	$MESES = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
		'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
	$DIAS_TXT = array('domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado');
	$hoyTexto = $DIAS_TXT[(int)date('w')] . ' ' . (int)date('j') . ' de ' . $MESES[(int)date('n')];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Habilitar el dia</title>
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
	input[type=text], input[type=password], input[type=date], select {
		width: 100%; padding: 10px 12px; margin-bottom: 14px;
		border: 1px solid #ced4da; border-radius: 8px; font-size: 15px; font-family: inherit;
	}

	.gente { margin-bottom: 14px; max-height: 40vh; overflow-y: auto; }
	.persona {
		display: flex; align-items: center; gap: 10px;
		padding: 10px 2px; border-bottom: 1px solid #f1f3f5;
		font-size: 15px; text-transform: none; font-weight: 400; color: #212529;
	}
	.persona:last-child { border-bottom: 0; }
	/* Grande a proposito: se usa con el dedo, de pie, con prisa */
	.persona input { width: 22px; height: 22px; margin: 0; flex: 0 0 auto; }
	.persona span { flex: 1 1 auto; }
	.persona em { font-style: normal; font-size: 12px; color: #6c757d; }

	.opcion {
		display: flex; align-items: center; gap: 10px; padding: 9px 2px;
		font-size: 15px; text-transform: none; font-weight: 400; color: #212529; margin: 0;
	}
	.opcion input { width: 20px; height: 20px; margin: 0; flex: 0 0 auto; }

	button {
		width: 100%; padding: 13px; border: 0; border-radius: 10px;
		background: #28a745; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer;
	}
	button:active { background: #1e7e34; }
	.volver { display: block; margin-top: 14px; text-align: center; font-size: 13px; color: #6c757d; }

	.error { margin: 0 0 14px; padding: 10px 12px; background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px; }
	.aviso { margin: 0 0 14px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; color: #92400e; font-size: 13px; }
	.nota { margin: 12px 0 0; font-size: 12px; color: #6c757d; }
	.ok { text-align: center; padding: 10px 0; }
	.ok .tic { font-size: 54px; color: #28a745; line-height: 1; }
	.ok .quien { font-size: 19px; font-weight: 600; margin-top: 10px; }
</style>
</head>
<body>
<div class="tarjeta">
	<div class="cab">
		<h1>Habilitar el dia</h1>
		<p>Hoy es <?php echo asisEsc($hoyTexto); ?>. Queda registrado con tu usuario.</p>
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
		<p class="nota">Hace falta un usuario con acceso al <b>Control de Asistencia</b>.</p>
		<a class="volver" href="kiosko.html">Volver a la pantalla</a>
	</form>

<?php } else if ($paso === 'listo') { ?>
	<div class="ok">
		<div class="tic">&#10004;</div>
		<div class="quien">Dia habilitado</div>
		<p><?php echo (int)$hechos; ?> persona(s) ya pueden marcar.</p>
	</div>
	<p class="nota">Diles que vuelvan a escanear el QR de la pantalla.</p>
	<a class="volver" href="kiosko.html">Volver a la pantalla</a>

<?php } else { ?>
	<?php
		$feriado = !empty($dia['feriado']) ? $dia['feriado'] : '';
		// Los que la regla ya deja trabajar no necesitan nada: se muestran
		// aparte para que el administrador no los marque por las dudas.
		$libres = array();
		$yaPueden = array();
		foreach ($dia['personal'] as $p) {
			if (!empty($p['labora'])) { $yaPueden[] = $p; } else { $libres[] = $p; }
		}
	?>
	<?php if ($feriado !== '') { ?>
		<p class="aviso">Hoy es feriado: <?php echo asisEsc($feriado); ?>.</p>
	<?php } ?>

	<form method="post">
		<!-- El ticket reemplaza a usuario y clave: vence en minutos y solo
		     sirve para terminar este tramite. -->
		<input type="hidden" name="t" value="<?php echo asisEsc($ticket); ?>">
		<input type="hidden" name="confirmar" value="1">

		<label for="motivo">Por que se abre hoy</label>
		<input type="text" id="motivo" name="motivo" maxlength="200" required
			placeholder="Dia del Pollo a la Brasa, Fiestas Patrias..."
			value="<?php echo asisEsc(isset($_POST['motivo']) ? $_POST['motivo'] : ($feriado !== '' ? $feriado : '')); ?>">

		<label>Quienes vienen a trabajar</label>
		<div class="gente">
			<?php foreach ($libres as $p) { ?>
				<label class="persona">
					<input type="checkbox" name="q[]" value="<?php echo (int)$p['idcolaborador']; ?>">
					<span><?php echo asisEsc($p['nombres']); ?>
						<em><?php echo asisEsc($p['motivo'] !== '' ? $p['motivo'] : 'Hoy descansa'); ?></em>
					</span>
				</label>
			<?php } ?>
			<?php if (!count($libres)) { ?>
				<p class="nota">Hoy ya le toca trabajar a todo el personal: no hace falta habilitar a nadie.</p>
			<?php } ?>
		</div>

		<?php if (count($yaPueden)) { ?>
			<p class="nota" style="margin-top:-6px;margin-bottom:14px">
				<?php echo count($yaPueden); ?> persona(s) ya pueden marcar hoy sin cambiar nada.
			</p>
		<?php } ?>

		<label>Como se les paga este dia</label>
		<label class="opcion">
			<input type="radio" name="comp" value="RECARGO" checked onchange="xSus()"> Se les paga doble
		</label>
		<label class="opcion">
			<input type="radio" name="comp" value="SUSTITUTORIO" onchange="xSus()"> Descansan otro dia
		</label>
		<div id="sus" hidden>
			<label for="sustituto">Que dia descansan</label>
			<input type="date" id="sustituto" name="sustituto" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
		</div>

		<button type="submit">Habilitar el dia</button>
		<p class="nota">La ley pide elegir una de las dos. Si descansan otro dia no hay recargo; si no, el dia se paga doble.</p>
		<a class="volver" href="kiosko.html">Cancelar</a>
	</form>

	<script>
	function xSus() {
		var s = document.querySelector('input[name=comp]:checked').value === 'SUSTITUTORIO';
		document.getElementById('sus').hidden = !s;
		document.getElementById('sustituto').required = s;
	}
	xSus();
	</script>
<?php } ?>

	</div>
</div>
</body>
</html>

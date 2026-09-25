<?php
	// Marcacion de entrada / salida.
	//
	// Aqui llega el celular del trabajador despues de escanear el QR de la
	// pantalla del local. Necesita las DOS pruebas:
	//   - el codigo del QR  -> que esta fisicamente en el local (vence en 15-30 s)
	//   - la cookie del celular -> que es quien dice ser
	//
	// Y si la sede lo exige, una tercera: la UBICACION. El QR se puede
	// fotografiar y mandar por WhatsApp; el GPS no se puede prestar.
	//
	// Cuando hace falta ubicacion la pagina trabaja en dos tiempos: primero
	// pide el permiso al navegador y despues se manda sola con las coordenadas.
	// Pedir GPS cuando la sede no lo exige es molesto y entrena a la gente a
	// decir que no, asi que primero se pregunta si hace falta.

	require_once __DIR__ . '/_api.php';

	header('Content-Type: text/html;charset=utf-8');
	header('Cache-Control: no-store');
	header('Referrer-Policy: no-referrer');
	header('X-Frame-Options: DENY');

	$idkiosko = isset($_GET['k']) ? (int)$_GET['k'] : 0;
	$codigo   = isset($_GET['c']) ? trim($_GET['c']) : '';
	$cookie   = isset($_COOKIE['asis_dev']) ? trim($_COOKIE['asis_dev']) : '';

	$tipo = 'error';         // error | entrada | salida | aviso | ubicando
	$titulo = '';
	$mensaje = '';
	$nombre = '';
	$detalle = '';
	$radio = 150;
	$habilitable = false;

	// Coordenadas que manda el propio formulario en el segundo paso
	$lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
	$lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
	$prec = isset($_POST['prec']) && $_POST['prec'] !== '' ? (float)$_POST['prec'] : null;
	$yaIntento = isset($_POST['gps']);       // el navegador ya respondio (bien o mal)
	$gpsFallo = isset($_POST['gps_error']) ? trim($_POST['gps_error']) : '';

	function marcar($idkiosko, $codigo, $cookie, $lat, $lng, $prec) {
		$cuerpo = array('idkiosko' => $idkiosko, 'codigo' => $codigo, 'dispositivo' => $cookie);
		if ($lat !== null && $lng !== null) {
			$cuerpo['lat'] = $lat;
			$cuerpo['lng'] = $lng;
			$cuerpo['precision'] = $prec;
		}
		return asisApiPublica('/marcar', $cuerpo);
	}

	if ($idkiosko <= 0 || !preg_match('/^[0-9a-f]{10}$/', $codigo)) {
		$titulo = 'Codigo no valido';
		$mensaje = 'Vuelve a escanear el codigo QR de la pantalla.';

	} else if (!preg_match('/^[0-9a-f]{64}$/', $cookie)) {
		// Sin cookie: o nunca enrolo, o la borro, o esta en modo incognito
		$titulo = 'Este celular no esta activado';
		$mensaje = 'Pide al administrador que active tu celular. Se hace una sola vez.';

	} else {
		// Paso 0: esta sede exige ubicacion?
		$info = asisApiPublica('/marcar/info', array('idkiosko' => $idkiosko));
		$exigeGps = $info['ok'] && !empty($info['datos']['gps_requerido']);
		if ($info['ok'] && isset($info['datos']['radio_m'])) { $radio = (int)$info['datos']['radio_m']; }

		if ($exigeGps && !$yaIntento) {
			// Paso 1: la pagina pide la ubicacion y se reenvia sola
			$tipo = 'ubicando';

		} else {
			if ($exigeGps && $gpsFallo !== '') {
				$titulo = 'No pudimos ubicarte';
				$mensaje = $gpsFallo;
			} else {
				$r = marcar($idkiosko, $codigo, $cookie, $lat, $lng, $prec);

				if (!$r['ok']) {
					$titulo = 'No se pudo marcar';
					$mensaje = $r['error'];
					// Un "no" que se puede resolver en el momento: el dia esta
					// cerrado o es su descanso y hace falta que un administrador
					// lo habilite. Sin este boton, la unica salida seria ir
					// hasta la computadora del POS.
					$habilitable = !empty($r['datos']['habilitable']);
				} else {
					$d = $r['datos'];
					$nombre = $d['nombres'];

					if ($d['resultado'] === 'ENTRADA' || $d['resultado'] === 'SALIDA') {
						$tipo = strtolower($d['resultado']);
						$titulo = $d['resultado'] === 'ENTRADA' ? 'Entrada registrada' : 'Salida registrada';
						$mensaje = $d['hora'];

						if ($d['resultado'] === 'ENTRADA' && isset($d['tardanza_min']) && $d['tardanza_min'] > 0) {
							$detalle = 'Llegaste ' . (int)$d['tardanza_min'] . ' minuto(s) tarde.';
						}
						if ($d['resultado'] === 'SALIDA' && !empty($d['horas'])) {
							$detalle = 'Trabajaste ' . $d['horas'] . ' horas hoy.';
						}
						// Lo que la politica del local decidio sola. Se avisa
						// siempre: el trabajador tiene que saber en el momento
						// que este dia se le paga distinto.
						if (!empty($d['nota'])) {
							$detalle = ($detalle !== '' ? $detalle . ' ' : '') . $d['nota'];
						}
					} else {
						// DEDUPE (doble escaneo) o COMPLETO (ya marco las dos)
						$tipo = 'aviso';
						$titulo = 'Sin cambios';
						$mensaje = $d['mensaje'];
					}
				}
			}
		}
	}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?php echo asisEsc($titulo !== '' ? $titulo : 'Marcando...'); ?></title>
<style>
	:root { color-scheme: light; }
	* { box-sizing: border-box; }
	body {
		margin: 0;
		padding: max(24px, env(safe-area-inset-top)) 18px max(24px, env(safe-area-inset-bottom));
		min-height: 100vh;
		display: flex; align-items: center; justify-content: center;
		background: #f4f6f8; color: #212529;
		font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
	}
	.tarjeta {
		width: 100%; max-width: 420px; text-align: center;
		background: #fff; border: 1px solid #edf0f2; border-radius: 14px;
		padding: 30px 22px;
		box-shadow: 0 1px 2px rgba(16,24,40,.04);
	}
	.icono { font-size: 62px; line-height: 1; }
	.titulo { font-size: 20px; font-weight: 600; margin: 14px 0 0; }
	.nombre { font-size: 15px; color: #6c757d; margin: 2px 0 0; }
	.hora { font-size: 46px; font-weight: 700; margin: 12px 0 0; font-variant-numeric: tabular-nums; }
	.detalle { margin: 10px 0 0; font-size: 13.5px; }
	.nota { margin: 18px 0 0; font-size: 12px; color: #6c757d; }
	/* Salida de emergencia cuando el marcador dice que no: se usa de pie y con
	   prisa, asi que va grande y separado del texto. */
	.boton {
		display: block; margin-top: 20px; padding: 13px 16px;
		background: #28a745; color: #fff; border-radius: 10px;
		font-size: 15px; font-weight: 600; text-decoration: none;
	}
	.boton:active { background: #1e7e34; }

	.entrada .icono, .salida .icono { color: #28a745; }
	.entrada .hora { color: #198754; }
	.salida  .hora { color: #0d6efd; }
	.aviso   .icono { color: #f59e0b; }
	.error   .icono { color: #dc3545; }
	.tarde { color: #dc3545; font-weight: 600; }

	/* Girito de espera mientras el navegador resuelve la ubicacion */
	.giro {
		width: 46px; height: 46px; margin: 6px auto 0;
		border: 4px solid #e5e7eb; border-top-color: #0d6efd; border-radius: 50%;
		animation: gira 1s linear infinite;
	}
	@keyframes gira { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<?php if ($tipo === 'ubicando') { ?>
<div class="tarjeta">
	<div class="giro"></div>
	<div class="titulo">Comprobando que estas en el local</div>
	<p class="detalle" id="paso">Permite el acceso a tu ubicacion cuando el celular lo pida.</p>
	<p class="nota">Se usa solo para confirmar que estas aqui. No queda un seguimiento de tus movimientos.</p>
</div>

<form method="post" id="f">
	<input type="hidden" name="gps" value="1">
	<input type="hidden" name="lat" id="lat">
	<input type="hidden" name="lng" id="lng">
	<input type="hidden" name="prec" id="prec">
	<input type="hidden" name="gps_error" id="gps_error">
</form>

<script>
(function () {
	'use strict';
	var f = document.getElementById('f');
	var enviado = false;

	function enviar() { if (!enviado) { enviado = true; f.submit(); } }

	function fallar(texto) {
		document.getElementById('gps_error').value = texto;
		enviar();
	}

	if (!navigator.geolocation) {
		fallar('Este navegador no puede dar tu ubicacion. Avisa al administrador para marcar a mano.');
		return;
	}

	// El codigo del QR vence en pocos segundos, asi que no se puede esperar
	// eternamente por una lectura perfecta: 10 s y se manda lo que haya.
	navigator.geolocation.getCurrentPosition(
		function (pos) {
			document.getElementById('lat').value = pos.coords.latitude;
			document.getElementById('lng').value = pos.coords.longitude;
			document.getElementById('prec').value = pos.coords.accuracy;
			document.getElementById('paso').textContent = 'Listo, registrando tu marca...';
			enviar();
		},
		function (err) {
			// Mensajes por causa: "error 1" no le dice nada a nadie
			if (err.code === 1) {
				fallar('No diste permiso para usar tu ubicacion. Tocala de nuevo y elige Permitir.');
			} else if (err.code === 3) {
				fallar('Tardo demasiado en ubicarte. Sal un momento al exterior y vuelve a escanear.');
			} else {
				fallar('Tu celular no pudo obtener la ubicacion. Revisa que el GPS este encendido.');
			}
		},
		{ enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
	);
})();
</script>

<?php } else { ?>
<div class="tarjeta <?php echo asisEsc($tipo); ?>">

<?php if ($tipo === 'entrada' || $tipo === 'salida') { ?>
	<div class="icono">&#10004;</div>
	<div class="titulo"><?php echo asisEsc($titulo); ?></div>
	<div class="nombre"><?php echo asisEsc($nombre); ?></div>
	<div class="hora"><?php echo asisEsc($mensaje); ?></div>
	<?php if ($detalle !== '') { ?>
		<p class="detalle <?php echo ($tipo === 'entrada' ? 'tarde' : ''); ?>"><?php echo asisEsc($detalle); ?></p>
	<?php } ?>
	<p class="nota">Ya puedes cerrar esta pagina.</p>

<?php } else if ($tipo === 'aviso') { ?>
	<div class="icono">&#33;</div>
	<div class="titulo"><?php echo asisEsc($titulo); ?></div>
	<div class="nombre"><?php echo asisEsc($nombre); ?></div>
	<p class="detalle"><?php echo asisEsc($mensaje); ?></p>

<?php } else { ?>
	<div class="icono">&#9888;</div>
	<div class="titulo"><?php echo asisEsc($titulo); ?></div>
	<p class="detalle"><?php echo asisEsc($mensaje); ?></p>
	<?php if ($habilitable) { ?>
		<a class="boton" href="habilitar.php">Soy el administrador: habilitar hoy</a>
		<p class="nota">Despues de habilitarlo, vuelve a escanear el QR.</p>
	<?php } ?>
<?php } ?>

</div>
<?php } ?>

</body>
</html>

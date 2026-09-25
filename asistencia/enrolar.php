<?php
	// Enrolamiento del celular del trabajador.
	//
	// Se abre escaneando el QR de invitacion que el administrador genera en la
	// ficha, en el POS. La invitacion dura 15 minutos y se quema al usarse, asi
	// que este enlace no sirve de nada si alguien lo reenvia despues.
	//
	// Resultado: una cookie HttpOnly de 180 dias que prueba QUIEN es el que
	// marca. La otra mitad (que este fisicamente en el local) la prueba el QR
	// rotativo del kiosko, en la fase 4.

	require_once __DIR__ . '/_api.php';

	header('Content-Type: text/html;charset=utf-8');
	header('Cache-Control: no-store');       // no dejar rastro del codigo en cache
	header('Referrer-Policy: no-referrer');  // el uuid no debe viajar a terceros
	header('X-Frame-Options: DENY');

	$invitacion = isset($_GET['i']) ? trim($_GET['i']) : '';
	// Formato UUID v4. Filtrar aqui evita mandar basura a la API.
	if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitacion)) {
		$invitacion = '';
	}

	$estado = 'inicio';   // inicio | listo | error
	$mensaje = '';
	$nombre = '';
	$reemplaza = false;
	$dias = 0;

	if ($invitacion === '') {
		$estado = 'error';
		$mensaje = 'El enlace esta incompleto. Vuelve a escanear el codigo QR.';

	} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// Confirmacion: el trabajador vio su nombre y toco el boton
		$r = asisApiPublica('/enrolar/confirmar', array(
			'invitacion' => $invitacion,
			'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
		));
		if ($r['ok']) {
			asisGuardarCookie($r['datos']['token'], (int)$r['datos']['dias']);
			$estado = 'listo';
			$nombre = $r['datos']['nombres'];
			$dias = (int)$r['datos']['dias'];
		} else {
			$estado = 'error';
			$mensaje = $r['error'];
		}

	} else {
		// Primera visita: mostrar a quien va a quedar atado este celular
		$r = asisApiPublica('/enrolar/info', array('invitacion' => $invitacion));
		if ($r['ok']) {
			$nombre = $r['datos']['nombres'];
			$reemplaza = !empty($r['datos']['reemplaza']);
		} else {
			$estado = 'error';
			$mensaje = $r['error'];
		}
	}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Activar mi celular</title>
<style>
	:root { color-scheme: light; }
	* { box-sizing: border-box; }
	body {
		margin: 0;
		padding: max(24px, env(safe-area-inset-top)) 18px max(24px, env(safe-area-inset-bottom));
		min-height: 100vh;
		display: flex; align-items: center; justify-content: center;
		background: #f4f6f8;
		color: #212529;
		font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
	}
	.tarjeta {
		width: 100%; max-width: 420px;
		background: #fff;
		border: 1px solid #edf0f2;
		border-radius: 14px;
		overflow: hidden;
		box-shadow: 0 1px 2px rgba(16,24,40,.04);
	}
	.cab { padding: 18px 20px; background: lavenderblush; }
	.cab h1 { margin: 0; font-size: 17px; font-weight: 600; color: #424242; }
	.cab p  { margin: 4px 0 0; font-size: 12px; color: #6c757d; }
	.cuerpo { padding: 22px 20px; text-align: center; }

	.icono { font-size: 44px; line-height: 1; margin-bottom: 12px; }
	.nombre { font-size: 21px; font-weight: 600; margin: 4px 0 2px; }
	.rotulo { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: .04em; }

	.aviso {
		margin: 16px 0 0; padding: 11px 13px; text-align: left;
		background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px;
		font-size: 12.5px; color: #7c2d12;
	}
	.nota { margin: 16px 0 0; font-size: 12px; color: #6c757d; }

	button {
		width: 100%; margin-top: 20px; padding: 14px;
		border: 0; border-radius: 10px;
		background: #28a745; color: #fff;
		font-size: 16px; font-weight: 600;
		cursor: pointer;
	}
	button:active { background: #1e7e34; }
	button:disabled { background: #adb5bd; cursor: default; }

	.error .icono { color: #dc3545; }
	.ok .icono { color: #28a745; }
</style>
</head>
<body>
<div class="tarjeta">
	<div class="cab">
		<h1>Control de Asistencia</h1>
		<p>Activa este celular para poder marcar tu entrada y salida.</p>
	</div>

<?php if ($estado === 'error') { ?>
	<div class="cuerpo error">
		<div class="icono">&#9888;</div>
		<p style="margin:0"><?php echo asisEsc($mensaje); ?></p>
		<p class="nota">Los codigos duran 15 minutos y se usan una sola vez.</p>
	</div>

<?php } else if ($estado === 'listo') { ?>
	<div class="cuerpo ok">
		<div class="icono">&#10004;</div>
		<div class="nombre"><?php echo asisEsc($nombre); ?></div>
		<p style="margin:6px 0 0">Tu celular quedo activado.</p>
		<p class="nota">
			Ya puedes marcar escaneando el codigo QR de la pantalla del local.<br>
			La activacion dura <?php echo (int)$dias; ?> dias. No borres los datos del navegador
			ni uses modo incognito, o tendras que activarlo de nuevo.
		</p>
	</div>

<?php } else { ?>
	<form method="post" class="cuerpo">
		<div class="rotulo">Vas a activar el celular de</div>
		<div class="nombre"><?php echo asisEsc($nombre); ?></div>

		<?php if ($reemplaza) { ?>
		<div class="aviso">
			<b>Esta persona ya tiene otro celular activado.</b><br>
			Si continuas, el anterior deja de servir para marcar.
		</div>
		<?php } ?>

		<div class="aviso" style="background:#f8fafc;border-color:#e5e7eb;color:#495057">
			Si este <b>no</b> es tu nombre, no continues: avisa al administrador.
		</div>

		<button type="submit" onclick="this.disabled=true;this.form.submit()">Si, soy yo &mdash; activar</button>
		<p class="nota">Este enlace vence en pocos minutos.</p>
	</form>
<?php } ?>
</div>
</body>
</html>

<?php
/**
 * Cambio de turno, desde el celular del trabajador.
 *
 * DOS MOMENTOS, LOS DOS EN EL MARCADOR
 *
 *   1. El que VINO A CUBRIR llega en su dia de descanso, marca, y el marcador
 *      le ofrece decir a quien esta cubriendo. Elige de una lista: los que hoy
 *      tenian que trabajar y todavia no vinieron.
 *
 *   2. El que FALTO lo confirma la proxima vez que marca. Es el momento
 *      natural: no estaba el dia del cambio -- por eso hubo cambio -- pero
 *      vuelve al dia siguiente.
 *
 * POR QUE NO HACE FALTA EL QR AQUI
 * El QR prueba PRESENCIA, y eso se necesita para marcar. Esto no es una marca:
 * es declarar o confirmar un acuerdo entre dos personas. Alcanza con la cookie
 * del celular, que es la que dice quien es.
 *
 * POR QUE NO INTERVIENE EL ADMINISTRADOR
 * Era el punto del pedido. Proponer el cambio ya habilita a trabajar; lo que
 * queda pendiente es solo COMO se paga ese dia. Y si nadie confirma, se paga
 * como corresponde a un descanso trabajado sin sustituto: con el recargo.
 */

require_once __DIR__ . '/_api.php';

$cookie = isset($_COOKIE['asis_disp']) ? $_COOKIE['asis_disp'] : '';

$titulo  = '';
$mensaje = '';
$tipo    = 'aviso';
$candidatos = array();
$pendientes = array();

if ($cookie === '') {
	$titulo  = 'Celular no vinculado';
	$mensaje = 'Pide al administrador que active tu celular. Se hace una sola vez.';

} else {
	$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

	// --- el que cubre declara a quien cubre --------------------------------
	if ($accion === 'proponer') {
		$aQuien = isset($_POST['idcolaborador_falta']) ? (int)$_POST['idcolaborador_falta'] : 0;
		$r = asisApiPublica('/cambio-turno/proponer', array(
			'dispositivo' => $cookie,
			'idcolaborador_falta' => $aQuien
		));
		if (!empty($r['ok'])) {
			$tipo    = 'ok';
			$titulo  = 'Listo';
			$mensaje = 'Queda anotado que hoy cubres a tu companero. '
			         . 'El te lo va a confirmar cuando marque.';
		} else {
			$titulo  = 'No se pudo anotar';
			$mensaje = $r['error'];
		}

	// --- el que falto responde ---------------------------------------------
	} elseif ($accion === 'resolver') {
		$idcambio = isset($_POST['idcambio']) ? (int)$_POST['idcambio'] : 0;
		$acepta   = isset($_POST['acepta']) && $_POST['acepta'] === '1';

		$r = asisApiPublica('/cambio-turno/resolver', array(
			'dispositivo' => $cookie,
			'idcambio'    => $idcambio,
			'acepta'      => $acepta
		));
		if (!empty($r['ok'])) {
			$tipo   = 'ok';
			$titulo = $acepta ? 'Cambio confirmado' : 'Cambio rechazado';
			$mensaje = $acepta
				? 'Ese dia queda como tu descanso, y a tu companero no se le paga doble '
				  . 'porque tuvo su dia libre a cambio.'
				: 'Se anoto que no hubo acuerdo. Ese dia sigue contando como falta tuya.';
		} else {
			$titulo  = 'No se pudo responder';
			$mensaje = $r['error'];
		}

	// --- nada que hacer todavia: se muestran las dos listas ----------------
	} else {
		$rp = asisApiPublica('/cambio-turno/pendientes', array('dispositivo' => $cookie));
		if (!empty($rp['ok'])) { $pendientes = $rp['datos']['pendientes']; }

		$rc = asisApiPublica('/cambio-turno/candidatos', array('dispositivo' => $cookie));
		if (!empty($rc['ok'])) { $candidatos = $rc['datos']['candidatos']; }

		if (!$pendientes && !$candidatos) {
			$titulo  = 'Nada pendiente';
			$mensaje = 'No hay cambios de turno para anotar ni para confirmar.';
		}
	}
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Cambio de turno</title>
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
	.detalle { margin: 10px 0 0; font-size: 13.5px; }
	.nota { margin: 14px 0 0; font-size: 12px; color: #6c757d; }
	/* Se usa de pie y con prisa: los botones van grandes y separados. */
	.boton {
		display: block; width: 100%; margin-top: 12px; padding: 13px 16px;
		background: #28a745; color: #fff; border: 0; border-radius: 10px;
		font: inherit; font-size: 15px; font-weight: 600; text-decoration: none;
		cursor: pointer;
	}
	.boton:active { background: #1e7e34; }
	/* El "No" no puede verse igual que el "Si": son decisiones distintas. */
	.boton-gris { background: #6c757d; }
	.boton-gris:active { background: #545b62; }
	.ok .icono { color: #28a745; }
	.aviso .icono { color: #f59e0b; }
	/* Cada cambio, separado del siguiente: son decisiones independientes. */
	.bloque { margin-top: 22px; padding-top: 18px; border-top: 1px solid #edf0f2; }
	.bloque:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
</style>
</head>
<body>

<div class="tarjeta <?php echo asisEsc($tipo); ?>">

<?php if ($titulo !== '') { ?>
	<div class="icono"><?php echo $tipo === 'ok' ? '&#10003;' : '&#9888;'; ?></div>
	<div class="titulo"><?php echo asisEsc($titulo); ?></div>
	<p class="detalle"><?php echo asisEsc($mensaje); ?></p>
	<a class="boton" href="cambio-turno.php">Volver</a>

<?php } else { ?>

	<?php if ($pendientes) { ?>
		<div class="titulo">Te cubrieron un dia</div>
		<?php foreach ($pendientes as $p) { ?>
			<div class="bloque"></div>
			<p class="detalle">
				<b><?php echo asisEsc($p['cubrio']); ?></b> dice que te cubrio el
				<b><?php echo asisEsc($p['fecha']); ?></b>. Es cierto?
			</p>
			<form method="post">
				<input type="hidden" name="accion" value="resolver">
				<input type="hidden" name="idcambio" value="<?php echo (int)$p['idcambio']; ?>">
				<input type="hidden" name="acepta" value="1">
				<button class="boton" type="submit">Si, cambiamos turno</button>
			</form>
			<form method="post">
				<input type="hidden" name="accion" value="resolver">
				<input type="hidden" name="idcambio" value="<?php echo (int)$p['idcambio']; ?>">
				<input type="hidden" name="acepta" value="0">
				<button class="boton boton-gris" type="submit">No, no cambiamos</button>
			</form>
			<p class="nota">
				Si confirmas, ese dia pasa a ser tu descanso y no se te descuenta.
			</p>
		<?php } ?>
	<?php } ?>

	<?php if ($candidatos) { ?>
		<div class="titulo">A quien estas cubriendo?</div>
		<p class="detalle">
			Hoy es tu dia de descanso. Si viniste a cubrir a un companero, dinos a quien.
		</p>
		<?php foreach ($candidatos as $c) { ?>
			<form method="post">
				<input type="hidden" name="accion" value="proponer">
				<input type="hidden" name="idcolaborador_falta" value="<?php echo (int)$c['idcolaborador']; ?>">
				<button class="boton" type="submit"><?php echo asisEsc($c['nombres']); ?></button>
			</form>
		<?php } ?>
		<p class="nota">
			Solo aparecen los que hoy tenian que trabajar y todavia no llegaron.
		</p>
	<?php } ?>

<?php } ?>

</div>

</body>
</html>

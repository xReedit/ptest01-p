<?php
	// Codigo que debe mostrar la pantalla del kiosko en este instante.
	// Lo pide kiosko.html cada pocos segundos.
	//
	// No lleva sesion del POS: la pantalla se autoriza con su propio token, que
	// le quedo guardado cuando se vinculo desde el panel.

	require_once __DIR__ . '/_api.php';

	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-store');

	$entrada = json_decode(file_get_contents('php://input'), true);
	$token = isset($entrada['kiosko_token']) ? trim($entrada['kiosko_token']) : '';

	if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
		echo json_encode(array('success' => false, 'datos' => null,
			'error' => 'Esta pantalla no esta vinculada. Abre el enlace de vinculacion desde el POS.'));
		exit;
	}

	$r = asisApiPublica('/codigo', array('kiosko_token' => $token));
	echo json_encode(array('success' => $r['ok'], 'datos' => $r['datos'], 'error' => $r['error']));

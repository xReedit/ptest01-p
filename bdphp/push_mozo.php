<?php
	// Notificaciones push a la app mozo.
	// El envio real lo hace backend-pedidos (tiene la llave de Firebase y los tokens de cada usuario,
	// tabla usuario_push_token); desde aqui solo se le avisa por HTTP que pasó algo.
	//
	// Uso desde log.php:
	//   include "push_mozo.php";
	//   pushMozoPedidoListo($g_idsede, $idpedido);                    // "Pedido de la mesa 10 listo"
	//   pushMozoPedidoListo($g_idsede, $idpedido, $idpedido_detalle); // "De la mesa 10 - Lomo saltado listo"
	// El backend busca pedido.idusuario y le manda el push solo a ese mozo.

	function pushMozoUrlBackend() {
		$url = 'https://app.restobar.papaya.com.pe/api.pwa/v3';
		// DESARROLLO: este PHP corre en 192.168.1.65 pero el backend corre en la PC del desarrollador.
		// Mantener esta IP igual que URL_SERVER de app/view/config.const.js (mismo criterio que log.php op 7000).
		if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '192.168.') === 0) {
			$url = 'http://192.168.1.52:5819/v3';
		}
		return $url;
	}

	// Nunca bloquea al POS: timeout corto y si el backend no responde solo queda en el error log.
	function pushMozoPedidoListo($idsede, $idpedido, $idpedido_detalle = 0) {
		$idsede = intval($idsede);
		$idpedido = intval($idpedido);
		$idpedido_detalle = intval($idpedido_detalle);
		if ($idsede <= 0 || $idpedido <= 0) { return false; }

		$body = array('idsede' => $idsede, 'idpedido' => $idpedido);
		if ($idpedido_detalle > 0) { $body['idpedido_detalle'] = $idpedido_detalle; }

		$ch = curl_init(pushMozoUrlBackend() . '/mozo/push-pedido-listo');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$rpt = curl_exec($ch);
		if ($rpt === false) {
			error_log('[push-mozo] pedido ' . $idpedido . ' detalle ' . $idpedido_detalle . ' sede ' . $idsede . ': ' . curl_error($ch));
		}
		curl_close($ch);
		return $rpt;
	}

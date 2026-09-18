<?php
	// Plantillas de arranque del modulo Encuestas (migracion 031). Al elegir una se copian sus
	// preguntas a enc_pregunta y desde ahi se editan como cualquier encuesta.
	// ponytail: array constante y no tabla; no se editan en runtime ni necesitan migracion para
	// cambiar un texto. Si algun dia cada org quiere sus propias plantillas, ahi va una tabla.
	function encPlantillas() {
		return array(
			array(
				'clave' => 'local',
				'nombre' => 'Satisfacción en el local',
				'para' => 'QR de entrada o salida, tablet',
				'texto_inicio' => 'Tu opinión nos ayuda a mejorar. Te toma menos de 30 segundos.',
				'texto_fin' => 'Gracias por tu tiempo. Te esperamos pronto.',
				'preguntas' => array(
					array('tipo' => 'csat', 'texto' => '¿Cómo fue tu experiencia en general?', 'obligatorio' => 1),
					array('tipo' => 'csat', 'texto' => '¿Cómo te atendió nuestro personal?', 'obligatorio' => 1),
					array('tipo' => 'csat', 'texto' => '¿Qué te pareció la comida?', 'obligatorio' => 1),
					array('tipo' => 'texto', 'texto' => '¿Hay algo que podamos mejorar?', 'obligatorio' => 0)
				)
			),
			array(
				'clave' => 'nps',
				'nombre' => 'NPS post-venta',
				'para' => 'Comprobante impreso y WhatsApp',
				'texto_inicio' => 'Gracias por tu compra. Solo dos preguntas.',
				'texto_fin' => 'Gracias, tu respuesta ya llegó al equipo.',
				'preguntas' => array(
					array('tipo' => 'nps', 'texto' => '¿Qué tan probable es que nos recomiendes a un amigo?', 'obligatorio' => 1),
					array('tipo' => 'texto', 'texto' => '¿Por qué esa nota?', 'obligatorio' => 0)
				)
			),
			array(
				'clave' => 'delivery',
				'nombre' => 'Delivery',
				'para' => 'Pedidos a domicilio',
				'texto_inicio' => '¿Cómo llegó tu pedido? Cuéntanos en un minuto.',
				'texto_fin' => 'Gracias. Con esto mejoramos cada entrega.',
				'preguntas' => array(
					array('tipo' => 'csat', 'texto' => '¿Qué te pareció el tiempo de entrega?', 'obligatorio' => 1),
					array('tipo' => 'csat', 'texto' => '¿En qué estado llegó tu pedido?', 'obligatorio' => 1),
					array('tipo' => 'ces', 'texto' => '¿Qué tan fácil fue hacer tu pedido?', 'obligatorio' => 1),
					array('tipo' => 'texto', 'texto' => '¿Algún comentario sobre tu pedido?', 'obligatorio' => 0)
				)
			),
			array(
				'clave' => 'express',
				'nombre' => 'Express (1 toque)',
				'para' => 'Tablet en la salida',
				'texto_inicio' => '',
				'texto_fin' => '¡Gracias!',
				'preguntas' => array(
					array('tipo' => 'csat', 'texto' => '¿Cómo estuvo todo?', 'obligatorio' => 1)
				)
			)
		);
	}
?>

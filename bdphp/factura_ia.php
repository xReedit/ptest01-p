<?php
	// Lectura de facturas con IA (Claude | OpenAI | Gemini). Plan: plan/COMPRAS-LECTURA-FACTURA-PLAN.md
	// Solo funciones: lo incluye log_compras.php. La factura NO se guarda: se lee desde el temporal de la subida.
	// La salida de la IA es dato NO confiable: se normaliza y valida aqui antes de mostrarla.

	class FacturaIaError extends Exception {}

	// ---------------- configuracion (private/compras_secrets.php; una variable de entorno igual tiene prioridad) ----------------
	function facturaIaConfig() {
		$f = __DIR__ . '/../private/compras_secrets.php';
		if (is_file($f)) { require_once $f; }
		$get = function ($k, $def) {
			$e = getenv($k);
			if ($e !== false && $e !== '') { return $e; }
			return defined($k) ? constant($k) : $def;
		};
		$c = array(
			'proveedor' => strtolower(trim((string)$get('FACTURA_IA_PROVEEDOR', ''))),
			'modelo'    => trim((string)$get('FACTURA_IA_MODELO', '')),
			'clave'     => trim((string)$get('FACTURA_IA_API_KEY', '')),
			'limite'    => (int)$get('FACTURA_IA_LIMITE_DIA', 50),
			// nginx corta a los 60 s: la llamada nunca espera mas de 50
			'timeout'   => max(10, min(50, (int)$get('FACTURA_IA_TIMEOUT', 50)))
		);
		if (!in_array($c['proveedor'], array('claude', 'openai', 'gemini'), true) || $c['modelo'] === '' || $c['clave'] === '' || strpos($c['clave'], 'PEGA_AQUI') !== false) {
			throw new FacturaIaError('La lectura de facturas no está configurada (private/compras_secrets.php). Registra la compra a mano.');
		}
		return $c;
	}

	// ---------------- instrucciones y formato (igual para los 3 proveedores) ----------------
	function facturaIaEsquema() {
		$num = array('type' => array('number', 'null'));
		$txt = array('type' => array('string', 'null'));
		return array(
			'type' => 'object',
			'properties' => array(
				'emisor' => array('type' => 'object', 'properties' => array('ruc' => $txt, 'razon_social' => $txt)),
				'comprobante' => array('type' => 'object', 'properties' => array(
					'tipo' => $txt, 'serie' => $txt, 'numero' => $txt, 'fecha' => $txt, 'moneda' => $txt, 'tipo_cambio' => $num)),
				'lineas' => array('type' => 'array', 'items' => array('type' => 'object', 'properties' => array(
					'descripcion' => $txt, 'cantidad' => $num, 'unidad' => $txt, 'valor_unitario' => $num, 'precio_unitario' => $num,
					'descuento' => $num, 'afectacion' => $txt, 'igv' => $num, 'isc' => $num, 'icbper' => $num, 'total' => $num))),
				'totales' => array('type' => 'object', 'properties' => array(
					'op_gravadas' => $num, 'op_exoneradas' => $num, 'op_inafectas' => $num, 'op_gratuitas' => $num, 'descuentos' => $num,
					'igv' => $num, 'isc' => $num, 'icbper' => $num, 'otros_cargos' => $num, 'percepcion' => $num, 'total' => $num, 'total_a_pagar' => $num)),
				'confianza' => $txt,
				'observaciones' => $txt
			),
			'required' => array('emisor', 'comprobante', 'lineas', 'totales')
		);
	}

	function facturaIaPrompt($conFormato) {
		$p = "Eres un asistente que lee comprobantes de compra de restaurantes en Perú (facturas, boletas, notas de venta, guías, tickets).\n" .
			"Extrae los datos EXACTAMENTE como aparecen impresos. Si un dato no se ve o no existe, usa null. NO inventes ni calcules datos que no estén impresos.\n\n" .
			"Reglas:\n" .
			"- emisor: el VENDEDOR (quien emite el comprobante), no el cliente. ruc: 11 dígitos sin espacios.\n" .
			"- comprobante.tipo: FACTURA, BOLETA, NOTA DE VENTA, GUIA u OTRO. serie (ej. F001, B002, E001) y numero por separado.\n" .
			"- comprobante.fecha: fecha de emisión en formato AAAA-MM-DD. moneda: PEN o USD. tipo_cambio solo si está impreso.\n" .
			"- lineas: una por cada producto, en el orden impreso. descripcion tal cual. unidad tal cual (KG, UND, NIU, CJA, BOL, LT...).\n" .
			"- valor_unitario: precio SIN impuestos si está impreso. precio_unitario: precio CON impuestos si está impreso.\n" .
			"- total de cada línea: el importe de la línea TAL COMO ESTÁ IMPRESO (puede ser con o sin IGV, no lo corrijas).\n" .
			"- afectacion: gravado, exonerado, inafecto o gratuito (bonificación / muestra / 'transferencia gratuita').\n" .
			"- igv / isc / icbper de la línea solo si aparecen por línea. descuento de la línea si aparece.\n" .
			"- totales: op_gravadas, op_exoneradas, op_inafectas, op_gratuitas, descuentos (globales), igv, isc, icbper (impuesto a las bolsas), otros_cargos,\n" .
			"  percepcion (régimen de percepciones), total (importe total del comprobante) y total_a_pagar (si hay percepción: total + percepción).\n" .
			"- Números: solo el número con punto decimal (ej. 1250.50), sin símbolos de moneda ni separadores de miles.\n" .
			"- confianza: alta, media o baja según qué tan legible está el comprobante. observaciones: qué no se pudo leer bien (o null).\n";
		if ($conFormato) {
			$p .= "\nResponde SOLO con un objeto JSON con esta forma (JSON Schema):\n" . json_encode(facturaIaEsquema(), JSON_UNESCAPED_UNICODE);
		}
		return $p;
	}

	// ---------------- llamada HTTP ----------------
	function facturaIaHttp($url, $headers, $body, $timeout) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10
		));
		$resp = curl_exec($ch);
		$err = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($resp === false) {
			error_log('factura_ia http: ' . $err);
			throw new FacturaIaError(stripos($err, 'timed out') !== false
				? 'La lectura tardó demasiado. Intenta con una foto más nítida o registra a mano.'
				: 'No se pudo conectar con el servicio de lectura. Registra la compra a mano.');
		}
		$j = json_decode($resp, true);
		if ($code < 200 || $code >= 300) {
			$msg = is_array($j) ? json_encode(isset($j['error']) ? $j['error'] : $j) : substr($resp, 0, 300);
			error_log('factura_ia http ' . $code . ': ' . $msg);
			if ($code === 401 || $code === 403) { throw new FacturaIaError('La clave del servicio de lectura no es válida. Revisa private/compras_secrets.php.'); }
			if ($code === 429) { throw new FacturaIaError('El servicio de lectura está saturado o sin saldo. Intenta en un momento o registra a mano.'); }
			if ($code === 404) { throw new FacturaIaError('El modelo configurado no existe para este proveedor. Revisa FACTURA_IA_MODELO.'); }
			throw new FacturaIaError('El servicio de lectura respondió con error (' . $code . '). Registra la compra a mano.');
		}
		if (!is_array($j)) { throw new FacturaIaError('Respuesta no válida del servicio de lectura.'); }
		return $j;
	}

	function facturaIaJson($texto) {
		$t = trim((string)$texto);
		$t = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $t);
		$d = json_decode($t, true);
		if (!is_array($d)) {
			// algunos modelos agregan texto: tomar el primer objeto JSON
			$a = strpos($t, '{'); $b = strrpos($t, '}');
			if ($a !== false && $b > $a) { $d = json_decode(substr($t, $a, $b - $a + 1), true); }
		}
		if (!is_array($d)) { throw new FacturaIaError('No se pudo interpretar lo leído. Intenta con otra foto o registra a mano.'); }
		return $d;
	}

	// ---------------- adaptadores ----------------
	function facturaIaClaude($c, $b64, $mime) {
		$doc = $mime === 'application/pdf'
			? array('type' => 'document', 'source' => array('type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64))
			: array('type' => 'image', 'source' => array('type' => 'base64', 'media_type' => $mime, 'data' => $b64));
		$j = facturaIaHttp('https://api.anthropic.com/v1/messages',
			array('Content-Type: application/json', 'x-api-key: ' . $c['clave'], 'anthropic-version: 2023-06-01'),
			array(
				'model' => $c['modelo'], 'max_tokens' => 8000,
				'tools' => array(array('name' => 'registrar_factura', 'description' => 'Datos leídos del comprobante', 'input_schema' => facturaIaEsquema())),
				'tool_choice' => array('type' => 'tool', 'name' => 'registrar_factura'),
				'messages' => array(array('role' => 'user', 'content' => array($doc, array('type' => 'text', 'text' => facturaIaPrompt(false)))))
			), $c['timeout']);
		foreach ((isset($j['content']) ? $j['content'] : array()) as $blk) {
			if (isset($blk['type']) && $blk['type'] === 'tool_use' && isset($blk['input']) && is_array($blk['input'])) { return $blk['input']; }
		}
		throw new FacturaIaError('No se pudo interpretar lo leído. Intenta con otra foto o registra a mano.');
	}

	function facturaIaOpenai($c, $b64, $mime) {
		$doc = $mime === 'application/pdf'
			? array('type' => 'file', 'file' => array('filename' => 'factura.pdf', 'file_data' => 'data:application/pdf;base64,' . $b64))
			: array('type' => 'image_url', 'image_url' => array('url' => 'data:' . $mime . ';base64,' . $b64, 'detail' => 'high'));
		// sin temperature: los modelos de razonamiento no lo aceptan
		$j = facturaIaHttp('https://api.openai.com/v1/chat/completions',
			array('Content-Type: application/json', 'Authorization: Bearer ' . $c['clave']),
			array(
				'model' => $c['modelo'], 'max_completion_tokens' => 16000,
				'response_format' => array('type' => 'json_object'),
				'messages' => array(array('role' => 'user', 'content' => array(array('type' => 'text', 'text' => facturaIaPrompt(true)), $doc)))
			), $c['timeout']);
		$txt = isset($j['choices'][0]['message']['content']) ? $j['choices'][0]['message']['content'] : '';
		return facturaIaJson($txt);
	}

	function facturaIaGemini($c, $b64, $mime) {
		$j = facturaIaHttp('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($c['modelo']) . ':generateContent',
			array('Content-Type: application/json', 'x-goog-api-key: ' . $c['clave']),
			array(
				'contents' => array(array('role' => 'user', 'parts' => array(
					array('inline_data' => array('mime_type' => $mime, 'data' => $b64)),
					array('text' => facturaIaPrompt(true))))),
				'generationConfig' => array('responseMimeType' => 'application/json', 'maxOutputTokens' => 16000)
			), $c['timeout']);
		$txt = isset($j['candidates'][0]['content']['parts'][0]['text']) ? $j['candidates'][0]['content']['parts'][0]['text'] : '';
		return facturaIaJson($txt);
	}

	// Lee el archivo y devuelve los datos normalizados. $ruta es el temporal de la subida (no se copia).
	function facturaIaLeer($c, $ruta, $mime) {
		$b64 = base64_encode(file_get_contents($ruta));
		$ini = microtime(true);
		if ($c['proveedor'] === 'claude') { $d = facturaIaClaude($c, $b64, $mime); }
		elseif ($c['proveedor'] === 'openai') { $d = facturaIaOpenai($c, $b64, $mime); }
		else { $d = facturaIaGemini($c, $b64, $mime); }
		$seg = round(microtime(true) - $ini, 2);
		error_log('factura_ia ok proveedor=' . $c['proveedor'] . ' modelo=' . $c['modelo'] . ' seg=' . $seg);
		return array(facturaNormalizar($d), $seg);
	}

	// ---------------- normalizar (dato no confiable) ----------------
	function fnum($v) {
		if ($v === null || $v === '') { return null; }
		if (is_string($v)) { $v = str_replace(array('S/', 'US$', '$', ' ', ','), array('', '', '', '', ''), $v); }
		return is_numeric($v) ? round((float)$v, 6) : null;
	}
	function ftxt($v, $max) {
		if ($v === null || is_array($v)) { return null; }
		$t = trim(preg_replace('/\s+/u', ' ', (string)$v));
		return $t === '' ? null : mb_substr($t, 0, $max, 'UTF-8');
	}
	function facturaNormalizar($d) {
		$e = isset($d['emisor']) && is_array($d['emisor']) ? $d['emisor'] : array();
		$cp = isset($d['comprobante']) && is_array($d['comprobante']) ? $d['comprobante'] : array();
		$t = isset($d['totales']) && is_array($d['totales']) ? $d['totales'] : array();
		$ruc = preg_replace('/\D/', '', (string)(isset($e['ruc']) ? $e['ruc'] : ''));
		$tipo = strtoupper((string)ftxt(isset($cp['tipo']) ? $cp['tipo'] : null, 20));
		$fecha = ftxt(isset($cp['fecha']) ? $cp['fecha'] : null, 10);
		$moneda = strtoupper((string)ftxt(isset($cp['moneda']) ? $cp['moneda'] : null, 5));
		$out = array(
			'emisor' => array('ruc' => $ruc !== '' ? substr($ruc, 0, 11) : null, 'razon_social' => ftxt(isset($e['razon_social']) ? $e['razon_social'] : null, 150)),
			'comprobante' => array(
				'tipo' => in_array($tipo, array('FACTURA', 'BOLETA', 'NOTA DE VENTA', 'GUIA'), true) ? $tipo : ($tipo !== '' ? 'OTRO' : null),
				'serie' => ftxt(isset($cp['serie']) ? $cp['serie'] : null, 10), 'numero' => ftxt(isset($cp['numero']) ? $cp['numero'] : null, 15),
				'fecha' => ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) ? $fecha : null,
				'moneda' => in_array($moneda, array('USD', 'US$', 'DOLARES'), true) ? 'USD' : 'PEN',
				'tipo_cambio' => fnum(isset($cp['tipo_cambio']) ? $cp['tipo_cambio'] : null)),
			'lineas' => array(),
			'totales' => array(),
			'confianza' => in_array(isset($d['confianza']) ? $d['confianza'] : '', array('alta', 'media', 'baja'), true) ? $d['confianza'] : 'media',
			'observaciones' => ftxt(isset($d['observaciones']) ? $d['observaciones'] : null, 300)
		);
		foreach (array('op_gravadas', 'op_exoneradas', 'op_inafectas', 'op_gratuitas', 'descuentos', 'igv', 'isc', 'icbper', 'otros_cargos', 'percepcion', 'total', 'total_a_pagar') as $k) {
			$out['totales'][$k] = fnum(isset($t[$k]) ? $t[$k] : null);
		}
		$lineas = isset($d['lineas']) && is_array($d['lineas']) ? $d['lineas'] : array();
		foreach (array_slice($lineas, 0, 200) as $l) {
			if (!is_array($l)) { continue; }
			$desc = ftxt(isset($l['descripcion']) ? $l['descripcion'] : null, 200);
			if ($desc === null) { continue; }
			$af = strtolower((string)ftxt(isset($l['afectacion']) ? $l['afectacion'] : null, 20));
			$out['lineas'][] = array(
				'descripcion' => $desc,
				'cantidad' => fnum(isset($l['cantidad']) ? $l['cantidad'] : null),
				'unidad' => ftxt(isset($l['unidad']) ? $l['unidad'] : null, 15),
				'valor_unitario' => fnum(isset($l['valor_unitario']) ? $l['valor_unitario'] : null),
				'precio_unitario' => fnum(isset($l['precio_unitario']) ? $l['precio_unitario'] : null),
				'descuento' => fnum(isset($l['descuento']) ? $l['descuento'] : null),
				'afectacion' => in_array($af, array('gravado', 'exonerado', 'inafecto', 'gratuito'), true) ? $af : 'gravado',
				'igv' => fnum(isset($l['igv']) ? $l['igv'] : null),
				'isc' => fnum(isset($l['isc']) ? $l['isc'] : null),
				'icbper' => fnum(isset($l['icbper']) ? $l['icbper'] : null),
				'total' => fnum(isset($l['total']) ? $l['total'] : null)
			);
		}
		return $out;
	}

	// ---------------- costos por linea + validacion de cuadre ----------------
	// Devuelve [lineas con costo_con / costo_sin (por unidad de la factura), avisos, resumen].
	// costo_con = todo lo pagado (IGV, ISC, ICBPER, percepcion y cargos repartidos) · costo_sin = sin IGV ni percepcion.
	function facturaCostos($f) {
		$av = array();
		$T = $f['totales'];
		$L = $f['lineas'];
		$tol = function ($ref) { return max(0.10, abs($ref) * 0.01); };

		// 1) total impreso de cada linea (con fallback) y cantidad
		$imp = array();
		foreach ($L as $i => $l) {
			$c = $l['cantidad'] !== null && $l['cantidad'] > 0 ? $l['cantidad'] : null;
			if ($c === null) { $av[] = array('tipo' => 'error', 'texto' => 'No se leyó la cantidad de "' . $l['descripcion'] . '"'); }
			$t = $l['total'];
			if ($t === null && $c !== null && $l['precio_unitario'] !== null) { $t = $c * $l['precio_unitario']; }
			if ($t === null && $c !== null && $l['valor_unitario'] !== null) { $t = $c * $l['valor_unitario'] - (float)$l['descuento']; }
			$imp[$i] = $l['afectacion'] === 'gratuito' ? 0.0 : (float)$t;
			if ($t === null && $l['afectacion'] !== 'gratuito') { $av[] = array('tipo' => 'error', 'texto' => 'No se leyó el importe de "' . $l['descripcion'] . '"'); }
		}
		$S = array_sum($imp);
		$igvT = (float)$T['igv']; $iscT = (float)$T['isc']; $icbT = (float)$T['icbper'];
		$total = $T['total'] !== null ? (float)$T['total'] : null;
		$gravIdx = array_keys(array_filter($L, function ($l) { return $l['afectacion'] === 'gravado'; }));

		// 2) ¿las lineas vienen con o sin IGV?
		$incluyen = true;
		if ($total !== null) {
			if (abs($S - $total) <= $tol($total)) { $incluyen = true; }
			elseif (abs($S + $igvT + $iscT + $icbT - $total) <= $tol($total)) { $incluyen = false; }
			elseif ($S > 0 && abs($S - $total) > $tol($total)) {
				// puede ser descuento global u otros cargos: se reparte abajo si la diferencia es chica
				$incluyen = abs($S - $total) <= abs($S + $igvT - $total);
			}
		}

		// 3) IGV por linea (el impreso, o el total repartido entre lineas gravadas)
		$igvL = array();
		$sumIgvLinea = 0; $baseGrav = 0;
		foreach ($L as $i => $l) {
			if ($l['igv'] !== null) { $sumIgvLinea += $l['igv']; }
			if ($l['afectacion'] === 'gravado' && $l['igv'] === null) { $baseGrav += $imp[$i]; }
		}
		$igvRestante = max(0, $igvT - $sumIgvLinea);
		$con = array();
		foreach ($L as $i => $l) {
			$igv = $l['igv'] !== null ? $l['igv'] : (($l['afectacion'] === 'gravado' && $baseGrav > 0) ? $igvRestante * $imp[$i] / $baseGrav : 0);
			$extra = 0;
			if (!$incluyen) {
				// lineas sin impuestos: se suman IGV y, si no vinieron por linea, ISC/ICBPER repartidos
				$extra = $igv;
				$extra += $l['isc'] !== null ? $l['isc'] : 0;
				$extra += $l['icbper'] !== null ? $l['icbper'] : 0;
			}
			$igvL[$i] = $igv;
			$con[$i] = $imp[$i] + $extra;
		}
		if (!$incluyen) {
			$iscSinLinea = $iscT - array_sum(array_map(function ($l) { return (float)$l['isc']; }, $L));
			$icbSinLinea = $icbT - array_sum(array_map(function ($l) { return (float)$l['icbper']; }, $L));
			$baseTodas = array_sum($imp);
			foreach ($L as $i => $l) {
				if ($baseTodas <= 0) { break; }
				if ($iscSinLinea > 0.009 && $l['isc'] === null) { $con[$i] += $iscSinLinea * $imp[$i] / $baseTodas; }
				if ($icbSinLinea > 0.009 && $l['icbper'] === null) { $con[$i] += $icbSinLinea * $imp[$i] / $baseTodas; }
			}
		}

		// 4) cuadre con el total: diferencia chica (descuento global / otros cargos) se reparte; grande, se avisa
		$sumCon = array_sum($con);
		$cuadra = null;
		if ($total !== null && $sumCon > 0) {
			$dif = $total - $sumCon;
			$explicable = abs($dif - ((float)$T['otros_cargos'] - (float)$T['descuentos'])) <= $tol($total) || abs($dif) <= $total * 0.02;
			if (abs($dif) <= 0.05) { $cuadra = true; }
			elseif ($explicable) {
				$k = $total / $sumCon;
				foreach ($con as $i => $v) { $con[$i] = $v * $k; $igvL[$i] = $igvL[$i] * $k; }
				$cuadra = true;
				$av[] = array('tipo' => 'aviso', 'texto' => 'Se repartió entre los productos un ' . ($dif < 0 ? 'descuento' : 'cargo') . ' global de ' . number_format(abs($dif), 2));
			} else {
				$cuadra = false;
				$av[] = array('tipo' => 'error', 'texto' => 'Los productos suman ' . number_format($sumCon, 2) . ' y la factura dice ' . number_format($total, 2) . ': revisa cantidades y precios.');
			}
		} elseif ($total === null) {
			$av[] = array('tipo' => 'aviso', 'texto' => 'No se leyó el total de la factura: revisa los montos.');
		}

		// 5) IGV razonable (18 % o 10 %) sobre lo gravado
		$baseG = $T['op_gravadas'] !== null ? (float)$T['op_gravadas'] : null;
		if ($igvT > 0 && $baseG > 0) {
			$tasa = $igvT * 100 / $baseG;
			if (abs($tasa - 18) > 0.6 && abs($tasa - 10) > 0.6 && abs($tasa - 10.5) > 0.6) {
				$av[] = array('tipo' => 'aviso', 'texto' => 'El IGV leído es ' . number_format($tasa, 1) . ' % de lo gravado: revísalo.');
			}
		}

		// 6) percepcion repartida (solo cuenta para quien NO recupera IGV)
		$perc = (float)$T['percepcion'];
		$sumConF = array_sum($con);
		$out = array();
		foreach ($L as $i => $l) {
			$c = $l['cantidad'] !== null && $l['cantidad'] > 0 ? $l['cantidad'] : 1;
			$percL = ($perc > 0 && $sumConF > 0) ? $perc * $con[$i] / $sumConF : 0;
			$lineaCon = $l['afectacion'] === 'gratuito' ? 0 : $con[$i] + $percL;
			$lineaSin = $l['afectacion'] === 'gratuito' ? 0 : max(0, $con[$i] - $igvL[$i]);
			$out[] = array_merge($l, array(
				'total_con' => round($lineaCon, 4), 'total_sin' => round($lineaSin, 4),
				'costo_con' => round($lineaCon / $c, 6), 'costo_sin' => round($lineaSin / $c, 6)
			));
		}

		// 7) otros controles
		$ruc = $f['emisor']['ruc'];
		if ($ruc !== null && strlen($ruc) !== 11 && strlen($ruc) !== 8) { $av[] = array('tipo' => 'aviso', 'texto' => 'El RUC leído (' . $ruc . ') no tiene 11 dígitos.'); }
		if ($f['comprobante']['fecha'] !== null && $f['comprobante']['fecha'] > date('Y-m-d')) { $av[] = array('tipo' => 'aviso', 'texto' => 'La fecha leída es futura: revísala.'); }
		if ($f['comprobante']['moneda'] === 'USD') { $av[] = array('tipo' => 'aviso', 'texto' => 'La factura está en dólares: confirma el tipo de cambio.'); }
		if ($f['confianza'] === 'baja') { $av[] = array('tipo' => 'aviso', 'texto' => 'La imagen se leyó con dificultad: revisa todo con cuidado.'); }
		if (!$L) { $av[] = array('tipo' => 'error', 'texto' => 'No se encontraron productos en el comprobante.'); }

		$resumen = array(
			'lineas_con_igv' => $incluyen, 'cuadra' => $cuadra,
			'total' => $total, 'total_a_pagar' => $T['total_a_pagar'] !== null ? (float)$T['total_a_pagar'] : ($total !== null ? $total + $perc : null),
			'igv' => $igvT, 'isc' => $iscT, 'icbper' => $icbT, 'percepcion' => $perc,
			'descuentos' => (float)$T['descuentos'], 'otros_cargos' => (float)$T['otros_cargos'],
			'op_gravadas' => $T['op_gravadas'], 'op_exoneradas' => $T['op_exoneradas'], 'op_inafectas' => $T['op_inafectas'], 'op_gratuitas' => $T['op_gratuitas']
		);
		return array($out, $av, $resumen);
	}

	// Parecido por PALABRAS (no por letras): 'POLLO ENTERO FRESCO' ~ 'POLLOS' (una empieza con la otra).
	// Devuelve 0..1 (coeficiente de Dice sobre palabras de 3+ letras).
	function facturaParecido($a, $b) {
		$pa = array_values(array_filter(explode(' ', $a), function ($w) { return strlen($w) >= 3; }));
		$pb = array_values(array_filter(explode(' ', $b), function ($w) { return strlen($w) >= 3; }));
		if (!$pa || !$pb) { return 0; }
		$usadas = array(); $m = 0;
		foreach ($pa as $x) {
			foreach ($pb as $j => $y) {
				if (isset($usadas[$j])) { continue; }
				$min = min(strlen($x), strlen($y));
				if ($x === $y || ($min >= 4 && (strpos($x, $y) === 0 || strpos($y, $x) === 0))) { $usadas[$j] = 1; $m++; break; }
			}
		}
		return 2 * $m / (count($pa) + count($pb));
	}

	// Texto de la factura para relacionarlo con un producto: mayusculas, sin tildes, espacios simples
	function facturaTextoNormal($s) {
		$s = mb_strtoupper(trim((string)$s), 'UTF-8');
		$s = strtr($s, array('Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'));
		$s = preg_replace('/[^A-Z0-9%\/\.\-x ]/u', ' ', $s);
		return mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, 200, 'UTF-8');
	}

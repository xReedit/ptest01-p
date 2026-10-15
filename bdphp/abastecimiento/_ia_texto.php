<?php
/**
 * Redaccion de texto con IA para Logistica (resumen del dia). Reutiliza el proveedor configurado para la lectura de
 * facturas (private/compras_secrets.php: FACTURA_IA_PROVEEDOR / MODELO / API_KEY; claude | openai | gemini) y su cliente
 * HTTP. Si no esta configurado o falla, devuelve null y el que llama usa su plantilla: la IA nunca bloquea nada.
 * La IA SOLO redacta: los numeros los calcula el servidor y van en el prompt; no decide cantidades.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
require_once __DIR__ . '/../factura_ia.php';

/** Texto plano o null. $max_tokens chico: es un mensaje de WhatsApp. */
function ia_redactar($instrucciones, $datos, $max_tokens = 700) {
    try {
        $c = facturaIaConfig();
    } catch (FacturaIaError $e) {
        return null;
    }
    $prompt = $instrucciones . "\n\nDATOS (JSON, no inventes nada que no esté aquí):\n" . json_encode($datos, JSON_UNESCAPED_UNICODE);
    $t = min(30, $c['timeout']);
    try {
        if ($c['proveedor'] === 'claude') {
            $j = facturaIaHttp('https://api.anthropic.com/v1/messages',
                array('Content-Type: application/json', 'x-api-key: ' . $c['clave'], 'anthropic-version: 2023-06-01'),
                array('model' => $c['modelo'], 'max_tokens' => $max_tokens, 'messages' => array(array('role' => 'user', 'content' => $prompt))), $t);
            foreach ((isset($j['content']) ? $j['content'] : array()) as $b) { if (isset($b['type']) && $b['type'] === 'text') { return trim($b['text']); } }
        } elseif ($c['proveedor'] === 'openai') {
            $j = facturaIaHttp('https://api.openai.com/v1/chat/completions',
                array('Content-Type: application/json', 'Authorization: Bearer ' . $c['clave']),
                array('model' => $c['modelo'], 'max_completion_tokens' => max(2000, $max_tokens), 'messages' => array(array('role' => 'user', 'content' => $prompt))), $t);
            if (!empty($j['choices'][0]['message']['content'])) { return trim($j['choices'][0]['message']['content']); }
        } elseif ($c['proveedor'] === 'gemini') {
            $j = facturaIaHttp('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($c['modelo']) . ':generateContent',
                array('Content-Type: application/json', 'x-goog-api-key: ' . $c['clave']),
                array('contents' => array(array('role' => 'user', 'parts' => array(array('text' => $prompt)))),
                      'generationConfig' => array('maxOutputTokens' => max(2000, $max_tokens))), $t);
            if (!empty($j['candidates'][0]['content']['parts'][0]['text'])) { return trim($j['candidates'][0]['content']['parts'][0]['text']); }
        }
    } catch (Throwable $e) {
        error_log('logistica ia_redactar: ' . $e->getMessage());
    }
    return null;
}

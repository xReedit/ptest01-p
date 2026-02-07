<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar configuración
require_once('ai_config.php');

// Leer datos del request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['prompt']) || empty($input['prompt'])) {
    echo json_encode([
        'success' => false,
        'error' => 'El prompt es requerido'
    ]);
    exit;
}

$prompt = $input['prompt'];
$numImages = isset($input['num_images']) ? intval($input['num_images']) : 3;

// Validar que no exceda el límite
if ($numImages > 4) {
    $numImages = 4;
}

try {
    // Seleccionar el proveedor de IA configurado
    $provider = AI_PROVIDER;
    
    switch ($provider) {
        case 'replicate':
            $images = generateWithReplicate($prompt, $numImages);
            break;
        case 'stability':
            $images = generateWithStability($prompt, $numImages);
            break;
        case 'openai':
            $images = generateWithOpenAI($prompt, $numImages);
            break;
        case 'pollinations':
            // Opción gratuita
            $images = generateWithPollinations($prompt, $numImages);
            break;
        default:
            throw new Exception('Proveedor de IA no configurado');
    }
    
    echo json_encode([
        'success' => true,
        'images' => $images,
        'provider' => $provider
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generar imágenes con Pollinations.ai (GRATIS)
 * No requiere API key
 */
function generateWithPollinations($prompt, $numImages) {
    $images = [];
    $encodedPrompt = urlencode($prompt);
    
    for ($i = 0; $i < $numImages; $i++) {
        // Agregar seed diferente para cada imagen
        $seed = rand(1, 999999);
        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?seed={$seed}&width=800&height=600&nologo=true";
        $images[] = $url;
    }
    
    return $images;
}

/**
 * Generar imágenes con Replicate
 */
function generateWithReplicate($prompt, $numImages) {
    $apiKey = REPLICATE_API_KEY;
    
    if (empty($apiKey)) {
        throw new Exception('API key de Replicate no configurada');
    }
    
    $images = [];
    
    for ($i = 0; $i < $numImages; $i++) {
        $ch = curl_init();
        
        $data = [
            'version' => 'stability-ai/sdxl:39ed52f2a78e934b3ba6e2a89f5b1c712de7dfea535525255b1aa35c5565e08b',
            'input' => [
                'prompt' => $prompt,
                'num_outputs' => 1,
                'width' => 800,
                'height' => 600
            ]
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.replicate.com/v1/predictions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception('Error al generar imagen con Replicate');
        }
        
        $result = json_decode($response, true);
        
        // Esperar a que se complete la generación
        $predictionUrl = $result['urls']['get'];
        $imageUrl = waitForReplicateCompletion($predictionUrl, $apiKey);
        
        if ($imageUrl) {
            $images[] = $imageUrl;
        }
    }
    
    return $images;
}

function waitForReplicateCompletion($url, $apiKey, $maxAttempts = 30) {
    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep(2);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiKey
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result['status'] === 'succeeded' && isset($result['output'][0])) {
            return $result['output'][0];
        }
        
        if ($result['status'] === 'failed') {
            throw new Exception('La generación de imagen falló');
        }
    }
    
    throw new Exception('Timeout esperando la generación de imagen');
}

/**
 * Generar imágenes con Stability AI
 */
function generateWithStability($prompt, $numImages) {
    $apiKey = STABILITY_API_KEY;
    
    if (empty($apiKey)) {
        throw new Exception('API key de Stability AI no configurada');
    }
    
    $images = [];
    
    $ch = curl_init();
    
    $data = [
        'text_prompts' => [
            ['text' => $prompt, 'weight' => 1]
        ],
        'cfg_scale' => 7,
        'height' => 600,
        'width' => 800,
        'samples' => $numImages,
        'steps' => 30
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error al generar imagen con Stability AI');
    }
    
    $result = json_decode($response, true);
    
    foreach ($result['artifacts'] as $artifact) {
        // Guardar imagen base64 como archivo temporal
        $imageData = base64_decode($artifact['base64']);
        $filename = 'ai_generated_' . uniqid() . '.png';
        $filepath = '../file/ai_images/' . $filename;
        
        // Crear directorio si no existe
        if (!is_dir('../file/ai_images/')) {
            mkdir('../file/ai_images/', 0777, true);
        }
        
        file_put_contents($filepath, $imageData);
        $images[] = '../../file/ai_images/' . $filename;
    }
    
    return $images;
}

/**
 * Generar imágenes con OpenAI DALL-E
 */
function generateWithOpenAI($prompt, $numImages) {
    $apiKey = OPENAI_API_KEY;
    
    if (empty($apiKey)) {
        throw new Exception('API key de OpenAI no configurada');
    }
    
    $images = [];
    
    $ch = curl_init();
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => min($numImages, 1), // DALL-E 3 solo permite 1 imagen por request
        'size' => '1024x1024',
        'quality' => 'standard'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/images/generations',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error al generar imagen con OpenAI');
    }
    
    $result = json_decode($response, true);
    
    foreach ($result['data'] as $image) {
        $images[] = $image['url'];
    }
    
    // Si se pidieron más imágenes, hacer múltiples requests
    for ($i = 1; $i < $numImages; $i++) {
        // Hacer otro request (esto incrementa el costo)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/images/generations',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (isset($result['data'][0])) {
            $images[] = $result['data'][0]['url'];
        }
    }
    
    return $images;
}

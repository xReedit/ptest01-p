<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar configuración
require_once('pexels_config.php');

// Leer datos del request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query']) || empty($input['query'])) {
    echo json_encode([
        'success' => false,
        'error' => 'El término de búsqueda es requerido'
    ]);
    exit;
}

$query = $input['query'];
$orientation = isset($input['orientation']) ? $input['orientation'] : '';
$per_page = isset($input['per_page']) ? intval($input['per_page']) : 16;

// 8 imágenes de cada fuente
$pexels_count = 8;
$unsplash_count = 8;

$allPhotos = [];
$errors = [];

// ============================================
// BUSCAR EN PEXELS
// ============================================
try {
    if (!empty(PEXELS_API_KEY)) {
        $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . $pexels_count;
        
        if (!empty($orientation)) {
            $url .= '&orientation=' . $orientation;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . PEXELS_API_KEY
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['photos']) && !empty($data['photos'])) {
                foreach ($data['photos'] as $photo) {
                    $allPhotos[] = [
                        'id' => 'pexels_' . $photo['id'],
                        'photographer' => $photo['photographer'],
                        'photographer_url' => $photo['photographer_url'],
                        'alt' => $photo['alt'] ?? '',
                        'src' => [
                            'original' => $photo['src']['original'],
                            'large' => $photo['src']['large'],
                            'medium' => $photo['src']['medium'],
                            'small' => $photo['src']['small']
                        ],
                        'url' => $photo['url'],
                        'source' => 'Pexels'
                    ];
                }
            }
        }
    }
} catch (Exception $e) {
    $errors[] = 'Pexels: ' . $e->getMessage();
}

// ============================================
// BUSCAR EN UNSPLASH
// ============================================
try {
    if (!empty(UNSPLASH_ACCESS_KEY)) {
        $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($query) . '&per_page=' . $unsplash_count;
        
        if (!empty($orientation)) {
            $url .= '&orientation=' . $orientation;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['results']) && !empty($data['results'])) {
                foreach ($data['results'] as $photo) {
                    $allPhotos[] = [
                        'id' => 'unsplash_' . $photo['id'],
                        'photographer' => $photo['user']['name'],
                        'photographer_url' => $photo['user']['links']['html'],
                        'alt' => $photo['alt_description'] ?? $photo['description'] ?? '',
                        'src' => [
                            'original' => $photo['urls']['raw'],
                            'large' => $photo['urls']['regular'],
                            'medium' => $photo['urls']['small'],
                            'small' => $photo['urls']['thumb']
                        ],
                        'url' => $photo['links']['html'],
                        'source' => 'Unsplash'
                    ];
                }
            }
        }
    }
} catch (Exception $e) {
    $errors[] = 'Unsplash: ' . $e->getMessage();
}

// ============================================
// RESPUESTA
// ============================================
if (empty($allPhotos)) {
    echo json_encode([
        'success' => false,
        'error' => 'No se encontraron imágenes en ninguna fuente',
        'details' => $errors
    ]);
} else {
    // Mezclar resultados aleatoriamente para variedad
    shuffle($allPhotos);
    
    echo json_encode([
        'success' => true,
        'photos' => $allPhotos,
        'total_results' => count($allPhotos),
        'sources' => [
            'pexels' => !empty(PEXELS_API_KEY),
            'unsplash' => !empty(UNSPLASH_ACCESS_KEY)
        ]
    ]);
}

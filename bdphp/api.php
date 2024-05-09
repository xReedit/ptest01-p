<?php

session_start();
//header("Cache-Control: no-cache,no-store");
// header("Access-Control-Allow-Origin: *");}
header("Access-Control-Allow-Origin: http://127.0.0.1");
header('Content-Type: application/json;charset=utf-8');
header('content-type: text/html; charset: utf-8');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

include "ManejoBD.php";
$bd=new xManejoBD("restobar");

date_default_timezone_set('America/Lima');

$g_ido = isset($_SESSION['ido']) ? $_SESSION['ido'] : 0; 
$g_idsede = isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0;
$g_us = isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0;


$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];


# demo
$URL_API_RESTOBAR = 'http://192.168.1.47:20223/api-restobar';

#produccion
// $URL_API_RESTOBAR = 'https://papaya.com.pe/api-restobar';

$routes = [
    'GET' => [
        'get-comprobante' => function($params) use ($URL_API_RESTOBAR) {
            list($id) = $params;                        
            $url = $URL_API_RESTOBAR . '/reimpresion/reimprimir-comprobante/' . $id;            
            $response = curl($url, 'GET');
            echo $response;            
        },
        'search-comprobante' => function($params) {
            global $bd;
            list($id) = $params;                        
                         
            $bd->prepare("SELECT * FROM ce WHERE idce = ?");
            $bd->execute([$id]);
            $response = $bd->fetchAll();
            $bd->commit();
            echo json_encode(array('success' => true, 'data' => $response));

        },
        'get-hours-clouse' => function($params) {
            global $bd;
            global $g_idsede;            
                         
            $bd->prepare("SELECT hora_cierre_dia FROM sede_opciones WHERE idsede = ?");
            $bd->execute([$g_idsede]);
            $response = $bd->fetchAll();
            $bd->commit();
            echo json_encode(array('success' => true, 'data' => $response, 'g_idsede' => $g_idsede));

        },
    ],
    'POST' => [
        // Define POST routes in the same way
    ],
    // Add more methods as needed
];


$route = $_GET['route'];
$params = explode('/', $route); // remove query string
$route = $params[0];

//remove params[0]
array_shift($params);


if (isset($routes[$method][$route])) {
    $routes[$method][$route]($params);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'no se encontró la ruta'.$route]);
}


// funcion CURL
function curl($url, $method, $data = null) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    // return $response;
    if ($err) {
        echo json_encode(['success' => false, 'error' => 'cURL Error #:' . $err]);        
    } else {
        echo $response;
    }

}
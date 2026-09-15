<?php

require_once __DIR__ . '/SecurityGuard.php';
SecurityGuard::verificarAcceso(true, ['get-version-changelog']);
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

// ponytail: estos endpoints solo LEEN la sesion. Liberamos el lock de $_SESSION
// aqui para que una llamada externa lenta (curl a papaya.com.pe) no bloquee al
// resto de requests de la misma sesion (sintoma: -1112/-104 quedaban pending y
// el POS no cargaba). Si el servidor no alcanza el host externo, ahora solo se
// retrasa este endpoint, no toda la pagina.
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }


$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];


# demo
// $URL_API_RESTOBAR = 'http://192.168.1.47:20223/api-restobar';
// $URL_API_BLOG = 'http://192.168.1.47:30000/api-blog';

#produccion
$URL_API_RESTOBAR = 'https://papaya.com.pe/api-restobar';
$URL_API_BLOG='https://papaya.com.pe/api-blog';

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
            echo json_encode(array('success' => true, 'data' => $response));

        },
        'get-hours-clouse' => function($params) {
            global $bd;
            global $g_idsede;            
                         
            $bd->prepare("SELECT hora_cierre_dia FROM sede_opciones WHERE idsede = ?");
            $bd->execute([$g_idsede]);
            $response = $bd->fetchAll();
            echo json_encode(array('success' => true, 'data' => $response, 'g_idsede' => $g_idsede));

        },
        // Resumen de cierres de caja de un turno, para "Registro de Pagos".
        // Sirve para detectar el doble cierre: un cajero puede cerrar las primeras ventas,
        // quedarse con ese efectivo y cerrar otra vez con el resto; cada cierre cuadra por
        // separado y hasta ahora el dueno no veia cuantos hubo.
        // ?route=get-cierres-turno&fecha=dd/mm/yyyy
        'get-cierres-turno' => function($params) {
            global $bd;
            global $g_idsede;

            try {
            $fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
            $d = DateTime::createFromFormat('d/m/Y', $fecha);
            if (!$d) { $d = new DateTime(); }
            $d->setTime(0, 0, 0);

            // El turno va de "hora de cierre" a "hora de cierre" del dia siguiente: un local que
            // cierra a las 03:00 tiene la madrugada del dia siguiente dentro del MISMO turno.
            // Misma frontera que usa procedure_registro_pagos_20001 para listar los registros.
            $bd->prepare("SELECT hora_cierre_dia FROM sede_opciones WHERE idsede = ?");
            $bd->execute([$g_idsede]);
            $opc = $bd->fetchAll();
            $hora = (isset($opc[0]) && trim($opc[0]['hora_cierre_dia']) !== '') ? trim($opc[0]['hora_cierre_dia']) : '00:00';
            if (strlen($hora) === 5) { $hora .= ':00'; }

            $ini = $d->format('Y-m-d') . ' ' . $hora;
            $fin = $d->modify('+1 day')->format('Y-m-d') . ' ' . $hora;

            // La columna que enlaza registro_pago con su cierre la crea la migracion 030. Si todavia
            // no se aplico, $bd->prepare() lanzaria Exception y la pantalla se caia con un 500; se
            // detecta antes y se devuelve el resumen sin el desglose por registro.
            $bd->prepare("SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro_pago'
                             AND COLUMN_NAME = 'idusuario_bitacora_cierre'");
            $bd->execute();
            $col = $bd->fetchAll();
            $hayVinculo = isset($col[0]) && (int)$col[0]['n'] > 0;

            // Un cierre = una fila de usuario_bitacora_cierre. cierre=1 es un cierre consumado;
            // cierre=0 es un cuadre que el cajero empezo y abandono (tambien vale la pena verlo).
            // registros/importe salen del vinculo que graba procedure_run_cierre (migracion 030):
            // los cierres anteriores a esa migracion devuelven 0 porque nunca se guardo el vinculo.
            $sqlTotales = $hayVinculo
                ? "(SELECT COUNT(*) FROM registro_pago rp
                      WHERE rp.idusuario_bitacora_cierre = b.idusuario_bitacora_cierre
                        AND rp.estado IN (0,1)) AS registros,
                   (SELECT COALESCE(SUM(rp.total), 0) FROM registro_pago rp
                      WHERE rp.idusuario_bitacora_cierre = b.idusuario_bitacora_cierre
                        AND rp.estado IN (0,1)) AS importe"
                : "0 AS registros, 0 AS importe";

            $bd->prepare(
                "SELECT b.idusuario_bitacora_cierre AS id, b.idusuario, u.nombres AS usuario,
                        b.fecha_hora, DATE_FORMAT(b.fecha_hora, '%H:%i') AS hora,
                        b.cierre, b.numintentos,
                        b.total_ingresos, b.total_egresos, b.total_efectivo,
                        IFNULL(IF(b.idusuario_supervisor > 0, s.nombres, ''), '') AS supervisor,
                        " . $sqlTotales . "
                   FROM usuario_bitacora_cierre b
                   INNER JOIN usuario u ON u.idusuario = b.idusuario
                   LEFT JOIN usuario s ON s.idusuario = b.idusuario_supervisor
                  WHERE b.idsede = ? AND b.estado = 0
                    AND b.fecha_hora >= ? AND b.fecha_hora < ?
                  ORDER BY b.fecha_hora ASC");
            $bd->execute([$g_idsede, $ini, $fin]);
            $filas = $bd->fetchAll();

            // Mapa idregistro_pago -> cierre, para etiquetar cada fila de la tabla.
            // Se devuelve aparte en vez de agregar la columna a procedure_registro_pagos_20001:
            // ese SP son 13 KB y reescribirlo entero para sumar un campo no compensa el riesgo.
            // La ventana se abre 3 dias hacia atras porque al ver "hoy" el SP tambien lista
            // registros de dias anteriores que quedaron sin cerrar.
            $bd->prepare(
                "SELECT idregistro_pago, " . ($hayVinculo ? "idusuario_bitacora_cierre" : "NULL AS idusuario_bitacora_cierre") . ", fecha_cierre
                   FROM registro_pago
                  WHERE idsede = ? AND cierre = 1
                    AND fecha_hora >= DATE_SUB(?, INTERVAL 3 DAY) AND fecha_hora < ?");
            $bd->execute([$g_idsede, $ini, $fin]);
            $mapa = array();
            foreach ($bd->fetchAll() as $r) {
                $mapa[(string)$r['idregistro_pago']] = array(
                    'c' => $r['idusuario_bitacora_cierre'] === null ? null : (int)$r['idusuario_bitacora_cierre'],
                    'f' => $r['fecha_cierre']
                );
            }

            echo json_encode(array(
                'success'     => true,
                'data'        => $filas,
                'mapa'        => empty($mapa) ? new stdClass() : $mapa,
                'hay_vinculo' => $hayVinculo,
                'hora_cierre' => substr($hora, 0, 5),
                'desde'       => $ini,
                'hasta'       => $fin
            ));
            } catch (Exception $e) {
                // Es un panel informativo: si falla, no debe tumbar "Registro de Pagos" con un 500.
                echo json_encode(array('success' => false, 'data' => array(), 'error' => $e->getMessage()));
            }
        },
        'get-check-alert-service' => function($params) use ($URL_API_RESTOBAR) {
            global $bd;
            global $g_idsede;

            $url = $URL_API_RESTOBAR . '/restobar/cobranza/advertencia/' . $g_idsede;
            $response = curl($url, 'GET');
            echo $response;

        },
        'get-version-changelog' => function($params) use ($URL_API_BLOG) {
            global $bd;
            global $g_us;

            // $url = $URL_API_BLOG . '/changelog/last-version';
            // $response = curl($url, 'GET', null, false);
            // $last_version = json_decode($response, true);

            // ponytail: changelog LOCAL (el blog quedo desactivado arriba). Una entrada por novedad, id creciente
            // (usuario.last_version_changelog es tinyint: ids 1..127; los usuarios hoy estan en 0, 1 o 3).
            // route = null => el dialogo solo anuncia (boton "Entendido"), sin enviar al blog.
            $CHANGELOG_LOCAL = [
                ['id' => 4, 'titulo' => 'Nueva función: Vincular Yape - Plin (WizPay)',
                 'descripcion' => 'Tu cajero confirma los pagos Yape y Plin al instante, aunque el dueño no esté en el local. WizPay detecta el pago en el celular Android del dueño y lo envía a la computadora de caja. Búscala en Operatividad > "Vincular Yape - Plin".',
                 'route' => null],
                ['id' => 5, 'titulo' => 'Nueva función: Inventario',
                 'descripcion' => 'Cuenta tu stock real y cuadra las diferencias sin planillas. Crea un conteo por almacén (o de porciones), reparte las secciones entre varios encargados, ciérralo y un administrador aprueba: las diferencias se aplican solas al stock y quedan en el kardex. Incluye stock valorizado, kardex por producto y tendencia de mermas. Búscala en Logistica > "Inventario".',
                 'route' => null],
            ];

            $sql_usuario = "SELECT last_version_changelog as d1 FROM usuario WHERE idusuario = $g_us";
            $version_usuario = (int)$bd->xDevolverUnDato($sql_usuario);

            $pendiente = null;
            foreach ($CHANGELOG_LOCAL as $c) {
                if ($c['id'] > $version_usuario) { $pendiente = $c; } // se queda con la ultima (mayor id)
            }

            if (!$g_us || $pendiente === null) {
                echo json_encode(['success' => false, 'data' => $version_usuario]);
                return;
            }

            $bd->xConsulta_NoReturn("UPDATE usuario SET last_version_changelog = " . (int)$pendiente['id'] . " WHERE idusuario = " . (int)$g_us);
            echo json_encode(['success' => true, 'data' => $pendiente]);

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
function curl($url, $method, $data = null, $return_echo = true) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_TIMEOUT, 7); // Cancelar si tarda más de 7 segundos
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3); // tope al connect/DNS
    curl_setopt($curl, CURLOPT_NOSIGNAL, 1); // timeouts fiables bajo Apache+mod_php (sin SIGALRM)
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    // return $response;
    if ($err) {
        if ($return_echo) {
            echo json_encode(['success' => false, 'error' => 'cURL Error #:' . $err]);        
        } else {
            return json_encode(['success' => false, 'error' => 'cURL Error #:' . $err]);        
        }
    } else {
        if ($return_echo) {
            echo $response;
        } else {
            return $response;
        }        
    }

}
<?php
/**
 * Funciones del Resumen del dia (las usan resumen.php y resumen_auto.php). Incluido despues de _logistica.php.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
require_once __DIR__ . '/_vencimientos.php';
require_once __DIR__ . '/_ia_texto.php';
require_once __DIR__ . '/_stock.php';

const DIAS_MINIMO = 3;          // minimo sugerido = lo que vende en 3 dias
const DIAS_DIFERENCIAS = 30;

function n1($bd, $sql, $p) { $r = log_filas($bd, $sql, $p); return $r ? (int)array_values($r[0])[0] : 0; }

function pendientes_hoy($bd, $org, $conVenc = true) {
    $o = (int)$org['idorg']; $c = (int)$org['idsede_central'];
    $sinConfirmar = log_filas($bd, "SELECT t.idtraslado, s.nombre AS sede, TIMESTAMPDIFF(HOUR, t.fecha_despacho, NOW()) AS horas
            FROM log_traslado t JOIN sede s ON s.idsede = t.idsede_destino
            WHERE t.idorg = ? AND t.estado = 'despachado' AND t.fecha_despacho < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY t.fecha_despacho", array($o));
    $venc = $conVenc ? por_vencer($bd, $org, null) : array();   // el diagrama lo trae de flujo_pesado (cache)
    return array(
        'pedidos' => n1($bd, "SELECT COUNT(*) FROM solicitud_producto WHERE idorg = ? AND idsede_destino = ? AND estado IN ('enviado','visto')", array($o, $c)),
        'por_comprar' => n1($bd, "SELECT COUNT(DISTINCT idproducto_central) FROM log_bandeja_linea WHERE idorg = ? AND decision = 'compra' AND estado = 'pendiente' AND idorden IS NULL", array($o)),
        'por_aprobar' => n1($bd, "SELECT COUNT(*) FROM orden_compra WHERE idorg = ? AND idsede = ? AND estado = 'por_aprobar'", array($o, $c)),
        'esperando' => n1($bd, "SELECT COUNT(*) FROM orden_compra WHERE idorg = ? AND idsede = ? AND estado IN ('aprobada','enviada','parcial')", array($o, $c)),
        'atrasadas' => n1($bd, "SELECT COUNT(*) FROM orden_compra WHERE idorg = ? AND idsede = ? AND estado IN ('enviada','parcial')
                AND IFNULL(fecha_envio, fecha) < DATE_SUB(NOW(), INTERVAL 3 DAY)", array($o, $c)),
        'por_despachar' => n1($bd, "SELECT COUNT(DISTINCT IF(decision = 'central', 0, idsede_origen), idsede) FROM log_bandeja_linea
                WHERE idorg = ? AND decision IN ('central','traslado','produccion') AND estado = 'pendiente' AND idtraslado IS NULL", array($o)),
        'sin_confirmar' => $sinConfirmar,
        'vencidos' => count(array_filter($venc, function ($v) { return $v['dias'] < 0; })),
        'vencen_7' => count(array_filter($venc, function ($v) { return $v['dias'] >= 0 && $v['dias'] <= 7; })),
        'se_pueden_salvar' => count(array_filter($venc, function ($v) { return $v['sugerencia'] !== null; })),
    );
}

function diferencias($bd, $org) {
    $o = (int)$org['idorg'];
    $d = DIAS_DIFERENCIAS;
    // envios recibidos en los ultimos 30 dias (indice ix_lt_org_estado + ix_lti_traslado)
    $rec = "t.idorg = ? AND t.estado IN ('recibido','recibido_con_diferencia') AND t.fecha_recibe >= DATE_SUB(NOW(), INTERVAL $d DAY)";
    return array(
        'por_sede' => log_filas($bd, "SELECT s.nombre, COUNT(DISTINCT t.idtraslado) AS envios,
                COUNT(DISTINCT IF(i.cantidad_recibida < i.cantidad_enviada, t.idtraslado, NULL)) AS con_faltante,
                SUM(i.cantidad_enviada - i.cantidad_recibida) AS faltante
                FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado JOIN sede s ON s.idsede = t.idsede_destino
                WHERE $rec GROUP BY t.idsede_destino HAVING con_faltante > 0 ORDER BY con_faltante DESC, faltante DESC LIMIT 10", array($o)),
        'por_chofer' => log_filas($bd, "SELECT IFNULL(c.nombre, 'Sin chofer') AS nombre, COUNT(DISTINCT t.idtraslado) AS envios,
                COUNT(DISTINCT IF(i.cantidad_recibida < i.cantidad_enviada, t.idtraslado, NULL)) AS con_faltante,
                SUM(i.cantidad_enviada - i.cantidad_recibida) AS faltante
                FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado
                LEFT JOIN log_ruta r ON r.idruta = t.idruta LEFT JOIN log_chofer c ON c.idchofer = r.idchofer
                WHERE $rec GROUP BY c.idchofer HAVING con_faltante > 0 ORDER BY con_faltante DESC LIMIT 10", array($o)),
        'por_producto' => log_filas($bd, "SELECT p.descripcion AS nombre, COUNT(*) AS veces, SUM(i.cantidad_enviada - i.cantidad_recibida) AS faltante,
                GROUP_CONCAT(DISTINCT i.motivo_diferencia SEPARATOR ', ') AS motivos
                FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado JOIN producto p ON p.idproducto = i.idproducto_central
                WHERE $rec AND i.cantidad_recibida < i.cantidad_enviada
                GROUP BY i.idproducto_central ORDER BY veces DESC, faltante DESC LIMIT 10", array($o)),
        'proveedores' => log_filas($bd, "SELECT pv.descripcion AS nombre, COUNT(DISTINCT oc.idorden) AS ordenes,
                COUNT(DISTINCT IF(i.cantidad_recibida < i.cantidad_pedida, oc.idorden, NULL)) AS incompletas
                FROM orden_compra oc JOIN orden_compra_item i ON i.idorden = oc.idorden JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
                WHERE oc.idorg = ? AND oc.idsede = ? AND oc.estado IN ('recibida','cerrada','parcial') AND oc.fecha >= DATE_SUB(NOW(), INTERVAL $d DAY)
                GROUP BY oc.idproveedor HAVING incompletas > 0 ORDER BY incompletas DESC LIMIT 10", array($o, (int)$org['idsede_central'])),
    );
}

/** Resumen en texto simple (plantilla) cuando no hay IA. */
function resumen_plantilla($h, $d, $empresa) {
    $l = array('*Resumen de logística · ' . $empresa . '*', '');
    $add = function ($n, $txt) use (&$l) { if ($n) { $l[] = '• ' . $txt; } };
    $add($h['pedidos'], $h['pedidos'] . ' pedido(s) de sedes por atender');
    $add($h['por_aprobar'], $h['por_aprobar'] . ' orden(es) de compra por aprobar');
    $add($h['por_comprar'], $h['por_comprar'] . ' producto(s) por comprar sin orden');
    $add($h['atrasadas'], $h['atrasadas'] . ' orden(es) enviadas hace más de 3 días sin llegar');
    $add($h['por_despachar'], $h['por_despachar'] . ' envío(s) a sedes por despachar');
    $add(count($h['sin_confirmar']), count($h['sin_confirmar']) . ' envío(s) sin confirmar hace más de 24 h: '
        . implode(', ', array_unique(array_column($h['sin_confirmar'], 'sede'))));
    $add($h['vencidos'], $h['vencidos'] . ' lote(s) vencidos para sacar de la venta');
    $add($h['vencen_7'], $h['vencen_7'] . ' lote(s) vencen en 7 días');
    if (count($l) === 2) { $l[] = 'Todo al día. 👍'; }
    if ($d['por_sede']) { $l[] = ''; $l[] = 'Faltantes en envíos (30 días): ' . implode(', ', array_map(function ($x) { return $x['nombre'] . ' ' . $x['con_faltante'] . ' de ' . $x['envios']; }, array_slice($d['por_sede'], 0, 3))); }
    if ($d['proveedores']) { $l[] = 'Proveedores que entregan incompleto: ' . implode(', ', array_map(function ($x) { return $x['nombre'] . ' (' . $x['incompletas'] . ' de ' . $x['ordenes'] . ')'; }, array_slice($d['proveedores'], 0, 3))); }
    return implode("\n", $l);
}

/** Texto del resumen del dia: redactado por la IA o, si no hay IA, plantilla. [texto, con_ia] */
function resumen_texto($bd, $org) {
    $h = pendientes_hoy($bd, $org); $d = diferencias($bd, $org);
    $emp = log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array((int)$org['idsede_central']))[0]['nombre'];
    // nombres claros y numeros limpios: la IA no debe adivinar que significa cada dato
    $fx = function ($filas, $campos) { return array_map(function ($f) use ($campos) {
        $o = array(); foreach ($campos as $k => $v) { $o[$v] = is_numeric($f[$k]) ? (float)$f[$k] : $f[$k]; } return $o; }, $filas); };
    $datos = array('empresa' => $emp, 'pendientes_hoy' => array(
        'envios_en_camino_sin_confirmar_mas_de_24_horas' => array_map(function ($x) { return $x['sede'] . ' (' . $x['horas'] . ' horas)'; }, $h['sin_confirmar']),
        'lotes_ya_vencidos' => $h['vencidos'], 'lotes_que_vencen_en_7_dias' => $h['vencen_7'],
        'ordenes_de_compra_enviadas_hace_mas_de_3_dias_sin_llegar' => $h['atrasadas'],
        'pedidos_de_sedes_por_atender' => $h['pedidos'], 'ordenes_de_compra_por_aprobar' => $h['por_aprobar'],
        'productos_por_comprar_sin_orden' => $h['por_comprar'], 'envios_a_sedes_por_despachar' => $h['por_despachar'],
        'ordenes_de_compra_esperando_mercaderia' => $h['esperando']),
        'diferencias_ultimos_30_dias' => array(
            'sedes_que_recibieron_con_faltantes' => $fx($d['por_sede'], array('nombre' => 'sede', 'con_faltante' => 'envios_con_faltante', 'envios' => 'envios_total', 'faltante' => 'unidades_faltantes')),
            'choferes_con_envios_incompletos' => $fx($d['por_chofer'], array('nombre' => 'chofer', 'con_faltante' => 'envios_con_faltante', 'envios' => 'envios_total', 'faltante' => 'unidades_faltantes')),
            'productos_que_llegan_mal' => $fx($d['por_producto'], array('nombre' => 'producto', 'veces' => 'veces', 'faltante' => 'unidades_faltantes', 'motivos' => 'motivos')),
            'proveedores_que_entregan_incompleto' => $fx($d['proveedores'], array('nombre' => 'proveedor', 'incompletas' => 'ordenes_incompletas', 'ordenes' => 'ordenes_total'))));
    $texto = ia_redactar("Eres el asistente de logística de una cadena de restaurantes en Perú. Escribe un mensaje de WhatsApp para la "
        . "administradora con el resumen del día. Reglas: español sencillo, máximo 12 líneas, empieza con *Resumen de logística*, "
        . "primero lo urgente (envíos sin confirmar, vencidos, órdenes atrasadas), usa viñetas •, menciona sedes/proveedores por "
        . "nombre, no inventes datos, si algo está en 0 o vacío no lo menciones ni comentes que falta, termina con una sola recomendación concreta.", $datos);
    return array($texto !== null ? $texto : resumen_plantilla($h, $d, $emp), $texto !== null);
}

/**
 * Manda el resumen al telefono_resumen por el WhatsApp de la sede central y anota el resultado.
 * Devuelve null si salio, o el error en texto simple.
 */
function resumen_enviar($bd, $org) {
    require_once __DIR__ . '/_whatsapp.php';
    $o = log_filas($bd, "SELECT telefono_resumen FROM org_logistica WHERE idorg = ?", array((int)$org['idorg']));
    $tel = $o ? $o[0]['telefono_resumen'] : null;
    if (!$tel) { $err = 'Falta el WhatsApp de quien recibe el resumen.'; }
    else {
        list($texto) = resumen_texto($bd, $org);
        // sale sin que nadie lo revise: sin links (los motivos de diferencia los escriben las sedes y van a la IA)
        $err = log_whatsapp($org['idorg'], $org['idsede_central'], $tel, preg_replace('#(https?://|www\.)\S+#i', '', $texto));
    }
    try {
        log_exec($bd, "UPDATE org_logistica SET resumen_resultado = CONCAT(DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), ' ', ?) WHERE idorg = ?",
            array($err === null ? 'Enviado' : mb_substr($err, 0, 180, 'UTF-8'), (int)$org['idorg']));
    } catch (Throwable $e) {
        error_log('logistica resumen_resultado: ' . $e->getMessage());
    }
    return $err;
}


/**
 * Valor (costo) de la mercaderia movida entre locales en los ultimos $dias, por origen -> destino: enviado, recibido,
 * faltante y en camino. Por indice ix_lt_org_estado + ix_lti_traslado. Costo: el guardado al despachar (o el actual).
 */
function valor_movido($bd, $org, $dias) {
    $cost = SQL_COSTO_ITEM;
    $filas = log_filas($bd, "SELECT t.idsede_origen, t.idsede_destino, so.nombre AS origen, sd.nombre AS destino, COUNT(DISTINCT t.idtraslado) AS envios,
            ROUND(SUM(i.cantidad_enviada * $cost), 2) AS enviado,
            ROUND(SUM(IF(t.estado = 'despachado', 0, IFNULL(i.cantidad_recibida, 0) * $cost)), 2) AS recibido,
            ROUND(SUM(IF(t.estado = 'despachado', 0, GREATEST(i.cantidad_enviada - IFNULL(i.cantidad_recibida, 0), 0) * $cost)), 2) AS faltante,
            ROUND(SUM(IF(t.estado = 'despachado', i.cantidad_enviada * $cost, 0)), 2) AS en_camino,
            SUM(IFNULL($cost, 0) <= 0) AS sin_costo
            FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado
            JOIN sede so ON so.idsede = t.idsede_origen JOIN sede sd ON sd.idsede = t.idsede_destino
            WHERE t.idorg = ? AND t.estado IN ('despachado', 'recibido', 'recibido_con_diferencia')
              AND t.fecha_despacho >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY t.idsede_origen, t.idsede_destino ORDER BY enviado DESC", array((int)$org['idorg'], (int)$dias));
    // por local: lo que salio y lo que entro
    $loc = array();
    foreach ($filas as $f) {
        foreach (array(array($f['idsede_origen'], $f['origen'], 'salio', $f['enviado']), array($f['idsede_destino'], $f['destino'], 'entro', $f['recibido'])) as $x) {
            if (!isset($loc[$x[0]])) { $loc[$x[0]] = array('local' => $x[1], 'salio' => 0, 'entro' => 0, 'falto' => 0); }
            $loc[$x[0]][$x[2]] += (float)$x[3];
        }
        $loc[$f['idsede_destino']]['falto'] += (float)$f['faltante'];
    }
    usort($loc, function ($a, $b) { return ($b['salio'] + $b['entro']) <=> ($a['salio'] + $a['entro']); });
    $sum = function ($k) use ($filas) { return round(array_sum(array_map(function ($f) use ($k) { return (float)$f[$k]; }, $filas)), 2); };
    return array('dias' => (int)$dias, 'rutas' => $filas, 'locales' => array_values($loc),
        'total' => array('enviado' => $sum('enviado'), 'recibido' => $sum('recibido'), 'faltante' => $sum('faltante'), 'en_camino' => $sum('en_camino'),
            'sin_costo' => (int)$sum('sin_costo')));
}

/**
 * Conteos por etapa para el diagrama de flujo del inicio (central). Una consulta por grupo, todas por indice:
 * solicitud_producto(idx_org_sede_destino), log_bandeja_linea, orden_compra(oc_org_sede_estado_IDX),
 * log_traslado(ix_lt_org_estado), compra(idx_compra_sede).
 */
// periodo del diagrama (solo para lo que YA paso: llego, recibido y dinero). Lista cerrada: nunca texto del usuario en el SQL.
const FLUJO_PERIODOS = array('hoy' => "CURDATE()", '7' => "CURDATE() - INTERVAL 6 DAY", '15' => "CURDATE() - INTERVAL 14 DAY",
    'mes' => "DATE_FORMAT(CURDATE(), '%Y-%m-01')");

/** Produccion para el diagrama: {activa, planta (nombre)}. */
function flujo_produccion($bd, $org) {
    require_once __DIR__ . '/_produccion.php';
    $pc = prod_cfg($bd, $org);
    $n = log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array($pc['idsede_planta']));
    return array('activa' => $pc['activa'], 'planta' => $n ? $n[0]['nombre'] : '');
}

const FLUJO_CACHE_SEG = 600;

/**
 * Lo caro del diagrama (stock parado, por comprar que otra sede tiene, almacen bajo, por vencer): recorre todos los
 * productos de todas las sedes. Se guarda por org en un archivo y se recalcula cada FLUJO_CACHE_SEG o antes si cambia
 * la bandeja (decidir, repartir, despachar, comprar), para no mostrar algo que ya se hizo.
 * ponytail: archivo en el tmp del servidor web; con varios servidores web pasar a una tabla.
 */
function flujo_pesado($bd, $org) {
    $o = (int)$org['idorg'];
    // firma por ix_lbl_org_decision (8 rangos exactos); COUNT(idtraslado) lee solo las lineas abiertas
    $f = log_filas($bd, "SELECT COUNT(*) AS n, COUNT(idtraslado) AS t, SUM(estado = 'en_proceso') AS e, IFNULL(MAX(id), 0) AS m
            FROM log_bandeja_linea WHERE idorg = ? AND decision IN ('central','traslado','compra','produccion')
            AND estado IN ('pendiente','en_proceso')", array($o))[0];
    $firma = implode('-', array_map('intval', $f));
    $arch = sys_get_temp_dir() . '/logistica_flujo_' . $o . '.json';
    $c = is_file($arch) ? json_decode((string)@file_get_contents($arch), true) : null;
    if (is_array($c) && isset($c['v']) && $c['firma'] === $firma && time() - (int)$c['t'] < FLUJO_CACHE_SEG) { return $c['v']; }
    $venc = por_vencer($bd, $org, null);
    $porComprar = array_map('intval', array_column(log_filas($bd, "SELECT DISTINCT idproducto_central FROM log_bandeja_linea
            WHERE idorg = ? AND decision = 'compra' AND estado = 'pendiente' AND idorden IS NULL LIMIT 500", array($o)), 'idproducto_central'));
    $parado = parado_org($bd, $org);   // stock parado en las sedes (solo lo que se vende)
    $v = array(
        'vencidos' => count(array_filter($venc, function ($x) { return $x['dias'] < 0; })),
        'vencen_7' => count(array_filter($venc, function ($x) { return $x['dias'] >= 0 && $x['dias'] <= 7; })),
        'otra_sede' => $porComprar ? count(stock_parado($bd, $org, $porComprar)) : 0,   // mejor traerlo que comprarlo
        'almacen_bajo' => count(array_filter(almacen_bajo($bd, $org), function ($x) { return $x['sugerido'] > 0; })),
        'parado' => array('productos' => count($parado), 'sedes' => count(array_unique(array_column($parado, 'idsede'))),
            'valor' => round(array_sum(array_column($parado, 'valor')), 2)),
    );
    // escritura atomica: otra pestana nunca lee un archivo a medias
    $tmp = $arch . '.' . getmypid();
    if (@file_put_contents($tmp, json_encode(array('t' => time(), 'firma' => $firma, 'v' => $v))) !== false && !@rename($tmp, $arch)) { @unlink($tmp); }
    return $v;
}

function flujo($bd, $org, $periodo = '7') {
    $o = (int)$org['idorg']; $c = (int)$org['idsede_central'];
    $per = array_key_exists($periodo, FLUJO_PERIODOS) ? $periodo : '7';
    $desde = FLUJO_PERIODOS[$per];
    $h = pendientes_hoy($bd, $org, false);
    $pz = flujo_pesado($bd, $org);
    $ped = array('enviado' => 0, 'visto' => 0);
    foreach (log_filas($bd, "SELECT estado, COUNT(*) AS n FROM solicitud_producto WHERE idorg = ? AND idsede_destino = ? AND estado IN ('enviado','visto') GROUP BY estado",
            array($o, $c)) as $f) { $ped[$f['estado']] = (int)$f['n']; }
    $dec = array('central' => 0, 'traslado' => 0, 'produccion' => 0);
    foreach (log_filas($bd, "SELECT decision, COUNT(DISTINCT idproducto_central) AS n FROM log_bandeja_linea
            WHERE idorg = ? AND decision IN ('central','traslado','produccion') AND estado = 'pendiente' AND idtraslado IS NULL GROUP BY decision", array($o)) as $f) {
        $dec[$f['decision']] = (int)$f['n'];
    }
    $oc = array('por_aprobar' => 0, 'aprobada' => 0, 'enviada' => 0, 'parcial' => 0);
    foreach (log_filas($bd, "SELECT estado, COUNT(*) AS n FROM orden_compra WHERE idorg = ? AND idsede = ? AND estado IN ('por_aprobar','aprobada','enviada','parcial') GROUP BY estado",
            array($o, $c)) as $f) { $oc[$f['estado']] = (int)$f['n']; }
    // en camino (todos) + recibidos del periodo; ix_lt_org_estado_recibe: no lee los recibidos viejos
    $tr = log_filas($bd, "SELECT SUM(estado = 'despachado') AS camino,
            SUM(estado IN ('recibido','recibido_con_diferencia')) AS recibidos, SUM(estado = 'recibido_con_diferencia') AS con_diferencia
            FROM log_traslado WHERE idorg = ? AND (estado = 'despachado'
              OR (estado IN ('recibido','recibido_con_diferencia') AND fecha_recibe >= $desde))", array($o))[0];
    // decidido "sale del almacen" pero el almacen PRINCIPAL de la central no alcanza (ej. esta en PRODUCCION)
    $falta = n1($bd, "SELECT COUNT(*) FROM (SELECT b.idproducto_central, SUM(b.cantidad) AS q FROM log_bandeja_linea b
            WHERE b.idorg = ? AND b.decision = 'central' AND b.estado = 'pendiente' AND b.idtraslado IS NULL GROUP BY b.idproducto_central) x
            WHERE x.q > IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = x.idproducto_central AND ps.idalmacen = ?), 0)",
        array($o, alm_principal($bd, $c)));
    // dinero: compras de la central en el periodo y deuda con proveedores (a credito sin pagar, como Cuentas por pagar)
    $compras = log_filas($bd, "SELECT COUNT(*) AS n, IFNULL(ROUND(SUM(total + 0), 2), 0) AS monto FROM compra
            WHERE idsede = ? AND estado = 0 AND idorg = ? AND fecha_hora >= $desde", array($c, $o))[0];
    $llego = (int)$compras['n'];
    $deuda = log_filas($bd, "SELECT COUNT(*) AS n, IFNULL(ROUND(SUM(a_pagar + 0), 2), 0) AS monto FROM compra
            WHERE idsede = ? AND estado = 0 AND idorg = ? AND idtipo_pago = 3 AND pagado = 0", array($c, $o))[0];   // 3 = credito
    $mov = log_filas($bd, "SELECT IFNULL(ROUND(SUM(i.cantidad_enviada * " . SQL_COSTO_ITEM . "), 2), 0) AS enviado,
            IFNULL(ROUND(SUM(IF(t.estado = 'despachado', 0, GREATEST(i.cantidad_enviada - IFNULL(i.cantidad_recibida, 0), 0) * " . SQL_COSTO_ITEM . ")), 2), 0) AS faltante
            FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado
            WHERE t.idorg = ? AND t.estado IN ('despachado', 'recibido', 'recibido_con_diferencia') AND t.fecha_despacho >= $desde", array($o))[0];   // ix_lt_org_estado (idorg, estado, fecha_despacho)
    return array(
        'pedidos_nuevos' => $ped['enviado'], 'pedidos_vistos' => $ped['visto'],
        'sale_central' => $dec['central'], 'traslado_sedes' => $dec['traslado'], 'por_producir' => $dec['produccion'], 'por_comprar' => $h['por_comprar'],
        'produccion' => flujo_produccion($bd, $org), 'almacen_bajo' => $pz['almacen_bajo'],
        // repartos sin pedido (Repartir / Stock parado / Por vencer) que esperan despacho
        'repartos' => n1($bd, "SELECT COUNT(DISTINCT idproducto_central) FROM log_bandeja_linea WHERE idorg = ? AND decision IN ('central','traslado')
                AND estado = 'pendiente' AND idtraslado IS NULL AND idsolicitud_producto = 0", array($o)),
        'falta_almacen' => $falta, 'otra_sede' => $pz['otra_sede'],
        'oc_por_aprobar' => $oc['por_aprobar'], 'oc_aprobada' => $oc['aprobada'], 'oc_enviada' => $oc['enviada'], 'oc_parcial' => $oc['parcial'],
        'oc_atrasadas' => $h['atrasadas'], 'compras_per' => $llego, 'periodo' => $per,
        'dinero' => array('deuda' => (float)$deuda['monto'], 'deuda_n' => (int)$deuda['n'], 'comprado' => (float)$compras['monto'],
            'enviado' => (float)$mov['enviado'], 'faltante' => (float)$mov['faltante']),
        'por_despachar' => $h['por_despachar'], 'en_camino' => (int)$tr['camino'], 'sin_confirmar' => count($h['sin_confirmar']),
        'recibidos_per' => (int)$tr['recibidos'], 'con_diferencia_per' => (int)$tr['con_diferencia'],
        'valor_en_camino' => (float)log_filas($bd, "SELECT IFNULL(ROUND(SUM(i.cantidad_enviada * " . SQL_COSTO_ITEM . "), 2), 0) AS v
                FROM log_traslado t JOIN log_traslado_item i ON i.idtraslado = t.idtraslado WHERE t.idorg = ? AND t.estado = 'despachado'", array($o))[0]['v'],
        'vencidos' => $pz['vencidos'], 'vencen_7' => $pz['vencen_7'], 'parado' => $pz['parado'],
    );
}

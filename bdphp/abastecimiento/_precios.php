<?php
/**
 * Precios de compra por proveedor (alerta de precio). Fuente: producto_historial_precio (lo escribe cada compra y
 * cada recepcion de la central). Por indice idx_php_costeo (idproducto, estado, id): nunca se recorre toda la tabla.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo

const PRECIO_ALERTA_PCT = 5;     // subida desde la que se avisa
const PRECIO_HIST_MAX = 20;      // compras que se miran por producto
const PRECIO_VIGENTE_DIAS = 180;  // un precio de otro proveedor mas viejo que esto ya no sirve para comparar

/** [idcentral => [ {idproveedor, proveedor, precio, fecha, idcompra}, ... ] ] lo mas nuevo primero. */
function historial_precios($bd, $ids) {
    $r = array();
    $ids = array_values(array_unique(array_map('intval', $ids)));
    // por producto: sus ultimas PRECIO_HIST_MAX compras por idx_php_costeo (idproducto, estado, id) hacia atras, nunca todo su historial
    $sub = "(SELECT idproducto_historial_precio, idproducto, idproveedor, precio, fecha, idcompra FROM producto_historial_precio
            WHERE idproducto = ? AND estado = 0 AND precio + 0 > 0 ORDER BY idproducto_historial_precio DESC LIMIT " . PRECIO_HIST_MAX . ")";
    foreach (array_chunk($ids, 200) as $parte) {
        foreach (log_filas($bd, "SELECT h.idproducto, IFNULL(h.idproveedor, 0) AS idproveedor, IFNULL(pv.descripcion, 'Sin proveedor') AS proveedor,
                h.precio + 0 AS precio, h.fecha, h.idcompra
                FROM (" . implode(' UNION ALL ', array_fill(0, count($parte), $sub)) . ") h LEFT JOIN proveedor pv ON pv.idproveedor = h.idproveedor
                ORDER BY h.idproducto, h.idproducto_historial_precio DESC", $parte) as $f) {
            $k = (int)$f['idproducto'];
            $r[$k][] = array('idproveedor' => (int)$f['idproveedor'], 'proveedor' => $f['proveedor'], 'precio' => (float)$f['precio'],
                'fecha' => $f['fecha'], 'idcompra' => $f['idcompra'] ? (int)$f['idcompra'] : null);
        }
    }
    return $r;
}

/**
 * Lectura de un historial: ultimo precio (y de quien), el anterior del MISMO proveedor y cuanto cambio, y el mejor
 * precio vigente de OTRO proveedor (el ultimo que cobro cada uno). $idproveedor: comparar contra ese proveedor.
 */
function lectura_precio($hist, $idproveedor = null) {
    if (!$hist) { return null; }
    $porProv = array();
    foreach ($hist as $h) { $porProv[$h['idproveedor']][] = $h; }
    $base = $idproveedor !== null && isset($porProv[$idproveedor]) ? $porProv[$idproveedor] : $porProv[$hist[0]['idproveedor']];
    $ult = $base[0];
    $ant = isset($base[1]) ? $base[1] : null;
    $mejor = null;
    $limite = strtotime('-' . PRECIO_VIGENTE_DIAS . ' days');
    foreach ($porProv as $idp => $lista) {
        if ($idp === $ult['idproveedor']) { continue; }
        $f = DateTime::createFromFormat('d/m/Y', substr((string)$lista[0]['fecha'], 0, 10));
        if (!$f || $f->getTimestamp() < $limite) { continue; }   // precio viejo: no se compara
        if (!$mejor || $lista[0]['precio'] < $mejor['precio']) { $mejor = $lista[0]; }
    }
    return array('ultimo' => $ult, 'anterior' => $ant,
        'cambio_pct' => $ant && $ant['precio'] > 0 ? round(($ult['precio'] - $ant['precio']) / $ant['precio'] * 100, 1) : null,
        'mejor_otro' => $mejor && $mejor['precio'] < $ult['precio'] ? $mejor : null, 'proveedores' => count($porProv));
}

const CALIF_DIAS = 90;   // periodo que se califica

/**
 * Como cumple cada proveedor de la central en los ultimos CALIF_DIAS dias: llego a tiempo (vs orden_compra.fecha_entrega,
 * migracion 026), llego completo (ordenes ya cerradas) y precio estable (su ultima compra vs su anterior, por producto).
 * Lee solo las ordenes del periodo por oc_org_sede_estado_IDX (idorg, idsede, estado, fecha); recepcion e items por idorden.
 * [idproveedor => {proveedor, ordenes, comprado, a_tiempo, tarde, dias_tarde, items, completos, productos, subieron,
 *  estrellas (1-5, null sin datos), etiqueta, problemas: [{idorden, fecha, texto}]}]
 */
function calificacion_proveedores($bd, $org) {
    $ocs = log_filas($bd, "SELECT oc.idorden, oc.idproveedor, IFNULL(pv.descripcion, '') AS proveedor, DATE(oc.fecha) AS fecha, oc.fecha_entrega,
            oc.estado, (SELECT DATE(MIN(r.fecha)) FROM recepcion r WHERE r.idorden = oc.idorden) AS llego,
            (SELECT IFNULL(SUM(c.total + 0), 0) FROM recepcion r JOIN compra c ON c.idcompra = r.idcompra WHERE r.idorden = oc.idorden) AS pagado,
            (SELECT COUNT(*) FROM orden_compra_item i WHERE i.idorden = oc.idorden) AS items,
            (SELECT IFNULL(SUM(i.cantidad_recibida + 0.0001 >= i.cantidad_pedida OR i.estado_item IN ('completo', 'reemplazado')), 0)
                FROM orden_compra_item i WHERE i.idorden = oc.idorden) AS completos
            FROM orden_compra oc LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
            WHERE oc.idorg = ? AND oc.idsede = ? AND oc.estado IN ('aprobada', 'enviada', 'parcial', 'recibida', 'cerrada')
              AND oc.fecha >= CURDATE() - INTERVAL " . CALIF_DIAS . " DAY AND oc.idproveedor IS NOT NULL
            ORDER BY oc.idorden DESC",
        array((int)$org['idorg'], (int)$org['idsede_central']));
    $hoy = date('Y-m-d');
    $r = array();
    foreach ($ocs as $o) {
        $k = (int)$o['idproveedor'];
        if (!isset($r[$k])) {
            $r[$k] = array('idproveedor' => $k, 'proveedor' => $o['proveedor'], 'ordenes' => 0, 'comprado' => 0, 'a_tiempo' => 0, 'tarde' => 0,
                'dias_tarde' => 0, 'items' => 0, 'completos' => 0, 'productos' => 0, 'subieron' => 0, 'problemas' => array());
        }
        $p = &$r[$k];
        $p['ordenes']++;
        $cerrada = in_array($o['estado'], array('recibida', 'cerrada'), true);
        $p['comprado'] += (float)$o['pagado'];   // lo que de verdad se compro al recibir (el total de la orden lleva precios previstos)
        // puntualidad: solo ordenes con "para cuando"; sin llegar y con la fecha pasada ya es tarde
        if ($o['fecha_entrega'] && ($o['llego'] || $o['fecha_entrega'] < $hoy)) {
            $dias = (int)round((strtotime($o['llego'] ?: $hoy) - strtotime($o['fecha_entrega'])) / 86400);
            if ($dias <= 0) { $p['a_tiempo']++; }
            else {
                $p['tarde']++;
                $p['dias_tarde'] += $dias;
                $p['problemas'][] = array('idorden' => (int)$o['idorden'], 'fecha' => $o['fecha'],
                    'texto' => $o['llego'] ? "Llegó $dias día(s) tarde" : "Aún no llega: va $dias día(s) de atraso");
            }
        }
        if ($cerrada && $o['llego']) {
            $p['items'] += (int)$o['items'];
            $p['completos'] += (int)$o['completos'];
            $falta = (int)$o['items'] - (int)$o['completos'];
            if ($falta > 0) { $p['problemas'][] = array('idorden' => (int)$o['idorden'], 'fecha' => $o['fecha'], 'texto' => "Faltaron $falta producto(s)"); }
        }
        unset($p);
    }
    if (!$r) { return $r; }
    // precio: por producto comprado a cada proveedor, su ultimo precio vs el anterior del MISMO proveedor
    $ids = array_column($ocs, 'idorden');
    $pares = array();
    foreach (array_chunk($ids, 500) as $parte) {
        foreach (log_filas($bd, "SELECT DISTINCT oc.idproveedor, i.idproducto FROM orden_compra_item i JOIN orden_compra oc ON oc.idorden = i.idorden
                WHERE i.idorden IN (" . marcas(count($parte)) . ")", $parte) as $f) { $pares[] = array((int)$f['idproveedor'], (int)$f['idproducto']); }
    }
    $hist = historial_precios($bd, array_column($pares, 1));
    foreach ($pares as list($k, $id)) {
        $mios = array_values(array_filter(isset($hist[$id]) ? $hist[$id] : array(), function ($h) use ($k) { return $h['idproveedor'] === $k; }));
        if (count($mios) < 2 || $mios[1]['precio'] <= 0) { continue; }
        $r[$k]['productos']++;
        if (($mios[0]['precio'] - $mios[1]['precio']) / $mios[1]['precio'] * 100 >= PRECIO_ALERTA_PCT) { $r[$k]['subieron']++; }
    }
    foreach ($r as &$p) {
        // nota: 40% a tiempo, 40% completo, 20% precio estable; lo que no tiene datos no pesa
        $partes = array();
        if ($p['a_tiempo'] + $p['tarde']) { $partes[] = array(0.4, $p['a_tiempo'] / ($p['a_tiempo'] + $p['tarde'])); }
        if ($p['items']) { $partes[] = array(0.4, $p['completos'] / $p['items']); }
        if ($p['productos']) { $partes[] = array(0.2, 1 - $p['subieron'] / $p['productos']); }
        $peso = array_sum(array_column($partes, 0));
        $p['estrellas'] = $peso ? max(1, round(array_sum(array_map(function ($x) { return $x[0] * $x[1]; }, $partes)) / $peso * 10) / 2) : null;
        $p['etiqueta'] = $p['estrellas'] === null ? 'Sin datos aún' : ($p['estrellas'] >= 4 ? 'Cumple bien' : ($p['estrellas'] >= 3 ? 'Regular' : 'Falla seguido'));
        $p['comprado'] = round($p['comprado'], 2);
        $p['problemas'] = array_slice($p['problemas'], 0, 10);
    }
    unset($p);
    return $r;
}

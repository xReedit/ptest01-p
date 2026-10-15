<?php
/**
 * Funciones de Por vencer (las usan vencimientos.php y resumen.php). Incluido despues de _logistica.php.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo

const DIAS_CONSUMO_V = 28;

/** Dias del lote: consumo por dia de cada producto de sede en los ultimos 28 dias. [idproducto => por_dia] */
function consumo_por_dia($bd, $ids) {
    if (!$ids) { return array(); }
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(DIAS_CONSUMO_V - 1))[0]['d'];
    $r = array();
    foreach (log_filas($bd, "SELECT idproducto, SUM(cantidad) AS c FROM log_consumo_dia WHERE idproducto IN ("
            . implode(',', array_fill(0, count($ids), '?')) . ") AND fecha >= ? GROUP BY idproducto", array_merge($ids, array($desde))) as $f) {
        $r[(int)$f['idproducto']] = max(0, (float)$f['c']) / DIAS_CONSUMO_V;
    }
    return $r;
}

/**
 * Lotes por vencer (o vencidos) con su cantidad estimada, por sede. $soloSede = null para todas.
 * Devuelve filas con la sugerencia de traslado ya calculada.
 */
function por_vencer($bd, $org, $soloSede) {
    $dias = max(1, (int)$org['dias_alerta_vencimiento']);
    $sedes = array();
    foreach (log_filas($bd, "SELECT ls.idsede, s.nombre, ls.idalmacen_principal FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idalmacen_principal > 0", array((int)$org['idorg'])) as $s) {
        $sedes[(int)$s['idalmacen_principal']] = $s;
    }
    if (!$sedes) { return array(); }
    $alms = array_keys($sedes);
    // saldo de movimientos por (almacen, lote): solo productos que tienen algun lote dentro de la ventana
    $lotes = log_filas($bd, "SELECT l.idlote, l.idproducto_central, l.lote, l.vencimiento, m.idalmacen, SUM(m.cantidad) AS saldo,
            DATEDIFF(l.vencimiento, CURDATE()) AS dias_restan
            FROM log_lote l JOIN log_lote_mov m ON m.idlote = l.idlote
            WHERE l.idorg = ? AND l.vencimiento IS NOT NULL AND m.idalmacen IN (" . implode(',', array_fill(0, count($alms), '?')) . ")
              AND l.idproducto_central IN (SELECT idproducto_central FROM log_lote WHERE idorg = ? AND vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
            GROUP BY l.idlote, m.idalmacen HAVING saldo > 0
            ORDER BY m.idalmacen, l.idproducto_central, l.vencimiento DESC",
        array_merge(array((int)$org['idorg']), $alms, array((int)$org['idorg'], $dias)));
    // agrupar por (almacen, producto central)
    $grupos = array();
    foreach ($lotes as $l) { $grupos[$l['idalmacen'] . '-' . $l['idproducto_central']][] = $l; }
    // productos de sede (vinculos) y su stock real
    $centrales = array_values(array_unique(array_map(function ($l) { return (int)$l['idproducto_central']; }, $lotes)));
    if (!$centrales) { return array(); }
    $prodSede = array();   // [idsede][idcentral] => idproducto
    foreach (log_filas($bd, "SELECT v.idsede, v.idproducto_central, v.idproducto FROM log_producto_central v JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            WHERE v.idproducto_central IN (" . implode(',', array_fill(0, count($centrales), '?')) . ")", $centrales) as $v) {
        $prodSede[(int)$v['idsede']][(int)$v['idproducto_central']] = (int)$v['idproducto'];
    }
    foreach ($centrales as $c) { $prodSede[(int)$org['idsede_central']][$c] = $c; }
    $todosProd = array();
    foreach ($prodSede as $m) { foreach ($m as $p) { $todosProd[] = $p; } }
    $stock = array();
    foreach (log_filas($bd, "SELECT idproducto, idalmacen, SUM(stock) AS s FROM producto_stock WHERE idproducto IN ("
            . implode(',', array_fill(0, count($todosProd), '?')) . ") GROUP BY idproducto, idalmacen", $todosProd) as $s) {
        $stock[$s['idproducto'] . '-' . $s['idalmacen']] = (float)$s['s'];
    }
    $porDia = consumo_por_dia($bd, $todosProd);
    $nombres = array();
    foreach (log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto p
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE p.idproducto IN ("
            . implode(',', array_fill(0, count($centrales), '?')) . ")", $centrales) as $n) { $nombres[(int)$n['idproducto']] = $n; }
    // traslados ya pedidos desde aqui (para no sugerir dos veces)
    $yaPedido = array();
    foreach (log_filas($bd, "SELECT idsede_origen, idproducto_central, SUM(cantidad) AS c FROM log_bandeja_linea
            WHERE idorg = ? AND decision = 'traslado' AND estado = 'pendiente' AND idsolicitud_producto = 0 GROUP BY idsede_origen, idproducto_central",
            array((int)$org['idorg'])) as $y) { $yaPedido[$y['idsede_origen'] . '-' . $y['idproducto_central']] = (float)$y['c']; }

    $res = array();
    foreach ($grupos as $lista) {
        $alm = (int)$lista[0]['idalmacen']; $c = (int)$lista[0]['idproducto_central'];
        $sede = $sedes[$alm]; $idsede = (int)$sede['idsede'];
        if ($soloSede !== null && $idsede !== (int)$soloSede) { continue; }
        $prod = isset($prodSede[$idsede][$c]) ? $prodSede[$idsede][$c] : 0;
        if (!$prod) { continue; }
        // FEFO: el stock real se asigna desde el lote que vence mas tarde (lista ya viene en ese orden)
        $resta = isset($stock[$prod . '-' . $alm]) ? max(0, $stock[$prod . '-' . $alm]) : 0;
        $acum = 0;   // lo que hay en lotes que vencen DESPUES del actual (se vende despues)
        $asig = array();
        foreach ($lista as $l) { $q = min($resta, (float)$l['saldo']); $asig[] = array($l, $q); $resta -= $q; }
        $vende = isset($porDia[$prod]) ? $porDia[$prod] : 0;
        foreach (array_reverse($asig) as $par) {   // del que vence antes al que vence despues
            list($l, $q) = $par;
            $dr = (int)$l['dias_restan'];
            if ($q <= 0 || $dr > (int)$org['dias_alerta_vencimiento']) { continue; }
            // lo que se alcanza a vender de ESTE lote antes de que venza (FEFO: primero se vende lo que vence antes;
            // lo ya vencido NO cuenta como vendido primero: se saca de la venta)
            $alcanza = $dr > 0 ? max(0, $vende * $dr - $acum) : 0;
            $sobra = round(max(0, $q - $alcanza), 2);
            if ($dr > 0) { $acum += $q; }
            $sug = null;
            $ya = isset($yaPedido[$idsede . '-' . $c]) ? $yaPedido[$idsede . '-' . $c] : 0;
            if ($sobra > 0 && $dr > 0 && $ya <= 0) {
                // la sede que mas lo vende y que alcanza a venderlo (descontando lo que ya tiene)
                $mejor = null;
                foreach ($sedes as $s2) {
                    $d = (int)$s2['idsede'];
                    if ($d === $idsede || empty($prodSede[$d][$c])) { continue; }
                    $p2 = $prodSede[$d][$c];
                    $v2 = isset($porDia[$p2]) ? $porDia[$p2] : 0;
                    $cap = $v2 * $dr - (isset($stock[$p2 . '-' . $s2['idalmacen_principal']]) ? $stock[$p2 . '-' . $s2['idalmacen_principal']] : 0);
                    if ($v2 > 0 && $cap >= 1 && (!$mejor || $v2 > $mejor['por_dia'])) { $mejor = array('idsede' => $d, 'nombre' => $s2['nombre'], 'por_dia' => $v2, 'cap' => $cap); }
                }
                if ($mejor) { $sug = array('idsede' => $mejor['idsede'], 'sede' => $mejor['nombre'], 'cantidad' => floor(min($sobra, $mejor['cap']))); }
                if ($sug && $sug['cantidad'] < 1) { $sug = null; }
            }
            $res[] = array('idlote' => (int)$l['idlote'], 'idproducto_central' => $c, 'producto' => $nombres[$c]['descripcion'],
                'unidad' => $nombres[$c]['unidad'], 'idsede' => $idsede, 'sede' => $sede['nombre'], 'lote' => $l['lote'],
                'vence' => $l['vencimiento'], 'dias' => $dr, 'cantidad' => round($q, 2), 'por_dia' => round($vende, 2),
                'sobra' => $sobra, 'sugerencia' => $sug, 'en_envios' => $ya > 0 ? $ya : 0);
        }
    }
    usort($res, function ($a, $b) { return $a['dias'] - $b['dias'] ?: strcmp($a['producto'], $b['producto']); });
    return $res;
}

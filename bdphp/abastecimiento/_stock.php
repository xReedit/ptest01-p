<?php
/**
 * Stock para decidir (bandeja, ordenes, diagrama). Regla: lo que logistica decide y despacha sale del ALMACEN PRINCIPAL
 * de cada sede (log_sede.idalmacen_principal); lo de otros almacenes de la misma sede (ej. PRODUCCION) no se cuenta,
 * solo se avisa para que lo pasen al principal antes de comprar.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo

const DIAS_PARADO = 30;       // stock que alcanza para mas de 30 dias (o que no se vende) = parado
const DIAS_CONSUMO = 28;
const DIAS_PRUEBA_VENTA = 90;   // se vigila un producto si se VENDIO en alguna sede en 90 dias: prueba que el POS lo descuenta
const DIAS_SIN_VENTA = 15;      // en una sede: 15 dias sin venderse (con stock) = parado
// "PRODUCCION: 50, OTRO: 3" del mismo producto en los otros almacenes (params: idalmacen principal). Por indice uq (idproducto, idalmacen).
const SQL_OTROS_ALMACENES = "(SELECT GROUP_CONCAT(CONCAT(a.descripcion, ': ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM ROUND(ps2.stock, 2)))) SEPARATOR ', ') FROM producto_stock ps2
        JOIN almacen a ON a.idalmacen = ps2.idalmacen WHERE ps2.idproducto = p.idproducto AND ps2.idalmacen <> ? AND ps2.stock > 0)";

// Costo unitario de un item de envio: el guardado al despachar (migracion 017) o, si es un envio anterior, el costo
// actual del producto central (producto.precio_unitario = ultimo costo de compra de la central). Alias: i = log_traslado_item.
const SQL_COSTO_ITEM = "IFNULL(i.costo, (SELECT pc.precio_unitario + 0 FROM producto pc WHERE pc.idproducto = i.idproducto_central))";

/** Otros almacenes de la sede con stock de ese producto: [{idalmacen, almacen, stock}] (el principal no cuenta). */
function otros_almacenes_lista($bd, $idproducto, $idsede, $almPrincipal) {
    return array_map(function ($f) { return array('idalmacen' => (int)$f['idalmacen'], 'almacen' => $f['almacen'], 'stock' => (float)$f['stock']); },
        log_filas($bd, "SELECT a.idalmacen, a.descripcion AS almacen, ps.stock + 0 AS stock FROM producto_stock ps
            JOIN almacen a ON a.idalmacen = ps.idalmacen AND a.idsede = ? AND a.estado = 0
            WHERE ps.idproducto = ? AND ps.idalmacen <> ? AND ps.stock > 0 ORDER BY ps.stock DESC", array((int)$idsede, (int)$idproducto, (int)$almPrincipal)));
}

function motivo_id($bd, $nombre, $tipo, $porDefecto) {
    $r = log_filas($bd, "SELECT idalmacen_motivo_es AS id FROM almacen_motivo_es WHERE motivo = ? AND tipo = ? LIMIT 1", array($nombre, $tipo));
    return $r ? (int)$r[0]['id'] : $porDefecto;
}

// Texto de cantidad para almacen_ie_detalle / producto_historial: 3 decimales, igual que round(..., 3) del llamador
// (si el llamador redondea a 4 el kardex y el stock no cuadran: 0.1255 -> "0.126").
function txt_cant($n) {
    $t = rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
    return $t === '' || $t === '-0' ? '0' : $t;
}

function alm_principal($bd, $idsede) {
    $r = log_filas($bd, "SELECT idalmacen_principal FROM log_sede WHERE idsede = ?", array((int)$idsede));
    return $r ? (int)$r[0]['idalmacen_principal'] : 0;
}

/**
 * Sedes con stock PARADO de esos productos: lo que les sobra despues de cubrir sus propios dias + su minimo
 * y lo ya comprometido en otros traslados. [idcentral => [ {idsede, sede, libre, dias_stock}, ... ]]
 */
/**
 * De esos productos centrales, los que SE DESCUENTAN CON VENTAS: tuvieron ventas en DIAS_PRUEBA_VENTA en alguna sede
 * (o en la central), o estan marcados 'si' a mano; nunca los marcados 'no'. Los abarrotes que solo se usan en cocina
 * sin receta no tienen ventas: su stock nunca baja y no deben verse como "parados".
 */
function se_venden($bd, $org, $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) { return array(); }
    $m = implode(',', array_fill(0, count($ids), '?'));
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(DIAS_PRUEBA_VENTA))[0]['d'];
    $ok = array();
    // copias en las sedes (PK log_consumo_dia: idproducto, fecha) y la central misma
    foreach (log_filas($bd, "SELECT DISTINCT v.idproducto_central AS id FROM log_producto_central v
            WHERE v.idproducto_central IN ($m) AND EXISTS (SELECT 1 FROM log_consumo_dia c WHERE c.idproducto = v.idproducto AND c.fecha >= ? AND c.cantidad > 0)
            UNION SELECT c.idproducto FROM log_consumo_dia c WHERE c.idproducto IN ($m) AND c.fecha >= ? AND c.cantidad > 0",
            array_merge($ids, array($desde), $ids, array($desde))) as $f) { $ok[(int)$f['id']] = true; }
    // heladeria: el bote se vende como porcion (porcion_historial), no como producto
    $porc = array_values(array_intersect($ids, array_keys(porciones_org($bd, (int)$org['idorg']))));
    if ($porc) {
        foreach (log_filas($bd, "SELECT DISTINCT v.idproducto_central AS id FROM log_producto_central v
                JOIN porcion po ON po.idproducto_de = v.idproducto AND po.idsede = v.idsede AND po.estado = 0
                WHERE v.idproducto_central IN (" . implode(',', array_fill(0, count($porc), '?')) . ") AND EXISTS (SELECT 1 FROM porcion_historial h
                  WHERE h.idporcion = po.idporcion AND h.fecha_date >= ? AND h.idtipo_movimiento_stock = 3)",
                array_merge($porc, array($desde))) as $f) { $ok[(int)$f['id']] = true; }
    }
    foreach (log_filas($bd, "SELECT idproducto_central AS id, modo FROM log_vigilar_parado WHERE idorg = ? AND idproducto_central IN ($m)",
            array_merge(array((int)$org['idorg']), $ids)) as $f) {
        if ($f['modo'] === 'si') { $ok[(int)$f['id']] = true; } else { unset($ok[(int)$f['id']]); }
    }
    return array_values(array_filter($ids, function ($i) use ($ok) { return isset($ok[$i]); }));
}

/**
 * Fila de una sede (stock del almacen y consumo de DIAS_CONSUMO dias) de un producto que alli se vende por porcion:
 * stock, consumo y ultima venta salen de su porcion, en botes. Sin porcion enlazada la fila queda como esta.
 */
function con_porcion($bd, $f, $factor) {
    $p = porcion_sede($bd, (int)$f['idsede'], (int)$f['idproducto'], $factor, DIAS_CONSUMO);
    if (!$p) { return $f; }
    $f['stock'] = $p['stock'];
    $f['consumo'] = $p['vendido'];
    $f['ultima'] = $p['ultima'];
    $f['porcion'] = $p['porcion'];
    return $f;
}

function stock_parado($bd, $org, $ids) {
    $ids = se_venden($bd, $org, $ids);   // abarrotes sin ventas no cuentan como "parado en otra sede"
    if (!$ids) { return array(); }
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(DIAS_CONSUMO - 1))[0]['d'];
    $filas = log_filas($bd, "SELECT v.idproducto_central, v.idsede, v.idproducto, s.nombre AS sede, IFNULL(ls.dias_cobertura, ?) AS dias,
            GREATEST(p.stock_minimo + 0, 0) AS minimo,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto AND ps.idalmacen = ls.idalmacen_principal), 0) AS stock,
            IFNULL((SELECT SUM(c.cantidad) FROM log_consumo_dia c WHERE c.idproducto = p.idproducto AND c.fecha >= ?), 0) AS consumo,
            IFNULL((SELECT SUM(b.cantidad) FROM log_bandeja_linea b WHERE b.idproducto_central = v.idproducto_central
                     AND b.decision IN ('traslado', 'produccion') AND b.idsede_origen = v.idsede AND b.estado <> 'hecho'), 0) AS comprometido
            FROM log_producto_central v
            JOIN log_sede ls ON ls.idsede = v.idsede AND ls.activo = 1
            JOIN sede s ON s.idsede = v.idsede
            JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            WHERE v.idproducto_central IN (" . implode(",", array_fill(0, count($ids), "?")) . ")",
        array_merge(array((int)$org['dias_cobertura'], $desde), $ids));
    $r = array();
    $porcOrg = porciones_org($bd, (int)$org['idorg']);
    foreach ($filas as $f) {
        if (isset($porcOrg[(int)$f['idproducto_central']])) { $f = con_porcion($bd, $f, $porcOrg[(int)$f['idproducto_central']]); }
        $stock = (float)$f['stock'];
        $porDia = max(0, (float)$f['consumo']) / DIAS_CONSUMO;
        $diasStock = $porDia > 0 ? $stock / $porDia : null;
        if ($stock <= 0 || ($diasStock !== null && $diasStock <= DIAS_PARADO)) { continue; }
        $libre = floor(($stock - $porDia * (int)$f['dias'] - (float)$f['minimo'] - (float)$f['comprometido']) * 100) / 100;
        if ($libre < 1) { continue; }
        $r[(int)$f['idproducto_central']][] = array('idsede' => (int)$f['idsede'], 'sede' => $f['sede'], 'libre' => $libre,
            'dias_stock' => $diasStock === null ? null : round($diasStock));
    }
    foreach ($r as &$lista) { usort($lista, function ($a, $b) { return $b['libre'] <=> $a['libre']; }); }
    return $r;
}


/**
 * Stock parado en las sedes (no la central), solo de productos que se descuentan con ventas. Parado = con stock y
 * DIAS_SIN_VENTA sin venderse, o que alcanza para mas de DIAS_PARADO dias. "sobra" = lo que se puede llevar a otra sede
 * (todo si no se vende; si se vende lento, lo que pasa de DIAS_PARADO dias), sin lo ya comprometido en traslados.
 * Sugiere la sede que mas lo vende. $soloSede null = todas.
 */
function parado_org($bd, $org, $soloSede = null) {
    $o = (int)$org['idorg']; $c = (int)$org['idsede_central'];
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(DIAS_CONSUMO - 1))[0]['d'];
    $desdeUlt = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(DIAS_PRUEBA_VENTA))[0]['d'];
    $cand = array_map('intval', array_column(log_filas($bd, "SELECT DISTINCT v.idproducto_central FROM log_sede ls
            JOIN log_producto_central v ON v.idsede = ls.idsede WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ?",
        array($o, $c)), 'idproducto_central'));
    $ids = se_venden($bd, $org, $cand);
    if (!$ids) { return array(); }
    $m = implode(',', array_fill(0, count($ids), '?'));
    // todas las sedes (para sugerir destino); por sede: stock del almacen principal, venta 28 dias, ultima venta, comprometido
    // STRAIGHT_JOIN: log_sede (ix_log_sede_org) -> log_producto_central (uq_lpc_sede_central) -> producto por PK; sin el,
    // MySQL empezaba por producto (ALL). Ultima venta: PK (idproducto, fecha) hacia atras, para en la primera con venta,
    // acotada a DIAS_PRUEBA_VENTA (antes MAX recorria todos los dias); mas vieja = "nunca" (igual cuenta como parado).
    $filas = log_filas($bd, "SELECT STRAIGHT_JOIN v.idsede, s.nombre AS sede, v.idproducto, v.idproducto_central, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = v.idproducto AND ps.idalmacen = ls.idalmacen_principal), 0) AS stock,
            IFNULL((SELECT SUM(cd.cantidad) FROM log_consumo_dia cd WHERE cd.idproducto = v.idproducto AND cd.fecha >= ?), 0) AS consumo,
            (SELECT cd.fecha FROM log_consumo_dia cd WHERE cd.idproducto = v.idproducto AND cd.fecha >= ? AND cd.cantidad > 0
              ORDER BY cd.fecha DESC LIMIT 1) AS ultima,
            IFNULL((SELECT SUM(b.cantidad) FROM log_bandeja_linea b WHERE b.idproducto_central = v.idproducto_central
                     AND b.decision IN ('traslado', 'produccion') AND b.idsede_origen = v.idsede AND b.estado <> 'hecho'), 0) AS comprometido,
            (SELECT pc.precio_unitario + 0 FROM producto pc WHERE pc.idproducto = v.idproducto_central) AS costo
            FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            JOIN log_producto_central v ON v.idsede = ls.idsede AND v.idproducto_central IN ($m)
            JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ?",
        array_merge(array($desde, $desdeUlt), $ids, array($o, $c)));
    $hoy = log_filas($bd, "SELECT CURDATE() AS h")[0]['h'];
    $porcOrg = porciones_org($bd, $o);
    foreach ($filas as &$f) {
        if (isset($porcOrg[(int)$f['idproducto_central']])) { $f = con_porcion($bd, $f, $porcOrg[(int)$f['idproducto_central']]); }
    }
    unset($f);
    $ritmo = array();
    foreach ($filas as $f) {
        $ritmo[(int)$f['idproducto_central']][] = array('idsede' => (int)$f['idsede'], 'sede' => $f['sede'],
            'por_dia' => max(0, (float)$f['consumo']) / DIAS_CONSUMO, 'stock' => (float)$f['stock']);
    }
    $r = array();
    foreach ($filas as $f) {
        if ($soloSede && (int)$f['idsede'] !== (int)$soloSede) { continue; }
        $st = (float)$f['stock'];
        if ($st <= 0) { continue; }
        $porDia = max(0, (float)$f['consumo']) / DIAS_CONSUMO;
        $diasSin = $f['ultima'] ? (int)round((strtotime($hoy) - strtotime($f['ultima'])) / 86400) : null;
        $alcanza = $porDia > 0 ? $st / $porDia : null;
        $sinVenta = $diasSin === null || $diasSin >= DIAS_SIN_VENTA;
        if (!$sinVenta && $alcanza !== null && $alcanza <= DIAS_PARADO) { continue; }
        $sobra = $sinVenta ? $st : $st - $porDia * DIAS_PARADO;
        $sobra = floor(($sobra - (float)$f['comprometido']) * 100) / 100;
        if ($sobra < 1) { continue; }
        // la sede (no esta) que mas lo vende y que no esta sobrada
        $dest = null;
        foreach ($ritmo[(int)$f['idproducto_central']] as $x) {
            if ($x['idsede'] === (int)$f['idsede'] || $x['por_dia'] <= 0) { continue; }
            if ($x['stock'] / $x['por_dia'] > DIAS_PARADO) { continue; }
            if (!$dest || $x['por_dia'] > $dest['por_dia']) { $dest = $x; }
        }
        $r[] = array('idsede' => (int)$f['idsede'], 'sede' => $f['sede'], 'idproducto_central' => (int)$f['idproducto_central'],
            'descripcion' => $f['descripcion'], 'unidad' => $f['unidad'], 'stock' => $st, 'sobra' => $sobra,
            'dias_sin_venta' => $diasSin, 'alcanza_dias' => $alcanza === null ? null : (int)round($alcanza), 'por_dia' => round($porDia, 2),
            'costo' => (float)$f['costo'], 'valor' => round($sobra * (float)$f['costo'], 2), 'comprometido' => (float)$f['comprometido'],
            'porcion' => isset($f['porcion']) ? $f['porcion'] : null,
            'destino' => $dest ? array('idsede' => $dest['idsede'], 'sede' => $dest['sede'], 'por_dia' => round($dest['por_dia'], 2)) : null);
    }
    usort($r, function ($a, $b) { return $b['valor'] <=> $a['valor'] ?: $b['sobra'] <=> $a['sobra']; });
    return $r;
}

const ALM_DIAS_ALERTA = 7;    // ponytail: "por terminarse" = le quedan menos de 7 dias de salidas; configurable si lo piden
const ALM_DIAS_COMPRA = 14;   // la compra sugerida cubre ~2 semanas de salidas (o el doble del minimo)

/**
 * Productos del almacen central que se estan terminando: stock <= su minimo, o alcanza para menos de ALM_DIAS_ALERTA
 * dias de lo que salio a las sedes en 30 dias. Con lo ya pedido (compras sin terminar para la central) y la compra sugerida.
 * Consultas: log_traslado(ix_lt_org_estado) + items por PK; producto(producto_idorg_IDX); log_bandeja_linea(ix_lbl_org_decision).
 */
function almacen_bajo($bd, $org) {
    $o = (int)$org['idorg']; $c = (int)$org['idsede_central'];
    $alm = alm_principal($bd, $c);
    $sale = array();
    foreach (log_filas($bd, "SELECT i.idproducto_central AS id, SUM(i.cantidad_enviada) / 30 AS dia FROM log_traslado t
            JOIN log_traslado_item i ON i.idtraslado = t.idtraslado
            WHERE t.idorg = ? AND t.estado IN ('despachado', 'recibido', 'recibido_con_diferencia') AND t.idsede_origen = ?
              AND t.fecha_despacho >= NOW() - INTERVAL 30 DAY GROUP BY i.idproducto_central", array($o, $c)) as $f) { $sale[(int)$f['id']] = (float)$f['dia']; }
    $pedido = array();
    foreach (log_filas($bd, "SELECT idproducto_central AS id, SUM(cantidad) AS q FROM log_bandeja_linea
            WHERE idorg = ? AND decision = 'compra' AND estado <> 'hecho' AND idsede = ? GROUP BY idproducto_central", array($o, $c)) as $f) { $pedido[(int)$f['id']] = (float)$f['q']; }
    $ids = array_keys($sale);
    $filas = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, p.idunidad_kardex, GREATEST(p.stock_minimo + 0, 0) AS minimo,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto AND ps.idalmacen = ?), 0) AS stock
            FROM producto p LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 AND (p.stock_minimo + 0 > 0"
            . ($ids ? " OR p.idproducto IN (" . implode(',', array_fill(0, count($ids), '?')) . ")" : "") . ")",
        array_merge(array($alm, $o, $c), $ids));
    $res = array();
    foreach ($filas as $f) {
        $id = (int)$f['idproducto']; $st = (float)$f['stock']; $min = (float)$f['minimo'];
        $dia = isset($sale[$id]) ? $sale[$id] : 0; $ya = isset($pedido[$id]) ? $pedido[$id] : 0;
        // solo lo que se mueve: un producto del catalogo en 0 que nunca sale no "se esta terminando"
        $bajo = ($dia > 0 && $st < $dia * ALM_DIAS_ALERTA) || ($min > 0 && $st <= $min && ($dia > 0 || $st > 0));
        if (!$bajo) { continue; }
        $q = max($min * 2, $dia * ALM_DIAS_COMPRA) - $st - $ya;
        if ((int)$f['idunidad_kardex'] === 0) { $q = ceil($q - 1e-9); }
        $res[] = array('idproducto_central' => $id, 'descripcion' => $f['descripcion'], 'unidad' => $f['unidad'], 'stock' => round($st, 3),
            'minimo' => round($min, 3), 'sale_dia' => round($dia, 2), 'dias' => $dia > 0 ? floor($st / $dia) : null,
            'pedido' => round($ya, 3), 'sugerido' => max(0, round($q, 2)));
    }
    usort($res, function ($a, $b) { return ($a['dias'] === null ? 9999 : $a['dias']) - ($b['dias'] === null ? 9999 : $b['dias']) ?: strcmp($a['descripcion'], $b['descripcion']); });
    return $res;
}


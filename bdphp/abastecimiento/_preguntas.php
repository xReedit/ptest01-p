<?php
/**
 * Logistica F8 — herramientas FIJAS de solo lectura para las preguntas en lenguaje natural.
 * La IA solo elige la herramienta y sus datos (producto, sede, proveedor, dias); aqui se validan y se consulta.
 * Nunca SQL libre. Todo acotado a la org de la sesion y por indice (prod > 20M filas).
 * Cada herramienta devuelve {titulo, respuesta (una frase), columnas, filas}.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
require_once __DIR__ . '/_consumo.php';
require_once __DIR__ . '/_vencimientos.php';
require_once __DIR__ . '/_stock.php';

const PREG_MAX_PRODUCTOS = 20;
const PREG_DIAS_CONSUMO = 28;

function pm($n) { return implode(',', array_fill(0, count($n), '?')); }
function pnum($n) { $t = rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); return $t === '' || $t === '-0' ? '0' : $t; }
function psoles($n) { return 'S/ ' . number_format((float)$n, 2, '.', ','); }

/** Sin tildes, minusculas, sin espacios dobles. */
function pnorm($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = strtr($s, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'));
    return preg_replace('/\s+/', ' ', $s);
}

/** Palabras para buscar: sin palabras vacias y en singular simple (arroces -> arroc, cervezas -> cerveza). */
function ppalabras($txt) {
    $vacias = array('de', 'del', 'la', 'las', 'el', 'los', 'en', 'y', 'un', 'una', 'para', 'con', 'por', 'kg', 'und', 'lt');
    $r = array();
    foreach (explode(' ', pnorm(preg_replace('/[^\p{L}\p{N} ]/u', ' ', (string)$txt))) as $w) {
        if (mb_strlen($w, 'UTF-8') < 2 || in_array($w, $vacias, true)) { continue; }
        if (mb_strlen($w, 'UTF-8') > 4 && substr($w, -2) === 'es') { $w = substr($w, 0, -2); }
        elseif (mb_strlen($w, 'UTF-8') > 3 && substr($w, -1) === 's') { $w = substr($w, 0, -1); }
        $r[] = $w;
    }
    return array_slice(array_values(array_unique($r)), 0, 4);
}

/** Sedes activas de la org (incluye la central). [idsede => nombre] */
function psedes($bd, $org) {
    $r = array();
    foreach (log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idorg = ? AND ls.activo = 1 ORDER BY s.nombre", array((int)$org['idorg'])) as $s) { $r[(int)$s['idsede']] = $s['nombre']; }
    if (!isset($r[(int)$org['idsede_central']])) {
        $c = log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array((int)$org['idsede_central']));
        $r[(int)$org['idsede_central']] = $c ? $c[0]['nombre'] : 'Central';
    }
    return $r;
}

/** Texto de sede -> idsede (o null si no se pidio sede). "central"/"almacen" = la central. */
function presolver_sede($bd, $org, $txt) {
    $t = pnorm($txt);
    if ($t === '' || in_array($t, array('todas', 'todas las sedes', 'todos', 'null'), true)) { return null; }
    $sedes = psedes($bd, $org);
    if (preg_match('/\b(central|almacen)\b/', $t)) { return (int)$org['idsede_central']; }
    $t = trim(preg_replace('/\b(sede|local|tienda)\b/', '', $t));
    $hall = array();
    foreach ($sedes as $id => $n) {
        $nn = pnorm($n);
        if ($nn === $t) { return $id; }
        if ($t !== '' && (strpos($nn, $t) !== false || strpos($t, $nn) !== false)) { $hall[] = $id; }
    }
    if (count($hall) === 1) { return $hall[0]; }
    $lista = implode(', ', array_map(function ($id) use ($sedes) { return $sedes[$id]; }, $hall ?: array_keys($sedes)));
    throw new ErrorUsuario(($hall ? '¿Cuál sede? Hay varias: ' : 'No encontré la sede "' . trim($txt) . '". Las sedes son: ') . $lista . '.');
}

/** Productos del catalogo central que tienen todas las palabras. Devuelve [ids, nombres, hay_mas]. */
function presolver_productos($bd, $org, $txt) {
    $pal = ppalabras($txt);
    if (!$pal) { throw new ErrorUsuario('¿De qué producto? Escribe su nombre, por ejemplo: arroz, aceite, cerveza.'); }
    $w = implode(' AND ', array_fill(0, count($pal), 'p.descripcion LIKE ?'));
    $filas = log_filas($bd, "SELECT p.idproducto, p.descripcion FROM producto p
            WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 AND $w ORDER BY p.descripcion LIMIT " . (PREG_MAX_PRODUCTOS + 1),
        array_merge(array((int)$org['idorg'], (int)$org['idsede_central']), array_map(function ($x) { return '%' . $x . '%'; }, $pal)));
    if (!$filas) { throw new ErrorUsuario('No encontré "' . trim($txt) . '" en el catálogo. Prueba con otra palabra (por ejemplo, la marca).'); }
    $mas = count($filas) > PREG_MAX_PRODUCTOS;
    $filas = array_slice($filas, 0, PREG_MAX_PRODUCTOS);
    return array(array_map('intval', array_column($filas, 'idproducto')), array_column($filas, 'descripcion'), $mas);
}

/** Proveedores de la central que coinciden. Devuelve [ids, nombres]. */
function presolver_proveedor($bd, $org, $txt) {
    $pal = ppalabras($txt);
    if (!$pal) { return array(array(), array()); }
    $w = implode(' AND ', array_fill(0, count($pal), 'descripcion LIKE ?'));
    $f = log_filas($bd, "SELECT idproveedor, descripcion FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 AND $w LIMIT 10",
        array_merge(array((int)$org['idorg'], (int)$org['idsede_central']), array_map(function ($x) { return '%' . $x . '%'; }, $pal)));
    if (!$f) { throw new ErrorUsuario('No encontré al proveedor "' . trim($txt) . '".'); }
    return array(array_map('intval', array_column($f, 'idproveedor')), array_column($f, 'descripcion'));
}

/** Copias en las sedes de esos productos centrales (+ la central misma): [ [idproducto, idcentral, idsede, sede] ] */
function pcopias($bd, $org, $ids, $idsede) {
    $sedes = psedes($bd, $org);
    $c = (int)$org['idsede_central'];
    $r = array();
    if ($idsede === null || $idsede === $c) { foreach ($ids as $id) { $r[] = array($id, $id, $c, $sedes[$c] . ' (central)'); } }
    if ($idsede === $c) { return $r; }
    $p = array_merge($ids, $idsede ? array($idsede) : array());
    foreach (log_filas($bd, "SELECT v.idproducto, v.idproducto_central, v.idsede FROM log_producto_central v
            JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            WHERE v.idproducto_central IN (" . pm($ids) . ")" . ($idsede ? " AND v.idsede = ?" : ''), $p) as $v) {
        if (!isset($sedes[(int)$v['idsede']]) || (int)$v['idsede'] === $c) { continue; }   // sede inactiva
        $r[] = array((int)$v['idproducto'], (int)$v['idproducto_central'], (int)$v['idsede'], $sedes[(int)$v['idsede']]);
    }
    return $r;
}

function pstock($bd, $ids) {
    $r = array();
    if (!$ids) { return $r; }
    foreach (log_filas($bd, "SELECT idproducto, SUM(stock) AS s FROM producto_stock WHERE idproducto IN (" . pm($ids) . ") GROUP BY idproducto", $ids) as $s) {
        $r[(int)$s['idproducto']] = (float)$s['s'];
    }
    return $r;
}

function pconsumo($bd, $ids, $dias) {
    $r = array();
    if (!$ids) { return $r; }
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array($dias - 1))[0]['d'];
    foreach (log_filas($bd, "SELECT idproducto, SUM(cantidad) AS c FROM log_consumo_dia WHERE idproducto IN (" . pm($ids) . ") AND fecha >= ?
            GROUP BY idproducto", array_merge($ids, array($desde))) as $s) { $r[(int)$s['idproducto']] = max(0, (float)$s['c']); }
    return $r;
}

function punidades($bd, $ids) {
    $r = array();
    foreach (log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto p
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex WHERE p.idproducto IN (" . pm($ids) . ")", $ids) as $f) {
        $r[(int)$f['idproducto']] = $f;
    }
    return $r;
}

function pper($d) { return $d === 1 ? ' en el último día' : ' en los últimos ' . $d . ' días'; }
function pdonde($idsede, $sedes) { return $idsede ? ' en ' . $sedes[$idsede] : ''; }

// ---------------------------------------------------------------- herramientas

function h_stock($bd, $org, $p) {
    list($ids, $nombres, $mas) = presolver_productos($bd, $org, $p['producto']);
    $cop = pcopias($bd, $org, $ids, $p['idsede']);
    $st = pstock($bd, array_column($cop, 0));
    $un = punidades($bd, $ids);
    $filas = array(); $tot = array();
    foreach ($cop as $c) {
        $s = isset($st[$c[0]]) ? $st[$c[0]] : 0;
        $tot[$c[1]] = (isset($tot[$c[1]]) ? $tot[$c[1]] : 0) + $s;
        $filas[] = array($un[$c[1]]['descripcion'], $c[3], (float)pnum($s), $un[$c[1]]['unidad']);
    }
    usort($filas, function ($a, $b) { return strcmp($a[0], $b[0]) ?: $b[2] <=> $a[2]; });
    $sedes = psedes($bd, $org);
    if (!$filas) {
        $resp = $sedes[$p['idsede']] . ' no tiene "' . $p['producto'] . '" en su catálogo.';
    } elseif (count($ids) === 1) {
        $resp = 'Hay ' . pnum($tot[$ids[0]]) . ' ' . $un[$ids[0]]['unidad'] . ' de ' . $nombres[0] . pdonde($p['idsede'], $sedes) . ($p['idsede'] ? '.' : ' sumando todas las sedes.');
    } else {
        $resp = 'Encontré ' . count($ids) . ($mas ? '+' : '') . ' productos con "' . $p['producto'] . '". ' . implode('; ', array_map(function ($id) use ($tot, $un) {
            return $un[$id]['descripcion'] . ': ' . trim(pnum(isset($tot[$id]) ? $tot[$id] : 0) . ' ' . $un[$id]['unidad']); }, array_slice($ids, 0, 4))) . (count($ids) > 4 ? '…' : '.');
    }
    if (count($ids) > 1 && !$p['idsede']) {
        // varios productos en todas las sedes: una fila por producto (con 47 sedes el detalle seria enorme)
        $c = (int)$org['idsede_central']; $res = array();
        foreach ($cop as $x) {
            $k = $x[1]; $v = isset($st[$x[0]]) ? $st[$x[0]] : 0;
            if (!isset($res[$k])) { $res[$k] = array($un[$k]['descripcion'], 0, 0, 0, 0, $un[$k]['unidad']); }
            $res[$k][1] += $v;
            if ($x[2] === $c) { $res[$k][2] += $v; } else { $res[$k][3] += $v; if ($v <= 0) { $res[$k][4]++; } }
        }
        $filas = array_map(function ($r) { return array($r[0], (float)pnum($r[1]), (float)pnum($r[2]), (float)pnum($r[3]), $r[4], $r[5]); }, array_values($res));
        usort($filas, function ($a, $b) { return strcmp($a[0], $b[0]); });
        return array('titulo' => 'Stock por producto (pregunta por uno solo para ver cada sede)', 'respuesta' => $resp,
            'columnas' => array('Producto', 'Total', 'Central', 'En sedes', 'Sedes en 0', 'Unidad'), 'filas' => $filas);
    }
    return array('titulo' => 'Stock' . pdonde($p['idsede'], $sedes), 'respuesta' => $resp,
        'columnas' => array('Producto', 'Sede', 'Stock', 'Unidad'), 'filas' => $filas);
}

function h_ventas($bd, $org, $p) {
    list($ids, $nombres) = presolver_productos($bd, $org, $p['producto']);
    $dias = $p['dias'] ?: 7;
    try { actualizar_consumo($bd, $org); } catch (Throwable $e) { error_log('logistica preguntas consumo: ' . $e->getMessage()); }
    $cop = pcopias($bd, $org, $ids, $p['idsede']);
    $co = pconsumo($bd, array_column($cop, 0), $dias);
    $un = punidades($bd, $ids);
    $filas = array(); $tot = array();
    foreach ($cop as $c) {
        $v = isset($co[$c[0]]) ? $co[$c[0]] : 0;
        if ($v <= 0) { continue; }
        $tot[$c[1]] = (isset($tot[$c[1]]) ? $tot[$c[1]] : 0) + $v;
        $filas[] = array($un[$c[1]]['descripcion'], $c[3], (float)pnum($v), (float)pnum($v / $dias), $un[$c[1]]['unidad']);
    }
    usort($filas, function ($a, $b) { return strcmp($a[0], $b[0]) ?: $b[2] <=> $a[2]; });
    $sedes = psedes($bd, $org);
    $per = pper($dias);
    if (!$filas) { $resp = 'No hay ventas de "' . $p['producto'] . '"' . pdonde($p['idsede'], $sedes) . $per . '.'; }
    elseif (count($ids) === 1) { $resp = 'Se vendieron ' . pnum($tot[$ids[0]]) . ' ' . $un[$ids[0]]['unidad'] . ' de ' . $nombres[0] . pdonde($p['idsede'], $sedes) . $per . ' (' . pnum($tot[$ids[0]] / $dias) . ' por día).'; }
    else { $resp = 'Ventas' . $per . ' de ' . count($tot) . ' producto(s) con "' . $p['producto'] . '".'; }
    return array('titulo' => 'Ventas' . $per, 'respuesta' => $resp,
        'columnas' => array('Producto', 'Sede', 'Vendido', 'Por día', 'Unidad'), 'filas' => $filas);
}

function h_compras($bd, $org, $p) {
    $dias = $p['dias'] ?: 30;
    $sede = $p['idsede'] ?: (int)$org['idsede_central'];
    $sedes = psedes($bd, $org);
    list($provs, $provN) = presolver_proveedor($bd, $org, $p['proveedor']);
    $prodIds = array(); $nombres = array();
    if (trim((string)$p['producto']) !== '') {
        list($ids, $nombres) = presolver_productos($bd, $org, $p['producto']);
        $prodIds = array_column(pcopias($bd, $org, $ids, $sede), 0);
        if (!$prodIds) { throw new ErrorUsuario($sedes[$sede] . ' no tiene "' . $p['producto'] . '" en su catálogo.'); }
    }
    // compras de la sede por indice idx_compra_sede (idsede, estado, idcompra); fecha_hora = cuando se registro
    $w = "c.idsede = ? AND c.idorg = ? AND c.estado = 0 AND c.fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $par = array($sede, (int)$org['idorg'], $dias);
    if ($provs) { $w .= " AND c.idproveedor IN (" . pm($provs) . ")"; $par = array_merge($par, $provs); }
    $per = pper($dias);
    $quien = $provs ? ' a ' . implode(' / ', $provN) : '';
    if ($prodIds || $provs) {
        // por producto: que se compro
        $wi = $prodIds ? " AND ci.idproducto IN (" . pm($prodIds) . ")" : '';
        $filas = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad, SUM(ci.cantidad + 0) AS cant, SUM(ci.ptotal + 0) AS total,
                COUNT(DISTINCT c.idcompra) AS compras
                FROM compra c JOIN compra_items ci ON ci.idcompra = c.idcompra AND ci.estado = 0 JOIN producto p ON p.idproducto = ci.idproducto
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE $w $wi GROUP BY ci.idproducto ORDER BY total DESC LIMIT 100", array_merge($par, $prodIds));
        $t = array_sum(array_map(function ($f) { return (float)$f['total']; }, $filas));
        $n = log_filas($bd, "SELECT COUNT(DISTINCT c.idcompra) AS n FROM compra c" . ($prodIds ? " JOIN compra_items ci ON ci.idcompra = c.idcompra AND ci.estado = 0" : '')
            . " WHERE $w $wi", array_merge($par, $prodIds))[0]['n'];
        $resp = $filas ? 'Se compró ' . psoles($t) . $quien . $per . ' en ' . $n . ' compra(s)' . pdonde($p['idsede'], $sedes) . '.'
            : 'No hay compras' . $quien . ($nombres ? ' de "' . $p['producto'] . '"' : '') . $per . pdonde($p['idsede'], $sedes) . '.';
        return array('titulo' => 'Compras' . $quien . $per, 'respuesta' => $resp, 'columnas' => array('Producto', 'Cantidad', 'Unidad', 'Total S/'),
            'filas' => array_map(function ($f) { return array($f['descripcion'], (float)pnum($f['cant']), $f['unidad'], round((float)$f['total'], 2)); }, $filas));
    }
    // sin producto ni proveedor: por proveedor
    $filas = log_filas($bd, "SELECT IFNULL(pv.descripcion, 'Sin proveedor') AS proveedor, COUNT(*) AS compras, SUM(c.total + 0) AS total
            FROM compra c LEFT JOIN proveedor pv ON pv.idproveedor = c.idproveedor
            WHERE $w GROUP BY c.idproveedor ORDER BY total DESC LIMIT 100", $par);
    $t = array_sum(array_map(function ($f) { return (float)$f['total']; }, $filas));
    return array('titulo' => 'Compras' . $per, 'respuesta' => $filas ? 'Se compró ' . psoles($t) . $per . pdonde($p['idsede'], $sedes) . ' a ' . count($filas) . ' proveedor(es).' : 'No hay compras' . $per . pdonde($p['idsede'], $sedes) . '.',
        'columnas' => array('Proveedor', 'Compras', 'Total S/'),
        'filas' => array_map(function ($f) { return array($f['proveedor'], (int)$f['compras'], round((float)$f['total'], 2)); }, $filas));
}

/** Productos de las sedes (no la central) con su stock y venta de 28 dias. $soloSede null = todas. */
function pproductos_sedes($bd, $org, $soloSede, $conCentral) {
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(PREG_DIAS_CONSUMO - 1))[0]['d'];
    $w = $soloSede ? " AND ls.idsede = ?" : ($conCentral ? '' : " AND ls.idsede <> ?");
    $par = array($desde, (int)$org['idorg']);
    if ($soloSede) { $par[] = $soloSede; } elseif (!$conCentral) { $par[] = (int)$org['idsede_central']; }
    // log_sede (ix_log_sede_org) -> log_producto_central (uq_lpc_sede_central) -> producto por PK (STRAIGHT_JOIN: sin el MySQL empieza por producto, ALL)
    $f = log_filas($bd, "SELECT STRAIGHT_JOIN s.nombre AS sede, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, GREATEST(p.stock_minimo + 0, 0) AS minimo,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto), 0) AS stock,
            IFNULL((SELECT SUM(c.cantidad) FROM log_consumo_dia c WHERE c.idproducto = p.idproducto AND c.fecha >= ?), 0) AS consumo
            FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            JOIN log_producto_central v ON v.idsede = ls.idsede
            JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE ls.idorg = ? AND ls.activo = 1 $w", $par);
    if ($conCentral && (!$soloSede || $soloSede === (int)$org['idsede_central'])) {
        $f = array_merge($f, log_filas($bd, "SELECT CONCAT(s.nombre, ' (central)') AS sede, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
                GREATEST(p.stock_minimo + 0, 0) AS minimo, IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto), 0) AS stock,
                0 AS consumo FROM producto p JOIN sede s ON s.idsede = p.idsede LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 AND p.stock_minimo + 0 > 0", array((int)$org['idorg'], (int)$org['idsede_central'])));
    }
    return $f;
}

function h_estancados($bd, $org, $p) {
    if ($p['idsede'] === (int)$org['idsede_central']) { throw new ErrorUsuario('Esto se revisa en las sedes, no en la central. Pregunta por una sede o por todas.'); }
    // misma regla que la pantalla Stock parado: solo productos que se descuentan con ventas (no abarrotes sin receta)
    $f = parado_org($bd, $org, $p['idsede']);
    $nS = count(array_unique(array_column($f, 'idsede')));
    return array('titulo' => 'Stock parado en las sedes', 'respuesta' => $f
            ? count($f) . ' producto(s) parados en ' . $nS . ' sede(s), por ' . psoles(array_sum(array_column($f, 'valor'))) . '. Puedes llevarlos a otra sede en vez de comprar.'
            : 'No hay stock parado' . pdonde($p['idsede'], psedes($bd, $org)) . '.',
        'columnas' => array('Sede', 'Producto', 'Sobra', 'Unidad', 'Sin vender', 'Valor S/'),
        'filas' => array_map(function ($x) {
            return array($x['sede'], $x['descripcion'], (float)pnum($x['sobra']), $x['unidad'],
                $x['dias_sin_venta'] === null ? 'nunca' : $x['dias_sin_venta'] . ' días', $x['valor']);
        }, array_slice($f, 0, 200)));
}

function h_bajo_minimo($bd, $org, $p) {
    $filas = array();
    foreach (pproductos_sedes($bd, $org, $p['idsede'], true) as $f) {
        $m = (float)$f['minimo']; $st = (float)$f['stock'];
        if ($m <= 0 || $st >= $m) { continue; }
        $filas[] = array($f['sede'], $f['descripcion'], (float)pnum($st), (float)pnum($m), (float)pnum($m - $st), $f['unidad']);
    }
    usort($filas, function ($a, $b) { return strcmp($a[0], $b[0]) ?: $b[4] <=> $a[4]; });
    $nS = count(array_unique(array_column($filas, 0)));
    return array('titulo' => 'Debajo del mínimo', 'respuesta' => $filas ? count($filas) . ' producto(s) debajo del mínimo en ' . $nS . ' sede(s).'
            : 'Ningún producto está debajo del mínimo' . pdonde($p['idsede'], psedes($bd, $org)) . '.',
        'columnas' => array('Sede', 'Producto', 'Stock', 'Mínimo', 'Falta', 'Unidad'), 'filas' => array_slice($filas, 0, 200));
}

function h_en_camino($bd, $org, $p) {
    $par = array((int)$org['idorg']);
    $w = '';
    if ($p['idsede']) { $w = ' AND t.idsede_destino = ?'; $par[] = $p['idsede']; }
    $f = log_filas($bd, "SELECT t.idtraslado, so.nombre AS origen, sd.nombre AS destino, DATE_FORMAT(t.fecha_despacho, '%d/%m/%Y %H:%i') AS salio,
            TIMESTAMPDIFF(HOUR, t.fecha_despacho, NOW()) AS horas, (SELECT COUNT(*) FROM log_traslado_item i WHERE i.idtraslado = t.idtraslado) AS productos
            FROM log_traslado t JOIN sede so ON so.idsede = t.idsede_origen JOIN sede sd ON sd.idsede = t.idsede_destino
            WHERE t.idorg = ? AND t.estado = 'despachado'$w ORDER BY t.fecha_despacho LIMIT 200", $par);
    $tarde = count(array_filter($f, function ($x) { return (int)$x['horas'] >= 24; }));
    return array('titulo' => 'Envíos en camino', 'respuesta' => $f ? count($f) . ' envío(s) en camino sin confirmar' . ($tarde ? ', ' . $tarde . ' con más de 24 horas: llama al encargado.' : '.') : 'No hay envíos en camino.',
        'columnas' => array('Envío N°', 'De', 'A', 'Salió', 'Horas', 'Productos'),
        'filas' => array_map(function ($x) { return array((int)$x['idtraslado'], $x['origen'], $x['destino'], $x['salio'], (int)$x['horas'], (int)$x['productos']); }, $f));
}

function h_por_llegar($bd, $org, $p) {
    list($provs, $provN) = presolver_proveedor($bd, $org, $p['proveedor']);
    $par = array((int)$org['idorg'], (int)$org['idsede_central']);
    $w = '';
    if ($provs) { $w = ' AND oc.idproveedor IN (' . pm($provs) . ')'; $par = array_merge($par, $provs); }
    $f = log_filas($bd, "SELECT oc.idorden, IFNULL(pv.descripcion, '') AS proveedor, oc.estado, DATE_FORMAT(oc.fecha, '%d/%m/%Y') AS fecha,
            DATEDIFF(NOW(), IFNULL(oc.fecha_envio, oc.fecha)) AS dias, oc.total
            FROM orden_compra oc LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
            WHERE oc.idorg = ? AND oc.idsede = ? AND oc.estado IN ('por_aprobar','aprobada','enviada','parcial')$w ORDER BY oc.fecha LIMIT 200", $par);
    $est = array('por_aprobar' => 'Por aprobar', 'aprobada' => 'Aprobada, falta enviar', 'enviada' => 'Enviada al proveedor', 'parcial' => 'Llegó una parte');
    $quien = $provs ? ' de ' . implode(' / ', $provN) : '';
    return array('titulo' => 'Órdenes de compra por llegar' . $quien, 'respuesta' => $f
            ? count($f) . ' orden(es)' . $quien . ' por llegar, por ' . psoles(array_sum(array_map(function ($x) { return (float)$x['total']; }, $f))) . '.'
            : 'No hay órdenes de compra pendientes' . $quien . '.',
        'columnas' => array('OC N°', 'Proveedor', 'Estado', 'Fecha', 'Días', 'Total S/'),
        'filas' => array_map(function ($x) use ($est) { return array((int)$x['idorden'], $x['proveedor'], $est[$x['estado']], $x['fecha'], (int)$x['dias'], round((float)$x['total'], 2)); }, $f));
}

function h_por_vencer($bd, $org, $p) {
    $f = por_vencer($bd, $org, $p['idsede']);
    $venc = count(array_filter($f, function ($x) { return $x['dias'] < 0; }));
    return array('titulo' => 'Lotes por vencer', 'respuesta' => $f
            ? count($f) . ' lote(s) por vencer en ' . (int)$org['dias_alerta_vencimiento'] . ' días' . ($venc ? ', ' . $venc . ' ya vencido(s): sácalos de la venta.' : '.')
            : 'No hay lotes por vencer' . pdonde($p['idsede'], psedes($bd, $org)) . '.',
        'columnas' => array('Producto', 'Sede', 'Lote', 'Vence', 'Días', 'Cantidad', 'Unidad'),
        'filas' => array_map(function ($x) { return array($x['producto'], $x['sede'], $x['lote'] ?: 's/l', $x['vence'], (int)$x['dias'], (float)pnum($x['cantidad']), $x['unidad']); }, array_slice($f, 0, 200)));
}

function h_pedidos($bd, $org, $p) {
    $par = array((int)$org['idorg'], (int)$org['idsede_central']);
    $w = '';
    if ($p['idsede']) { $w = ' AND s.idsede_solicita = ?'; $par[] = $p['idsede']; }
    $f = log_filas($bd, "SELECT s.idsolicitud_producto, sd.nombre AS sede, DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y %H:%i') AS fecha, s.estado,
            (SELECT COUNT(*) FROM solicitud_producto_detalle d WHERE d.idsolicitud_producto = s.idsolicitud_producto) AS productos, IFNULL(s.nota, '') AS nota
            FROM solicitud_producto s JOIN sede sd ON sd.idsede = s.idsede_solicita
            WHERE s.idorg = ? AND s.idsede_destino = ? AND s.estado IN ('enviado','visto')$w ORDER BY s.fecha_solicitud LIMIT 200", $par);
    return array('titulo' => 'Pedidos de las sedes por atender', 'respuesta' => $f ? count($f) . ' pedido(s) por atender. Atiéndelos en Bandeja central.' : 'No hay pedidos por atender.',
        'columnas' => array('Pedido N°', 'Sede', 'Fecha', 'Estado', 'Productos', 'Nota'),
        'filas' => array_map(function ($x) { return array((int)$x['idsolicitud_producto'], $x['sede'], $x['fecha'], $x['estado'] === 'visto' ? 'Visto' : 'Nuevo', (int)$x['productos'], $x['nota']); }, $f));
}

/** Herramientas: nombre => [funcion, necesita producto]. */
function pherramientas() {
    return array(
        'stock' => array('h_stock', true), 'ventas' => array('h_ventas', true), 'compras' => array('h_compras', false),
        'estancados' => array('h_estancados', false), 'bajo_minimo' => array('h_bajo_minimo', false), 'en_camino' => array('h_en_camino', false),
        'por_llegar' => array('h_por_llegar', false), 'por_vencer' => array('h_por_vencer', false), 'pedidos' => array('h_pedidos', false),
    );
}

/** Valida los datos (vengan de la IA o de un boton) y corre la herramienta. */
function pcorrer($bd, $org, $herr, $a) {
    $hs = pherramientas();
    if (!isset($hs[$herr])) { throw new ErrorUsuario('No entendí la pregunta.'); }
    $txt = function ($k) use ($a) { return isset($a[$k]) && is_string($a[$k]) ? mb_substr(trim($a[$k]), 0, 60, 'UTF-8') : ''; };
    $p = array('producto' => $txt('producto'), 'proveedor' => $txt('proveedor'),
        'dias' => isset($a['dias']) && is_numeric($a['dias']) ? max(1, min(90, (int)$a['dias'])) : 0);
    $p['idsede'] = presolver_sede($bd, $org, $txt('sede'));
    if ($hs[$herr][1] && $p['producto'] === '') { throw new ErrorUsuario('¿De qué producto? Por ejemplo: "¿cuánto arroz hay?"'); }
    $r = call_user_func($hs[$herr][0], $bd, $org, $p);
    $r['herramienta'] = $herr;
    // contexto: lo manda el chat con la siguiente pregunta ("¿y en AYCHA?") para completar lo que no diga
    $r['contexto'] = array('herramienta' => $herr, 'producto' => $p['producto'], 'sede' => $txt('sede'), 'proveedor' => $p['proveedor'], 'dias' => $p['dias']);
    $r['sugerencias'] = psugerir($herr, $r['contexto']);
    return $r;
}

/** Preguntas para seguir la conversacion. Van por op=boton (sin IA): funcionan aunque la IA no este configurada. */
function psugerir($herr, $c) {
    $mes = (int)date('j');
    $pr = $c['producto']; $sd = $c['sede']; $pv = $c['proveedor'];
    $s = function ($texto, $h, $a = array()) { return array('texto' => $texto, 'herramienta' => $h) + $a; };
    $en = $sd !== '' ? ' en ' . $sd : '';
    $todas = array(
        'pedidos' => $s('¿Qué pedidos faltan atender?', 'pedidos'),
        'bajo_minimo' => $s('¿Qué está debajo del mínimo?', 'bajo_minimo'),
        'estancados' => $s('¿Qué hay parado en las sedes?', 'estancados'),
        'por_llegar' => $s('¿Qué falta que llegue de los proveedores?', 'por_llegar'),
        'en_camino' => $s('¿Qué va en camino?', 'en_camino'),
        'por_vencer' => $s('¿Qué está por vencer' . $en . '?', 'por_vencer', array('sede' => $sd)),
        'compras_mes' => $s('¿Cuánto compramos este mes?', 'compras', array('dias' => $mes)),
    );
    switch ($herr) {
    case 'stock':
        $r = array($s('¿Cuánto ' . $pr . ' se vendió esta semana' . $en . '?', 'ventas', array('producto' => $pr, 'sede' => $sd, 'dias' => 7)),
                   $s('¿Qué compré de ' . $pr . ' este mes?', 'compras', array('producto' => $pr, 'dias' => $mes)));
        $r[] = $sd !== '' ? $s('¿Cuánto ' . $pr . ' hay en todas las sedes?', 'stock', array('producto' => $pr)) : $todas['estancados'];
        return $r;
    case 'ventas':
        return array($s('¿Cuánto ' . $pr . ' queda' . $en . '?', 'stock', array('producto' => $pr, 'sede' => $sd)),
                     $c['dias'] === 30 ? $s('¿Cuánto ' . $pr . ' se vendió esta semana' . $en . '?', 'ventas', array('producto' => $pr, 'sede' => $sd, 'dias' => 7))
                                       : $s('¿Y en los últimos 30 días?', 'ventas', array('producto' => $pr, 'sede' => $sd, 'dias' => 30)),
                     $s('¿Qué compré de ' . $pr . ' este mes?', 'compras', array('producto' => $pr, 'dias' => $mes)));
    case 'compras':
        $r = array($s('¿Qué falta que llegue' . ($pv !== '' ? ' de ' . $pv : ' de los proveedores') . '?', 'por_llegar', array('proveedor' => $pv)));
        if ($pr !== '') { $r[] = $s('¿Cuánto ' . $pr . ' hay en todas las sedes?', 'stock', array('producto' => $pr)); }
        $r[] = $c['dias'] === 30 ? $todas['compras_mes'] : $s('¿Y en los últimos 30 días?', 'compras', array('producto' => $pr, 'proveedor' => $pv, 'dias' => 30));
        return $r;
    case 'estancados':   return array($todas['por_vencer'], $todas['bajo_minimo'], $todas['pedidos']);
    case 'bajo_minimo':  return array($todas['pedidos'], $todas['estancados'], $todas['por_llegar']);
    case 'en_camino':    return array($todas['pedidos'], $todas['por_llegar'], $todas['bajo_minimo']);
    case 'por_llegar':   return array($todas['en_camino'], $todas['compras_mes'], $todas['bajo_minimo']);
    case 'por_vencer':   return array($s('¿Qué hay parado' . ($en ?: ' en las sedes') . '?', 'estancados', array('sede' => $sd)), $todas['bajo_minimo'], $todas['pedidos']);
    default:             return array($todas['bajo_minimo'], $todas['en_camino'], $todas['por_llegar']);
    }
}

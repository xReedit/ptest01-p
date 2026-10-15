<?php
/**
 * Consumo diario por producto de sede (migracion logistica/005). Lo llama pedidos.php antes de sugerir.
 * Incluido despues de _logistica.php.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo

// tipos de producto_historial que cuentan como consumo (collation ci: 'VENTA' = 'Venta')
const CONSUMO_TIPOS = "'VENTA', 'VENTA DEVOLUCION', 'VENTA_DEVOLUCION', 'Disminuye', 'SALIDA'";
const CONSUMO_DIAS_INICIALES = 60;

/** SELECT que resume producto_historial en (idproducto, fecha, idsede, cantidad). $filtro va despues del WHERE base. */
function consumo_select($filtro) {
    return "SELECT h.idproducto, STR_TO_DATE(h.fecha, '%Y-%m-%d') AS f, h.idsede,
                SUM(CASE WHEN h.tipo_movimiento IN ('VENTA DEVOLUCION', 'VENTA_DEVOLUCION') THEN -ABS(h.cantidad + 0)
                         ELSE ABS(h.cantidad + 0) END) AS cantidad
            FROM producto_historial h
            WHERE h.tipo_movimiento IN (" . CONSUMO_TIPOS . ") AND h.hora <> 'POR DIA' AND h.idproducto > 0 $filtro
            GROUP BY h.idproducto, f, h.idsede
            HAVING f IS NOT NULL";
}

/**
 * Primer idproducto_historial cuya fecha es >= $desde, por biseccion sobre la PK (~25 lecturas de 1 fila).
 * producto_historial crece en orden de fecha; las filas 'POR DIA' (agregados con fecha vieja) se saltan.
 */
function primer_id_desde($bd, $desde, $hasta) {
    $lo = (int)log_filas($bd, "SELECT IFNULL(MIN(idproducto_historial), 0) AS m FROM producto_historial")[0]['m'];
    $hi = (int)$hasta;
    while ($hi - $lo > 1000) {
        $mid = (int)(($lo + $hi) / 2);
        $r = log_filas($bd, "SELECT fecha FROM producto_historial WHERE idproducto_historial >= ? AND hora <> 'POR DIA'
                ORDER BY idproducto_historial LIMIT 1", array($mid));
        if ($r && strcmp((string)$r[0]['fecha'], $desde) < 0) { $lo = $mid; } else { $hi = $mid; }
    }
    return $lo;
}

const CONSUMO_MARGEN_MIN = 3;        // una fila se cuenta cuando tiene 3+ minutos (hora tiene resolucion de minuto: >= 2 min reales)
const CONSUMO_VENTANA_IDS = 20000;   // ultimas filas que se miran para hallar el tope

/**
 * Hasta que id se puede contar sin perder filas: el autoincremento se asigna al insertar, no al confirmar, asi que una
 * venta aun sin commit puede tener un id MENOR que filas ya visibles; si el cursor saltara a MAX(id) esa venta no se
 * contaria nunca. Tope = la fila mas nueva con >= CONSUMO_MARGEN_MIN minutos (fecha 'Y-m-d' + hora '%h:%i %p').
 * Por PK hacia atras desde MAX(id), para en la primera fila vieja; como mucho CONSUMO_VENTANA_IDS filas (sin candados).
 * ponytail: una transaccion del POS abierta mas de 3 min (o filas con hora en otro formato) aun puede saltarse;
 * si toda la ventana es reciente o ilegible se toma el inicio de la ventana (20000 filas atras = viejo de sobra).
 */
function consumo_tope($bd, $desdeId, $max) {
    $ini = max((int)$desdeId, (int)$max - CONSUMO_VENTANA_IDS);
    if ($max <= $ini) { return (int)$desdeId; }
    $r = log_filas($bd, "SELECT idproducto_historial AS m FROM producto_historial
            WHERE idproducto_historial > ? AND idproducto_historial <= ? AND hora <> 'POR DIA'
              AND STR_TO_DATE(CONCAT(LEFT(fecha, 10), ' ', hora), '%Y-%m-%d %h:%i %p') <= NOW() - INTERVAL " . CONSUMO_MARGEN_MIN . " MINUTE
            ORDER BY idproducto_historial DESC LIMIT 1",
        array($ini, (int)$max));
    if ($r && $r[0]['m'] !== null) { return (int)$r[0]['m']; }
    return $ini > (int)$desdeId ? $ini : (int)$desdeId;
}

/**
 * Trae al resumen lo nuevo de producto_historial para las sedes de la org.
 * Siempre POR SEDE con rango (idsede = ? AND idproducto_historial > ?): usa idx_sede (idsede + PK implicita) y
 * lee solo filas de esa sede, nunca las de las otras 587 sedes ni el historial completo (prod > 20M filas).
 * Primera vez: desde el id de hace 60 dias (biseccion). Despues: desde el cursor de la org.
 * GET_LOCK evita contar dos veces si dos usuarios abren la pantalla a la vez (el otro sigue sin esperar).
 * Sin bloquear al POS: se LEE producto_historial con SELECT simple (lectura consistente, sin candados; un
 * INSERT...SELECT en REPEATABLE READ pone candados compartidos sobre las filas leidas y frena las ventas) y luego se
 * escribe en una transaccion corta que solo toca log_consumo_dia + el cursor (todo o nada: el cursor es uno por org).
 * No se usa READ COMMITTED: con binlog_format=STATEMENT MySQL rechaza escribir InnoDB en RC (error 1665).
 * Llamar SIN transaccion abierta (bandeja/pedidos/reponer_auto no la tienen): begin_transaction() haria commit implicito.
 * ponytail: cursor por org; una sede activada despues no recibe sus 60 dias previos (pasar a cursor por sede si hace falta).
 */
function actualizar_consumo($bd, $org) {
    $idorg = (int)$org['idorg'];
    $lock = 'log_consumo_' . $idorg;
    $r = log_filas($bd, "SELECT GET_LOCK(?, 0) AS ok", array($lock));
    if (!$r || (int)$r[0]['ok'] !== 1) { return; }
    try {
        $sedes = array_map('intval', array_column(
            log_filas($bd, "SELECT idsede FROM log_sede WHERE idorg = ? AND activo = 1", array($idorg)), 'idsede'));
        if (!$sedes) { return; }
        $max = (int)log_filas($bd, "SELECT IFNULL(MAX(idproducto_historial), 0) AS m FROM producto_historial")[0]['m'];
        $cur = log_filas($bd, "SELECT ultimo_id FROM log_consumo_cursor WHERE idorg = ?", array($idorg));
        $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(CONSUMO_DIAS_INICIALES))[0]['d'];   // reloj de MySQL
        $desdeId = $cur ? (int)$cur[0]['ultimo_id'] : primer_id_desde($bd, $desde, $max);
        $hasta = consumo_tope($bd, $desdeId, $max);
        if ($hasta <= $desdeId) { return; }
        $filas = array();
        foreach ($sedes as $s) {
            foreach (log_filas($bd, consumo_select("AND h.idsede = ? AND h.idproducto_historial > ? AND h.idproducto_historial <= ? AND h.fecha >= ?"),
                    array($s, $desdeId, $hasta, $desde)) as $f) {
                $filas[] = array((int)$f['idproducto'], $f['f'], (int)$f['idsede'], (float)$f['cantidad']);
            }
        }
        $bd->bd->begin_transaction();
        try {
            foreach (array_chunk($filas, 500) as $parte) {
                log_exec($bd, "INSERT INTO log_consumo_dia (idproducto, fecha, idsede, cantidad) VALUES "
                    . implode(',', array_fill(0, count($parte), '(?, ?, ?, ?)'))
                    . " ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)", call_user_func_array('array_merge', $parte));
            }
            log_exec($bd, "INSERT INTO log_consumo_cursor (idorg, ultimo_id, fecha_hora) VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE ultimo_id = VALUES(ultimo_id), fecha_hora = NOW()", array($idorg, $hasta));
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
    } finally {
        $bd->bd->query("DO RELEASE_LOCK('" . $bd->bd->real_escape_string($lock) . "')");
    }
}

const ESTADOS_ABIERTOS = "'enviado', 'visto', 'atendido', 'despachado'";   // pedido aun no recibido
const DIAS_PROMEDIO = 28;

/** Sugerido de un producto que la sede vende por porcion: stock en botes y venta por dia desde su primera venta (minimo 7 dias). */
function en_porciones($bd, $idsede, $idproducto, $factor) {
    $p = porcion_sede($bd, $idsede, $idproducto, $factor, DIAS_PROMEDIO);
    if (!$p) { return null; }
    $dias = $p['dias'] ? max(7, min(DIAS_PROMEDIO, $p['dias'])) : 0;
    return array('porcion' => $p['porcion'], 'stock' => round($p['stock'], 2), 'dias' => $dias, 'por_dia' => $dias ? $p['vendido'] / $dias : 0);
}

/** Cantidad sugerida. Pieza -> entero hacia arriba; kg/litros -> 1 decimal hacia arriba. */
function sugerir($porDia, $dias, $minimo, $stock, $enCamino, $porPieza) {
    $falta = $porDia * $dias + $minimo - $stock - $enCamino;
    if ($falta <= 0) { return 0; }
    return $porPieza ? (float)ceil($falta - 1e-9) : ceil($falta * 10 - 1e-9) / 10;
}

/**
 * Pedido sugerido de una sede (lo usan "Pedir a la central" y la reposicion automatica).
 * $soloBajoMinimo: solo productos con stock minimo puesto y stock <= minimo.
 * Llamar antes a actualizar_consumo().
 */
function sugerido_sede($bd, $ido, $idsede, $dias, $soloBajoMinimo) {
    // dias de historia de la sede (una org nueva no tiene 28): promedio sobre lo que hay, minimo 7
    // fechas con el reloj de MySQL (el mismo que escribe producto_historial): PHP puede estar en otra zona horaria
    $h = log_filas($bd, "SELECT DATEDIFF(CURDATE(), MIN(fecha)) + 1 AS dias FROM log_consumo_dia WHERE idsede = ?", array((int)$idsede))[0];
    $diasHist = $h['dias'] !== null ? (int)$h['dias'] : 0;
    $diasBase = $diasHist > 0 ? max(7, min(DIAS_PROMEDIO, $diasHist)) : 0;
    $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(max(0, $diasBase - 1)))[0]['d'];

    $enCamino = array();
    foreach (log_filas($bd, "SELECT d.idproducto, SUM(d.cantidad_solicitada) AS c
            FROM solicitud_producto s JOIN solicitud_producto_detalle d ON d.idsolicitud_producto = s.idsolicitud_producto
            WHERE s.idorg = ? AND s.idsede_solicita = ? AND s.estado IN (" . ESTADOS_ABIERTOS . ")
              AND s.fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND d.tipo_producto = 'almacen'
            GROUP BY d.idproducto", array((int)$ido, (int)$idsede)) as $r) {
        $enCamino[(int)$r['idproducto']] = (float)$r['c'];
    }

    $filas = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia,
            IFNULL(u.abreviatura, '') AS unidad, p.idunidad_kardex, p.stock_minimo + 0 AS stock_minimo,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto), 0) AS stock,
            IFNULL((SELECT SUM(c.cantidad) FROM log_consumo_dia c WHERE c.idproducto = p.idproducto AND c.fecha >= ?), 0) AS consumo,
            (SELECT CONCAT(lp.nombre, '|', lp.factor) FROM log_presentacion lp
              WHERE lp.idproducto_central = v.idproducto_central AND lp.activo = 1 ORDER BY lp.factor DESC LIMIT 1) AS presentacion,
            (SELECT po.porciones + 0 FROM log_producto_porcion po WHERE po.idproducto_central = v.idproducto_central) AS porciones
            FROM log_producto_central v
            JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
            LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE v.idsede = ?" . ($soloBajoMinimo ? " AND p.stock_minimo + 0 > 0 HAVING stock <= stock_minimo" : "") . "
            ORDER BY familia, p.descripcion", array($desde, (int)$idsede));

    $productos = array();
    $diasPorcion = 0;
    foreach ($filas as $f) {
        $id = (int)$f['idproducto'];
        $porc = $f['porciones'] ? en_porciones($bd, $idsede, $id, (float)$f['porciones']) : null;
        if ($porc) {
            // se vende por porcion: stock y venta de la porcion, pasados a la unidad que se pide (botes)
            $f['stock'] = $porc['stock'];
            $diasPorcion = max($diasPorcion, $porc['dias']);
            if ($soloBajoMinimo && $f['stock'] > (float)$f['stock_minimo']) { continue; }
        }
        $porDia = $porc ? $porc['por_dia'] : ($diasBase > 0 ? max(0, (float)$f['consumo']) / $diasBase : 0);
        $camino = isset($enCamino[$id]) ? $enCamino[$id] : 0;
        $productos[] = array(
            'idproducto' => $id, 'descripcion' => $f['descripcion'], 'familia' => $f['familia'], 'unidad' => $f['unidad'],
            'stock' => round((float)$f['stock'], 2), 'stock_minimo' => round(max(0, (float)$f['stock_minimo']), 2),
            'por_dia' => round($porDia, 2), 'en_camino' => $camino, 'presentacion' => $f['presentacion'],
            'porcion' => $porc ? $porc['porcion'] : null, 'porciones' => $porc ? (float)$f['porciones'] : null,
            'sugerido' => sugerir($porDia, $dias, max(0, (float)$f['stock_minimo']), (float)$f['stock'], $camino, (int)$f['idunidad_kardex'] === 0),
        );
    }
    return array('dias_historia' => max($diasBase, $diasPorcion), 'productos' => $productos);
}

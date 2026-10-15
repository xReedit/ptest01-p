<?php
/**
 * Logistica — Produccion (migracion logistica/024).
 * ?op=inicio&sede= | guardar_receta | baja_receta | producir | pedir_insumos | repartir_info | repartir | cfg | guardar_cfg
 * La central ve y maneja la produccion de cualquier sede (la planta y los locales); cada sede, solo la suya.
 * Producir: salida de insumos (almacen_ie tipo '2', cantidad negativa) + entrada del producto (tipo '1'), motivo
 * PRODUCCION, producto_historial, costo y lote con vencimiento. Todo en el almacen principal de la sede.
 * Consultas por indice: log_receta(ix_lr_sede), log_receta_insumo(ix_lri_receta), producto(producto_idorg_IDX),
 * producto_stock(idproducto), log_bandeja_linea(ix_lbl_central), log_produccion(ix_lp_sede_fecha).
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/_produccion.php';
require_once __DIR__ . '/_catalogo.php';
require_once __DIR__ . '/_repartir.php';

const PROD_MAX = 999999;

/** Sede sobre la que se trabaja: la central elige cualquiera de la empresa; una sede, solo ella misma. */
function prod_sede($bd, $org, $idsede, $pedida) {
    $central = (int)$org['idsede_central'] === (int)$idsede;
    $s = $central && $pedida ? (int)$pedida : (int)$idsede;
    $r = log_filas($bd, "SELECT ls.idsede, s.nombre, ls.idalmacen_principal FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idsede = ? AND ls.idorg = ? AND ls.activo = 1", array($s, (int)$org['idorg']));
    if (!$r) { throw new ErrorUsuario($central ? 'Esa sede no participa en Logística.' : 'Esta sede no participa en Logística. Pide a la central que la active.'); }
    if ((int)$r[0]['idalmacen_principal'] <= 0) { throw new ErrorUsuario('La sede ' . $r[0]['nombre'] . ' no tiene almacén elegido. Elígelo en Configurar.'); }
    return array('idsede' => $s, 'nombre' => $r[0]['nombre'], 'alm' => (int)$r[0]['idalmacen_principal']);
}

/** Org con produccion encendida + permiso. */
function prod_org($bd, $ido, $us) {
    $org = log_org($bd, $ido);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    log_requiere_permiso($bd, $us);
    $cfg = prod_cfg($bd, $org);
    if (!$cfg['activa']) { throw new ErrorUsuario('Producción está apagada. La central la activa en Configurar.'); }
    return $org + $cfg;
}

/** Cantidad > 0 escrita con numeros (acepta coma decimal). */
function prod_num($v, $txt) {
    $t = is_scalar($v) ? str_replace(',', '.', trim((string)$v)) : '';
    if (!is_numeric($t) || (float)$t <= 0 || (float)$t > PROD_MAX) { throw new ErrorUsuario($txt); }
    return round((float)$t, 3);
}

/** Producto activo de la sede (o error). */
function prod_de_sede($bd, $org, $s, $idprod) {
    $r = log_filas($bd, "SELECT p.idproducto, p.descripcion FROM producto p WHERE p.idproducto = ? AND p.idorg = ? AND p.idsede = ? AND p.estado = 0",
        array((int)$idprod, (int)$org['idorg'], $s['idsede']));
    if (!$r) { throw new ErrorUsuario('Ese producto no es de ' . $s['nombre'] . '. Elígelo de la lista.'); }
    return $r[0];
}

/**
 * Lo que sale de una receta nueva, si aun no existe (dentro de la transaccion, con el catalogo bloqueado). Familia PRODUCCION.
 * En la planta va al catalogo central y se copia a todas las sedes (asi pueden pedirlo); en un local, solo en ese local.
 * $n = {descripcion, idunidad_kardex}. Devuelve el idproducto de la sede.
 */
function prod_crear_salida($bd, $org, $s, $n) {
    $c = array('descripcion' => log_mayus(isset($n['descripcion']) ? $n['descripcion'] : '', 150), 'familia' => 'PRODUCCION', 'codigo_barra' => '',
        'unidad' => null, 'idunidad_kardex' => isset($n['idunidad_kardex']) ? (int)$n['idunidad_kardex'] : 0, 'precio_venta' => null);
    if ($c['descripcion'] === '') { throw new ErrorUsuario('Escribe el nombre del producto que sale.'); }
    bloquear_catalogo_org($bd, $org['idorg']);   // doble clic: el segundo ve el producto ya creado
    $planta = $s['idsede'] === (int)$org['idsede_planta'];
    $donde = $planta ? (int)$org['idsede_central'] : $s['idsede'];
    $ya = log_filas($bd, "SELECT idproducto FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ? LIMIT 1",
        array((int)$org['idorg'], $donde, $c['descripcion']));
    $id = $ya ? (int)$ya[0]['idproducto'] : crear_en_central($bd, $org['idorg'], $donde, $c);
    if (!$planta) { return $id; }
    replicar_lista($bd, $org['idorg'], $org, array($id), false);
    if ($donde === $s['idsede']) { return $id; }   // la planta es la central
    $copia = log_filas($bd, "SELECT idproducto FROM log_producto_central WHERE idsede = ? AND idproducto_central = ?", array($s['idsede'], $id));
    if (!$copia) {
        throw new ErrorUsuario('En ' . $s['nombre'] . ' ya hay un ' . $c['descripcion'] . ' propio sin vincular. Vincúlalo en Productos de las sedes o elígelo de la lista.');
    }
    return (int)$copia[0]['idproducto'];
}

/** Producto central de un producto de la sede (la central es el propio); 0 = propio de la sede, no esta en el catalogo. */
function prod_central_de($bd, $org, $idsede, $idprod) {
    if ((int)$idsede === (int)$org['idsede_central']) { return (int)$idprod; }
    $r = log_filas($bd, "SELECT idproducto_central FROM log_producto_central WHERE idproducto = ?", array((int)$idprod));
    return $r ? (int)$r[0]['idproducto_central'] : 0;
}

/**
 * Lo que ya esta pedido y aun no llega, para no pedirlo dos veces. Central: lineas 'compra' sin terminar para la central
 * (ix_lbl_central). Sede: sus pedidos abiertos a la central (idx_producto del detalle).
 */
function prod_ya_pedido($bd, $org, $idsede, $idprod, $idcentral) {
    if ((int)$idsede === (int)$org['idsede_central']) {
        return (float)log_filas($bd, "SELECT IFNULL(SUM(cantidad), 0) AS q FROM log_bandeja_linea
                WHERE idproducto_central = ? AND estado <> 'hecho' AND decision = 'compra' AND idsede = ? AND idorg = ?",
            array((int)$idcentral, (int)$idsede, (int)$org['idorg']))[0]['q'];
    }
    return (float)log_filas($bd, "SELECT IFNULL(SUM(d.cantidad_solicitada), 0) AS q FROM solicitud_producto_detalle d
            JOIN solicitud_producto s ON s.idsolicitud_producto = d.idsolicitud_producto
            WHERE d.idproducto = ? AND s.idsede_solicita = ? AND s.estado IN ('enviado', 'visto', 'atendido', 'despachado')",
        array((int)$idprod, (int)$idsede))[0]['q'];
}

/** Receta de la planta lista para repartir: {rec, c (producto central)} o error claro. */
function prod_receta_planta($bd, $org, $s, $idreceta) {
    if ($s['idsede'] !== (int)$org['idsede_planta']) { throw new ErrorUsuario('Solo la planta reparte a las sedes. Lo que produce un local queda en su almacén.'); }
    $rec = log_filas($bd, "SELECT r.idreceta, r.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM log_receta r
            JOIN producto p ON p.idproducto = r.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE r.idreceta = ? AND r.idsede = ? AND r.activo = 1", array((int)$idreceta, $s['idsede']));
    if (!$rec) { throw new ErrorUsuario('Esa receta ya no existe. Actualiza la página.'); }
    $c = prod_central_de($bd, $org, $s['idsede'], $rec[0]['idproducto']);
    if (!$c) { throw new ErrorUsuario($rec[0]['descripcion'] . ' no está en el catálogo: las sedes no lo tienen. Vincúlalo en Productos de las sedes.'); }
    return array('rec' => $rec[0], 'c' => $c);
}

/** Recetas de la sede con su stock, insumos y (en la planta) lo que piden las sedes. */
function prod_recetas($bd, $org, $s) {
    $rec = log_filas($bd, "SELECT r.idreceta, r.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, r.rinde + 0 AS rinde, r.vence_dias, r.nota,
            IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = r.idproducto AND ps.idalmacen = ?), 0) + 0 AS stock
            FROM log_receta r JOIN producto p ON p.idproducto = r.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            WHERE r.idsede = ? AND r.activo = 1 ORDER BY p.descripcion", array($s['alm'], $s['idsede']));
    $esPlanta = $s['idsede'] === (int)$org['idsede_planta'];
    foreach ($rec as &$r) {
        $r['insumos'] = log_filas($bd, "SELECT i.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, i.cantidad + 0 AS cantidad,
                IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = i.idproducto AND ps.idalmacen = ?), 0) + 0 AS stock
                FROM log_receta_insumo i JOIN producto p ON p.idproducto = i.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE i.idreceta = ? ORDER BY i.id", array($s['alm'], (int)$r['idreceta']));
        $costo = 0;
        foreach ($r['insumos'] as &$i) {
            $i['costo'] = prod_costo($bd, $i['idproducto']);
            $costo += $i['costo'] * (float)$i['cantidad'];
            $i['idcentral'] = prod_central_de($bd, $org, $s['idsede'], $i['idproducto']);
            $i['pedido'] = $i['idcentral'] ? prod_ya_pedido($bd, $org, $s['idsede'], $i['idproducto'], $i['idcentral']) : 0;
        }
        unset($i);
        $r['costo_unidad'] = (float)$r['rinde'] > 0 ? round($costo / (float)$r['rinde'], 4) : 0;
        $r['pendiente'] = 0;
        if ($esPlanta) {
            // lo que la Bandeja mando a producir aqui y aun no sale (producto central = el propio o su vinculo)
            $c = $s['idsede'] === (int)$org['idsede_central'] ? (int)$r['idproducto']
                : (int)(log_filas($bd, "SELECT idproducto_central FROM log_producto_central WHERE idproducto = ?", array((int)$r['idproducto']))[0]['idproducto_central'] ?? 0);
            if ($c) {
                $r['pendiente'] = (float)log_filas($bd, "SELECT IFNULL(SUM(cantidad), 0) AS q FROM log_bandeja_linea
                        WHERE idproducto_central = ? AND estado = 'pendiente' AND decision = 'produccion' AND idsede_origen = ? AND idtraslado IS NULL AND idorg = ?",
                    array($c, $s['idsede'], (int)$org['idorg']))[0]['q'];
            }
        }
        $r['sugerido'] = max(0, round($r['pendiente'] - (float)$r['stock'], 3));
    }
    unset($r);
    return $rec;
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'inicio':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $central = (int)$org['idsede_central'] === (int)$idsede;
        $s = prod_sede($bd, $org, $idsede, isset($_GET['sede']) ? $_GET['sede'] : ($central ? $org['idsede_planta'] : 0));
        $planta = log_filas($bd, "SELECT nombre FROM sede WHERE idsede = ?", array((int)$org['idsede_planta']));
        return array(
            'central' => $central, 'idsede' => $s['idsede'], 'sede' => $s['nombre'], 'idsede_central' => (int)$org['idsede_central'],
            'planta' => array('idsede' => (int)$org['idsede_planta'], 'nombre' => $planta ? $planta[0]['nombre'] : ''),
            'es_planta' => $s['idsede'] === (int)$org['idsede_planta'],
            'sedes' => $central ? log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                    WHERE ls.idorg = ? AND ls.activo = 1 ORDER BY ls.idsede <> ?, s.nombre", array((int)$ido, (int)$org['idsede_planta'])) : array(),
            'recetas' => prod_recetas($bd, $org, $s),
            'unidades' => log_filas($bd, "SELECT idunidad_medida, unidad, abreviatura FROM unidad_medida WHERE estado = 0
                    AND (idorg IS NULL OR idorg = ?) ORDER BY unidad", array((int)$ido)),
            'productos' => log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto p
                    LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                    WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 ORDER BY p.descripcion LIMIT 5000", array((int)$ido, $s['idsede'])),
            'historial' => log_filas($bd, "SELECT pr.idproduccion, DATE_FORMAT(pr.fecha_hora, '%d/%m/%Y %H:%i') AS fecha, p.descripcion,
                    IFNULL(u.abreviatura, '') AS unidad, pr.cantidad_esperada + 0 AS esperada, pr.cantidad_real + 0 AS salio, pr.costo_total + 0 AS costo,
                    DATE_FORMAT(pr.vencimiento, '%d/%m/%Y') AS vence, pr.nota, IFNULL(NULLIF(TRIM(us.nombres), ''), us.usuario) AS quien
                    FROM log_produccion pr JOIN producto p ON p.idproducto = pr.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                    LEFT JOIN usuario us ON us.idusuario = pr.idusuario
                    WHERE pr.idsede = ? ORDER BY pr.fecha_hora DESC LIMIT 30", array($s['idsede'])),
        );
    });

case 'guardar_receta':
    // {sede, idreceta?, idproducto, rinde, vence_dias, nota, insumos:[{idproducto, cantidad}]}
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $b = log_body();
        $s = prod_sede($bd, $org, $idsede, isset($b['sede']) ? $b['sede'] : 0);
        $nuevo = empty($b['idproducto']) && isset($b['nuevo']) && is_array($b['nuevo']) ? $b['nuevo'] : null;
        $sal = $nuevo ? array('idproducto' => 0, 'descripcion' => log_mayus(isset($nuevo['descripcion']) ? $nuevo['descripcion'] : '', 150))
            : prod_de_sede($bd, $org, $s, isset($b['idproducto']) ? $b['idproducto'] : 0);
        if ($nuevo) { $nuevo['idunidad_kardex'] = unidad_valida($bd, $ido, isset($nuevo['idunidad_kardex']) ? $nuevo['idunidad_kardex'] : 0); }
        $rinde = prod_num(isset($b['rinde']) ? $b['rinde'] : '', 'Escribe cuánto sale con esta receta (ej. 5).');
        $vence = isset($b['vence_dias']) && $b['vence_dias'] !== '' && $b['vence_dias'] !== null ? (int)$b['vence_dias'] : null;
        if ($vence !== null && ($vence < 1 || $vence > 3650)) { throw new ErrorUsuario('Los días que dura deben ser entre 1 y 3650 (o déjalo vacío si no vence).'); }
        $nota = isset($b['nota']) && is_string($b['nota']) ? trim($b['nota']) : '';
        if (mb_strlen($nota) > 255) { throw new ErrorUsuario('La nota es muy larga (máximo 255 letras).'); }
        $ins = isset($b['insumos']) && is_array($b['insumos']) ? array_slice($b['insumos'], 0, 60) : array();
        $vistos = array(); $filas = array();
        foreach ($ins as $i) {
            $p = prod_de_sede($bd, $org, $s, is_array($i) && isset($i['idproducto']) ? $i['idproducto'] : 0);
            if ((int)$p['idproducto'] === (int)$sal['idproducto'] || ($nuevo && log_mayus($p['descripcion'], 150) === $sal['descripcion'])) {
                throw new ErrorUsuario($p['descripcion'] . ' no puede ser insumo de sí mismo.');
            }
            if (isset($vistos[$p['idproducto']])) { throw new ErrorUsuario($p['descripcion'] . ' está dos veces. Déjalo una sola vez.'); }
            $vistos[$p['idproducto']] = true;
            $filas[] = array((int)$p['idproducto'], prod_num(isset($i['cantidad']) ? $i['cantidad'] : '', 'Escribe la cantidad de ' . $p['descripcion'] . '.'));
        }
        if (!$filas) { throw new ErrorUsuario('Agrega al menos un insumo.'); }
        $id = isset($b['idreceta']) ? (int)$b['idreceta'] : 0;
        $bd->bd->begin_transaction();
        try {
            if ($nuevo) {
                $sal['idproducto'] = prod_crear_salida($bd, $org, $s, $nuevo);
                if (isset($vistos[$sal['idproducto']])) { throw new ErrorUsuario($sal['descripcion'] . ' no puede ser insumo de sí mismo.'); }
            }
            if ($id) {
                $r = log_filas($bd, "SELECT idreceta FROM log_receta WHERE idreceta = ? AND idsede = ? AND activo = 1 FOR UPDATE", array($id, $s['idsede']));
                if (!$r) { throw new ErrorUsuario('Esa receta ya no existe. Actualiza la página.'); }
            }
            // un producto, una receta activa por sede: si ya hay otra, se edita esa
            $otra = log_filas($bd, "SELECT idreceta FROM log_receta WHERE idsede = ? AND idproducto = ? AND activo = 1 AND idreceta <> ? FOR UPDATE",
                array($s['idsede'], (int)$sal['idproducto'], $id));
            if ($otra) { throw new ErrorUsuario($sal['descripcion'] . ' ya tiene receta en ' . $s['nombre'] . '. Edita esa.'); }
            if ($id) {
                log_exec($bd, "UPDATE log_receta SET idproducto = ?, rinde = ?, vence_dias = ?, nota = ?, idusuario = ?, fecha_hora = NOW() WHERE idreceta = ?",
                    array((int)$sal['idproducto'], $rinde, $vence, $nota === '' ? null : $nota, (int)$us, $id));
                log_exec($bd, "DELETE FROM log_receta_insumo WHERE idreceta = ?", array($id));
            } else {
                log_exec($bd, "INSERT INTO log_receta (idorg, idsede, idproducto, rinde, vence_dias, nota, idusuario) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    array((int)$ido, $s['idsede'], (int)$sal['idproducto'], $rinde, $vence, $nota === '' ? null : $nota, (int)$us));
                $id = (int)$bd->lastInsertId();
            }
            foreach ($filas as $f) { log_exec($bd, "INSERT INTO log_receta_insumo (idreceta, idproducto, cantidad) VALUES (?, ?, ?)", array($id, $f[0], $f[1])); }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('idreceta' => $id, 'idproducto' => (int)$sal['idproducto'], 'creado' => (bool)$nuevo);
    });

case 'baja_receta':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $b = log_body();
        $s = prod_sede($bd, $org, $idsede, isset($b['sede']) ? $b['sede'] : 0);
        log_exec($bd, "UPDATE log_receta SET activo = 0, idusuario = ?, fecha_hora = NOW() WHERE idreceta = ? AND idsede = ?",
            array((int)$us, isset($b['idreceta']) ? (int)$b['idreceta'] : 0, $s['idsede']));
        return array('ok' => true);
    });

case 'producir':
    // {sede, idreceta, cantidad (lo que se quiere sacar), salio (lo que salio de verdad), insumos?:[{idproducto, cantidad}],
    //  vencimiento 'YYYY-MM-DD'?, nota, token}
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $b = log_body();
        $s = prod_sede($bd, $org, $idsede, isset($b['sede']) ? $b['sede'] : 0);
        $rec = log_filas($bd, "SELECT r.*, p.descripcion FROM log_receta r JOIN producto p ON p.idproducto = r.idproducto
                WHERE r.idreceta = ? AND r.idsede = ? AND r.activo = 1", array(isset($b['idreceta']) ? (int)$b['idreceta'] : 0, $s['idsede']));
        if (!$rec) { throw new ErrorUsuario('Esa receta ya no existe. Actualiza la página.'); }
        $rec = $rec[0];
        $cant = prod_num(isset($b['cantidad']) ? $b['cantidad'] : '', 'Escribe cuánto vas a producir.');
        $salio = prod_num(isset($b['salio']) ? $b['salio'] : $cant, 'Escribe cuánto salió de verdad.');
        $nota = isset($b['nota']) && is_string($b['nota']) ? trim($b['nota']) : '';
        if (mb_strlen($nota) > 255) { throw new ErrorUsuario('La nota es muy larga (máximo 255 letras).'); }
        $vence = isset($b['vencimiento']) && is_string($b['vencimiento']) ? trim($b['vencimiento']) : '';
        if ($vence !== '') {
            $d = DateTime::createFromFormat('!Y-m-d', $vence);
            if (!$d || $d->format('Y-m-d') !== $vence || $vence < date('Y-m-d')) { throw new ErrorUsuario('La fecha de vencimiento no es válida (no puede ser pasada).'); }
        } elseif ($rec['vence_dias']) {
            $vence = date('Y-m-d', strtotime('+' . (int)$rec['vence_dias'] . ' days'));
        }
        // insumos: lo que dice la receta a escala, o lo que el usuario anoto que uso de verdad (solo de esta receta)
        $base = array();
        foreach (log_filas($bd, "SELECT i.idproducto, i.cantidad + 0 AS cantidad, p.descripcion FROM log_receta_insumo i JOIN producto p ON p.idproducto = i.idproducto
                WHERE i.idreceta = ?", array((int)$rec['idreceta'])) as $i) {
            $base[(int)$i['idproducto']] = array('q' => round((float)$i['cantidad'] * $cant / (float)$rec['rinde'], 3), 'nom' => $i['descripcion']);
        }
        if (isset($b['insumos']) && is_array($b['insumos'])) {
            foreach ($b['insumos'] as $i) {
                $p = is_array($i) && isset($i['idproducto']) ? (int)$i['idproducto'] : 0;
                if (!isset($base[$p])) { throw new ErrorUsuario('Hay un insumo que no es de esta receta. Actualiza la página.'); }
                $t = isset($i['cantidad']) && is_scalar($i['cantidad']) ? str_replace(',', '.', trim((string)$i['cantidad'])) : '';
                if (!is_numeric($t) || (float)$t < 0 || (float)$t > PROD_MAX) { throw new ErrorUsuario('Revisa la cantidad usada de ' . $base[$p]['nom'] . '.'); }
                $base[$p]['q'] = round((float)$t, 3);
            }
        }
        if (array_sum(array_column($base, 'q')) <= 0) { throw new ErrorUsuario('No se usa ningún insumo: revisa las cantidades.'); }
        $token = log_token($b);
        $motS = motivo_id($bd, 'PRODUCCION', 2, 0);
        $motE = motivo_id($bd, 'PRODUCCION', 1, 0);
        if (!$motS || !$motE) { throw new ErrorUsuario('Falta configurar el motivo de almacén "PRODUCCION". Avisa a soporte.'); }
        $alm = $s['alm'];
        $ie = function ($tipo, $mot, $txt) use ($bd, $ido, $s, $us, $alm) {
            log_exec($bd, "INSERT INTO almacen_ie (idorg, idsede, idusuario, idalmacen, tipo, fecha, idproducto_motivo_es, motivo, fecha_hora, hora)
                    VALUES (?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%d/%m/%Y'), ?, ?, NOW(), DATE_FORMAT(NOW(), '%H:%i:%s'))",
                array((int)$ido, $s['idsede'], (int)$us, $alm, $tipo, $mot, mb_substr($txt, 0, 100, 'UTF-8')));
            return (int)$bd->lastInsertId();
        };
        $det = function ($idie, $prod, $q, $hist) use ($bd, $s, $us, $alm) {
            // el trigger de almacen_ie_detalle solo hace UPDATE: la fila de stock debe existir
            log_exec($bd, "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado) SELECT ?, ?, 0, 0 FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM producto_stock WHERE idproducto = ? AND idalmacen = ?)", array($prod, $alm, $prod, $alm));
            log_exec($bd, "INSERT INTO almacen_ie_detalle (idalmacen_ie, idp, procede, cantidad) VALUES (?, ?, 0, ?)", array($idie, $prod, txt_cant($q)));
            log_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                    SELECT ?, CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock FROM producto_stock ps
                    WHERE ps.idproducto = ? AND ps.idalmacen = ? ORDER BY ps.idproducto_stock LIMIT 1",
                array($hist, txt_cant(abs($q)), (int)$us, $s['idsede'], $prod, $alm, $prod, $alm));
        };
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'produccion', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            // stock de los insumos, bloqueado: no se usa lo que no hay
            $costo = 0; $usados = array();
            foreach ($base as $p => $x) {
                if ($x['q'] <= 0) { continue; }
                $st = log_filas($bd, "SELECT stock FROM producto_stock WHERE idproducto = ? AND idalmacen = ? ORDER BY idproducto_stock LIMIT 1 FOR UPDATE", array($p, $alm));
                $hay = $st ? (float)$st[0]['stock'] : 0;
                if ($x['q'] > $hay + 0.0001) { throw new ErrorUsuario('De ' . $x['nom'] . ' solo hay ' . txt_cant($hay) . ' y la receta usa ' . txt_cant($x['q']) . '.'); }
                $cu = prod_costo($bd, $p);
                $costo += $cu * $x['q'];
                $usados[] = array($p, $x['q'], $cu);
            }
            log_exec($bd, "INSERT INTO log_produccion (idorg, idsede, idreceta, idproducto, idalmacen, cantidad_esperada, cantidad_real, costo_total, vencimiento, nota, idusuario)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array((int)$ido, $s['idsede'], (int)$rec['idreceta'], (int)$rec['idproducto'], $alm, $cant, $salio, round($costo, 4),
                      $vence === '' ? null : $vence, $nota === '' ? null : $nota, (int)$us));
            $idp = (int)$bd->lastInsertId();
            $sal = $ie('2', $motS, 'PRODUCCION #' . $idp . ' INSUMOS DE ' . $rec['descripcion']);
            foreach ($usados as $u) {
                $det($sal, $u[0], -$u[1], 'PRODUCCION SALIDA');   // producto_historial.tipo_movimiento: 20 caracteres
                log_exec($bd, "INSERT INTO log_produccion_insumo (idproduccion, idproducto, cantidad, costo) VALUES (?, ?, ?, ?)", array($idp, $u[0], $u[1], $u[2]));
            }
            // para el costo promedio: lo que habia antes de que entre lo producido
            $antes = log_filas($bd, "SELECT IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto AND ps.idalmacen = ?), 0) AS stock,
                    p.precio_unitario + 0 AS costo FROM producto p WHERE p.idproducto = ?", array($alm, (int)$rec['idproducto']))[0];
            $ent = $ie('1', $motE, 'PRODUCCION #' . $idp . ' ' . $rec['descripcion']);
            $det($ent, (int)$rec['idproducto'], $salio, 'PRODUCCION ENTRADA');
            // costo de lo producido (promedio con lo que ya habia): el del producto de la sede y, si es la planta, tambien el del central (lo usa Envios);
            // lo que produce un local para si no cambia el costo de toda la cadena
            $q0 = max(0, (float)$antes['stock']); $c0 = (float)$antes['costo'];
            $cu = round($q0 > 0 && $c0 > 0 ? ($q0 * $c0 + $costo) / ($q0 + $salio) : $costo / $salio, 4);
            if ($cu > 0) {
                log_exec($bd, "UPDATE producto SET precio_unitario = ? WHERE idproducto = ?", array($cu, (int)$rec['idproducto']));
                if ($s['idsede'] === (int)$org['idsede_planta']) {
                    log_exec($bd, "UPDATE producto p JOIN log_producto_central v ON v.idproducto_central = p.idproducto SET p.precio_unitario = ?
                            WHERE v.idproducto = ?", array($cu, (int)$rec['idproducto']));
                }
            }
            // lote con vencimiento (los lotes son por producto central: si el producido no esta en el catalogo, no hay lote)
            $idlote = null;
            if ($vence !== '') {
                $c = $s['idsede'] === (int)$org['idsede_central'] ? (int)$rec['idproducto']
                    : (int)(log_filas($bd, "SELECT idproducto_central FROM log_producto_central WHERE idproducto = ?", array((int)$rec['idproducto']))[0]['idproducto_central'] ?? 0);
                if ($c) {
                    log_exec($bd, "INSERT INTO log_lote (idorg, idproducto_central, lote, vencimiento) VALUES (?, ?, ?, ?)", array((int)$ido, $c, 'PROD-' . $idp, $vence));
                    $idlote = (int)$bd->lastInsertId();
                    log_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'produccion', ?)", array($idlote, $alm, $salio, $idp));
                }
            }
            log_exec($bd, "UPDATE log_produccion SET idalmacen_ie_salida = ?, idalmacen_ie_entrada = ?, idlote = ? WHERE idproduccion = ?", array($sal, $ent, $idlote, $idp));
            $res = array('idproduccion' => $idp, 'producto' => $rec['descripcion'], 'salio' => $salio, 'costo' => round($costo, 2), 'costo_unidad' => $cu,
                'vence' => $vence === '' ? null : date('d/m/Y', strtotime($vence)));
            log_token_cerrar($bd, 'produccion', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'pedir_insumos':
    // {sede, idreceta, items:[{idproducto, cantidad}], token}: lo que falta para producir.
    // En la central -> "Por comprar" (Ordenes de compra). En otra sede -> un pedido a la central (la Bandeja decide).
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $b = log_body();
        $s = prod_sede($bd, $org, $idsede, isset($b['sede']) ? $b['sede'] : 0);
        $rec = log_filas($bd, "SELECT r.idreceta, p.descripcion FROM log_receta r JOIN producto p ON p.idproducto = r.idproducto
                WHERE r.idreceta = ? AND r.idsede = ? AND r.activo = 1", array(isset($b['idreceta']) ? (int)$b['idreceta'] : 0, $s['idsede']));
        if (!$rec) { throw new ErrorUsuario('Esa receta ya no existe. Actualiza la página.'); }
        $ins = array();
        foreach (log_filas($bd, "SELECT i.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM log_receta_insumo i
                JOIN producto p ON p.idproducto = i.idproducto LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE i.idreceta = ?", array((int)$rec[0]['idreceta'])) as $i) { $ins[(int)$i['idproducto']] = $i; }
        $items = array();
        foreach ((isset($b['items']) && is_array($b['items']) ? array_slice($b['items'], 0, 60) : array()) as $it) {
            $p = is_array($it) && isset($it['idproducto']) ? (int)$it['idproducto'] : 0;
            if (!isset($ins[$p])) { throw new ErrorUsuario('Hay un insumo que no es de esta receta. Actualiza la página.'); }
            $t = isset($it['cantidad']) && is_scalar($it['cantidad']) ? str_replace(',', '.', trim((string)$it['cantidad'])) : '';
            if ($t === '' || (is_numeric($t) && (float)$t == 0)) { continue; }
            $q = prod_num($t, 'Revisa la cantidad de ' . $ins[$p]['descripcion'] . '.');
            $c = prod_central_de($bd, $org, $s['idsede'], $p);
            if (!$c) { throw new ErrorUsuario($ins[$p]['descripcion'] . ' es propio de ' . $s['nombre'] . ' (no está en el catálogo): cómpralo en Compras.'); }
            $items[$p] = array('q' => $q, 'c' => $c);
        }
        if (!$items) { throw new ErrorUsuario('Pon la cantidad en al menos un insumo.'); }
        $central = $s['idsede'] === (int)$org['idsede_central'];
        if ($central) { log_requiere_central($bd, $ido, $idsede, $us); }   // solo la central decide comprar
        $token = log_token($b);
        $bd->bd->begin_transaction();
        try {
            if ($previo = log_token_reservar($bd, 'prod_insumos', $token, $ido)) { $bd->bd->rollback(); return $previo; }
            if ($central) {
                // decision 'compra' sin pedido (como Stock parado / Por vencer): aparece en Por comprar
                foreach ($items as $p => $x) {
                    log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision, cantidad, idusuario)
                            VALUES (?, 0, 0, ?, ?, 'compra', ?, ?)", array((int)$ido, $s['idsede'], $x['c'], $x['q'], (int)$us));
                }
                $res = array('donde' => 'compra', 'productos' => count($items));
            } else {
                $stock = function ($p) use ($bd, $s) {
                    return (float)log_filas($bd, "SELECT IFNULL(SUM(stock), 0) AS q FROM producto_stock WHERE idproducto = ? AND idalmacen = ?", array($p, $s['alm']))[0]['q'];
                };
                log_exec($bd, "INSERT INTO solicitud_producto (idorg, idsede_solicita, idsede_destino, fecha_solicitud, estado, nota, idusuario_solicita)
                        VALUES (?, ?, ?, NOW(), 'enviado', ?, ?)",
                    array((int)$ido, $s['idsede'], (int)$org['idsede_central'], mb_substr('Para producir ' . $rec[0]['descripcion'], 0, 500, 'UTF-8'), (int)$us));
                $idsol = (int)$bd->lastInsertId();
                foreach ($items as $p => $x) {
                    log_exec($bd, "INSERT INTO solicitud_producto_detalle (idsolicitud_producto, tipo_producto, idproducto, descripcion,
                            cantidad_solicitada, cantidad_despachada, stock_actual, unidad_medida) VALUES (?, 'almacen', ?, ?, ?, 0, ?, ?)",
                        array($idsol, $p, $ins[$p]['descripcion'], $x['q'], round($stock($p), 2), $ins[$p]['unidad']));
                }
                $res = array('donde' => 'pedido', 'idsolicitud' => $idsol, 'productos' => count($items));
            }
            log_token_cerrar($bd, 'prod_insumos', $token, $res);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'repartir_info':
    // GET &sede=&idreceta=: cuanto hay libre en la planta y cuanto tiene cada sede de ese producto
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $s = prod_sede($bd, $org, $idsede, isset($_GET['sede']) ? $_GET['sede'] : 0);
        $x = prod_receta_planta($bd, $org, $s, isset($_GET['idreceta']) ? $_GET['idreceta'] : 0);
        return rep_libre($bd, $org, $s, $x['rec']['idproducto'], $x['c'])
            + array('producto' => $x['rec']['descripcion'], 'unidad' => $x['rec']['unidad'], 'sedes' => rep_sedes($bd, $org, $s, $x['c']));
    });

case 'repartir':
    // {sede (la planta), idreceta, items:[{idsede, cantidad}], token}: lo producido sale a las sedes por Envios (_repartir.php)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = prod_org($bd, $ido, $us);
        $b = log_body();
        $s = prod_sede($bd, $org, $idsede, isset($b['sede']) ? $b['sede'] : 0);
        $x = prod_receta_planta($bd, $org, $s, isset($b['idreceta']) ? $b['idreceta'] : 0);
        return rep_guardar($bd, $org, $s, $x['c'], $x['rec']['idproducto'], $x['rec']['descripcion'], $x['rec']['unidad'],
            isset($b['items']) ? $b['items'] : array(), $us, log_token($b));
    });

case 'cfg':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $cfg = prod_cfg($bd, $org);
        return $cfg + array('sedes' => log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                WHERE ls.idorg = ? AND ls.activo = 1 ORDER BY ls.idsede <> ?, s.nombre", array((int)$ido, (int)$org['idsede_central'])),
            'idsede_central' => (int)$org['idsede_central']);
    });

case 'guardar_cfg':
    // {activa, idsede_planta}
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $p = isset($b['idsede_planta']) ? (int)$b['idsede_planta'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM log_sede WHERE idsede = ? AND idorg = ? AND activo = 1", array($p, (int)$ido))) {
            throw new ErrorUsuario('Elige la sede donde está la planta de producción.');
        }
        log_exec($bd, "UPDATE org_logistica SET produccion_activa = ?, idsede_planta = ? WHERE idorg = ?",
            array(empty($b['activa']) ? 0 : 1, $p === (int)$org['idsede_central'] ? null : $p, (int)$ido));
        return prod_cfg($bd, $org);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

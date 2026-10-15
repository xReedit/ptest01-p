<?php
/**
 * Funciones del catalogo central (las usan catalogo.php y vincular.php). Incluido despues de _logistica.php.
 */
if (!function_exists('log_filas')) { http_response_code(404); exit; }   // no se abre directo
const CAMPOS_PRODUCTO = "p.idproducto, p.descripcion, p.codigo_barra, p.precio_venta, p.idunidad_kardex,
    p.idproducto_familia, p.venta_x_peso, p.para_receta, p.img, IFNULL(pf.descripcion, '') AS familia";

/** Familia por nombre en la sede (se crea si no existe, como log_compras 'producto-nuevo'). */
function familia_en_sede($bd, $ido, $idsede, $nombre) {
    $nombre = log_mayus($nombre, 80);
    if ($nombre === '') { return ''; }
    $sql = "SELECT idproducto_familia FROM producto_familia WHERE idorg = ? AND idsede = ? AND estado = 0
             AND UPPER(descripcion) = ? ORDER BY idproducto_familia LIMIT 1";
    $f = log_filas($bd, $sql, array($ido, $idsede, $nombre));
    if (!$f) {
        // el trigger setid asigna el id ('f' + correlativo)
        log_exec($bd, "INSERT INTO producto_familia (idproducto_familia, idorg, idsede, descripcion, estado)
                       VALUES ('0', ?, ?, ?, 0)", array($ido, $idsede, $nombre));
        $f = log_filas($bd, $sql, array($ido, $idsede, $nombre));
        if (!$f) { throw new Exception('familia creada sin id'); }
    }
    return $f[0]['idproducto_familia'];
}

function producto_central($bd, $org, $idproducto) {
    $r = log_filas($bd, "SELECT " . CAMPOS_PRODUCTO . " FROM producto p
            LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
            WHERE p.idproducto = ? AND p.idorg = ? AND p.idsede = ? AND p.estado = 0",
        array((int)$idproducto, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Producto no encontrado en el catálogo de la central.'); }
    return $r[0];
}

/** Crea el producto en la sede y lo enlaza. Devuelve 'creado' | 'ya' | 'conflicto' (mismo nombre sin vincular). */
function replicar_en_sede($bd, $ido, $prod, $sede) {
    $idsede = (int)$sede['idsede'];
    $ya = log_filas($bd, "SELECT idproducto FROM log_producto_central WHERE idsede = ? AND idproducto_central = ?",
        array($idsede, (int)$prod['idproducto']));
    if ($ya) { return 'ya'; }
    $mismo = log_filas($bd, "SELECT p.idproducto FROM producto p
            LEFT JOIN log_producto_central v ON v.idproducto = p.idproducto
            WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 AND UPPER(p.descripcion) = ? AND v.idproducto IS NULL LIMIT 1",
        array($ido, $idsede, log_mayus($prod['descripcion'], 150)));
    if ($mismo) { return 'conflicto'; }

    $fam = familia_en_sede($bd, $ido, $idsede, $prod['familia']);
    log_exec($bd, "INSERT INTO producto (idorg, idsede, descripcion, codigo_barra, precio, precio_unitario, precio_venta,
            idproducto_familia, stock_minimo, img, estado, idunidad_kardex, venta_x_peso, para_receta)
            VALUES (?, ?, ?, ?, '0', '0', ?, ?, '0', ?, 0, ?, ?, ?)",
        array($ido, $idsede, $prod['descripcion'], (string)$prod['codigo_barra'], (string)$prod['precio_venta'], $fam,
              (string)$prod['img'], (int)$prod['idunidad_kardex'], (string)$prod['venta_x_peso'], (string)$prod['para_receta']));
    $idnuevo = (int)$bd->lastInsertId();
    log_exec($bd, "INSERT INTO log_producto_central (idproducto, idproducto_central, idorg, idsede) VALUES (?, ?, ?, ?)",
        array($idnuevo, (int)$prod['idproducto'], $ido, $idsede));
    $alm = (int)$sede['idalmacen_principal'];
    if ($alm > 0) {
        // producto_stock no tiene unico (idproducto, idalmacen) en prod: se revisa antes de insertar
        log_exec($bd, "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado)
                SELECT ?, ?, 0, 0 FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM producto_stock WHERE idproducto = ? AND idalmacen = ?)",
            array($idnuevo, $alm, $idnuevo, $alm));
    }
    return 'creado';
}

function sedes_destino($bd, $org) {
    return log_filas($bd, "SELECT idsede, idalmacen_principal FROM log_sede
            WHERE idorg = ? AND activo = 1 AND idsede <> ?", array((int)$org['idorg'], (int)$org['idsede_central']));
}

/** Replica productos centrales a las sedes activas. $propiaTx = false si el caller ya abrio la transaccion. */
function replicar_lista($bd, $ido, $org, $ids, $propiaTx = true) {
    $sedes = sedes_destino($bd, $org);
    $res = array('creado' => 0, 'ya' => 0, 'conflicto' => array());
    if (!$propiaTx) {
        foreach ($ids as $id) {
            $prod = producto_central($bd, $org, $id);
            foreach ($sedes as $s) {
                $r = replicar_en_sede($bd, $ido, $prod, $s);
                if ($r === 'conflicto') { $res['conflicto'][] = array('idsede' => (int)$s['idsede'], 'producto' => $prod['descripcion']); }
                else { $res[$r]++; }
            }
        }
        return $res;
    }
    $bd->bd->begin_transaction();
    try {
        bloquear_catalogo_org($bd, $ido);   // dos "Enviar a las sedes" a la vez no duplican copias
        foreach ($ids as $id) {
            $prod = producto_central($bd, $org, $id);
            foreach ($sedes as $s) {
                $r = replicar_en_sede($bd, $ido, $prod, $s);
                if ($r === 'conflicto') { $res['conflicto'][] = array('idsede' => (int)$s['idsede'], 'producto' => $prod['descripcion']); }
                else { $res[$r]++; }
            }
        }
        $bd->bd->commit();
    } catch (Throwable $e) {
        $bd->bd->rollback();
        throw $e;
    }
    return $res;
}

/**
 * Unidad por nombre o abreviatura: vacio/UND/UNIDAD/PIEZA = 0 (pieza); si existe se usa; si no, se crea.
 * Busca en la maestra (idorg NULL) y en las de la org; si no existe se crea SOLO para la org (migracion 004),
 * con tipo 'unidad' y conversion 1 para no mezclarse con las conversiones peso/volumen del costeo.
 */
function unidad_por_nombre($bd, $ido, $nombre) {
    $n = log_mayus($nombre, 50);
    if (in_array($n, array('', 'UND', 'UNIDAD', 'UNIDADES', 'UNIDAD (PIEZA)', 'PIEZA', 'UN', 'U'), true)) { return 0; }
    $r = log_filas($bd, "SELECT idunidad_medida FROM unidad_medida WHERE estado = 0 AND (idorg IS NULL OR idorg = ?)
            AND (UPPER(unidad) = ? OR UPPER(abreviatura) = ?) ORDER BY idorg IS NOT NULL, idunidad_medida LIMIT 1", array((int)$ido, $n, $n));
    if ($r) { return (int)$r[0]['idunidad_medida']; }
    $abrev = mb_substr(preg_replace('/\s+/', '', $n), 0, 5, 'UTF-8');
    log_exec($bd, "INSERT INTO unidad_medida (unidad, tipo, conversion_a_base, estado, abreviatura, idorg) VALUES (?, 'unidad', 1, '0', ?, ?)",
        array($n, $abrev, (int)$ido));
    return (int)$bd->lastInsertId();
}

/**
 * Serializa altas del catalogo por org (producto no tiene UNIQUE por nombre): bloquea la fila de org_logistica
 * hasta el commit, asi un doble clic espera y luego ve el producto ya creado. Llamar DENTRO de la tx.
 */
function bloquear_catalogo_org($bd, $ido) {
    log_filas($bd, "SELECT idorg FROM org_logistica WHERE idorg = ? FOR UPDATE", array((int)$ido));
}

function existe_en_central($bd, $ido, $idsede, $descripcion) {
    // idorg + idsede: usa producto_idorg_IDX (solo idsede no puede usarlo y recorre la tabla)
    return (bool)log_filas($bd, "SELECT 1 FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ? LIMIT 1",
        array((int)$ido, (int)$idsede, $descripcion));
}

/** Crea el producto en la sede central (+ fila de stock en su almacen). El caller maneja la transaccion. */
/** idunidad_kardex valido para la org (maestra o propia); si no, 0 = por unidad. */
function unidad_valida($bd, $ido, $id) {
    $id = (int)$id;
    if ($id <= 0) { return 0; }
    $r = log_filas($bd, "SELECT 1 FROM unidad_medida WHERE idunidad_medida = ? AND estado = 0 AND (idorg IS NULL OR idorg = ?)", array($id, (int)$ido));
    return $r ? $id : 0;
}

function crear_en_central($bd, $ido, $idsede, $c) {
    $c['idunidad_kardex'] = $c['unidad'] !== null ? unidad_por_nombre($bd, $ido, $c['unidad']) : unidad_valida($bd, $ido, $c['idunidad_kardex']);
    $fam = familia_en_sede($bd, $ido, $idsede, $c['familia']);
    log_exec($bd, "INSERT INTO producto (idorg, idsede, descripcion, codigo_barra, precio, precio_unitario, precio_venta,
            idproducto_familia, stock_minimo, img, estado, idunidad_kardex)
            VALUES (?, ?, ?, ?, '0', '0', ?, ?, '0', '', 0, ?)",
        array($ido, $idsede, $c['descripcion'], $c['codigo_barra'], precio_txt($c['precio_venta']), $fam, $c['idunidad_kardex']));
    $id = (int)$bd->lastInsertId();
    $alm = log_filas($bd, "SELECT idalmacen_principal FROM log_sede WHERE idsede = ?", array($idsede));
    if ($alm && (int)$alm[0]['idalmacen_principal'] > 0) {
        log_exec($bd, "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado) VALUES (?, ?, 0, 0)",
            array($id, (int)$alm[0]['idalmacen_principal']));
    }
    return $id;
}

/**
 * Presentaciones y proveedor habitual del producto central (vienen en el mismo body de crear/editar).
 * presentaciones: [{nombre, factor}] -> lista completa: las que no vienen se desactivan (activo=0, las OC futuras
 * las referencian). proveedor: texto; '' = sin proveedor habitual; si no existe en la central se crea.
 * Si el body no trae la clave, no se toca. El caller maneja la transaccion.
 */
function guardar_extras($bd, $ido, $org, $idcentral, $b) {
    if (isset($b['presentaciones']) && is_array($b['presentaciones'])) {
        $vistas = array();
        foreach ($b['presentaciones'] as $pr) {
            $nombre = log_mayus(is_array($pr) && isset($pr['nombre']) ? $pr['nombre'] : '', 40);
            $factor = is_array($pr) && isset($pr['factor']) ? (float)$pr['factor'] : 0;
            if ($nombre === '') { continue; }
            if ($factor <= 0 || $factor > 100000) { throw new ErrorUsuario("Indica cuántas unidades trae $nombre."); }
            if (isset($vistas[$nombre])) { throw new ErrorUsuario("La presentación $nombre está repetida."); }
            $vistas[$nombre] = true;
            log_exec($bd, "INSERT INTO log_presentacion (idorg, idproducto_central, nombre, factor, activo) VALUES (?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE factor = VALUES(factor), activo = 1",
                array($ido, $idcentral, $nombre, $factor));
        }
        $sql = "UPDATE log_presentacion SET activo = 0 WHERE idproducto_central = ?";
        $params = array($idcentral);
        if ($vistas) {
            $sql .= " AND nombre NOT IN (" . implode(',', array_fill(0, count($vistas), '?')) . ")";
            $params = array_merge($params, array_keys($vistas));
        }
        log_exec($bd, $sql, $params);
    }
    if (array_key_exists('proveedor', $b)) {
        $nombre = log_mayus((string)$b['proveedor'], 150);
        log_exec($bd, "UPDATE log_proveedor_producto SET preferido = 0 WHERE idproducto_central = ?", array($idcentral));
        if ($nombre !== '') {
            $idp = proveedor_en_central($bd, $ido, (int)$org['idsede_central'], $nombre);
            log_exec($bd, "INSERT INTO log_proveedor_producto (idproducto_central, idproveedor, idorg, preferido) VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE preferido = 1", array($idcentral, $idp, $ido));
        }
    }
    // en las sedes se vende por porcion (1 bote = 20): al recibir suma porciones. Vacio o 0 = producto normal
    if (array_key_exists('porciones', $b)) {
        $txt = str_replace(',', '.', trim(is_scalar($b['porciones']) ? (string)$b['porciones'] : ''));
        if ($txt === '' || (is_numeric($txt) && (float)$txt == 0)) {
            log_exec($bd, "DELETE FROM log_producto_porcion WHERE idproducto_central = ?", array($idcentral));
        } elseif (!is_numeric($txt) || (float)$txt < 1 || (float)$txt > 10000) {
            throw new ErrorUsuario('Las porciones que salen de una unidad deben ser un número entre 1 y 10000.');
        } else {
            log_exec($bd, "INSERT INTO log_producto_porcion (idproducto_central, idorg, porciones) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE porciones = VALUES(porciones)", array($idcentral, $ido, round((float)$txt, 3)));
        }
    }
}

/** Proveedor por nombre en la sede central (vincula si existe, crea si no; como x-compras2). */
function proveedor_en_central($bd, $ido, $idsede, $nombre) {
    $sql = "SELECT idproveedor FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ?
             ORDER BY idproveedor LIMIT 1";
    $r = log_filas($bd, $sql, array($ido, $idsede, $nombre));
    if ($r) { return (int)$r[0]['idproveedor']; }
    log_exec($bd, "INSERT INTO proveedor (idorg, idsede, descripcion, dni, direccion, estado, telefono) VALUES (?, ?, ?, '', '', 0, '')",
        array($ido, $idsede, $nombre));
    return (int)$bd->lastInsertId();
}

function leer_campos($b) {
    $c = array(
        'descripcion'     => log_mayus(isset($b['descripcion']) ? $b['descripcion'] : '', 150),
        'familia'         => log_mayus(isset($b['familia']) ? $b['familia'] : '', 80),
        'codigo_barra'    => trim(substr((string)(isset($b['codigo_barra']) ? $b['codigo_barra'] : ''), 0, 255)),
        'idunidad_kardex' => isset($b['idunidad_kardex']) ? (int)$b['idunidad_kardex'] : 0,
        'unidad'          => isset($b['unidad']) ? (string)$b['unidad'] : null,   // texto libre: se resuelve con unidad_por_nombre
        // precio de venta del catalogo: es el precio INICIAL de cada sede; despues cada sede lo cambia a su gusto
        'precio_venta'    => isset($b['precio_venta']) && $b['precio_venta'] !== '' ? str_replace(',', '.', trim((string)$b['precio_venta'])) : null,
    );
    if ($c['precio_venta'] !== null && (!is_numeric($c['precio_venta']) || ($c['precio_venta'] = (float)$c['precio_venta']) < 0 || $c['precio_venta'] > 999999)) {
        throw new ErrorUsuario('El precio de venta no es válido.');
    }
    if ($c['descripcion'] === '') { throw new ErrorUsuario('Escribe el nombre del producto.'); }
    if ($c['familia'] === '') { throw new ErrorUsuario('Escribe la familia del producto.'); }
    return $c;
}

/** [idcentral, idcopia1, ...]: el producto central y sus copias en las sedes (por PK, sin recorrer la org). */
function ids_con_copias($bd, $idcentral) {
    $ids = array_map('intval', array_column(
        log_filas($bd, "SELECT idproducto FROM log_producto_central WHERE idproducto_central = ?", array((int)$idcentral)), 'idproducto'));
    array_unshift($ids, (int)$idcentral);
    return $ids;
}

/** Marcadores ?,?,? para un IN con n valores. */
function marcas($n) {
    return implode(',', array_fill(0, $n, '?'));
}

/**
 * Productos de la central que todavia faltan en al menos una sede activa (para "Enviar a las sedes").
 * Recorre solo los productos de la central (indice idorg,idsede) y cuenta vinculos por ix_lpc_central.
 */
function ids_pendientes_envio($bd, $org, $limite) {
    $total = count(sedes_destino($bd, $org));
    if ($total === 0) { return array(); }
    return array_map('intval', array_column(log_filas($bd, "SELECT p.idproducto FROM producto p
            WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0
              AND (SELECT COUNT(*) FROM log_producto_central v JOIN log_sede ls ON ls.idsede = v.idsede AND ls.activo = 1
                    WHERE v.idproducto_central = p.idproducto) < ?
            ORDER BY p.idproducto LIMIT " . (int)$limite,
        array((int)$org['idorg'], (int)$org['idsede_central'], $total)), 'idproducto'));
}

/** precio_venta es varchar en el legacy: '12.5', '0'. */
function precio_txt($n) {
    if ($n === null) { return '0'; }
    $t = rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
    return $t === '' ? '0' : $t;
}

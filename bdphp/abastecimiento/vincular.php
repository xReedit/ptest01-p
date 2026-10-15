<?php
/**
 * Logistica F1 — "Productos de las sedes": productos de una sede que NO vienen del catalogo central.
 * ?op=sedes | pendientes&idsede= | vincular | agregar
 *  - vincular: "es el mismo que..." -> enlaza al producto central y toma su nombre/familia/unidad/codigo.
 *  - agregar:  lo crea en la central con sus datos, lo enlaza y lo envia a las demas sedes.
 *  - si no se hace nada, queda como producto "solo de esta sede".
 * Lo hace logistica desde la sede central. Nunca se vincula automatico por parecido de nombre.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_catalogo.php';

/** Sede participante de la org (no la central). */
function sede_de_org($bd, $org, $idsede) {
    $r = log_filas($bd, "SELECT ls.idsede, ls.idalmacen_principal, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idsede = ? AND ls.idorg = ? AND ls.idsede <> ?", array((int)$idsede, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Sede no válida.'); }
    return $r[0];
}

/** Producto de la sede, activo y sin vinculo. */
function producto_sede_libre($bd, $org, $idproducto) {
    $r = log_filas($bd, "SELECT " . CAMPOS_PRODUCTO . ", p.idsede
            FROM producto p
            LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
            LEFT JOIN log_producto_central v ON v.idproducto = p.idproducto
            WHERE p.idproducto = ? AND p.idorg = ? AND p.estado = 0 AND p.idsede <> ? AND v.idproducto IS NULL",
        array((int)$idproducto, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Ese producto ya no está pendiente (quizá ya se vinculó).'); }
    sede_de_org($bd, $org, $r[0]['idsede']);
    return $r[0];
}

/** Enlaza y copia los campos heredados de la central (con @log_sync para pasar el trigger 003). Caller maneja la tx. */
function enlazar($bd, $ido, $prodSede, $central) {
    $idsede = (int)$prodSede['idsede'];
    if (log_filas($bd, "SELECT 1 FROM log_producto_central WHERE idsede = ? AND idproducto_central = ?",
            array($idsede, (int)$central['idproducto']))) {
        throw new ErrorUsuario('Esa sede ya tiene otro producto vinculado a "' . $central['descripcion'] . '".');
    }
    // la sede no debe quedar con dos productos del mismo nombre (y luego el producto central ya no se podria editar)
    if (log_filas($bd, "SELECT 1 FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ? AND idproducto <> ? LIMIT 1",
            array($ido, $idsede, log_mayus($central['descripcion'], 150), (int)$prodSede['idproducto']))) {
        throw new ErrorUsuario('Esa sede ya tiene otro producto llamado "' . $central['descripcion'] . '". Vincula ese, o renómbralo primero.');
    }
    log_exec($bd, "INSERT INTO log_producto_central (idproducto, idproducto_central, idorg, idsede) VALUES (?, ?, ?, ?)",
        array((int)$prodSede['idproducto'], (int)$central['idproducto'], $ido, $idsede));
    $fam = familia_en_sede($bd, $ido, $idsede, $central['familia']);
    log_exec($bd, "UPDATE producto SET descripcion = ?, idproducto_familia = ?, codigo_barra = ?, idunidad_kardex = ? WHERE idproducto = ?",
        array($central['descripcion'], $fam, (string)$central['codigo_barra'], (int)$central['idunidad_kardex'], (int)$prodSede['idproducto']));
}

/** Transaccion con @log_sync (el trigger 003 deja pasar los campos heredados). */
function en_tx($bd, $fn) {
    $bd->bd->begin_transaction();
    log_sync_on($bd);
    try {
        $r = $fn();
        $bd->bd->commit();
        return $r;
    } catch (Throwable $e) {
        $bd->bd->rollback();
        throw $e;
    } finally {
        log_sync_off($bd);
    }
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'sedes':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        return log_filas($bd, "SELECT s.idsede, s.nombre,
                (SELECT COUNT(*) FROM producto p LEFT JOIN log_producto_central v ON v.idproducto = p.idproducto
                  WHERE p.idorg = ls.idorg AND p.idsede = s.idsede AND p.estado = 0 AND v.idproducto IS NULL) AS pendientes
                FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre",
            array($ido, (int)$org['idsede_central']));
    });

case 'pendientes':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $sede = sede_de_org($bd, $org, isset($_GET['idsede']) ? $_GET['idsede'] : 0);
        $productos = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia, IFNULL(u.unidad, '') AS unidad
                FROM producto p
                LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                LEFT JOIN log_producto_central v ON v.idproducto = p.idproducto
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 AND v.idproducto IS NULL
                ORDER BY pf.descripcion, p.descripcion LIMIT 500", array($ido, (int)$sede['idsede']));
        // catalogo central para "es el mismo que...": solo los que esta sede aun no tiene vinculados
        $central = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia
                FROM producto p LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0
                  AND NOT EXISTS (SELECT 1 FROM log_producto_central v WHERE v.idsede = ? AND v.idproducto_central = p.idproducto)
                ORDER BY p.descripcion", array($ido, (int)$org['idsede_central'], (int)$sede['idsede']));
        return array('sede' => $sede, 'productos' => $productos, 'central' => $central);
    });

case 'vincular':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_sede_libre($bd, $org, isset($b['idproducto']) ? $b['idproducto'] : 0);
        $central = producto_central($bd, $org, isset($b['idproducto_central']) ? $b['idproducto_central'] : 0);
        en_tx($bd, function () use ($bd, $ido, $prod, $central) { enlazar($bd, $ido, $prod, $central); });
        return array('nombre' => $central['descripcion']);
    });

case 'vincular_varios':
    // pares [{idproducto, idproducto_central}] (max 200). Cada par en su propia transaccion: uno con problema
    // no frena a los demas; se devuelve que se vinculo y que no, con el motivo.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $pares = isset($b['pares']) && is_array($b['pares']) ? array_slice($b['pares'], 0, 200) : array();
        $res = array('vinculados' => array(), 'errores' => array());
        foreach ($pares as $par) {
            $idp = is_array($par) && isset($par['idproducto']) ? (int)$par['idproducto'] : 0;
            try {
                $prod = producto_sede_libre($bd, $org, $idp);
                $central = producto_central($bd, $org, isset($par['idproducto_central']) ? $par['idproducto_central'] : 0);
                en_tx($bd, function () use ($bd, $ido, $prod, $central) { enlazar($bd, $ido, $prod, $central); });
                $res['vinculados'][] = array('idproducto' => $idp, 'idproducto_central' => (int)$central['idproducto']);
            } catch (ErrorUsuario $e) {
                $res['errores'][] = array('idproducto' => $idp, 'motivo' => $e->getMessage());
            }
        }
        return $res;
    });

case 'agregar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_sede_libre($bd, $org, isset($b['idproducto']) ? $b['idproducto'] : 0);
        $central = (int)$org['idsede_central'];
        $nombre = log_mayus($prod['descripcion'], 150);
        if (existe_en_central($bd, $ido, $central, $nombre)) {
            throw new ErrorUsuario('El catálogo ya tiene un producto llamado "' . $nombre . '": usa "Es el mismo que…".');
        }
        return en_tx($bd, function () use ($bd, $ido, $org, $central, $prod, $nombre) {
            $id = crear_en_central($bd, $ido, $central, array(
                'descripcion' => $nombre,
                'familia' => $prod['familia'] !== '' ? $prod['familia'] : 'SIN FAMILIA',
                'codigo_barra' => (string)$prod['codigo_barra'],
                'idunidad_kardex' => (int)$prod['idunidad_kardex'],
                'unidad' => null,
                'precio_venta' => (float)$prod['precio_venta'],
            ));
            enlazar($bd, $ido, $prod, producto_central($bd, $org, $id));
            return replicar_lista($bd, $ido, $org, array($id), false);   // a las demas sedes
        });
    });

default:
    log_out(false, null, 'Operación no válida.');
}

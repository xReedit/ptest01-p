<?php
/**
 * Logistica — produccion (migracion logistica/024). Compartido por produccion.php, bandeja.php y traslados.php.
 * La PLANTA es la sede donde se produce para toda la cadena (org_logistica.idsede_planta; NULL = la central).
 * Consultas por indice: log_receta (ix_lr_sede), log_producto_central (PK idproducto / ix_lpc_central), producto_stock(idproducto).
 */

/** {activa, idsede_planta} — la planta cae en la central si no se eligio otra. */
function prod_cfg($bd, $org) {
    $r = log_filas($bd, "SELECT produccion_activa, idsede_planta FROM org_logistica WHERE idorg = ?", array((int)$org['idorg']));
    $p = $r && $r[0]['idsede_planta'] ? (int)$r[0]['idsede_planta'] : (int)$org['idsede_central'];
    return array('activa' => $r && (int)$r[0]['produccion_activa'] === 1, 'idsede_planta' => $p);
}

/**
 * Productos centrales (de $ids) que la planta sabe producir: [idcentral => true]. Vacio si produccion esta apagada.
 * La receta es de un producto de la planta; si la planta no es la central se traduce por log_producto_central.
 */
function prod_centrales_con_receta($bd, $org, $ids) {
    $cfg = prod_cfg($bd, $org);
    if (!$cfg['activa'] || !$ids) { return array(); }
    $m = implode(',', array_fill(0, count($ids), '?'));
    $sql = $cfg['idsede_planta'] === (int)$org['idsede_central']
        ? "SELECT DISTINCT r.idproducto AS c FROM log_receta r WHERE r.idsede = ? AND r.activo = 1 AND r.idproducto IN ($m)"
        : "SELECT DISTINCT v.idproducto_central AS c FROM log_receta r JOIN log_producto_central v ON v.idproducto = r.idproducto
            WHERE r.idsede = ? AND r.activo = 1 AND v.idproducto_central IN ($m)";
    $res = array();
    foreach (log_filas($bd, $sql, array_merge(array($cfg['idsede_planta']), array_map('intval', $ids))) as $f) { $res[(int)$f['c']] = true; }
    return $res;
}

/** Costo por unidad de un producto de una sede: costo promedio del central (si esta en el catalogo) o el propio. */
function prod_costo($bd, $idprod) {
    $r = log_filas($bd, "SELECT IFNULL(NULLIF((SELECT ps.costo_promedio FROM log_sede ls JOIN producto_stock ps ON ps.idalmacen = ls.idalmacen_principal
                WHERE ls.idsede = x.idsede AND ps.idproducto = x.idproducto LIMIT 1), 0), x.precio_unitario + 0) AS costo
            FROM (SELECT IFNULL(pc.idproducto, p.idproducto) AS idproducto, IFNULL(pc.idsede, p.idsede) AS idsede,
                         IFNULL(pc.precio_unitario, p.precio_unitario) AS precio_unitario
                  FROM producto p LEFT JOIN log_producto_central v ON v.idproducto = p.idproducto
                  LEFT JOIN producto pc ON pc.idproducto = v.idproducto_central
                  WHERE p.idproducto = ?) x", array((int)$idprod));
    return $r ? round((float)$r[0]['costo'], 4) : 0;
}

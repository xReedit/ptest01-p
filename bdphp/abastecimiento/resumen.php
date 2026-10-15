<?php
/**
 * Logistica F7 — IA operativa (central).
 * ?op=hoy | flujo | valor&dias= | whatsapp | telefono | auto | enviar_ahora | minimos&idsede= | aplicar_minimos
 * - hoy: pendientes del dia con su cantidad (cada uno lleva a su pantalla).
 * - diferencias: lo que se repite en 30 dias (faltantes en envios por sede/chofer/producto; proveedores que entregan menos).
 * - whatsapp: el resumen redactado por la IA (o plantilla si no hay IA) para mandar por WhatsApp.
 * - auto: envio automatico diario a una hora (lo dispara resumen_auto.php); enviar_ahora: prueba el canal ya mismo.
 * - minimos: stock minimo sugerido por sede = lo que vende en DIAS_MINIMO dias (reparto + margen).
 * Los numeros los calcula el servidor; la IA solo redacta.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_resumen.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'hoy':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $cfg = log_filas($bd, "SELECT telefono_resumen, resumen_auto, resumen_hora, resumen_resultado FROM org_logistica WHERE idorg = ?", array((int)$ido))[0];
        return array('hoy' => pendientes_hoy($bd, $org), 'diferencias' => diferencias($bd, $org), 'telefono' => $cfg['telefono_resumen'],
            'auto' => array('activo' => (int)$cfg['resumen_auto'] === 1, 'hora' => (int)$cfg['resumen_hora'], 'resultado' => $cfg['resumen_resultado']));
    });

case 'flujo':
    // diagrama del inicio: cuanto hay en cada etapa (pedidos -> compras -> almacen -> reparto)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        return flujo($bd, log_requiere_central($bd, $ido, $idsede, $us), isset($_GET['periodo']) ? (string)$_GET['periodo'] : '7');
    });

case 'valor':
    // valor de la mercaderia movida entre locales (7, 30 o 90 dias)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $d = isset($_GET['dias']) ? (int)$_GET['dias'] : 30;
        return valor_movido($bd, $org, in_array($d, array(7, 30, 90), true) ? $d : 30);
    });

case 'whatsapp':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        list($texto, $conIa) = resumen_texto($bd, $org);
        return array('texto' => $texto, 'con_ia' => $conIa,
            'telefono' => log_filas($bd, "SELECT telefono_resumen FROM org_logistica WHERE idorg = ?", array((int)$ido))[0]['telefono_resumen']);
    });

case 'telefono':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $tel = substr(preg_replace('/\D/', '', isset($b['telefono']) ? (string)$b['telefono'] : ''), 0, 15);
        if (strlen($tel) < 9) { throw new ErrorUsuario('Escribe un número de WhatsApp válido (9 dígitos).'); }
        log_exec($bd, "UPDATE org_logistica SET telefono_resumen = ? WHERE idorg = ?", array($tel, (int)$ido));
        return array('telefono' => $tel);
    });

case 'auto_cfg':
    // lo que muestra Configurar: si esta activo, a que hora, a que celular y como salio el ultimo
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $c = log_filas($bd, "SELECT telefono_resumen, resumen_auto, resumen_hora, resumen_resultado FROM org_logistica WHERE idorg = ?", array((int)$ido))[0];
        return array('telefono' => $c['telefono_resumen'],
            'auto' => array('activo' => (int)$c['resumen_auto'] === 1, 'hora' => (int)$c['resumen_hora'], 'resultado' => $c['resumen_resultado']));
    });

case 'auto':
    // {activo, hora, telefono}: el reloj (resumen_auto.php) lo manda solo cada dia a esa hora
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $activo = !empty($b['activo']) ? 1 : 0;
        $hora = isset($b['hora']) && is_numeric($b['hora']) ? (int)$b['hora'] : -1;
        if ($hora < 5 || $hora > 22) { throw new ErrorUsuario('Elige una hora entre las 5 a.m. y las 10 p.m.'); }
        $tel = substr(preg_replace('/\D/', '', isset($b['telefono']) ? (string)$b['telefono'] : ''), 0, 15);
        if (($activo || $tel !== '') && strlen($tel) < 9) { throw new ErrorUsuario('Escribe el WhatsApp de quien recibe el resumen (9 dígitos).'); }
        log_exec($bd, "UPDATE org_logistica SET resumen_auto = ?, resumen_hora = ?, telefono_resumen = IF(? = '', telefono_resumen, ?) WHERE idorg = ?",
            array($activo, $hora, $tel, $tel, (int)$ido));
        return array('activo' => $activo === 1, 'hora' => $hora);
    });

case 'enviar_ahora':
    // prueba el canal: manda el resumen ya mismo por el WhatsApp de la sede central
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new ErrorUsuario('Operación no válida.'); }
        // no usar el WhatsApp de la sede para mandar en rafaga (riesgo de bloqueo del numero)
        $tk = 'o' . (int)$ido . '-' . intdiv(time(), 120);
        $bd->prepare("INSERT INTO log_token (tipo, token, idorg) VALUES ('wsp_prueba', ?, ?)");
        $bd->execute(array($tk, (int)$ido));
        if ($bd->stmt->errno === 1062) { throw new ErrorUsuario('Ya se envió hace un momento. Espera 2 minutos para volver a probar.'); }
        $err = resumen_enviar($bd, $org);
        if ($err !== null) {
            // no salio: se puede reintentar ya (ej. recien encendieron la PC de la sede)
            log_exec($bd, "DELETE FROM log_token WHERE tipo = 'wsp_prueba' AND token = ?", array($tk));
            throw new ErrorUsuario($err);
        }
        return array('enviado' => true);
    });

case 'minimos':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $s = isset($_GET['idsede']) ? (int)$_GET['idsede'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM log_sede WHERE idsede = ? AND idorg = ? AND activo = 1 AND idsede <> ?", array($s, (int)$ido, (int)$org['idsede_central']))) {
            throw new ErrorUsuario('Elige una sede.');
        }
        $desde = log_filas($bd, "SELECT DATE_SUB(CURDATE(), INTERVAL ? DAY) AS d", array(27))[0]['d'];
        $filas = log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(pf.descripcion, '') AS familia, IFNULL(u.abreviatura, '') AS unidad,
                p.idunidad_kardex, GREATEST(p.stock_minimo + 0, 0) AS minimo,
                IFNULL((SELECT SUM(c.cantidad) FROM log_consumo_dia c WHERE c.idproducto = p.idproducto AND c.fecha >= ?), 0) AS consumo
                FROM log_producto_central v JOIN producto p ON p.idproducto = v.idproducto AND p.estado = 0
                LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE v.idsede = ? ORDER BY pf.descripcion, p.descripcion", array($desde, $s));
        $r = array();
        foreach ($filas as $f) {
            $porDia = max(0, (float)$f['consumo']) / 28;
            $sug = (int)$f['idunidad_kardex'] === 0 ? ceil($porDia * DIAS_MINIMO - 1e-9) : ceil($porDia * DIAS_MINIMO * 10 - 1e-9) / 10;
            if ($porDia <= 0 && (float)$f['minimo'] <= 0) { continue; }   // sin ventas ni minimo: nada que sugerir
            $r[] = array('idproducto' => (int)$f['idproducto'], 'descripcion' => $f['descripcion'], 'familia' => $f['familia'], 'unidad' => $f['unidad'],
                'por_dia' => round($porDia, 2), 'minimo' => (float)$f['minimo'], 'sugerido' => (float)$sug);
        }
        return array('dias' => DIAS_MINIMO, 'productos' => $r);
    });

case 'aplicar_minimos':
    // items: [{idproducto, minimo}] de UNA sede; el minimo es dato propio de la sede (el trigger 003 no lo bloquea)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $s = isset($b['idsede']) ? (int)$b['idsede'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM log_sede WHERE idsede = ? AND idorg = ? AND activo = 1", array($s, (int)$ido))) { throw new ErrorUsuario('Elige una sede.'); }
        $items = isset($b['items']) && is_array($b['items']) ? array_slice($b['items'], 0, 2000) : array();
        if (!$items) { throw new ErrorUsuario('Marca al menos un producto.'); }
        $n = 0;
        $bd->bd->begin_transaction();
        try {
            foreach ($items as $it) {
                $id = is_array($it) && isset($it['idproducto']) ? (int)$it['idproducto'] : 0;
                // "mucho", "", null -> no valido (no se convierte a 0 en silencio)
                $m = is_array($it) && isset($it['minimo']) && is_numeric($it['minimo']) ? round((float)$it['minimo'], 2) : -1;
                if ($m < 0 || $m > 999999) { throw new ErrorUsuario('Hay un mínimo no válido.'); }
                $n += log_exec($bd, "UPDATE producto SET stock_minimo = ? WHERE idproducto = ? AND idsede = ? AND idorg = ? AND estado = 0",
                    array(rtrim(rtrim(number_format($m, 2, '.', ''), '0'), '.') ?: '0', $id, $s, (int)$ido));
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('actualizados' => $n);
    });

default:
    log_out(false, null, 'Operación no válida.');
}

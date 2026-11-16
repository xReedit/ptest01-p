<?php
// Filtros por fecha para columnas de texto 'dd/mm/yyyy' (ce.fecha y similares).
// STR_TO_DATE(columna) obliga a MySQL a leer TODOS los comprobantes de la sede. Comparar contra la lista
// de dias en el mismo formato de texto usa el indice idx_ce_sede_fecha (idsede, fecha).
// Plan: plan/OPTIMIZACION-CONSULTAS-PLAN.md (C2).

// Dias entre $desde y $hasta (inclusive, cualquier formato que entienda DateTime: 'Y-m-d', ...) como
// lista SQL: "'01/11/2026','02/11/2026'". Fechas invalidas o rango vacio => "''" (no trae nada).
function ceFechasEntre($desde, $hasta, $maxDias = 400) {
	try {
		$d = new DateTime($desde);
		$h = new DateTime($hasta);
	} catch (Exception $e) {
		return "''";
	}
	$lista = array();
	while ($d <= $h && count($lista) < $maxDias) {
		$lista[] = "'" . $d->format('d/m/Y') . "'";
		$d->modify('+1 day');
	}
	return $lista ? implode(',', $lista) : "''";
}

// Hoy y los $dias anteriores segun el reloj de MySQL (igual que CURDATE()): equivale a
// STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN CURDATE() - INTERVAL $dias DAY AND CURDATE().
function ceFechasUltimosDias($dias) {
	$dias = max(0, min(400, (int)$dias));
	$lista = array();
	for ($k = 0; $k <= $dias; $k++) {
		$lista[] = "DATE_FORMAT(CURDATE() - INTERVAL $k DAY, '%d/%m/%Y')";
	}
	return implode(',', $lista);
}

// Todos los dias del mes de una fecha 'dd/mm/yyyy' (equivale a MONTH(...) = MONTH(...) AND YEAR(...) = YEAR(...)).
function ceFechasMesDe($fechaDmy) {
	$f = DateTime::createFromFormat('d/m/Y', (string)$fechaDmy);
	if (!$f) { return "''"; }
	return ceFechasEntre($f->format('Y-m-01'), $f->format('Y-m-t'));
}

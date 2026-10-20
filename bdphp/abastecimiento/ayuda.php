<?php
/**
 * Logistica — ayuda del modulo. GET ayuda.php -> temas (la vista filtra por rol). Texto en _ayuda.php.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_ayuda.php';

log_responder(function () { return ayuda_temas(); });

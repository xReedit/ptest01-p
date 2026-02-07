<?php
/**
 * Configuración para búsqueda de imágenes en Pexels
 * 
 * IMPORTANTE: Este archivo contiene la API key. No lo subas a repositorios públicos.
 */

// ============================================
// API KEY DE PEXELS
// ============================================
// Obtén tu API key GRATIS en: https://www.pexels.com/api/
// 
// Pasos para obtener tu API key:
// 1. Visita https://www.pexels.com/api/
// 2. Haz clic en "Get Started"
// 3. Crea una cuenta o inicia sesión
// 4. Completa el formulario describiendo tu proyecto
// 5. Acepta los términos de servicio
// 6. Recibirás tu API key inmediatamente

define('PEXELS_API_KEY', 'hdhFccBIgcvXjkufoB7ntE3R9nlK0QXNAWk6MvNshyyvNJ2cVLBfYHJD');

// ============================================
// API KEY DE UNSPLASH
// ============================================
// Obtén tu API key GRATIS en: https://unsplash.com/developers
// 
// Pasos para obtener tu API key:
// 1. Visita https://unsplash.com/developers
// 2. Haz clic en "Register as a developer"
// 3. Crea una cuenta o inicia sesión
// 4. Crea una nueva aplicación
// 5. Copia tu "Access Key"

define('UNSPLASH_ACCESS_KEY', 'LrJmVEx42y7Ju35iExgwOlNLn2r2revumGB7KMR6P58');

// ============================================
// LÍMITES Y RESTRICCIONES
// ============================================
// Plan GRATUITO de Pexels:
// - 200 requests por hora
// - Sin límite mensual
// - Acceso a toda la biblioteca de fotos
// - Fotos de alta calidad
// - Uso comercial permitido
// - Atribución recomendada pero no obligatoria
//
// Plan GRATUITO de Unsplash:
// - 50 requests por hora
// - Acceso a millones de fotos de alta calidad
// - Uso comercial permitido
// - Atribución REQUERIDA (automática en el componente)

// ============================================
// GUÍA DE USO
// ============================================
/*
 * TÉRMINOS DE BÚSQUEDA RECOMENDADOS:
 * 
 * Para platos peruanos:
 * - "ceviche fish dish"
 * - "peruvian food"
 * - "lomo saltado beef"
 * - "causa potato"
 * - "anticuchos grilled"
 * - "arroz con mariscos seafood rice"
 * 
 * Para platos generales:
 * - "pasta dish"
 * - "pizza food"
 * - "burger meal"
 * - "salad bowl"
 * - "dessert cake"
 * - "soup bowl"
 * 
 * TIPS:
 * - Usa términos en inglés para mejores resultados
 * - Sé específico con los ingredientes
 * - Incluye el tipo de presentación si es importante
 * - Combina términos: "grilled chicken vegetables"
 * 
 * ORIENTACIONES DISPONIBLES:
 * - landscape: Horizontal (recomendado para platos)
 * - portrait: Vertical
 * - square: Cuadrada
 * 
 * TAMAÑOS DE IMAGEN DISPONIBLES:
 * - original: Tamaño completo (puede ser muy grande)
 * - large: 940px de ancho
 * - medium: 350px de ancho
 * - small: 130px de ancho
 */

// ============================================
// ATRIBUCIÓN (Recomendada)
// ============================================
/*
 * Aunque Pexels no requiere atribución obligatoria,
 * es una buena práctica dar crédito a los fotógrafos.
 * 
 * El componente ya incluye automáticamente:
 * - Nombre del fotógrafo en cada imagen
 * - Link a Pexels en la galería
 * 
 * Esto cumple con las mejores prácticas de Pexels.
 */

// ============================================
// NOTAS IMPORTANTES
// ============================================
/*
 * VENTAJAS DE PEXELS:
 * ✅ Completamente GRATIS
 * ✅ No requiere tarjeta de crédito
 * ✅ Fotos de alta calidad profesional
 * ✅ Uso comercial permitido
 * ✅ 200 requests por hora (suficiente para uso normal)
 * ✅ Millones de fotos disponibles
 * ✅ API simple y rápida
 * 
 * LIMITACIONES:
 * ⚠️ Búsquedas en inglés dan mejores resultados
 * ⚠️ No todas las búsquedas específicas tienen resultados
 * ⚠️ 200 requests/hora (resetea cada hora)
 * 
 * ALTERNATIVAS SI NECESITAS MÁS:
 * - Unsplash API (también gratis, 50 requests/hora)
 * - Pixabay API (gratis, sin límites estrictos)
 * - Flickr API (gratis, más compleja)
 */

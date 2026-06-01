# Indicaciones de optimización — Web y Web Móvil

**Proyecto:** Centro Ruliman Inventario  
**Alcance:** Solo versión web (escritorio) y web móvil (navegador en celular).  
**Fuera de alcance:** APK Flutter, API `/api/v1/*`, escáner de códigos de barras.

**Escenario operativo:**
- 25–30 usuarios contando simultáneamente en un almacén.
- Cada usuario cuenta entre 600 y 800 productos por conteo.
- Catálogo de ~60 000 productos registrados.
- Búsqueda manual por código o descripción (sin escáner).

---

## 1. Contexto técnico actual

### 1.1 Web vs web móvil

| Canal | Archivos principales | Autenticación |
|-------|---------------------|---------------|
| Web escritorio | `app/pages/conteo.php`, `public/assets/js/conteo.js` | Sesión PHP |
| Web móvil | **Los mismos archivos** + `public/assets/css/user-mobile.css` | Sesión PHP |

Ambos canales comparten el mismo flujo: login → conteo → buscar → cantidad → borrador/finalizar.

### 1.2 Endpoints relevantes (solo web)

| Acción | Ruta | Archivo |
|--------|------|---------|
| Buscar producto | `GET /actions/buscar_producto?q=` | `app/actions/buscar_producto.php` |
| Guardar borrador | `POST /actions/guardar_borrador` | `app/actions/guardar_borrador.php` |
| Finalizar conteo | `POST /actions/finalizar_conteo` | `app/actions/finalizar_conteo.php` |

La búsqueda está centralizada en `app/repositories/ProductRepository.php` → método `searchActive()`.

### 1.3 Carga estimada en una toma real

| Concepto | Cálculo | Impacto |
|----------|---------|---------|
| Líneas en BD | 30 × 700 ≈ 21 000 filas en `conteo_detalle` | Aceptable |
| Autoguardado actual | Cada 30 s, payload completo (~800 ítems) | **Crítico** |
| Por guardado (800 ítems) | DELETE 800 + INSERT 800 ≈ 1 600 escrituras | Muy pesado |
| Pico teórico | 30 usuarios × 2 guardados/min ≈ 96 000 escrituras/min | Puede saturar MySQL |
| Payload JSON | ~120–200 KB por usuario por guardado | Carga de red y PHP |
| Búsquedas | ~5–15/min por usuario ≈ 150–450/min total | Manejable con caché |

Cada usuario tiene su propio `conteo_id` (aislamiento correcto). El cuello de botella principal es el **reemplazo total del detalle en cada autoguardado**, no la concurrencia entre usuarios.

### 1.4 Lo que ya funciona bien (no rehacer)

- Búsqueda unificada con FULLTEXT e índices en `productos`.
- Debounce 420 ms, `AbortController` y guard de respuestas obsoletas en `conteo.js`.
- Control de versión `conteo_version` en guardado de borrador.
- Monitoreo de búsquedas lentas vía `monitor_duration()` (>500 ms).
- Índices: `FULLTEXT(descripcion)`, `(estado, codigo)`, `UNIQUE(conteo_id, producto_id)` en detalle.

---

## 2. Prioridad 1 — Autoguardado inteligente (CRÍTICO)

### 2.1 Problema

En `public/assets/js/conteo.js` línea ~584:

```javascript
setInterval(() => guardarBorrador(true), 30000);
```

- Guarda cada 30 segundos aunque no haya cambios.
- Envía **todos** los ítems (600–800) en cada petición.
- En backend, `reemplazar_detalle_conteo()` hace DELETE completo + INSERT masivo.

Archivo backend: `app/includes/conteo_items.php` → función `reemplazar_detalle_conteo()`.

### 2.2 Solución A — Flag `dirty` (guardar solo si hubo cambios)

**Archivo:** `public/assets/js/conteo.js`

1. Agregar al objeto `state`:
   ```javascript
   dirty: false,
   ```

2. Crear función:
   ```javascript
   function markDirty() {
     state.dirty = true;
     setSaveStatus('Cambios pendientes por guardar.');
     scheduleLocalBackup(); // ver sección 2.4
   }
   ```

3. Llamar `markDirty()` en:
   - `addProductLine()` — al agregar o mover producto.
   - Listener de `input` en cantidades (`listaProductos`).
   - Confirmación de eliminar producto.

4. En `guardarBorrador(auto)`:
   ```javascript
   if (auto && !state.dirty) return null;
   ```

5. Tras guardado exitoso:
   ```javascript
   state.dirty = false;
   ```

6. **No** llamar `markDirty()` dentro de `renderList()`.

### 2.3 Solución B — Intervalo más largo con desfase aleatorio

Reemplazar el `setInterval` fijo de 30 s por:

```javascript
const autosaveIntervalMs = 120000 + Math.floor(Math.random() * 60000); // 2–2.5 min
const autosaveInitialDelayMs = Math.floor(Math.random() * 60000);       // 0–60 s

setTimeout(() => {
  setInterval(() => guardarBorrador(true), autosaveIntervalMs);
}, autosaveInitialDelayMs);
```

**Objetivo:** evitar que los 30 usuarios disparen el guardado al mismo segundo.

**Beneficio esperado:** si un usuario no modifica nada durante 10 minutos → 0 peticiones (antes: 20 peticiones).

### 2.4 Solución C — Respaldo local con `localStorage`

**Archivo:** `public/assets/js/conteo.js`

1. Función de respaldo con debounce (~2 s):
   ```javascript
   let localBackupTimer = null;

   function scheduleLocalBackup() {
     clearTimeout(localBackupTimer);
     localBackupTimer = setTimeout(persistLocalDraft, 2000);
   }

   function persistLocalDraft() {
     const conteoId = Number($('conteoId')?.value || 0);
     if (conteoId <= 0 || state.items.size === 0) return;
     const key = `conteo_draft_${conteoId}`;
     localStorage.setItem(key, JSON.stringify({
       items: Array.from(state.items.values()),
       conteoVersion: Number($('conteoVersion')?.value || 0),
       savedAt: Date.now(),
     }));
   }
   ```

2. Al cargar la página (después de hidratar `CONTEO_INICIAL`):
   - Leer draft de `localStorage`.
   - Si existe y tiene más ítems o `savedAt` más reciente que la carga del servidor, mostrar confirmación: *"Hay un borrador local más reciente. ¿Recuperarlo?"*

3. Limpiar draft local tras finalizar conteo exitosamente.

**Importante para web móvil:** los cierres accidentales del navegador son frecuentes; esto protege horas de trabajo sin depender del servidor.

---

## 3. Prioridad 2 — Guardado incremental (delta)

### 3.1 Problema

Cada autoguardado reemplaza 800 filas aunque solo cambió 1 cantidad.

### 3.2 Solución — Nuevo endpoint y sync parcial

#### Backend

**Nuevo archivo:** `app/includes/conteo_sync.php`

```php
<?php
declare(strict_types=1);

require_once APP_PATH . '/repositories/ProductRepository.php';
require_once APP_INCLUDES_PATH . '/conteo_items.php';

function sincronizar_detalle_conteo(PDO $pdo, int $conteoId, array $upsert, array $remove): int
{
    $remove = array_values(array_unique(array_filter(array_map('intval', $remove), fn (int $id): bool => $id > 0)));
    if ($remove) {
        $placeholders = implode(',', array_fill(0, count($remove), '?'));
        $pdo->prepare("DELETE FROM conteo_detalle WHERE conteo_id = ? AND producto_id IN ({$placeholders})")
            ->execute(array_merge([$conteoId], $remove));
    }

    $cantidades = normalizar_items_conteo($upsert);
    if (!$cantidades) {
        return 0;
    }

    $productos = (new ProductRepository($pdo))->findActiveByIds(array_keys($cantidades));
    if (!$productos) {
        return 0;
    }

    $productosPorId = [];
    foreach ($productos as $producto) {
        $productosPorId[(int) $producto['id']] = $producto;
    }

    $values = [];
    $params = [];
    foreach ($cantidades as $productoId => $cantidad) {
        if (!isset($productosPorId[$productoId])) {
            continue;
        }
        $producto = $productosPorId[$productoId];
        $values[] = '(?, ?, ?, ?, ?)';
        $params[] = $conteoId;
        $params[] = $productoId;
        $params[] = $producto['codigo'];
        $params[] = $producto['descripcion'];
        $params[] = $cantidad;
    }

    if (!$values) {
        return 0;
    }

    $sql = 'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad) VALUES '
        . implode(', ', $values)
        . ' ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad), codigo = VALUES(codigo), descripcion = VALUES(descripcion)';

    $pdo->prepare($sql)->execute($params);

    return count($values);
}
```

**Nuevo action:** `app/actions/guardar_cambios.php`

- Misma validación que `guardar_borrador.php`: sesión, CSRF, rol, `conteo_version`, ventana de toma.
- Payload JSON:
  ```json
  {
    "csrf_token": "...",
    "conteo_id": 12,
    "conteo_version": 5,
    "upsert": [{"producto_id": 101, "cantidad": 4}],
    "remove": [88, 99]
  }
  ```
- Llamar `sincronizar_detalle_conteo()` en lugar de `reemplazar_detalle_conteo()`.
- Responder con `{ ok, conteo_id, conteo_version, lineas }`.

**Registrar ruta** en `public/index.php`:
```php
'guardar_cambios' => APP_PATH . '/actions/guardar_cambios.php',
```

**Mantener** `reemplazar_detalle_conteo()` solo para **finalizar conteo** (una sola vez al cerrar).

#### Frontend

**Archivo:** `public/assets/js/conteo.js`

1. Agregar al state:
   ```javascript
   pendingUpsert: new Map(),  // producto_id → item completo
   pendingRemove: new Set(),
   ```

2. Al agregar/editar producto:
   ```javascript
   state.pendingUpsert.set(String(producto_id), item);
   state.pendingRemove.delete(String(producto_id));
   markDirty();
   ```

3. Al eliminar producto:
   ```javascript
   state.pendingRemove.add(String(producto_id));
   state.pendingUpsert.delete(String(producto_id));
   markDirty();
   ```

4. Nueva función `buildDeltaPayload()`:
   ```javascript
   function buildDeltaPayload() {
     return {
       csrf_token: $('csrfToken').value,
       conteo_id: Number($('conteoId').value || 0),
       conteo_version: Number($('conteoVersion')?.value || 0),
       upsert: Array.from(state.pendingUpsert.values()),
       remove: Array.from(state.pendingRemove).map(Number),
     };
   }
   ```

5. Modificar `guardarBorrador()`:
   - Si hay cambios pendientes (`pendingUpsert` o `pendingRemove`), POST a `/actions/guardar_cambios`.
   - Tras éxito: limpiar `pendingUpsert` y `pendingRemove`, actualizar `conteo_version`, `state.dirty = false`.
   - Si no hay pendientes y es autoguardado → no hacer nada.

**Beneficio:** autoguardado típico envía 2–10 ítems en lugar de 800 → ~99% menos escrituras en BD.

---

## 4. Prioridad 3 — Búsqueda optimizada (60 000 productos)

### 4.1 Lookup exacto por código

**Archivo:** `app/repositories/ProductRepository.php` → inicio de `searchActive()`

Antes de LIKE/FULLTEXT, intentar coincidencia exacta:

```php
$exactStmt = $this->pdo->prepare(
    'SELECT id, codigo, descripcion FROM productos WHERE estado = 1 AND codigo = :codigo LIMIT 1'
);
$exactStmt->execute([':codigo' => $search]);
if ($exact = $exactStmt->fetch()) {
    return [$exact];
}
```

Usa índice `UNIQUE(codigo)` → respuesta en milisegundos cuando el usuario escribe el código completo.

### 4.2 Caché de búsqueda en servidor (archivos)

**Nuevo archivo:** `app/includes/search_cache.php`

```php
<?php
declare(strict_types=1);

function search_cache_dir(): string
{
    $dir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'search';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function search_cache_get(string $key): ?array
{
    $file = search_cache_dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || ($data['expires'] ?? 0) < time()) {
        @unlink($file);
        return null;
    }
    return $data['results'] ?? null;
}

function search_cache_set(string $key, array $results, int $ttlSeconds = 600): void
{
    $file = search_cache_dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    file_put_contents($file, json_encode([
        'expires' => time() + $ttlSeconds,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE));
}

function search_cache_invalidate_all(): void
{
    foreach (glob(search_cache_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        @unlink($file);
    }
}
```

**Integrar en `ProductRepository::searchActive()`:**

```php
$cacheKey = mb_strtolower($search) . '|' . $limit;
if ($cached = search_cache_get($cacheKey)) {
    return $cached;
}
// ... consulta MySQL existente ...
search_cache_set($cacheKey, $results);
return $results;
```

**Invalidar caché** al modificar catálogo — llamar `search_cache_invalidate_all()` en:
- `app/actions/agregar_producto.php`
- `app/actions/editar_producto.php`
- `app/actions/importar_productos_procesar.php`
- `app/actions/eliminar_producto.php`

**TTL recomendado:** 600 segundos (10 minutos).

### 4.3 Caché en cliente (`sessionStorage`)

**Archivo:** `public/assets/js/conteo.js`

```javascript
const SEARCH_CACHE_MAX = 100;

function getSearchCacheKey(term) {
  return term.trim().toLowerCase();
}

function getCachedSearch(term) {
  try {
    const raw = sessionStorage.getItem('search_cache');
    const cache = raw ? JSON.parse(raw) : {};
    return cache[getSearchCacheKey(term)] ?? null;
  } catch { return null; }
}

function setCachedSearch(term, products) {
  try {
    const raw = sessionStorage.getItem('search_cache');
    const cache = raw ? JSON.parse(raw) : {};
    const key = getSearchCacheKey(term);
    cache[key] = products;
    const keys = Object.keys(cache);
    while (keys.length > SEARCH_CACHE_MAX) {
      delete cache[keys.shift()];
    }
    sessionStorage.setItem('search_cache', JSON.stringify(cache));
  } catch { /* quota exceeded — ignorar */ }
}
```

En `buscarProductos()`:
1. Consultar `getCachedSearch(term)` → si hay hit, renderizar sin fetch.
2. Tras fetch exitoso → `setCachedSearch(term, products)`.

### 4.4 Debounce en móvil

Opcional: usar 500 ms en pantallas ≤768 px:

```javascript
const debounceMs = window.matchMedia('(max-width: 768px)').matches ? 500 : 420;
searchTimer = setTimeout(() => buscarProductos(event.target.value), debounceMs);
```

---

## 5. Prioridad 4 — UI con 600–800 productos en pantalla

### 5.1 Problema

`renderList()` en `conteo.js` reconstruye todo el DOM (`list.innerHTML = ''`) en cada cambio. Con 800 ítems, web móvil se vuelve lenta.

### 5.2 Solución — Lista parcial + búsqueda local

**Archivo:** `public/assets/js/conteo.js`

1. Constante:
   ```javascript
   const VISIBLE_ITEMS_LIMIT = 50;
   ```

2. Agregar al state:
   ```javascript
   localFilter: '',
   ```

3. Modificar `renderList()`:
   ```javascript
   function renderList() {
     const allItems = Array.from(state.items.values());
     let items = allItems;

     if (state.localFilter.trim()) {
       const q = state.localFilter.trim().toLowerCase();
       items = allItems.filter(item =>
         item.codigo.toLowerCase().includes(q) ||
         item.descripcion.toLowerCase().includes(q)
       );
     } else {
       items = allItems.slice(0, VISIBLE_ITEMS_LIMIT);
     }

     $('contadorLineas').textContent = allItems.length;
     // Actualizar texto informativo:
     // "742 productos · mostrando últimos 50" o "12 coincidencias"
     // Renderizar solo `items` en el DOM
   }
   ```

4. **Nuevo campo en `conteo.php`** (debajo del buscador de catálogo):
   ```html
   <label class="form-label" for="buscarEnConteo">Buscar en mi conteo</label>
   <input class="form-control" id="buscarEnConteo" placeholder="Filtrar productos ya contados" autocomplete="off">
   ```

5. Listener:
   ```javascript
   $('buscarEnConteo')?.addEventListener('input', (e) => {
     state.localFilter = e.target.value;
     renderList();
   });
   ```

**Archivo CSS:** `public/assets/css/user-mobile.css` — asegurar que el nuevo campo sea visible y usable en pantallas pequeñas.

### 5.3 Optimización adicional (opcional)

- Al editar cantidad de un solo ítem, actualizar solo esa fila DOM en lugar de re-renderizar toda la lista.
- Paginación local con botones "Anterior / Siguiente" si se implementa "Ver todos".

---

## 6. Prioridad 5 — Eliminar escáner (solo web)

### 6.1 Archivos a modificar

| Archivo | Qué eliminar |
|---------|--------------|
| `app/pages/conteo.php` | Botón `#abrirEscaner`, modal `#modalEscanerProducto`, clases `.search-scan` |
| `public/assets/js/conteo.js` | Variables `scannerStream`, `scannerTimer`, `scannerActive`; funciones `startScanner`, `stopScanner`, `scanLoop`; listener de `#abrirEscaner` |
| `public/assets/css/style.css` | Reglas `.scanner-modal`, `.scanner-view`, `.scanner-frame`, `.scanner-status`, `.search-scan` |

### 6.2 Mejora tras eliminar escáner

- Ampliar área del campo `#buscarProducto` en móvil (`user-mobile.css`).
- Tras guardar cantidad, devolver foco a `#buscarProducto` (ya parcialmente en `focusQuantity` → Enter en cantidad).
- Quitar referencias al escáner en comentarios o documentación obsoleta.

---

## 7. Prioridad 6 — Servidor y base de datos

### 7.1 Variables de entorno

**Archivo:** `.env` (producción)

```env
APP_AUTO_MIGRATE=0
```

Evita ejecutar `ensure_schema()` en cada request (`config/database.php`).

### 7.2 MySQL (`my.ini`)

```ini
max_connections = 80
innodb_buffer_pool_size = 1G
innodb_flush_log_at_trx_commit = 2
innodb_log_file_size = 256M
```

### 7.3 PHP (`php.ini`)

```ini
memory_limit = 256M
max_execution_time = 60
opcache.enable = 1
opcache.memory_consumption = 128
```

### 7.4 Apache

- `MaxRequestWorkers 50`
- Habilitar compresión gzip/deflate para respuestas JSON.

### 7.5 Índice adicional

```sql
CREATE INDEX idx_detalle_conteo ON conteo_detalle (conteo_id);
```

Acelera SELECT/DELETE por conteo con 600–800 filas.

### 7.6 Verificar índices de productos

Confirmar en MySQL:

```sql
SHOW INDEX FROM productos;
```

Debe existir `idx_productos_fulltext_descripcion` (FULLTEXT). Si no, ejecutar migraciones o aplicar manualmente desde `config/schema.php`.

---

## 8. Monitoreo el día de la toma

Consulta útil:

```sql
SELECT event, message, context, created_at
FROM app_logs
WHERE event LIKE '%borrador%'
   OR event LIKE '%product_search%'
ORDER BY id DESC
LIMIT 100;
```

Umbrales sugeridos en `monitor_duration()`:
- Búsqueda: >300 ms → warning
- Guardado borrador/cambios: >2000 ms → warning

---

## 9. Plan de implementación por fases

| Fase | Tarea | Esfuerzo | Archivos |
|------|-------|----------|----------|
| **1** | Flag `dirty` + autoguardado 2 min desfasado | 0.5 día | `conteo.js` |
| **2** | Respaldo `localStorage` | 0.5 día | `conteo.js` |
| **3** | Caché búsqueda servidor + código exacto + sessionStorage | 1 día | `ProductRepository.php`, `search_cache.php`, actions catálogo, `conteo.js` |
| **4** | Lista parcial + buscar en conteo local | 1 día | `conteo.js`, `conteo.php`, `user-mobile.css` |
| **5** | Eliminar escáner | 0.5 día | `conteo.php`, `conteo.js`, `style.css` |
| **6** | Guardado delta (`guardar_cambios`) | 2–3 días | `conteo_sync.php`, `guardar_cambios.php`, `index.php`, `conteo.js` |
| **7** | Tuning servidor + `APP_AUTO_MIGRATE=0` | 0.5 día | `.env`, MySQL, PHP |

**Orden recomendado:** 1 → 3 → 5 → 2 → 4 → 6 → 7

---

## 10. Qué NO implementar (fuera de alcance)

- APK Flutter y carpeta `mobile_app/`
- Endpoints `/api/v1/*` y autenticación Bearer
- Redis o Memcached (caché por archivo es suficiente para 30 usuarios web)
- Cola offline avanzada (localStorage + servidor cubren el caso)
- Paginación AJAX del catálogo admin (solo afecta administradores, no contadores)

---

## 11. Checklist de pruebas antes de producción

- [ ] 1 usuario agrega 800 productos: UI fluida en Chrome móvil.
- [ ] Autoguardado no dispara si no hay cambios (`dirty = false`).
- [ ] Autoguardado delta envía solo ítems modificados (revisar Network tab).
- [ ] Recuperación de borrador desde `localStorage` tras cerrar pestaña.
- [ ] Búsqueda por código exacto retorna en <100 ms.
- [ ] Búsqueda repetida usa caché (sin segunda petición al servidor).
- [ ] Importar productos invalida caché de búsqueda.
- [ ] Finalizar conteo guarda detalle completo correctamente.
- [ ] Conflicto de versión (`409`) si dos pestañas editan el mismo conteo.
- [ ] 3–5 usuarios simultáneos en prueba de carga sin errores 500.

---

## 12. Prompt para aplicar las mejoras (copiar y pegar)

Usa el siguiente prompt en Cursor u otro asistente de código para implementar todo lo descrito en este documento.

---

```
Implementa las optimizaciones descritas en docs/indicaciones_optimizacion_web_movil.md para el proyecto Centro Ruliman Inventario.

ALCANCE ESTRICTO:
- Solo web (escritorio) y web móvil (mismo conteo.php + conteo.js + user-mobile.css).
- NO modificar mobile_app/ (Flutter) ni endpoints /api/v1/*.
- Eliminar completamente el escáner de códigos de barras en la web.

CONTEXTO:
- 25-30 usuarios contando simultáneamente.
- Cada usuario cuenta 600-800 productos.
- Catálogo de ~60 000 productos.
- Búsqueda manual por código o descripción.

IMPLEMENTAR EN ESTE ORDEN:

FASE 1 — Autoguardado inteligente (public/assets/js/conteo.js):
1. Agregar state.dirty y markDirty().
2. Llamar markDirty() al agregar, editar cantidad o eliminar producto.
3. En guardarBorrador(auto): retornar sin guardar si auto && !state.dirty.
4. Reemplazar setInterval de 30s por intervalo 2-2.5 min con delay inicial aleatorio 0-60s.

FASE 2 — Respaldo localStorage (conteo.js):
1. persistLocalDraft() con debounce 2s al marcar dirty.
2. Al cargar conteo: ofrecer recuperar borrador local si es más reciente.
3. Limpiar localStorage al finalizar conteo.

FASE 3 — Búsqueda optimizada:
1. ProductRepository::searchActive(): lookup exacto por codigo antes de FULLTEXT/LIKE.
2. Crear app/includes/search_cache.php (caché por archivos en storage/cache/search/, TTL 600s).
3. Integrar caché en searchActive(); invalidar en agregar/editar/eliminar/importar productos.
4. sessionStorage en conteo.js para últimas 100 búsquedas.
5. Debounce 500ms en viewport ≤768px, 420ms en desktop.

FASE 4 — UI lista parcial (conteo.js, conteo.php, user-mobile.css):
1. renderList() muestra solo últimos 50 ítems si no hay filtro local.
2. Campo #buscarEnConteo para filtrar productos ya contados (filtro local, sin servidor).
3. Mostrar contador "X productos · mostrando últimos 50" o "Y coincidencias".

FASE 5 — Eliminar escáner:
1. Quitar botón, modal y referencias en conteo.php.
2. Quitar todo el JS de BarcodeDetector/scanner en conteo.js.
3. Quitar estilos .scanner-* y .search-scan en style.css.
4. Mejorar UX del campo #buscarProducto en user-mobile.css.

FASE 6 — Guardado delta:
1. Crear app/includes/conteo_sync.php con sincronizar_detalle_conteo() usando INSERT ON DUPLICATE KEY UPDATE.
2. Crear app/actions/guardar_cambios.php (misma auth/CSRF/version que guardar_borrador).
3. Registrar ruta guardar_cambios en public/index.php.
4. En conteo.js: pendingUpsert (Map), pendingRemove (Set), buildDeltaPayload().
5. guardarBorrador() usa /actions/guardar_cambios cuando hay cambios pendientes.
6. Mantener reemplazar_detalle_conteo() solo en finalizar_conteo.php.

FASE 7 — Servidor:
1. Documentar en .env.example: APP_AUTO_MIGRATE=0 para producción.
2. Agregar migración/ensure_index para idx_detalle_conteo en config/schema.php si no existe.

REGLAS DE CÓDIGO:
- Seguir convenciones existentes del proyecto (PHP 8, PDO, vanilla JS, Bootstrap 5).
- Cambios mínimos y focalizados; no refactorizar código no relacionado.
- Mantener conteo_version y manejo de conflictos 409.
- Probar que buscar_producto.php y guardar_borrador.php sigan funcionando.
- No crear commits unless asked.

Al terminar, resume qué archivos se modificaron y cómo probar cada fase.
```

---

*Documento generado para soporte de 25–30 usuarios en conteo web/web móvil. Actualizar tras cada fase implementada.*

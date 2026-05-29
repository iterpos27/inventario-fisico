# Plan Integral de Mejoras - Centro del Ruliman Inventario

## 1. Objetivo

Este documento unifica el analisis realizado sobre el sistema web, la version web movil para usuarios y la APK Flutter. El objetivo es dejar una guia clara para optimizar rendimiento, seguridad, escalabilidad, experiencia de usuario y mantenimiento del proyecto.

El sistema actualmente ya cuenta con una base mucho mas robusta que al inicio: estructura reorganizada, rutas centralizadas, sesiones protegidas, CSRF, tokens moviles hasheados, busqueda web optimizada con FULLTEXT, transacciones en conteos y mejores flujos de consolidado.

Lo que queda por mejorar ya no es solamente visual. El siguiente nivel es preparar el sistema para crecer con muchos productos, varios usuarios contando al mismo tiempo, importaciones grandes y una APK estable.

## 2. Estado Actual del Sistema

### 2.1 Web Administrador

Fortalezas actuales:

- Panel de administrador separado del flujo de usuarios.
- CRUD principal para productos, usuarios, agencias y tomas.
- Acciones sensibles usando POST y CSRF.
- Busqueda web de productos optimizada con repositorio y FULLTEXT.
- Reportes y consolidado ya movidos a flujos mas seguros.
- Ordenamiento por codigo y descripcion en productos.

Falencias principales:

- Algunas consultas de reportes todavia pueden volverse lentas con mucho historial.
- La paginacion de productos usa conteo total, lo cual puede pesar cuando el catalogo crezca mucho mas.
- Las importaciones grandes siguen dependiendo de una peticion web larga.
- Parte del CSS ha sido tocado varias veces y conviene separar estilos por contexto para no romper admin, web usuario y movil.

### 2.2 Web Usuarios

Fortalezas actuales:

- Busqueda de productos con AJAX y cancelacion de solicitudes anteriores.
- Productos agregados se muestran arriba.
- Confirmacion visual y estado de guardado.
- Confirmacion para eliminar productos.
- Guardado de borrador y finalizacion con validaciones de backend.
- Soporte de camara para QR/codigo de barras en navegador compatible.

Falencias principales:

- El autoguardado web esta cada 30 segundos, lo cual podria ser mucho si hay varios usuarios activos.
- El guardado reemplaza todo el detalle del conteo en cada envio.
- Si dos guardados llegan fuera de orden, uno viejo podria pisar datos nuevos.
- El escaner web depende de `BarcodeDetector`, que no esta disponible en todos los navegadores.
- En celulares reales, la camara puede fallar si se entra por `http://IP/...` en vez de HTTPS.

### 2.3 Web Movil Usuarios

Fortalezas actuales:

- Se puede contar desde navegador movil.
- El flujo tiene busqueda, seleccion, cantidad, borrador y finalizacion.
- Se habilito lectura por camara para QR/codigo de barras.

Falencias principales:

- El diseno movil y el diseno escritorio comparten demasiados estilos.
- Cambios visuales en una version pueden afectar la otra.
- La experiencia movil necesita mantenerse separada de la web de escritorio.
- El uso de camara necesita HTTPS o contexto seguro para funcionar de forma confiable.

### 2.4 APK Flutter Usuarios

Fortalezas actuales:

- Login con token.
- Token guardado con `FlutterSecureStorage`.
- Lista de tomas asignadas.
- Pantalla de conteo.
- Busqueda de productos por API.
- Escaner de codigo de barras.
- Guardado local basico.
- Autoguardado al servidor cada 3 minutos.
- Manejo de producto repetido actualizando cantidad.

Falencias principales:

- La APK no usa la misma busqueda optimizada que la web.
- La API `/api/productos` mantiene una logica vieja con `LIKE`.
- Las llamadas HTTP no tienen timeout.
- No hay cancelacion real de busquedas anteriores en Flutter.
- El guardado local usa `SharedPreferences`, que no es ideal para borradores grandes.
- No existe una cola offline real de sincronizacion.
- Los errores del backend se muestran demasiado directo al usuario.

## 3. Hallazgos Criticos

### 3.1 Busqueda desalineada entre web y APK

La web usa `ProductRepository` con FULLTEXT para buscar productos. La APK consume `/api/productos`, pero ese endpoint todavia usa consultas propias con `codigo LIKE` y `descripcion LIKE`.

Problema:

- Una busqueda puede funcionar bien en web, pero lenta o incompleta en APK.
- Se duplican reglas.
- Cada mejora de busqueda debe hacerse dos veces.

Solucion:

- Hacer que `/api/productos` use `ProductRepository::searchActive()`.
- Mantener una sola regla de busqueda para web, web movil y APK.

### 3.2 Guardado de conteos por reemplazo completo

Actualmente al guardar se borra el detalle completo del conteo y se vuelve a insertar.

Problema:

- Funciona, pero puede ser riesgoso con solicitudes simultaneas.
- Si una peticion vieja termina despues de una nueva, podria sobrescribir datos recientes.

Solucion:

- Agregar campo de version o `updated_at` al conteo.
- Enviar la version desde frontend/APK.
- Rechazar guardados antiguos.
- A futuro, guardar cambios por item en vez de reemplazar todo.

### 3.3 Migraciones ejecutandose en cada request

El sistema ejecuta `ensure_schema()` al cargar la conexion.

Problema:

- En desarrollo ayuda.
- En produccion agrega trabajo innecesario en cada pantalla/API.
- Puede causar lentitud si el sistema crece.

Solucion:

- Crear comando o instalador para migraciones.
- En produccion no ejecutar cambios de esquema en cada request.
- Usar una tabla `schema_migrations` para saber que migraciones ya corrieron.

### 3.4 Reportes con consultas que no aprovechan bien indices

Algunas consultas usan funciones como `DATE(fecha_inicio)` en el WHERE.

Problema:

- MySQL no aprovecha igual los indices.
- Con mucho historial los reportes pueden ponerse lentos.

Solucion:

- Usar rangos:
  - `fecha_inicio >= '2026-05-01 00:00:00'`
  - `fecha_inicio < '2026-06-01 00:00:00'`
- Crear resumenes por toma finalizada.

### 3.5 Offline movil muy basico

La APK guarda borradores en `SharedPreferences`.

Problema:

- No es ideal para muchos items.
- No permite cola robusta de sincronizacion.
- No maneja conflictos de forma clara.

Solucion:

- Migrar a SQLite usando `sqflite` o `drift`.
- Crear tabla local de items contados.
- Crear tabla de operaciones pendientes.
- Sincronizar por lotes.

## 4. Plan Recomendado - Primera Fase

Esta fase busca estabilizar rendimiento, busqueda, guardado y estructura base.

### 4.1 Unificar `/api/productos` con `ProductRepository`

Objetivo:

- Que web, web movil y APK usen la misma busqueda.

Resultado esperado:

- Busquedas consistentes.
- Mejor rendimiento en APK.
- Menos duplicacion de codigo.

### 4.2 Agregar timeout y control de busquedas viejas en APK

Objetivo:

- Evitar que la app se quede esperando indefinidamente.
- Evitar que una respuesta vieja reemplace resultados nuevos.

Resultado esperado:

- Busqueda movil mas fluida.
- Menos errores confusos.
- Mejor experiencia con redes lentas.

### 4.3 Sacar `ensure_schema()` del flujo normal de produccion

Objetivo:

- Que el sistema no revise estructura de base de datos en cada request.

Resultado esperado:

- Menos carga innecesaria.
- Mejor arranque de paginas y APIs.
- Base lista para migraciones formales.

### 4.4 Optimizar reportes quitando `DATE()` del WHERE

Objetivo:

- Que las consultas usen indices correctamente.

Resultado esperado:

- Reportes mas rapidos.
- Mejor rendimiento con historiales grandes.

### 4.5 Agregar control de version en guardado de conteos

Objetivo:

- Evitar que guardados viejos sobrescriban datos nuevos.

Resultado esperado:

- Mas seguridad en autoguardado.
- Menos riesgo de perdida de informacion.

### 4.6 Separar CSS de admin, usuario web y usuario movil

Objetivo:

- Evitar que una mejora visual rompa otra parte del sistema.

Resultado esperado:

- Admin estable.
- Usuario web escritorio mejor organizado.
- Usuario movil independiente.

### 4.7 Migrar offline APK de `SharedPreferences` a SQLite

Objetivo:

- Preparar la APK para modo offline real.

Resultado esperado:

- Borradores mas confiables.
- Mejor manejo de muchos productos.
- Base lista para sincronizacion avanzada.

## 5. Plan Recomendado - Segunda Fase

Esta fase convierte el sistema de funcional a mantenible y escalable.

### 5.1 Importaciones grandes en segundo plano

Problema actual:

- Un Excel grande depende de una peticion web larga.

Mejora:

- Procesar archivos por lotes.
- Mostrar progreso.
- Registrar filas importadas, actualizadas, omitidas y con error.

Resultado:

- Importaciones de 30k, 60k o mas productos sin bloquear el navegador.

### 5.2 Logs y monitoreo basico

Mejora:

- Registrar busquedas lentas.
- Registrar errores API.
- Registrar fallos de importacion.
- Registrar duracion de reportes.
- Registrar guardados fallidos.

Resultado:

- Cuando algo vaya lento, se sabra donde esta el problema.

### 5.3 Auditoria de acciones importantes

Mejora:

- Guardar quien crea, edita, elimina, finaliza, asigna usuarios o genera consolidado.

Resultado:

- Mayor control operativo.
- Mejor trazabilidad cuando varios usuarios usan el sistema.

### 5.4 Mejorar reportes y consolidado para alto volumen

Mejora:

- Crear resumenes por toma.
- Cachear consolidados finalizados.
- Evitar recalcular todo cada vez que se descarga.

Resultado:

- Reportes mas rapidos.
- Menos carga en base de datos.

### 5.5 API versionada

Mejora:

- Pasar de `/api/productos` a rutas tipo `/api/v1/productos`.

Resultado:

- La APK no se rompe cuando se mejoren endpoints en el futuro.
- Se puede mantener compatibilidad con versiones antiguas.

### 5.6 Roles y permisos mas finos

Mejora:

- Separar permisos:
  - Administrador.
  - Supervisor.
  - Operador.
  - Solo reportes.

Resultado:

- Mejor seguridad interna.
- Menos riesgo de que un usuario tenga mas acceso del necesario.

### 5.7 Pruebas automatizadas basicas

Mejora:

- Tests para:
  - Login.
  - Busqueda.
  - Guardar borrador.
  - Finalizar conteo.
  - Consolidado.
  - Importacion.

Resultado:

- Menos regresiones.
- Mas confianza antes de subir cambios a Git.

## 6. Plan Recomendado - Tercera Fase

Esta fase apunta a madurez operativa.

### 6.1 Sincronizacion offline real en APK

Mejora:

- Cola local de cambios.
- Reintentos automaticos.
- Estado de sincronizacion por item.
- Resolucion de conflictos.

Resultado:

- La APK puede trabajar sin internet y sincronizar despues sin perder datos.

### 6.2 Dashboard en vivo

Mejora:

- Mostrar tomas activas.
- Usuarios pendientes.
- Usuarios finalizados.
- Lineas contadas.
- Ultimo guardado por usuario.

Resultado:

- El administrador puede monitorear el inventario en tiempo real.

### 6.3 Backups programados

Mejora:

- Exportar base de datos automaticamente.
- Guardar copias por fecha.
- Mantener historial de respaldos.

Resultado:

- Menor riesgo ante fallos de PC, XAMPP o base de datos.

### 6.4 Actualizacion versionada de APK

Mejora:

- Versionar APK.
- Mostrar aviso de nueva version.
- Mantener changelog.

Resultado:

- Mejor control del aplicativo movil.

### 6.5 Instalador/configurador inicial

Mejora:

- Pantalla o script para configurar:
  - Base de datos.
  - Usuario admin inicial.
  - URL del sistema.
  - Claves.

Resultado:

- Instalaciones mas limpias.
- Menos configuracion manual.

### 6.6 Documentacion tecnica y manual de usuario

Mejora:

- Manual para administrador.
- Manual para usuario de conteo.
- Manual para APK.
- Guia tecnica de despliegue.

Resultado:

- Sistema mas facil de mantener y entregar.

## 7. Recomendaciones por Area

### 7.1 Base de Datos

Recomendaciones:

- Mantener indices para codigo, estado, toma, usuario y fechas.
- Revisar consultas con `EXPLAIN` antes de optimizar a ciegas.
- Evitar funciones sobre columnas indexadas en WHERE.
- Crear migraciones versionadas.
- Crear resumenes para reportes pesados.

Prioridad:

1. Reportes por rango sin `DATE()`.
2. Control de version en conteos.
3. Migraciones formales.
4. Resumenes por toma.

### 7.2 Backend PHP

Recomendaciones:

- Usar repositories para no mezclar SQL con vistas.
- Unificar reglas de busqueda.
- Manejar errores tecnicos en logs privados.
- Devolver mensajes amigables al usuario.
- Separar acciones web y API versionada.

Prioridad:

1. API productos con `ProductRepository`.
2. Errores API genericos.
3. Logs.
4. API v1.

### 7.3 Frontend Web Admin

Recomendaciones:

- Mantener tablas simples y rapidas.
- Evitar AJAX automatico para busquedas administrativas pesadas.
- Usar boton o Enter para buscar.
- Separar CSS admin.

Prioridad:

1. CSS admin separado.
2. Paginacion mas liviana.
3. Importacion con progreso.

### 7.4 Frontend Web Usuario

Recomendaciones:

- Mantener busqueda rapida con cancelacion.
- Mantener productos recientes arriba.
- Autoguardar solo si hubo cambios.
- Reducir frecuencia de autoguardado si hay muchos usuarios.

Prioridad:

1. Control de version de guardado.
2. Autoguardado con cambios pendientes.
3. QR con fallback.

### 7.5 Web Movil

Recomendaciones:

- Separar estilos moviles.
- Mantener botones grandes.
- Mantener flujo corto: buscar, seleccionar, cantidad, guardar.
- Verificar camara con HTTPS.

Prioridad:

1. CSS movil separado.
2. HTTPS local o configuracion segura.
3. Fallback de scanner.

### 7.6 APK Flutter

Recomendaciones:

- Usar la misma busqueda que web.
- Agregar timeout.
- Controlar respuestas viejas.
- Migrar offline a SQLite.
- Mejorar mensajes de error.

Prioridad:

1. API productos unificada.
2. Timeout HTTP.
3. Control de busqueda vieja.
4. SQLite local.
5. Cola offline.

## 8. Orden de Ejecucion Recomendado

### Bloque 1 - Estabilidad y rendimiento base

1. Unificar `/api/productos` con `ProductRepository`.
2. Agregar timeout y control de busqueda vieja en APK.
3. Sacar `ensure_schema()` del flujo normal de produccion.
4. Optimizar reportes quitando `DATE()` del WHERE.
5. Agregar control de version en guardado de conteos.
6. Separar CSS de admin, usuario web y usuario movil.
7. Migrar offline APK de `SharedPreferences` a SQLite.

### Bloque 2 - Escalabilidad operativa

1. Importaciones grandes en segundo plano.
2. Logs y monitoreo basico.
3. Auditoria de acciones importantes.
4. Reportes y consolidado con resumenes.
5. API versionada.
6. Roles y permisos mas finos.
7. Pruebas automatizadas basicas.

### Bloque 3 - Madurez del sistema

1. Sincronizacion offline real.
2. Dashboard en vivo.
3. Backups programados.
4. Actualizacion versionada de APK.
5. Instalador/configurador inicial.
6. Documentacion tecnica y manuales.

## 9. Conclusion

El sistema ya tiene una base funcional y bastante avanzada para el uso actual. La prioridad no debe ser agregar mas pantallas sin control, sino fortalecer los puntos que sostienen todo el flujo:

- Busqueda rapida y unica para todos los canales.
- Guardado confiable.
- Reportes eficientes.
- Movil y APK sincronizados con la misma logica.
- CSS separado para no romper interfaces.
- Importaciones y reportes preparados para alto volumen.

Si se ejecuta el Bloque 1, el sistema quedara mucho mas estable para operar con 60k productos o mas. Con el Bloque 2, el proyecto se vuelve mantenible y auditable. Con el Bloque 3, queda preparado para uso mas profesional, con offline real, backups, versionado y documentacion.

# Inventario Fisico

Aplicacion web en PHP 8, MySQL/MariaDB, Bootstrap 5, PDO y PhpSpreadsheet para realizar conteos fisicos de inventario desde celular o PC.

El sistema permite cargar un catalogo de productos desde Excel, crear tomas fisicas, asignarlas a usuarios operativos, registrar cantidades contadas desde celular, guardar borradores, continuar conteos pendientes y finalizar conteos generando archivos Excel individuales y consolidados.

## Caracteristicas

- Login con sesiones PHP.
- Panel principal con resumen de productos, tomas fisicas y conteos.
- Importacion de productos desde Excel.
- Creacion administrativa de tomas fisicas.
- Conteos individuales por usuario participante.
- Conteo mobile first con busqueda rapida.
- Guardado manual de borradores.
- Autoguardado cada 30 segundos.
- Finalizacion de conteos con bloqueo de edicion.
- Generacion de Excel final con codigo, descripcion, cantidad y usuario.
- Reporte consolidado por toma fisica.
- Reportes de conteos individuales borrador y finalizados.
- Gestion de usuarios administradores y operativos.
- Logo configurable.

## Requisitos

- PHP 8 o superior.
- MySQL o MariaDB.
- Apache, por ejemplo desde XAMPP.
- Composer.

## Instalacion local en XAMPP

1. Copiar la carpeta del proyecto dentro de `htdocs`.
2. Crear la base de datos importando `database.sql` desde phpMyAdmin o MySQL Workbench.
3. Entrar a la carpeta del proyecto y ejecutar:

```bash
composer require phpoffice/phpspreadsheet
```

4. Revisar las credenciales en `config/database.php`.
5. Abrir el proyecto desde el navegador, por ejemplo:

```text
http://localhost/centro_ruliman_inventario
```

## Usuario inicial

- Usuario: `admin`
- Contrasena: `admin123`

## Configuracion de base de datos

Editar `config/database.php` si el servidor MySQL usa otro usuario, clave, host o puerto.

```php
$dbHost = 'localhost';
$dbName = 'centro_ruliman_inventario';
$dbUser = 'root';
$dbPass = '';
```

Si MySQL corre en otro puerto, usar este formato:

```php
$dbHost = '127.0.0.1;port=3307';
```

## Hosting cPanel

Subir los archivos al hosting, importar `database.sql`, ajustar `config/database.php` con los datos de la base de datos del hosting y ejecutar Composer si el proveedor lo permite.

Si el hosting no permite Composer por terminal, generar la carpeta `vendor` localmente y subirla junto con el proyecto.

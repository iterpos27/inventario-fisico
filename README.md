# CENTRO DEL RULIMÁN - Sistema de Conteo e Inventario

Aplicación web en PHP 8, MySQL/MariaDB, Bootstrap 5, PDO y PhpSpreadsheet para conteos físicos de inventario desde celular.

## Instalación local en XAMPP

1. Copiar la carpeta `centro_ruliman_inventario` dentro de `htdocs`.
2. Crear la base de datos importando `database.sql` desde phpMyAdmin.
3. Entrar a la carpeta del proyecto y ejecutar:

```bash
composer require phpoffice/phpspreadsheet
```

4. Revisar credenciales en `config/database.php`.
5. Abrir `http://localhost/centro_ruliman_inventario`.

Usuario inicial:

- Usuario: `admin`
- Contraseña: `admin123`

## Hosting cPanel

Subir los archivos al hosting, importar `database.sql`, ajustar `config/database.php` con usuario, clave y nombre de base de datos del hosting, y ejecutar Composer si el proveedor lo permite. Si no permite Composer por terminal, subir también la carpeta `vendor` generada localmente.

# Centro del Ruliman Conteo

App Flutter para usuarios de conteo.

## Requisitos

- Flutter SDK instalado.
- Android Studio o Android SDK configurado.
- El backend PHP accesible desde el telefono.

En emulador Android, `localhost` apunta al emulador. Use:

```txt
https://10.0.2.2/centro_ruliman_inventario
```

En telefono fisico, use la IP del computador en la red:

```txt
https://192.168.1.10/centro_ruliman_inventario
```

## Comandos

```bash
flutter create --platforms android .
flutter pub get
flutter run
flutter build apk --release
```

El primer comando genera la carpeta `android/` porque este repositorio deja versionado el codigo de la app y no los archivos generados por Flutter.

## Funciones incluidas

- Login por API con token.
- Lista de tomas asignadas.
- Inicio de conteo.
- Busqueda de producto por codigo o descripcion.
- Escaneo de codigo de barras con camara.
- Guardar borrador.
- Finalizar conteo.
- Cache local del conteo en curso para recuperarlo si se cierra la app.

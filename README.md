# PlantITa

Aplicación web para el cuidado de plantas.

## Sprint 1: Autenticación y Catálogo Básico

### Objetivo
Crear arquitectura, repositorio y subirlo a Github. Permitir al usuario registrarse, iniciar sesión y explorar el catálogo de plantas.

### Historias de Usuario
- HU01 – Registro: Como usuario, quiero registrarme para acceder a la aplicación.
- HU02 – Login: Como usuario registrado, quiero iniciar sesión para usar la aplicación.
- HU03 – Recuperación de contraseña: Como usuario, quiero recuperar mi contraseña si la olvido.

### Tareas Técnicas
- Base de datos: Tabla usuarios y tabla plantas (20 plantas cargadas).
- Backend PHP: register.php, login.php, get_plantas.php, forgot_password.php.
- Frontend: Pantalla Login, Pantalla Registro, Pantalla Recuperación, Pantalla catálogo (cards simples).
- Testing: Registro funciona, Login funciona, Catálogo carga desde DB, Recuperación simula envío.

## Estructura

- `index.html` - Interfaz principal
- `styles.css` - Estilos con Roboto y colores #e6c2bf #dfb160 #007aef
- `js/app.js` - Lógica del frontend
- `register.php` - Registro de usuarios
- `login.php` - Inicio de sesión
- `get_plantas.php` - Obtener plantas del catálogo
- `forgot_password.php` - Recuperación de contraseña
- `create_db.php` - Inicializa la base de datos SQLite
- `plantita.db` - Base de datos generada tras ejecutar `create_db.php`

## Instalación

1. Coloca el proyecto en un servidor PHP local (XAMPP, WAMP, etc.)
2. Ejecuta `create_db.php` para crear `plantita.db`:

```bash
php create_db.php
```

3. Abre `index.html` en el navegador desde el servidor local.

## Desarrollo

Próximos sprints incluirán colección personal, agenda de cuidados, etc.
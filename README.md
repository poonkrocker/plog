# Pizzalog

Clon minimalista de Fotolog construido en PHP 8 + MySQL.

---

## Estructura de archivos

```
pizzalog/
├── schema.sql                  → Schema de la base de datos
├── install.php                 → Instalador (eliminar post-deploy)
├── includes/
│   ├── config.php              → Configuración (DB, constantes)
│   ├── db.php                  → Conexión PDO singleton
│   ├── auth.php                → Login, registro, invitaciones
│   ├── image.php               → Procesamiento de imágenes (Imagick)
│   ├── helpers.php             → Funciones de posts, comentarios, FF
│   └── layout.php              → Layout HTML base
├── public/                     → Raíz del dominio (document root)
│   ├── .htaccess               → URL rewriting (/username)
│   ├── index.php               → Feed principal
│   ├── login.php
│   ├── logout.php
│   ├── register.php            → Registro con código de invitación
│   ├── user.php                → Perfil de usuario
│   ├── upload.php              → Subir/editar post del día
│   ├── archive.php             → Archivo calendario
│   ├── favorites.php           → Lista completa de FFs
│   ├── settings.php            → Ajustes de perfil
│   ├── invitations.php         → Ver/generar invitaciones
│   ├── uploads/                → Fotos originales (permisos 755)
│   │   └── thumbs/             → Thumbnails (permisos 755)
│   └── assets/
│       ├── css/pizzalog.css
│       └── img/
│           ├── no-photo.png    → Placeholder foto
│           └── default-avatar.png
└── admin/
    └── index.php               → Panel de administración
```

---

## Deploy en DonWeb

### 1. Base de datos
Crear en el panel Ferozo:
- Una base MySQL: `pizzalog`
- Un usuario MySQL con todos los permisos sobre esa base

### 2. Subdominio
En el panel Ferozo:
- Crear subdominio `pizzalog.net` (o configurar el dominio principal)
- Apuntar el document root a la carpeta `public/`

### 3. Configuración
Editar `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pizzalog');
define('DB_USER', 'tu_usuario_mysql');
define('DB_PASS', 'tu_password_mysql');
define('SITE_URL', 'https://pizzalog.net');
```

### 4. Subir archivos
Subir todo el proyecto por FTP. La carpeta `public/` debe ser el document root.

### 5. Permisos
```
chmod 755 public/uploads/
chmod 755 public/uploads/thumbs/
```

### 6. Instalar
Editar `install.php`:
- Cambiar `INSTALL_SECRET` por algo seguro
- Cambiar `$admin_mail`

Acceder: `https://pizzalog.net/install.php?secret=TU_SECRETO`

Anotar la contraseña generada y **eliminar install.php del servidor**.

### 7. Placeholders de imagen
Subir a `public/assets/img/`:
- `no-photo.png` (110x82 px, imagen placeholder)
- `default-avatar.png` (80x80 px, avatar por defecto)

---

## Reglas de negocio

| Regla | Valor |
|---|---|
| Posts por día | 1 (por día calendario, zona horaria Argentina) |
| Foto max | 800x600 px |
| Título max | 150 caracteres |
| Texto del post max | 2000 caracteres |
| Comentarios por post | 20 |
| Texto de comentario max | 500 caracteres |
| Emojis | No permitidos (posts ni comentarios) |
| Thumbnails | 110x82 px (crop centrado) |
| Invitaciones por usuario | 5 (admin: ilimitadas) |
| Borrar comentarios | Solo el dueño del perfil (y admin) |

---

## Extensiones PHP requeridas
- `pdo_mysql`
- `imagick` (≥ 3.x) — confirmado disponible en DonWeb
- `mbstring`
- `session`

---

## Notas

- El URL rewriting convierte `pizzalog.net/username` → `user.php?u=username`
- Las imágenes se procesan con Imagick al subir (resize + thumbnail automático)
- La zona horaria usada es `America/Argentina/Cordoba`
- Los passwords se hashean con `PASSWORD_BCRYPT`

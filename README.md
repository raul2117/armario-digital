# Armario digital

Aplicación web en PHP para organizar tu ropa: fotos de tus prendas, outfits, calendario, estadísticas de uso y recomendaciones según el tiempo que hace. Pensada para instalarla en casa (por ejemplo, en una Raspberry Pi) y usarla desde el móvil o el ordenador, con varios usuarios.

Interfaz propia, alegre y adaptada al móvil, con modo claro y oscuro. Sin frameworks: PHP, MariaDB/MySQL, CSS y algo de JavaScript.

## Qué hace

- **Mi armario:** prendas con foto (desde la cámara del móvil), categoría, color, temporada, marca y notas. Filtros por categoría, favoritas, búsqueda y prendas «olvidadas». Si una prenda no tiene foto, se dibuja su silueta en su color.
- **Outfits:** se montan tocando prendas que van a su hueco (arriba, abajo, abrigo, calzado, complemento). Se pueden programar para un día.
- **Me lo pongo:** apunta que has usado una prenda o un outfit; con eso se alimentan el calendario y las estadísticas.
- **Inicio:** sugerencia de outfit del día, el tiempo de tu ubicación con previsión de 4 días y un consejo (abrigarse, entretiempo, ropa ligera, paraguas…).
- **Calendario:** outfits programados y lo que te pusiste cada día.
- **Estadísticas:** prendas por categoría, usos por mes, las más y las menos usadas, colores y temporadas.
- **Ajustes:** nombre, ubicación, apariencia (claro / oscuro / automático), preferencias, cambio de contraseña, cerrar otras sesiones y descarga de tus prendas en CSV.
- **Usuarios (administrador):** alta de usuarios con contraseña temporal, roles, activar/desactivar, desbloquear, cerrar sesiones, borrar y registro de accesos.

## Requisitos

- Linux con Apache 2.4, PHP 8.x (con `pdo_mysql` y `gd`) y MariaDB 10.5 o superior.
- Conexión a internet en el servidor para el tiempo (opcional).

El script de despliegue instala lo que falte en Debian, Raspberry Pi OS o Ubuntu.

## Instalación

### Desde Windows con el script (recomendado)

1. Descarga o clona el repositorio.
2. Haz doble clic en **`Desplegar.bat`**. La primera vez te pregunta el servidor SSH (por ejemplo `pi@192.168.1.50`) y lo guarda en `despliegue/servidor.txt`.
3. El script copia la app a `/var/www/html/armario`, instala lo que falte, crea la base de datos con una contraseña aleatoria y configura Apache.
4. Abre `http://IP-DEL-SERVIDOR/armario/` y crea la cuenta de administrador.

Para actualizar, vuelve a ejecutar `Desplegar.bat`: se conservan los datos, las fotos y la configuración del servidor.

Opciones: `powershell -File despliegue\desplegar.ps1 -Servidor usuario@IP -Destino /var/www/html/ropa`

Para no escribir la contraseña SSH cada vez (opcional, una sola vez desde PowerShell):

```powershell
ssh-keygen -t ed25519
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh usuario@IP "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```

### A mano en el servidor

```bash
git clone https://github.com/USUARIO/armario-digital.git /tmp/armario-deploy
bash /tmp/armario-deploy/despliegue/remoto.sh /var/www/html/armario
```

O sin el script: copia los archivos a tu carpeta web, copia `config.example.php` como `config.php` con tus datos, importa `sql/schema.sql` (cambiando antes `cambia_esta_clave`) y da permisos de escritura a Apache sobre `uploads/`.

## Datos de prueba

En el servidor, por SSH:

```bash
sudo php /var/www/html/armario/herramientas/datos_prueba.php                        # crea el usuario prueba@armario.local con datos
sudo php /var/www/html/armario/herramientas/datos_prueba.php --ciudad="Sevilla"     # lo mismo, con esa ubicación
sudo php /var/www/html/armario/herramientas/datos_prueba.php --email=tu@correo.com  # añade datos de prueba a tu usuario
sudo php /var/www/html/armario/herramientas/datos_prueba.php --borrar               # borra el usuario de prueba
```

Crea unas 39 prendas, 8 outfits (algunos programados para los próximos días) y unos 4 meses de historial de usos.

## El tiempo

Cada usuario elige su ubicación buscando su ciudad o pueblo (o con la ubicación del dispositivo, que los navegadores solo permiten si la app se abre por `https`). Los datos vienen de [Open-Meteo](https://open-meteo.com/), gratis para uso no comercial y sin clave. La previsión se guarda 30 minutos en caché. A partir de las 20:00 el consejo es para el día siguiente.

## Seguridad

- No hay registro libre: solo el administrador crea cuentas. La primera cuenta se crea en la configuración inicial, que después se desactiva sola.
- Contraseñas de al menos 10 caracteres con letras y números, guardadas con `password_hash` (bcrypt). Las contraseñas temporales obligan a cambiarlas al entrar.
- Bloqueo de cuenta tras 5 fallos (15 minutos) y de equipo tras 20 fallos en 15 minutos. Registro de accesos de 90 días.
- Al desactivar a alguien o cambiarle la contraseña se cierran sus sesiones.
- Token CSRF en todos los formularios, consultas preparadas (PDO), cookies `HttpOnly` y `SameSite`, cabeceras contra *clickjacking* y *MIME sniffing*.
- Las fotos se validan por tipo real, se renombran y se reducen; en `uploads/` PHP no se ejecuta. `includes/` y `herramientas/` no son accesibles desde la web.
- Cada usuario solo ve y modifica sus propios datos.

Las fotos se sirven con un nombre aleatorio, pero no pasan por el login: si publicas la app en internet, conviene servirla por `https` y proteger también `uploads/`.

## Estructura

| Ruta | Para qué |
|---|---|
| `index.php` | Inicio: sugerencia, el tiempo, contadores, últimas prendas |
| `armario.php`, `prenda.php` | Listado de prendas y alta/edición/borrado |
| `outfit.php`, `usar.php` | Outfits y «Me lo pongo» |
| `calendario.php`, `estadisticas.php` | Calendario y estadísticas |
| `ajustes.php`, `ubicacion.php`, `cambiar_clave.php`, `exportar.php` | Ajustes, ubicación, contraseña y CSV |
| `usuarios.php` | Gestión de usuarios (administrador) |
| `login.php`, `instalar.php`, `salir.php` | Entrar, configuración inicial y salir |
| `api/` | Favoritas y tema (llamadas desde JavaScript) |
| `includes/` | Arranque, funciones, el tiempo, cabeceras y pies |
| `assets/` | `app.css` y `app.js` |
| `sql/schema.sql` | Base de datos (se puede ejecutar varias veces para actualizar) |
| `herramientas/datos_prueba.php` | Datos de prueba (solo desde la terminal) |
| `despliegue/` | Scripts de instalación y actualización |

## Pendiente

- Recuperar la contraseña por email.

## Créditos y licencia

- Avisos y confirmaciones: [SweetAlert2](https://sweetalert2.github.io/).
- Tipografías: Bricolage Grotesque y DM Sans (Google Fonts).
- Datos del tiempo: [Open-Meteo](https://open-meteo.com/).

Código bajo licencia MIT (ver `LICENSE`).

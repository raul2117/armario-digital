#!/usr/bin/env bash
# Se ejecuta en la Raspberry. Lo lanza desplegar.ps1; no hace falta usarlo a mano.
set -euo pipefail

DESTINO="${1:-/var/www/html/armario}"
ORIGEN="/tmp/armario-deploy"

echo "==> Comprobando Apache, PHP y MariaDB"
FALTA=""
command -v apache2 >/dev/null 2>&1 || FALTA="$FALTA apache2"
command -v php >/dev/null 2>&1     || FALTA="$FALTA php libapache2-mod-php"
command -v mysql >/dev/null 2>&1   || FALTA="$FALTA mariadb-server"
php -m 2>/dev/null | grep -qi pdo_mysql || FALTA="$FALTA php-mysql"
php -m 2>/dev/null | grep -qi '^gd$'    || FALTA="$FALTA php-gd"
if [ -n "$FALTA" ]; then
  echo "    Instalando:$FALTA"
  sudo apt-get update -qq
  sudo apt-get install -y $FALTA
fi

echo "==> Copiando la aplicación a $DESTINO"
sudo mkdir -p "$DESTINO/uploads"
PRIMERA=0
[ -f "$DESTINO/config.php" ] || PRIMERA=1
# Todas las páginas PHP de la raíz salvo la configuración, más las carpetas de la app.
# config.php del servidor nunca se sobrescribe; la primera vez se crea a partir de config.example.php
for f in "$ORIGEN"/*.php; do
  case "$(basename "$f")" in config.php|config.example.php) ;; *) sudo cp "$f" "$DESTINO/" ;; esac
done
sudo cp -r "$ORIGEN"/{includes,assets,api,herramientas} "$DESTINO/"
[ "$PRIMERA" = 1 ] && sudo cp "$ORIGEN/config.example.php" "$DESTINO/config.php"
# Ya no existe el registro libre
sudo rm -f "$DESTINO/registro.php"

if [ "$PRIMERA" = 1 ]; then
  echo "==> Primera instalación: creando base de datos con una clave aleatoria"
  CLAVE="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)"
  sudo sed -i "s/cambia_esta_clave/$CLAVE/" "$DESTINO/config.php"
  sed "s/cambia_esta_clave/$CLAVE/" "$ORIGEN/sql/schema.sql" | sudo mysql
else
  echo "==> Actualizando tablas (sin tocar tus datos)"
  sed -n '/^USE armario;/,$p' "$ORIGEN/sql/schema.sql" | sudo mysql
fi

echo "==> Permisos"
sudo chown -R root:root "$DESTINO"
# Al copiar desde Windows las carpetas pueden llegar como 700: se fijan permisos legibles para Apache
sudo find "$DESTINO" -type d -exec chmod 755 {} +
sudo find "$DESTINO" -type f -exec chmod 644 {} +
sudo chown -R www-data:www-data "$DESTINO/uploads"
sudo chmod 640 "$DESTINO/config.php"
sudo chgrp www-data "$DESTINO/config.php"

echo "==> Configuración de Apache y PHP"
sudo tee /etc/apache2/conf-available/armario.conf >/dev/null <<EOF
<Directory "$DESTINO/uploads">
    <IfModule php_module>
        php_admin_flag engine off
    </IfModule>
    <IfModule php7_module>
        php_admin_flag engine off
    </IfModule>
    Options -Indexes
</Directory>
<Directory "$DESTINO/includes">
    Require all denied
</Directory>
<Directory "$DESTINO/herramientas">
    Require all denied
</Directory>
EOF
sudo a2enconf -q armario >/dev/null
for ini in /etc/php/*/apache2/conf.d; do
  printf 'upload_max_filesize = 8M\npost_max_size = 10M\n' | sudo tee "$ini/99-armario.ini" >/dev/null
done
sudo systemctl reload apache2

rm -rf "$ORIGEN"
IP="$(hostname -I | awk '{print $1}')"
echo
echo "Listo. Abre http://$IP/$(basename "$DESTINO")/"

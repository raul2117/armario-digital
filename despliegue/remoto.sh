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
command -v openssl >/dev/null 2>&1 || FALTA="$FALTA openssl"
command -v curl >/dev/null 2>&1    || FALTA="$FALTA curl"
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
# Se reemplazan enteras (así no quedan archivos viejos)
sudo rm -rf "$DESTINO"/{includes,assets,api,herramientas}
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
# El usuario de la app solo necesita leer y escribir datos; las tablas las crea y actualiza root
sudo mysql -e "REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'armario'@'localhost'; GRANT SELECT, INSERT, UPDATE, DELETE ON armario.* TO 'armario'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Permisos"
sudo chown -R root:root "$DESTINO"
# Al copiar desde Windows las carpetas pueden llegar como 700: se fijan permisos legibles para Apache
sudo find "$DESTINO" -type d -exec chmod 755 {} +
sudo find "$DESTINO" -type f -exec chmod 644 {} +
sudo chown -R www-data:www-data "$DESTINO/uploads"
sudo chmod 640 "$DESTINO/config.php"
sudo chgrp www-data "$DESTINO/config.php"

echo "==> HTTPS"
sudo a2enmod -q ssl headers rewrite >/dev/null
DOCROOT="$(dirname "$DESTINO")"
APP="$(basename "$DESTINO")"
CERTS=/etc/ssl/armario
if [ ! -f "$CERTS/armario.crt" ]; then
  # Certificado propio (10 años) válido para el nombre y las IP de este equipo
  SAN="DNS:$(hostname),DNS:$(hostname).local,DNS:localhost,IP:127.0.0.1"
  for ip in $(hostname -I); do case "$ip" in *:*) ;; *) SAN="$SAN,IP:$ip" ;; esac; done
  sudo mkdir -p "$CERTS"
  sudo openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=$(hostname)" \
    -addext "subjectAltName=$SAN" -keyout "$CERTS/armario.key" -out "$CERTS/armario.crt" 2>/dev/null
  sudo chmod 600 "$CERTS/armario.key"
  echo "    Certificado creado para: $SAN"
fi
SITIOS="$(sudo apache2ctl -S 2>/dev/null || true)"
if [ -f /etc/apache2/sites-enabled/armario-ssl.conf ] || ! grep -q ':443' <<<"$SITIOS"; then
  sudo tee /etc/apache2/sites-available/armario-ssl.conf >/dev/null <<FIN_SSL
<VirtualHost *:443>
    DocumentRoot $DOCROOT
    SSLEngine on
    SSLCertificateFile $CERTS/armario.crt
    SSLCertificateKeyFile $CERTS/armario.key
</VirtualHost>
FIN_SSL
  sudo a2ensite -q armario-ssl >/dev/null
else
  echo "    Ya hay un sitio HTTPS en este servidor: se usa ese."
fi

echo "==> Configuración de Apache y PHP"
escribir_conf() {
  # $1 = 1 para redirigir http -> https
  {
    if [ "$1" = 1 ]; then
      cat <<FIN_REDIR
<Directory "$DESTINO">
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=302,L]
</Directory>
FIN_REDIR
    fi
    cat <<FIN_CONF
<Directory "$DESTINO/uploads">
    # Solo fotos: aquí no se ejecuta nada, ni con mod_php ni con PHP-FPM
    <IfModule php_module>
        php_admin_flag engine off
    </IfModule>
    <IfModule php7_module>
        php_admin_flag engine off
    </IfModule>
    <FilesMatch "\.(php[0-9]?|phtml|phar|phps|pl|py|cgi|sh|html?|svg)$">
        Require all denied
    </FilesMatch>
    RemoveHandler .php .phtml .phar
    Options -Indexes -ExecCGI
    Header always set X-Content-Type-Options "nosniff"
</Directory>
<Directory "$DESTINO/includes">
    Require all denied
</Directory>
<Directory "$DESTINO/herramientas">
    Require all denied
</Directory>
FIN_CONF
  } | sudo tee /etc/apache2/conf-available/armario.conf >/dev/null
}
escribir_conf 0
sudo a2enconf -q armario >/dev/null
for ini in /etc/php/*/apache2/conf.d; do
  printf 'upload_max_filesize = 8M\npost_max_size = 10M\n' | sudo tee "$ini/99-armario.ini" >/dev/null
done
sudo apache2ctl configtest
sudo systemctl reload apache2
sleep 1

# Solo se obliga a usar https si responde bien (si no, la app sigue funcionando por http)
CODIGO="$(curl -sk -o /dev/null -w '%{http_code}' "https://127.0.0.1/$APP/login.php" || true)"
if [ "$CODIGO" = 200 ] || [ "$CODIGO" = 302 ]; then
  escribir_conf 1
  sudo apache2ctl configtest
  sudo systemctl reload apache2
  HTTPS_OK=1
else
  echo "    AVISO: https://127.0.0.1/$APP/ no responde ($CODIGO). La app queda en http."
  HTTPS_OK=0
fi

rm -rf "$ORIGEN"
IP="$(hostname -I | awk '{print $1}')"
echo
if [ "$HTTPS_OK" = 1 ]; then
  echo "Listo. Abre https://$IP/$APP/"
  echo "El certificado es propio: la primera vez el navegador avisará; acepta el aviso para esta dirección."
else
  echo "Listo. Abre http://$IP/$APP/"
fi

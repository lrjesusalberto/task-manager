# Imagen oficial de PHP: usa mysqlnd, que negocia caching_sha2_password.
#
# MySQL 9 elimino el plugin mysql_native_password del servidor, asi que
# caching_sha2_password es la unica opcion y el cliente tiene que soportarlo.
# El PHP que construye Nixpacks enlaza libmysqlclient y falla con
# SQLSTATE[HY000] [2054] aunque openssl este cargado.
FROM php:8.4-cli-alpine

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

# Railway inyecta PORT; 8080 es el valor por defecto en local.
ENV PORT=8080
EXPOSE 8080

CMD php database/migrar.php && php -S 0.0.0.0:${PORT} -t public public/router.php

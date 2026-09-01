# Imagen oficial de PHP: incluye mysqlnd, que sí negocia caching_sha2_password
# (el método de autenticación por defecto de MySQL 8). El build de Nixpacks
# enlazaba libmysqlclient, que no lo soporta y fallaba con SQLSTATE[HY000] [2054].
FROM php:8.4-cli-alpine

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

# Railway inyecta PORT; 8080 es el valor por defecto en local.
ENV PORT=8080
EXPOSE 8080

CMD php database/migrar.php && php -S 0.0.0.0:${PORT} -t public public/router.php

# Task Manager

Gestor de tareas con **API REST en PHP** y base de datos **MySQL**. El backend expone endpoints
JSON y el frontend los consume con `fetch`, sin recargar la página.

## Qué hace

- **CRUD completo de tareas**: crear, listar, ver, editar, completar y eliminar.
- **Categorías** como entidad propia, con color y recuento de tareas asociadas.
- **Prioridades** (baja, media, alta) y **fecha de vencimiento**, con aviso de tareas vencidas.
- **Filtros combinables** por estado, prioridad y categoría, más búsqueda por título.
- **Validación en servidor** con errores por campo y códigos HTTP correctos.

## Tecnologías

| Área | Herramientas |
| --- | --- |
| Backend | PHP 8.4, sin frameworks |
| Base de datos | MySQL 8 (SQLite en desarrollo) |
| Acceso a datos | PDO con consultas preparadas |
| Frontend | JavaScript (módulos ES), CSS puro |
| Pruebas | 29 pruebas propias sobre SQLite en memoria |

## API

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/api/tareas` | Lista tareas y devuelve el resumen |
| `POST` | `/api/tareas` | Crea una tarea |
| `GET` | `/api/tareas/{id}` | Devuelve una tarea |
| `PUT` | `/api/tareas/{id}` | Actualiza los campos enviados |
| `PATCH` | `/api/tareas/{id}/completar` | Alterna completada / pendiente |
| `DELETE` | `/api/tareas/{id}` | Elimina la tarea |
| `GET` | `/api/categorias` | Lista categorías con su recuento |
| `POST` | `/api/categorias` | Crea una categoría |
| `PUT` | `/api/categorias/{id}` | Actualiza una categoría |
| `DELETE` | `/api/categorias/{id}` | Elimina una categoría |
| `GET` | `/api/salud` | Comprobación de estado |

`GET /api/tareas` admite los parámetros `estado` (`todas`, `pendientes`, `completadas`),
`prioridad`, `categoria_id` y `buscar`.

### Ejemplo

```bash
curl -X POST http://localhost:8080/api/tareas \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Preparar entrevista","prioridad":"alta","vence_el":"2026-12-31"}'
```

```json
{
  "id": 7,
  "titulo": "Preparar entrevista",
  "completada": false,
  "prioridad": "alta",
  "vence_el": "2026-12-31",
  "categoria_nombre": null
}
```

Los errores de validación devuelven `422` con el detalle por campo:

```json
{
  "error": "Los datos enviados no son válidos.",
  "detalles": { "titulo": "El título es obligatorio." }
}
```

## Puesta en marcha

Necesitas PHP 8.1 o superior con las extensiones `pdo_mysql` (producción) o `pdo_sqlite`
(desarrollo).

```bash
# 1. Configurar el entorno
cp .env.example .env
```

Para desarrollo, la forma más rápida es usar SQLite sin instalar MySQL. En `.env`:

```
DB_SQLITE=database/tareas.sqlite
APP_DEBUG=true
```

Para MySQL, rellena `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`, y deja `DB_SQLITE` comentado.

```bash
# 2. Crear las tablas y datos de ejemplo
php database/instalar.php --ejemplo

# 3. Arrancar
php -S localhost:8080 -t public public/router.php
```

La aplicación queda en `http://localhost:8080`.

## Pruebas

```bash
php tests/ApiTest.php
```

Cubren el CRUD, los filtros, el orden por prioridad, el recuento del resumen y la integridad
referencial. Se ejecutan sobre SQLite en memoria, así que no tocan datos reales ni requieren
configuración.

## Estructura

```
public/           Raíz web
├─ index.php        Punto de entrada: API y frontend
├─ router.php       Enrutador del servidor integrado de PHP
├─ index.html       Interfaz
└─ assets/          CSS y JavaScript
src/
├─ Core/            Configuración, conexión, enrutador y respuestas
├─ Models/          Acceso a datos (Tarea y Categoria)
└─ Controllers/     Validación y respuestas de la API
database/
├─ schema.sql       Esquema de MySQL
├─ schema.sqlite.sql Esquema equivalente para SQLite
└─ instalar.php     Crea las tablas y datos de ejemplo
tests/              Pruebas de la API
```

## Detalles de implementación

**Consultas preparadas en todas las operaciones.** Ningún valor de entrada se concatena en el SQL,
lo que evita la inyección de SQL. Los nombres de columna en los `UPDATE` dinámicos proceden de una
lista blanca fija, no de las claves que envía el cliente.

**Borrado con integridad referencial.** Las tareas referencian la categoría con `ON DELETE SET
NULL`: al eliminar una categoría, sus tareas no desaparecen, solo se quedan sin ella. Hay una
prueba automatizada que lo verifica.

**Actualizaciones parciales.** `PUT /api/tareas/{id}` solo modifica los campos presentes en la
petición, en lugar de sobrescribir la fila entera con valores por defecto.

**Escapado en el frontend.** El texto que introduce el usuario se inserta con `textContent` antes
de volcarlo al DOM, de modo que un título con HTML se muestra como texto y no se ejecuta.

**Un único punto de entrada.** `index.php` distingue entre rutas `/api/...` y el resto, que sirve
el frontend. Así la aplicación funciona con una sola configuración de servidor.

## Licencia

MIT

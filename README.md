# Wine-Cellar 🍷

Sistema de Gestión de Bodega de Vinos - Wine Cellar Management System

## Descripción

Una aplicación web para gestionar una bodega de vinos especializada en vinos finos, incluyendo:

- **Coleccionistas**: Gestión de clientes y coleccionistas de vino
- **Sommeliers Certificados**: Registro de sommeliers con niveles de certificación
- **Botellas**: Catalogación de botellas por denominación (DOCG, DOC, IGT, orgánico)
- **Envejecimiento**: Seguimiento de registros de envejecimiento
- **Catas**: Gestión de sesiones de cata y evaluaciones

## Requisitos

- Node.js 18+
- npm 8+

## Instalación

```bash
npm install
```

## Uso

### Iniciar el servidor

```bash
npm start
```

La aplicación estará disponible en http://localhost:3000

### Ejecutar tests

```bash
npm test
```

### Linting

```bash
npm run lint
```

## API Endpoints

### Coleccionistas
- `GET /api/collectors` - Listar todos los coleccionistas
- `GET /api/collectors/:id` - Obtener un coleccionista
- `POST /api/collectors` - Crear un coleccionista
- `PUT /api/collectors/:id` - Actualizar un coleccionista
- `DELETE /api/collectors/:id` - Eliminar un coleccionista

### Sommeliers
- `GET /api/sommeliers` - Listar todos los sommeliers
- `GET /api/sommeliers/:id` - Obtener un sommelier
- `POST /api/sommeliers` - Crear un sommelier
- `PUT /api/sommeliers/:id` - Actualizar un sommelier
- `DELETE /api/sommeliers/:id` - Eliminar un sommelier

### Botellas
- `GET /api/bottles` - Listar todas las botellas
- `GET /api/bottles?denomination=DOCG` - Filtrar por denominación
- `GET /api/bottles/:id` - Obtener una botella
- `POST /api/bottles` - Crear una botella
- `PUT /api/bottles/:id` - Actualizar una botella
- `DELETE /api/bottles/:id` - Eliminar una botella

### Envejecimiento
- `GET /api/aging` - Listar registros de envejecimiento
- `GET /api/aging/:id` - Obtener un registro
- `POST /api/aging` - Crear un registro
- `PUT /api/aging/:id` - Actualizar un registro
- `DELETE /api/aging/:id` - Eliminar un registro

### Catas
- `GET /api/tastings` - Listar catas
- `GET /api/tastings/:id` - Obtener una cata
- `POST /api/tastings` - Crear una cata
- `PUT /api/tastings/:id` - Actualizar una cata
- `DELETE /api/tastings/:id` - Eliminar una cata

## Denominaciones de Vino Soportadas

- **DOCG** (Denominazione di Origine Controllata e Garantita)
- **DOC** (Denominazione di Origine Controllata)
- **IGT** (Indicazione Geografica Tipica)
- **organic** (Vino Orgánico)

## Tecnologías

- **Backend**: Node.js, Express.js
- **Base de datos**: SQLite (better-sqlite3)
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Testing**: Jest, Supertest
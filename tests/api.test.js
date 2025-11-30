const request = require('supertest');
const path = require('path');
const fs = require('fs');

// Use test database
const testDbPath = path.join(__dirname, 'test.db');
process.env.DB_PATH = testDbPath;

// Clean up before tests
if (fs.existsSync(testDbPath)) {
  fs.unlinkSync(testDbPath);
}

const app = require('../src/app');
const db = require('../src/db');

afterAll(() => {
  db.close();
  if (fs.existsSync(testDbPath)) {
    fs.unlinkSync(testDbPath);
  }
});

describe('Wine Cellar API', () => {
  describe('Health Check', () => {
    test('GET /api/health returns ok', async () => {
      const res = await request(app).get('/api/health');
      expect(res.statusCode).toBe(200);
      expect(res.body.status).toBe('ok');
    });
  });

  describe('Collectors API', () => {
    let collectorId;

    test('POST /api/collectors creates a new collector', async () => {
      const res = await request(app)
        .post('/api/collectors')
        .send({
          name: 'Juan García',
          email: 'juan@example.com',
          phone: '+34 612 345 678',
          address: 'Madrid, Spain'
        });
      expect(res.statusCode).toBe(201);
      expect(res.body.name).toBe('Juan García');
      expect(res.body.email).toBe('juan@example.com');
      collectorId = res.body.id;
    });

    test('POST /api/collectors requires name and email', async () => {
      const res = await request(app)
        .post('/api/collectors')
        .send({ name: 'Test' });
      expect(res.statusCode).toBe(400);
    });

    test('GET /api/collectors returns all collectors', async () => {
      const res = await request(app).get('/api/collectors');
      expect(res.statusCode).toBe(200);
      expect(Array.isArray(res.body)).toBe(true);
      expect(res.body.length).toBeGreaterThan(0);
    });

    test('GET /api/collectors/:id returns a collector', async () => {
      const res = await request(app).get(`/api/collectors/${collectorId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.id).toBe(collectorId);
    });

    test('PUT /api/collectors/:id updates a collector', async () => {
      const res = await request(app)
        .put(`/api/collectors/${collectorId}`)
        .send({
          name: 'Juan García Updated',
          email: 'juan.updated@example.com',
          phone: '+34 612 345 678',
          address: 'Barcelona, Spain'
        });
      expect(res.statusCode).toBe(200);
      expect(res.body.name).toBe('Juan García Updated');
    });

    test('DELETE /api/collectors/:id deletes a collector', async () => {
      const res = await request(app).delete(`/api/collectors/${collectorId}`);
      expect(res.statusCode).toBe(204);
    });

    test('GET /api/collectors/:id returns 404 for deleted collector', async () => {
      const res = await request(app).get(`/api/collectors/${collectorId}`);
      expect(res.statusCode).toBe(404);
    });
  });

  describe('Sommeliers API', () => {
    let sommelierId;

    test('POST /api/sommeliers creates a new sommelier', async () => {
      const res = await request(app)
        .post('/api/sommeliers')
        .send({
          name: 'María López',
          email: 'maria@example.com',
          certification_level: 'Level 3',
          certification_date: '2020-05-15',
          specialization: 'Italian Wines'
        });
      expect(res.statusCode).toBe(201);
      expect(res.body.name).toBe('María López');
      expect(res.body.certification_level).toBe('Level 3');
      sommelierId = res.body.id;
    });

    test('POST /api/sommeliers requires certification level', async () => {
      const res = await request(app)
        .post('/api/sommeliers')
        .send({ name: 'Test', email: 'test@example.com' });
      expect(res.statusCode).toBe(400);
    });

    test('GET /api/sommeliers returns all sommeliers', async () => {
      const res = await request(app).get('/api/sommeliers');
      expect(res.statusCode).toBe(200);
      expect(Array.isArray(res.body)).toBe(true);
    });

    test('GET /api/sommeliers/:id returns a sommelier', async () => {
      const res = await request(app).get(`/api/sommeliers/${sommelierId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.id).toBe(sommelierId);
    });
  });

  describe('Bottles API', () => {
    let bottleId;

    test('POST /api/bottles creates a new bottle', async () => {
      const res = await request(app)
        .post('/api/bottles')
        .send({
          name: 'Barolo Riserva',
          producer: 'Cantina Giacomo',
          vintage: 2015,
          denomination: 'DOCG',
          region: 'Piedmont',
          grape_variety: 'Nebbiolo',
          alcohol_content: 14.5,
          quantity: 6
        });
      expect(res.statusCode).toBe(201);
      expect(res.body.name).toBe('Barolo Riserva');
      expect(res.body.denomination).toBe('DOCG');
      bottleId = res.body.id;
    });

    test('POST /api/bottles validates denomination', async () => {
      const res = await request(app)
        .post('/api/bottles')
        .send({
          name: 'Test Wine',
          producer: 'Test Producer',
          denomination: 'INVALID'
        });
      expect(res.statusCode).toBe(400);
    });

    test('GET /api/bottles returns all bottles', async () => {
      const res = await request(app).get('/api/bottles');
      expect(res.statusCode).toBe(200);
      expect(Array.isArray(res.body)).toBe(true);
    });

    test('GET /api/bottles?denomination=DOCG filters by denomination', async () => {
      const res = await request(app).get('/api/bottles?denomination=DOCG');
      expect(res.statusCode).toBe(200);
      expect(res.body.every(b => b.denomination === 'DOCG')).toBe(true);
    });

    test('GET /api/bottles/:id returns a bottle', async () => {
      const res = await request(app).get(`/api/bottles/${bottleId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.id).toBe(bottleId);
    });

    // Create more bottles for testing
    test('POST /api/bottles creates DOC bottle', async () => {
      const res = await request(app)
        .post('/api/bottles')
        .send({
          name: 'Chianti Classico',
          producer: 'Fattoria di Felsina',
          vintage: 2018,
          denomination: 'DOC',
          region: 'Tuscany'
        });
      expect(res.statusCode).toBe(201);
    });

    test('POST /api/bottles creates organic bottle', async () => {
      const res = await request(app)
        .post('/api/bottles')
        .send({
          name: 'Organic Primitivo',
          producer: 'Azienda Bio',
          vintage: 2020,
          denomination: 'organic',
          region: 'Puglia'
        });
      expect(res.statusCode).toBe(201);
    });
  });

  describe('Aging Records API', () => {
    let agingId;
    let bottleId;

    beforeAll(async () => {
      // Get a bottle ID
      const res = await request(app).get('/api/bottles');
      bottleId = res.body[0].id;
    });

    test('POST /api/aging creates a new aging record', async () => {
      const res = await request(app)
        .post('/api/aging')
        .send({
          bottle_id: bottleId,
          start_date: '2020-01-01',
          storage_location: 'Cellar A - Rack 3',
          temperature: 14.5,
          humidity: 70,
          notes: 'Optimal conditions maintained'
        });
      expect(res.statusCode).toBe(201);
      expect(res.body.bottle_id).toBe(bottleId);
      agingId = res.body.id;
    });

    test('POST /api/aging requires bottle_id and start_date', async () => {
      const res = await request(app)
        .post('/api/aging')
        .send({ storage_location: 'Test' });
      expect(res.statusCode).toBe(400);
    });

    test('GET /api/aging returns all aging records', async () => {
      const res = await request(app).get('/api/aging');
      expect(res.statusCode).toBe(200);
      expect(Array.isArray(res.body)).toBe(true);
    });

    test('GET /api/aging/:id returns an aging record', async () => {
      const res = await request(app).get(`/api/aging/${agingId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.id).toBe(agingId);
    });

    test('PUT /api/aging/:id updates an aging record', async () => {
      const res = await request(app)
        .put(`/api/aging/${agingId}`)
        .send({
          bottle_id: bottleId,
          start_date: '2020-01-01',
          end_date: '2024-01-01',
          storage_location: 'Cellar A - Rack 3',
          temperature: 14.0,
          humidity: 72,
          notes: 'Completed aging'
        });
      expect(res.statusCode).toBe(200);
      expect(res.body.end_date).toBe('2024-01-01');
    });
  });

  describe('Tastings API', () => {
    let tastingId;
    let bottleId;
    let sommelierId;

    beforeAll(async () => {
      // Get bottle and sommelier IDs
      const bottles = await request(app).get('/api/bottles');
      bottleId = bottles.body[0].id;
      const sommeliers = await request(app).get('/api/sommeliers');
      sommelierId = sommeliers.body[0].id;
    });

    test('POST /api/tastings creates a new tasting', async () => {
      const res = await request(app)
        .post('/api/tastings')
        .send({
          bottle_id: bottleId,
          sommelier_id: sommelierId,
          tasting_date: '2024-01-15',
          appearance: 'Deep ruby with garnet rim',
          aroma: 'Complex nose with notes of cherry, tar, and roses',
          taste: 'Full-bodied with firm tannins, excellent structure',
          finish: 'Long and persistent',
          overall_rating: 92,
          notes: 'Exceptional vintage, ready to drink but will improve'
        });
      expect(res.statusCode).toBe(201);
      expect(res.body.overall_rating).toBe(92);
      tastingId = res.body.id;
    });

    test('POST /api/tastings validates rating range', async () => {
      const res = await request(app)
        .post('/api/tastings')
        .send({
          bottle_id: bottleId,
          tasting_date: '2024-01-15',
          overall_rating: 150 // Invalid
        });
      expect(res.statusCode).toBe(400);
    });

    test('GET /api/tastings returns all tastings', async () => {
      const res = await request(app).get('/api/tastings');
      expect(res.statusCode).toBe(200);
      expect(Array.isArray(res.body)).toBe(true);
    });

    test('GET /api/tastings/:id returns a tasting', async () => {
      const res = await request(app).get(`/api/tastings/${tastingId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.id).toBe(tastingId);
    });

    test('GET /api/tastings?sommelier_id=X filters by sommelier', async () => {
      const res = await request(app).get(`/api/tastings?sommelier_id=${sommelierId}`);
      expect(res.statusCode).toBe(200);
      expect(res.body.every(t => t.sommelier_id === sommelierId)).toBe(true);
    });
  });
});

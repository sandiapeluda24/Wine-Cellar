const db = require('../db');

const AgingRecord = {
  create(data) {
    const stmt = db.prepare(`
      INSERT INTO aging_records (bottle_id, start_date, end_date, storage_location, temperature, humidity, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `);
    const result = stmt.run(
      data.bottle_id,
      data.start_date,
      data.end_date,
      data.storage_location,
      data.temperature,
      data.humidity,
      data.notes
    );
    return this.findById(result.lastInsertRowid);
  },

  findAll() {
    return db.prepare(`
      SELECT ar.*, b.name as bottle_name 
      FROM aging_records ar 
      LEFT JOIN bottles b ON ar.bottle_id = b.id 
      ORDER BY ar.start_date DESC
    `).all();
  },

  findById(id) {
    return db.prepare(`
      SELECT ar.*, b.name as bottle_name 
      FROM aging_records ar 
      LEFT JOIN bottles b ON ar.bottle_id = b.id 
      WHERE ar.id = ?
    `).get(id);
  },

  findByBottle(bottleId) {
    return db.prepare('SELECT * FROM aging_records WHERE bottle_id = ? ORDER BY start_date DESC').all(bottleId);
  },

  update(id, data) {
    const stmt = db.prepare(`
      UPDATE aging_records 
      SET bottle_id = ?, start_date = ?, end_date = ?, storage_location = ?, temperature = ?, humidity = ?, notes = ?
      WHERE id = ?
    `);
    stmt.run(
      data.bottle_id,
      data.start_date,
      data.end_date,
      data.storage_location,
      data.temperature,
      data.humidity,
      data.notes,
      id
    );
    return this.findById(id);
  },

  delete(id) {
    const stmt = db.prepare('DELETE FROM aging_records WHERE id = ?');
    return stmt.run(id);
  }
};

module.exports = AgingRecord;

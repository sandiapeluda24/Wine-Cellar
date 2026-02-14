const db = require('../db');

const Collector = {
  create(data) {
    const stmt = db.prepare(`
      INSERT INTO collectors (name, email, phone, address)
      VALUES (?, ?, ?, ?)
    `);
    const result = stmt.run(data.name, data.email, data.phone, data.address);
    return this.findById(result.lastInsertRowid);
  },

  findAll() {
    return db.prepare('SELECT * FROM collectors ORDER BY name').all();
  },

  findById(id) {
    return db.prepare('SELECT * FROM collectors WHERE id = ?').get(id);
  },

  update(id, data) {
    const stmt = db.prepare(`
      UPDATE collectors 
      SET name = ?, email = ?, phone = ?, address = ?
      WHERE id = ?
    `);
    stmt.run(data.name, data.email, data.phone, data.address, id);
    return this.findById(id);
  },

  delete(id) {
    const stmt = db.prepare('DELETE FROM collectors WHERE id = ?');
    return stmt.run(id);
  }
};

module.exports = Collector;

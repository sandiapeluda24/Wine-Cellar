const db = require('../db');

const Sommelier = {
  create(data) {
    const stmt = db.prepare(`
      INSERT INTO sommeliers (name, email, certification_level, certification_date, specialization)
      VALUES (?, ?, ?, ?, ?)
    `);
    const result = stmt.run(
      data.name,
      data.email,
      data.certification_level,
      data.certification_date,
      data.specialization
    );
    return this.findById(result.lastInsertRowid);
  },

  findAll() {
    return db.prepare('SELECT * FROM sommeliers ORDER BY name').all();
  },

  findById(id) {
    return db.prepare('SELECT * FROM sommeliers WHERE id = ?').get(id);
  },

  update(id, data) {
    const stmt = db.prepare(`
      UPDATE sommeliers 
      SET name = ?, email = ?, certification_level = ?, certification_date = ?, specialization = ?
      WHERE id = ?
    `);
    stmt.run(
      data.name,
      data.email,
      data.certification_level,
      data.certification_date,
      data.specialization,
      id
    );
    return this.findById(id);
  },

  delete(id) {
    const stmt = db.prepare('DELETE FROM sommeliers WHERE id = ?');
    return stmt.run(id);
  }
};

module.exports = Sommelier;

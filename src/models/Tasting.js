const db = require('../db');

const Tasting = {
  create(data) {
    const stmt = db.prepare(`
      INSERT INTO tastings (bottle_id, sommelier_id, tasting_date, appearance, aroma, taste, finish, overall_rating, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);
    const result = stmt.run(
      data.bottle_id,
      data.sommelier_id,
      data.tasting_date,
      data.appearance,
      data.aroma,
      data.taste,
      data.finish,
      data.overall_rating,
      data.notes
    );
    return this.findById(result.lastInsertRowid);
  },

  findAll() {
    return db.prepare(`
      SELECT t.*, b.name as bottle_name, s.name as sommelier_name 
      FROM tastings t 
      LEFT JOIN bottles b ON t.bottle_id = b.id 
      LEFT JOIN sommeliers s ON t.sommelier_id = s.id 
      ORDER BY t.tasting_date DESC
    `).all();
  },

  findById(id) {
    return db.prepare(`
      SELECT t.*, b.name as bottle_name, s.name as sommelier_name 
      FROM tastings t 
      LEFT JOIN bottles b ON t.bottle_id = b.id 
      LEFT JOIN sommeliers s ON t.sommelier_id = s.id 
      WHERE t.id = ?
    `).get(id);
  },

  findByBottle(bottleId) {
    return db.prepare(`
      SELECT t.*, s.name as sommelier_name 
      FROM tastings t 
      LEFT JOIN sommeliers s ON t.sommelier_id = s.id 
      WHERE t.bottle_id = ? 
      ORDER BY t.tasting_date DESC
    `).all(bottleId);
  },

  findBySommelier(sommelierId) {
    return db.prepare(`
      SELECT t.*, b.name as bottle_name 
      FROM tastings t 
      LEFT JOIN bottles b ON t.bottle_id = b.id 
      WHERE t.sommelier_id = ? 
      ORDER BY t.tasting_date DESC
    `).all(sommelierId);
  },

  update(id, data) {
    const stmt = db.prepare(`
      UPDATE tastings 
      SET bottle_id = ?, sommelier_id = ?, tasting_date = ?, appearance = ?, aroma = ?, taste = ?, finish = ?, overall_rating = ?, notes = ?
      WHERE id = ?
    `);
    stmt.run(
      data.bottle_id,
      data.sommelier_id,
      data.tasting_date,
      data.appearance,
      data.aroma,
      data.taste,
      data.finish,
      data.overall_rating,
      data.notes,
      id
    );
    return this.findById(id);
  },

  delete(id) {
    const stmt = db.prepare('DELETE FROM tastings WHERE id = ?');
    return stmt.run(id);
  }
};

module.exports = Tasting;

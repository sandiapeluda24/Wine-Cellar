const db = require('../db');

const Bottle = {
  create(data) {
    const stmt = db.prepare(`
      INSERT INTO bottles (name, producer, vintage, denomination, region, grape_variety, 
        alcohol_content, bottle_size, quantity, purchase_price, purchase_date, collector_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);
    const result = stmt.run(
      data.name,
      data.producer,
      data.vintage,
      data.denomination,
      data.region,
      data.grape_variety,
      data.alcohol_content,
      data.bottle_size,
      data.quantity,
      data.purchase_price,
      data.purchase_date,
      data.collector_id
    );
    return this.findById(result.lastInsertRowid);
  },

  findAll() {
    return db.prepare('SELECT * FROM bottles ORDER BY name').all();
  },

  findById(id) {
    return db.prepare('SELECT * FROM bottles WHERE id = ?').get(id);
  },

  findByDenomination(denomination) {
    return db.prepare('SELECT * FROM bottles WHERE denomination = ? ORDER BY name').all(denomination);
  },

  findByCollector(collectorId) {
    return db.prepare('SELECT * FROM bottles WHERE collector_id = ? ORDER BY name').all(collectorId);
  },

  update(id, data) {
    const stmt = db.prepare(`
      UPDATE bottles 
      SET name = ?, producer = ?, vintage = ?, denomination = ?, region = ?, grape_variety = ?,
        alcohol_content = ?, bottle_size = ?, quantity = ?, purchase_price = ?, purchase_date = ?, collector_id = ?
      WHERE id = ?
    `);
    stmt.run(
      data.name,
      data.producer,
      data.vintage,
      data.denomination,
      data.region,
      data.grape_variety,
      data.alcohol_content,
      data.bottle_size,
      data.quantity,
      data.purchase_price,
      data.purchase_date,
      data.collector_id,
      id
    );
    return this.findById(id);
  },

  delete(id) {
    const stmt = db.prepare('DELETE FROM bottles WHERE id = ?');
    return stmt.run(id);
  }
};

module.exports = Bottle;

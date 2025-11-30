const Database = require('better-sqlite3');
const path = require('path');

const dbPath = process.env.DB_PATH || path.join(__dirname, '..', 'wine_cellar.db');
const db = new Database(dbPath);

// Initialize database tables
db.exec(`
  CREATE TABLE IF NOT EXISTS collectors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    phone TEXT,
    address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS sommeliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    certification_level TEXT NOT NULL,
    certification_date DATE,
    specialization TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS bottles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    producer TEXT NOT NULL,
    vintage INTEGER,
    denomination TEXT NOT NULL CHECK (denomination IN ('DOCG', 'DOC', 'IGT', 'organic')),
    region TEXT,
    grape_variety TEXT,
    alcohol_content REAL,
    bottle_size TEXT DEFAULT '750ml',
    quantity INTEGER DEFAULT 1,
    purchase_price REAL,
    purchase_date DATE,
    collector_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collector_id) REFERENCES collectors(id)
  );

  CREATE TABLE IF NOT EXISTS aging_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bottle_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    storage_location TEXT,
    temperature REAL,
    humidity REAL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bottle_id) REFERENCES bottles(id)
  );

  CREATE TABLE IF NOT EXISTS tastings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bottle_id INTEGER NOT NULL,
    sommelier_id INTEGER,
    tasting_date DATE NOT NULL,
    appearance TEXT,
    aroma TEXT,
    taste TEXT,
    finish TEXT,
    overall_rating INTEGER CHECK (overall_rating >= 1 AND overall_rating <= 100),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bottle_id) REFERENCES bottles(id),
    FOREIGN KEY (sommelier_id) REFERENCES sommeliers(id)
  );
`);

module.exports = db;

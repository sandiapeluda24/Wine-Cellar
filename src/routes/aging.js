const express = require('express');
const router = express.Router();
const AgingRecord = require('../models/AgingRecord');
const Bottle = require('../models/Bottle');

// Get all aging records
router.get('/', (req, res) => {
  try {
    const { bottle_id } = req.query;
    let records;
    if (bottle_id) {
      records = AgingRecord.findByBottle(bottle_id);
    } else {
      records = AgingRecord.findAll();
    }
    res.json(records);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Get aging record by ID
router.get('/:id', (req, res) => {
  try {
    const record = AgingRecord.findById(req.params.id);
    if (!record) {
      return res.status(404).json({ error: 'Aging record not found' });
    }
    res.json(record);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Create new aging record
router.post('/', (req, res) => {
  try {
    const { bottle_id, start_date, end_date, storage_location, temperature, humidity, notes } = req.body;
    
    if (!bottle_id || !start_date) {
      return res.status(400).json({ error: 'Bottle ID and start date are required' });
    }
    
    // Verify bottle exists
    const bottle = Bottle.findById(bottle_id);
    if (!bottle) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    
    const record = AgingRecord.create({
      bottle_id, start_date, end_date, storage_location, temperature, humidity, notes
    });
    res.status(201).json(record);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Update aging record
router.put('/:id', (req, res) => {
  try {
    const existing = AgingRecord.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Aging record not found' });
    }
    
    const { bottle_id, start_date, end_date, storage_location, temperature, humidity, notes } = req.body;
    
    if (!bottle_id || !start_date) {
      return res.status(400).json({ error: 'Bottle ID and start date are required' });
    }
    
    const record = AgingRecord.update(req.params.id, {
      bottle_id, start_date, end_date, storage_location, temperature, humidity, notes
    });
    res.json(record);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Delete aging record
router.delete('/:id', (req, res) => {
  try {
    const existing = AgingRecord.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Aging record not found' });
    }
    AgingRecord.delete(req.params.id);
    res.status(204).send();
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

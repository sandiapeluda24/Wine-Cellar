const express = require('express');
const router = express.Router();
const Collector = require('../models/Collector');

// Get all collectors
router.get('/', (req, res) => {
  try {
    const collectors = Collector.findAll();
    res.json(collectors);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Get collector by ID
router.get('/:id', (req, res) => {
  try {
    const collector = Collector.findById(req.params.id);
    if (!collector) {
      return res.status(404).json({ error: 'Collector not found' });
    }
    res.json(collector);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Create new collector
router.post('/', (req, res) => {
  try {
    const { name, email, phone, address } = req.body;
    if (!name || !email) {
      return res.status(400).json({ error: 'Name and email are required' });
    }
    const collector = Collector.create({ name, email, phone, address });
    res.status(201).json(collector);
  } catch (error) {
    if (error.message.includes('UNIQUE constraint failed')) {
      return res.status(400).json({ error: 'Email already exists' });
    }
    res.status(500).json({ error: error.message });
  }
});

// Update collector
router.put('/:id', (req, res) => {
  try {
    const existing = Collector.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Collector not found' });
    }
    const { name, email, phone, address } = req.body;
    if (!name || !email) {
      return res.status(400).json({ error: 'Name and email are required' });
    }
    const collector = Collector.update(req.params.id, { name, email, phone, address });
    res.json(collector);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Delete collector
router.delete('/:id', (req, res) => {
  try {
    const existing = Collector.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Collector not found' });
    }
    Collector.delete(req.params.id);
    res.status(204).send();
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

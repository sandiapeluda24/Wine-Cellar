const express = require('express');
const router = express.Router();
const Sommelier = require('../models/Sommelier');

// Get all sommeliers
router.get('/', (req, res) => {
  try {
    const sommeliers = Sommelier.findAll();
    res.json(sommeliers);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Get sommelier by ID
router.get('/:id', (req, res) => {
  try {
    const sommelier = Sommelier.findById(req.params.id);
    if (!sommelier) {
      return res.status(404).json({ error: 'Sommelier not found' });
    }
    res.json(sommelier);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Create new sommelier
router.post('/', (req, res) => {
  try {
    const { name, email, certification_level, certification_date, specialization } = req.body;
    if (!name || !email || !certification_level) {
      return res.status(400).json({ error: 'Name, email and certification level are required' });
    }
    const sommelier = Sommelier.create({ name, email, certification_level, certification_date, specialization });
    res.status(201).json(sommelier);
  } catch (error) {
    if (error.message.includes('UNIQUE constraint failed')) {
      return res.status(400).json({ error: 'Email already exists' });
    }
    res.status(500).json({ error: error.message });
  }
});

// Update sommelier
router.put('/:id', (req, res) => {
  try {
    const existing = Sommelier.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Sommelier not found' });
    }
    const { name, email, certification_level, certification_date, specialization } = req.body;
    if (!name || !email || !certification_level) {
      return res.status(400).json({ error: 'Name, email and certification level are required' });
    }
    const sommelier = Sommelier.update(req.params.id, { name, email, certification_level, certification_date, specialization });
    res.json(sommelier);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Delete sommelier
router.delete('/:id', (req, res) => {
  try {
    const existing = Sommelier.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Sommelier not found' });
    }
    Sommelier.delete(req.params.id);
    res.status(204).send();
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

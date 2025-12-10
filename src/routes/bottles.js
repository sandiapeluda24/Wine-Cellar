const express = require('express');
const router = express.Router();
const Bottle = require('../models/Bottle');

const VALID_DENOMINATIONS = ['DOCG', 'DOC', 'IGT', 'organic'];

// Get all bottles
router.get('/', (req, res) => {
  try {
    const { denomination, collector_id } = req.query;
    let bottles;
    if (denomination) {
      if (!VALID_DENOMINATIONS.includes(denomination)) {
        return res.status(400).json({ error: 'Invalid denomination. Must be one of: DOCG, DOC, IGT, organic' });
      }
      bottles = Bottle.findByDenomination(denomination);
    } else if (collector_id) {
      bottles = Bottle.findByCollector(collector_id);
    } else {
      bottles = Bottle.findAll();
    }
    res.json(bottles);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Get bottle by ID
router.get('/:id', (req, res) => {
  try {
    const bottle = Bottle.findById(req.params.id);
    if (!bottle) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    res.json(bottle);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Create new bottle
router.post('/', (req, res) => {
  try {
    const { name, producer, vintage, denomination, region, grape_variety, 
      alcohol_content, bottle_size, quantity, purchase_price, purchase_date, collector_id } = req.body;
    
    if (!name || !producer || !denomination) {
      return res.status(400).json({ error: 'Name, producer and denomination are required' });
    }
    
    if (!VALID_DENOMINATIONS.includes(denomination)) {
      return res.status(400).json({ error: 'Invalid denomination. Must be one of: DOCG, DOC, IGT, organic' });
    }
    
    const bottle = Bottle.create({
      name, producer, vintage, denomination, region, grape_variety,
      alcohol_content, bottle_size, quantity, purchase_price, purchase_date, collector_id
    });
    res.status(201).json(bottle);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Update bottle
router.put('/:id', (req, res) => {
  try {
    const existing = Bottle.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    
    const { name, producer, vintage, denomination, region, grape_variety,
      alcohol_content, bottle_size, quantity, purchase_price, purchase_date, collector_id } = req.body;
    
    if (!name || !producer || !denomination) {
      return res.status(400).json({ error: 'Name, producer and denomination are required' });
    }
    
    if (!VALID_DENOMINATIONS.includes(denomination)) {
      return res.status(400).json({ error: 'Invalid denomination. Must be one of: DOCG, DOC, IGT, organic' });
    }
    
    const bottle = Bottle.update(req.params.id, {
      name, producer, vintage, denomination, region, grape_variety,
      alcohol_content, bottle_size, quantity, purchase_price, purchase_date, collector_id
    });
    res.json(bottle);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Delete bottle
router.delete('/:id', (req, res) => {
  try {
    const existing = Bottle.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    Bottle.delete(req.params.id);
    res.status(204).send();
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

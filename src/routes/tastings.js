const express = require('express');
const router = express.Router();
const Tasting = require('../models/Tasting');
const Bottle = require('../models/Bottle');
const Sommelier = require('../models/Sommelier');

// Get all tastings
router.get('/', (req, res) => {
  try {
    const { bottle_id, sommelier_id } = req.query;
    let tastings;
    if (bottle_id) {
      tastings = Tasting.findByBottle(bottle_id);
    } else if (sommelier_id) {
      tastings = Tasting.findBySommelier(sommelier_id);
    } else {
      tastings = Tasting.findAll();
    }
    res.json(tastings);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Get tasting by ID
router.get('/:id', (req, res) => {
  try {
    const tasting = Tasting.findById(req.params.id);
    if (!tasting) {
      return res.status(404).json({ error: 'Tasting not found' });
    }
    res.json(tasting);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Create new tasting
router.post('/', (req, res) => {
  try {
    const { bottle_id, sommelier_id, tasting_date, appearance, aroma, taste, finish, overall_rating, notes } = req.body;
    
    if (!bottle_id || !tasting_date) {
      return res.status(400).json({ error: 'Bottle ID and tasting date are required' });
    }
    
    // Verify bottle exists
    const bottle = Bottle.findById(bottle_id);
    if (!bottle) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    
    // Verify sommelier exists if provided
    if (sommelier_id) {
      const sommelier = Sommelier.findById(sommelier_id);
      if (!sommelier) {
        return res.status(404).json({ error: 'Sommelier not found' });
      }
    }
    
    // Validate rating
    if (overall_rating !== undefined && (overall_rating < 1 || overall_rating > 100)) {
      return res.status(400).json({ error: 'Overall rating must be between 1 and 100' });
    }
    
    const tasting = Tasting.create({
      bottle_id, sommelier_id, tasting_date, appearance, aroma, taste, finish, overall_rating, notes
    });
    res.status(201).json(tasting);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Update tasting
router.put('/:id', (req, res) => {
  try {
    const existing = Tasting.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Tasting not found' });
    }
    
    const { bottle_id, sommelier_id, tasting_date, appearance, aroma, taste, finish, overall_rating, notes } = req.body;
    
    if (!bottle_id || !tasting_date) {
      return res.status(400).json({ error: 'Bottle ID and tasting date are required' });
    }
    
    // Verify bottle exists
    const bottle = Bottle.findById(bottle_id);
    if (!bottle) {
      return res.status(404).json({ error: 'Bottle not found' });
    }
    
    // Verify sommelier exists if provided
    if (sommelier_id) {
      const sommelier = Sommelier.findById(sommelier_id);
      if (!sommelier) {
        return res.status(404).json({ error: 'Sommelier not found' });
      }
    }
    
    // Validate rating
    if (overall_rating !== undefined && (overall_rating < 1 || overall_rating > 100)) {
      return res.status(400).json({ error: 'Overall rating must be between 1 and 100' });
    }
    
    const tasting = Tasting.update(req.params.id, {
      bottle_id, sommelier_id, tasting_date, appearance, aroma, taste, finish, overall_rating, notes
    });
    res.json(tasting);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Delete tasting
router.delete('/:id', (req, res) => {
  try {
    const existing = Tasting.findById(req.params.id);
    if (!existing) {
      return res.status(404).json({ error: 'Tasting not found' });
    }
    Tasting.delete(req.params.id);
    res.status(204).send();
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

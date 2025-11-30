const express = require('express');
const cors = require('cors');
const path = require('path');

const collectorsRouter = require('./routes/collectors');
const sommeliersRouter = require('./routes/sommeliers');
const bottlesRouter = require('./routes/bottles');
const agingRouter = require('./routes/aging');
const tastingsRouter = require('./routes/tastings');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '..', 'public')));

// API Routes
app.use('/api/collectors', collectorsRouter);
app.use('/api/sommeliers', sommeliersRouter);
app.use('/api/bottles', bottlesRouter);
app.use('/api/aging', agingRouter);
app.use('/api/tastings', tastingsRouter);

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', message: 'Wine Cellar API is running' });
});

// Serve the main HTML file for any non-API routes
app.get('/{*path}', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});

module.exports = app;

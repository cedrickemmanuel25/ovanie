// stats.routes.js//
const express = require('express');
const router = express.Router();

const {
  salesByCategory,
  monthlyTraffic
} = require('../controllers/stats.controller');

const { requireAuth } = require('../middlewares/auth.middleware');
const { requireAdmin } = require('../middlewares/admin.middleware');

router.get('/sales-by-category', requireAuth, requireAdmin, salesByCategory);
router.get('/monthly-traffic', requireAuth, requireAdmin, monthlyTraffic);

module.exports = router;

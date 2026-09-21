const Order = require('../models/Order');
const User = require('../models/User');

/* ===============================
   VENTES PAR CATÉGORIE
================================ */
exports.salesByCategory = async (req, res) => {
  try {
    const result = await Order.aggregate([
      { $unwind: '$items' },
      {
        $group: {
          _id: '$items.category',
          total: { $sum: '$items.quantity' }
        }
      }
    ]);

    res.json({
      labels: result.map(r => r._id),
      values: result.map(r => r.total)
    });

  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Erreur stats catégories' });
  }
};

/* ===============================
   TRAFIC MENSUEL
================================ */
exports.monthlyTraffic = async (req, res) => {
  try {
    const result = await User.aggregate([
      {
        $group: {
          _id: { $month: '$createdAt' },
          count: { $sum: 1 }
        }
      },
      { $sort: { '_id': 1 } }
    ]);

    const months = [
      'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin',
      'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'
    ];

    res.json({
      labels: result.map(r => months[r._id - 1]),
      values: result.map(r => r.count)
    });

  } catch (err) {
    res.status(500).json({ error: 'Erreur trafic' });
  }
};

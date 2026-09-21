// =======================================
// APP PRINCIPAL – EXPRESS SERVER
// =======================================

const express = require('express');
const path = require('path');
const cors = require('cors');
const session = require('express-session');
const MongoStore = require('connect-mongo');
require('dotenv').config();

// Connexion DB
const connectDB = require('./config/db');

// Routes
const authRoutes = require('./routes/auth.routes');
const adminRoutes = require('./routes/admin.routes');
const productRoutes = require('./routes/product.routes');
const categoryRoutes = require('./routes/category.routes');
const statsRoutes = require('./routes/stats.routes');

// =======================================
// INITIALISATION
// =======================================

const app = express();
connectDB();

// =======================================
// MIDDLEWARES GLOBAUX
// =======================================

// CORS (frontend séparé ou même domaine)
app.use(cors({
  origin: true,
  credentials: true
}));

// Body parsers
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Sessions (AUTH PRINCIPALE)
app.use(session({
  name: 'marketplace.sid',
  secret: process.env.SESSION_SECRET || 'secret_super_secure',
  resave: false,
  saveUninitialized: false,
  store: MongoStore.create({
    mongoUrl: process.env.MONGO_URI,
    collectionName: 'sessions'
  }),
  cookie: {
    httpOnly: true,
    secure: false,           // true en HTTPS
    maxAge: 1000 * 60 * 60 * 24 // 1 jour
  }
}));

// Fichiers uploadés
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// Frontend statique (admin, public, user)
app.use(express.static(path.join(__dirname, '../frontend')));

// =======================================
// ROUTES API
// =======================================

app.use('/api/auth', authRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api/products', productRoutes);
app.use('/api/categories', categoryRoutes);
app.use('/api/stats', statsRoutes);

// =======================================
// ROUTE TEST
// =======================================

app.get('/api/health', (req, res) => {
  res.json({
    status: 'OK',
    uptime: process.uptime(),
    timestamp: new Date()
  });
});

// =======================================
// GESTION ERREURS
// =======================================

app.use((err, req, res, next) => {
  console.error('❌ Erreur serveur:', err);
  res.status(500).json({ error: 'Erreur serveur interne' });
});

// =======================================
// EXPORT
// =======================================

module.exports = app;

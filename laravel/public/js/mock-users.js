// Stocker tous les utilisateurs
localStorage.setItem('users', JSON.stringify([
  {
    id: "USER-001",
    fullname: "Paul Yao",
    email: "paul@example.com",
    phone: "+225070000000",
    address: "123 Rue des Bâtisseurs, Abidjan",
    orders: [
      {
        id: "CMD-001",
        date: "2026-01-05",
        status: "en-attente",
        total: 120000,
        items: [
          { name: "Ciment 50kg", qty: 10, price: 12000 }
        ]
      }
    ],
    wishlist: [
      { id: "PROD-001", name: "Sac de ciment", category: "Matériaux Gros œuvre", price: 5000, image: "IMAGES/materiaux-de-construction.jpg" }
    ],
    messages: [
      { id: "MSG-001", sender: "Support IMOo", subject: "Votre commande #12345 a été expédiée", preview: "Votre commande #12345 a été expédiée...", content: "Votre commande a été expédiée...", date: "2026-01-02", read: false }
    ]
  }
]));

// Définir l’utilisateur connecté
localStorage.setItem('currentUser', JSON.stringify({ id: "USER-001" }));

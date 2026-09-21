

//order_detail//

if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}


function goShipment() {
  // Redirection vers la page d'expédition
  window.location.href = "shipment.html";
}

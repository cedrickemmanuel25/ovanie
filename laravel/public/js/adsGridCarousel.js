// carousel.js - Version dynamique et fade
if (window.IMOO.isAuthenticated) {
  console.log("Utilisateur connecté :", window.IMOO.user);
}

document.addEventListener('DOMContentLoaded', () => {
  const adBoxes = document.querySelectorAll('.right-grid .ad-box img'); // toutes les images à faire défiler
  const delay = 4000;       // durée entre les changements (ms)
  const fadeDuration = 500; // durée du fade (ms)

  // Récupération dynamique des images via data-attributes ou tableau global
  // Exemple : chaque img peut avoir un attribut data-images="img1.jpg,img2.jpg,img3.jpg"
  const adImages = Array.from(adBoxes).map(img => {
    const data = img.dataset.images; 
    return data ? data.split(',') : [];
  });

  const currentIndexes = new Array(adBoxes.length).fill(0);

  function changeImage(boxIndex) {
    const img = adBoxes[boxIndex];
    const images = adImages[boxIndex];
    if (!images.length) return;

    // Fade out
    img.style.transition = `opacity ${fadeDuration}ms`;
    img.style.opacity = 0;

    setTimeout(() => {
      // Changer l'image
      currentIndexes[boxIndex] = (currentIndexes[boxIndex] + 1) % images.length;
      img.src = images[currentIndexes[boxIndex]];
      // Fade in
      img.style.opacity = 1;
    }, fadeDuration);
  }

  // Initialisation
  adBoxes.forEach(img => {
    img.style.opacity = 1;
  });

  // Boucle automatique pour chaque image
  adBoxes.forEach((img, index) => {
    if (!adImages[index].length) return;
    setInterval(() => changeImage(index), delay);
  });
});

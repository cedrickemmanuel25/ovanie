
if (window.IMOO.isAuthenticated) {
    console.log("Utilisateur connecté :", window.IMOO.user);
}

document.addEventListener('DOMContentLoaded', () => {

    // =========================
    // SLIDER ROTATIF ADS
    // =========================
    (function setupRotativeSlider() {
        const rotativeImages = document.querySelectorAll('.ads-rotative img');
        if (!rotativeImages || rotativeImages.length === 0) return;

        let currentIndex = 0;
        rotativeImages[currentIndex].classList.add('active');

        setInterval(() => {
            rotativeImages[currentIndex].classList.remove('active');
            currentIndex = (currentIndex + 1) % rotativeImages.length;
            rotativeImages[currentIndex].classList.add('active');
        }, 4000);
    })();


    document.querySelectorAll('.banner-link').forEach(el => {

        el.addEventListener('click', function () {

            fetch('/banner-click/' + this.dataset.id, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

        });

    });
    // =========================
    // SLIDER CAROUSEL
    // =========================
    (function setupCarouselSlider() {
        const images = document.querySelectorAll('.carousel img');
        if (!images || images.length === 0) return;

        let currentIndex = 0;
        images[currentIndex].classList.add('active');

        setInterval(() => {
            images[currentIndex].classList.remove('active');
            currentIndex = (currentIndex + 1) % images.length;
            images[currentIndex].classList.add('active');
        }, 4000);
    })();

});

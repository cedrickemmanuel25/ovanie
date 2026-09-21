(function () {
    'use strict';

    function setAmbientColor() {
        var home = document.querySelector('.ovanie-home--mockup[data-hero-image]');
        if (!home) return;

        var source = home.getAttribute('data-hero-image');
        if (!source) return;

        var image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = function () {
            var aspectRatio = image.naturalWidth / image.naturalHeight;
            if (aspectRatio > 0) {
                home.style.setProperty('--hero-aspect', String(aspectRatio));

                var updateAmbientStart = function () {
                    var hero = home.querySelector('.ovanie-hero');
                    if (hero) {
                        home.style.setProperty('--hero-ambient-start', Math.max(0, hero.offsetHeight - 24) + 'px');
                    }
                };

                updateAmbientStart();
                window.addEventListener('resize', updateAmbientStart, { passive: true });
            }

            var canvas = document.createElement('canvas');
            var size = 36;
            canvas.width = size;
            canvas.height = size;

            var context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) return;

            context.drawImage(image, 0, 0, size, size);

            try {
                var pixels = context.getImageData(0, Math.floor(size * .45), size, Math.ceil(size * .55)).data;
                var red = 0;
                var green = 0;
                var blue = 0;
                var weightTotal = 0;

                for (var index = 0; index < pixels.length; index += 4) {
                    var alpha = pixels[index + 3] / 255;
                    var brightness = (pixels[index] + pixels[index + 1] + pixels[index + 2]) / 3;
                    var weight = alpha * (.35 + brightness / 255);
                    red += pixels[index] * weight;
                    green += pixels[index + 1] * weight;
                    blue += pixels[index + 2] * weight;
                    weightTotal += weight;
                }

                if (!weightTotal) return;

                red = Math.round(red / weightTotal);
                green = Math.round(green / weightTotal);
                blue = Math.round(blue / weightTotal);

                home.style.setProperty('--hero-ambient-rgb', red + ', ' + green + ', ' + blue);
            } catch (error) {
                // La couleur de secours CSS reste active si le navigateur bloque l'analyse.
            }
        };
        image.src = source;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setAmbientColor, { once: true });
    } else {
        setAmbientColor();
    }
}());

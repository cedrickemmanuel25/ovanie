document.addEventListener("DOMContentLoaded", () => {
  const track = document.getElementById("homeTopAdsTrack");
  const dotsWrap = document.getElementById("homeTopAdsDots");

  if (!track) return;

  const slides = Array.from(track.querySelectorAll(".home-top-ad"));
  if (!slides.length) return;

  let current = 0;
  let timer = null;

  dotsWrap.innerHTML = slides.map((_, i) =>
    `<button type="button" aria-label="Aller à la publicité ${i + 1}" data-index="${i}"></button>`
  ).join("");

  const dots = Array.from(dotsWrap.querySelectorAll("button"));

  // ─── Color extraction ─────────────────────────────────────────────────────
  // Extracts the dominant color from the bottom strip of an image using canvas,
  // then sets it as the CSS variable --carousel-fade-color on :root.
  function extractBottomColor(img, callback) {
    try {
      const canvas = document.createElement("canvas");
      const SAMPLE_W = 80;
      const SAMPLE_H = 20;
      canvas.width  = SAMPLE_W;
      canvas.height = SAMPLE_H;
      const ctx = canvas.getContext("2d");

      // Draw only the bottom strip of the image
      ctx.drawImage(
        img,
        0, img.naturalHeight - Math.max(img.naturalHeight * 0.15, 1),
        img.naturalWidth, img.naturalHeight * 0.15,
        0, 0,
        SAMPLE_W, SAMPLE_H
      );

      const data = ctx.getImageData(0, 0, SAMPLE_W, SAMPLE_H).data;
      let r = 0, g = 0, b = 0, count = 0;
      for (let i = 0; i < data.length; i += 4) {
        r += data[i];
        g += data[i + 1];
        b += data[i + 2];
        count++;
      }
      if (count > 0) {
        r = Math.round(r / count);
        g = Math.round(g / count);
        b = Math.round(b / count);
        // Darken the extracted color a bit so it blends naturally
        r = Math.round(r * 0.7);
        g = Math.round(g * 0.7);
        b = Math.round(b * 0.7);
        callback(`rgb(${r},${g},${b})`);
      }
    } catch (e) {
      // Canvas tainted by cross-origin image – fall back to dark blue
      callback("rgb(5,7,10)");
    }
  }

  function applyFadeColor(color) {
    const fadeEl = document.getElementById("dynamic-carousel-fade");
    if (fadeEl) {
      fadeEl.style.background = `linear-gradient(to bottom, ${color} 0%, var(--ov-bg, #f4f5f8) 100%)`;
    }
    // Also update CSS variable for any other elements that use it
    document.documentElement.style.setProperty("--carousel-fade-color", color);
  }

  function updateFadeFromSlide(slide) {
    const img = slide.querySelector("img, .home-top-ad__image");
    if (!img) { applyFadeColor("rgb(5,7,10)"); return; }

    if (img.complete && img.naturalWidth > 0) {
      extractBottomColor(img, applyFadeColor);
    } else {
      img.addEventListener("load", () => extractBottomColor(img, applyFadeColor), { once: true });
      // Fallback while image loads
      applyFadeColor("rgb(5,7,10)");
    }
  }
  // ──────────────────────────────────────────────────────────────────────────

  function stopAllVideos() {
    slides.forEach(slide => {
      const video = slide.querySelector("video");
      if (video) {
        video.pause();
        video.currentTime = 0;
      }
    });
  }

  function clearTimer() {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  }

  function scheduleNext(duration) {
    clearTimer();
    timer = setTimeout(() => {
      goTo((current + 1) % slides.length);
    }, duration);
  }

  function goTo(index) {
    clearTimer();
    stopAllVideos();

    slides.forEach((slide, i) => {
      slide.classList.toggle("is-active", i === index);
    });

    dots.forEach((dot, i) => {
      dot.classList.toggle("active", i === index);
    });

    current = index;
    const activeSlide = slides[current];

    // Update the dynamic fade gradient
    updateFadeFromSlide(activeSlide);

    const type = activeSlide.dataset.type || "image";
    let duration = parseInt(activeSlide.dataset.duration || "6000", 10);
    if (type === "image" && duration < 6000) {
      duration = 6000;
    }

    if (type === "video") {
      const video = activeSlide.querySelector("video");
      if (video) {
        video.play().catch(() => {});
        const waitTime = Math.max(duration, 4000);

        if (video.readyState >= 1 && !isNaN(video.duration) && video.duration > 0) {
          scheduleNext(Math.min(video.duration * 1000 + 1200, 15000));
        } else {
          scheduleNext(waitTime);
        }

        video.onended = () => {
          goTo((current + 1) % slides.length);
        };
        return;
      }
    }

    scheduleNext(duration);
  }

  dots.forEach(dot => {
    dot.addEventListener("click", () => {
      goTo(parseInt(dot.dataset.index, 10));
    });
  });

  track.addEventListener("mouseenter", clearTimer);
  track.addEventListener("mouseleave", () => {
    const activeSlide = slides[current];
    let duration = parseInt(activeSlide.dataset.duration || "6000", 10);
    const type = activeSlide.dataset.type || "image";
    if (type === "image" && duration < 6000) duration = 6000;
    scheduleNext(duration);
  });

  goTo(0);
});
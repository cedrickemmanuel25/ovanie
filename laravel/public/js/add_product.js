document.addEventListener("DOMContentLoaded", () => {
  const steps = Array.from(document.querySelectorAll(".wizard-step"));
  const markers = Array.from(document.querySelectorAll(".wizard-step-marker"));
  let current = 0;

  function showStep(index) {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => step.classList.toggle("active", i === current));
    markers.forEach((marker, i) => marker.classList.toggle("active", i === current));
    if (window.lucide) window.lucide.createIcons();
  }

  document.querySelectorAll(".next-step").forEach((button) => {
    button.addEventListener("click", () => showStep(current + 1));
  });

  document.querySelectorAll(".prev-step").forEach((button) => {
    button.addEventListener("click", () => showStep(current - 1));
  });

  document.querySelectorAll(".image-input").forEach((input) => {
    input.addEventListener("change", () => {
      const file = input.files && input.files[0];
      const preview = input.closest(".image-upload-box")?.querySelector(".image-preview");
      if (!file || !preview) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        preview.style.backgroundImage = `url("${event.target.result}")`;
      };
      reader.readAsDataURL(file);
    });
  });

  const form = document.getElementById("addProductForm");
  form?.addEventListener("submit", (event) => {
    const firstImage = form.querySelector(".image-input");
    const price = Number(form.querySelector("input[name='price']")?.value || 0);
    const stock = Number(form.querySelector("input[name='stock']")?.value || -1);

    if (!firstImage?.files?.length) {
      event.preventDefault();
      showStep(0);
      alert("Veuillez ajouter au moins l'image principale.");
      return;
    }

    if (price <= 0 || stock < 0) {
      event.preventDefault();
      showStep(1);
      alert("Veuillez vérifier le prix et le stock.");
    }
  });
});

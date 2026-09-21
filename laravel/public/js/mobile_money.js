/* =====================================================
   MOBILE MONEY – LARAVEL / XAMPP
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {

  const form = document.getElementById("mobileMoneyForm");
  const resultEl = document.getElementById("result");

  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    resultEl.textContent = "";

    const operator = document.getElementById("operator").value;
    const number = document.getElementById("mobile_number").value.trim();
    const amount = document.getElementById("amount").value;

    /* ========= VALIDATION ========= */
    if (!operator) {
      alert("Veuillez choisir un opérateur.");
      return;
    }

    if (!/^\d{8,15}$/.test(number)) {
      alert("Numéro Mobile Money invalide.");
      return;
    }

    resultEl.textContent = "Paiement en cours...";

    try {
      const res = await fetch("/vendor/payments/mobile-money", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute("content")
        },
        credentials: "include",
        body: JSON.stringify({
          operator,
          number,
          amount
        })
      });

      const data = await res.json();

      if (!res.ok || !data.success) {
        throw new Error(data.message || "Paiement refusé");
      }

      resultEl.textContent =
        `Paiement de ${Number(amount).toLocaleString("fr-FR")} FCFA effectué avec succès.`;

      form.reset();

    } catch (err) {
      console.error(err);
      resultEl.textContent =
        "Erreur lors du paiement. Veuillez réessayer.";
    }
  });

});

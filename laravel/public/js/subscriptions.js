/* ===========================================
   ABONNEMENT VENDEUR – SERVER-FIRST (JWT)
   =========================================== */

document.addEventListener("DOMContentLoaded", () => {
  const token = localStorage.getItem("token");
  if (!token) {
    window.location.href = "/login.html";
    return;
  }

  const planButtons = document.querySelectorAll(".btn-subscribe");

  planButtons.forEach(btn => {
    btn.addEventListener("click", async () => {
      const plan = btn.dataset.plan; // ex: "mensuel" | "annuel"
      if (!plan) return;

      try {
        const res = await fetch("/api/vendor/subscribe", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`
          },
          body: JSON.stringify({ plan })
        });

        if (!res.ok) {
          const err = await res.json().catch(() => null);
          throw new Error(err?.error || "Erreur lors de l'abonnement");
        }

        await res.json();

        alert(`Abonnement ${plan} activé avec succès.`);
        window.location.href = "vendor_status.html";

      } catch (err) {
        console.error(err);
        alert("Abonnement impossible : " + err.message);
      }
    });
  });
});

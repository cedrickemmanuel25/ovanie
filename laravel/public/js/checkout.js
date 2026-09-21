document.addEventListener("DOMContentLoaded", () => {
    const checkoutForm = document.getElementById("checkoutForm");
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const submitBtn = document.getElementById("checkoutSubmitBtn");

    const bankFields = document.getElementById("bankFields");
    const bankReferenceField = document.getElementById("bankReference");
    const bankReferenceInput = document.getElementById("bankReferenceInput");
    const bankReceiptFile = document.getElementById("bankReceiptFile");

    function getSelectedPaymentMethod() {
        return document.querySelector('input[name="payment_method"]:checked')?.value || null;
    }

    function updatePaymentUI() {
        const selected = getSelectedPaymentMethod();

        if (!submitBtn) return;

        if (selected === "paydunya") {
            submitBtn.textContent = "Payer maintenant ";
            if (bankFields) bankFields.style.display = "none";
        }

        if (selected === "cash_on_delivery") {
            submitBtn.textContent = "Payer les frais  maintenant";
            if (bankFields) bankFields.style.display = "none";
        }

        if (selected === "bank_transfer") {
            submitBtn.textContent = "Confirmer la commande par virement";
            if (bankFields) bankFields.style.display = "block";
        }
    }

    paymentRadios.forEach(radio => {
        radio.addEventListener("change", updatePaymentUI);
    });

    checkoutForm?.addEventListener("submit", (e) => {
        const selected = getSelectedPaymentMethod();

        if (!selected) {
            e.preventDefault();
            alert("Veuillez choisir un mode de paiement.");
            return;
        }

        if (selected === "bank_transfer") {
            const ref = bankReferenceField?.value.trim();
            const file = bankReceiptFile?.files?.[0];

            if (!ref) {
                e.preventDefault();
                alert("Veuillez entrer la référence du virement.");
                return;
            }

            if (!file) {
                e.preventDefault();
                alert("Veuillez ajouter le reçu du virement.");
                return;
            }

            const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];

            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert("Format invalide. Image JPG, PNG ou PDF uniquement.");
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                e.preventDefault();
                alert("Fichier trop volumineux. Maximum 2MB.");
                return;
            }

            if (bankReferenceInput) {
                bankReferenceInput.value = ref;
            }
        }
    });

    updatePaymentUI();
});
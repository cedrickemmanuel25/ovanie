document.addEventListener("DOMContentLoaded", () => {

  const loginForm = document.getElementById("login-form");
  if (!loginForm) return;

  const loginBtn = loginForm.querySelector(".btn-login");
  const identifierField = loginForm.querySelector("#email");
  const passwordField = loginForm.querySelector("#password");
  const toggleBtn = document.getElementById("password-toggle");

  // ==============================
  // UTILITAIRES
  // ==============================

  function createAlert(message, type = "error") {
    clearGlobalAlert();

    const alertBox = document.createElement("div");
    alertBox.className = `form-alert ${type}`;
    alertBox.textContent = message;

    loginForm.prepend(alertBox);

    setTimeout(() => {
      alertBox.remove();
    }, 4000);
  }

  function clearGlobalAlert() {
    const old = loginForm.querySelector(".form-alert");
    if (old) old.remove();
  }

  function showFieldError(field, message) {
    removeFieldError(field);

    const error = document.createElement("div");
    error.className = "field-error";
    error.style.color = "#cc1f1a";
    error.style.fontSize = "13px";
    error.style.marginTop = "6px";
    error.textContent = message;

    field.parentElement.appendChild(error);
  }

  function removeFieldError(field) {
    const oldError = field.parentElement.querySelector(".field-error");
    if (oldError) oldError.remove();
  }

  function clearAllFieldErrors() {
    loginForm.querySelectorAll(".field-error").forEach(el => el.remove());
  }

  // ==============================
  // VALIDATION
  // ==============================

  const isValidEmail = (email) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  const isValidPhone = (phone) =>
    /^\+?225\d{8,10}$/.test(phone.replace(/\s/g, ""));

  loginForm.addEventListener("submit", (e) => {

    clearGlobalAlert();
    clearAllFieldErrors();

    const identifier = identifierField.value.trim();
    const password = passwordField.value;

    if (!identifier || !password) {
      e.preventDefault();
      createAlert("Veuillez remplir tous les champs.");
      return;
    }

    if (!isValidEmail(identifier) && !isValidPhone(identifier)) {
      e.preventDefault();
      showFieldError(identifierField, "Email ou numéro invalide.");
      return;
    }

    if (password.length < 6) {
      e.preventDefault();
      showFieldError(passwordField, "Mot de passe trop court (6 caractères minimum).");
      return;
    }

    // Désactive le bouton pour éviter double soumission
    loginBtn.disabled = true;
    loginBtn.textContent = "Connexion...";
  });

  // ==============================
  // TOGGLE MOT DE PASSE
  // ==============================

  if (passwordField && toggleBtn) {
    toggleBtn.style.cursor = "pointer";

    toggleBtn.addEventListener("click", () => {
      const isHidden = passwordField.type === "password";
      passwordField.type = isHidden ? "text" : "password";
      toggleBtn.textContent = isHidden ? "🙈" : "👁️";
    });
  }

});

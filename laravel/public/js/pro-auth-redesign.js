(() => {
  'use strict';

  const all = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  // Password visibility.
  all('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.getElementById(button.dataset.passwordToggle);
      if (!target) return;
      const show = target.type === 'password';
      target.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
  });

  // 6-digit verification code: single-char inputs with paste/arrow support.
  const codeInputs = all('[data-code-digit]');
  const hiddenCode = document.querySelector('[data-code-value]');
  const syncCode = () => {
    if (hiddenCode) hiddenCode.value = codeInputs.map((input) => input.value.replace(/\D/g, '')).join('').slice(0, 6);
  };
  codeInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(-1);
      if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
      syncCode();
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !input.value && codeInputs[index - 1]) codeInputs[index - 1].focus();
      if (event.key === 'ArrowLeft' && codeInputs[index - 1]) codeInputs[index - 1].focus();
      if (event.key === 'ArrowRight' && codeInputs[index + 1]) codeInputs[index + 1].focus();
    });
    input.addEventListener('paste', (event) => {
      const digits = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!digits) return;
      event.preventDefault();
      digits.split('').forEach((digit, offset) => {
        if (codeInputs[offset]) codeInputs[offset].value = digit;
      });
      syncCode();
      codeInputs[Math.min(digits.length, 6) - 1]?.focus();
    });
  });
  document.querySelector('[data-code-form]')?.addEventListener('submit', syncCode);

  // Password strength/rules used by reset and invitation screens.
  const bindPasswordRules = (input) => {
    const scope = input.closest('[data-password-scope]') || document;
    const rules = {
      length: (v) => v.length >= 8,
      lower: (v) => /[a-z]/.test(v),
      upper: (v) => /[A-Z]/.test(v),
      number: (v) => /\d/.test(v),
      symbol: (v) => /[^A-Za-z0-9]/.test(v),
    };
    const refresh = () => {
      const value = input.value;
      let score = 0;
      Object.entries(rules).forEach(([key, test]) => {
        const valid = test(value);
        scope.querySelectorAll(`[data-rule="${key}"]`).forEach((node) => node.classList.toggle('is-valid', valid));
        if (valid) score += 1;
      });
      const normalized = value ? Math.min(4, Math.max(1, Math.round((score / 5) * 4))) : 0;
      all('[data-strength-bar]', scope).forEach((bar, i) => bar.classList.toggle('is-on', i < normalized));
      const label = scope.querySelector('[data-strength-label]');
      if (label) label.textContent = ['', 'Faible', 'Moyen', 'Bon', 'Fort'][normalized];
    };
    input.addEventListener('input', refresh);
    refresh();
  };
  all('[data-password-rules-input]').forEach(bindPasswordRules);

  // Step 4 selectable functions + live summary.
  const roleInputs = all('[data-role-input]');
  const updateRoles = () => {
    roleInputs.forEach((input) => {
      input.closest('[data-role-card]')?.classList.toggle('is-selected', input.checked);
      const summary = document.querySelector(`[data-enabled-role="${CSS.escape(input.value)}"]`);
      summary?.classList.toggle('is-hidden', !input.checked);
    });
    const empty = document.querySelector('[data-enabled-empty]');
    if (empty) empty.classList.toggle('opx-hidden', roleInputs.some((input) => input.checked));
  };
  roleInputs.forEach((input) => input.addEventListener('change', updateRoles));
  updateRoles();

  // Confirm destructive invitation refusal without depending on global project JS.
  all('[data-opx-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.dataset.opxConfirm || 'Confirmer cette action ?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
})();

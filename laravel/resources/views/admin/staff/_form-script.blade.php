<script>
(() => {
    const role = document.getElementById('role');
    const employeeCode = document.getElementById('employee_code');
    const prefixes = { logistique: 'LOG', support: 'SUP', commercial: 'COM' };

    const syncPermissions = (applyDefaults = false) => {
        document.querySelectorAll('[data-permission-role]').forEach(group => {
            const active = group.dataset.permissionRole === role?.value;
            group.hidden = !active;
            group.querySelectorAll('input[type=checkbox]').forEach(input => {
                input.disabled = !active;
                if (active && applyDefaults) input.checked = true;
            });
        });
    };

    role?.addEventListener('change', () => {
        syncPermissions(true);
        if (employeeCode && (!employeeCode.value || /^(LOG|SUP|COM)-/.test(employeeCode.value))) {
            employeeCode.value = `${prefixes[role.value] || 'STA'}-0001`;
        }
    });

    syncPermissions(false);
})();
</script>

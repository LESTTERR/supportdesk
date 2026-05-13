document.addEventListener('DOMContentLoaded', checkSetupStatus);

async function checkSetupStatus() {
    const setupForm = document.getElementById('setup-form');
    const unavailable = document.getElementById('setup-unavailable');

    try {
        const res = await fetch('../api/staff.php?action=setup-status');
        const data = await res.json();

        if (data.has_admin) {
            setupForm.style.display = 'none';
            unavailable.style.display = 'block';
        }
    } catch {
        setupForm.style.display = 'none';
        unavailable.textContent = 'Unable to check setup status.';
        unavailable.style.display = 'block';
    }
}

async function setupAdmin(event) {
    event.preventDefault();

    const form = document.getElementById('setup-form');
    const btn = document.getElementById('setup-submit');
    const errorEl = document.getElementById('setup-error');
    const successEl = document.getElementById('setup-success');

    errorEl.style.display = 'none';
    successEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Creating...';

    try {
        const res = await fetch('../api/staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'setup-admin',
                name: form.name.value.trim(),
                email: form.email.value.trim(),
                password: form.password.value
            })
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Unable to create admin account');
        }

        successEl.textContent = 'Admin account created. Redirecting...';
        successEl.style.display = 'block';
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 900);
    } catch (err) {
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create first admin';
    }
}

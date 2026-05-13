/**
 * login.js
 * Handles the login form submission.
 */

document.addEventListener('DOMContentLoaded', initFirstAdminLink);

function appBase() {
    return window.location.pathname.includes('/pages/') ? '../' : '';
}

function pagePath(page) {
    return window.location.pathname.includes('/pages/') ? page : `pages/${page}`;
}

async function initFirstAdminLink() {
    const link = document.getElementById('first-admin-link');
    if (!link) return;

    try {
        const res = await fetch(`${appBase()}api/staff.php?action=setup-status`);
        const data = await res.json();
        link.style.display = data.has_admin ? 'none' : 'block';
    } catch {
        link.style.display = 'none';
    }
}

async function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');

    errorEl.style.display = 'none';

    try {
        const res = await fetch(`${appBase()}api/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = data.user.role === 'customer'
                ? pagePath('tickets.html')
                : pagePath('dashboard.html');
        } else {
            errorEl.textContent = data.message || 'Invalid credentials';
            errorEl.style.display = 'block';
        }
    } catch (err) {
        errorEl.textContent = 'Something went wrong';
        errorEl.style.display = 'block';
    }
}

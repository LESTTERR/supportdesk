/**
 * login.js
 * Handles the login form submission.
 */


async function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');

    errorEl.style.display = 'none';

    try {
        const res = await fetch('api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'pages/dashboard.html';
        } else {
            errorEl.textContent = data.message || 'Invalid credentials';
            errorEl.style.display = 'block';
        }
    } catch (err) {
        errorEl.textContent = 'Something went wrong';
        errorEl.style.display = 'block';
    }
}
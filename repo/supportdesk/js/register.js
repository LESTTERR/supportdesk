/**
 * register.js
 * Handles the registration form submission.
 */

async function handleRegister(event) {
    event.preventDefault();
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('register-error');
    const successEl = document.getElementById('register-success');

    errorEl.style.display = 'none';
    successEl.style.display = 'none';

    try {
        const res = await fetch('../api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });
        const data = await res.json();
        if (data.success) {
            successEl.textContent = 'Registration successful! Redirecting...';
            successEl.style.display = 'block';
            setTimeout(() => {
                window.location.href = 'tickets.html';
            }, 1500);
        } else {
            errorEl.textContent = data.message || 'Registration failed';
            errorEl.style.display = 'block';
        }
    } catch (err) {
        errorEl.textContent = 'Something went wrong';
        errorEl.style.display = 'block';
    }
}

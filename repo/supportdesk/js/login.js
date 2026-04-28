/**
 * login.js
 * Handles the login form submission.
 */

async function handleLogin(event) {
  event.preventDefault();

  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const errorEl  = document.getElementById('login-error');

  errorEl.style.display = 'none';

  try {
    // User
    const users = [
      { email: 'admin@gmail.com', password: 'admin1234', name: 'Admin User', role: 'Admin' },
      { email: 'agent@gmail.com', password: 'agent1234', name: 'Support Agent', role: 'Agent' }
    ];

    // 🔍 Check login
    const user = users.find(u => u.email === email && u.password === password);

    if (!user) {
      errorEl.textContent  = 'Invalid credentials.';
      errorEl.style.display = 'block';
      return;
    }

    //  Save user 
    sessionStorage.setItem('supportdesk_user', JSON.stringify({
      name: user.name,
      role: user.role,
      email: user.email
    }));

    // 🚀 Redirect
    window.location.href = 'pages/dashboard.html';

  } catch (err) {
    errorEl.textContent  = 'Something went wrong.';
    errorEl.style.display = 'block';
    console.error(err);
  }
}
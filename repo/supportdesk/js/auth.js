const AUTH_CHECK_URL = '../api/current-user.php';
let currentUser = null;

async function requireAuth() {
    const user = await getUser();
    if (!user) {
        const depth = window.location.pathname.includes('/pages/') ? '../' : '';
        window.location.href = depth + 'index.html';
        return null;
    }
    return user;
}

async function getUser() {
    if (currentUser) return currentUser;
    try {
        const res = await fetch(AUTH_CHECK_URL);
        if (!res.ok) return null;
        const data = await res.json();
        if (data.authenticated) {
            currentUser = data.user;
            return currentUser;
        }
        return null;
    } catch {
        return null;
    }
}

async function logout() {
    await fetch('../api/logout.php', { method: 'POST' });
    sessionStorage.clear();
    const depth = window.location.pathname.includes('/pages/') ? '../' : '';
    window.location.href = depth + 'index.html';
}

async function initSidebarUser() {
    const user = await requireAuth();
    if (!user) return;
    document.getElementById('user-avatar').textContent = initials(user.name);
    document.getElementById('user-name').textContent = user.name;
    document.getElementById('user-role').textContent = user.role;
}

async function initNavBadge() {
    const el = document.getElementById('open-count');
    if (!el) return;
    const res = await fetch('../api/tickets.php?status=open');
    const tickets = await res.json();
    el.textContent = tickets.length;
}

function initials(name) {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

initSidebarUser();
initNavBadge();
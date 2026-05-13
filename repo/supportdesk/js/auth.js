const AUTH_CHECK_URL = '../api/current-user.php';
let currentUser = null;

async function requireAuth() {
    const user = await getUser();
    if (!user) {
        window.location.href = window.location.pathname.includes('/pages/')
            ? 'login.html'
            : 'pages/login.html';
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
    window.location.href = window.location.pathname.includes('/pages/')
        ? 'login.html'
        : 'pages/login.html';
}

async function initSidebarUser() {
    const user = await requireAuth();
    if (!user) return;
    const avatar = document.getElementById('user-avatar');
    const name = document.getElementById('user-name');
    const role = document.getElementById('user-role');
    if (avatar) avatar.textContent = initials(user.name);
    if (name) name.textContent = user.name;
    if (role) role.textContent = formatRole(user.role);
    applyRoleUi(user);
}

async function initNavBadge() {
    const el = document.getElementById('open-count');
    if (!el) return;
    try {
        const res = await fetch('../api/tickets.php?status=open');
        const tickets = await res.json();
        el.textContent = Array.isArray(tickets) ? tickets.length : '0';
    } catch {
        el.textContent = '0';
    }
}

function initials(name) {
    return String(name || '')
        .split(' ')
        .filter(Boolean)
        .map(w => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2) || '--';
}

function formatRole(role = '') {
    const labels = {
        customer: 'Customer',
        agent: 'Support Agent',
        admin: 'Administrator'
    };
    return labels[role] || role;
}

function applyRoleUi(user) {
    document.querySelectorAll('.admin-only').forEach((el) => {
        el.style.display = user.role === 'admin' ? '' : 'none';
    });

    if (window.location.pathname.endsWith('/staff.html') && user.role !== 'admin') {
        window.location.href = 'tickets.html';
        return;
    }

    if (user.role !== 'customer') return;

    const dashboardLink = document.querySelector('.sidebar-nav a[href="dashboard.html"]');
    if (dashboardLink) dashboardLink.style.display = 'none';

    const ticketsLink = document.querySelector('.sidebar-nav a[href="tickets.html"]');
    if (ticketsLink) {
        ticketsLink.childNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.includes('All Tickets')) {
                node.textContent = ' My Tickets ';
            }
        });
    }

    const pageTitle = document.querySelector('.page-title');
    if (pageTitle && pageTitle.textContent.trim() === 'All Tickets') {
        pageTitle.textContent = 'My Tickets';
    }

    const newTicketText = document.querySelector('.page-sub');
    if (newTicketText && newTicketText.textContent.includes('Submit a new support request')) {
        newTicketText.textContent = 'Send a request to the support team';
    }

    if (window.location.pathname.endsWith('/dashboard.html')) {
        window.location.href = 'tickets.html';
    }
}

initSidebarUser();
initNavBadge();

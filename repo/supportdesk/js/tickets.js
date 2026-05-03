/**
 * tickets.js
 * Loads and renders the ticket list with search + filter.
 */
let currentFilter = 'all';
let searchQuery = '';

document.addEventListener('DOMContentLoaded', () => {
    loadTickets();
    document.getElementById('search-input').addEventListener('input', (e) => {
        searchQuery = e.target.value;
        loadTickets();
    });
});

async function loadTickets() {
    const params = new URLSearchParams();
    if (currentFilter !== 'all') params.append('status', currentFilter);
    if (searchQuery) params.append('search', searchQuery);

    const res = await fetch(`../api/tickets.php?${params.toString()}`);
    const tickets = await res.json();
    renderTickets(tickets);
}

function renderTickets(tickets) {
    const tbody = document.getElementById('ticket-tbody');
    if (!tickets.length) {
        tbody.innerHTML = '<tr><td colspan="7">No tickets found</td></tr>';
        return;
    }
    tbody.innerHTML = tickets.map(t => `
        <tr>
            <td class="ticket-id">#${t.id}</td>
            <td class="ticket-subject"><a href="ticket-detail.html?id=${t.id}">${escHtml(t.subject)}</a></td>
            <td>${statusBadge(t.status)}</td>
            <td>${priorityBadge(t.priority)}</td>
            <td><div class="ticket-user"><div class="av-sm">${initials(t.user_name)}</div>${escHtml(t.user_name)}</div></td>
            <td>Unassigned</td>
            <td>${formatDate(t.created_at)}</td>
        </tr>
    `).join('');
}

function setFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadTickets();
}

function filterTickets() { loadTickets(); } // debounced if needed
// helpers: statusBadge, priorityBadge, formatDate, initials, escHtml same as before


/* ── Helpers ── */
function escHtml(str = '') {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function initials(name = '') {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  });
}

function statusBadge(s = '') {
  const map = {
    open: 'badge-open',
    closed: 'badge-closed',
    'in progress': 'badge-progress'
  };
  return `<span class="badge ${map[s] || 'badge-open'}">${escHtml(s)}</span>`;
}

function priorityBadge(p = '') {
  const map = {
    High:'badge-high',
    Medium:'badge-medium',
    Low:'badge-low',
    Critical:'badge-critical'
  };
  return `<span class="badge ${map[p] || 'badge-medium'}">${escHtml(p)}</span>`;
}

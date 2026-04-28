/**
 * tickets.js
 * Loads and renders the ticket list with search + filter.
 */

let allTickets = [];
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', loadTickets);

function loadTickets() {
  const tbody = document.getElementById('ticket-tbody');

  try {
    // Get from localStorage 
    allTickets = JSON.parse(localStorage.getItem('tickets') || '[]');

    // Ensure default fields 
    allTickets = allTickets.map(t => ({
      ...t,
      status: t.status || 'open',
      user_name: t.user_name || 'You',
      created_at: t.created_at || new Date().toISOString()
    }));

    // Summary
    const open   = allTickets.filter(t => t.status === 'open').length;
    const prog   = allTickets.filter(t => t.status === 'in progress').length;
    const closed = allTickets.filter(t => t.status === 'closed').length;

    const sumEl  = document.getElementById('ticket-summary');
    if (sumEl) {
      sumEl.textContent = `${open} open · ${prog} in progress · ${closed} closed`;
    }

    renderTickets();

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" class="table-loading">Failed to load tickets.</td></tr>`;
    console.error(err);
  }
}

function renderTickets() {
  const tbody  = document.getElementById('ticket-tbody');
  const query  = (document.getElementById('search-input')?.value || '').toLowerCase();

  const rows = allTickets.filter(t => {
    const matchFilter = currentFilter === 'all' || t.status === currentFilter;
    const matchSearch = !query
      || t.subject.toLowerCase().includes(query)
      || String(t.id).includes(query)
      || t.user_name.toLowerCase().includes(query);

    return matchFilter && matchSearch;
  });

  if (rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="table-loading">No tickets match your search.</td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(t => `
    <tr>
      <td class="ticket-id">#${t.id}</td>
      <td class="ticket-subject">
        <a href="ticket-detail.html?id=${t.id}">${escHtml(t.subject)}</a>
      </td>
      <td>${statusBadge(t.status)}</td>
      <td>${priorityBadge(t.priority)}</td>
      <td>
        <div class="ticket-user">
          <div class="av-sm">${initials(t.user_name)}</div>
          ${escHtml(t.user_name)}
        </div>
      </td>
      <td class="ticket-date">${escHtml(t.assigned_to || 'Unassigned')}</td>
      <td class="ticket-date">${formatDate(t.created_at)}</td>
    </tr>
  `).join('');
}

function filterTickets() {
  renderTickets();
}

function setFilter(filter, btn) {
  currentFilter = filter;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderTickets();
}

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
/**
 * tickets.js
 * Loads and renders the ticket list with search + filter.
 */
let currentFilter = 'all';
let searchQuery = '';

document.addEventListener('DOMContentLoaded', () => {
    loadTickets();

    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (event) => {
            searchQuery = event.target.value.trim();
            loadTickets();
        });
    }
});

async function loadTickets() {
    const params = new URLSearchParams();
    if (currentFilter !== 'all') params.append('status', currentFilter);
    if (searchQuery) params.append('search', searchQuery);

    const tbody = document.getElementById('ticket-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="table-loading">Loading tickets...</td></tr>';

    try {
        const res = await fetch(`../api/tickets.php?${params.toString()}`);
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Unable to load tickets');
        }

        renderTickets(data);
        updateSummary(data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-loading">${escHtml(err.message)}</td></tr>`;
    }
}

function renderTickets(tickets) {
    const tbody = document.getElementById('ticket-tbody');
    if (!tickets.length) {
        tbody.innerHTML = '<tr><td colspan="7">No tickets found</td></tr>';
        return;
    }

    tbody.innerHTML = tickets.map((ticket) => `
        <tr>
            <td class="ticket-id">#${ticket.id}</td>
            <td class="ticket-subject"><a href="ticket-detail.html?id=${ticket.id}">${escHtml(ticket.subject)}</a></td>
            <td>${statusBadge(ticket.status)}</td>
            <td>${priorityBadge(ticket.priority)}</td>
            <td><div class="ticket-user"><div class="av-sm">${initials(ticket.user_name)}</div>${escHtml(ticket.user_name)}</div></td>
            <td>${ticket.assignee_name ? escHtml(ticket.assignee_name) : '<span class="muted-text">Unassigned</span>'}</td>
            <td>${formatDate(ticket.created_at)}</td>
        </tr>
    `).join('');
}

function updateSummary(tickets) {
    const summary = document.getElementById('ticket-summary');
    if (!summary) return;

    const label = currentFilter === 'all' ? 'tickets' : currentFilter.replace('_', ' ') + ' tickets';
    summary.textContent = `${tickets.length} ${label}`;
}

function setFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach((button) => button.classList.remove('active'));
    btn.classList.add('active');
    loadTickets();
}

function filterTickets() {
    const searchInput = document.getElementById('search-input');
    searchQuery = searchInput ? searchInput.value.trim() : '';
    loadTickets();
}

function escHtml(str = '') {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2) || '--';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function statusBadge(status = '') {
    const map = {
        open: 'badge-open',
        in_progress: 'badge-progress',
        resolved: 'badge-closed',
        closed: 'badge-closed',
    };
    const label = status.replace('_', ' ');
    return `<span class="badge ${map[status] || 'badge-open'}">${escHtml(label)}</span>`;
}

function priorityBadge(priority = '') {
    const normalized = priority.toLowerCase();
    const map = {
        high: 'badge-high',
        medium: 'badge-medium',
        low: 'badge-low',
        critical: 'badge-critical',
    };
    return `<span class="badge ${map[normalized] || 'badge-medium'}">${escHtml(normalized || 'medium')}</span>`;
}

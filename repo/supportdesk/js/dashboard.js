/**
 * dashboard.js
 * Loads stats, recent tickets and activity feed.
 */

document.addEventListener('DOMContentLoaded', async () => {
  setTodayDate();
  loadStats();
  loadRecentTickets();
  loadActivity();
});

function setTodayDate() {
  const el = document.getElementById('today-date');
  if (!el) return;
  el.textContent = new Date().toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  });
}

/* ── STATS (LOCAL) ── */
function loadStats() {
  try {
    const tickets = JSON.parse(localStorage.getItem('tickets') || '[]');

    const open     = tickets.filter(t => t.status === 'open').length;
    const resolved = tickets.filter(t => t.status === 'resolved').length;
    const progress = tickets.filter(t => t.status === 'in-progress').length;

    setText('stat-open', open);
    setText('stat-progress', progress);
    setText('stat-resolved', resolved);
    setText('stat-response', '—');

    const trendOpen = document.getElementById('stat-open-trend');
    if (trendOpen) {
      trendOpen.textContent = 'Local data';
      trendOpen.className   = 'stat-trend up';
    }

  } catch (err) {
    console.error('Failed to load stats:', err);
  }
}

/* ── RECENT TICKETS (LOCAL) ── */
function loadRecentTickets() {
  const container = document.getElementById('recent-tickets-list');

  try {
    const tickets = JSON.parse(localStorage.getItem('tickets') || '[]');

    if (tickets.length === 0) {
      container.innerHTML = '<p class="loading-msg">No tickets yet.</p>';
      return;
    }

    const latest = tickets.slice(-5).reverse();

    container.innerHTML = latest.map(t => `
      <div class="recent-ticket">
        <span class="rt-id">#${t.id}</span>
        <span class="rt-subject">${escHtml(t.subject)}</span>
        <span class="rt-pri">${priorityBadge(t.priority)}</span>
      </div>
    `).join('');

  } catch (err) {
    container.innerHTML = '<p class="loading-msg">Failed to load tickets.</p>';
    console.error(err);
  }
}

/* ── ACTIVITY (FAKE DATA) ── */
function loadActivity() {
  const container = document.getElementById('activity-list');

  try {
    const tickets = JSON.parse(localStorage.getItem('tickets') || '[]');

    if (tickets.length === 0) {
      container.innerHTML = '<p class="loading-msg">No activity yet.</p>';
      return;
    }

    const activities = tickets.slice(-5).reverse().map(t => ({
      actor: 'You',
      description: `created ticket #${t.id}`,
      time_ago: 'just now',
      type: 'opened'
    }));

    const colorMap = {
      resolved: '#2563EB',
      escalated: '#D97706',
      opened: '#16A34A',
      note: '#7C3AED',
      closed: '#16A34A'
    };

    container.innerHTML = activities.map(a => `
      <div class="activity-item">
        <div class="act-dot" style="background:${colorMap[a.type] || '#888'}"></div>
        <div class="act-text">
          <strong>${escHtml(a.actor)}</strong> ${escHtml(a.description)} · ${escHtml(a.time_ago)}
        </div>
      </div>
    `).join('');

  } catch (err) {
    container.innerHTML = '<p class="loading-msg">Failed to load activity.</p>';
    console.error(err);
  }
}

/* ── Helpers ── */
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function escHtml(str = '') {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function priorityBadge(p = '') {
  const map = {
    High:'badge-high',
    Medium:'badge-medium',
    Low:'badge-low',
    Critical:'badge-critical'
  };
  const cls = map[p] || 'badge-medium';
  return `<span class="badge ${cls}">${escHtml(p)}</span>`;
}
/**
 * dashboard.js
 * Loads stats, recent tickets and activity feed.
 */
document.addEventListener('DOMContentLoaded', async () => {
    setTodayDate();
    await loadDashboardData();
});

function setTodayDate() {
    const el = document.getElementById('today-date');
    if (el) {
        el.textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }
}

async function loadDashboardData() {
    try {
        const res = await fetch('../api/dashboard-dash.php');
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Unable to load dashboard');
        }

        document.getElementById('stat-open').textContent = data.stats.open;
        document.getElementById('stat-progress').textContent = data.stats.in_progress;
        document.getElementById('stat-resolved').textContent = data.stats.resolved_today;
        document.getElementById('stat-response').textContent = data.stats.avg_response;

        renderRecentTickets(data.recent_tickets || []);
        renderActivity(data.activity || []);
    } catch (err) {
        const recentContainer = document.getElementById('recent-tickets-list');
        const activityContainer = document.getElementById('activity-list');
        if (recentContainer) recentContainer.innerHTML = `<p class="loading-msg">${escHtml(err.message)}</p>`;
        if (activityContainer) activityContainer.innerHTML = '<p class="loading-msg">Activity is unavailable.</p>';
    }
}

function renderRecentTickets(tickets) {
    const recentContainer = document.getElementById('recent-tickets-list');
    if (!recentContainer) return;

    if (!tickets.length) {
        recentContainer.innerHTML = '<p class="loading-msg">No tickets yet.</p>';
        return;
    }

    recentContainer.innerHTML = tickets.map((ticket) => `
        <a class="recent-ticket" href="ticket-detail.html?id=${ticket.id}">
            <span class="rt-id">#${ticket.id}</span>
            <span class="rt-subject">${escHtml(ticket.subject)}</span>
            <span class="rt-pri">${priorityBadge(ticket.priority)}</span>
        </a>
    `).join('');
}

function renderActivity(activity) {
    const activityContainer = document.getElementById('activity-list');
    if (!activityContainer) return;

    if (!activity.length) {
        activityContainer.innerHTML = '<p class="loading-msg">No activity yet.</p>';
        return;
    }

    activityContainer.innerHTML = activity.map((item) => `
        <div class="activity-item">
            <div class="act-dot ${item.type === 'reply' ? 'act-dot-reply' : ''}"></div>
            <div class="act-text"><strong>${escHtml(item.actor)}</strong> ${escHtml(item.description)} - ${escHtml(item.time_ago)}</div>
        </div>
    `).join('');
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

function escHtml(str = '') {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

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
    if (el) el.textContent = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

async function loadDashboardData() {
    try {
        const res = await fetch('../api/dashboard-dash.php');
        const data = await res.json();

        document.getElementById('stat-open').textContent = data.stats.open;
        document.getElementById('stat-progress').textContent = data.stats.in_progress;
        document.getElementById('stat-resolved').textContent = data.stats.resolved_today;
        document.getElementById('stat-response').textContent = data.stats.avg_response;

        const recentContainer = document.getElementById('recent-tickets-list');
        if (data.recent_tickets.length) {
            recentContainer.innerHTML = data.recent_tickets.map(t => `
                <div class="recent-ticket">
                    <span class="rt-id">#${t.id}</span>
                    <span class="rt-subject">${escHtml(t.subject)}</span>
                    <span class="rt-pri">${priorityBadge(t.priority)}</span>
                </div>
            `).join('');
        } else {
            recentContainer.innerHTML = '<p class="loading-msg">No tickets yet.</p>';
        }

        const activityContainer = document.getElementById('activity-list');
        activityContainer.innerHTML = data.activity.map(a => `
            <div class="activity-item">
                <div class="act-dot" style="background:#16A34A"></div>
                <div class="act-text"><strong>${escHtml(a.actor)}</strong> ${escHtml(a.description)} · ${escHtml(a.time_ago)}</div>
            </div>
        `).join('');
    } catch (err) {
        console.error('Dashboard load error', err);
    }
}

function priorityBadge(p) {
    const map = { High:'badge-high', Medium:'badge-medium', Low:'badge-low', Critical:'badge-critical' };
    return `<span class="badge ${map[p] || 'badge-medium'}">${escHtml(p)}</span>`;
}


/* -- Helpers -- */
function escHtml(str = '') {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

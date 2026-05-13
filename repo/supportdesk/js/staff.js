document.addEventListener('DOMContentLoaded', () => {
    loadStaff();
});

async function loadStaff() {
    const tbody = document.getElementById('staff-tbody');
    tbody.innerHTML = '<tr><td colspan="4" class="table-loading">Loading staff...</td></tr>';

    try {
        const res = await fetch('../api/staff.php');
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Unable to load staff');
        }

        if (!data.staff.length) {
            tbody.innerHTML = '<tr><td colspan="4">No staff accounts yet.</td></tr>';
            return;
        }

        tbody.innerHTML = data.staff.map((user) => `
            <tr>
                <td>${escHtml(user.name)}</td>
                <td>${escHtml(user.email)}</td>
                <td>${escHtml(formatRole(user.role))}</td>
                <td>${formatDate(user.created_at)}</td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="table-loading">${escHtml(err.message)}</td></tr>`;
    }
}

async function createStaffAccount(event) {
    event.preventDefault();

    const form = document.getElementById('staff-form');
    const btn = document.getElementById('staff-submit');
    const errorEl = document.getElementById('staff-error');
    const successEl = document.getElementById('staff-success');

    errorEl.style.display = 'none';
    successEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Creating...';

    const payload = {
        name: form.name.value.trim(),
        email: form.email.value.trim(),
        password: form.password.value,
        role: form.role.value
    };

    try {
        const res = await fetch('../api/staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Unable to create account');
        }

        form.reset();
        successEl.textContent = `${formatRole(data.staff_user.role)} account created.`;
        successEl.style.display = 'block';
        await loadStaff();
    } catch (err) {
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create staff account';
    }
}

function formatRole(role = '') {
    const labels = {
        agent: 'Support Agent',
        admin: 'Administrator'
    };
    return labels[role] || role;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function escHtml(str = '') {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

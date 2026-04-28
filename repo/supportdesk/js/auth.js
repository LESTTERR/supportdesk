/**
 * auth.js
 * Shared authentication helpers.
 * Included on every protected page.
 */

const AUTH_KEY = 'supportdesk_user';

/** Returns the logged-in user object or null */
function getUser() {
  try {
    const raw = sessionStorage.getItem(AUTH_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

/** Redirect to login if not authenticated */
function requireAuth() {
  const user = getUser();
  if (!user) {
    const depth = window.location.pathname.includes('/pages/') ? '../' : '';
    window.location.href = depth + 'index.html';
    return null;
  }
  return user;
}

/** Log out and redirect */
function logout() {
  sessionStorage.removeItem(AUTH_KEY);
  const depth = window.location.pathname.includes('/pages/') ? '../' : '';
  window.location.href = depth + 'index.html';
}

/** Populate sidebar user info */
function initSidebarUser() {
  const user = requireAuth();
  if (!user) return;

  const avatarEl  = document.getElementById('user-avatar');
  const nameEl    = document.getElementById('user-name');
  const roleEl    = document.getElementById('user-role');

  if (avatarEl) avatarEl.textContent = initials(user.name);
  if (nameEl)   nameEl.textContent   = user.name;
  if (roleEl)   roleEl.textContent   = user.role || 'Agent';
}

/** Open ticket count badge (STATIC ONLY) */
function initNavBadge() {
  const el = document.getElementById('open-count');
  if (!el) return;

  // Static value 
  el.textContent = '0';
}

/** Helper — initials from full name */
function initials(name = '') {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) || '??';
}

// Run on every protected page
initSidebarUser();
initNavBadge();

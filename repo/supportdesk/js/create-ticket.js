/**
 * create-ticket.js
 * Handles the create ticket form: file uploads, validation, submit.
 */
let selectedFiles = [];

document.addEventListener('DOMContentLoaded', async () => {
  setupDragDrop();
  await populateRequesterFields();
});

async function submitTicket(event) {
    event.preventDefault();
    const btn = document.getElementById('submit-btn');
    const errorEl = document.getElementById('form-error');
    errorEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    const form = document.getElementById('ticket-form');
    const formData = new FormData();
    formData.append('full_name', document.getElementById('full-name').value.trim());
    formData.append('email', document.getElementById('user-email').value.trim());
    formData.append('subject', form.subject.value.trim());
    formData.append('category', form.category.value);
    formData.append('priority', form.priority.value);
    formData.append('description', form.description.value.trim());
    selectedFiles.forEach(file => formData.append('attachments[]', file));

    try {
        const res = await fetch('../api/tickets.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (res.ok) {
            showToast();
            window.setTimeout(() => {
              window.location.href = `ticket-detail.html?id=${encodeURIComponent(data.ticket_id)}`;
            }, 700);
        } else {
            throw new Error(data.error || 'Submission failed');
        }
    } catch (err) {
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit ticket';
    }
}

/* ── File Handling ──*/
function onFileSelect(input) {
  const newFiles = Array.from(input.files);
  addFiles(newFiles);
  input.value = '';
}

function onDragOver(event) {
  event.preventDefault();
  document.getElementById('file-drop-zone').classList.add('drag-over');
}

function onDrop(event) {
  event.preventDefault();
  document.getElementById('file-drop-zone').classList.remove('drag-over');
  const files = Array.from(event.dataTransfer.files);
  addFiles(files);
}

function setupDragDrop() {
  const zone = document.getElementById('file-drop-zone');
  if (!zone) return;
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
}

async function populateRequesterFields() {
  if (typeof getUser !== 'function') return;
  const user = await getUser();
  if (!user || user.role === 'agent' || user.role === 'admin') return;

  const nameField = document.getElementById('full-name');
  const emailField = document.getElementById('user-email');
  if (nameField) {
    nameField.value = user.name || '';
    nameField.readOnly = true;
  }
  if (emailField) {
    emailField.value = user.email || '';
    emailField.readOnly = true;
  }
}

function addFiles(files) {
  const allowed = ['image/png', 'image/jpeg', 'application/pdf'];
  const maxSize = 10 * 1024 * 1024;

  files.forEach(file => {
    if (!allowed.includes(file.type)) {
      showError(`File type not allowed: ${file.name}`);
      return;
    }
    if (file.size > maxSize) {
      showError(`File too large (max 10 MB): ${file.name}`);
      return;
    }
    selectedFiles.push(file);
  });

  renderFileList();
}

function removeFile(index) {
  selectedFiles.splice(index, 1);
  renderFileList();
}

function renderFileList() {
  const list = document.getElementById('file-list');
  list.innerHTML = selectedFiles.map((f, i) => `
    <div class="file-item">
      <span>${escHtml(f.name)} <span style="color:var(--c-muted)">(${formatBytes(f.size)})</span></span>
      <button type="button" onclick="removeFile(${i})" title="Remove">&times;</button>
    </div>
  `).join('');
}

/* ── Form Submit ── */
function clearForm() {
  document.getElementById('ticket-form').reset();
  selectedFiles = [];
  renderFileList();
  document.getElementById('form-error').style.display = 'none';
}

/* ── Helpers ── */
function showError(msg) {
  const el = document.getElementById('form-error');
  el.textContent    = msg;
  el.style.display  = 'block';
}

function showToast() {
  const toast = document.getElementById('toast');
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}

function escHtml(str = '') {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatBytes(bytes) {
  if (bytes < 1024)        return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

/**
 * create-ticket.js
 * Handles the create ticket form: file uploads, validation, submit.
 */

let selectedFiles = [];

document.addEventListener('DOMContentLoaded', () => {
  setupDragDrop();
});

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
async function submitTicket(event) {
  event.preventDefault();

  const btn     = document.getElementById('submit-btn');
  const errorEl = document.getElementById('form-error');
  errorEl.style.display = 'none';

  btn.textContent = 'Submitting…';
  btn.disabled    = true;

  try {
    const form = document.getElementById('ticket-form');

    // for demo / local storage)
    const formData = {
      subject: form.subject?.value || '',
      description: form.description?.value || '',
      priority: form.priority?.value || '',
      files: selectedFiles.map(f => f.name)
    };

    // Save locally 
    const tickets = JSON.parse(localStorage.getItem('tickets') || '[]');
    tickets.push({
      ...formData,
      id: Date.now(),
      status: 'open'
    });
    localStorage.setItem('tickets', JSON.stringify(tickets));

    // Success UI
    showToast();
    clearForm();

  } catch (err) {
    showError('Something went wrong.');
    console.error(err);
  } finally {
    btn.textContent = 'Submit ticket';
    btn.disabled    = false;
  }
}

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
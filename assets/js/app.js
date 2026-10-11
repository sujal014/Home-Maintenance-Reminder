const API = 'api/tasks.php';
const form = document.getElementById('task-form');
const listEl = document.getElementById('task-list');
const emptyEl = document.getElementById('empty-state');
const formError = document.getElementById('form-error');

let tasks = [];            // local copy of what the server returned
let currentFilter = 'all';

/* ---------- API helper ---------- */
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function api(url, options = {}) {
  const res = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,          // proves the request came from our page
    },
    ...options,
  });
  if (res.status === 401) {               // session expired -> back to login
    window.location.href = 'login.php';
    throw { error: 'Not logged in' };
  }
  const data = await res.json();
  if (!res.ok) throw data;       // lets callers catch {errors: {...}}
  return data;
}

async function loadTasks() {
  try {
    tasks = await api(API);
    render();
  } catch {
    emptyEl.textContent = 'Could not load tasks. Is the PHP server running?';
    emptyEl.hidden = false;
  }
}

/* ---------- Date helpers ---------- */
function daysUntil(dateStr) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const due = new Date(dateStr + 'T00:00:00');
  return Math.round((due - today) / 86400000);
}

function statusOf(task) {
  if (task.completed == 1) return 'done';
  const d = daysUntil(task.due_date);
  if (d < 0) return 'overdue';
  if (d <= 7) return 'soon';
  return 'ok';
}

function describeDue(task) {
  if (task.completed == 1) return 'Completed ' + (task.last_completed ?? '');
  const d = daysUntil(task.due_date);
  if (d < 0) return `Overdue by ${-d} day${d === -1 ? '' : 's'}`;
  if (d === 0) return 'Due today';
  if (d === 1) return 'Due tomorrow';
  return `Due in ${d} days (${task.due_date})`;
}

/* ---------- Rendering ---------- */
function render() {
  listEl.replaceChildren();

  const visible = tasks.filter(t => currentFilter === 'all' || statusOf(t) === currentFilter);
  emptyEl.textContent = 'Nothing here. Add your first task above!';
  emptyEl.hidden = visible.length > 0;

  for (const task of visible) {
    const status = statusOf(task);

    const li = document.createElement('li');
    li.className = `task ${status}`;

    const info = document.createElement('div');
    info.className = 'task-info';

    const title = document.createElement('strong');
    title.textContent = task.title;               // textContent = safe from XSS

    const badge = document.createElement('span');
    badge.className = 'badge';
    badge.textContent = task.category;

    const due = document.createElement('div');
    due.className = 'due';
    due.textContent = describeDue(task) +
      (task.frequency !== 'once' ? ` · repeats ${task.frequency}` : '');

    info.append(title, ' ', badge, due);

    if (task.notes) {
      const notes = document.createElement('div');
      notes.className = 'notes';
      notes.textContent = task.notes;
      info.append(notes);
    }

    const actions = document.createElement('div');
    actions.className = 'actions';

    if (task.completed != 1) {
      const done = document.createElement('button');
      done.className = 'btn btn-success';
      done.textContent = 'Done';
      done.addEventListener('click', () => completeTask(task.id));
      actions.append(done);
    }

    const del = document.createElement('button');
    del.className = 'btn btn-danger';
    del.textContent = 'Delete';
    del.addEventListener('click', () => deleteTask(task));
    actions.append(del);

    li.append(info, actions);
    listEl.append(li);
  }
}

/* ---------- Actions ---------- */
async function completeTask(id) {
  await api(`${API}?id=${id}`, { method: 'PUT' });
  loadTasks();
}

async function deleteTask(task) {
  if (!confirm(`Delete "${task.title}"?`)) return;
  await api(`${API}?id=${task.id}`, { method: 'DELETE' });
  loadTasks();
}

/* ---------- Form validation + submit ---------- */
function showErrors(errors) {
  document.querySelectorAll('[data-error-for]').forEach(el => {
    el.textContent = errors[el.dataset.errorFor] ?? '';
  });
}

function validate(data) {
  const errors = {};
  if (!data.title.trim()) errors.title = 'Please enter a task name.';
  if (!data.due_date) errors.due_date = 'Please choose a due date.';
  return errors;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();                      // stop the normal page reload
  formError.textContent = '';

  const data = Object.fromEntries(new FormData(form));
  const errors = validate(data);
  showErrors(errors);
  if (Object.keys(errors).length) return;  // client-side check failed

  try {
    await api(API, { method: 'POST', body: JSON.stringify(data) });
    form.reset();
    setDefaultDate();
    loadTasks();
  } catch (err) {
    showErrors(err.errors ?? {});          // server-side errors
    if (!err.errors) formError.textContent = 'Something went wrong. Try again.';
  }
});

/* ---------- Filters ---------- */
document.getElementById('filters').addEventListener('click', (e) => {
  const btn = e.target.closest('.filter');
  if (!btn) return;
  currentFilter = btn.dataset.filter;
  document.querySelectorAll('.filter').forEach(b => b.classList.toggle('active', b === btn));
  render();
});

/* ---------- Init ---------- */
function setDefaultDate() {
  const d = new Date();
  d.setDate(d.getDate() + 7);
  document.getElementById('due_date').value = d.toLocaleDateString('en-CA'); // YYYY-MM-DD
}

setDefaultDate();
loadTasks();

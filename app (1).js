/**
 * app.js — UI controller.
 */

const els = {
    form: document.getElementById('student-form'),
    formTitle: document.getElementById('form-title'),
    submitBtn: document.getElementById('submit-btn'),
    cancelBtn: document.getElementById('cancel-btn'),
    id: document.getElementById('student-id'),
    name: document.getElementById('name'),
    age: document.getElementById('age'),
    email: document.getElementById('email'),
    program: document.getElementById('program'),
    subGroup: document.getElementById('sub-program-group'),
    subProgram: document.getElementById('sub-program'),
    year: document.getElementById('year'),
    gpa: document.getElementById('gpa'),
    list: document.getElementById('student-list'),
    count: document.getElementById('count'),
    search: document.getElementById('search'),
    clearBtn: document.getElementById('clear-btn'),
    exportBtn: document.getElementById('export-btn'),
    userName: document.getElementById('user-name'),
    logoutBtn: document.getElementById('logout-btn'),
    toast: document.getElementById('toast'),
};

let allStudents = [];
let editingId = null;

// Read the Major options from index.html once. Each <option> carries a
// data-program attribute saying which Program it belongs to.
const ALL_MAJORS = Array.from(els.subProgram.options)
    .filter(o => o.value)
    .map(o => ({
        value: o.value,
        label: o.textContent.trim(),
        program: o.dataset.program,
    }));

/* ---------- Utilities ---------- */

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

let toastTimer;
function showToast(message, type = '') {
    els.toast.textContent = message;
    els.toast.className = 'toast show ' + type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        els.toast.className = 'toast ' + type;
    }, 2500);
}

/* ---------- Program / Major dropdowns ---------- */

// Show only the majors that belong to `program`; hide the Major field if none.
function updateMajorOptions(program, selected = '') {
    const options = ALL_MAJORS.filter(m => m.program === program);

    els.subProgram.innerHTML =
        '<option value="">— Select major —</option>' +
        options
            .map(m => `<option value="${escapeHtml(m.value)}">${escapeHtml(m.label)}</option>`)
            .join('');

    els.subGroup.hidden = options.length === 0;
    els.subProgram.value = options.some(m => m.value === selected) ? selected : '';
}

/* ---------- Rendering ---------- */

function renderTable(students) {
    els.count.textContent = allStudents.length;

    if (students.length === 0) {
        const msg =
            allStudents.length === 0
                ? 'No students yet. Add one above.'
                : 'No matching students.';
        els.list.innerHTML = `<tr><td colspan="8" class="empty">${msg}</td></tr>`;
        return;
    }

    els.list.innerHTML = students
        .map(
            s => `
        <tr>
            <td>${s.id}</td>
            <td>${escapeHtml(s.name)}</td>
            <td>${s.age ?? '—'}</td>
            <td>${escapeHtml(s.email) || '—'}</td>
            <td>${escapeHtml(s.major) || '—'}</td>
            <td>${s.year}</td>
            <td>${s.gpa !== null && s.gpa !== undefined ? Number(s.gpa).toFixed(2) : '—'}</td>
            <td>
                <div class="actions">
                    <button class="btn btn-secondary btn-sm" data-action="edit" data-id="${s.id}">Edit</button>
                    <button class="btn btn-danger btn-sm" data-action="delete" data-id="${s.id}">Delete</button>
                </div>
            </td>
        </tr>`
        )
        .join('');
}

function applySearch() {
    const q = els.search.value.trim().toLowerCase();
    const filtered = q
        ? allStudents.filter(s =>
              [s.name, s.email, s.major].some(v =>
                  String(v ?? '').toLowerCase().includes(q)
              )
          )
        : allStudents;
    renderTable(filtered);
}

async function refresh() {
    try {
        allStudents = await API.list();
        applySearch();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

/* ---------- Form handling ---------- */

function readForm() {
    // Combine program + major into the single `major` column the DB has
    const program = els.program.value;
    const sub = els.subProgram.value;
    const major = sub ? `${program} - ${sub}` : program;

    return {
        name: els.name.value.trim(),
        age: els.age.value,
        email: els.email.value.trim(),
        major: major,
        year: els.year.value,
        gpa: els.gpa.value,
    };
}

function resetForm() {
    els.form.reset();
    els.id.value = '';
    editingId = null;
    updateMajorOptions(''); // hides the Major field again
    els.formTitle.textContent = 'Add New Student';
    els.submitBtn.textContent = 'Add Student';
    els.cancelBtn.hidden = true;
}

function enterEditMode(student) {
    editingId = student.id;
    els.id.value = student.id;
    els.name.value = student.name ?? '';
    els.age.value = student.age ?? '';
    els.email.value = student.email ?? '';

    // Saved as "Program - Major". Split on the FIRST " - " only, because
    // some majors (e.g. "BSED - English Major") contain " - " themselves.
    const saved = String(student.major ?? '');
    const cut = saved.indexOf(' - ');
    const program = cut === -1 ? saved : saved.slice(0, cut);
    const sub = cut === -1 ? '' : saved.slice(cut + 3);

    els.program.value = program; // becomes '' if it isn't one of the options
    updateMajorOptions(els.program.value, sub);

    els.year.value = student.year;
    els.gpa.value = student.gpa ?? '';

    els.formTitle.textContent = `Edit Student #${student.id}`;
    els.submitBtn.textContent = 'Save Changes';
    els.cancelBtn.hidden = false;

    window.scrollTo({ top: 0, behavior: 'smooth' });
    els.name.focus();
}

/* ---------- Event listeners ---------- */

els.form.addEventListener('submit', async e => {
    e.preventDefault();
    els.submitBtn.disabled = true;

    try {
        const data = readForm();
        if (!data.name) throw new Error('Name is required');

        if (editingId !== null) {
            await API.update(editingId, data);
            showToast('Student updated', 'success');
        } else {
            await API.create(data);
            showToast('Student added', 'success');
        }
        resetForm();
        await refresh();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        els.submitBtn.disabled = false;
    }
});

els.cancelBtn.addEventListener('click', resetForm);

els.search.addEventListener('input', applySearch);

els.program.addEventListener('change', () => {
    updateMajorOptions(els.program.value);
});

// Event delegation for Edit/Delete buttons
els.list.addEventListener('click', async e => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id);

    if (btn.dataset.action === 'edit') {
        const student = allStudents.find(s => s.id === id);
        if (student) enterEditMode(student);
    }

    if (btn.dataset.action === 'delete') {
        if (!confirm(`Delete student #${id}? This cannot be undone.`)) return;
        try {
            await API.remove(id);
            if (editingId === id) resetForm();
            showToast('Student deleted', 'success');
            await refresh();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }
});

els.clearBtn.addEventListener('click', async () => {
    if (!confirm('Erase ALL student records from the database? This cannot be undone.')) return;
    try {
        await API.clearAll();
        resetForm();
        showToast('All data cleared', 'success');
        await refresh();
    } catch (err) {
        showToast(err.message, 'error');
    }
});

els.exportBtn.addEventListener('click', () => {
    const blob = new Blob([JSON.stringify(allStudents, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students-${new Date().toISOString().slice(0, 10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
});

els.logoutBtn.addEventListener('click', async () => {
    try {
        await API.logout();
    } catch (err) {
        showToast(err.message, 'error');
        return;
    }
    window.location.replace('login.html');
});

/* ---------- Start ---------- */

// Only admins get past this point: ask the server who is logged in, send
// everyone else to the login page, then load the students.
async function init() {
    updateMajorOptions('');
    try {
        const session = await API.session();
        if (!session.loggedIn) {
            window.location.replace('login.html');
            return;
        }
        els.userName.textContent = session.username;
        document.body.classList.remove('auth-pending');
        await refresh();
    } catch (err) {
        // Couldn't reach the server: show the page so the error is visible
        document.body.classList.remove('auth-pending');
        showToast(err.message, 'error');
    }
}

init();

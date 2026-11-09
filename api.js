/**
 * api.js — Data access facade.
 * To connect to the C++ backend, replace each `return Storage.X()` call
 * with a fetch() to the corresponding endpoint. Signatures stay identical.
 */

const API = {
    /** GET all students */
    async list() {
        return Storage.getAll();
        // Later:
        // const res = await fetch('/api/students');
        // if (!res.ok) throw new Error('Failed to load students');
        // return res.json();
    },

    /** POST new student */
    async create(data) {
        return Storage.create(data);
        // Later:
        // const res = await fetch('/api/students', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(data),
        // });
        // if (!res.ok) throw new Error('Failed to create student');
        // return res.json();
    },

    /** PUT existing student */
    async update(id, data) {
        return Storage.update(id, data);
        // Later:
        // const res = await fetch(`/api/students/${id}`, {
        //     method: 'PUT',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(data),
        // });
        // if (!res.ok) throw new Error('Failed to update student');
        // return res.json();
    },

    /** DELETE student */
    async remove(id) {
        return Storage.remove(id);
        // Later:
        // const res = await fetch(`/api/students/${id}`, { method: 'DELETE' });
        // if (!res.ok) throw new Error('Failed to delete student');
        // return true;
    },

    /** Wipe everything */
    async clearAll() {
        return Storage.clear();
        // Later: DELETE /api/students
    },
};
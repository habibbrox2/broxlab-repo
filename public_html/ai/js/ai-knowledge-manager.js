(function(){
    const apiList = '/api/admin/ai-knowledge';

    function showAlert(msg, type='success'){
        const el = document.getElementById('alertContainer');
        el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    }

    async function fetchList(){
        const res = await fetch(apiList, { credentials: 'same-origin' });
        const data = await res.json();
        const list = data.items || [];
        const container = document.getElementById('kbList');
        if (!list.length) {
            container.innerHTML = '<p class="text-muted">No knowledge slices found.</p>';
            return;
        }
        let html = '<div class="list-group">';
        list.forEach(item => {
            html += `<div class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${escapeHtml(item.title)}</h6>
                    <small class="text-muted">${escapeHtml(item.excerpt)}</small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-2" data-id="${item.id}" onclick="kbEdit(${item.id})">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" data-id="${item.id}" onclick="kbDelete(${item.id})">Delete</button>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function escapeHtml(s){
        return (s||'').replace(/[&<>\"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m];});
    }

    window.kbEdit = async function(id){
        const res = await fetch(apiList + '/' + id, { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) { showAlert('Failed to load item', 'danger'); return; }
        const it = data.item;
        document.getElementById('kb_id').value = it.id;
        document.getElementById('kb_title').value = it.title || '';
        document.getElementById('kb_content').value = it.content || '';
        document.getElementById('kb_source_type').value = it.source_type || 'text';
        var modal = new bootstrap.Modal(document.getElementById('kbModal'));
        modal.show();
    }

    window.kbDelete = async function(id){
        if (!confirm('Delete this slice?')) return;
        const form = new FormData();
        form.append('id', id);
        form.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
        const res = await fetch('/api/admin/ai-knowledge/delete', { method: 'POST', body: form, credentials: 'same-origin' });
        const data = await res.json();
        if (data.success) { showAlert('Deleted'); fetchList(); }
        else showAlert('Delete failed', 'danger');
    }

    async function save(){
        const id = document.getElementById('kb_id').value || 0;
        const payload = {
            id: id,
            title: document.getElementById('kb_title').value,
            content: document.getElementById('kb_content').value,
            source_type: document.getElementById('kb_source_type').value,
            csrf_token: document.querySelector('input[name=csrf_token]').value
        };
        const res = await fetch(apiList, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials: 'same-origin' });
        const data = await res.json();
        if (data.success) {
            showAlert('Saved');
            var modalEl = document.getElementById('kbModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            fetchList();
        } else {
            showAlert('Save failed', 'danger');
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        fetchList();
        document.getElementById('btnAddSlice').addEventListener('click', function(){
            document.getElementById('kbForm').reset();
            document.getElementById('kb_id').value = 0;
            var modal = new bootstrap.Modal(document.getElementById('kbModal'));
            modal.show();
        });
        document.getElementById('kbSaveBtn').addEventListener('click', save);
    });

})();

/**
 * assets/js/main.js
 * -----------------------------------------------------------------
 * Shared front-end behaviour for the ZPPSU IPO Dashboard:
 *  - Lucide icons renderer
 *  - Mobile & desktop sidebar collapse toggling
 *  - Profile dropdown toggle
 *  - Toast notifications (call showToast from anywhere)
 *  - Generic modal open/close helpers
 *  - Confirm-dialog helper for delete/reject actions
 *  - Document preview modal
 * -----------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', function () {
    // ---- 1. Initialize Lucide Icons -------------------------------
    if (typeof lucide !== 'undefined') {
        lucide.createIcons({
            attrs: {
                'stroke-width': 1.8
            }
        });
    }

    // ---- 2. Mobile Sidebar Toggle ---------------------------------
    const toggleBtn = document.getElementById('sidebarToggle') || document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ---- 3. Desktop Sidebar Collapse Toggle -----------------------
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (collapseBtn && sidebar) {
        collapseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.body.classList.toggle('sidebar-compact');
        });
    }

    // ---- 4. Topbar Profile Menu Dropdown --------------------------
    const userToggle = document.getElementById('userMenuToggle');
    const userMenu = document.getElementById('userMenuDropdown');
    if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = userMenu.style.display === 'block';
            userMenu.style.display = isOpen ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (userMenu.style.display === 'block' && !userMenu.contains(e.target)) {
                userMenu.style.display = 'none';
            }
        });
    }

    // ---- 5. Dark Mode Switch Toggle (Fully Functional) ------------
    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        themeSwitch.checked = currentTheme === 'dark';
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark');
        }

        themeSwitch.addEventListener('change', () => {
            if (themeSwitch.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                showToast('info', 'Dark mode enabled');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                showToast('info', 'Light mode enabled');
            }
        });
    }

    // ---- View Switcher (Cards vs Table) ----
    document.querySelectorAll('.view-switch-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const targetView = this.dataset.view;
            const container = this.closest('.card') || document;
            container.querySelectorAll('.view-switch-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const cardsView = container.querySelector('.project-card-grid');
            const tableView = container.querySelector('.table-wrap');

            if (targetView === 'cards') {
                if (cardsView) cardsView.style.display = 'grid';
                if (tableView) tableView.style.display = 'none';
            } else {
                if (cardsView) cardsView.style.display = 'none';
                if (tableView) tableView.style.display = 'block';
            }
        });
    });

    // ---- 6. Modal open/close (data-modal-target / data-modal-close) -
    document.querySelectorAll('[data-modal-target]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.querySelector(trigger.dataset.modalTarget);
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.modal-overlay')?.classList.remove('open'));
    });
    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('open'); });
    });

    // ---- 7. Confirm dialogs for destructive actions ----------------
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // ---- 8. Auto-show flash toast rendered by PHP (window.FLASH) ---
    if (window.FLASH && window.FLASH.message) {
        showToast(window.FLASH.type || 'info', window.FLASH.message);
    }
});

/** Show a floating toast notification. type: success|error|warning|info */
function showToast(type, message, timeout = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const icons = { success: '✔', error: '✕', warning: '!', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = `<strong style="font-size:15px;line-height:1;">${icons[type] || 'ℹ'}</strong><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.25s ease';
        setTimeout(() => toast.remove(), 250);
    }, timeout);
}

/** Small helper other pages can call for AJAX POSTs (JSON in/out). */
async function postJSON(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    return res.json();
}

/**
 * Document preview modal — used across every page that lists uploaded
 * documents (admin, staff, evaluator, manager, applicant).
 */
function openDocPreview(url, fileType, fileName) {
    let overlay = document.getElementById('docPreviewOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'docPreviewOverlay';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal-box" style="width:min(820px, 94vw);max-height:92vh;">
                <div class="modal-head">
                    <h3 id="docPreviewTitle">Preview</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('docPreviewOverlay').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body" id="docPreviewBody" style="padding:0;max-height:76vh;overflow:auto;"></div>
                <div class="modal-foot">
                    <a id="docPreviewDownload" class="btn btn-primary btn-sm" target="_blank">Download File</a>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('open'); });
    }

    document.getElementById('docPreviewTitle').textContent = fileName || 'Document Preview';
    document.getElementById('docPreviewDownload').href = url;
    const body = document.getElementById('docPreviewBody');
    const ext = (fileType || '').toLowerCase();

    if (['jpg', 'jpeg', 'png'].includes(ext)) {
        body.innerHTML = `<img src="${url}" alt="${fileName}" style="width:100%;display:block;max-height:74vh;object-fit:contain;background:#000;">`;
    } else if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${url}" style="width:100%;height:74vh;border:none;display:block;"></iframe>`;
    } else {
        body.innerHTML = `
            <div style="padding:48px 20px;text-align:center;color:var(--color-text-muted);font-size:13.5px;">
                <i data-lucide="file-question" style="width:40px;height:40px;margin-bottom:12px;color:var(--color-text-subtle);"></i>
                <br>Inline preview is not supported for ${ext.toUpperCase() || 'this'} files in the browser.<br>
                Click <strong>Download File</strong> below to view it locally.
            </div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    overlay.classList.add('open');
}

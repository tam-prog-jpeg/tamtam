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

    // ---- 4. Topbar Notification Dropdown & Profile Menu -----------
    const notifBtn = document.getElementById('notifBellBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const userToggle = document.getElementById('userMenuToggle');
    const userMenu = document.getElementById('userMenuDropdown');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (userMenu) userMenu.style.display = 'none';
            const isOpen = notifDropdown.style.display === 'block';
            notifDropdown.style.display = isOpen ? 'none' : 'block';
            notifBtn.setAttribute('aria-expanded', !isOpen);
        });
    }

    if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            if (notifDropdown) notifDropdown.style.display = 'none';
            const isOpen = userMenu.style.display === 'block';
            userMenu.style.display = isOpen ? 'none' : 'block';
        });
    }

    document.addEventListener('click', (e) => {
        if (notifDropdown && notifDropdown.style.display === 'block' && (!notifBtn || !notifBtn.contains(e.target)) && !notifDropdown.contains(e.target)) {
            notifDropdown.style.display = 'none';
            if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
        }
        if (userMenu && userMenu.style.display === 'block' && (!userToggle || !userToggle.contains(e.target)) && !userMenu.contains(e.target)) {
            userMenu.style.display = 'none';
        }
    });

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

    // ---- 9. Password Visibility Toggle ---------------------------
    document.querySelectorAll('.password-toggle-btn, [data-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.dataset.passwordTarget;
            const input = targetId ? document.querySelector(targetId) : this.closest('.input-icon-wrap')?.querySelector('input');
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            this.title = isPassword ? 'Hide password' : 'Show password';
            
            // Swap Lucide icon or fallback SVG
            const icon = this.querySelector('i, svg');
            if (icon) {
                if (icon.hasAttribute('data-lucide')) {
                    icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else {
                    icon.outerHTML = isPassword 
                        ? '<svg class="lucide-icon" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>'
                        : '<svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                }
            }
        });
    });

    // ---- 10. Live Password Strength Meter -------------------------
    document.querySelectorAll('[data-strength-meter]').forEach((input) => {
        const targetSelector = input.dataset.strengthMeter;
        const targetWrap = document.querySelector(targetSelector);
        if (!targetWrap) return;

        const segments = targetWrap.querySelectorAll('.password-strength-segment');
        const statusText = targetWrap.querySelector('.strength-text');

        input.addEventListener('input', function () {
            const val = this.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/\d/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const colors = ['#EF4444', '#F59E0B', '#3B82F6', '#10B981'];
            const labels = ['Too Weak', 'Fair', 'Good', 'Strong'];

            segments.forEach((seg, idx) => {
                if (val.length === 0) {
                    seg.style.backgroundColor = 'var(--color-border)';
                } else if (idx < score) {
                    seg.style.backgroundColor = colors[score - 1];
                } else {
                    seg.style.backgroundColor = 'var(--color-border)';
                }
            });

            if (statusText) {
                statusText.textContent = val.length === 0 ? 'Enter password' : labels[score - 1] || 'Too Weak';
                statusText.style.color = val.length === 0 ? 'var(--color-text-muted)' : (colors[score - 1] || colors[0]);
            }
        });
    });

    // ---- 11. Live Password Match Validator -----------------------
    document.querySelectorAll('[data-match-target]').forEach((confirmInput) => {
        const targetSelector = confirmInput.dataset.matchTarget;
        const mainInput = document.querySelector(targetSelector);
        if (!mainInput) return;

        function checkMatch() {
            if (!confirmInput.value) {
                confirmInput.classList.remove('is-valid', 'is-invalid');
                return;
            }
            if (confirmInput.value === mainInput.value) {
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
            }
        }

        confirmInput.addEventListener('input', checkMatch);
        mainInput.addEventListener('input', () => {
            if (confirmInput.value) checkMatch();
        });
    });

    // ---- 12. Live Character & Word Counters -----------------------
    document.querySelectorAll('[data-char-counter]').forEach((field) => {
        const targetSelector = field.dataset.charCounter;
        const counterEl = document.querySelector(targetSelector);
        const max = parseInt(field.getAttribute('maxlength') || field.dataset.maxChars || 0, 10);
        if (!counterEl || !max) return;

        function updateCounter() {
            const current = field.value.length;
            counterEl.textContent = `${current} / ${max}`;
            counterEl.classList.remove('warning', 'danger');
            if (current >= max) {
                counterEl.classList.add('danger');
            } else if (current >= max * 0.85) {
                counterEl.classList.add('warning');
            }
        }
        field.addEventListener('input', updateCounter);
        updateCounter();
    });

    // ---- 13. Interactive File Dropzone ----------------------------
    document.querySelectorAll('.file-dropzone').forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        const previewWrap = zone.querySelector('.file-dropzone-preview');
        const defaultContent = zone.querySelector('.file-dropzone-default');
        if (!input) return;

        ['dragenter', 'dragover'].forEach(event => {
            zone.addEventListener(event, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(event => {
            zone.addEventListener(event, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });

        zone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleFileSelect(input.files[0]);
            }
        });

        input.addEventListener('change', () => {
            if (input.files.length) {
                handleFileSelect(input.files[0]);
            }
        });

        function handleFileSelect(file) {
            if (!file) return;
            const sizeStr = file.size > 1048576 
                ? (file.size / 1048576).toFixed(2) + ' MB'
                : (file.size / 1024).toFixed(1) + ' KB';

            if (previewWrap) {
                const nameEl = previewWrap.querySelector('.file-preview-name');
                const sizeEl = previewWrap.querySelector('.file-preview-size');
                if (nameEl) nameEl.textContent = file.name;
                if (sizeEl) sizeEl.textContent = sizeStr;
                previewWrap.style.display = 'flex';
                if (defaultContent) defaultContent.style.display = 'none';
            }
        }

        const removeBtn = zone.querySelector('.file-remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                input.value = '';
                if (previewWrap) previewWrap.style.display = 'none';
                if (defaultContent) defaultContent.style.display = 'block';
            });
        }
    });

    // ---- 14. Interactive Selection Chips / Tags -------------------
    document.querySelectorAll('.form-chip').forEach((chip) => {
        const checkbox = chip.querySelector('input[type="checkbox"]');
        chip.addEventListener('click', (e) => {
            if (e.target !== checkbox) {
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    chip.classList.toggle('active', checkbox.checked);
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    chip.classList.toggle('active');
                }
            } else {
                chip.classList.toggle('active', checkbox.checked);
            }
        });
    });

    // ---- 15. Form Submit Button Loading State ---------------------
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', function (e) {
            if (form.hasAttribute('data-no-loading')) return;
            // Check form validity if HTML5 validation is enabled
            if (form.checkValidity && !form.checkValidity()) {
                return; // Let native or custom validation trigger
            }
            const submitBtn = form.querySelector('button[type="submit"]:not([data-no-loading])');
            if (submitBtn && !submitBtn.disabled) {
                // Set explicit dimensions before making text transparent to avoid layout shift
                const btnWidth = submitBtn.offsetWidth;
                submitBtn.style.minWidth = btnWidth + 'px';
                submitBtn.classList.add('btn-loading');
                // Auto-timeout failsafe
                setTimeout(() => {
                    submitBtn.classList.remove('btn-loading');
                }, 8000);
            }
        });
    });

    // ---- 16. Copy to Clipboard Utility ----------------------------
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const textToCopy = this.dataset.copy || (this.dataset.copyTarget ? document.querySelector(this.dataset.copyTarget)?.value : '');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalHtml = this.innerHTML;
                this.classList.add('btn-success');
                this.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;"></i> Copied!';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                showToast('success', 'Copied to clipboard');
                setTimeout(() => {
                    this.classList.remove('btn-success');
                    this.innerHTML = originalHtml;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }, 2000);
            }).catch(() => {
                showToast('error', 'Unable to copy to clipboard');
            });
        });
    });
});

/** Show a floating toast notification at bottom-left. type: success|error|warning|info */
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
        toast.style.transform = 'translateX(-30px)';
        toast.style.transition = 'all 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
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
 * Enhanced Document preview modal with loading & error states
 * Used across every page that lists uploaded documents (admin, staff, evaluator, manager, applicant).
 */
function openDocPreview(url, fileType, fileName) {
    let overlay = document.getElementById('docPreviewOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'docPreviewOverlay';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal-box" style="width:min(860px, 94vw);max-height:92vh;display:flex;flex-direction:column;">
                <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <span id="docPreviewExtBadge" class="badge badge-info" style="text-transform:uppercase;font-size:11px;">FILE</span>
                        <h3 id="docPreviewTitle" style="margin:0;font-size:16px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Document Preview</h3>
                    </div>
                    <button type="button" class="modal-close" onclick="document.getElementById('docPreviewOverlay').classList.remove('open')">&times;</button>
                </div>
                <div class="modal-body" id="docPreviewBody" style="padding:0;min-height:360px;max-height:75vh;overflow:auto;position:relative;background:var(--color-surface-subtle);display:flex;align-items:center;justify-content:center;">
                    <!-- Dynamic preview content injected here -->
                </div>
                <div class="modal-foot" style="display:flex;align-items:center;justify-content:space-between;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('docPreviewOverlay').classList.remove('open')">Close</button>
                    <a id="docPreviewDownload" class="btn btn-primary btn-sm" target="_blank" download>
                        <i data-lucide="download" style="width:15px;height:15px;"></i>
                        <span>Download File</span>
                    </a>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('open'); });
    }

    const cleanTitle = fileName || 'Document Preview';
    const ext = (fileType || (cleanTitle.split('.').pop() || '')).toLowerCase();
    
    document.getElementById('docPreviewTitle').textContent = cleanTitle;
    document.getElementById('docPreviewExtBadge').textContent = ext.toUpperCase() || 'DOC';
    
    const downloadBtn = document.getElementById('docPreviewDownload');
    downloadBtn.href = url;
    downloadBtn.setAttribute('download', cleanTitle);

    const body = document.getElementById('docPreviewBody');
    body.innerHTML = `
        <div id="docPreviewLoader" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:48px 20px;color:var(--color-text-muted);">
            <div style="width:28px;height:28px;border:3px solid #E5E7EB;border-top-color:var(--color-primary-600);border-radius:50%;animation:spin 0.8s linear infinite;"></div>
            <span style="font-size:13px;font-weight:500;">Loading document preview...</span>
        </div>
    `;

    overlay.classList.add('open');

    const removeLoader = () => {
        const loader = document.getElementById('docPreviewLoader');
        if (loader) loader.remove();
    };

    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
        const img = new Image();
        img.src = url;
        img.alt = cleanTitle;
        img.style.cssText = 'max-width:100%;max-height:74vh;object-fit:contain;display:none;margin:auto;';
        img.onload = () => {
            removeLoader();
            img.style.display = 'block';
        };
        img.onerror = () => {
            removeLoader();
            body.innerHTML = `
                <div style="padding:48px 24px;text-align:center;color:var(--color-text-muted);font-size:13.5px;">
                    <i data-lucide="alert-circle" style="width:40px;height:40px;color:var(--color-danger);margin-bottom:10px;"></i>
                    <div style="font-weight:600;color:var(--color-text);margin-bottom:4px;">Unable to load image preview</div>
                    <div>The image could not be loaded directly. You may still download the file below.</div>
                </div>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };
        body.appendChild(img);
    } else if (ext === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.style.cssText = 'width:100%;height:74vh;border:none;display:none;background:#FFFFFF;';
        iframe.onload = () => {
            removeLoader();
            iframe.style.display = 'block';
        };
        iframe.onerror = () => {
            removeLoader();
            body.innerHTML = `
                <div style="padding:48px 24px;text-align:center;color:var(--color-text-muted);font-size:13.5px;">
                    <i data-lucide="file-text" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:10px;"></i>
                    <div style="font-weight:600;color:var(--color-text);margin-bottom:4px;">PDF Preview Unavailable</div>
                    <div>Your browser could not preview this PDF document inline. Please download to view.</div>
                </div>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };
        body.appendChild(iframe);
        // Fallback timer if iframe load event is blocked by sandbox
        setTimeout(() => {
            removeLoader();
            iframe.style.display = 'block';
        }, 1200);
    } else if (ext === 'txt') {
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.text();
            })
            .then(text => {
                removeLoader();
                body.style.display = 'block';
                body.innerHTML = `
                    <div style="width:100%;height:100%;max-height:74vh;overflow:auto;padding:22px;box-sizing:border-box;">
                        <pre style="margin:0;font-family:var(--font-mono);font-size:12.5px;color:var(--color-text);white-space:pre-wrap;line-height:1.6;">${text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>
                    </div>`;
            })
            .catch(() => {
                removeLoader();
                body.innerHTML = `
                    <div style="padding:48px 24px;text-align:center;color:var(--color-text-muted);font-size:13.5px;">
                        <i data-lucide="alert-circle" style="width:40px;height:40px;color:var(--color-danger);margin-bottom:10px;"></i>
                        <div style="font-weight:600;color:var(--color-text);margin-bottom:4px;">Unable to load text preview</div>
                        <div>You can still download the file using the button below.</div>
                    </div>`;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
    } else {
        removeLoader();
        body.innerHTML = `
            <div style="padding:48px 28px;text-align:center;color:var(--color-text-muted);max-width:480px;margin:auto;">
                <div style="width:60px;height:60px;border-radius:14px;background:var(--color-surface);border:1px solid var(--color-border);color:var(--color-primary-600);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:var(--shadow-sm);">
                    <i data-lucide="file-text" style="width:30px;height:30px;"></i>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--color-text);margin-bottom:6px;word-break:break-all;">${cleanTitle}</div>
                <div style="font-size:13px;line-height:1.5;color:var(--color-text-muted);margin-bottom:18px;">
                    Direct in-browser preview is not supported for <strong>.${ext.toUpperCase()}</strong> files. Click below to download and inspect this document locally.
                </div>
                <a href="${url}" class="btn btn-primary btn-sm" target="_blank" download="${cleanTitle}" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 18px;">
                    <i data-lucide="download" style="width:14px;height:14px;"></i> Download and View Document
                </a>
            </div>`;
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

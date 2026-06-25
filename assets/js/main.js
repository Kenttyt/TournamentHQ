/**
 * Main JavaScript
 * Table Tennis Tournament Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    initSidebar();
    initModals();
    initSearch();
    initForms();
    initNotificationPage();
    autoFlashDismiss();
    setupCollapse();
});

/* ============================================================
   SIDEBAR TOGGLE
   ============================================================ */
function initSidebar() {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const wrapper = document.querySelector('.main-wrapper');
    if (!toggle || !sidebar) return;

    const isMobile = () => window.innerWidth <= 768;

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            wrapper?.classList.toggle('expanded');
        }
        toggle.setAttribute('aria-expanded', sidebar.classList.contains('open') || sidebar.classList.contains('collapsed'));
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (isMobile() && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== toggle) {
                sidebar.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Close sidebar on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && (sidebar.classList.contains('open') || sidebar.classList.contains('collapsed'))) {
            if (isMobile()) {
                sidebar.classList.remove('open');
            } else {
                sidebar.classList.add('collapsed');
                wrapper?.classList.add('expanded');
            }
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
}

/* ============================================================
   MODAL SYSTEM
   ============================================================ */
function initModals() {
    // Open modal on data-modal-open click
    document.addEventListener('click', (e) => {
        const openBtn = e.target.closest('[data-modal-open]');
        if (openBtn) {
            const id = openBtn.getAttribute('data-modal-open');
            openModal(id);
        }

        const closeBtn = e.target.closest('[data-modal-close], .modal-close');
        if (closeBtn) {
            const overlay = closeBtn.closest('.modal-overlay');
            if (overlay) closeModal(overlay.id);
        }

        // Close on overlay backdrop click
        if (e.target.classList.contains('modal-overlay')) {
            closeModal(e.target.id);
        }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => {
                closeModal(m.id);
            });
        }
    });
}

function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // Focus first focusable element
        const focusable = overlay.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) setTimeout(() => focusable.focus(), 50);
        // Trap focus
        overlay._trapHandler = (e) => {
            if (e.key !== 'Tab') return;
            const focusables = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        overlay.addEventListener('keydown', overlay._trapHandler);
    }
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (overlay._trapHandler) {
            overlay.removeEventListener('keydown', overlay._trapHandler);
            overlay._trapHandler = null;
        }
    }
}

/* ============================================================
   SEARCH / FILTER
   ============================================================ */
function initSearch() {
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-search-table');
        const table   = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('input', () => {
            const query = input.value.toLowerCase().trim();
            const rows  = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    });

    // Status/role filter dropdowns
    const filterSelects = document.querySelectorAll('[data-filter-table]');
    filterSelects.forEach(select => {
        const tableId = select.getAttribute('data-filter-table');
        const colIdx  = parseInt(select.getAttribute('data-filter-col') || '0');
        const table   = document.getElementById(tableId);
        if (!table) return;

        select.addEventListener('change', () => {
            const val  = select.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cell = row.cells[colIdx];
                if (!val || !cell) { row.style.display = ''; return; }
                row.style.display = cell.textContent.toLowerCase().includes(val) ? '' : 'none';
            });
        });
    });
}

/* ============================================================
   FORMS
   ============================================================ */
function initForms() {
    // Confirm delete buttons
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm]');
        if (btn) {
            const message = btn.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        }
    });

}

/* ============================================================
   NOTIFICATIONS PAGE (sidebar)
   ============================================================ */
function initNotificationPage() {
    const list = document.querySelector('.notifications-page-list');
    if (!list) return;

    const actionUrl = '/TournamentHQ/includes/notification_action.php';

    // ---- Mark-read on link click (existing behaviour) ----
    list.querySelectorAll('.notification-card-link[data-notification-id]').forEach((item) => {
        item.addEventListener('click', () => {
            const id = item.getAttribute('data-notification-id');
            if (!id) return;
            const body = new URLSearchParams();
            body.set('action', 'mark_read');
            body.set('notification_id', id);
            fetch(actionUrl, { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
        });
    });

    // ---- Kebab menu toggle ----
    list.addEventListener('click', (e) => {
        const kebabBtn = e.target.closest('.notif-kebab-btn');
        if (kebabBtn) {
            e.preventDefault();
            e.stopPropagation();
            const menu = kebabBtn.nextElementSibling;
            const isOpen = !menu.hidden;

            // Close all other open menus first
            list.querySelectorAll('.notif-kebab-menu:not([hidden])').forEach(m => m.hidden = true);

            menu.hidden = isOpen;
            return;
        }

        // ---- Kebab action buttons ----
        const actionBtn = e.target.closest('[data-notif-action]');
        if (actionBtn) {
            e.preventDefault();
            e.stopPropagation();
            const action = actionBtn.getAttribute('data-notif-action');
            const notifId = actionBtn.getAttribute('data-notif-id');
            const card = actionBtn.closest('.notification-card');

            // Close the menu
            const menu = actionBtn.closest('.notif-kebab-menu');
            if (menu) menu.hidden = true;

            if (!notifId) return;

            const body = new URLSearchParams();
            body.set('notification_id', notifId);

            if (action === 'mark_read') {
                body.set('action', 'mark_read');
                fetch(actionUrl, { method: 'POST', body, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok && card) {
                            card.classList.remove('is-unread');
                            // Remove the "Mark as Read" button from the menu
                            actionBtn.remove();
                        }
                    })
                    .catch(() => {});
            } else if (action === 'delete') {
                body.set('action', 'delete');
                fetch(actionUrl, { method: 'POST', body, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok && card) {
                            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                card.remove();
                                // If no notifications remain, show the empty state
                                if (!list.querySelector('.notification-card')) {
                                    const emptyDiv = document.createElement('div');
                                    emptyDiv.className = 'notification-empty';
                                    emptyDiv.innerHTML = '<p>No notifications yet</p>';
                                    list.replaceWith(emptyDiv);
                                }
                            }, 300);
                        }
                    })
                    .catch(() => {});
            }
        }
    });

    // ---- Close kebab menus when clicking outside ----
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.notif-kebab-wrap')) {
            list.querySelectorAll('.notif-kebab-menu:not([hidden])').forEach(m => m.hidden = true);
        }
    });
}

/* ============================================================
   FLASH AUTO-DISMISS
   ============================================================ */
function autoFlashDismiss() {
    const flash = document.getElementById('flashMessage');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.5s ease';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }
}

/* ============================================================
   UTILITY EXPORTS
   ============================================================ */
window.TTMS = {
    openModal,
    closeModal,
    toast,
    loading,
    validate,
    debounce,
    setupCollapse,
    copyToClipboard,
};

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
function toast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('ttms-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ttms-toast-container';
        container.style.cssText = 'position:fixed;top:24px;right:24px;z-index:10000;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'rgba(0,212,170,0.15)', border: 'rgba(0,212,170,0.3)', text: '#00d4aa' },
        error: { bg: 'rgba(255,80,80,0.15)', border: 'rgba(255,80,80,0.3)', text: '#ff6b6b' },
        warning: { bg: 'rgba(255,165,0,0.15)', border: 'rgba(255,165,0,0.35)', text: '#ffb74d' },
        info: { bg: 'rgba(108,99,255,0.15)', border: 'rgba(108,99,255,0.3)', text: '#8b85ff' },
    };
    const c = colors[type] || colors.info;

    const el = document.createElement('div');
    el.style.cssText = `background:${c.bg};border:1px solid ${c.border};color:${c.text};padding:12px 20px;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;box-shadow:0 4px 12px rgba(0,0,0,0.3);opacity:0;transform:translateX(20px);transition:all 0.3s ease;max-width:360px;`;
    el.textContent = message;
    container.appendChild(el);

    requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'translateX(0)'; });

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        setTimeout(() => el.remove(), 300);
    }, duration);
}

/* ============================================================
   LOADING STATE
   ============================================================ */
function loading(button, state) {
    if (!button) return;
    if (state) {
        button.dataset.origHtml = button.innerHTML;
        button.disabled = true;
        button.style.opacity = '0.7';
        button.style.pointerEvents = 'none';
        button.innerHTML = '<svg style="animation:spin 1s linear infinite;width:18px;height:18px;margin-right:8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Loading...';
        if (!document.getElementById('ttms-spin-keyframes')) {
            const style = document.createElement('style');
            style.id = 'ttms-spin-keyframes';
            style.textContent = '@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
    } else {
        button.disabled = false;
        button.style.opacity = '';
        button.style.pointerEvents = '';
        if (button.dataset.origHtml) button.innerHTML = button.dataset.origHtml;
    }
}

/* ============================================================
   INLINE FORM VALIDATION
   ============================================================ */
function validate(form) {
    if (!form) return true;
    let valid = true;
    form.querySelectorAll('.ttms-field-error').forEach(e => e.remove());

    form.querySelectorAll('[required]').forEach(input => {
        if (!input.value.trim()) {
            valid = false;
            showFieldError(input, 'This field is required');
        }
    });

    form.querySelectorAll('input[type="email"][required]').forEach(input => {
        if (input.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
            valid = false;
            showFieldError(input, 'Please enter a valid email');
        }
    });

    return valid;
}

function showFieldError(input, message) {
    const err = document.createElement('div');
    err.className = 'ttms-field-error';
    err.style.cssText = 'color:#ff6b6b;font-size:12px;margin-top:4px;';
    err.textContent = message;
    input.parentNode.appendChild(err);
    input.style.borderColor = '#ff6b6b';
    input.addEventListener('input', () => {
        input.style.borderColor = '';
        err.remove();
    }, { once: true });
}

/* ============================================================
   DEBOUNCE
   ============================================================ */
function debounce(fn, ms) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}

/* ============================================================
   COLLAPSIBLE / ACCORDION
   ============================================================ */
function setupCollapse() {
    document.querySelectorAll('[data-collapse-toggle]').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-collapse-toggle');
            const target = document.getElementById(targetId);
            if (!target) return;
            const isOpen = target.style.display !== 'none';
            target.style.display = isOpen ? 'none' : '';
            toggle.setAttribute('aria-expanded', !isOpen);
        });
    });
}

/* ============================================================
   COPY TO CLIPBOARD
   ============================================================ */
function copyToClipboard(text, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            toast('Copied to clipboard', 'success', 2000);
            if (btn) {
                const orig = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;"></i>';
                lucide.createIcons();
                setTimeout(() => { btn.innerHTML = orig; lucide.createIcons(); }, 1500);
            }
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        toast('Copied to clipboard', 'success', 2000);
    }
}

/* ============================================================
   LIGHTBOX (payment proof image preview)
   ============================================================ */
function openLightbox(src) {
    var overlay = document.getElementById('proofLightbox');
    if (!overlay) return;
    var img = overlay.querySelector('img');
    img.src = src;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    var overlay = document.getElementById('proofLightbox');
    if (!overlay) return;
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

/* ============================================================
   BULK APPROVE / REJECT
   ============================================================ */
function toggleAllBulkChecks(checkbox, tid) {
    document.querySelectorAll('.bulk-check[data-tournament="' + tid + '"]').forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}
function getCheckedIds(tid) {
    var ids = [];
    document.querySelectorAll('.bulk-check[data-tournament="' + tid + '"]:checked').forEach(function(cb) {
        ids.push(cb.value);
    });
    return ids;
}
function bulkApprove(tid) {
    var ids = getCheckedIds(tid);
    if (!ids.length) { alert('Select at least one player.'); return; }
    document.getElementById('bulkApproveIds_' + tid).value = ids.join(',');
    document.getElementById('bulkApproveForm_' + tid).submit();
}
function bulkReject(tid) {
    var ids = getCheckedIds(tid);
    if (!ids.length) { alert('Select at least one player.'); return; }
    document.getElementById('bulkRejectIds_' + tid).value = ids.join(',');
    document.getElementById('bulkRejectForm_' + tid).submit();
}

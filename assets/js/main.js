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
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (isMobile() && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== toggle) {
                sidebar.classList.remove('open');
            }
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
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
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

    const actionUrl = '/table-tennis-system/includes/notification_action.php';
    list.querySelectorAll('[data-notification-id]').forEach((item) => {
        item.addEventListener('click', () => {
            const id = item.getAttribute('data-notification-id');
            if (!id) return;
            const body = new URLSearchParams();
            body.set('action', 'mark_read');
            body.set('notification_id', id);
            fetch(actionUrl, { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
        });
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
};

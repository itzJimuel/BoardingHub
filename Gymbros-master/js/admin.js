/* Admin Panel Interactive Script - GymBros */

function getAdminApiUrl() {
    return window.location.pathname.includes('/php/') ? 'admin_actions.php' : '../php/admin_actions.php';
}

document.addEventListener('DOMContentLoaded', () => {
    initAdminTabs();
    initFilterAndSearch();
    initDeleteRequestsFilter();
    initModals();
    initSidebarToggle();
});

function initSidebarToggle() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('adminSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
}

// Toast Notification System
function showToast(message, type = 'success') {
    let container = document.getElementById('admin-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'admin-toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `admin-toast ${type}`;
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = `<i class="fas ${icon}"></i> <span>${escapeHtml(message)}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Styled replacement for alert()/confirm(). Resolves with the value of the button
// that closed the dialog, or '' when it was dismissed with Esc.
function showAdminDialog({ tone = 'info', icon = 'fa-circle-info', title, message, content = [], actions, dismissible = true }) {
    const dialog = document.createElement('dialog');
    const titleId = 'bh-alert-title-' + Date.now();
    dialog.className = 'bh-dialog bh-alert';
    dialog.setAttribute('aria-labelledby', titleId);

    const body = document.createElement('div');
    body.className = 'bh-alert-body';

    const iconBox = document.createElement('div');
    iconBox.className = `bh-alert-icon is-${tone}`;
    iconBox.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i>`;

    const heading = document.createElement('h2');
    heading.id = titleId;
    heading.className = 'bh-alert-title';
    heading.textContent = title;
    body.append(iconBox, heading);

    if (message) {
        const text = document.createElement('p');
        text.className = 'bh-alert-message';
        text.textContent = message;
        body.append(text);
    }
    body.append(...content);

    const buttons = document.createElement('form');
    buttons.method = 'dialog';
    buttons.className = 'bh-alert-actions';
    (actions || [{ label: 'OK', value: 'ok', variant: 'primary', autofocus: true }]).forEach(action => {
        const btn = document.createElement('button');
        btn.value = action.value;
        btn.className = `btn-dialog-${action.variant || 'secondary'}`;
        btn.autofocus = Boolean(action.autofocus);
        if (action.icon) btn.innerHTML = `<i class="fas ${action.icon}" aria-hidden="true"></i> `;
        btn.append(action.label);
        buttons.append(btn);
    });

    dialog.append(body, buttons);
    if (!dismissible) dialog.addEventListener('cancel', (e) => e.preventDefault());
    document.body.append(dialog);
    dialog.showModal();
    dialog.scrollTop = 0; // focusing a bottom button must not hide the title on short screens

    return new Promise(resolve => {
        dialog.addEventListener('close', () => {
            resolve(dialog.returnValue);
            setTimeout(() => dialog.remove(), 300); // let the exit transition finish
        }, { once: true });
    });
}

function createAlertNote(tone, icon, heading, text) {
    const note = document.createElement('div');
    note.className = `bh-alert-note is-${tone}`;
    note.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i>`;

    const copy = document.createElement('div');
    if (heading) {
        const strong = document.createElement('strong');
        strong.textContent = heading;
        copy.append(strong);
    }
    copy.append(text);
    note.append(copy);
    return note;
}

function createCopyField(label, value, variant = '') {
    const field = document.createElement('div');
    field.className = 'bh-copy-field' + (variant ? ` is-${variant}` : '');

    const text = document.createElement('div');
    const labelEl = document.createElement('span');
    labelEl.className = 'bh-copy-field-label';
    labelEl.textContent = label;
    const valueEl = document.createElement('span');
    valueEl.className = 'bh-copy-field-value';
    valueEl.textContent = value;
    text.append(labelEl, valueEl);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'bh-copy-btn';
    btn.setAttribute('aria-label', `Copy ${label.toLowerCase()}`);
    btn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i> <span>Copy</span>';
    btn.addEventListener('click', () => copyFieldValue(btn, valueEl));

    field.append(text, btn);
    return field;
}

async function copyFieldValue(button, valueEl) {
    const icon = button.querySelector('i');
    const label = button.querySelector('span');
    try {
        await navigator.clipboard.writeText(valueEl.textContent);
        icon.className = 'fas fa-check';
        label.textContent = 'Copied';
        button.classList.add('is-copied');
    } catch (err) {
        // The Clipboard API needs HTTPS or localhost; select the text so it can be copied by hand
        window.getSelection().selectAllChildren(valueEl);
        label.textContent = 'Press Ctrl+C';
    }
    clearTimeout(button.resetTimer);
    button.resetTimer = setTimeout(() => {
        icon.className = 'fas fa-copy';
        label.textContent = 'Copy';
        button.classList.remove('is-copied');
    }, 2000);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, (m) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[m]);
}

// Tab Switching Logic
function initAdminTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.admin-tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetTab = btn.getAttribute('data-tab');
            if (!targetTab) return;

            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.add('hidden'));

            btn.classList.add('active');
            const pane = document.getElementById(`tab-${targetTab}`);
            if (pane) {
                pane.classList.remove('hidden');
            }

            // Sync URL query parameter without full reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', targetTab);
            window.history.replaceState({}, '', url.toString());
        });
    });
}

// Search & Filter Logic (Client-side real-time filtering by Employee ID / Name / Username / Role / Status)
function initFilterAndSearch() {
    const searchInput = document.getElementById('search-employee-id');
    const roleFilter = document.getElementById('filter-role');
    const statusFilter = document.getElementById('filter-status');

    function filterTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const role = (roleFilter ? roleFilter.value : '').toLowerCase();
        const status = (statusFilter ? statusFilter.value : '').toLowerCase();

        const rows = document.querySelectorAll('#all-users-table-body tr');

        rows.forEach(row => {
            const empId = row.getAttribute('data-empid') || '';
            const username = row.getAttribute('data-username') || '';
            const fullname = row.getAttribute('data-fullname') || '';
            const userRole = row.getAttribute('data-role') || '';
            const userStatus = row.getAttribute('data-status') || '';

            const matchesSearch = !query || 
                empId.toLowerCase().includes(query) || 
                username.toLowerCase().includes(query) || 
                fullname.toLowerCase().includes(query);

            const matchesRole = !role || userRole.toLowerCase() === role;
            const matchesStatus = !status || userStatus.toLowerCase() === status;

            if (matchesSearch && matchesRole && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (roleFilter) roleFilter.addEventListener('change', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);
}

// Deletion Requests Real-time Live Filter
function initDeleteRequestsFilter() {
    const searchInput = document.getElementById('search-deletion-requests');
    const statusFilter = document.getElementById('filter-deletion-status');

    function filterDeleteTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const status = (statusFilter ? statusFilter.value : '').toLowerCase();

        const rows = document.querySelectorAll('#deletion-requests-table-body tr');

        rows.forEach(row => {
            if (!row.getAttribute('data-reqid')) return;
            const target = (row.getAttribute('data-target-user') || '').toLowerCase();
            const requester = (row.getAttribute('data-requester') || '').toLowerCase();
            const reason = (row.getAttribute('data-reason') || '').toLowerCase();
            const reqStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const reqId = (row.getAttribute('data-reqid') || '').toLowerCase();

            const matchesSearch = !query || 
                target.includes(query) || 
                requester.includes(query) || 
                reason.includes(query) ||
                reqId.includes(query) ||
                `#req-${reqId}`.includes(query);

            const matchesStatus = !status || reqStatus === status;

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterDeleteTable);
    if (statusFilter) statusFilter.addEventListener('change', filterDeleteTable);
}

// Modal Controllers
function initModals() {
    // Close modal when clicking background overlay or cancel buttons
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    document.querySelectorAll('.modal-close, .btn-modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) closeModal(modal.id);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Alias used by inline onclick handlers in dashboard.php
function closeAdminModal(modalId) {
    closeModal(modalId);
}

// Account Status Update (Approve / Block / Pending)
function updateUserStatus(userId, status) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_status',
            user_id: userId,
            status: status,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            if (data.self_demoted) {
                setTimeout(() => { window.location.href = 'logout.php'; }, 1800);
            } else {
                setTimeout(() => window.location.reload(), 800);
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Network request failed: ' + err, 'error');
    });
}

// Reactivating the standby Super Admin makes them the active one and puts you on standby
async function handOverSuperAdmin(userId, username) {
    const choice = await showAdminDialog({
        tone: 'warning',
        icon: 'fa-right-left',
        title: 'Hand over Super Admin access?',
        message: `@${username} will become the active Super Admin. Your account will go on standby and you'll be signed out. Only one Super Admin can be active at a time, so @${username} can hand access back to you later.`,
        actions: [
            { label: 'Cancel', value: 'cancel', variant: 'secondary', autofocus: true },
            { label: 'Hand over and sign out', value: 'confirm', variant: 'danger' }
        ]
    });
    if (choice === 'confirm') updateUserStatus(userId, 'approved');
}

// Edit User Info Modal Loader & Handler
function openEditUserModal(userId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_user_details',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;
            document.getElementById('edit-id-number').value = u.id_number;
            document.getElementById('edit-username').value = u.username;
            document.getElementById('edit-firstname').value = u.first_name;
            document.getElementById('edit-middlename').value = u.middle_name || '';
            document.getElementById('edit-lastname').value = u.last_name;
            document.getElementById('edit-email').value = u.email;
            document.getElementById('edit-birthdate').value = u.birthdate;
            document.getElementById('edit-sex').value = u.sex;
            document.getElementById('edit-purok').value = u.purok_street;
            document.getElementById('edit-barangay').value = u.barangay;
            document.getElementById('edit-city').value = u.city_municipality;
            document.getElementById('edit-province').value = u.province;
            document.getElementById('edit-zip').value = u.zip_code;
            
            const roleSelect = document.getElementById('edit-role');
            if (roleSelect) roleSelect.value = u.role;
            const statusSelect = document.getElementById('edit-status');
            if (statusSelect) statusSelect.value = u.status;

            // The role field only exists for Super Admins, who can't demote or deactivate themselves
            const currentUserIdEl = document.getElementById('current-user-id');
            const isOwnSuperAdminAccount = roleSelect && currentUserIdEl && u.id_number === currentUserIdEl.value;
            [roleSelect, statusSelect].forEach(select => {
                if (!select) return;
                select.disabled = Boolean(isOwnSuperAdminAccount);
                select.title = isOwnSuperAdminAccount ? 'To step down, hand over access to the standby Super Admin' : '';
            });

            if (roleSelect) {
                const limitReached = document.getElementById('super-admin-limit-reached')?.value === '1';
                const superAdminOption = roleSelect.querySelector('option[value="superadmin"]');
                superAdminOption.disabled = limitReached && u.role !== 'superadmin';
                superAdminOption.textContent = superAdminOption.disabled ? 'Super Administrator (limit reached)' : 'Super Administrator';
            }

            openModal('modal-edit-user');
        } else {
            showToast(data.message || 'Failed to load user details', 'error');
        }
    });
}

async function submitEditUserForm(e) {
    e.preventDefault();

    const editingId = document.getElementById('edit-id-number').value;
    const currentUserIdEl = document.getElementById('current-user-id');
    const roleSelect = document.getElementById('edit-role');
    const statusSelect = document.getElementById('edit-status');
    const promotingToActiveSuperadmin = roleSelect && statusSelect &&
        roleSelect.value === 'superadmin' && statusSelect.value === 'approved' &&
        currentUserIdEl && editingId !== currentUserIdEl.value;

    if (promotingToActiveSuperadmin) {
        const username = document.getElementById('edit-username').value;
        const choice = await showAdminDialog({
            tone: 'warning',
            icon: 'fa-right-left',
            title: 'Hand over Super Admin access?',
            message: `@${username} will become the active Super Admin. Your account will go on standby and you'll be signed out. Only one Super Admin can be active at a time, so @${username} can hand access back to you later.`,
            actions: [
                { label: 'Cancel', value: 'cancel', variant: 'secondary', autofocus: true },
                { label: 'Hand over and sign out', value: 'confirm', variant: 'danger' }
            ]
        });
        if (choice !== 'confirm') return;
    }

    const csrfToken = document.getElementById('csrf_token_val').value;

    const payload = {
        action: 'update_user_info',
        csrf_token: csrfToken,
        id_number: document.getElementById('edit-id-number').value,
        username: document.getElementById('edit-username').value,
        first_name: document.getElementById('edit-firstname').value,
        middle_name: document.getElementById('edit-middlename').value,
        last_name: document.getElementById('edit-lastname').value,
        email: document.getElementById('edit-email').value,
        birthdate: document.getElementById('edit-birthdate').value,
        sex: document.getElementById('edit-sex').value,
        purok_street: document.getElementById('edit-purok').value,
        barangay: document.getElementById('edit-barangay').value,
        city_municipality: document.getElementById('edit-city').value,
        province: document.getElementById('edit-province').value,
        zip_code: document.getElementById('edit-zip').value
    };

    if (roleSelect && !roleSelect.disabled) payload.role = roleSelect.value;
    if (statusSelect && !statusSelect.disabled) payload.status = statusSelect.value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-edit-user');
            if (data.self_demoted) {
                setTimeout(() => { window.location.href = 'logout.php'; }, 1800);
            } else {
                setTimeout(() => window.location.reload(), 800);
            }
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Privileges Modal Loader & Handler (Super Admin Only)
function openPrivilegesModal(userId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_user_details',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;
            const targetIdEl = document.getElementById('priv-target-user-id');
            const targetNameEl = document.getElementById('priv-target-name');
            if (targetIdEl) targetIdEl.value = u.id_number;
            if (targetNameEl) targetNameEl.innerText = `${u.first_name} ${u.last_name} (@${u.username})`;

            let privs = {};
            try {
                privs = u.privileges ? (typeof u.privileges === 'object' ? u.privileges : JSON.parse(u.privileges)) : {};
            } catch (e) {
                privs = {};
            }

            const privKeys = [
                'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
                'can_delete_users', 'can_manage_requests', 'can_give_privileges',
                'can_view_reports', 'can_export_logs',
                'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
            ];

            privKeys.forEach(key => {
                const el = document.getElementById('priv_' + key);
                if (el) {
                    el.checked = !!privs[key];
                }
            });

            openModal('modal-privileges');
        } else {
            showToast(data.message || 'Failed to load user info', 'error');
        }
    });
}

function submitPrivilegesForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('priv-target-user-id').value;

    const privKeys = [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
    ];

    const privileges = {};
    privKeys.forEach(key => {
        const el = document.getElementById('priv_' + key);
        if (el) {
            privileges[key] = el.checked;
        }
    });

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'grant_privileges',
            user_id: userId,
            privileges: privileges,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-privileges');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

/* ==========================================================================
   SUPERADMIN PRIVILEGES STUDIO (privileges.php)
   ========================================================================== */

const ALL_PRIVILEGE_KEYS = [
    'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
    'can_delete_users', 'can_manage_requests', 'can_give_privileges',
    'can_view_reports', 'can_export_logs',
    'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
];

const PRIVILEGE_PRESETS = {
    full_delegate: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics'
    ],
    senior_admin: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_requests',
        'can_view_reports', 'can_export_logs', 'can_manage_classes', 'can_manage_bookings'
    ],
    user_manager: [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts'
    ],
    audit_officer: [
        'can_view_reports', 'can_export_logs', 'can_manage_requests'
    ],
    gym_manager: [
        'can_manage_classes', 'can_manage_bookings', 'can_manage_metrics', 'can_approve_users'
    ],
    standard_staff: [
        'can_approve_users', 'can_view_reports'
    ]
};

function onPrivilegeUserSelected(userId) {
    const selectEl = document.getElementById('privilege-user-select');
    const displayCard = document.getElementById('target-user-profile-display');
    const detailsBox = document.getElementById('active-profile-details');
    const submitBtn = document.getElementById('btn-save-privileges-submit');
    const targetHiddenInput = document.getElementById('studio-target-user-id');
    const superadminNotice = document.getElementById('superadmin-notice-banner');

    if (!userId) {
        if (displayCard) displayCard.classList.add('empty-state');
        if (detailsBox) detailsBox.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = true;
        if (targetHiddenInput) targetHiddenInput.value = '';
        revokeAllPrivileges();
        return;
    }

    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) return;

    const role = selectedOption.getAttribute('data-role');
    const status = selectedOption.getAttribute('data-status');
    const name = selectedOption.getAttribute('data-name');
    const username = selectedOption.getAttribute('data-username');
    const email = selectedOption.getAttribute('data-email');
    const privsRaw = selectedOption.getAttribute('data-privileges');

    if (displayCard) displayCard.classList.remove('empty-state');
    if (detailsBox) detailsBox.classList.remove('hidden');

    document.getElementById('profile-fullname').innerText = name;
    document.getElementById('profile-username').innerText = `@${username}`;
    document.getElementById('profile-id-number').innerText = userId;
    document.getElementById('profile-email').innerText = email;

    // Role badge
    const roleBadge = document.getElementById('profile-role-badge');
    if (role === 'superadmin') {
        roleBadge.innerHTML = '<span class="badge badge-superadmin"><i class="fas fa-crown"></i> Super Admin</span>';
        if (superadminNotice) superadminNotice.classList.remove('hidden');
    } else if (role === 'admin') {
        roleBadge.innerHTML = '<span class="badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
        if (superadminNotice) superadminNotice.classList.add('hidden');
    } else {
        roleBadge.innerHTML = '<span class="badge badge-user"><i class="fas fa-user"></i> Member</span>';
        if (superadminNotice) superadminNotice.classList.add('hidden');
    }

    // Status badge
    const statusBadge = document.getElementById('profile-status-badge');
    if (status === 'approved') {
        statusBadge.innerHTML = '<span class="badge badge-approved"><i class="fas fa-check-circle"></i> Active</span>';
    } else if (status === 'pending') {
        statusBadge.innerHTML = '<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>';
    } else {
        statusBadge.innerHTML = '<span class="badge badge-blocked"><i class="fas fa-ban"></i> Blocked</span>';
    }

    if (targetHiddenInput) targetHiddenInput.value = userId;

    // Parse and apply privileges
    let privs = {};
    if (role === 'superadmin') {
        ALL_PRIVILEGE_KEYS.forEach(k => privs[k] = true);
    } else {
        try {
            privs = privsRaw ? (typeof privsRaw === 'object' ? privsRaw : JSON.parse(privsRaw)) : {};
        } catch (e) {
            privs = {};
        }
    }

    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = !!privs[key];
            updatePrivilegeUIState(key);
        }
    });

    if (submitBtn) {
        submitBtn.disabled = (role === 'superadmin');
    }

    updatePrivilegesStudioCounters();
}

function loadUserIntoStudio(userId) {
    const selectEl = document.getElementById('privilege-user-select');
    if (selectEl) {
        selectEl.value = userId;
        onPrivilegeUserSelected(userId);
        
        // Scroll to studio smoothly
        const studioEl = document.querySelector('.privilege-studio-layout');
        if (studioEl) {
            studioEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function updatePrivilegeUIState(privKey) {
    const chk = document.getElementById('check_' + privKey);
    const card = document.getElementById('card_' + privKey);
    const pill = document.getElementById('pill_' + privKey);

    if (chk && card) {
        if (chk.checked) {
            card.classList.add('checked');
            if (pill) {
                pill.innerText = 'Active';
                pill.className = 'priv-status-pill';
            }
        } else {
            card.classList.remove('checked');
            if (pill) {
                pill.innerText = 'Inactive';
                pill.className = 'priv-status-pill';
            }
        }
    }
    updatePrivilegesStudioCounters();
}

function updatePrivilegesStudioCounters() {
    let activeCount = 0;
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk && chk.checked) {
            activeCount++;
        }
    });

    const total = ALL_PRIVILEGE_KEYS.length;
    const counterEl = document.getElementById('active-privileges-counter');
    const barEl = document.getElementById('privilege-progress-bar');

    if (counterEl) {
        counterEl.innerText = `${activeCount} / ${total}`;
    }
    if (barEl) {
        const pct = Math.round((activeCount / total) * 100);
        barEl.style.width = `${pct}%`;
    }
}

function grantAllPrivileges() {
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = true;
            updatePrivilegeUIState(key);
        }
    });
    showToast('All Superadmin privileges selected', 'success');
}

function revokeAllPrivileges() {
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = false;
            updatePrivilegeUIState(key);
        }
    });
}

function toggleCategoryCheckboxes(catKey) {
    const container = document.querySelector(`.privilege-cards-grid[data-category="${catKey}"]`);
    if (!container) return;

    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(c => c.checked);

    checkboxes.forEach(c => {
        c.checked = !allChecked;
        const key = c.getAttribute('data-priv-key');
        if (key) updatePrivilegeUIState(key);
    });
}

function applyPrivilegePreset(presetKey) {
    const selectEl = document.getElementById('privilege-user-select');
    if (!selectEl || !selectEl.value) {
        showToast('Please select a target account first before applying a preset', 'error');
        return;
    }

    const activeKeys = PRIVILEGE_PRESETS[presetKey] || [];
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            chk.checked = activeKeys.includes(key);
            updatePrivilegeUIState(key);
        }
    });

    showToast(`Applied preset: ${presetKey.replace('_', ' ').toUpperCase()}`, 'success');
}

function resetCurrentPrivilegesForm() {
    const selectEl = document.getElementById('privilege-user-select');
    if (selectEl && selectEl.value) {
        onPrivilegeUserSelected(selectEl.value);
        showToast('Privilege form reset to current database settings', 'success');
    }
}

function submitPrivilegesStudioForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('studio-target-user-id').value;
    const submitBtn = document.getElementById('btn-save-privileges-submit');

    if (!userId) {
        showToast('Please choose an account to configure', 'error');
        return;
    }

    const privileges = {};
    ALL_PRIVILEGE_KEYS.forEach(key => {
        const chk = document.getElementById('check_' + key);
        if (chk) {
            privileges[key] = chk.checked;
        }
    });

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'grant_privileges',
            user_id: userId,
            privileges: privileges,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Save Privileges';
        }
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 900);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Save Privileges';
        }
        showToast('Request failed: ' + err, 'error');
    });
}

function handleCardClick(privKey, event) {
    if (event && (event.target.tagName === 'INPUT' || event.target.closest('.switch'))) {
        return;
    }
    const chk = document.getElementById('check_' + privKey);
    if (chk) {
        chk.checked = !chk.checked;
        updatePrivilegeUIState(privKey);
    }
}

function filterStudioUserDropdown(query) {
    const selectEl = document.getElementById('privilege-user-select');
    if (!selectEl) return;

    const q = (query || '').toLowerCase().trim();
    const optgroups = selectEl.querySelectorAll('optgroup');
    let firstMatchValue = null;
    let totalMatches = 0;

    optgroups.forEach(group => {
        let groupHasMatch = false;
        const options = group.querySelectorAll('option');

        options.forEach(opt => {
            const val = (opt.value || '').toLowerCase();
            const text = (opt.textContent || '').toLowerCase();
            const name = (opt.getAttribute('data-name') || '').toLowerCase();
            const username = (opt.getAttribute('data-username') || '').toLowerCase();
            const email = (opt.getAttribute('data-email') || '').toLowerCase();

            const isMatch = !q || val.includes(q) || text.includes(q) || name.includes(q) || username.includes(q) || email.includes(q);

            if (isMatch) {
                opt.hidden = false;
                opt.disabled = false;
                opt.style.display = '';
                groupHasMatch = true;
                totalMatches++;
                if (!firstMatchValue && opt.value) {
                    firstMatchValue = opt.value;
                }
            } else {
                opt.hidden = true;
                opt.disabled = true;
                opt.style.display = 'none';
            }
        });

        group.style.display = groupHasMatch ? '' : 'none';
    });

    // If Enter key is pressed or user wants to auto-pick first match
    return { firstMatchValue, totalMatches };
}

// Add enter key support to studio search input
document.addEventListener('DOMContentLoaded', () => {
    const studioSearch = document.getElementById('studio-user-search');
    if (studioSearch) {
        studioSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const res = filterStudioUserDropdown(studioSearch.value);
                if (res && res.firstMatchValue) {
                    const selectEl = document.getElementById('privilege-user-select');
                    if (selectEl) {
                        selectEl.value = res.firstMatchValue;
                        onPrivilegeUserSelected(res.firstMatchValue);
                        showToast('Loaded account: ' + selectEl.options[selectEl.selectedIndex].text, 'success');
                    }
                }
            }
        });
    }
});

function filterMatrixTable() {
    const searchInput = document.getElementById('matrix-search-input');
    const roleSelect = document.getElementById('matrix-filter-role');
    const tbody = document.getElementById('privileges-matrix-tbody');
    if (!tbody) return;

    const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
    const role = (roleSelect ? roleSelect.value : '').toLowerCase();

    const rows = tbody.querySelectorAll('tr:not(.no-results-row)');
    let visibleCount = 0;

    rows.forEach(row => {
        const empId = (row.getAttribute('data-empid') || '').toLowerCase();
        const username = (row.getAttribute('data-username') || '').toLowerCase();
        const fullname = (row.getAttribute('data-fullname') || '').toLowerCase();
        const email = (row.getAttribute('data-email') || '').toLowerCase();
        const privs = (row.getAttribute('data-privs') || '').toLowerCase();
        const userRole = (row.getAttribute('data-role') || '').toLowerCase();

        const matchesQuery = !query || 
            empId.includes(query) || 
            username.includes(query) || 
            fullname.includes(query) || 
            email.includes(query) || 
            privs.includes(query);

        const matchesRole = !role || userRole === role;

        if (matchesQuery && matchesRole) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    let emptyRow = tbody.querySelector('.no-results-row');
    if (visibleCount === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.className = 'no-results-row';
            emptyRow.innerHTML = `
                <td colspan="6" style="text-align: center; padding: 35px 20px; color: #94a3b8;">
                    <i class="fas fa-search" style="font-size: 26px; margin-bottom: 10px; color: var(--accent, #ff5e00); opacity: 0.7; display: block;"></i>
                    <strong style="font-size: 15px; color: #fff; display: block; margin-bottom: 4px;">No matching accounts found</strong>
                    <span style="font-size: 13px;">Try adjusting your search query or role filter.</span>
                </td>
            `;
            tbody.appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
}


// Administrator Delete Request Flow
function openDeleteRequestModal(userId, username) {
    document.getElementById('delreq-user-id').value = userId;
    document.getElementById('delreq-username').innerText = `${username} (ID: ${userId})`;
    document.getElementById('delreq-reason').value = '';
    openModal('modal-delete-request');
}

function submitDeleteRequestForm(e) {
    e.preventDefault();
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('delreq-user-id').value;
    const reason = document.getElementById('delreq-reason').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'request_delete',
            user_id: userId,
            reason: reason,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-delete-request');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Super Admin Direct Delete
function openDirectDeleteModal(userId, username) {
    document.getElementById('direct-del-user-id').value = userId;
    document.getElementById('direct-del-username').innerText = `${username} (ID: ${userId})`;
    openModal('modal-direct-delete');
}

function confirmDirectDelete() {
    const csrfToken = document.getElementById('csrf_token_val').value;
    const userId = document.getElementById('direct-del-user-id').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_account',
            user_id: userId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-direct-delete');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Super Admin Review Delete Request
function openReviewDeleteRequestModal(requestId) {
    const csrfToken = document.getElementById('csrf_token_val').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_delete_request_details',
            request_id: requestId,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data) {
            const d = data.data;
            document.getElementById('review-req-id').value = d.id;
            document.getElementById('review-req-reason').innerText = d.reason;
            document.getElementById('review-req-by').innerText = `${d.requester_fname || ''} ${d.requester_lname || ''} (@${d.requester_username || d.requested_by})`;
            document.getElementById('review-req-date').innerText = d.requested_at;

            // Target user full info
            document.getElementById('review-target-name').innerText = `${d.first_name} ${d.middle_name || ''} ${d.last_name}`;
            document.getElementById('review-target-id').innerText = d.target_user_id;
            document.getElementById('review-target-username').innerText = d.username;
            document.getElementById('review-target-email').innerText = d.email;
            document.getElementById('review-target-role').innerText = d.role;
            document.getElementById('review-target-address').innerText = `${d.purok_street}, ${d.barangay}, ${d.city_municipality}, ${d.province}`;

            openModal('modal-review-delete-request');
        } else {
            showToast(data.message || 'Failed to load request details', 'error');
        }
    });
}

function processDeleteRequest(decision) {
    const csrfToken = document.getElementById('csrf_token_val').value;
    const requestId = document.getElementById('review-req-id').value;

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'review_delete_request',
            request_id: requestId,
            decision: decision,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-review-delete-request');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Live validation for the Create Account form. Mirrors the server rules in
// Validation::validateUsername / validateName (the same rules as the Register form).
const CREATE_ACCOUNT_RULES = {
    'create-username': { label: 'Username', required: true, check: usernameErrors },
    'create-firstname': { label: 'First Name', required: true, check: nameErrors },
    'create-middlename': { label: 'Middle Name', required: false, check: nameErrors },
    'create-lastname': { label: 'Last Name', required: true, check: nameErrors }
};

function usernameErrors(value) {
    if (/\s/.test(value)) return ['Spaces are not allowed'];
    if (!/^[a-zA-Z0-9._@!#$*-]*$/.test(value)) return ['Only letters, numbers and . _ - @ ! # $ * are allowed'];
    if (value.length < 3 || value.length > 20) return ['Must be 3-20 characters long'];
    return [];
}

function nameErrors(value) {
    const errors = [];
    const trimmed = value.trim();
    if (/[^A-Za-z0-9\s]/.test(trimmed)) errors.push('Special characters are not allowed');
    if (/\d/.test(trimmed)) errors.push('Numbers are not allowed');
    if (/\s{2,}/.test(value)) errors.push('Double spaces are not allowed');
    if (/([a-z])\1\1/i.test(trimmed)) errors.push('Three identical letters in a row are not allowed');
    if (/^[A-Z\s]+$/.test(trimmed)) errors.push('All capital letters are not allowed');
    if (!/^[A-Z]/.test(trimmed)) errors.push('Must start with a capital letter');
    if (!errors.length && trimmed.split(/\s+/).some(word => !/^[A-Z][a-z]*$/.test(word))) {
        errors.push('Each word must start with a capital letter followed by lowercase letters');
    }
    return errors;
}

// Validates one field, marks it red (with the reasons underneath) or green, returns true when valid
function validateCreateField(id) {
    const input = document.getElementById(id);
    const rule = CREATE_ACCOUNT_RULES[id];
    if (!input || !rule) return true;

    const value = input.value;
    let errors = [];
    if (value.trim() === '') {
        if (rule.required) errors = [`${rule.label} is required`];
    } else {
        errors = rule.check(value);
    }

    const field = input.closest('.form-field');
    let msg = field.querySelector('.field-error-msg');
    if (!msg) {
        msg = document.createElement('div');
        msg.className = 'field-error-msg';
        field.appendChild(msg);
    }
    msg.innerHTML = errors.map(err => `<span><i class="fas fa-exclamation-circle"></i> ${escapeHtml(err)}</span>`).join('');
    msg.style.display = errors.length ? 'flex' : 'none';
    input.classList.toggle('is-invalid', errors.length > 0);
    input.classList.toggle('is-valid', errors.length === 0 && value.trim() !== '');

    return errors.length === 0;
}

document.addEventListener('DOMContentLoaded', () => {
    Object.keys(CREATE_ACCOUNT_RULES).forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('input', () => validateCreateField(id));
        input.addEventListener('blur', () => validateCreateField(id));
    });
});

function showAccountCreatedDialog(account, result) {
    const fullName = [account.first_name, account.middle_name, account.last_name, account.extension_name]
        .map(part => (part || '').trim())
        .filter(Boolean)
        .join(' ');

    const details = document.createElement('dl');
    details.className = 'bh-alert-details';
    [['Username', '@' + account.username], ['Email', account.email], ['Role', account.role_label]].forEach(([term, value]) => {
        const dt = document.createElement('dt');
        dt.textContent = term;
        const dd = document.createElement('dd');
        dd.textContent = value;
        details.append(dt, dd);
    });

    const content = [createCopyField('Account ID', result.id_number), details];
    if (result.temp_password) {
        content.push(
            createAlertNote('warning', 'fa-triangle-exclamation', 'The welcome email could not be sent',
                "Share this temporary password with the user yourself. It won't be shown again."),
            createCopyField('Temporary password', result.temp_password, 'secret')
        );
    } else {
        content.push(createAlertNote('info', 'fa-envelope', null,
            "A temporary password is being emailed to them. They'll choose their own password the first time they sign in."));
    }
    if (result.self_demoted) {
        content.push(createAlertNote('danger', 'fa-user-lock', 'You will be signed out',
            'Only one Super Admin can be active at a time, so your account is now on standby. The new Super Admin can hand access back to you.'));
    }

    const actions = result.self_demoted
        ? [{ label: 'Sign out', value: 'logout', variant: 'primary', icon: 'fa-right-from-bracket', autofocus: true }]
        : [
            { label: 'View accounts', value: 'accounts', variant: 'secondary' },
            { label: 'Create another', value: 'another', variant: 'primary', icon: 'fa-user-plus', autofocus: true }
        ];

    return showAdminDialog({
        tone: 'success',
        icon: 'fa-circle-check',
        title: 'Account created',
        message: `${fullName || account.username}'s account is ready.`,
        content,
        actions,
        // A one-time password must not vanish on a stray Esc press
        dismissible: !result.temp_password && !result.self_demoted
    });
}

// Super Admin Create Account Form Submit
async function submitCreateAccountForm(e) {
    e.preventDefault();

    const invalidIds = Object.keys(CREATE_ACCOUNT_RULES).filter(id => !validateCreateField(id));
    if (invalidIds.length) {
        showToast('Please fix the fields marked in red before creating the account.', 'error');
        document.getElementById(invalidIds[0]).focus();
        return;
    }

    const roleSelect = document.getElementById('create-role');
    if (roleSelect.value === 'superadmin') {
        const newUsername = document.getElementById('create-username').value.trim();
        const choice = await showAdminDialog({
            tone: 'warning',
            icon: 'fa-user-shield',
            title: 'Create a new Super Admin?',
            message: `@${newUsername} will become the active Super Admin. Your account will go on standby and you'll be signed out. Only one Super Admin can be active at a time, so @${newUsername} can hand access back to you later.`,
            actions: [
                { label: 'Cancel', value: 'cancel', variant: 'secondary', autofocus: true },
                { label: 'Create and sign out', value: 'confirm', variant: 'danger' }
            ]
        });
        if (choice !== 'confirm') return;
    }

    const csrfToken = document.getElementById('csrf_token_val').value;

    const privKeys = [
        'can_approve_users', 'can_block_users', 'can_update_info', 'can_manage_roles', 'can_create_accounts',
        'can_delete_users', 'can_manage_requests', 'can_give_privileges',
        'can_view_reports', 'can_export_logs',
        'can_manage_classes', 'can_manage_bookings'
    ];

    const privileges = {};
    privKeys.forEach(k => {
        const el = document.getElementById('create_priv_' + k);
        if (el) {
            privileges[k] = el.checked;
        }
    });

    const payload = {
        action: 'create_account',
        csrf_token: csrfToken,
        username: document.getElementById('create-username').value,
        first_name: document.getElementById('create-firstname').value,
        middle_name: document.getElementById('create-middlename').value,
        last_name: document.getElementById('create-lastname').value,
        extension_name: document.getElementById('create-extension').value,
        birthdate: document.getElementById('create-birthdate').value,
        email: document.getElementById('create-email').value,
        sex: document.getElementById('create-sex').value,
        purok_street: document.getElementById('create-purok').value,
        barangay: document.getElementById('create-barangay').value,
        city_municipality: document.getElementById('create-city').value,
        province: document.getElementById('create-province').value,
        zip_code: document.getElementById('create-zip').value,
        role: document.getElementById('create-role').value,
        status: document.getElementById('create-status').value,
        privileges: privileges
    };

    fetch(getAdminApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(async data => {
        if (data.success) {
            const roleLabel = roleSelect.options[roleSelect.selectedIndex].text;
            document.getElementById('form-create-account').reset();
            // The page reloads afterwards, so the new ID stays on screen until the admin dismisses it
            const choice = await showAccountCreatedDialog({ ...payload, role_label: roleLabel }, data);
            if (data.self_demoted) {
                window.location.href = 'logout.php';
            } else if (choice === 'accounts') {
                window.location.href = 'dashboard.php';
            } else {
                window.location.reload();
            }
        } else {
            showToast(data.message, 'error');
        }
    });
}

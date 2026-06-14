        let authToken = sessionStorage.getItem('nexus_token') || "";
        let currentUserRole = parseInt(sessionStorage.getItem('nexus_role')) || 1;
        let allTickets = [];
        let currentTicketId = null;
        let statusUptimeInterval = null;
        let autoRefreshInterval = null;
        let heartbeatInterval = null;
        let usersRefreshInterval = null;
        let statusStartTs = null;


        document.addEventListener('click', () => {
            document.querySelectorAll('.inline-select-dropdown.open').forEach(d => {
                d.classList.remove('open');
                const row = d.closest('tr');
                if (row) row.classList.remove('has-open-dropdown');
            });
            document.querySelectorAll('.custom-select-container.open').forEach(c => {
                c.classList.remove('open');
            });
        });

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function syncCustomSelect(select) {
            if (typeof select === 'string') select = document.getElementById(select);
            if (!select) return;
            const container = select.nextElementSibling;
            if (!container || !container.classList.contains('custom-select-container')) return;
            const trigger = container.querySelector('.custom-select-trigger');
            const selectedOption = select.options[select.selectedIndex];

            const colorsMap = {
                'low': 'var(--text-muted)',
                'medium': 'var(--accent)',
                'high': 'var(--danger)',
                'open': 'var(--danger)',
                'in_progress': 'var(--warning)',
                'closed': 'var(--success)',
            };

            if (selectedOption && trigger) {
                trigger.innerText = selectedOption.text;
                const color = colorsMap[selectedOption.value];
                if (color) {
                    trigger.style.color = color;
                    trigger.style.fontWeight = '600';
                } else {
                    trigger.style.color = '';
                    trigger.style.fontWeight = '';
                }
            }
            const dropdown = container.querySelector('.custom-select-dropdown');
            if (dropdown) {
                dropdown.innerHTML = '';
                Array.from(select.options).forEach(opt => {
                    const div = document.createElement('div');
                    div.className = 'custom-select-option';
                    div.innerText = opt.text;
                    div.dataset.value = opt.value;

                    const color = colorsMap[opt.value];
                    if (color) {
                        div.style.color = color;
                        div.style.fontWeight = '600';
                    }

                    div.addEventListener('click', (e) => {
                        e.stopPropagation();
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change'));
                        trigger.innerText = opt.text;
                        if (color) {
                            trigger.style.color = color;
                            trigger.style.fontWeight = '600';
                        } else {
                            trigger.style.color = '';
                            trigger.style.fontWeight = '';
                        }
                        container.classList.remove('open');
                    });
                    dropdown.appendChild(div);
                });
            }
        }

        function convertSelectToCustom(select) {
            if (typeof select === 'string') select = document.getElementById(select);
            if (!select) return;
            if (select.dataset.converted === 'true') {
                syncCustomSelect(select);
                return;
            }
            select.style.display = 'none';
            select.dataset.converted = 'true';

            const container = document.createElement('div');
            container.className = 'custom-select-container';

            const trigger = document.createElement('div');
            trigger.className = 'custom-select-trigger';

            const dropdown = document.createElement('div');
            dropdown.className = 'custom-select-dropdown';

            container.appendChild(trigger);
            container.appendChild(dropdown);
            select.parentNode.insertBefore(container, select.nextSibling);

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.custom-select-container.open').forEach(c => {
                    if (c !== container) c.classList.remove('open');
                });
                container.classList.toggle('open');
            });

            syncCustomSelect(select);
        }

        function setBtnRefreshing(btn) {
            if (!btn || btn.tagName !== 'BUTTON') return null;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerText = 'Refreshing...';
            return orig;
        }

        function restoreBtnRefreshing(btn, orig) {
            if (orig) {
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }, 400);
            }
        }


        let settings = {
            darkMode: true,
            warnAt: 1,
            dangerAt: 3,
            autoRefresh: true,
            refreshInterval: 5,
        };


        function getSettingsKey() {
            return 'nexus_settings_' + (sessionStorage.getItem('nexus_user_id') || 'guest');
        }

        function loadSettingsUI() {
            const s = JSON.parse(localStorage.getItem(getSettingsKey()) || '{}');
            settings = Object.assign(settings, s);
            document.getElementById('settingDarkMode').checked = settings.darkMode;
            document.getElementById('settingAutoRefresh').checked = settings.autoRefresh;
            document.getElementById('settingRefreshInterval').value = settings.refreshInterval;
            if (document.getElementById('settingWarnAt')) document.getElementById('settingWarnAt').value = settings.warnAt;
            if (document.getElementById('settingDangerAt')) document.getElementById('settingDangerAt').value = settings.dangerAt;
            onAutoRefreshToggle(true);
            const btn = document.getElementById('saveSettingsBtn');
            if (btn) btn.style.display = 'none';
        }

        function markSettingsDirty() {
            const btn = document.getElementById('saveSettingsBtn');
            if (btn) btn.style.display = '';
        }

        function onAutoRefreshToggle(silent = false) {
            const isOn = document.getElementById('settingAutoRefresh').checked;
            const row = document.getElementById('refreshIntervalRow');
            row.style.opacity = isOn ? '1' : '0.4';
            row.style.pointerEvents = isOn ? 'auto' : 'none';
            if (!silent) {
                settings.autoRefresh = isOn;
                const s = JSON.parse(localStorage.getItem(getSettingsKey()) || '{}');
                Object.assign(s, { autoRefresh: isOn });
                localStorage.setItem(getSettingsKey(), JSON.stringify(s));
                applySettings();
            }
        }

        function saveSettings() {
            settings.darkMode = document.getElementById('settingDarkMode').checked;
            settings.autoRefresh = document.getElementById('settingAutoRefresh').checked;
            settings.refreshInterval = parseInt(document.getElementById('settingRefreshInterval').value) || 5;
            if (document.getElementById('settingWarnAt')) settings.warnAt = parseInt(document.getElementById('settingWarnAt').value) || 1;
            if (document.getElementById('settingDangerAt')) settings.dangerAt = parseInt(document.getElementById('settingDangerAt').value) || 3;
            localStorage.setItem(getSettingsKey(), JSON.stringify(settings));
            applySettings();
            const btn = document.getElementById('saveSettingsBtn');
            btn.innerHTML = '✓ Saved';
            setTimeout(() => { btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Save Changes'; btn.style.display = 'none'; }, 1500);
        }

        function applySettings() {

            document.documentElement.setAttribute('data-theme', settings.darkMode ? 'dark' : 'light');
            const icon = document.getElementById('themeIcon');
            if (icon) {
                if (settings.darkMode) {
                    icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
                } else {
                    icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
                }
            }

            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            if (settings.autoRefresh && authToken) {
                autoRefreshInterval = setInterval(loadTickets, (settings.refreshInterval || 5) * 60 * 1000);
            }
        }

        function toggleTheme() {
            const s = JSON.parse(localStorage.getItem(getSettingsKey()) || '{}');
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            settings.darkMode = !isDark;
            Object.assign(s, { darkMode: settings.darkMode });
            localStorage.setItem(getSettingsKey(), JSON.stringify(s));
            applySettings();
            const chk = document.getElementById('settingDarkMode');
            if (chk) chk.checked = settings.darkMode;
        }

        function updatePendingBadge(count) {
            const badge = document.getElementById('pendingCount');
            if (!badge) return;
            badge.innerText = count;
            const warn = settings.warnAt || 3;
            const danger = settings.dangerAt || 6;
            if (count === 0) {
                badge.style.background = 'rgba(16,185,129,0.15)';
                badge.style.color = 'var(--success)';
                badge.style.borderColor = 'rgba(16,185,129,0.3)';
            } else if (count < warn) {
                badge.style.background = 'rgba(16,185,129,0.15)';
                badge.style.color = 'var(--success)';
                badge.style.borderColor = 'rgba(16,185,129,0.3)';
            } else if (count < danger) {
                badge.style.background = 'rgba(245,158,11,0.15)';
                badge.style.color = 'var(--warning)';
                badge.style.borderColor = 'rgba(245,158,11,0.3)';
            } else {
                badge.style.background = 'rgba(239,68,68,0.15)';
                badge.style.color = 'var(--danger)';
                badge.style.borderColor = 'rgba(239,68,68,0.3)';
            }
        }


        (function () {
            const s = JSON.parse(localStorage.getItem(getSettingsKey()) || '{}');
            settings = Object.assign(settings, s);
            applySettings();
        })();


        function toggleProfileMenu() {
            const dd = document.getElementById('profileDropdown');
            dd.classList.toggle('open');
        }

        function updateProfileDropdown(name, email, role) {
            const monogram = name ? name.substring(0, 2).toUpperCase() : '??';
            const roleLabel = role === 3 ? 'Admin' : (role === 2 ? 'IT Support' : 'Employee');
            document.getElementById('profileDropdownAvatar').innerText = monogram;
            document.getElementById('profileDropdownName').innerText = name || '—';
            document.getElementById('profileDropdownEmail').innerText = email || '—';
            document.getElementById('profileDropdownRole').innerText = roleLabel;
        }


        document.addEventListener('click', (e) => {
            const trigger = document.getElementById('profileTrigger');
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown && !trigger?.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });


        function openChangePasswordModal() {
            document.getElementById('pwCurrent').value = '';
            document.getElementById('pwNew').value = '';
            document.getElementById('pwConfirm').value = '';
            document.getElementById('pwError').style.display = 'none';
            document.getElementById('pwSuccess').style.display = 'none';
            const overlay = document.getElementById('changePasswordOverlay');
            overlay.style.display = 'flex';
            setTimeout(() => overlay.style.opacity = '1', 10);
        }

        function closeChangePasswordModal() {
            const overlay = document.getElementById('changePasswordOverlay');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        async function changePassword() {
            const current = document.getElementById('pwCurrent').value;
            const newPw = document.getElementById('pwNew').value;
            const confirm = document.getElementById('pwConfirm').value;
            const errEl = document.getElementById('pwError');
            const successEl = document.getElementById('pwSuccess');
            const btn = document.getElementById('changePwBtn');

            errEl.style.display = 'none';
            successEl.style.display = 'none';

            if (!current || !newPw || !confirm) {
                errEl.innerText = 'All fields are required.'; errEl.style.display = 'block'; return;
            }
            if (newPw !== confirm) {
                errEl.innerText = 'New passwords do not match.'; errEl.style.display = 'block'; return;
            }
            if (newPw.length < 8) {
                errEl.innerText = 'Password must be at least 8 characters.'; errEl.style.display = 'block'; return;
            }

            btn.disabled = true; btn.innerText = 'Saving...';

            try {
                const res = await fetch('/api/auth/change-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ current_password: current, password: newPw, password_confirmation: confirm })
                });
                const data = await res.json();
                if (res.ok) {
                    successEl.innerText = 'Password changed successfully!';
                    successEl.style.display = 'block';
                    document.getElementById('pwCurrent').value = '';
                    document.getElementById('pwNew').value = '';
                    document.getElementById('pwConfirm').value = '';
                    setTimeout(() => closeChangePasswordModal(), 1800);
                } else {
                    const firstErr = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Failed to change password.');
                    errEl.innerText = firstErr; errEl.style.display = 'block';
                }
            } catch (e) {
                errEl.innerText = 'Connection error.'; errEl.style.display = 'block';
            } finally {
                btn.disabled = false; btn.innerText = 'Save Password';
            }
        }

        function copyDemoText(el) {
            const text = el.dataset.copy;
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                el.innerText = 'Copied!';
                el.style.color = 'var(--success)';
                setTimeout(() => {
                    el.innerText = text;
                    el.style.color = '';
                }, 1000);
            });
        }

        function showAuthTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('authLogin').style.display = isLogin ? 'block' : 'none';
            document.getElementById('authRegister').style.display = isLogin ? 'none' : 'block';
            document.getElementById('tabBtnLogin').style.background = isLogin ? 'var(--accent)' : 'transparent';
            document.getElementById('tabBtnLogin').style.color = isLogin ? 'white' : 'var(--text-muted)';
            document.getElementById('tabBtnRegister').style.background = isLogin ? 'transparent' : 'var(--accent)';
            document.getElementById('tabBtnRegister').style.color = isLogin ? 'var(--text-muted)' : 'white';
            document.getElementById('loginError').style.display = 'none';
            document.getElementById('registerMsg').style.display = 'none';
        }

        async function registerUser(event) {
            event.preventDefault();
            const name = document.getElementById('regName').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const password = document.getElementById('regPassword').value;
            const msg = document.getElementById('registerMsg');
            const btn = document.querySelector('#authRegister .btn-primary');

            btn.disabled = true;
            btn.innerText = 'Creating...';
            msg.style.display = 'none';

            try {
                const response = await fetch('/api/auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ name, email, password })
                });
                const data = await response.json();

                if (response.ok) {
                    msg.style.color = 'var(--warning, #f59e0b)';
                    msg.innerText = 'Registration received! Your account is awaiting admin approval before you can sign in.';
                    msg.style.display = 'block';
                    document.getElementById('regName').value = '';
                    document.getElementById('regEmail').value = '';
                    document.getElementById('regPassword').value = '';

                } else {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Registration failed.');
                    msg.style.color = 'var(--danger)';
                    msg.innerText = firstError;
                    msg.style.display = 'block';
                }
            } catch (e) {
                msg.style.color = 'var(--danger)';
                msg.innerText = 'Connection error.';
                msg.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerText = 'Create Account';
            }
        }

        function applyNavVisibility() {
            document.getElementById('nav-settings').style.display = 'flex';
            if (currentUserRole >= 2) {
                document.getElementById('nav-status').style.display = 'flex';
            } else {
                document.getElementById('nav-status').style.display = 'none';
            }
            if (currentUserRole === 3) {
                document.getElementById('nav-users').style.display = 'flex';
                const adminSec = document.getElementById('adminOnlySettings');
                if (adminSec) adminSec.style.display = 'block';
                const cacheBtn = document.getElementById('clearCacheBtn');
                if (cacheBtn) cacheBtn.style.display = 'inline-flex';
            } else {
                document.getElementById('nav-users').style.display = 'none';
                const adminSec = document.getElementById('adminOnlySettings');
                if (adminSec) adminSec.style.display = 'none';
                const cacheBtn = document.getElementById('clearCacheBtn');
                if (cacheBtn) cacheBtn.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {

            ['newUserRole', 'ticketCategory', 'updateStatus', 'updatePriority'].forEach(convertSelectToCustom);

            if (authToken) {
                const userName = sessionStorage.getItem('nexus_name') || 'Agent';
                const userEmail = sessionStorage.getItem('nexus_email') || '';
                document.getElementById('userName').innerText = userName;
                document.getElementById('userRoleBadge').innerText = currentUserRole === 3 ? 'ADMIN' : (currentUserRole === 2 ? 'IT SUPPORT' : 'EMPLOYEE');
                document.getElementById('userAvatar').innerText = userName.substring(0, 2).toUpperCase();
                updateProfileDropdown(userName, userEmail, currentUserRole);
                applyNavVisibility();
                document.getElementById('loginOverlay').style.display = 'none';
                const savedTab = localStorage.getItem('nexus_tab') || 'incident';
                const adminOnly = ['status', 'users'];
                const tabToLoad = (adminOnly.includes(savedTab) && currentUserRole !== 3) ? 'incident' : savedTab;
                switchTab(tabToLoad);
            }
        });

        async function authenticate(event) {
            if (event) event.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const btn = document.querySelector('#loginOverlay .btn-primary');

            if (!email || !password) return;

            btn.innerText = "Authenticating...";
            document.getElementById('loginError').style.display = 'none';

            try {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ email, password })
                });
                const data = await response.json();

                if (response.ok && data.access_token) {
                    authToken = data.access_token;
                    currentUserRole = data.user.role_id;
                    sessionStorage.setItem('nexus_token', authToken);
                    sessionStorage.setItem('nexus_role', currentUserRole);
                    sessionStorage.setItem('nexus_name', data.user.name);
                    sessionStorage.setItem('nexus_email', data.user.email);
                    sessionStorage.setItem('nexus_user_id', data.user.id);
                    document.getElementById('userName').innerText = data.user.name;
                    document.getElementById('userRoleBadge').innerText = currentUserRole === 3 ? 'ADMIN' : (currentUserRole === 2 ? 'IT SUPPORT' : 'EMPLOYEE');
                    document.getElementById('userAvatar').innerText = data.user.name.substring(0, 2).toUpperCase();
                    updateProfileDropdown(data.user.name, data.user.email, currentUserRole);
                    applyNavVisibility();

                    const overlay = document.getElementById('loginOverlay');
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);

                    switchTab('incident');
                    startHeartbeat();
                    loadSettingsUI();
                } else {
                    document.getElementById('loginError').innerText = data.message || "Authentication failed.";
                    document.getElementById('loginError').style.display = 'block';
                    btn.innerText = "Sign In";
                }
            } catch (error) {
                document.getElementById('loginError').innerText = "Connection error. Ensure the server is running.";
                document.getElementById('loginError').style.display = 'block';
                btn.innerText = "Sign In";
            }
        }

        async function logout() {
            if (!authToken) return;
            try {
                await fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                });
            } catch (e) { }

            authToken = "";
            sessionStorage.removeItem('nexus_token');
            sessionStorage.removeItem('nexus_role');
            sessionStorage.removeItem('nexus_name');
            sessionStorage.removeItem('nexus_email');
            sessionStorage.removeItem('nexus_user_id');

            const emptyRow = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No data loaded.</td></tr>';
            document.getElementById('ticketTableBody').innerHTML = emptyRow;
            document.getElementById('requestTableBody').innerHTML = emptyRow;
            document.getElementById('pendingTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No pending registrations.</td></tr>';
            document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">Loading...</td></tr>';
            updatePendingBadge(0);
            document.getElementById('nav-users').style.display = 'none';
            document.getElementById('nav-settings').style.display = 'none';


            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            showAuthTab('login');


            if (statusUptimeInterval) { clearInterval(statusUptimeInterval); statusUptimeInterval = null; }
            if (usersRefreshInterval) { clearInterval(usersRefreshInterval); usersRefreshInterval = null; }
            stopHeartbeat();

            const overlay = document.getElementById('loginOverlay');
            overlay.style.display = 'flex';
            setTimeout(() => overlay.style.opacity = '1', 10);
            document.querySelector('#loginOverlay .btn-primary').innerText = "Sign In";
        }

        function getStatusBadge(status) {
            if (status === 'open') return '<span class="badge badge-open">OPEN</span>';
            if (status === 'in_progress') return '<span class="badge badge-progress">IN PROGRESS</span>';
            return '<span class="badge badge-closed">CLOSED</span>';
        }

        function getPriorityBadge(priority) {
            if (priority === 'high') return '<span class="badge badge-high">HIGH</span>';
            if (priority === 'medium') return '<span class="badge badge-medium">MEDIUM</span>';
            return '<span class="badge badge-low">LOW</span>';
        }

        function getPrioritySort(priority) {
            if (priority === 'high') return 3;
            if (priority === 'medium') return 2;
            return 1;
        }

        function getStatusSort(status) {
            if (status === 'open') return 1;
            if (status === 'in_progress') return 2;
            return 3;
        }

        let sortDirections = {};

        const _thLabels = new WeakMap();
        function sortTable(th) {
            const tr = th.parentElement;
            const tbody = tr.parentElement.parentElement.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length <= 1) return;
            if (rows.length === 1 && rows[0].cells.length === 1) return;

            const colIndex = Array.from(tr.children).indexOf(th);
            const tableId = tbody.id || 'table_' + Math.random();
            tbody.id = tableId;

            const key = `${tableId}_${colIndex}`;
            const asc = !sortDirections[key];
            sortDirections[key] = asc;


            tr.querySelectorAll('th').forEach(h => {
                if (!_thLabels.has(h)) _thLabels.set(h, h.innerText.trim());
                h.dataset.sortArrow = '';
                h.innerText = _thLabels.get(h);
            });
            th.dataset.sortArrow = asc ? ' ▲' : ' ▼';
            th.innerText = _thLabels.get(th) + (asc ? ' ▲' : ' ▼');

            rows.sort((a, b) => {
                const cellA = a.cells[colIndex];
                const cellB = b.cells[colIndex];

                const valA = cellA.hasAttribute('data-sort') ? cellA.getAttribute('data-sort') : cellA.innerText.trim();
                const valB = cellB.hasAttribute('data-sort') ? cellB.getAttribute('data-sort') : cellB.innerText.trim();

                const numA = parseFloat(valA);
                const numB = parseFloat(valB);

                if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '' && !isNaN(valA) && !isNaN(valB)) {
                    return asc ? numA - numB : numB - numA;
                }
                return asc ? String(valA).localeCompare(String(valB)) : String(valB).localeCompare(String(valA));
            });

            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }

        function switchTab(tabId) {
            if (typeof closeMobileSidebar === 'function') closeMobileSidebar();
            ['incident', 'service', 'status', 'users', 'settings'].forEach(id => {
                document.getElementById('tab-' + id).style.display = 'none';
                document.getElementById('nav-' + id)?.classList.remove('active');
            });

            document.getElementById('tab-' + tabId).style.display = 'block';
            document.getElementById('nav-' + tabId)?.classList.add('active');


            localStorage.setItem('nexus_tab', tabId);

            if (statusUptimeInterval) { clearInterval(statusUptimeInterval); statusUptimeInterval = null; }
            if (usersRefreshInterval) { clearInterval(usersRefreshInterval); usersRefreshInterval = null; }

            if (tabId === 'status') {
                loadSystemStatus();
                statusUptimeInterval = setInterval(tickUptime, 1000);
            } else if (tabId === 'users') {
                loadUsers();

                usersRefreshInterval = setInterval(() => { if (authToken) loadUsers(); }, 30_000);
            } else if (tabId === 'settings') {
                loadSettingsUI();
            } else if (tabId === 'incident' || tabId === 'service') {
                loadTickets();
            }
        }

        function startHeartbeat() {
            if (heartbeatInterval) return;

            sendHeartbeat();
            heartbeatInterval = setInterval(sendHeartbeat, 60_000);
        }

        function stopHeartbeat() {
            if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }
        }

        function getLocalIP() {
            return new Promise((resolve) => {
                const ips = [];
                const pc = new RTCPeerConnection({
                    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
                });
                pc.createDataChannel('');
                pc.createOffer().then(o => pc.setLocalDescription(o));
                pc.onicecandidate = (e) => {
                    if (e && e.candidate && e.candidate.candidate) {
                        const m = e.candidate.candidate.match(/([0-9]{1,3}(\.[0-9]{1,3}){3})/);
                        if (m) ips.push(m[1]);
                    } else {
                        pc.close();
                        const real = ips.find(ip => !ip.startsWith('127.') && !ip.startsWith('169.254.'));
                        resolve(real || null);
                    }
                };
                setTimeout(() => {
                    pc.close();
                    const real = ips.find(ip => !ip.startsWith('127.') && !ip.startsWith('169.254.'));
                    resolve(real || null);
                }, 3000);
            });
        }

        async function sendHeartbeat() {
            if (!authToken) return;
            try {
                const ip = await getLocalIP();
                await fetch('/api/auth/heartbeat', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json',
                        ...(ip ? { 'Content-Type': 'application/json' } : {})
                    },
                    ...(ip ? { body: JSON.stringify({ ip }) } : {})
                });
            } catch (_) { }
        }

        function updateServerLed(isOnline, text = null) {
            const color = isOnline ? 'var(--success)' : 'var(--danger)';
            const led1 = document.getElementById('serverLedIcon');
            const txt1 = document.getElementById('serverLedText');
            const led2 = document.getElementById('topbarLedIcon');
            const txt2 = document.getElementById('topbarLedText');

            if (led1) {
                led1.style.background = color;
                led1.style.boxShadow = `0 0 12px ${color}`;
            }
            if (txt1 && text) {
                txt1.innerText = text;
                txt1.style.color = isOnline ? 'var(--text-main)' : 'var(--danger)';
            }

            if (led2) {
                led2.style.background = color;
                led2.style.boxShadow = `0 0 8px ${color}`;
            }
            if (txt2) {
                txt2.innerText = isOnline ? 'API Connected' : 'API Unreachable';
            }
        }

        function tickUptime() {
            if (statusStartTs === null) return;
            const elapsed = Math.floor(Date.now() / 1000) - statusStartTs;
            const h = Math.floor(elapsed / 3600);
            const m = Math.floor((elapsed % 3600) / 60);
            const s = elapsed % 60;
            const pad = n => String(n).padStart(2, '0');
            document.getElementById('statTime').innerText = `${pad(h)}:${pad(m)}:${pad(s)}`;
        }

        async function loadSystemStatus(btn = null) {
            const origBtn = setBtnRefreshing(btn);

            document.getElementById('statLaravel').innerText = 'Loading...';
            document.getElementById('statPhp').innerText = 'Loading...';
            document.getElementById('statDb').innerText = 'Loading...';
            document.getElementById('statDb').style.color = 'var(--text-muted)';
            document.getElementById('statMem').innerText = 'Loading...';
            document.getElementById('statTime').innerText = 'Loading...';

            updateServerLed(true, 'Loading...');

            try {
                const response = await fetch('/api/status', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                });

                if (response.status === 401) {
                    logout();
                    return;
                }

                const json = await response.json();
                const data = json.data || json;
                document.getElementById('statLaravel').innerText = 'Laravel v' + data.laravel_version;
                document.getElementById('statPhp').innerText = 'PHP v' + data.php_version;

                const dbStatus = data.database || 'Unknown';
                const dbEl = document.getElementById('statDb');
                dbEl.innerText = dbStatus;
                dbEl.style.color = dbStatus.toLowerCase() === 'online' ? 'var(--success)' : 'var(--danger)';

                document.getElementById('statMem').innerText = data.memory_usage_mb + ' MB';

                if (data.start_timestamp) {
                    statusStartTs = data.start_timestamp;
                } else {

                    statusStartTs = Math.floor(Date.now() / 1000);
                }
                tickUptime();

                updateServerLed(true, 'API Connected');

            } catch (e) {
                document.getElementById('statLaravel').innerText = 'Error loading metrics';
                document.getElementById('statDb').innerText = 'Unknown';
                document.getElementById('statDb').style.color = 'var(--danger)';
                updateServerLed(false, 'API Unreachable');
            } finally {
                restoreBtnRefreshing(btn, origBtn);
            }
        }

        async function loadTickets(btn = null) {
            const origBtn = setBtnRefreshing(btn);
            const tbody = document.getElementById('ticketTableBody');
            const rtbody = document.getElementById('requestTableBody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">Loading data from secure API...</td></tr>';
            if (rtbody) rtbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">Loading data from secure API...</td></tr>';

            try {
                const response = await fetch('/api/tickets', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                if (response.status === 401) { logout(); return; }

                const json = await response.json();
                allTickets = json.data || json;


                allTickets.sort((a, b) => {
                    const pa = getPrioritySort(a.priority);
                    const pb = getPrioritySort(b.priority);
                    if (pa !== pb) return pb - pa;
                    return new Date(a.created_at) - new Date(b.created_at);
                });


                const INCIDENT_CATEGORIES = ['Hardware Issue', 'Network/Internet'];
                const incidents = allTickets.filter(t => t.category && INCIDENT_CATEGORIES.includes(t.category.name));
                const requests = allTickets.filter(t => !t.category || !INCIDENT_CATEGORIES.includes(t.category.name));

                if (tbody) {
                    if (incidents.length === 0) tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No active incidents found.</td></tr>';
                    else {
                        tbody.innerHTML = '';
                        incidents.forEach(ticket => tbody.appendChild(createTicketRow(ticket, 'TIC')));
                    }
                }

                if (rtbody) {
                    if (requests.length === 0) rtbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No active requests found.</td></tr>';
                    else {
                        rtbody.innerHTML = '';
                        requests.forEach(ticket => rtbody.appendChild(createTicketRow(ticket, 'REQ')));
                    }
                }
            } catch (error) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--danger); padding: 40px;">API Connection Error.</td></tr>';
                if (rtbody) rtbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--danger); padding: 40px;">API Connection Error.</td></tr>';
            } finally {
                restoreBtnRefreshing(btn, origBtn);
            }
        }

        function createTicketRow(ticket, prefix) {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.onclick = (e) => {
                const selection = window.getSelection();
                if (selection && selection.toString().trim() !== '') return;
                openDetails(ticket.id, prefix);
            };
            const dateStr = ticket.created_at
                ? new Date(ticket.created_at).toLocaleDateString('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' })
                : '-';
            const title = escapeHtml(ticket.title || '-');
            const categoryName = escapeHtml(ticket.category ? ticket.category.name : '-');
            const requesterName = escapeHtml(ticket.user ? ticket.user.name : '-');
            tr.innerHTML = `
                <td style="font-weight: 500; font-family: monospace; color: var(--text-muted);" data-sort="${ticket.id}">#${prefix}-${ticket.id.toString().padStart(4, '0')}</td>
                <td style="font-weight: 500;" data-sort="${title}">${title}</td>
                <td style="color: var(--text-muted);" data-sort="${categoryName}">${categoryName}</td>
                <td data-sort="${getPrioritySort(ticket.priority)}">${getPriorityBadge(ticket.priority)}</td>
                <td data-sort="${getStatusSort(ticket.status)}">${getStatusBadge(ticket.status)}</td>
                <td style="color: var(--text-muted);" data-sort="${requesterName}">${requesterName}</td>
                <td style="color: var(--text-muted); white-space: nowrap;" data-sort="${ticket.created_at}">${dateStr}</td>
            `;
            return tr;
        }

        function showNewTicketModal(isReq = false) {
            document.getElementById('ticketTitle').value = '';
            document.getElementById('ticketDesc').value = '';

            const cat = document.getElementById('ticketCategory');
            cat.innerHTML = isReq
                ? '<option value="2">Software / License</option><option value="4">Access / Accounts</option>'
                : '<option value="1">Hardware Issue</option><option value="3">Network / Connectivity</option><option value="4">Other</option>';
            syncCustomSelect(cat);

            document.getElementById('ticketModalTitle').innerText = isReq ? 'Create New Request' : 'Create New Ticket';
            document.getElementById('ticketModalSubtitle').innerText = isReq
                ? 'Submit a service request for software, access, or equipment.'
                : 'Please provide details about the incident.';
            document.getElementById('ticketSubmitBtn').innerText = isReq ? 'Submit Request' : 'Submit Ticket';

            const overlay = document.getElementById('ticketOverlay');
            overlay.style.display = 'flex';
            setTimeout(() => overlay.style.opacity = '1', 10);
        }

        function closeNewTicketModal() {
            const overlay = document.getElementById('ticketOverlay');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        async function submitTicket() {
            const title = document.getElementById('ticketTitle').value;
            const description = document.getElementById('ticketDesc').value;
            const category_id = parseInt(document.getElementById('ticketCategory').value);

            if (!title || !description) return;

            try {
                const response = await fetch('/api/tickets', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ title, description, category_id, priority: 'medium' })
                });

                if (response.ok) {
                    closeNewTicketModal();
                    loadTickets();
                } else {
                    const errorData = await response.json();
                    alert("Validation Error: " + (errorData.message || 'Helytelen adatok.'));
                }
            } catch (error) {
                alert("Connection error.");
            }
        }

        function openDetails(id, prefix = 'INC') {
            const ticket = allTickets.find(t => t.id === id);
            if (!ticket) return;
            currentTicketId = id;

            document.getElementById('detailId').innerText = `#${prefix}-${ticket.id.toString().padStart(4, '0')}`;
            document.getElementById('detailTitle').innerText = ticket.title;
            document.getElementById('detailStatus').innerHTML = getStatusBadge(ticket.status);
            document.getElementById('detailDesc').innerText = ticket.description;
            document.getElementById('detailUser').innerText = ticket.user ? ticket.user.name : 'Unknown';
            document.getElementById('detailCategory').innerText = ticket.category ? ticket.category.name : 'Unknown';

            if (currentUserRole > 1) {
                document.getElementById('itActions').style.display = 'block';
                document.getElementById('saveBtn').style.display = 'block';
                document.getElementById('updateStatus').value = ticket.status;
                document.getElementById('updatePriority').value = ticket.priority || 'medium';
                syncCustomSelect('updateStatus');
                syncCustomSelect('updatePriority');
            } else {
                document.getElementById('itActions').style.display = 'none';
                document.getElementById('saveBtn').style.display = 'none';
            }

            if (currentUserRole === 3) {
                document.getElementById('adminActions').style.display = 'block';
            } else {
                document.getElementById('adminActions').style.display = 'none';
            }

            const overlay = document.getElementById('detailsOverlay');
            overlay.style.display = 'flex';
            setTimeout(() => overlay.style.opacity = '1', 10);
        }

        function closeDetailsModal() {
            const overlay = document.getElementById('detailsOverlay');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
            currentTicketId = null;
        }

        async function saveTicketDetails() {
            if (currentUserRole === 1 || !currentTicketId) return;

            const newStatus = document.getElementById('updateStatus').value;
            const newPriority = document.getElementById('updatePriority').value;
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerText = "Saving...";

            try {
                const response = await fetch('/api/tickets/' + currentTicketId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ status: newStatus, priority: newPriority })
                });

                if (response.status === 401) { logout(); return; }

                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.message || 'Update failed.');
                }

                closeDetailsModal();
                loadTickets();
            } catch (e) {
                alert('Error saving ticket: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = "Save Changes";
            }
        }

        async function deleteTicket() {
            if (currentUserRole !== 3 || !currentTicketId) return;

            const ticketId = currentTicketId;
            const btn = document.getElementById('adminActions').querySelector('button');

            if (btn.dataset.armed !== 'true') {
                btn.dataset.armed = 'true';
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Confirm Delete?';
                btn.style.background = 'rgba(239,68,68,0.35)';
                btn.style.fontWeight = '700';
                setTimeout(() => {
                    btn.dataset.armed = 'false';
                    btn.innerHTML = origHtml;
                    btn.style.background = 'rgba(239,68,68,0.1)';
                    btn.style.fontWeight = '';
                }, 3000);
                return;
            }


            btn.disabled = true;
            btn.innerHTML = 'Deleting...';

            try {
                const res = await fetch('/api/tickets/' + ticketId, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                if (res.ok || res.status === 204) {
                    closeDetailsModal();
                    loadTickets();
                } else {
                    const err = await res.json().catch(() => ({}));
                    alert('Delete failed: ' + (err.message || 'Status ' + res.status));
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Delete Permanently';
                }
            } catch (e) {
                alert('Network error: could not delete ticket.');
                btn.disabled = false;
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Delete Permanently';
            }
        }



        async function loadUsers(btn = null) {
            const origBtn = setBtnRefreshing(btn);

            const pendingTbody = document.getElementById('pendingTableBody');
            const usersTbody = document.getElementById('usersTableBody');
            if (pendingTbody) pendingTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">Loading data from secure API...</td></tr>';
            if (usersTbody) usersTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">Loading data from secure API...</td></tr>';

            try {
                const response = await fetch('/api/users', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                if (response.status === 401) { logout(); return; }
                const json = await response.json();
                const users = json.data || [];

                const pending = users.filter(u => !u.is_approved);
                const approved = users.filter(u => u.is_approved);

                updatePendingBadge(pending.length);

                const pendingTbody = document.getElementById('pendingTableBody');
                if (pending.length === 0) {
                    pendingTbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">No pending registrations.</td></tr>';
                } else {
                    pendingTbody.innerHTML = '';
                    pending.forEach(u => {
                        const userName = escapeHtml(u.name || '-');
                        const userEmail = escapeHtml(u.email || '-');
                        const registeredAt = u.created_at ? new Date(u.created_at).toLocaleDateString('hu-HU') : '-';
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="font-weight:500;" data-sort="${userName}">${userName}</td>
                            <td style="color:var(--text-muted);" data-sort="${userEmail}">${userEmail}</td>
                            <td style="color:var(--text-muted);" data-sort="${u.created_at || ''}">${registeredAt}</td>
                            <td></td>
                            <td style="white-space:nowrap;">
                                <button class="btn btn-secondary btn-approve" data-id="${u.id}" style="color:var(--success); border-color:rgba(16,185,129,0.4);">
                                    ✓ Approve
                                </button>
                                <button class="btn btn-secondary btn-reject" data-id="${u.id}" data-name="${userName}" style="color:var(--danger); border-color:rgba(239,68,68,0.4); margin-left:6px;">
                                    ✕ Reject
                                </button>
                            </td>`;
                        tr.querySelector('.btn-approve').addEventListener('click', (e) => approveUser(u.id));
                        tr.querySelector('.btn-reject').addEventListener('click', (e) => armButton(e.currentTarget, () => deleteUser(u.id)));
                        pendingTbody.appendChild(tr);
                    });
                }

                const usersTbody = document.getElementById('usersTableBody');
                if (approved.length === 0) {
                    usersTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No approved users.</td></tr>';
                } else {
                    usersTbody.innerHTML = '';
                    approved.forEach(u => {
                        const roleColors = {
                            'Employee': { bg: 'rgba(156,163,175,0.15)', clr: '#9ca3af' },
                            'IT Support': { bg: 'rgba(6,182,212,0.15)', clr: '#06b6d4' },
                            'Admin': { bg: 'rgba(99,102,241,0.15)', clr: '#6366f1' },
                        };
                        const roleName = u.role ? u.role.name : 'Employee';
                        const rc = roleColors[roleName] || { bg: 'rgba(100,100,100,0.15)', clr: 'white' };
                        const currentUserId = parseInt(sessionStorage.getItem('nexus_user_id')) || 0;
                        const isSelf = (u.id === currentUserId);

                        const uName = escapeHtml(u.name || '-');
                        const uEmail = escapeHtml(u.email || '-');
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="font-weight:500;" data-sort="${uName}">${uName}</td>
                            <td style="color:var(--text-muted);" data-sort="${uEmail}">${uEmail}</td>
                            <td data-sort="${u.role_id}">
                                <div class="inline-select-container">
                                    <div class="inline-select-trigger" style="color:${rc.clr};" data-value="${u.role_id}">
                                        ${roleName}
                                    </div>
                                    <div class="inline-select-dropdown">
                                        <div class="inline-select-option" data-value="1" style="color:#9ca3af;">Employee</div>
                                        <div class="inline-select-option" data-value="2" style="color:#06b6d4;">IT Support</div>
                                        <div class="inline-select-option" data-value="3" style="color:#6366f1;">Admin</div>
                                    </div>
                                </div>
                            </td>
                            <td data-sort="${u.is_online ? 2 : 1}">
                                ${u.is_online
                                ? `<span style="display:inline-flex; align-items:center; gap:6px; color:#10b981; font-weight:500;"><span style="width:8px; height:8px; border-radius:50%; background:#10b981; box-shadow:0 0 8px rgba(16,185,129,0.5); animation: pulse-online 2s infinite;"></span>${u.last_ip || 'Online'}</span>`
                                : `<span style="display:inline-flex; align-items:center; gap:6px; color:#9ca3af; font-weight:500;"><span style="width:8px; height:8px; border-radius:50%; background:#9ca3af;"></span>Offline</span>`
                            }
                            </td>
                            <td style="white-space: nowrap;">
                                <button class="btn btn-secondary btn-logout-user" style="color:var(--text-muted); border-color:rgba(156,163,175,0.4);">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    Logout
                                </button>
                                <button class="btn btn-secondary btn-suspend-user" style="color:var(--warning); border-color:rgba(245,158,11,0.4); margin-left:6px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    Suspend
                                </button>
                                <button class="btn btn-secondary btn-del-user" style="color:var(--danger); border-color:rgba(239,68,68,0.4); margin-left:6px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4h6v2"></path></svg>
                                    Delete
                                </button>
                            </td>`;
                        const container = tr.querySelector('.inline-select-container');
                        const trigger = container.querySelector('.inline-select-trigger');
                        const dropdown = container.querySelector('.inline-select-dropdown');

                        trigger.addEventListener('click', (e) => {
                            e.stopPropagation();
                            document.querySelectorAll('.inline-select-dropdown.open').forEach(d => {
                                if (d !== dropdown) {
                                    d.classList.remove('open');
                                    const row = d.closest('tr');
                                    if (row) row.classList.remove('has-open-dropdown');
                                }
                            });
                            const isOpen = dropdown.classList.toggle('open');
                            tr.classList.toggle('has-open-dropdown', isOpen);
                        });

                        container.querySelectorAll('.inline-select-option').forEach(opt => {
                            opt.addEventListener('click', async (e) => {
                                e.stopPropagation();
                                const newRoleId = parseInt(opt.dataset.value);
                                const newRoleName = opt.innerText.trim();
                                const rclrs = { 1: '#9ca3af', 2: '#06b6d4', 3: '#6366f1' };

                                await updateUser(u.id, { role_id: newRoleId });

                                trigger.innerText = newRoleName;
                                trigger.style.color = rclrs[newRoleId];
                                trigger.dataset.value = newRoleId;
                                dropdown.classList.remove('open');
                                tr.classList.remove('has-open-dropdown');
                            });
                        });

                        tr.querySelector('.btn-logout-user').addEventListener('click', (e) => armButton(e.currentTarget, () => forceLogoutUser(u.id)));
                        tr.querySelector('.btn-suspend-user').addEventListener('click', (e) => armButton(e.currentTarget, () => suspendUser(u.id)));
                        tr.querySelector('.btn-del-user').addEventListener('click', (e) => armButton(e.currentTarget, () => deleteUser(u.id)));
                        usersTbody.appendChild(tr);
                    });
                }
            } catch (e) {
                document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--danger);padding:30px;">Failed to load users.</td></tr>';
            } finally {
                restoreBtnRefreshing(btn, origBtn);
            }
        }


        function armButton(btn, action) {
            if (btn.dataset.armed === 'true') {
                action();
            } else {
                btn.dataset.armed = 'true';
                const orig = btn.innerText;
                const origStyle = btn.getAttribute('style');
                btn.innerText = 'Confirm?';
                btn.style.background = btn.style.borderColor || 'rgba(239,68,68,0.3)';
                btn.style.fontWeight = '700';
                setTimeout(() => {
                    btn.dataset.armed = 'false';
                    btn.innerText = orig;
                    btn.setAttribute('style', origStyle);
                }, 3000);
            }
        }

        async function updateUser(userId, payload) {
            try {
                const response = await fetch('/api/users/' + userId, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                    , body: JSON.stringify(payload)
                });
                if (!response.ok) {
                    const err = await response.json();
                    alert(err.message || 'Update failed.');
                }
            } catch (e) {
                alert('Connection error.');
            }
        }

        async function approveUser(userId) {
            try {
                const response = await fetch('/api/users/' + userId + '/approve', {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                if (!response.ok) {
                    const err = await response.json();
                    alert(err.message || 'Approval failed.');
                    return;
                }
                loadUsers();
            } catch (e) {
                alert('Connection error.');
            }
        }

        async function deleteUser(userId) {
            try {
                const response = await fetch('/api/users/' + userId, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                if (response.status === 422) {
                    const err = await response.json();
                    alert(err.message);
                    return;
                }
                loadUsers();
            } catch (e) {
                alert('Connection error.');
            }
        }


        async function forceLogoutUser(userId) {
            try {
                const res = await fetch('/api/users/' + userId + '/force-logout', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (res.status === 422) { alert(data.message); return; }
                loadUsers();
            } catch (e) { alert('Connection error.'); }
        }

        async function logoutAllUsers() {
            try {
                const res = await fetch('/api/users/logout-all', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                alert(data.message);
                loadUsers();
            } catch (e) { alert('Connection error.'); }
        }

        async function suspendUser(userId) {
            try {
                const res = await fetch('/api/users/' + userId + '/suspend', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (res.status === 422) { alert(data.message); return; }
                loadUsers();
            } catch (e) { alert('Connection error.'); }
        }

        async function suspendAllUsers() {
            try {
                console.log('suspendAllUsers started');
                const res = await fetch('/api/users/suspend-all', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                console.log('suspendAllUsers result:', data);
                if (data.message) {
                    alert(data.message);
                } else {
                    alert('Action completed.');
                }
                loadUsers();
            } catch (e) {
                console.error(e);
                alert('Connection error.');
            }
        }


        async function clearCache() {
            const btn = document.getElementById('clearCacheBtn');
            btn.disabled = true;
            btn.innerText = 'Clearing...';
            try {
                const response = await fetch('/api/status/clear-cache', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken }
                });
                const data = await response.json();
                btn.innerText = '✓ Cleared';
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 .49-3.51"></path></svg> Clear Cache';
                }, 2000);

                loadSystemStatus();
            } catch (e) {
                btn.disabled = false;
                btn.innerText = 'Error';
                setTimeout(() => btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 .49-3.51"></path></svg> Clear Cache', 2000);
            }
        }


        function showNewUserModal() {
            document.getElementById('newUserName').value = '';
            document.getElementById('newUserEmail').value = '';
            document.getElementById('newUserPassword').value = '';
            document.getElementById('newUserRole').value = '1';
            syncCustomSelect('newUserRole');
            document.getElementById('newUserError').style.display = 'none';
            const overlay = document.getElementById('newUserOverlay');
            overlay.style.display = 'flex';
            setTimeout(() => overlay.style.opacity = '1', 10);
        }

        function closeNewUserModal() {
            const overlay = document.getElementById('newUserOverlay');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        async function createUser() {
            const name = document.getElementById('newUserName').value.trim();
            const email = document.getElementById('newUserEmail').value.trim();
            const password = document.getElementById('newUserPassword').value;
            const role_id = parseInt(document.getElementById('newUserRole').value);
            const errEl = document.getElementById('newUserError');
            const btn = document.getElementById('createUserBtn');

            if (!name || !email || !password) {
                errEl.innerText = 'All fields are required.';
                errEl.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerText = 'Creating...';
            errEl.style.display = 'none';

            try {
                const response = await fetch('/api/users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ name, email, password, role_id })
                });
                const data = await response.json();
                if (response.ok) {
                    closeNewUserModal();
                    loadUsers();
                } else {
                    const firstErr = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Creation failed.');
                    errEl.innerText = firstErr;
                    errEl.style.display = 'block';
                }
            } catch (e) {
                errEl.innerText = 'Connection error.';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerText = 'Create User';
            }
        }

window.copyDemoText = function(el) {
    const text = el.getAttribute('data-copy');
    
    function onSuccess() {
        const orig = el.innerText;
        el.innerText = 'Copied!';
        el.style.backgroundColor = 'var(--success)';
        el.style.color = '#fff';
        setTimeout(() => {
            el.innerText = orig;
            el.style.backgroundColor = '';
            el.style.color = '';
        }, 1000);
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    } else {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            onSuccess();
        } catch (err) {
            console.error('Fallback copy failed', err);
        }
        document.body.removeChild(textArea);
    }
};

window.toggleMobileSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
};

window.closeMobileSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
};


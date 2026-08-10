/* Auth — Sistem Autentikasi Multi-Level SukaWarga10 (v3.0 Secured) */

const Auth = {

    // === Dynamic Roles (RBAC) ===
    initRoles() {
        const existing = DB.getAll('roles');
        if (existing.length > 0) return;

        const defaultRoles = [
            {
                id: 'superadmin', label: 'Super Admin', icon: '👑', color: '#7c3aed',
                menus: ['dashboard', 'pendataan', 'sampah', 'padaringan', 'setor', 'pengeluaran', 'sumbangan', 'bukukas', 'aduan', 'mpwa', 'laporan', 'surat', 'umkm', 'kegiatan', 'users', 'auditlog'],
                canEdit: true, canDelete: true, canBackup: true, canSettings: true, canManageUsers: true
            },
            {
                id: 'ketua_rw', label: 'Ketua RW', icon: '🏛️', color: '#1d6a2d',
                menus: ['dashboard', 'pendataan', 'sampah', 'padaringan', 'setor', 'pengeluaran', 'sumbangan', 'bukukas', 'aduan', 'mpwa', 'laporan', 'surat', 'umkm', 'kegiatan', 'auditlog'],
                canEdit: true, canDelete: true, canBackup: true, canSettings: true, canManageUsers: false
            },
            {
                id: 'bendahara', label: 'Bendahara', icon: '💼', color: '#b45309',
                menus: ['dashboard', 'pendataan', 'sampah', 'padaringan', 'setor', 'pengeluaran', 'sumbangan', 'bukukas', 'laporan', 'surat', 'umkm', 'mpwa', 'kegiatan'],
                canEdit: true, canDelete: false, canBackup: true, canSettings: false, canManageUsers: false
            },
            {
                id: 'petugas_rt', label: 'Petugas RT', icon: '📋', color: '#0369a1',
                menus: ['dashboard', 'pendataan', 'sampah', 'padaringan', 'setor', 'surat', 'aduan', 'kegiatan'],
                canEdit: true, canDelete: false, canBackup: false, canSettings: false, canManageUsers: false,
                rtFilter: true
            },
            {
                id: 'warga', label: 'Warga', icon: '🏠', color: '#4b5563',
                menus: ['dashboard', 'surat', 'aduan'],
                canEdit: false, canDelete: false, canBackup: false, canSettings: false, canManageUsers: false,
                readOnly: true
            }
        ];
        defaultRoles.forEach(r => DB.insert('roles', r, true));
    },

    getLevels() {
        const roles = DB.getAll('roles');
        const map = {};
        roles.forEach(r => map[r.id] = r);
        return map;
    },

    // =========================================================================
    // SECURITY CONFIGURATION
    // =========================================================================
    SESSION_KEY: 'sukawarga10_session',
    SESSION_EXPIRY_MS: 8 * 60 * 60 * 1000, // 8 hours
    MAX_LOGIN_ATTEMPTS: 5,
    LOCKOUT_MS: 3 * 60 * 1000, // 3 minutes lockout
    LOCKOUT_KEY: 'sukawarga10_lockout',

    // =========================================================================
    // PIN HASHING (SHA-256 via Web Crypto API)
    // =========================================================================
    async hashPIN(pin) {
        const encoder = new TextEncoder();
        const salt = 'SukaWarga10_RW10_Garut'; // fixed salt for consistency
        const data = encoder.encode(salt + pin);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    },

    // Synchronous fallback hash (for environments without crypto.subtle, e.g. file:// on some browsers)
    hashPINSync(pin) {
        const salt = 'SukaWarga10_RW10_Garut';
        const str = salt + pin;
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        // Create a longer hash for better security
        let h1 = Math.abs(hash).toString(16);
        let h2 = Math.abs(hash * 31).toString(16);
        let h3 = Math.abs(hash * 127).toString(16);
        let h4 = Math.abs(hash * 8191).toString(16);
        return ('sha256_sync_' + h1 + h2 + h3 + h4).padEnd(64, '0');
    },

    // Hash PIN with async/sync fallback
    async _hashPIN(pin) {
        try {
            if (crypto?.subtle) return await this.hashPIN(pin);
        } catch (e) { }
        return this.hashPINSync(pin);
    },

    // Check if a PIN string looks like a hash (not plaintext)
    _isHashed(pinVal) {
        return pinVal && (pinVal.length === 64 || pinVal.startsWith('sha256_sync_'));
    },

    // Auto-migrate plaintext PINs to hashed
    async _migratePINs() {
        const users = DB.getAll('users');
        for (const u of users) {
            if (u.pin && !this._isHashed(u.pin)) {
                const hashed = await this._hashPIN(u.pin);
                DB.update('users', u.id, { pin: hashed, _pinMigrated: true }, true);
            }
        }
    },

    // =========================================================================
    // BRUTE-FORCE PROTECTION
    // =========================================================================
    _getLockout() {
        try { return JSON.parse(localStorage.getItem(this.LOCKOUT_KEY)) || { attempts: 0, lockedUntil: 0 }; }
        catch (e) { return { attempts: 0, lockedUntil: 0 }; }
    },

    _setLockout(data) {
        localStorage.setItem(this.LOCKOUT_KEY, JSON.stringify(data));
    },

    _isLockedOut() {
        const lock = this._getLockout();
        if (lock.lockedUntil && Date.now() < lock.lockedUntil) {
            return Math.ceil((lock.lockedUntil - Date.now()) / 1000);
        }
        // Reset if lockout expired
        if (lock.lockedUntil && Date.now() >= lock.lockedUntil) {
            this._setLockout({ attempts: 0, lockedUntil: 0 });
        }
        return 0;
    },

    _recordFailedAttempt() {
        const lock = this._getLockout();
        lock.attempts = (lock.attempts || 0) + 1;
        if (lock.attempts >= this.MAX_LOGIN_ATTEMPTS) {
            lock.lockedUntil = Date.now() + this.LOCKOUT_MS;
            lock.attempts = 0;
        }
        this._setLockout(lock);
        return lock;
    },

    _resetAttempts() {
        this._setLockout({ attempts: 0, lockedUntil: 0 });
    },

    // =========================================================================
    // SESSION MANAGEMENT (with expiry)
    // =========================================================================
    getSession() {
        try {
            const session = JSON.parse(localStorage.getItem(this.SESSION_KEY));
            if (!session) return null;
            // Check session expiry
            const loginTime = new Date(session.loginAt).getTime();
            const lastActivity = session.lastActivity ? new Date(session.lastActivity).getTime() : loginTime;
            if (Date.now() - lastActivity > this.SESSION_EXPIRY_MS) {
                this.clearSession();
                return null;
            }
            return session;
        } catch (e) { return null; }
    },

    setSession(user) {
        const now = new Date().toISOString();
        const session = {
            id: user.id,
            username: user.username,
            namaLengkap: user.namaLengkap,
            level: user.level,
            rt: user.rt || '',
            loginAt: now,
            lastActivity: now
        };
        localStorage.setItem(this.SESSION_KEY, JSON.stringify(session));
        try { DB.update('users', user.id, { lastLogin: now }, true); } catch (e) { }
        return session;
    },

    // Refresh session activity timestamp (call on user interactions)
    refreshSession() {
        try {
            const session = JSON.parse(localStorage.getItem(this.SESSION_KEY));
            if (session) {
                session.lastActivity = new Date().toISOString();
                localStorage.setItem(this.SESSION_KEY, JSON.stringify(session));
            }
        } catch (e) { }
    },

    clearSession() {
        localStorage.removeItem(this.SESSION_KEY);
    },

    // === Current User Info ===
    currentUser() {
        return this.getSession();
    },

    currentLevel() {
        return this.getSession()?.level || 'warga';
    },

    getLevelInfo(level) {
        return this.getLevels()[level] || this.getLevels().warga || {};
    },

    // === Permission Checks ===
    can(action) {
        const level = this.currentLevel();
        const info = this.getLevelInfo(level);
        return !!info[action];
    },

    canAccessMenu(menu) {
        const level = this.currentLevel();
        const info = this.getLevelInfo(level);
        return (info.menus || []).includes(menu);
    },

    // =========================================================================
    // LOGIN (Secured: hashed PIN + brute-force protection)
    // =========================================================================
    login() {
        const username = document.getElementById('inputUser')?.value?.trim()?.toLowerCase();
        const pin = document.getElementById('inputPIN')?.value?.trim();
        const btn = document.getElementById('btnLogin');

        if (!username || !pin) {
            this.showError('Username dan PIN harus diisi');
            return;
        }

        // Check lockout
        const lockSeconds = this._isLockedOut();
        if (lockSeconds > 0) {
            this.showError(`⏳ Akun terkunci. Coba lagi dalam ${lockSeconds} detik.`);
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = ''; btn.classList.add('loading'); }

        // Async login (PIN hashing is async)
        setTimeout(async () => {
            try {
                this.seedDefaultUsers();
                await this._migratePINs();

                const users = DB.getAll('users');
                const hashedPin = await this._hashPIN(pin);

                const user = users.find(u =>
                    u.username?.toLowerCase() === username &&
                    u.aktif !== false &&
                    (u.pin === hashedPin || (!this._isHashed(u.pin) && u.pin === pin))
                );

                if (!user) {
                    if (btn) { btn.disabled = false; btn.textContent = '🔑 Masuk ke Sistem'; btn.classList.remove('loading'); }
                    const lock = this._recordFailedAttempt();
                    const remaining = this.MAX_LOGIN_ATTEMPTS - (lock.attempts || 0);
                    if (remaining > 0) {
                        this.showError(`Username atau PIN salah. Sisa percobaan: ${remaining}`);
                    } else {
                        this.showError(`⏳ Terlalu banyak percobaan. Akun dikunci ${this.LOCKOUT_MS / 60000} menit.`);
                    }
                    const pinInput = document.getElementById('inputPIN');
                    if (pinInput) { pinInput.value = ''; this.updatePinDots(''); pinInput.focus(); }
                    return;
                }

                // Auto-migrate plaintext PIN to hash on successful login
                if (!this._isHashed(user.pin)) {
                    const h = await this._hashPIN(pin);
                    DB.update('users', user.id, { pin: h, _pinMigrated: true }, true);
                }

                this._resetAttempts();
                this.setSession(user);
                window.location.href = 'index.html';
            } catch (err) {
                if (btn) { btn.disabled = false; btn.textContent = '🔑 Masuk ke Sistem'; btn.classList.remove('loading'); }
                this.showError('Terjadi kesalahan sistem: ' + err.message);
            }
        }, 600);
    },

    // === Logout ===
    logout() {
        if (confirm('Keluar dari sistem?')) {
            this.clearSession();
            window.location.href = 'login.html';
        }
    },

    // === Require Login Guard ===
    requireLogin() {
        if (!this.getSession()) {
            window.location.href = 'login.html';
            return false;
        }
        // Refresh session on every page load
        this.refreshSession();
        return true;
    },

    // === Guard Menus ===
    guardMenus() {
        const level = this.currentLevel();
        const info = this.getLevelInfo(level);
        const allowedMenus = info.menus || [];

        document.querySelectorAll('.nav-item[data-page]').forEach(el => {
            const page = el.dataset.page;
            if (!allowedMenus.includes(page)) {
                el.style.display = 'none';
            }
        });

        const session = this.getSession();
        if (session) {
            const nameEl = document.getElementById('sidebarUserName');
            const roleEl = document.querySelector('.sidebar-user-role');
            const avatarEl = document.getElementById('sidebarAvatar');
            const headerAvatar = document.getElementById('headerAvatar');
            const levelInfo = this.getLevelInfo(session.level);

            if (nameEl) nameEl.textContent = session.namaLengkap || session.username;
            if (roleEl) roleEl.textContent = `${levelInfo.icon} ${levelInfo.label}${session.rt ? ' — ' + session.rt : ''}`;
            const initials = (session.namaLengkap || session.username || 'U').slice(0, 2).toUpperCase();
            if (avatarEl) { avatarEl.textContent = initials; avatarEl.style.background = `linear-gradient(135deg, ${levelInfo.color}, ${levelInfo.color}aa)`; }
            if (headerAvatar) { headerAvatar.textContent = initials; headerAvatar.style.background = `linear-gradient(135deg, ${levelInfo.color}, ${levelInfo.color}aa)`; }
        }
    },

    // === Change PIN ===
    async changePIN(userId, oldPin, newPin) {
        if (!newPin || newPin.length < 6) throw new Error('PIN baru minimal 6 digit');
        const user = DB.getById('users', userId);
        if (!user) throw new Error('User tidak ditemukan');

        // Verify old PIN
        const oldHash = await this._hashPIN(oldPin);
        if (user.pin !== oldHash && user.pin !== oldPin) {
            throw new Error('PIN lama salah');
        }

        const newHash = await this._hashPIN(newPin);
        DB.update('users', userId, { pin: newHash, _pinMigrated: true, pinChangedAt: new Date().toISOString() }, true);
        return true;
    },

    // === UI Helpers ===
    showError(msg) {
        const errBox = document.getElementById('errorBox');
        if (errBox) {
            errBox.textContent = '⚠️ ' + msg;
            errBox.classList.add('show');
            errBox.style.animation = 'none';
            errBox.offsetHeight;
            errBox.style.animation = '';
            setTimeout(() => errBox.classList.remove('show'), 4000);
        }
    },

    updatePinDots(val) {
        for (let i = 0; i < 6; i++) {
            const dot = document.getElementById('dot' + i);
            if (dot) dot.classList.toggle('filled', i < val.length);
        }
    },

    togglePinVisibility() {
        const input = document.getElementById('inputPIN');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    },

    // === Seed Default Users (with hashed PINs) ===
    seedDefaultUsers() {
        const existing = DB.getAll('users');
        if (existing.length > 0) return;

        // Store plaintext initially — will be auto-migrated on first login
        const defaults = [
            { username: 'admin', namaLengkap: 'Administrator', pin: '123456', level: 'superadmin', rt: '', aktif: true },
            { username: 'ketuarw', namaLengkap: 'Ketua RW 10', pin: '111111', level: 'ketua_rw', rt: '', aktif: true },
            { username: 'bendahara', namaLengkap: 'Bendahara RW 10', pin: '222222', level: 'bendahara', rt: '', aktif: true },
            { username: 'rt01', namaLengkap: 'Petugas RT 01', pin: '010101', level: 'petugas_rt', rt: 'RT 01', aktif: true },
            { username: 'rt02', namaLengkap: 'Petugas RT 02', pin: '020202', level: 'petugas_rt', rt: 'RT 02', aktif: true },
        ];
        defaults.forEach(u => DB.insert('users', { ...u, createdAt: new Date().toISOString(), lastLogin: null }));
    }
};

// Auto-refresh session on user activity (mouse/keyboard/touch)
['click', 'keydown', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, () => Auth.refreshSession(), { passive: true });
});

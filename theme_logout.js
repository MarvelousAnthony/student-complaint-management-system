/**
 * theme_logout.js
 * 
 * Client-side script handling:
 * 1. Global Light/Dark Theme management (LocalStorage, dynamic button injection).
 * 2. High-fidelity Logout Confirmation Modal (intercepts clicks to logout.php).
 * 3. Tab Visibility Auto-Logout (logs out when switching tabs or minimizing, preserves redirect context).
 * 4. Dynamic Password Visibility eye toggles on all password fields.
 */

// --- 1. IMMEDIATELY APPLY THEME (prevents flashing) ---
(function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
    }
})();

// SVG Icons
const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
</svg>`;

const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
</svg>`;

// --- INITIALIZE UTILITY FUNCTIONS ---
function init() {
    injectThemeToggleButton();
    setupLogoutModalListener();
    setupTabVisibilityAutoLogout();
    setupPasswordVisibilityToggles();
    setupAlertAutoDismiss();
    
    // Add animations styles to head if not present
    if (!document.getElementById('scms-animation-styles')) {
        const style = document.createElement('style');
        style.id = 'scms-animation-styles';
        style.innerHTML = `
            .animate-spin-slow {
                animation: spin 8s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .modal-fade-in {
                animation: fadeIn 0.25s ease-out forwards;
            }
            .modal-scale-up {
                animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes scaleUp {
                from { transform: scale(0.9); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
}

// Durable trigger checking current readyState
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// --- INJECT THEME TOGGLE BUTTON ---
function injectThemeToggleButton() {
    if (document.getElementById('theme-toggle-btn')) return;

    // Create the floating button
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'theme-toggle-btn';
    
    // Apply styling classes
    toggleBtn.className = 'fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full flex items-center justify-center cursor-pointer transition-all duration-300 shadow-2xl active:scale-90 border focus:outline-none backdrop-blur-md';
    
    // Set initial icon & theme-specific classes
    updateToggleButtonState(toggleBtn);

    // Event listener
    toggleBtn.addEventListener('click', () => {
        const isCurrentLight = document.documentElement.classList.contains('light');
        if (isCurrentLight) {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.add('light');
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
        updateToggleButtonState(toggleBtn);
        
        // Refresh charts on admin dashboard if function exists
        if (typeof window.renderCharts === 'function') {
            window.renderCharts();
        }
    });

    document.body.appendChild(toggleBtn);
}

function updateToggleButtonState(btn) {
    const isLight = document.documentElement.classList.contains('light');
    
    if (isLight) {
        btn.innerHTML = moonIcon;
        btn.style.backgroundColor = '#ffffff';
        btn.style.borderColor = '#cbd5e1';
        btn.title = 'Switch to Dark Mode';
    } else {
        btn.innerHTML = sunIcon;
        btn.style.backgroundColor = '#1e293b';
        btn.style.borderColor = '#334155';
        btn.title = 'Switch to Light Mode';
    }
}

// --- SETUP LOGOUT MODAL LISTENER ---
function setupLogoutModalListener() {
    document.addEventListener('click', (e) => {
        // Intercept any click on anchors pointing to logout.php
        const logoutLink = e.target.closest('a[href*="logout.php"]');
        
        // If it's the confirm button inside the modal itself, DO NOT intercept!
        if (logoutLink && !logoutLink.classList.contains('confirm-logout-btn')) {
            e.preventDefault();
            triggerLogoutModal(logoutLink.href);
        }
    });
}

// --- TRIGGER CUSTOM LOGOUT MODAL ---
function triggerLogoutModal(logoutUrl) {
    // Remove if already exists
    const existing = document.getElementById('logout-modal-overlay');
    if (existing) existing.remove();

    // Create Modal Elements
    const overlay = document.createElement('div');
    overlay.id = 'logout-modal-overlay';
    overlay.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center opacity-0 modal-fade-in';
    
    const isLight = document.documentElement.classList.contains('light');
    const modalBg = isLight ? 'bg-white' : 'bg-slate-900';
    const modalBorder = isLight ? 'border-slate-200' : 'border-slate-800';
    const textColor = isLight ? 'text-slate-900' : 'text-slate-100';
    const muteColor = isLight ? 'text-slate-600' : 'text-slate-400';
    const cancelBtnBg = isLight ? 'bg-slate-100 hover:bg-slate-200 text-slate-800' : 'bg-slate-800 hover:bg-slate-700 text-slate-200';

    overlay.innerHTML = `
        <div id="logout-modal-container" class="w-full max-w-md p-6 rounded-2xl border ${modalBorder} ${modalBg} shadow-2xl mx-4 transform scale-90 opacity-0 modal-scale-up">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-500 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold ${textColor}">Sign Out Confirmation</h3>
                    <p class="text-xs ${muteColor} mt-1">Are you sure you want to sign out of Adeleke University CMS?</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="logout-cancel-btn" class="px-4 py-2 text-xs font-semibold rounded-xl transition-all ${cancelBtnBg}">
                    No, Keep Me In
                </button>
                <a href="${logoutUrl}" class="confirm-logout-btn px-4.5 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-xl transition-all shadow-lg shadow-rose-600/15">
                    Yes, Sign Out
                </a>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Event listeners to close
    const closeModal = () => {
        overlay.classList.add('transition-opacity', 'duration-200', 'opacity-0');
        setTimeout(() => overlay.remove(), 200);
    };

    document.getElementById('logout-cancel-btn').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
}

// --- 3. AUTO-LOGOUT WHEN SWITCHING TABS OR LEAVING BROWSER ---
function setupTabVisibilityAutoLogout() {
    const path = window.location.pathname;
    // Do not run on authentication pages to prevent redirect loops
    const isAuthPage = path.includes('login.php') || path.includes('register.php') || path.includes('logout.php') || path.includes('create_admin.php');
    
    if (!isAuthPage) {
        // Track page unloading to prevent logging out during normal link navigation
        let isUnloading = false;
        window.addEventListener('beforeunload', () => {
            isUnloading = true;
        });

        // Guard against file upload pickers which blur the browser window natively
        let isFilePickerOpen = false;
        document.addEventListener('click', (e) => {
            const fileInput = e.target.closest('input[type="file"]');
            if (fileInput) {
                isFilePickerOpen = true;
                // Reset file picker flag after 20 seconds
                setTimeout(() => {
                    isFilePickerOpen = false;
                }, 20000);
            }
        });
        window.addEventListener('focus', () => {
            isFilePickerOpen = false;
        });

        const performLogout = () => {
            if (isUnloading || isFilePickerOpen) return;
            
            // Get current filename and query parameters (e.g. view_complaint_student.php?id=5)
            const currentPage = window.location.pathname.substring(window.location.pathname.lastIndexOf('/') + 1);
            const currentQuery = window.location.search;
            const redirectUrl = currentPage + currentQuery;
            
            // Immediately log out and pass current page as redirect target
            window.location.href = 'logout.php?redirect=' + encodeURIComponent(redirectUrl);
        };

        // Trigger on visibility hide (switching tabs or minimizing)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                performLogout();
            }
        });

        // Trigger on window blur (leaving browser or clicking other app window)
        window.addEventListener('blur', () => {
            // Tiny delay to ensure visibility state or other focus triggers don't collide
            setTimeout(() => {
                performLogout();
            }, 250);
        });
    }
}

// --- 4. DYNAMIC PASSWORD VISIBILITY EYE TOGGLE ---
function setupPasswordVisibilityToggles() {
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.dataset.passwordToggled === 'true') return;
        input.dataset.passwordToggled = 'true';

        // Wrap input inside relative div to position button absolutely relative to it
        const wrapper = document.createElement('div');
        wrapper.className = 'relative w-full';
        
        // Insert wrapper before input, and insert input inside wrapper
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // Add padding on the right to prevent text overlapping the icon
        input.classList.add('pr-10');

        // Create toggle eye button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 hover:text-slate-350 focus:outline-none z-10 cursor-pointer';
        
        // Icons
        const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
        const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 013.123-4.838m3.072-1.838A9.878 9.878 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 7c.88 0 1.72.115 2.52.332m0 0L17.5 10.5m-5.5 2a3.5 3.5 0 005.5 0" /></svg>`;

        toggleBtn.innerHTML = eyeOffIcon;

        // Toggle action
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.innerHTML = eyeIcon;
            } else {
                input.type = 'password';
                toggleBtn.innerHTML = eyeOffIcon;
            }
        });

        wrapper.appendChild(toggleBtn);
    });
}

// --- 5. AUTOMATIC ALERT AUTO-DISMISS AFTER 5 SECONDS ---
function setupAlertAutoDismiss() {
    const selectors = [
        '[id*="alert"]', 
        '[id*="error"]',
        '[id*="success"]',
        '.bg-rose-500\\/10', 
        '.bg-emerald-500\\/10'
    ];
    
    // Select all potential error/success alert containers
    const alerts = Array.from(document.querySelectorAll(selectors.join(', ')));
    
    alerts.forEach(alert => {
        // Skip hidden template containers that have no display text
        if (!alert.textContent.trim() && alert.id === 'js-error-alert') {
            // Keep observing in case it gets shown dynamically
            const observer = new MutationObserver(() => {
                if (!alert.classList.contains('hidden')) {
                    dismissElement(alert);
                    observer.disconnect();
                }
            });
            observer.observe(alert, { attributes: true, attributeFilter: ['class'] });
            return;
        }
        
        dismissElement(alert);
    });
    
    function dismissElement(el) {
        el.style.transition = 'opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(() => {
                el.style.display = 'none';
            }, 600);
        }, 5000); // 5 seconds duration
    }
}

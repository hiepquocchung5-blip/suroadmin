<?php
/**
 * Advanced Admin Panel Layout - Footer
 * Integrated with Security Listeners, UI Helpers, and Global JS Router.
 */
?>
            </div><!-- End .max-w-7xl -->
        </div><!-- End #content-container -->
        
        <!-- Modern Footer Status Bar -->
        <footer class="h-10 px-8 glass-panel border-t border-slate-200/60 flex items-center justify-between text-slate-400 text-[10px] font-bold tracking-tight z-40 relative">
            <div class="flex items-center space-x-4">
                <span class="flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span> 
                    Secure Session: <span class="text-slate-500 ml-1"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'GUEST'); ?></span>
                </span>
                <span class="text-slate-300">|</span>
                <span class="flex items-center">
                    <i data-lucide="clock" class="w-3 h-3 mr-1.5"></i>
                    Expires in: <span id="session-timer" class="text-slate-500 ml-1">--:--</span>
                </span>
            </div>
            <div class="flex items-center space-x-6">
                <span class="hidden sm:inline-flex items-center bg-slate-100 px-2 py-0.5 rounded text-slate-500 uppercase tracking-tighter">
                    V2.5.0-STABLE
                </span>
                <a href="?module=system_logs" class="hover:text-blue-600 transition-colors uppercase tracking-widest">System Health</a>
                <a href="mailto:support@suro.io" class="hover:text-blue-600 transition-colors uppercase tracking-widest border-l border-slate-200 pl-6">Support</a>
            </div>
        </footer>
    </main>
</div>

<!-- Global Toast Container -->
<div id="toast-container" class="fixed bottom-12 right-6 z-[100] space-y-3 pointer-events-none flex flex-col items-end"></div>

<!-- Core System Scripts -->
<script>
    /**
     * CORE: Initialize Lucide Icons
     */
    lucide.createIcons();

    /**
     * UI HELPER: Sidebar Persistence & Collapse Logic
     */
    const sidebar = document.getElementById('desktop-sidebar');
    const collapseIcon = document.getElementById('collapse-icon');
    
    if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
        sidebar.classList.add('sidebar-collapsed');
        if(collapseIcon) collapseIcon.setAttribute('data-lucide', 'chevron-right');
        lucide.createIcons();
    }

    function toggleSidebarCollapse() {
        const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
        localStorage.setItem('admin_sidebar_collapsed', isCollapsed);
        if(collapseIcon) {
            collapseIcon.setAttribute('data-lucide', isCollapsed ? 'chevron-right' : 'chevron-left');
            lucide.createIcons();
        }
    }

    /**
     * UI HELPER: Toast Notifications
     */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const config = {
            success: { bg: 'bg-white border-emerald-500 text-emerald-800', icon: 'check-circle' },
            error: { bg: 'bg-white border-red-500 text-red-800', icon: 'alert-circle' },
            warning: { bg: 'bg-white border-orange-500 text-orange-800', icon: 'alert-triangle' }
        };
        const theme = config[type] || config.success;

        toast.className = `pointer-events-auto flex items-center p-4 rounded-2xl shadow-2xl border-l-4 transition-all duration-500 transform translate-x-20 opacity-0 ${theme.bg}`;
        toast.innerHTML = `
            <div class="p-2 rounded-xl bg-slate-50 mr-3"><i data-lucide="${theme.icon}" class="w-5 h-5"></i></div>
            <div class="flex flex-col">
                <span class="text-xs font-black uppercase tracking-widest opacity-40">${type}</span>
                <span class="text-sm font-bold -mt-0.5">${message}</span>
            </div>
        `;
        container.appendChild(toast);
        lucide.createIcons({ node: toast });
        requestAnimationFrame(() => toast.classList.remove('translate-x-20', 'opacity-0'));
        setTimeout(() => {
            toast.classList.add('translate-x-20', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }

    /**
     * SYSTEM ROUTER: Active Link Highlighter
     * Correctly handles your module-based routing (?module=live etc.)
     */
    function initRouter() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentModule = urlParams.get('module') || 'dashboard';
        
        // Find all nav links and update active state
        document.querySelectorAll('aside nav a').forEach(link => {
            const href = link.getAttribute('href');
            if (href && (href.includes(`module=${currentModule}`) || (currentModule === 'dashboard' && href.includes('index.php') && !href.includes('module=')))) {
                link.classList.add('nav-item-active');
                // Also update the icon color
                const icon = link.querySelector('i');
                if (icon) icon.classList.add('text-white');
            } else {
                link.classList.remove('nav-item-active');
            }
        });
    }

    /**
     * SECURITY: CSRF Token Interceptor
     */
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    window.secureFetch = async function(url, options = {}) {
        options.headers = { ...options.headers, 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' };
        const response = await fetch(url, options);
        if (response.status === 403) {
            showToast('Security Violation: Refreshing...', 'error');
            setTimeout(() => window.location.reload(), 2000);
        }
        return response;
    };

    /**
     * SESSION: Activity Watchdog
     */
    let sessionTime = 3600;
    const timerDisplay = document.getElementById('session-timer');
    const sessionInterval = setInterval(() => {
        if (sessionTime <= 0) {
            clearInterval(sessionInterval);
            window.location.href = '../../index.php?logout=timeout';
            return;
        }
        sessionTime--;
        if (timerDisplay) {
            const mins = Math.floor(sessionTime / 60);
            const secs = sessionTime % 60;
            timerDisplay.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            if (sessionTime < 300) timerDisplay.classList.add('text-red-500', 'animate-pulse');
        }
    }, 1000);

    // Mobile Navigation Toggle
    function toggleMobileMenu() {
        const drawer = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('mobile-sidebar-backdrop');
        const isHidden = drawer.classList.contains('-translate-x-full');
        if (isHidden) {
            const mobileNav = document.getElementById('mobile-nav-content');
            if (mobileNav && mobileNav.innerHTML.trim() === "") {
                mobileNav.innerHTML = document.querySelector('aside nav').innerHTML;
                lucide.createIcons({ node: mobileNav });
            }
            drawer.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        } else {
            drawer.classList.add('-translate-x-full');
            setTimeout(() => backdrop.classList.add('hidden'), 300);
        }
    }

    // Initialize Router on Load
    document.addEventListener('DOMContentLoaded', initRouter);
</script>
</body>
</html>
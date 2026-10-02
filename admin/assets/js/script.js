document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Sidebar Toggle ---
    const sidebarToggle = document.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', e => {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    }

    // --- 2. Theme Toggle ---
    const themeToggleBtn = document.getElementById('themeToggle');
    const htmlEl = document.documentElement;
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Apply saved theme immediately
    htmlEl.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const newTheme = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            htmlEl.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        if (theme === 'dark') {
            themeToggleBtn.innerHTML = '<i class="fas fa-sun text-warning"></i> Light';
        } else {
            themeToggleBtn.innerHTML = '<i class="fas fa-moon text-dark"></i> Dark';
        }
    }

    // --- 3. Toast Notifications ---
    const systemToastEl = document.getElementById('systemToast');
    if (systemToastEl) {
        const toast = new bootstrap.Toast(systemToastEl, { delay: 4000 });
        toast.show();
    }

    // --- 4. Delete Modal Info Passing ---
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const studentId = button.getAttribute('data-bs-id');
            const studentName = button.getAttribute('data-bs-name');
            const studentEnrollment = button.getAttribute('data-bs-enrollment');
            
            document.getElementById('deleteStudentId').value = studentId;
            
            const nameSpan = document.getElementById('deleteStudentName');
            const enrollmentSpan = document.getElementById('deleteStudentEnrollment');
            if (nameSpan && studentName) nameSpan.textContent = studentName;
            if (enrollmentSpan && studentEnrollment) enrollmentSpan.textContent = studentEnrollment;
        });
    }

    // --- 5. Keyboard Shortcuts ---
    document.addEventListener('keydown', (e) => {
        // Ctrl + K for Search
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            } else {
                // Redirect to search if not on students page
                window.location.href = 'students.php?focus=search';
            }
        }
        // Esc for Modals is handled natively by Bootstrap, but can add custom here if needed
    });

    // Check URL for search focus
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('focus') === 'search') {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.focus();
    }

    // --- 6. Number Counting Animation (Dashboard) ---
    const animatedNumbers = document.querySelectorAll('.animate-number');
    animatedNumbers.forEach(numEl => {
        const target = parseInt(numEl.getAttribute('data-target'), 10);
        if (isNaN(target)) return;
        
        let current = 0;
        const increment = target / 30; // 30 frames
        const updateNumber = () => {
            current += increment;
            if (current < target) {
                numEl.innerText = Math.ceil(current);
                requestAnimationFrame(updateNumber);
            } else {
                numEl.innerText = target;
            }
        };
        updateNumber();
    });

    // --- 7. Client-Side Form Validation ---
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

});

// --- 8. Sidebar Scroll Preservation (Synchronous to prevent flash) ---
// Runs outside DOMContentLoaded so it executes immediately when parsed.
// Since this script is at the end of <body>, the sidebar DOM already exists.
(function() {
    const sidebarContainer = document.querySelector('#sidebar-wrapper .list-group');
    if (sidebarContainer) {
        // Restore saved scroll position instantly
        const savedScrollTop = sessionStorage.getItem('adminSidebarScrollTop');
        if (savedScrollTop !== null) {
            sidebarContainer.scrollTop = parseInt(savedScrollTop, 10);
        } else {
            // First load: scroll active item into view if it's off-screen
            const activeSidebarItem = sidebarContainer.querySelector('.list-group-item.active');
            if (activeSidebarItem) {
                const itemRect = activeSidebarItem.getBoundingClientRect();
                const containerRect = sidebarContainer.getBoundingClientRect();
                
                if (itemRect.top < containerRect.top || itemRect.bottom > containerRect.bottom) {
                    sidebarContainer.scrollTop = activeSidebarItem.offsetTop - (containerRect.height / 2) + (itemRect.height / 2);
                }
            }
        }

        // Save scroll position whenever the sidebar scrolls
        sidebarContainer.addEventListener('scroll', () => {
            sessionStorage.setItem('adminSidebarScrollTop', sidebarContainer.scrollTop);
        }, { passive: true });
    }
})();

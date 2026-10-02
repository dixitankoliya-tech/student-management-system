        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light glass-header py-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-secondary btn-icon" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="d-none d-md-block ms-3 fw-semibold text-secondary">
                        Student Management System <span class="mx-2">|</span> GLS UNIVERSITY
                    </div>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0 align-items-center">
                            <li class="nav-item me-3">
                                <span class="badge bg-light text-dark border d-none d-lg-inline-block shadow-sm">
                                    <i class="fas fa-keyboard me-1"></i> Ctrl + K to Search
                                </span>
                            </li>
                            
                            <li class="nav-item me-3">
                                <a href="notifications.php" class="nav-link position-relative text-secondary mt-1">
                                    <i class="fas fa-bell fa-lg"></i>
                                    <?php
                                        // Try to safely fetch unread notifications count if user is logged in
                                        $unread_count = 0;
                                        if (isset($_SESSION['user_id'])) {
                                            try {
                                                $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                                $unread_stmt->execute([$_SESSION['user_id']]);
                                                $unread_count = $unread_stmt->fetchColumn();
                                            } catch (Exception $e) {}
                                        }
                                    ?>
                                    <?php if ($unread_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm" style="font-size: 0.55rem; padding: 0.25em 0.5em; margin-top: 10px; margin-left: -10px;">
                                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                                    </span>
                                    <?php endif; ?>
                                </a>
                            </li>

                            <li class="nav-item me-3">
                                <button id="themeToggle" class="btn btn-sm btn-outline-secondary rounded-pill">
                                    <i class="fas fa-moon"></i> Dark
                                </button>
                            </li>
                            <li class="nav-item dropdown ms-2">
                                <a class="nav-link fw-bold text-dark d-flex align-items-center dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                        $currentUser = getCurrentUser();
                                        $initials = 'U';
                                        $displayName = 'User';
                                        if ($currentUser) {
                                            $nameParts = explode(' ', $currentUser['full_name']);
                                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                                            $displayName = htmlspecialchars($currentUser['full_name']);
                                        }
                                    ?>
                                    <div class="avatar-initials bg-primary text-white me-2 shadow-sm"><?php echo $initials; ?></div>
                                    <div class="d-none d-lg-block">
                                        <div class="lh-1"><?php echo $displayName; ?></div>
                                        <div class="fw-normal text-muted" style="font-size: 0.7rem;">Administrator</div>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius: 12px; min-width: 220px;">
                                    <li class="px-3 py-2 border-bottom mb-1">
                                        <div class="fw-bold text-dark"><?php echo $displayName; ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></div>
                                    </li>
                                    <li><a class="dropdown-item py-2" href="settings.php"><i class="fas fa-cog me-3 text-muted"></i>Settings</a></li>
                                    <li><a class="dropdown-item py-2" href="audit_logs.php"><i class="fas fa-history me-3 text-muted"></i>Activity Log</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item text-danger py-2" href="logout.php"><i class="fas fa-sign-out-alt me-3"></i>Sign Out</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container-fluid px-4 py-4">

<!-- Global Search Modal -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0 bg-light p-3">
                <div class="input-group input-group-lg border rounded-3 bg-white">
                    <span class="input-group-text bg-transparent border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="globalSearchInput" class="form-control border-0 shadow-none bg-transparent" placeholder="Search students, faculty, subjects, programs..." autocomplete="off">
                    <button type="button" class="btn-close me-2 mt-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="globalSearchResults">
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-keyboard fs-3 mb-3 opacity-25"></i>
                    <h5>Start typing to search...</h5>
                    <p class="small">Press ESC to close.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchModal = new bootstrap.Modal(document.getElementById('globalSearchModal'));
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    let debounceTimer;

    // Ctrl+K to open
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchModal.show();
        }
    });

    document.getElementById('globalSearchModal').addEventListener('shown.bs.modal', function () {
        searchInput.focus();
    });

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-keyboard fs-3 mb-3 opacity-25"></i>
                    <h5>Start typing to search...</h5>
                </div>`;
            return;
        }

        searchResults.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';

        debounceTimer = setTimeout(() => {
            fetch(`api/global_search.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    if (data.results.length === 0) {
                        searchResults.innerHTML = `
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-search-minus fs-3 mb-3 opacity-25"></i>
                                <h5>No results found for "${query}"</h5>
                            </div>`;
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    data.results.forEach(r => {
                        html += `
                            <a href="${r.url}" class="list-group-item list-group-item-action py-3 px-4 border-bottom">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-primary me-3" style="width: 40px; height: 40px;">
                                        <i class="fas ${r.icon}"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">${r.title}</div>
                                        <div class="small text-muted">${r.type} &bull; ${r.subtitle}</div>
                                    </div>
                                </div>
                            </a>
                        `;
                    });
                    html += '</div>';
                    searchResults.innerHTML = html;
                })
                .catch(err => {
                    searchResults.innerHTML = `<div class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle mb-2"></i><br>Search error: ${err.message}</div>`;
                });
        }, 300);
    });
});
</script>

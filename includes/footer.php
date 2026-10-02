            </div> <!-- Close container-fluid -->
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <!-- Global Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
        <?php if(isset($_GET['msg']) || isset($_GET['error'])): ?>
            <?php 
                $toastClass = isset($_GET['error']) ? 'bg-danger text-white' : 'bg-success text-white';
                $toastIcon = isset($_GET['error']) ? 'fa-exclamation-circle' : 'fa-check-circle';
                
                $toastMsg = '';
                if(isset($_GET['error'])) $toastMsg = htmlspecialchars($_GET['error']);
                elseif(isset($_GET['msg']) && $_GET['msg'] == 'added') {
                    $toastMsg = "Student added successfully!";
                    if (isset($_GET['enrollment'])) {
                        $toastMsg .= "<br><small>Enrollment No: <strong>" . htmlspecialchars($_GET['enrollment']) . "</strong></small>";
                    }
                }
                elseif(isset($_GET['msg']) && $_GET['msg'] == 'updated') $toastMsg = "Student updated successfully!";
                elseif(isset($_GET['msg']) && $_GET['msg'] == 'deleted') $toastMsg = "Student deleted successfully!";
            ?>
            <?php if($toastMsg): ?>
            <div id="systemToast" class="toast align-items-center <?php echo $toastClass; ?> border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-medium">
                        <i class="fas <?php echo $toastIcon; ?> me-2"></i> <?php echo $toastMsg; ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Global Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold" id="deleteConfirmModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 3rem; opacity: 0.8;"></i>
                    </div>
                    <h5 class="mb-2 text-dark">Are you absolutely sure?</h5>
                    <p class="text-muted mb-0">This action cannot be undone. This will permanently delete the student record from the database.</p>
                    <div class="alert alert-danger mt-3 text-start mb-0">
                        <strong>Student:</strong> <span id="deleteStudentName"></span><br>
                        <strong>Enrollment No:</strong> <span id="deleteStudentEnrollment"></span>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="delete_student.php" id="deleteForm" class="m-0">
                        <?php if (function_exists('csrfField')) csrfField(); ?>
                        <input type="hidden" name="id" id="deleteStudentId" value="">
                        <button type="submit" class="btn btn-danger rounded-pill px-4"><i class="fas fa-trash me-2"></i>Yes, Delete Record</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>

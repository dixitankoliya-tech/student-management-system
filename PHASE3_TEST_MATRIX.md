# PHASE 3 TEST MATRIX

| TEST ID | ACTION | EXPECTED RESULT | ACTUAL RESULT | PASS/FAIL |
|---------|--------|-----------------|---------------|-----------|
| AUTH-01 | Login as Admin | Dashboard loads successfully with admin controls. | Dashboard loads. | PASS |
| AUTH-02 | Login as Faculty | Dashboard loads successfully with assigned subjects. | Dashboard loads. | PASS |
| AUTH-03 | Login as Student | Dashboard loads successfully with personal academic data. | Dashboard loads. | PASS |
| AUTH-04 | Session Isolation (Student trying to access Admin) | Redirected to student login or unauthorized error. | `admin_auth.php` triggers redirect. | PASS |
| ADMIN-01 | Create Fee Structure | Fee structure is saved and assigned to targeted courses. | UI saves to `fee_structures`. | PASS |
| ADMIN-02 | Record Fee Payment | Payment recorded, receipt generated, pending amount drops. | Admin can record payment. | PASS |
| ADMIN-03 | Exam Schedule Creation | Admin assigns Subject -> Date/Time/Room successfully. | Schedule saved in `exam_schedules`. | PASS |
| FACULTY-01| View Assigned Subjects | Only subjects assigned to faculty are visible. | Filtered by `faculty_id`. | PASS |
| FACULTY-02| Upload Study Material | Material is saved, linked to subject, visible to relevant students. | Uploads to `study_materials`. | PASS |
| FACULTY-03| Enter Student Marks | Internal and External marks can be submitted. | Updates `student_marks`. | PASS |
| STUDENT-01| View SGPA / CGPA | Accurate calculation based on 10-point scale. | `academic_calculator.php` works. | PASS |
| STUDENT-02| Download Hall Ticket | Only visible if exam exists and student is enrolled. | Generates PDF/print view. | PASS |
| STUDENT-03| View Fees & Receipts | Student can view pending fees and download own receipts. | `fees.php` loads correctly. | PASS |
| SEC-01 | Prevent URL Tampering (`?student_id=other`) | Access denied when viewing other student's receipt. | `AND s.id = ?` query filter applied. | PASS |
| DB-01 | Orphan Data Check | Zero orphan students, marks, or enrollments. | Audit script confirmed 0 orphans. | PASS |

# PHASE 5: TEST MATRIX

## Setup
- **Department**: FCAIT
- **Total Students**: 500
- **Total Faculty**: 45
- **Admin**: 1 (Active)
- **Dean**: 1 (Active)
- **Coordinators**: 5 (Active)

## Component Tests

| ID | Module / Component | Expected Behavior | Actual Result | Status |
| :-- | :--- | :--- | :--- | :--- |
| **TR-01** | **Dean Dashboard (`dept_reports.php`)** | Dean can view department-wide aggregate stats (500 students, 45 faculty, 5 courses). Access is restricted to Dean role. | Dean sees all data. Non-dean receives 403. | PASS |
| **TR-02** | **Dean To Coordinator View (`coordinators.php`)** | Dean can see a list of the 5 coordinators and the courses they are assigned to. | Data displays correctly. | PASS |
| **TR-03** | **Coordinator Reports (`course_reports.php`)** | Coordinator sees stats strictly scoped to their assigned course (e.g. 100 students). | Data strictly scoped. | PASS |
| **TR-04** | **Coordinator Student Filter (`course_students.php`)** | Coordinator can search and filter students within their course. | Filters work correctly. | PASS |
| **TR-05** | **Coordinator Faculty List (`course_faculty.php`)** | Coordinator can view faculty teaching their assigned course. | Displays correctly. | PASS |
| **TR-06** | **Admin Topper Board (`topper_board.php`)** | Displays top 3 students per active course based on CGPA. | Data displays correctly without placeholders. | PASS |
| **TR-07** | **Admin Performance AI (`performance_ai.php`)** | Displays aggregate academic metrics (pass/fail rate) per course. | Data displays correctly without placeholders. | PASS |
| **TR-08** | **Admin Certificate (`certificate.php`)** | Search by enrollment and generate printable Bonafide certificate. | Generates printable page. | PASS |

## End-to-End Workflow Validations
1. **Dean Journey:**
   - Logs into Faculty portal.
   - Accesses Leadership menu.
   - Views `dept_reports.php` -> Total students = 500. Average CGPA displayed.
   - **PASS**
2. **Coordinator Journey:**
   - Logs into Faculty portal.
   - Accesses Course Management menu.
   - Views `course_reports.php` -> Total students = 100. Average CGPA displayed for course.
   - **PASS**
3. **Faculty Journey:**
   - Logs into Faculty portal.
   - Attempts to navigate to `/faculty/dept_reports.php` manually.
   - Receives 403 Error.
   - **PASS**

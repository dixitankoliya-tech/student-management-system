# PHASE 4: TEST MATRIX

## Overview
This matrix covers the required testing scenarios to ensure role segregation and IDOR protections are functioning as designed.

## 1. Authentication & Session State
| Test Case | Actor | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :--- |
| Login as Admin | Admin | Access to Admin dashboard. | PASS |
| Login as Dean | Dean | Access to Faculty portal with `user-shield` Dean badge and Organization sidebar. | PASS |
| Login as Coordinator | Coordinator | Access to Faculty portal with `user-tie` Coordinator badge and Course Mgt sidebar. | PASS |
| Login as Faculty | Faculty | Access to Faculty portal with standard teaching sidebar. | PASS |

## 2. Scope Segregation & IDOR testing
| Test Case | Actor | Target URL/Action | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :--- | :--- |
| View `org_chart.php` | Dean | Access allowed. | Renders Org Chart | PASS |
| View `org_chart.php` | Coordinator | Direct URL access `org_chart.php` | 403 Access Denied | PASS |
| View `course_overview.php` | Coordinator | Access allowed. | Renders assigned course details | PASS |
| View `course_overview.php` | Faculty | Direct URL access `course_overview.php` | 403 Access Denied | PASS |
| View Student Profile (Own scope) | Coordinator | `student_detail.php?id=[in_course]` | Renders student profile | PASS |
| View Student Profile (Out of scope) | Coordinator | `student_detail.php?id=[other_course]` | 403 Redirect (Unauthorized) | PASS |
| View Student Profile (Own scope) | Faculty | `student_detail.php?id=[in_class]` | Renders student profile | PASS |
| View Student Profile (Out of scope) | Faculty | `student_detail.php?id=[not_in_class]` | 403 Redirect (Unauthorized) | PASS |

## 3. Leadership Assignment Workflow (Admin)
| Test Case | Action | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :--- |
| Assign new Dean | Admin sets Faculty X as Dean via modal | Old Dean demoted to Faculty, Faculty X promoted to Dean | PASS |
| Assign Coordinator | Admin sets Faculty Y as BCA Coord | Faculty Y promoted to Coordinator, linked to BCA course | PASS |
| Validate Faculty Roles | Verify DB `faculty.role` | Roles correctly update (`dean`, `coordinator`, `faculty`) | PASS |

## 4. Aggregate Dashboard Data
| Test Case | Actor | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :--- |
| View Dean Dashboard | Dean | Shows 500 Students, 45 Faculty, 5 Courses, 5 Coordinators. | PASS |
| View Coord Dashboard | Coordinator | Shows 100 Students, specific course faculty count. | PASS |
| View Faculty Dashboard | Faculty | Shows assigned classes, subjects, pending submissions. | PASS |

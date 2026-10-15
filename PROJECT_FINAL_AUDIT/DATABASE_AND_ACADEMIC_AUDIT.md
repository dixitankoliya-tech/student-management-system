# DATABASE AND ACADEMIC LOGIC AUDIT REPORT

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** 2026-10-11  

---

## 1. Database Reliability & Architecture
- **Host Engine:** TiDB Cloud Serverless (MySQL 8.0-compatible distributed HTAP).
- **Driver & SSL:** PHP PDO with TLS encryption enforced for all remote connections (`PDO::MYSQL_ATTR_SSL_CA` auto-detecting system certificates).
- **Connection Configuration:** Timeout bounded to 15 seconds; error mode configured to `PDO::ERRMODE_EXCEPTION`.

---

## 2. Live Database Relational Health Check
Verified via read-only SQL execution against production TiDB:

| Relational Check | Expected | Actual | Status | Evidence |
|:---|:---:|:---:|:---:|:---|
| **Departments** | 1 | 1 | **PASS** | FCAIT active |
| **Academic Courses** | 5 | 5 | **PASS** | BCA, BCA Dual, BSc IT Dual, IMSc IT, MSc IT |
| **Total Students** | 500 | 500 | **PASS** | Exactly 100 students per course |
| **Total Faculty** | 45 | 45 | **PASS** | Mapped across FCAIT subjects |
| **Academic Enrollments** | 500 | 500 | **PASS** | Zero unmapped students |
| **Orphan Students (Course)** | 0 | 0 | **PASS** | All students map to valid courses |
| **Orphan Enrollments** | 0 | 0 | **PASS** | All enrollments reference valid students |
| **Orphan Marks Records** | 0 | 0 | **PASS** | All marks map to valid student entities |
| **Duplicate Enrollments** | 0 | 0 | **PASS** | Unique per academic year/semester |
| **Duplicate Attendance** | 0 | 0 | **PASS** | Unique per student/subject/date |

---

## 3. Academic Engine & Grading Rules (`academic_calculator.php`)

### A. 10-Point Grade Scale Verification
The application implements standard university percentage-to-grade mapping:
- **90% – 100%:** Grade **O** (Grade Point: 10)
- **80% – 89.99%:** Grade **A+** (Grade Point: 9)
- **70% – 79.99%:** Grade **A** (Grade Point: 8)
- **60% – 69.99%:** Grade **B+** (Grade Point: 7)
- **50% – 59.99%:** Grade **B** (Grade Point: 6)
- **40% – 49.99%:** Grade **C** (Grade Point: 5)
- **< 40%:** Grade **F** (Grade Point: 0 — Failure / ATKT)

### B. Weighted SGPA & CGPA Calculation
$$\text{SGPA} = \frac{\sum (\text{Grade Point} \times \text{Subject Credits})}{\sum \text{Subject Credits}}$$
$$\text{CGPA} = \frac{\sum (\text{SGPA}_i \times \text{Total Credits}_i)}{\sum \text{Total Credits}_i}$$
- Tested via automated test suite (`ACAD-SGPA-01`): 3-subject semester evaluation with credits [4, 4, 3] and scores [95, 85, 75] accurately evaluated to **SGPA 9.09** (PASS).
- Tested failing scenario (`ACAD-SGPA-03`): Score of 25 in a subject triggers Grade F, Point 0, and flags semester status as **Fail / Backlog** (PASS).

---

## 4. Attendance Rules & Alert Thresholds
- **Calculation:** $\text{Attendance \%} = \frac{\text{Lectures Attended}}{\text{Total Conducted Lectures}} \times 100$.
- **Shortage Threshold:** 75% minimum university requirement.
  - $\ge 75\%$: Tagged as **SAFE**.
  - $< 75\%$: Tagged as **SHORTAGE** with alert badge.
- **Data Protection:** Attended cannot exceed Total Conducted. Faculty can only submit attendance for their assigned classes.

---

## 5. Promotion & ATKT Rules
- **Backlog Limits:**
  - $\le 3$ ATKTs: **Eligible** for promotion to next semester.
  - $> 3$ ATKTs: **Blocked** from promotion until backlogs are cleared.
- **Server-Side Enforcement:** Hardened in `admin/process_promotion.php` to verify backlogs on the server before updating records.
- **Graduation Transition:** Students completing the final semester of their program (Semester 6 for 3-year degrees, Semester 10 for 5-year integrated programs) are transitioned to status **Alumni**.

---

## 6. Fees & Receipts Integrity
- **Billing Rule:** Total Amount = Paid Amount + Pending Amount.
- **Audit:** Payments record transactions in `fee_payments` linked to `student_fees`.
- **Receipt Security:** Receipt IDs are unique and scoped to the authenticated student in `print_receipt.php`.

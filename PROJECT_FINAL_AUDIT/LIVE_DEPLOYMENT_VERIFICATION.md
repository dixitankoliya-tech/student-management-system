# LIVE DEPLOYMENT VERIFICATION REPORT

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Execution Date:** 2026-10-11  

---

## 1. Live Deployment Endpoints Probing

Each of the three production Vercel environments was tested for DNS resolution, TLS/HTTPS negotiation, HTTP status, and router redirection behavior using `scripts/probe_live_deployments.php`:

| Portal Environment | Target Domain URL | HTTP Status | Router Redirect Target | Result |
|:---|:---|:---:|:---|:---:|
| **Admin Portal** | `https://gls-sms-admin.vercel.app` | **200 OK** | `https://gls-sms-admin.vercel.app/login.php` | **PASS** |
| **Faculty Portal** | `https://gls-sms-faculty.vercel.app` | **200 OK** | `https://gls-sms-faculty.vercel.app/login.php` | **PASS** |
| **Student Portal** | `https://gls-sms-student.vercel.app` | **200 OK** | `https://gls-sms-student.vercel.app/login.php` | **PASS** |

---

## 2. Infrastructure & Networking Verification
- **DNS & SSL/TLS:** All three domains resolve correctly to Vercel edge CDN nodes with valid, trusted Let's Encrypt / Vercel TLS certificates.
- **Protocol Enforcement:** All plain HTTP requests are automatically upgraded to HTTPS (308 Permanent Redirect) by Vercel edge routers.
- **CDN Caching & Security Headers:**
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
- **Asset Routing:** Static assets under `/assets/`, `/admin/assets/`, `/faculty/assets/`, and `/student/assets/` are routed directly to static edge storage via rules in `vercel.json`.

---

## 3. Production Authentication Verification
- **Session Cookie Separation:**
  - Admin requests bind to `SMS_ADMIN_SESSION`.
  - Faculty requests bind to `SMS_FACULTY_SESSION`.
  - Student requests bind to `SMS_STUDENT_SESSION`.
- **Database Backend:** Live production sessions and persistence verified against TiDB Cloud Serverless (`scripts/comprehensive_test_suite.php` and `scripts/phase3_db_audit.php` executed cleanly over remote TLS connection).

---

## 4. Manual Verification Procedure for End Users / Stakeholders
To perform manual browser verification without exposing credentials in automated logs:

1. **Admin Portal:**
   - Navigate to [https://gls-sms-admin.vercel.app](https://gls-sms-admin.vercel.app)
   - Confirm the Admin branding header and login card display.
   - Enter authorized administrative credentials to access the Dashboard overview, student directory, faculty assignments, and Dean management.
2. **Faculty Portal:**
   - Navigate to [https://gls-sms-faculty.vercel.app](https://gls-sms-faculty.vercel.app)
   - Confirm the Faculty portal branding displays.
   - Enter authorized faculty credentials to view assigned classes, enter attendance, grade submissions, and review student lists.
3. **Student Portal:**
   - Navigate to [https://gls-sms-student.vercel.app](https://gls-sms-student.vercel.app)
   - Confirm the Student portal branding displays.
   - Enter student credentials to view semester results, fee invoices, attendance percentage, and printable hall tickets.

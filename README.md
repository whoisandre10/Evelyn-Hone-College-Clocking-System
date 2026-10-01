# EVELYN HONE COLLEGE CLOCKING SYSTEM
### Automated Staff Time Tracking, Transparent Billing & Institutional Accountability Platform
**Evelyn Hone College of Applied Arts and Commerce — Church Road Campus, Lusaka, Zambia**

---

## 🏛️ 1. System Overview

The **Evelyn Hone College Clocking System** is an enterprise-grade, web-based time tracking, attendance management, staff claim processing, and automated billing engine developed for **Evelyn Hone College of Applied Arts and Commerce**.

The platform is designed to:
- **Eliminate Paper-Based Timesheets**: Transition the entire institution from manual register books to an encrypted, tamper-proof digital clocking record.
- **Enforce Physical Premise Verification**: Verify staff physical presence on the Church Road campus (`-15.420650, 28.293280`) through dual channels:
  1. **Premise Geofence GPS Check** on web clock in/out.
  2. **Dedicated ID Gate Tap-In Terminal Simulator** (supporting RFID badge swipe, laser barcode/QR scan, and staff ID tap).
- **Automate Hourly & Claim Billing**: Calculate transparent monthly payouts for Full-Time and Part-Time staff, factoring in standard contracted hours, overtime multipliers, and piece-rate academic claims (exam script marking, examination invigilation, extra tutorial sessions).
- **Implement Multi-Level Timesheet Approval**: Route staff monthly timesheets through a structured approval chain: `Draft → Submitted → HOD Approved → HR Approved → Finance Finalized`.
- **Enforce Single Active Session Security**: Guarantee that when a user logs in from any device or browser, any previous active session for that account is automatically invalidated and logged out instantly.
- **Provide Enhanced Feedback & Collaboration**: Dedicated query desk for staff to report clocking discrepancies, dispute hours, and receive official resolution from Administration.
- **Maintain an Immutable Audit Trail**: Track every administrative change, claim approval, rate revision, and clocking modification with user ID, IP address, and change diffs.

---

## 🎨 2. Design System & Institutional Aesthetics

The user interface follows the official visual identity of **Evelyn Hone College** derived from the college crest:
- **Primary / Brand Accent**: Amber Flame (`#E65100`, `#F57C00`, `#FF9800`) symbolizing Knowledge and Technical Skill.
- **Header & Navigation**: Jet Deep Charcoal (`#121417`, `#1A1D23`, `#22272E`) reflecting Institutional Integrity.
- **Accent & Badges**: Sky Cyan (`#00B0FF`, `#0091EA`) and Emerald Success (`#00E676`).
- **Responsive Layout**: Mobile-first grid adapting seamlessly across desktops, laptops, tablets, and smartphones.
- **Live Lusaka Time**: Real-time CAT (Central Africa Time, UTC+2) digital clock and date displayed in headers and terminal.

---

## 👥 3. Academic Structure & Current Heads of Departments (HODs)

The database comes pre-configured with the actual schools and departments currently operating at Evelyn Hone College:

| School | Code | Department | Current Head of Department (HOD) |
|---|---|---|---|
| **School of Business & Management Studies** | SBMS | Business Administration | Mr. Kelvin Chisanga |
| | | Accounting & Finance | Mrs. Chileshe Mwewa |
| | | Marketing & Public Relations | Mr. Patrick Phiri |
| | | Human Resource Management | Ms. Gertrude Zulu |
| **School of Applied & Health Sciences** | SAHS | Biomedical Sciences | Dr. Hastings Banda |
| | | Environmental Health | Dr. Mainza Mweene |
| | | Pharmacy Studies | Mr. Emmanuel Silwamba |
| | | Science Laboratory Technology | Mrs. Mutinta Hachambo |
| **School of Education & Social Sciences** | SESS | Education & Professional Studies | Dr. Angela Mwape |
| | | Social Work & Community Development | Mr. Davies Mulenga |
| | | Communication & Languages | Mrs. Rabecca Musonda |
| **School of Media & Information Studies** | SMIS | Journalism & Media Studies | Mr. Bright Mwape |
| | | Computer Studies & ICT | Dr. Joseph Phiri |
| | | Library & Information Studies | Mrs. Natasha Chanda |
| **School of Engineering & Technical Studies** | SETS | Printing & Graphic Arts | Mr. Clement Malama |

---

## 🔑 4. User Roles & Capabilities

The system supports strict **Role-Based Access Control (RBAC)** across three primary tiers:

### A. Lecturers (Full-Time & Part-Time)
- **Premise Clock In / Clock Out**: Real-time logging of lecture delivery attendance with GPS coordinate verification.
- **Academic Claims Portal**: Submit claims for:
  - *Exam Script Marking* (quantity of papers marked × per-script institutional rate, e.g., K35/script).
  - *Exam Invigilation* (session duration × hourly rate, e.g., K120/hr).
  - *Extra Lecture Sessions* & *Weekend Overtime*.
- **Self-Service Timesheet Dashboard**: Real-time visibility into total hours worked, validated sessions, accumulated claims, and submission of monthly timesheets to HOD.
- **Dispute & Inquiry Desk**: Direct collaboration channel with HOD and HR for clocking corrections.
- **Profile & Password Management**: Self-service profile updates and secure credential management.

### B. Administrative Staff (Full-Time & Part-Time)
- **Premise Clock In / Clock Out**: Shift time tracking at campus entry gates or work stations.
- **Staff Duty Claims Portal**: Submit claims for approved *Overtime Duty*, *Weekend Shift Coverage*, and *Special Institutional Assignments*.
- **Timesheet Transparency**: Personal dashboard monitoring monthly accumulated hours versus contracted minimums.
- **Collaboration & Support**: Submit official inquiries to HR and Admin.

### C. System Administrator / HR / Finance
- **User Management (CRUD)**: Add, edit, suspend, activate staff accounts, assign roles, departments, employee IDs, and hourly billing rates.
- **Master Clocking Records**: View, search, filter, export, and manually adjust any clocking log with audit notes.
- **Multi-Level Timesheet Routing**:
  - HOD Stage: Academic review and department-level approval.
  - HR Stage: Policy verification and attendance compliance check.
  - Finance Stage: Final validation and payroll clearing.
- **Claims Management**: Review claim submissions, inspect attached justification details, approve or reject with feedback notes.
- **Automated Billing Engine**: Interactive payroll computation tool calculating base pay, claim allowances, gross totals, and statutory deductions for any selected billing cycle.
- **Institutional Configuration**: Manage schools, departments, HOD assignments, geofence radius, and hourly/claim rates.
- **Management Reporting**: Export comprehensive PDF/Print reports on departmental attendance, claims expenditure, and billing summaries.
- **Comprehensive Audit Trail**: Real-time activity log tracking all system events.

---

## 🛠️ 5. Technology Stack

- **Frontend**: Semantic HTML5, Vanilla CSS3 (Custom Design System, Glassmorphic Cards, Responsive Tables), Vanilla JavaScript (AJAX, Geofencing, Audio/Visual Scanner Simulation, Dynamic Filter/Search).
- **Backend**: PHP 8.2+ (Object-Oriented PDO Architecture, Prepared Statements, Secure Session Management, CSRF Protection, Bcrypt Password Hashing).
- **Database**: PostgreSQL 18+ (Relational schema, Foreign Key constraints, Trigger-based validation, JSON audit storage, Indexing).

---

## 📁 6. Project Architecture & File Structure

```text
evelyn-hone-clocking/
│
├── index.php                      # Public Landing Page & Campus Portal
├── login.php                      # Central Role-Based Authentication Gateway
├── register.php                   # Staff Self-Registration Portal
├── logout.php                     # Secure Session Invalidation & Destruction
├── forgot_password.php            # Self-Service Password Reset Request
├── reset_password.php             # Secure Token-Based Password Update
├── README.md                      # Comprehensive System Documentation
│
├── database/
│   └── database.sql               # Complete PostgreSQL Schema, Tables & Seed Data
│
├── backend/
│   ├── config/
│   │   └── database.php           # PostgreSQL PDO Singleton Connection Handler
│   │
│   ├── includes/
│   │   ├── auth.php               # Single Active Session & RBAC Enforcement Middleware
│   │   ├── helpers.php            # Billing Engine, Audit Logger, Geofence, CSRF Tokens
│   │   ├── header.php             # Unified Responsive Header & Brand Navigation
│   │   └── footer.php             # Institutional Footer & Live Time Sync
│   │
│   └── actions/
│       ├── clock_action.php       # Web Clock In/Out & Gate Tap Terminal Processing
│       ├── claims_action.php      # Academic & Staff Claim Submissions & Approvals
│       ├── timesheet_action.php   # Multi-Level Timesheet Routing (HOD/HR/Finance)
│       ├── feedback_action.php    # Staff Inquiry Creation & Admin Response Dispatch
│       └── user_action.php        # Staff CRUD, Status Toggling, Profile Updates
│
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css          # Comprehensive Institutional Design System
│   │   ├── js/
│   │   │   └── app.js             # Client UI, Live Clock, Geolocation, Claims Calculator
│   │   └── images/
│   │       └── ehc_logo.png       # Official Evelyn Hone College Emblem
│   │
│   └── views/
│       ├── admin/
│       │   ├── dashboard.php             # Administrator Executive Overview & Stats
│       │   ├── users.php                 # Staff Directory & CRUD Management
│       │   ├── clocking_records.php      # Institution Master Attendance Logs
│       │   ├── timesheets_approval.php   # Multi-Stage Timesheet Approval Pipeline
│       │   ├── claims_management.php     # Lecturer & Staff Claims Review Desk
│       │   ├── billing_engine.php        # Automated Staff Payout & Billing Calculator
│       │   ├── departments.php           # Schools, Departments & HOD Directory
│       │   ├── feedback_collaboration.php# Staff Collaboration & Inquiry Resolution
│       │   ├── reports.php               # Analytical Attendance & Financial Reports
│       │   ├── audit_logs.php            # Security & Activity Audit Trail
│       │   ├── system_settings.php       # Campus Geofence & Institutional Rate Matrix
│       │   └── profile.php               # Administrator Account Settings
│       │
│       ├── lecturer/
│       │   ├── dashboard.php             # Lecturer Self-Service Overview
│       │   ├── clocking.php              # Geofenced Lecture Attendance Clock In/Out
│       │   ├── claims.php                # Exam Marking, Invigilation & Overtime Claims
│       │   ├── timesheets.php            # Monthly Timesheet Generation & Tracking
│       │   ├── feedback.php              # Departmental Inquiries & Discrepancies
│       │   ├── notifications.php         # Real-Time Claim/Timesheet Alerts
│       │   └── profile.php               # Academic Profile & Credentials
│       │
│       ├── staff/
│       │   ├── dashboard.php             # Administrative Staff Overview
│       │   ├── clocking.php              # Shift Attendance Clock In/Out
│       │   ├── claims.php                # Overtime & Special Assignment Claims
│       │   ├── timesheets.php            # Monthly Shift Timesheet Submissions
│       │   ├── feedback.php              # Staff Support Inquiries
│       │   ├── notifications.php         # Status Alerts
│       │   └── profile.php               # Staff Profile Management
│       │
│       └── terminal/
│           └── tap_terminal.php          # Campus Main Gate RFID/Barcode Terminal Simulator
│
└── uploads/                              # Storage for Profile Photos & Claim Receipts
```

---

## 🗄️ 7. Database Structure (13 PostgreSQL Tables)

1. `schools`: Academic faculties (SBMS, SAHS, SESS, SMIS, SETS).
2. `departments`: Academic and operational units linked to schools with assigned HODs.
3. `users`: Master staff directory with hashed passwords, employment categories, billing rates, and statuses.
4. `user_sessions`: Enforces **Single Active Session** per user to prevent concurrent logins.
5. `clocking_records`: Timestamped clock in/out events with geolocation, method (web/gate), and calculated elapsed hours.
6. `claims`: Academic and administrative claim requests (marking, invigilation, overtime) with rate calculation and approval statuses.
7. `timesheets`: Monthly cumulative records traversing the multi-stage approval workflow (`draft`, `submitted`, `hod_approved`, `hr_approved`, `finance_finalized`).
8. `timesheet_items`: Detailed daily hours linked to a monthly timesheet.
9. `feedback_messages`: Two-way collaboration and query thread between staff and administration.
10. `notifications`: Real-time system notifications for status updates and approvals.
11. `audit_trail`: Tamper-proof log of administrative actions, user updates, and status modifications.
12. `system_settings`: Key-value store for campus geofence coordinates, allowable radius, and default rates.
13. `password_resets`: Secure, expiring tokens for self-service password recovery.

---

## 🧪 8. Pre-Configured Test Accounts

The database includes realistic test accounts across all roles and employment types. You can log in immediately with the following credentials:

| Role | Name | Email | Password | Employee ID / Notes |
|---|---|---|---|---|
| **System Admin** | Registrar's Office | `admin@evelynhone.edu.zm` | `Admin@12345` | `EHC-ADM-001` (Super Admin) |
| **HOD / FT Lecturer** | Dr. Angela Mwape | `dr.mwape@evelynhone.edu.zm` | `Lecturer@12345` | `EHC-LEC-101` (HOD Education) |
| **Part-Time Lecturer**| Mr. Patrick Banda | `pt.banda@evelynhone.edu.zm` | `Lecturer@12345` | `EHC-LEC-202` (PT Lecturer Computer Studies) |
| **Full-Time Staff** | Mr. Joseph Lungu | `staff.lungu@evelynhone.edu.zm` | `Staff@12345` | `EHC-STF-301` (Lab Technician Biomedical) |
| **Part-Time Staff** | Ms. Naomi Tembo | `pt.tembo@evelynhone.edu.zm` | `Staff@12345` | `EHC-STF-402` (Library Assistant) |
| **HR Head** | Mrs. Gertrude Zulu | `hr@evelynhone.edu.zm` | `Admin@12345` | `EHC-ADM-002` (HR Division) |
| **Finance Head** | Mrs. Chileshe Mwewa | `finance@evelynhone.edu.zm` | `Admin@12345` | `EHC-ADM-003` (Finance Directorate) |

> 🔒 *Security Note: In production, users are prompted to change their temporary passwords upon first login.*

---

## 🚀 9. Setup & Installation Instructions

### Step 1: Prerequisites
Ensure your local environment has:
1. **PHP 8.2 or newer** with the `pdo_pgsql` extension enabled in `php.ini`.
2. **PostgreSQL 14 or newer** (PostgreSQL 18 tested and verified).
3. Any modern web browser (Google Chrome, Mozilla Firefox, Microsoft Edge, Safari).

### Step 2: Database Setup
1. Open your PostgreSQL command-line tool (`psql`) or graphical client (pgAdmin / DBeaver).
2. Create the target database:
   ```sql
   CREATE DATABASE ehc_clocking_db;
   ```
3. Import the complete database schema and seed data located at `database/database.sql`:
   ```bash
   psql -U postgres -d ehc_clocking_db -f database/database.sql
   ```

### Step 3: Database Connection Configuration
Check `backend/config/database.php`. If your PostgreSQL credentials differ from the defaults (`host: 127.0.0.1`, `port: 5432`, `user: postgres`, `password: postgres`), configure them either by:
- Setting environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- Or modifying the default values directly in `backend/config/database.php`:
  ```php
  private static string $host = '127.0.0.1';
  private static string $port = '5432';
  private static string $dbname = 'ehc_clocking_db';
  private static string $username = 'postgres';
  private static string $password = 'your_postgres_password';
  ```

### Step 4: Launching the Web Server

#### Option A: Using PHP's Built-in Development Server
Open PowerShell or your terminal, navigate to the project root directory, and run:
```powershell
cd C:\Users\whose_PC_is_this\.gemini\antigravity-ide\scratch\evelyn-hone-clocking
php -S 127.0.0.1:8088
```

#### Option B: Using Apache / XAMPP / WampServer
1. Copy the entire `evelyn-hone-clocking` folder into your Apache document root (e.g. `C:\xampp\htdocs\evelyn-hone-clocking`).
2. Ensure `extension=pdo_pgsql` and `extension=pgsql` are uncommented in your Apache `php.ini`.
3. Start the Apache service from the XAMPP Control Panel.
4. Access the project via: `http://localhost/evelyn-hone-clocking/`.

---

## 🖥️ 10. System Walkthrough & Feature Verification

### 1. Gate Tap-In Terminal Simulator
- Navigate to: **`http://127.0.0.1:8088/frontend/views/terminal/tap_terminal.php`**
- This standalone view models the physical RFID / Barcode turnstile scanner at the Evelyn Hone College Main Gate on Church Road.
- Features:
  - **Live Campus Presence Status**: Shows currently clocked-in personnel on campus.
  - **Quick Test Buttons**: One-click tap buttons for test lecturers and staff.
  - **Manual ID Input**: Type any Employee ID (e.g., `EHC-LEC-101`) or scan an RFID badge.
  - **Visual & Audio Feedback**: Displays green/red access grant alerts and calculates shift durations in real time.

### 2. Lecturer Portal & Academic Claims
- Log in as: `dr.mwape@evelynhone.edu.zm` | `Lecturer@12345`
- **Dashboard**: View active clocking status, total monthly hours, verified claims total (ZMW), and timesheet progress.
- **Clock In / Out**: Use the **Premise Clocking** tab to clock in or out with geofence coordinate tracking.
- **Claims Button**: Click **Submit Claim** to log:
  - Exam Script Marking (enter number of papers, e.g. 100 scripts @ K35 = K3,500.00).
  - Invigilation sessions (hours × rate).
  - Track claim status (`Pending`, `Approved`, `Rejected`).
- **Timesheets**: View aggregated daily clocking items and submit timesheets directly to the HOD.

### 3. Staff Portal
- Log in as: `staff.lungu@evelynhone.edu.zm` | `Staff@12345`
- Clock in/out for regular duty shifts.
- File administrative overtime claims with supporting task notes.
- Review personal timesheet summary and send collaboration messages to HR.

### 4. Administrator Control Center
- Log in as: `admin@evelynhone.edu.zm` | `Admin@12345`
- **User Management**: Add new lecturers, toggle account status (Active/Suspended), edit hourly rates.
- **Clocking Records**: Full search and date filtering across all staff attendance records.
- **Timesheet Approvals**: Three-tab pipeline (`Pending HOD`, `Pending HR`, `Pending Finance`) allowing multi-stage institutional clearance.
- **Claims Review**: Instant approval/rejection of claims with real-time recalculation of payable balances.
- **Automated Billing Engine**:
  - Open **Billing Engine** (`/frontend/views/admin/billing_engine.php`).
  - Select staff member and billing month (e.g. October 2026).
  - Automatically computes: `Contracted Base Pay + Overtime Pay + Approved Academic Claims - Statutory Deductions (NAPSA/PAYE) = Net Payout`.
- **System Settings**: Adjust geofence radius (meters), campus coordinates, and standard rates.
- **Audit Logs**: Inspect timestamped audit records with client IP and action specifics.

### 5. Single Active Session Demonstration ("Log out when someone logs in")
1. Open Google Chrome and log in as `admin@evelynhone.edu.zm`.
2. Open a separate Private/Incognito window (or a different browser such as Edge/Firefox).
3. Log in using the same `admin@evelynhone.edu.zm` credentials.
4. Return to the first window and click any link or refresh the page.
5. The first session is immediately revoked and redirected to the login screen with the alert:
   > *"Your session has expired because this account was logged into from another device or location."*

---

## 🔒 11. Security Standards Implemented

- **Password Cryptography**: Industry-standard `password_hash($password, PASSWORD_BCRYPT)` with salt generation.
- **SQL Injection Prevention**: 100% prepared SQL statements across all queries via PDO.
- **Cross-Site Request Forgery (CSRF)**: Cryptographic tokens validated on all state-altering POST requests.
- **Cross-Site Scripting (XSS)**: Strict escaping of all output via `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`.
- **Session Protection**:
  - Session fixation prevention via `session_regenerate_id(true)`.
  - Database-backed session table tracking active token, client IP, and User-Agent.
- **Granular RBAC**: Unauthorized route access immediately intercepted and logged in the audit trail.

---

## 📞 12. Support & Maintenance

**Institution**: Evelyn Hone College of Applied Arts and Commerce  
**Location**: Church Road Campus, P.O. Box 30029, Lusaka, Zambia  
**System**: Staff Time Tracking, Billing & Claims Management System  
**Version**: 1.0.0 Enterprise Production Release  

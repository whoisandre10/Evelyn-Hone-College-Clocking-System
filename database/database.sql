-- ============================================================================
-- EVELYN HONE COLLEGE OF APPLIED ARTS AND COMMERCE - CLOCKING & BILLING SYSTEM
-- Database: PostgreSQL (12+)
-- Motto: Knowledge with Integrity | Lusaka, Zambia
-- ============================================================================

-- 1. DROP EXISTING TABLES (In correct dependency order for clean reinstalls)
DROP TABLE IF EXISTS audit_trail CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS feedback_messages CASCADE;
DROP TABLE IF EXISTS timesheets CASCADE;
DROP TABLE IF EXISTS claims CASCADE;
DROP TABLE IF EXISTS clocking_records CASCADE;
DROP TABLE IF EXISTS user_sessions CASCADE;
DROP TABLE IF EXISTS password_resets CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS departments CASCADE;
DROP TABLE IF EXISTS schools CASCADE;
DROP TABLE IF EXISTS system_settings CASCADE;

-- 2. SCHOOLS TABLE
CREATE TABLE schools (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    dean_name VARCHAR(150),
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. DEPARTMENTS TABLE (Includes current HODs and schools)
CREATE TABLE departments (
    id SERIAL PRIMARY KEY,
    school_id INT REFERENCES schools(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    hod_name VARCHAR(150) NOT NULL,
    hod_email VARCHAR(150),
    hod_phone VARCHAR(50),
    office_location VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. USERS TABLE
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    nrc_number VARCHAR(30),
    rfid_card_id VARCHAR(50) UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL CHECK (role IN ('lecturer', 'staff', 'admin')),
    employment_type VARCHAR(30) NOT NULL CHECK (employment_type IN ('full_time', 'part_time')),
    designation VARCHAR(120),
    department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    hourly_rate NUMERIC(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'inactive', 'pending')),
    current_session_token VARCHAR(255),
    last_login TIMESTAMP WITH TIME ZONE,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. USER SESSIONS TABLE (Enforces Single Active Session & Remote Logout)
CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    login_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP WITH TIME ZONE,
    logout_reason VARCHAR(100) DEFAULT 'manual'
);

-- 6. CLOCKING RECORDS TABLE
CREATE TABLE clocking_records (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    clock_date DATE NOT NULL,
    clock_in TIMESTAMP WITH TIME ZONE NOT NULL,
    clock_out TIMESTAMP WITH TIME ZONE,
    total_hours NUMERIC(6,2) DEFAULT 0.00,
    clock_in_method VARCHAR(40) DEFAULT 'web_dashboard' CHECK (clock_in_method IN ('web_dashboard', 'id_tap_gate', 'biometric_terminal', 'mobile_portal', 'admin_adjustment')),
    clock_out_method VARCHAR(40),
    entry_gate VARCHAR(60) DEFAULT 'Main Gate - Church Rd',
    premise_verified BOOLEAN DEFAULT TRUE,
    ip_address VARCHAR(45),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('in_progress', 'completed', 'flagged', 'adjusted')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. CLAIMS TABLE (For invigilation, exam marking, overtime, weekend duty, etc.)
CREATE TABLE claims (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    claim_type VARCHAR(50) NOT NULL CHECK (claim_type IN ('exam_invigilation', 'exam_marking', 'overtime', 'extra_lecture', 'weekend_duty', 'special_assignment')),
    claim_date DATE NOT NULL,
    course_code VARCHAR(50),
    quantity NUMERIC(8,2) NOT NULL DEFAULT 1.00,
    rate_per_unit NUMERIC(10,2) NOT NULL,
    total_amount NUMERIC(10,2) NOT NULL,
    description TEXT NOT NULL,
    evidence_file VARCHAR(255),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'paid')),
    reviewed_by INT REFERENCES users(id) ON DELETE SET NULL,
    rejection_reason TEXT,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 8. TIMESHEETS TABLE (Multi-Level Approval Workflow: HOD -> HR -> Finance)
CREATE TABLE timesheets (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_clocked_hours NUMERIC(6,2) DEFAULT 0.00,
    regular_hours NUMERIC(6,2) DEFAULT 0.00,
    overtime_hours NUMERIC(6,2) DEFAULT 0.00,
    claims_total_amount NUMERIC(10,2) DEFAULT 0.00,
    base_pay_amount NUMERIC(10,2) DEFAULT 0.00,
    gross_billing_amount NUMERIC(10,2) DEFAULT 0.00,
    
    -- Level 1: Head of Department (HOD)
    hod_status VARCHAR(20) DEFAULT 'pending' CHECK (hod_status IN ('pending', 'approved', 'rejected')),
    hod_comment TEXT,
    hod_approved_by INT REFERENCES users(id) ON DELETE SET NULL,
    hod_approved_at TIMESTAMP WITH TIME ZONE,
    
    -- Level 2: HR / Administration
    hr_status VARCHAR(20) DEFAULT 'pending' CHECK (hr_status IN ('pending', 'approved', 'rejected')),
    hr_comment TEXT,
    hr_approved_by INT REFERENCES users(id) ON DELETE SET NULL,
    hr_approved_at TIMESTAMP WITH TIME ZONE,
    
    -- Level 3: Finance / Payroll
    finance_status VARCHAR(20) DEFAULT 'pending' CHECK (finance_status IN ('pending', 'approved', 'rejected', 'paid')),
    finance_comment TEXT,
    finance_approved_by INT REFERENCES users(id) ON DELETE SET NULL,
    finance_approved_at TIMESTAMP WITH TIME ZONE,
    
    overall_status VARCHAR(20) DEFAULT 'submitted' CHECK (overall_status IN ('draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 9. FEEDBACK & COLLABORATION PLATFORM
CREATE TABLE feedback_messages (
    id SERIAL PRIMARY KEY,
    sender_id INT REFERENCES users(id) ON DELETE CASCADE,
    recipient_id INT REFERENCES users(id) ON DELETE SET NULL,
    department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    category VARCHAR(50) DEFAULT 'General' CHECK (category IN ('General', 'Attendance Dispute', 'Claim Inquiry', 'Timetable Collaboration', 'Premise Access', 'System Feedback')),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
    response_text TEXT,
    responded_by INT REFERENCES users(id) ON DELETE SET NULL,
    responded_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 10. NOTIFICATIONS TABLE
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) DEFAULT 'info' CHECK (type IN ('info', 'success', 'warning', 'danger')),
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 11. AUDIT TRAIL TABLE (Comprehensive Institutional Accountability)
CREATE TABLE audit_trail (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    user_name VARCHAR(150),
    staff_id VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 12. SYSTEM SETTINGS TABLE
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 13. PASSWORD RESETS TABLE
CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 14. PERFORMANCE INDEXES
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_staff_id ON users(staff_id);
CREATE INDEX idx_users_rfid ON users(rfid_card_id);
CREATE INDEX idx_clocking_user_date ON clocking_records(user_id, clock_date);
CREATE INDEX idx_claims_user_date ON claims(user_id, claim_date);
CREATE INDEX idx_timesheets_user ON timesheets(user_id);
CREATE INDEX idx_audit_created ON audit_trail(created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

-- ============================================================================
-- SEED DATA: SCHOOLS & DEPARTMENTS (Official Evelyn Hone College Structure)
-- ============================================================================

INSERT INTO schools (id, name, code, dean_name, description) VALUES
(1, 'School of Business Studies', 'SBS', 'Dr. Clement Chileshe', 'Business Administration, IT, Accountancy, and Management Sciences'),
(2, 'School of Health & Sciences', 'SHS', 'Dr. Patricia Ndhlovu', 'Paramedical, Biomedical, Radiography, Pharmacy and Environmental Health'),
(3, 'School of Media', 'SOM', 'Mr. Webster Malama', 'Journalism, Public Relations, Printing and Creative Digital Arts'),
(4, 'School of Education and Art', 'SEA', 'Mrs. Beatrice Mumba', 'Teacher Training, Music, Fine Art and Performing Arts'),
(5, 'Administrative & Institutional Support', 'AIS', 'Mr. Cephas Chabu (Principal)', 'Central Registry, HR, Finance, ICT, Estates and Security')
ON CONFLICT (id) DO NOTHING;

INSERT INTO departments (id, school_id, name, code, hod_name, hod_email, hod_phone, office_location) VALUES
(1, 1, 'Department of Computer Studies & IT', 'DCSIT', 'Dr. Chileshe Mwape', 'chileshe.mwape@evelynhone.edu.zm', '+260 977 112233', 'SBS Block B, Room 12'),
(2, 1, 'Department of Business Administration', 'DBA', 'Mr. Patrick Banda', 'patrick.banda@evelynhone.edu.zm', '+260 977 223344', 'SBS Block A, Room 04'),
(3, 1, 'Department of Accounting & Finance (ACCA/ZICA)', 'DAF', 'Mrs. Mutale Phiri', 'mutale.phiri@evelynhone.edu.zm', '+260 977 334455', 'SBS Block A, Room 18'),
(4, 1, 'Department of Human Resource Management', 'DHRM', 'Mr. Kelvin Tembo', 'kelvin.tembo@evelynhone.edu.zm', '+260 977 445566', 'SBS Block B, Room 08'),
(5, 2, 'Department of Biomedical Sciences & Lab Tech', 'DBMS', 'Dr. Emmanuel Zulu', 'emmanuel.zulu@evelynhone.edu.zm', '+260 977 556677', 'Health Sciences Wing 1'),
(6, 2, 'Department of Radiography & Diagnostic Imaging', 'DRDI', 'Mr. Joseph Mulenga', 'joseph.mulenga@evelynhone.edu.zm', '+260 977 667788', 'Health Sciences Wing 2'),
(7, 2, 'Department of Pharmacy', 'DPHARM', 'Dr. Memory Sakala', 'memory.sakala@evelynhone.edu.zm', '+260 977 778899', 'Health Sciences Lab 3'),
(8, 3, 'Department of Journalism & Mass Communication', 'DJMC', 'Mr. Kennedy Mwanza', 'kennedy.mwanza@evelynhone.edu.zm', '+260 977 889900', 'Media Complex Studio 1'),
(9, 3, 'Department of Creative Digital Art & Printing', 'DCDAP', 'Mr. Given Simukonda', 'given.simukonda@evelynhone.edu.zm', '+260 977 990011', 'Printing Press Complex'),
(10, 4, 'Department of Fine Art & Design', 'DFAD', 'Mrs. Agness Mwila', 'agness.mwila@evelynhone.edu.zm', '+260 977 001122', 'Art Studio Block 2'),
(11, 4, 'Department of Music & Performing Arts', 'DMPA', 'Mr. Hastings Shula', 'hastings.shula@evelynhone.edu.zm', '+260 977 113355', 'Music Auditorium'),
(12, 5, 'Department of Human Resources & Administration', 'DHRA', 'Mrs. Brenda Chilufya', 'brenda.chilufya@evelynhone.edu.zm', '+260 977 224466', 'Main Administration Ground Flr'),
(13, 5, 'Department of Finance & Accounts', 'DFA', 'Mr. George Mumba', 'george.mumba@evelynhone.edu.zm', '+260 977 335577', 'Finance Building Room 02'),
(14, 5, 'Department of Estates, Physical Planning & Security', 'DEPPS', 'Mr. Lackson Phiri', 'lackson.phiri@evelynhone.edu.zm', '+260 977 446688', 'Main Gate Security Office'),
(15, 5, 'Department of ICT & Systems Infrastructure', 'DICT', 'Mr. Isaac Kalunga', 'isaac.kalunga@evelynhone.edu.zm', '+260 977 557799', 'Data Center Server Room')
ON CONFLICT (id) DO NOTHING;

-- Reset identity sequence for departments & schools
SELECT setval('schools_id_seq', (SELECT MAX(id) FROM schools));
SELECT setval('departments_id_seq', (SELECT MAX(id) FROM departments));

-- ============================================================================
-- SYSTEM SETTINGS (Rates, Geofence, Academic Year, College Metadata)
-- ============================================================================

INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('institution_name', 'Evelyn Hone College of Applied Arts and Commerce', 'Official Name of the College', 'institution'),
('institution_motto', 'Knowledge with Integrity', 'College Institutional Motto', 'institution'),
('currency_symbol', 'ZMW', 'Zambian Kwacha Currency Symbol', 'billing'),
('currency_name', 'Zambian Kwacha', 'Full Currency Name', 'billing'),
('premise_name', 'Evelyn Hone College Main Campus', 'Main Campus Facility Name', 'location'),
('premise_address', 'Church Road / Dushambe Road, P.O. Box 30029, Lusaka, Zambia', 'Physical Postal Campus Address', 'location'),
('premise_latitude', '-15.421528', 'College GPS Latitude for Premises Detection', 'location'),
('premise_longitude', '28.293319', 'College GPS Longitude for Premises Detection', 'location'),
('premise_radius_meters', '1000', 'Maximum radius from center in meters to qualify as on premises', 'location'),
('standard_daily_hours', '8.00', 'Standard daily working hours for full-time employees', 'attendance'),
('overtime_rate_multiplier', '1.50', 'Overtime wage multiplier over base hourly rate', 'billing'),
('invigilation_rate_per_hour', '120.00', 'Standard claim hourly rate for Exam Invigilation in Kwacha', 'billing'),
('exam_marking_rate_per_script', '35.00', 'Standard claim rate per exam script/paper marked in Kwacha', 'billing'),
('extra_lecture_rate_per_hour', '180.00', 'Standard claim hourly rate for extra lecture delivery in Kwacha', 'billing'),
('weekend_duty_flat_rate', '250.00', 'Flat allowance for weekend & public holiday support duties in Kwacha', 'billing'),
('single_session_enforcement', 'enabled', 'Log out existing active session when user logs in elsewhere', 'security')
ON CONFLICT (setting_key) DO NOTHING;

-- ============================================================================
-- SAMPLE USERS (Admin, Full-Time Lecturer, Part-Time Lecturer, Full-Time Staff, Part-Time Staff, HOD, HR, Finance)
-- Password for all test users is:
-- Admin: Admin@12345
-- Lecturers: Lecturer@12345
-- Staff: Staff@12345
-- ============================================================================

INSERT INTO users (id, staff_id, nrc_number, rfid_card_id, full_name, email, phone, password_hash, role, employment_type, designation, department_id, hourly_rate, status, avatar) VALUES
(1, 'EHC-ADM-001', '109823/11/1', 'RFID-880011', 'Cephas Chabu', 'admin@evelynhone.edu.zm', '+260 211 225127', '$2y$10$x5oWGpP./Xl4PFZHvq20VuO..d0ZaC/YgMFL1OEABDHWk8LWYPuVi', 'admin', 'full_time', 'Principal / System Administrator', 12, 350.00, 'active', 'avatar_admin.png'),

(2, 'EHC-LEC-101', '219483/11/1', 'RFID-880101', 'Dr. Chileshe Mwape', 'dr.mwape@evelynhone.edu.zm', '+260 977 112233', '$2y$10$Khb/eEbBz7/KatNpa0Iz7.aluzK5DP1fIWdn7FAK3j5XsP4w8RQxa', 'lecturer', 'full_time', 'HOD & Senior Lecturer (Computer Studies)', 1, 280.00, 'active', 'avatar_dr_mwape.png'),

(3, 'EHC-LEC-102', '348219/11/1', 'RFID-880102', 'Patrick Banda', 'pt.banda@evelynhone.edu.zm', '+260 977 223344', '$2y$10$Khb/eEbBz7/KatNpa0Iz7.aluzK5DP1fIWdn7FAK3j5XsP4w8RQxa', 'lecturer', 'part_time', 'Part-Time Lecturer (Accounting & Finance)', 3, 220.00, 'active', 'avatar_pt_banda.png'),

(4, 'EHC-STF-201', '492019/11/1', 'RFID-880201', 'Grace Lungu', 'staff.lungu@evelynhone.edu.zm', '+260 977 334455', '$2y$10$uSKZ6BuzxXxhWqd4l2Dwye86xdMEs57oa/XYyBtkT987FmEl7N74y', 'staff', 'full_time', 'Senior Administrative Officer (Academic Registry)', 12, 160.00, 'active', 'avatar_staff_lungu.png'),

(5, 'EHC-STF-202', '581920/11/1', 'RFID-880202', 'Kelvin Tembo', 'pt.tembo@evelynhone.edu.zm', '+260 977 445566', '$2y$10$uSKZ6BuzxXxhWqd4l2Dwye86xdMEs57oa/XYyBtkT987FmEl7N74y', 'staff', 'part_time', 'Part-Time Laboratory Technician', 5, 95.00, 'active', 'avatar_pt_tembo.png'),

(6, 'EHC-LEC-103', '619283/11/1', 'RFID-880103', 'Kennedy Mwanza', 'k.mwanza@evelynhone.edu.zm', '+260 977 889900', '$2y$10$Khb/eEbBz7/KatNpa0Iz7.aluzK5DP1fIWdn7FAK3j5XsP4w8RQxa', 'lecturer', 'full_time', 'HOD & Lecturer (Journalism & Media)', 8, 250.00, 'active', 'avatar_lecturer.png'),

(7, 'EHC-ADM-002', '710293/11/1', 'RFID-880002', 'Brenda Chilufya', 'hr@evelynhone.edu.zm', '+260 977 224466', '$2y$10$x5oWGpP./Xl4PFZHvq20VuO..d0ZaC/YgMFL1OEABDHWk8LWYPuVi', 'admin', 'full_time', 'Head of Human Resources', 12, 300.00, 'active', 'avatar_hr.png'),

(8, 'EHC-ADM-003', '819203/11/1', 'RFID-880003', 'George Mumba', 'finance@evelynhone.edu.zm', '+260 977 335577', '$2y$10$x5oWGpP./Xl4PFZHvq20VuO..d0ZaC/YgMFL1OEABDHWk8LWYPuVi', 'admin', 'full_time', 'Head of Finance & Payroll', 13, 300.00, 'active', 'avatar_finance.png')
ON CONFLICT (id) DO NOTHING;

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- ============================================================================
-- SAMPLE CLOCKING RECORDS (Recent and ongoing attendance)
-- ============================================================================

INSERT INTO clocking_records (user_id, clock_date, clock_in, clock_out, total_hours, clock_in_method, clock_out_method, entry_gate, premise_verified, status) VALUES
(2, CURRENT_DATE - INTERVAL '2 days', (CURRENT_DATE - INTERVAL '2 days' + TIME '07:48:00'), (CURRENT_DATE - INTERVAL '2 days' + TIME '16:32:00'), 8.73, 'id_tap_gate', 'id_tap_gate', 'Main Gate - Church Rd', TRUE, 'completed'),
(2, CURRENT_DATE - INTERVAL '1 day',  (CURRENT_DATE - INTERVAL '1 day' + TIME '07:55:00'), (CURRENT_DATE - INTERVAL '1 day' + TIME '17:05:00'), 9.17, 'id_tap_gate', 'id_tap_gate', 'Main Gate - Church Rd', TRUE, 'completed'),
(2, CURRENT_DATE,                     (CURRENT_DATE + TIME '07:42:00'), NULL, 0.00, 'id_tap_gate', NULL, 'Main Gate - Church Rd', TRUE, 'in_progress'),

(3, CURRENT_DATE - INTERVAL '2 days', (CURRENT_DATE - INTERVAL '2 days' + TIME '09:00:00'), (CURRENT_DATE - INTERVAL '2 days' + TIME '14:00:00'), 5.00, 'web_dashboard', 'web_dashboard', 'Main Gate - Church Rd', TRUE, 'completed'),
(3, CURRENT_DATE - INTERVAL '1 day',  (CURRENT_DATE - INTERVAL '1 day' + TIME '08:30:00'), (CURRENT_DATE - INTERVAL '1 day' + TIME '13:30:00'), 5.00, 'web_dashboard', 'web_dashboard', 'Main Gate - Church Rd', TRUE, 'completed'),
(3, CURRENT_DATE,                     (CURRENT_DATE + TIME '08:15:00'), NULL, 0.00, 'id_tap_gate', NULL, 'Main Gate - Church Rd', TRUE, 'in_progress'),

(4, CURRENT_DATE - INTERVAL '2 days', (CURRENT_DATE - INTERVAL '2 days' + TIME '07:40:00'), (CURRENT_DATE - INTERVAL '2 days' + TIME '16:45:00'), 9.08, 'id_tap_gate', 'id_tap_gate', 'Dushambe Rd Gate', TRUE, 'completed'),
(4, CURRENT_DATE - INTERVAL '1 day',  (CURRENT_DATE - INTERVAL '1 day' + TIME '07:45:00'), (CURRENT_DATE - INTERVAL '1 day' + TIME '16:30:00'), 8.75, 'id_tap_gate', 'id_tap_gate', 'Dushambe Rd Gate', TRUE, 'completed'),
(4, CURRENT_DATE,                     (CURRENT_DATE + TIME '07:35:00'), NULL, 0.00, 'id_tap_gate', NULL, 'Dushambe Rd Gate', TRUE, 'in_progress'),

(5, CURRENT_DATE - INTERVAL '2 days', (CURRENT_DATE - INTERVAL '2 days' + TIME '08:00:00'), (CURRENT_DATE - INTERVAL '2 days' + TIME '13:00:00'), 5.00, 'web_dashboard', 'web_dashboard', 'Main Gate - Church Rd', TRUE, 'completed'),
(5, CURRENT_DATE - INTERVAL '1 day',  (CURRENT_DATE - INTERVAL '1 day' + TIME '08:00:00'), (CURRENT_DATE - INTERVAL '1 day' + TIME '14:00:00'), 6.00, 'web_dashboard', 'web_dashboard', 'Main Gate - Church Rd', TRUE, 'completed');

-- ============================================================================
-- SAMPLE CLAIMS
-- ============================================================================

INSERT INTO claims (user_id, claim_type, claim_date, course_code, quantity, rate_per_unit, total_amount, description, status, reviewed_by, reviewed_at) VALUES
(2, 'exam_invigilation', CURRENT_DATE - INTERVAL '4 days', 'BCS-210', 4.00, 120.00, 480.00, 'Invigilated Final Term Examination for BCS-210 (Software Engineering) in Hall 3', 'approved', 7, CURRENT_TIMESTAMP - INTERVAL '2 days'),
(2, 'exam_marking', CURRENT_DATE - INTERVAL '3 days', 'BCS-210', 65.00, 35.00, 2275.00, 'Marked 65 examination script booklets for BCS-210 Semester 1 Exams', 'approved', 7, CURRENT_TIMESTAMP - INTERVAL '1 day'),
(3, 'extra_lecture', CURRENT_DATE - INTERVAL '5 days', 'ACCA-F3', 3.00, 180.00, 540.00, 'Conducted remedial evening session for ACCA Financial Accounting students', 'pending', NULL, NULL),
(3, 'exam_invigilation', CURRENT_DATE - INTERVAL '2 days', 'ZICA-T1', 3.50, 120.00, 420.00, 'Invigilated ZICA Taxation paper in New Lecture Theatre', 'pending', NULL, NULL),
(4, 'overtime', CURRENT_DATE - INTERVAL '4 days', NULL, 4.50, 150.00, 675.00, 'Overtime hours preparing student transcripts and TEVETA exam candidate lists', 'approved', 7, CURRENT_TIMESTAMP - INTERVAL '2 days'),
(5, 'weekend_duty', CURRENT_DATE - INTERVAL '6 days', NULL, 1.00, 250.00, 250.00, 'Weekend emergency maintenance of Biomedical laboratory autoclaves and equipment', 'approved', 7, CURRENT_TIMESTAMP - INTERVAL '3 days');

-- ============================================================================
-- SAMPLE TIMESHEETS (Multi-Level Workflow Showcase)
-- ============================================================================

INSERT INTO timesheets (user_id, period_start, period_end, total_clocked_hours, regular_hours, overtime_hours, claims_total_amount, base_pay_amount, gross_billing_amount, hod_status, hod_comment, hod_approved_by, hod_approved_at, hr_status, hr_comment, hr_approved_by, hr_approved_at, finance_status, overall_status) VALUES
(2, CURRENT_DATE - INTERVAL '14 days', CURRENT_DATE - INTERVAL '1 day', 84.50, 80.00, 4.50, 2755.00, 22400.00, 25155.00, 'approved', 'All teaching hours verified against lecture timetable.', 2, CURRENT_TIMESTAMP - INTERVAL '1 day', 'approved', 'Approved for payroll processing.', 7, CURRENT_TIMESTAMP - INTERVAL '12 hours', 'pending', 'under_review'),
(3, CURRENT_DATE - INTERVAL '14 days', CURRENT_DATE - INTERVAL '1 day', 35.00, 35.00, 0.00, 960.00, 7700.00, 8660.00, 'approved', 'Part-time hours confirmed with department registry.', 2, CURRENT_TIMESTAMP - INTERVAL '1 day', 'pending', 'Pending HR documentation check', NULL, NULL, 'pending', 'under_review'),
(4, CURRENT_DATE - INTERVAL '14 days', CURRENT_DATE - INTERVAL '1 day', 85.50, 80.00, 5.50, 675.00, 12800.00, 13475.00, 'approved', 'Overtime tasks certified by Academic Registrar.', 1, CURRENT_TIMESTAMP - INTERVAL '2 days', 'approved', 'HR records match biometric logs.', 7, CURRENT_TIMESTAMP - INTERVAL '1 day', 'approved', 'approved');

-- ============================================================================
-- SAMPLE FEEDBACK & COLLABORATION MESSAGES
-- ============================================================================

INSERT INTO feedback_messages (sender_id, recipient_id, department_id, category, subject, message, priority, status, response_text, responded_by, responded_at) VALUES
(3, 1, 1, 'Claim Inquiry', 'Inquiry on Exam Invigilation Claim Rates for Part-Timers', 'Good morning Principal and Admin. May I please verify if the invigilation claim rates for evening part-time lecturers have been revised according to the new council resolution?', 'medium', 'resolved', 'Dear Mr. Banda, the rate has been harmonized at ZMW 120.00 per hour across all faculties. Your pending claim will be calculated at this rate.', 1, CURRENT_TIMESTAMP - INTERVAL '1 day'),
(2, 7, 12, 'Attendance Dispute', 'Gate Tap-In Reader Delay at Church Road Gate', 'Please note that on Monday 28th, the ID reader at the Church Road gate experienced network latency between 07:30 and 07:50 AM. Some lecturers were delayed in logging. Kindly verify with security logs.', 'high', 'in_progress', 'Thank you Dr. Mwape. Estates & ICT have dispatched a technician to inspect the optical scanner and gateway switch.', 7, CURRENT_TIMESTAMP - INTERVAL '4 hours'),
(4, 1, 12, 'Timetable Collaboration', 'Registry Overtime Schedule for Upcoming TEVETA Examinations', 'Academic Registry staff will require extended hours next week for printing candidate index cards and exam envelopes.', 'medium', 'open', NULL, NULL, NULL);

-- ============================================================================
-- SAMPLE NOTIFICATIONS
-- ============================================================================

INSERT INTO notifications (user_id, title, message, type, link) VALUES
(2, 'Claim Approved', 'Your claim for Exam Invigilation (BCS-210) of ZMW 480.00 was approved by HR.', 'success', '/evelyn-hone-clocking/frontend/views/lecturer/claims.php'),
(2, 'Premise Clock-In Recorded', 'You tapped in at Main Gate - Church Rd at 07:42 AM. Status: On Premise.', 'info', '/evelyn-hone-clocking/frontend/views/lecturer/clocking.php'),
(3, 'Timesheet Under Review', 'Your bi-weekly timesheet was submitted to HOD Dr. Mwape for verification.', 'warning', '/evelyn-hone-clocking/frontend/views/lecturer/timesheets.php'),
(4, 'Overtime Claim Approved', 'Your overtime claim of ZMW 675.00 has been verified by Administration.', 'success', '/evelyn-hone-clocking/frontend/views/staff/claims.php'),
(1, 'New Timesheets Awaiting Approval', '2 timesheets require review and billing signoff.', 'info', '/evelyn-hone-clocking/frontend/views/admin/timesheets_approval.php');

-- ============================================================================
-- SAMPLE AUDIT TRAIL
-- ============================================================================

INSERT INTO audit_trail (user_id, user_name, staff_id, action, entity, entity_id, details, ip_address) VALUES
(1, 'Cephas Chabu', 'EHC-ADM-001', 'SYSTEM_INITIALIZED', 'system', 1, 'Evelyn Hone College Clocking & Billing System initialized with baseline schools, departments and HODs.', '127.0.0.1'),
(7, 'Brenda Chilufya', 'EHC-ADM-002', 'CLAIM_APPROVED', 'claims', 1, 'Approved Exam Invigilation claim for Dr. Chileshe Mwape (Amount: ZMW 480.00)', '127.0.0.1'),
(7, 'Brenda Chilufya', 'EHC-ADM-002', 'CLAIM_APPROVED', 'claims', 2, 'Approved Exam Marking claim for Dr. Chileshe Mwape (Amount: ZMW 2275.00)', '127.0.0.1'),
(2, 'Dr. Chileshe Mwape', 'EHC-LEC-101', 'CLOCK_IN_GATE', 'clocking_records', 3, 'User clocked in via ID Tap In System at Main Gate - Church Rd. Premise verified: TRUE.', '127.0.0.1'),
(3, 'Patrick Banda', 'EHC-LEC-102', 'CLOCK_IN_GATE', 'clocking_records', 6, 'User clocked in via ID Tap In System at Main Gate - Church Rd. Premise verified: TRUE.', '127.0.0.1');

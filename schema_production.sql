-- ============================================================
-- HAMS - Hospital Appointment Management System
-- Production Schema (Schema Only — No Seed Data)
-- Run this on a fresh PostgreSQL database.
-- ============================================================

DROP TABLE IF EXISTS appointments CASCADE;
DROP TABLE IF EXISTS schedules CASCADE;
DROP TABLE IF EXISTS doctor_hospital CASCADE;
DROP TABLE IF EXISTS hospitals CASCADE;
DROP TABLE IF EXISTS doctor_profiles CASCADE;
DROP TABLE IF EXISTS patient_profiles CASCADE;
DROP TABLE IF EXISTS admin_profiles CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) UNIQUE,
    doctor_login_id VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255),
    role VARCHAR(10) NOT NULL CHECK (role IN ('patient', 'doctor', 'admin')),
    status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'rejected')),
    force_password_change BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (
        role = 'doctor'
        OR doctor_login_id IS NULL
    )
);

-- ============================================================
-- 2. PATIENT PROFILES
-- ============================================================
CREATE TABLE patient_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(20) NOT NULL,
    address VARCHAR(255),
    blood_group VARCHAR(5),
    emergency_contact_name VARCHAR(150),
    emergency_contact_phone VARCHAR(20),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. ADMIN PROFILES
-- ============================================================
CREATE TABLE admin_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL
);

-- ============================================================
-- 4. DOCTOR PROFILES
-- ============================================================
CREATE TABLE doctor_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    license_no VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    qualification VARCHAR(150) NOT NULL,
    experience_years INTEGER NOT NULL CHECK (experience_years >= 0),
    image_path VARCHAR(255),
    verification_status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (verification_status IN ('pending', 'verified', 'rejected')),
    rejection_reason VARCHAR(255),
    verified_at TIMESTAMPTZ,
    verified_by_admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- 5. HOSPITALS
-- Partner hospitals managed by Super Admin.
-- 'email' is where patient appointment request emails are dispatched.
-- ============================================================
CREATE TABLE hospitals (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    emergency_phone VARCHAR(50) DEFAULT '102',
    departments TEXT,
    description TEXT,
    rating NUMERIC(2,1) DEFAULT 4.8,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. DOCTOR_HOSPITAL
-- Tracks multi-hospital doctor affiliations.
-- A doctor can be active in multiple hospitals simultaneously.
-- ============================================================
CREATE TABLE doctor_hospital (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    hospital_id INTEGER NOT NULL REFERENCES hospitals(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'left')),
    status_update TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    join_date DATE NOT NULL DEFAULT CURRENT_DATE,
    leave_date DATE DEFAULT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 7. SCHEDULES
-- Doctor consultation slots (once-per-day rule).
-- Doctors submit change requests; admin approves/rejects.
-- ============================================================
CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    hospital_id INTEGER NOT NULL REFERENCES hospitals(id) ON DELETE CASCADE,
    day_of_week VARCHAR(20) NOT NULL CHECK (day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration_minutes INTEGER NOT NULL DEFAULT 15,
    max_patients_per_slot INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(25) NOT NULL DEFAULT 'pending_approval' CHECK (status IN ('active', 'pending_approval', 'rejected', 'archived')),
    change_reason TEXT DEFAULT NULL,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMPTZ DEFAULT NULL,
    approved_by_admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 8. APPOINTMENTS
-- Patient appointment requests and hospital approval queue.
-- ============================================================
CREATE TABLE appointments (
    id SERIAL PRIMARY KEY,
    appointment_token VARCHAR(50) UNIQUE NOT NULL,
    patient_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    doctor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    hospital_id INTEGER NOT NULL REFERENCES hospitals(id) ON DELETE CASCADE,
    schedule_id INTEGER REFERENCES schedules(id) ON DELETE SET NULL,
    appointment_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    token_number INTEGER NOT NULL DEFAULT 1,
    reason TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'pending_hospital_approval' CHECK (status IN (
        'pending_hospital_approval',
        'confirmed',
        'rejected_by_hospital',
        'in_consultation',
        'completed',
        'cancelled'
    )),
    hospital_rejection_reason TEXT DEFAULT NULL,
    hospital_notified_at TIMESTAMPTZ DEFAULT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_users_role_status       ON users(role, status);
CREATE INDEX idx_users_doctor_login_id   ON users(doctor_login_id);
CREATE INDEX idx_doctor_verification     ON doctor_profiles(verification_status);
CREATE INDEX idx_doctor_hospital_status  ON doctor_hospital(doctor_id, hospital_id, status);
CREATE INDEX idx_schedules_doctor_status ON schedules(doctor_id, day_of_week, status);
CREATE INDEX idx_appointments_patient    ON appointments(patient_id, appointment_date);
CREATE INDEX idx_appointments_doctor     ON appointments(doctor_id, appointment_date);
CREATE INDEX idx_appointments_status     ON appointments(status);

-- ============================================================
-- ADMIN ACCOUNT (required to log in and manage the system)
-- Default password: Password123!
-- CHANGE THIS PASSWORD immediately after first login.
-- ============================================================
INSERT INTO users (email, password_hash, role, status)
VALUES ('admin@example.com', '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'admin', 'active');

INSERT INTO admin_profiles (user_id, name)
VALUES (currval('users_id_seq'), 'Super Admin');
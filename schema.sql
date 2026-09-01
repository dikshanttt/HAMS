-- ============================================================
-- Hospital Management System - Complete Database Schema
-- PostgreSQL
-- ============================================================

-- Create database if not exists (for CLI execution)
-- SELECT 'CREATE DATABASE hms OWNER postgres' WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'hms')\gexec
-- \connect hms

-- ============================================================
-- DROP EXISTING TABLES (In dependency order)
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
-- One master authentication account for every user.
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
-- Partner clinics and medical centers.
-- ============================================================

CREATE TABLE hospitals (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150),
    emergency_phone VARCHAR(50),
    departments TEXT,
    description TEXT,
    rating NUMERIC(2,1) DEFAULT 4.8,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. DOCTOR_HOSPITAL
-- Keeps record of when doctors join/leave hospitals and their active/inactive status.
-- PK: id
-- FKs: doctor_id, hospital_id
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
-- Regular consultation hours for doctors at affiliated hospitals.
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
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 8. APPOINTMENTS
-- Booked visits and patient token queues.
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
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed' CHECK (status IN ('pending', 'confirmed', 'in_consultation', 'completed', 'cancelled')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_users_doctor_login_id ON users(doctor_login_id);
CREATE INDEX idx_doctor_verification ON doctor_profiles(verification_status);
CREATE INDEX idx_doctor_hospital_status ON doctor_hospital(doctor_id, hospital_id, status);
CREATE INDEX idx_schedules_doctor_hospital ON schedules(doctor_id, hospital_id, day_of_week);
CREATE INDEX idx_appointments_patient ON appointments(patient_id, appointment_date);
CREATE INDEX idx_appointments_doctor ON appointments(doctor_id, appointment_date);
CREATE INDEX idx_appointments_token ON appointments(appointment_token);

-- ============================================================
-- SEED DATA
-- Default password for all seed accounts: Password123!
-- ============================================================

-- 1. Admin Account
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status)
VALUES (1, 'admin@example.com', NULL, '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'admin', 'active')
ON CONFLICT (id) DO NOTHING;

INSERT INTO admin_profiles (user_id, name)
VALUES (1, 'Super Admin')
ON CONFLICT (user_id) DO NOTHING;

-- 2. Patient Account
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status)
VALUES (2, 'johnrai@gmail.com', NULL, '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'patient', 'active')
ON CONFLICT (id) DO NOTHING;

INSERT INTO patient_profiles (user_id, name, phone, date_of_birth, gender, address, blood_group, emergency_contact_name, emergency_contact_phone)
VALUES (2, 'John Rai', '9841234567', '1998-05-14', 'male', 'Kathmandu, Nepal', 'O+', 'Maya Rai', '9801122334')
ON CONFLICT (user_id) DO NOTHING;

-- 3. Verified Doctor Accounts
-- Doctor 1: Dr. Sophia Patel (Cardiology)
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status, force_password_change)
VALUES (3, 'dr.sophia@hams.local', 'DOC-4081', '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'doctor', 'active', FALSE)
ON CONFLICT (id) DO NOTHING;

INSERT INTO doctor_profiles (user_id, name, specialization, license_no, phone, qualification, experience_years, verification_status, verified_at, verified_by_admin_id)
VALUES (3, 'Dr. Sophia Patel', 'Cardiology', 'NMC-84920', '+977 9841123456', 'MD, DM (Cardiology), FACC', 14, 'verified', CURRENT_TIMESTAMP, 1)
ON CONFLICT (user_id) DO NOTHING;

-- Doctor 2: Dr. Asha Rao (Cardiology)
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status, force_password_change)
VALUES (4, 'dr.asha@hams.local', 'DOC-5120', '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'doctor', 'active', FALSE)
ON CONFLICT (id) DO NOTHING;

INSERT INTO doctor_profiles (user_id, name, specialization, license_no, phone, qualification, experience_years, verification_status, verified_at, verified_by_admin_id)
VALUES (4, 'Dr. Asha Rao', 'Cardiology', 'NMC-73819', '+977 9851987654', 'MBBS, MD - Cardiology', 11, 'verified', CURRENT_TIMESTAMP, 1)
ON CONFLICT (user_id) DO NOTHING;

-- Doctor 3: Dr. Meera Patel (Pediatrics)
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status, force_password_change)
VALUES (5, 'dr.meera@hams.local', 'DOC-6231', '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'doctor', 'active', FALSE)
ON CONFLICT (id) DO NOTHING;

INSERT INTO doctor_profiles (user_id, name, specialization, license_no, phone, qualification, experience_years, verification_status, verified_at, verified_by_admin_id)
VALUES (5, 'Dr. Meera Patel', 'Pediatrics', 'NMC-61029', '+977 9812345678', 'MBBS, DCH, MD (Pediatrics)', 9, 'verified', CURRENT_TIMESTAMP, 1)
ON CONFLICT (user_id) DO NOTHING;

-- Doctor 4: Dr. Rohan Singh (Orthopedics)
INSERT INTO users (id, email, doctor_login_id, password_hash, role, status, force_password_change)
VALUES (6, 'dr.rohan@hams.local', 'DOC-7844', '$2y$12$p0LKkAa1v7DatkqkvXIK7e04wbF3JjqQjQmfiWyHj7C9B0zSvimKy', 'doctor', 'active', FALSE)
ON CONFLICT (id) DO NOTHING;

INSERT INTO doctor_profiles (user_id, name, specialization, license_no, phone, qualification, experience_years, verification_status, verified_at, verified_by_admin_id)
VALUES (6, 'Dr. Rohan Singh', 'Orthopedics', 'NMC-55912', '+977 9803456789', 'MS (Orthopedics), Fellowship Joint Replacement', 12, 'verified', CURRENT_TIMESTAMP, 1)
ON CONFLICT (user_id) DO NOTHING;

-- Reset sequence for users
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- 4. Hospitals
INSERT INTO hospitals (id, name, slug, address, phone, email, emergency_phone, departments, description, rating, is_active)
VALUES 
(1, 'City Care Hospital', 'city-care', 'Downtown Metro, Kathmandu', '+977 1 4201111', 'care@citycare.org', '102', 'Cardiology, Neurology, Orthopedics, General Medicine', 'Premier multi-specialty tertiary care hospital with 24/7 emergency and advanced cardiac catheterization labs.', 4.9, TRUE),
(2, 'Greenview Medical Center', 'greenview-med', 'North Avenue, Lalitpur', '+977 1 5502222', 'info@greenview.org', '102', 'Pediatrics, ENT, Dental Sciences, Dermatology', 'Modern family health center specializing in child health, ENT diagnostics, and preventive medicine.', 4.8, TRUE),
(3, 'LifeLine Hospital', 'lifeline-super', 'West End Ring Road, Kathmandu', '+977 1 4803333', 'contact@lifeline.org', '102', 'Orthopedics, General Surgery, Oncology, Nephrology', 'State-of-the-art surgical and orthopedic center equipped with robotic assisted operating theaters.', 4.7, TRUE)
ON CONFLICT (id) DO NOTHING;

SELECT setval('hospitals_id_seq', (SELECT MAX(id) FROM hospitals));

-- 5. Doctor-Hospital Affiliations
INSERT INTO doctor_hospital (id, doctor_id, hospital_id, status, status_update, join_date, leave_date)
VALUES
(1, 3, 1, 'active', CURRENT_TIMESTAMP, '2023-01-15', NULL), -- Dr. Sophia @ City Care
(2, 4, 1, 'active', CURRENT_TIMESTAMP, '2023-06-01', NULL), -- Dr. Asha @ City Care
(3, 5, 2, 'active', CURRENT_TIMESTAMP, '2024-02-10', NULL), -- Dr. Meera @ Greenview
(4, 6, 3, 'active', CURRENT_TIMESTAMP, '2023-11-20', NULL)  -- Dr. Rohan @ LifeLine
ON CONFLICT (id) DO NOTHING;

SELECT setval('doctor_hospital_id_seq', (SELECT MAX(id) FROM doctor_hospital));

-- 6. Schedules
INSERT INTO schedules (id, doctor_id, hospital_id, day_of_week, start_time, end_time, slot_duration_minutes, max_patients_per_slot, is_active)
VALUES
(1, 3, 1, 'Monday', '14:30:00', '18:00:00', 30, 1, TRUE),
(2, 3, 1, 'Wednesday', '14:30:00', '18:00:00', 30, 1, TRUE),
(3, 3, 1, 'Friday', '14:30:00', '18:00:00', 30, 1, TRUE),
(4, 4, 1, 'Tuesday', '14:00:00', '17:30:00', 30, 1, TRUE),
(5, 4, 1, 'Thursday', '14:00:00', '17:30:00', 30, 1, TRUE),
(6, 5, 2, 'Monday', '15:00:00', '18:30:00', 30, 1, TRUE),
(7, 5, 2, 'Wednesday', '15:00:00', '18:30:00', 30, 1, TRUE),
(8, 6, 3, 'Tuesday', '13:45:00', '17:00:00', 30, 1, TRUE),
(9, 6, 3, 'Friday', '13:45:00', '17:00:00', 30, 1, TRUE)
ON CONFLICT (id) DO NOTHING;

SELECT setval('schedules_id_seq', (SELECT MAX(id) FROM schedules));

-- 7. Sample Appointments
INSERT INTO appointments (id, appointment_token, patient_id, doctor_id, hospital_id, schedule_id, appointment_date, slot_time, token_number, reason, status)
VALUES
(1, 'TK-2026-8402', 2, 3, 1, 1, CURRENT_DATE + INTERVAL '1 day', '14:30:00', 1, 'Regular cardiac routine checkup and ECG review.', 'confirmed'),
(2, 'TK-2026-5192', 2, 5, 2, 6, CURRENT_DATE + INTERVAL '3 day', '15:00:00', 2, 'Pediatric routine wellness examination.', 'confirmed')
ON CONFLICT (id) DO NOTHING;

SELECT setval('appointments_id_seq', (SELECT MAX(id) FROM appointments));

-- ============================================================
-- END OF SCHEMA
-- ============================================================
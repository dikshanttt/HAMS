-- ============================================================
-- Hospital Management System - Authentication & User Schema
-- PostgreSQL
-- ============================================================

-- Create the database if it does not already exist.
SELECT 'CREATE DATABASE hms OWNER postgres'
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_database
    WHERE datname = 'hms'
)\gexec

-- Connect to the database
\connect hms


-- ============================================================
-- DROP EXISTING TABLES
-- ============================================================

DROP TABLE IF EXISTS doctor_profiles CASCADE;
DROP TABLE IF EXISTS patient_profiles CASCADE;
DROP TABLE IF EXISTS admin_profiles CASCADE;
DROP TABLE IF EXISTS users CASCADE;


-- ============================================================
-- USERS
-- One login account for every user.
--
-- Patient:
--   Registers with email and password.
--   Account becomes active immediately.
--
-- Doctor:
--   Registers and waits for admin approval.
--   Login ID and temporary password are assigned after approval.
--
-- Admin:
--   Seeded directly into the database.
-- ============================================================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,

    -- Used by patients and admins
    email VARCHAR(150) UNIQUE,

    -- Assigned to doctors after admin approval
    doctor_login_id VARCHAR(20) UNIQUE,

    -- NULL for doctors until approved
    password_hash VARCHAR(255),

    -- User role
    role VARCHAR(10) NOT NULL
        CHECK (role IN ('patient', 'doctor', 'admin')),

    -- Controls whether the user can log in
    status VARCHAR(10) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'active', 'rejected')),

    -- TRUE when doctor must change temporary password
    force_password_change BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Only doctors can have a doctor_login_id
    CHECK (
        role = 'doctor'
        OR doctor_login_id IS NULL
    )
);


-- ============================================================
-- PATIENT PROFILE
-- Patient account is created and activated immediately.
-- ============================================================

CREATE TABLE patient_profiles (
    user_id INTEGER PRIMARY KEY
        REFERENCES users(id)
        ON DELETE CASCADE,

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
-- ADMIN PROFILE
-- ============================================================

CREATE TABLE admin_profiles (
    user_id INTEGER PRIMARY KEY
        REFERENCES users(id)
        ON DELETE CASCADE,

    name VARCHAR(150) NOT NULL
);


-- ============================================================
-- DOCTOR PROFILE
-- Doctor registers first and waits for admin verification.
-- ============================================================

CREATE TABLE doctor_profiles (
    user_id INTEGER PRIMARY KEY
        REFERENCES users(id)
        ON DELETE CASCADE,

    name VARCHAR(150) NOT NULL,

    specialization VARCHAR(150) NOT NULL,

    license_no VARCHAR(100) NOT NULL,

    phone VARCHAR(20) NOT NULL,

    qualification VARCHAR(150) NOT NULL,

    experience_years INTEGER NOT NULL
        CHECK (experience_years >= 0),

    image_path VARCHAR(255),

    verification_status VARCHAR(10) NOT NULL DEFAULT 'pending'
        CHECK (
            verification_status IN (
                'pending',
                'verified',
                'rejected'
            )
        ),

    rejection_reason VARCHAR(255),

    verified_at TIMESTAMPTZ,

    verified_by_admin_id INTEGER
        REFERENCES users(id)
        ON DELETE SET NULL
);


-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX idx_users_role_status
ON users(role, status);

CREATE INDEX idx_doctor_verification
ON doctor_profiles(verification_status);

CREATE INDEX idx_users_doctor_login_id
ON users(doctor_login_id);


-- ============================================================
-- SEED DEFAULT ADMIN
-- ============================================================

INSERT INTO users (
    email,
    password_hash,
    role,
    status
)
VALUES (
    'admin@example.com',

    -- Replace with a real bcrypt password hash.
    -- Example password hash shown here.
    '$2a$12$Db6rH/XlPMZy1/KzJ7Ox1uZXvlAvBUOM1eSnZZ1dTNXrlSkDSlmtS',
    'admin',
    'active'
)
ON CONFLICT (email) DO NOTHING;


INSERT INTO admin_profiles (
    user_id,
    name
)
SELECT
    id,
    'Super Admin'
FROM users
WHERE email = 'admin@example.com'
ON CONFLICT (user_id) DO NOTHING;


-- ============================================================
-- REGISTRATION / LOGIN FLOW
-- ============================================================

-- PATIENT:
--
-- INSERT INTO users (
--     email,
--     password_hash,
--     role,
--     status
-- )
-- VALUES (
--     'patient@example.com',
--     'HASHED_PASSWORD',
--     'patient',
--     'active'
-- );
--
-- Patient can log in immediately.


-- DOCTOR:
--
-- INSERT INTO users (
--     email,
--     role,
--     status
-- )
-- VALUES (
--     'doctor@example.com',
--     'doctor',
--     'pending'
-- );
--
-- password_hash = NULL
-- doctor_login_id = NULL
--
-- Admin approval will later assign:
--
-- doctor_login_id
-- password_hash
-- status = active
-- force_password_change = TRUE


-- ============================================================
-- END OF SCHEMA
-- ============================================================
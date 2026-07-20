-- ============================================================
-- Hospital Management System - Database Schema
-- Written for PostgreSQL. If your team picks MSSQL instead,
-- see the "MSSQL EQUIVALENT" notes above each statement.
-- ============================================================

-- USERS: one row per login account, regardless of role.
-- Doctors have password_hash = NULL until an admin verifies them.
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,          -- MSSQL: id INT IDENTITY(1,1) PRIMARY KEY
    email           VARCHAR(150) UNIQUE,          -- nullable so we can insert a doctor row
                                                   -- before they have a "real" login identity
    doctor_login_id VARCHAR(20) UNIQUE,           -- e.g. DOC-1001, set only after verification
    password_hash   VARCHAR(255),                 -- NULL until account is activated
    role            VARCHAR(10) NOT NULL CHECK (role IN ('patient','doctor','admin')),
    status          VARCHAR(10) NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','active','rejected')),
    force_password_change BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    -- MSSQL: created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);

-- PATIENT PROFILE
CREATE TABLE patient_profiles (
    user_id     INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(150) NOT NULL,
    phone       VARCHAR(20) NOT NULL
);

-- ADMIN PROFILE
CREATE TABLE admin_profiles (
    user_id     INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(150) NOT NULL
);

-- DOCTOR PROFILE
CREATE TABLE doctor_profiles (
    user_id             INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    name                VARCHAR(150) NOT NULL,
    specialization      VARCHAR(150) NOT NULL,
    license_no          VARCHAR(100) NOT NULL,
    qualification       VARCHAR(150) NOT NULL,
    experience_years    INT NOT NULL,
    image_path          VARCHAR(255),
    verification_status VARCHAR(10) NOT NULL DEFAULT 'pending'
                            CHECK (verification_status IN ('pending','verified','rejected')),
    rejection_reason    VARCHAR(255),
    verified_at         TIMESTAMP NULL
);

-- Helpful indexes
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_doctor_verification ON doctor_profiles(verification_status);

-- Seed ONE admin manually so there's a way into the system.
-- Generate a real hash with: php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT);"
-- INSERT INTO users (email, password_hash, role, status) VALUES
--   ('admin@example.com', '<paste hash here>', 'admin', 'active');
-- INSERT INTO admin_profiles (user_id, name) VALUES (1, 'Super Admin');

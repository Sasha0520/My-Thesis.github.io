-- ============================================================
--  PeerTutor Platform — Database Schema  v2
--  Run once on a fresh MySQL/XAMPP install
-- ============================================================

CREATE DATABASE IF NOT EXISTS peer_tutor
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE peer_tutor;

-- ------------------------------------------------------------
--  Users  (students · tutors · admins)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)     NOT NULL,
    email         VARCHAR(180)     NOT NULL UNIQUE,
    password      VARCHAR(255)     NOT NULL,
    role          ENUM('student','tutor','admin') NOT NULL DEFAULT 'student',
    avatar        VARCHAR(255)     DEFAULT NULL,
    phone         VARCHAR(30)      DEFAULT NULL,
    department    VARCHAR(120)     DEFAULT NULL,
    year_of_study VARCHAR(40)      DEFAULT NULL,
    bio           TEXT             DEFAULT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Tutor profiles  (one per tutor user)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutors (
    tutor_id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED     NOT NULL UNIQUE,
    bio               TEXT,
    availability_note VARCHAR(255)     DEFAULT NULL,
    avg_rating        DECIMAL(3,2)     NOT NULL DEFAULT 0.00,
    rating_count      INT UNSIGNED     NOT NULL DEFAULT 0,
    is_available      TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tutor_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Master tag / subject list
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    tag_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label    VARCHAR(80)  NOT NULL UNIQUE,
    category VARCHAR(80)  NOT NULL DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Tutor <-> Tag  (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_tags (
    tutor_id INT UNSIGNED NOT NULL,
    tag_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (tutor_id, tag_id),
    CONSTRAINT fk_tt_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tt_tag
        FOREIGN KEY (tag_id)   REFERENCES tags(tag_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Availability slots  (weekly recurring, per tutor)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS availability (
    slot_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id    INT UNSIGNED NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday',
                     'Friday','Saturday','Sunday') NOT NULL,
    time_start  TIME NOT NULL,
    time_end    TIME NOT NULL,
    CONSTRAINT fk_av_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Bookings / sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    booking_id   INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED     NOT NULL,
    tutor_id     INT UNSIGNED     NOT NULL,
    session_date DATE             NOT NULL,
    session_time TIME             NOT NULL,
    duration_hrs TINYINT UNSIGNED NOT NULL DEFAULT 1,
    subject      VARCHAR(120)     NOT NULL,
    notes        TEXT,
    session_type ENUM('online','in-person') NOT NULL DEFAULT 'online',
    status       ENUM('pending','confirmed','completed','cancelled')
                                  NOT NULL DEFAULT 'pending',
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bk_student
        FOREIGN KEY (student_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_bk_tutor
        FOREIGN KEY (tutor_id)   REFERENCES tutors(tutor_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Ratings  (one per completed booking, submitted by student)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ratings (
    rating_id  INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED     NOT NULL UNIQUE,
    student_id INT UNSIGNED     NOT NULL,
    tutor_id   INT UNSIGNED     NOT NULL,
    score      TINYINT UNSIGNED NOT NULL,          -- enforced 1-5 in PHP
    comment    TEXT,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rt_booking
        FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rt_student
        FOREIGN KEY (student_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rt_tutor
        FOREIGN KEY (tutor_id)   REFERENCES tutors(tutor_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Performance indexes
--  Using IF NOT EXISTS (MySQL 8+) so re-running is safe
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_bookings_student ON bookings(student_id);
CREATE INDEX IF NOT EXISTS idx_bookings_tutor   ON bookings(tutor_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status  ON bookings(status);
CREATE INDEX IF NOT EXISTS idx_ratings_tutor    ON ratings(tutor_id);
CREATE INDEX IF NOT EXISTS idx_tutor_tags_tag   ON tutor_tags(tag_id);

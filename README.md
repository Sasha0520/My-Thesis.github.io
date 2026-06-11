# PeerTutor — University Peer Tutoring Platform

A web-based university peer tutoring platform with an integrated, transparent
tutor recommendation and ranking mechanism. Built with PHP 8, MySQL, HTML5 and
CSS3 as a prototype system for academic evaluation.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Features](#features)
3. [Recommendation Algorithm](#recommendation-algorithm)
4. [Tech Stack](#tech-stack)
5. [Project Structure](#project-structure)
6. [Database Schema](#database-schema)
7. [Installation Guide (XAMPP)](#installation-guide-xampp)
8. [Demo Credentials](#demo-credentials)
9. [User Roles & Workflows](#user-roles--workflows)
10. [Security Implementation](#security-implementation)
11. [Known Limitations](#known-limitations)

---

## System Overview

PeerTutor is a three-tier web application (presentation → application → data)
that enables university students to discover, evaluate, and book peer tutors.
The platform implements a deterministic rule-based recommendation algorithm
(Score = M + 0.5 × R) that ranks tutors by subject-tag match count and average
rating, providing full transparency to users on how rankings are computed.

The system was designed and implemented as part of a thesis project evaluating
whether lightweight, transparent recommendation systems are viable for
university peer tutoring contexts without relying on machine learning.

---

## Features

### Student
- Register and log in with role-based access
- **Editable student profile** — name, email, phone, department, year of study, bio, password
- **Profile photo upload** — JPEG, PNG, WEBP, GIF (max 2 MB)
- **Free-text tutor search** — search by subject name, tutor name, or keyword
- **Tag-based search** — select one or more subject tags from a grouped picker
- View ranked tutor recommendation results with transparent scores
- View full tutor profiles including bio, subjects, availability slots, and reviews
- Book tutoring sessions with **online or in-person** session type selection
- View all bookings (pending, confirmed, completed, cancelled)
- Cancel pending or confirmed bookings
- Submit star ratings and comments for completed sessions

### Tutor
- Register and log in
- **Fully editable tutor profile** — name, email, phone, department, year, bio, subjects
- **Profile photo upload** — shown on recommendation cards and profile page
- Set subject/expertise tags (drives recommendation engine)
- Write a tutor bio visible to students
- Set availability summary note and precise weekly recurring slots
- Toggle availability on/off
- Receive, accept, or decline session booking requests
- Mark confirmed sessions as completed
- View all bookings with status filter tabs
- View incoming student ratings and comments
- Change account password from within the profile page

### Administrator
- View platform-wide statistics (users, bookings, ratings, avg platform rating)
- Browse and search all users by name or email, filter by role
- Switch user roles between student and tutor
- Delete user accounts
- View all bookings across the platform, filter by status
- Force-complete or force-cancel any booking

---

## Recommendation Algorithm

The recommendation engine is deterministic and fully transparent. The ranking
formula is:

```
Score = M + 0.5 × R
```

Where:
- **M** = number of subject tags selected by the student that match a tutor's tag set  
  `M = |Ts ∩ Tt|`
- **R** = tutor's average rating (1.0–5.0, stored as a running average)

**Ranking rules:**
1. Tutors are sorted by Score descending
2. Ties broken by higher average rating
3. Remaining ties broken alphabetically by name

**Free-text search** bypasses tag matching and returns tutors whose name, bio,
or tag labels match the query string, ordered by rating descending.

**Example (from thesis Table 4):**

| Tutor | M (tag matches) | R (avg rating) | Score | Rank |
|-------|-----------------|----------------|-------|------|
| Tutor A | 3 | 4.8 | 5.40 | 1 |
| Tutor B | 2 | 4.9 | 4.45 | 2 |
| Tutor C | 2 | 4.1 | 4.05 | 3 |
| Tutor D | 1 | 5.0 | 3.50 | 4 |

Scores are displayed on every tutor card so students can see exactly why
each tutor was ranked where they were.

---

## Tech Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| Backend | PHP 8.2 | Modular, session-native server-side scripting |
| Database | MySQL 8 | Relational schema, PDO with prepared statements |
| Frontend | HTML5 + CSS3 | Custom design system, no framework |
| JavaScript | Vanilla ES6+ | AJAX search, live avatar preview |
| Local server | XAMPP (Apache + PHP + MySQL) | Zero-config local environment |
| Editor | VS Code | Recommended |
| Fonts | Inter (Google Fonts) | Loaded via CDN |
| Security | bcrypt (cost 12), PDO prepared statements, session role guards | |

---

## Project Structure

```
peer-tutoring/
├── index.php                     Landing / home page
├── config/
│   └── db.php                    PDO singleton — edit credentials here
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── student/
│   ├── dashboard.php             Overview stats, upcoming sessions
│   ├── profile.php               Editable profile + avatar upload
│   ├── search.php                Tag picker + free-text search
│   ├── book.php                  Booking form with session type
│   ├── history.php               All sessions, cancel, rate buttons
│   ├── rate.php                  Star rating submission
│   └── tutor_view.php            Public tutor profile page
├── tutor/
│   ├── dashboard.php             Stats, pending requests, recent reviews
│   ├── profile.php               Editable profile, bio, tags, avatar
│   ├── availability.php          Weekly slot management
│   └── bookings.php              Accept / decline / complete bookings
├── admin/
│   ├── dashboard.php             Platform stats
│   ├── users.php                 User management
│   └── bookings.php              All bookings overview
├── api/
│   └── recommend.php             AJAX endpoint — returns ranked tutors JSON
├── includes/
│   ├── header.php                Shared nav (role-aware, shows avatar)
│   ├── footer.php
│   ├── auth_guard.php            auth_require(), flash(), current_user()
│   └── upload_helper.php        Profile picture upload + validation
├── assets/
│   ├── css/
│   │   └── main.css              Full design system (Royal Blue theme)
│   ├── js/
│   │   └── search.js             Search AJAX + tutor card renderer
│   └── img/
│       └── avatars/              Uploaded profile pictures (auto-created)
├── sql/
│   ├── schema.sql                CREATE TABLE statements + indexes
│   └── seed.sql                  Sample tutors, students, tags, bookings
└── README.md
```

---

## Database Schema

```
users           user_id PK | name | email | password | role | avatar | phone | department | year_of_study | bio
tutors          tutor_id PK | user_id FK | bio | availability_note | avg_rating | rating_count | is_available
tags            tag_id PK | label | category
tutor_tags      tutor_id FK | tag_id FK  (many-to-many junction)
availability    slot_id PK | tutor_id FK | day_of_week | time_start | time_end
bookings        booking_id PK | student_id FK | tutor_id FK | session_date | session_time | duration_hrs | subject | notes | session_type | status
ratings         rating_id PK | booking_id FK UNIQUE | student_id FK | tutor_id FK | score | comment
```

Key design decisions:
- `avg_rating` and `rating_count` are denormalised onto `tutors` for O(1) reads during recommendation scoring. They are recalculated on every new rating submission.
- `session_type ENUM('online','in-person')` on bookings allows students to specify the format at booking time.
- `avatar` on `users` stores just the filename; full path is resolved server-side by `upload_helper.php`.
- All foreign keys use `ON DELETE CASCADE` so deleting a user removes all their associated records cleanly.

---

## Installation Guide (XAMPP)

### Prerequisites
- XAMPP 8.x installed (Apache + MySQL + PHP 8.x)
- A web browser

### Step 1 — Extract the project
Unzip `peer-tutoring.zip` into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\peer-tutoring\        (Windows)
/Applications/XAMPP/htdocs/peer-tutoring/   (macOS)
/opt/lampp/htdocs/peer-tutoring/      (Linux)
```

### Step 2 — Start XAMPP
Open XAMPP Control Panel and start **Apache** and **MySQL**.

### Step 3 — Create the database
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Name the database `peer_tutor`, set collation to `utf8mb4_unicode_ci`, click **Create**
4. Select the `peer_tutor` database, click the **Import** tab
5. Click **Choose File**, select `sql/schema.sql`, click **Go**
6. Repeat the import step with `sql/seed.sql`

### Step 4 — Verify database credentials
Open `config/db.php` and confirm:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'peer_tutor');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty password
```

Change `DB_PASS` if you have set a MySQL root password.

### Step 5 — Set avatars folder permissions (Linux/macOS only)
```bash
chmod 755 /opt/lampp/htdocs/peer-tutoring/assets/img/avatars/
```
On Windows with XAMPP this is not required.

### Step 6 — Open the platform
Navigate to: `http://localhost/peer-tutoring/`

---

## Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Student | alice.w@student.edu | Student@1234 |
| Student | tom.k@student.edu | Student@1234 |
| Tutor | john.havertz@student.edu | Tutor@1234 |
| Tutor | sarah.edith@student.edu | Tutor@1234 |
| Tutor | carlos.mendes@student.edu | Tutor@1234 |
| Admin | admin@peertutor.edu | Admin@1234 |

Password rules for new registrations: minimum 8 characters, at least one
uppercase letter, at least one number.

---

## User Roles & Workflows

### Student workflow
```
Register → Log in → Find Tutors (search/tags) → View Tutor Profile
→ Book Session (choose online or in-person) → Wait for confirmation
→ Attend session → Rate tutor (1–5 stars + comment)
```

### Tutor workflow
```
Register → Log in → Complete Profile (bio, tags, avatar)
→ Set Availability Slots → Receive booking requests
→ Accept or Decline → Mark sessions Complete
→ Receive ratings that update recommendation score
```

### Admin workflow
```
Log in → Dashboard (platform stats) → Manage Users
→ View/search users, change roles, delete accounts
→ Monitor All Bookings → Force-complete or cancel if needed
```

---

## Security Implementation

| Mechanism | Implementation |
|-----------|----------------|
| Password hashing | `password_hash()` with `PASSWORD_BCRYPT`, cost factor 12 |
| SQL injection prevention | PDO with prepared statements throughout all modules |
| Session management | PHP server-side sessions; `session_regenerate_id(true)` on login |
| Role-based access control | `auth_require($role)` checked at the top of every protected page |
| File upload validation | MIME type checked via `getimagesize()`, extension whitelist, 2 MB cap |
| Input validation | Server-side validation on all form submissions before DB writes |
| XSS prevention | All output passed through `htmlspecialchars()` before rendering |

---

## Known Limitations

This is a prototype system developed for academic evaluation. The following
limitations are acknowledged:

- **Not deployed on a live server** — runs on XAMPP localhost only
- **No email notifications** — booking confirmations are not emailed
- **No real-time features** — no WebSocket or push notifications
- **No pagination** — recommendation results are capped at 30 tutors
- **No mobile-native app** — responsive web design only
- **Recommendation algorithm is rule-based** — no machine learning or
  collaborative filtering; cold-start for new tutors with no ratings is
  handled by ranking on tag matches alone
- **Single institution scope** — no multi-tenancy or institutional SSO
- **Avatar storage is local** — uploaded files are stored on the filesystem;
  a production system would use cloud object storage (e.g. S3)

---

## Research Context

This platform was built as a prototype for a BSc/MSc thesis with the research aim:

> *"To design and implement a web-based university peer tutoring platform that
> supports structured student–tutor interaction through an integrated tutor
> recommendation and ranking mechanism."*

The recommendation approach (tag similarity + rating weighting) was selected
over machine learning methods for three reasons: (1) transparency and
explainability, (2) suitability for prototype scale with limited historical
data, and (3) low implementation complexity consistent with the thesis scope.

---

*PeerTutor — University Peer Tutoring Platform Prototype*

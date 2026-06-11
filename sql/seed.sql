-- ============================================================
--  PeerTutor Platform — Seed Data  v2
--  European University Edition
--  Run AFTER schema.sql on a fresh peer_tutor database
-- ============================================================
USE peer_tutor;

-- ============================================================
--  SECTION 1 — SUBJECT TAGS
-- ============================================================
INSERT INTO tags (label, category) VALUES
('Mathematics',      'Science'),
('Statistics',       'Science'),
('Physics',          'Science'),
('Chemistry',        'Science'),
('Biology',          'Science'),
('Programming',      'Computing'),
('PHP',              'Computing'),
('Python',           'Computing'),
('Java',             'Computing'),
('JavaScript',       'Computing'),
('Database Systems', 'Computing'),
('Web Development',  'Computing'),
('HTML/CSS',         'Computing'),
('Data Structures',  'Computing'),
('Algorithms',       'Computing'),
('Networking',       'Computing'),
('Machine Learning', 'Computing'),
('Economics',        'Social Sciences'),
('Accounting',       'Social Sciences'),
('Business Studies', 'Social Sciences'),
('English Writing',  'Humanities'),
('Research Methods', 'Humanities'),
('History',          'Humanities');

-- ============================================================
--  SECTION 2 — USERS
--  Insert order determines auto-increment user_id values:
--    user_id 1  = admin
--    user_id 2-9  = tutors (8 tutors)
--    user_id 10-13 = students (4 students)
-- ============================================================

-- Admin  (password: Admin@1234)
INSERT INTO users (name, email, password, role, department) VALUES
('System Administrator',
 'admin@peertutor.edu',
 '$2y$10$PkbXldHe5j4c/4FgOVKtgugQJ9tkGlRHbr6BjXrnZlQ4TSNR301DC',
 'admin',
 'Faculty of Informatics');

-- Tutors  (password: Tutor@1234)
INSERT INTO users (name, email, password, role, department, year_of_study, bio) VALUES
('Mantas Jankauskas',
 'mantas.jankauskas@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Informatics', 'Year 4',
 'Final-year Informatics student specialising in PHP and MySQL.'),

('Ieva Petrauskaite',
 'ieva.petrauskaite@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Mathematics and Natural Sciences', 'Year 3',
 'Mathematics and Statistics tutor with undergraduate tutoring experience.'),

('Oleksii Melnyk',
 'oleksii.melnyk@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Informatics', 'Year 3',
 'Java and Data Structures specialist.'),

('Gabija Kazlauskaite',
 'gabija.kazlauskaite@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Informatics', 'Year 4',
 'Frontend developer teaching HTML, CSS and JavaScript with practical sessions.'),

('Katarzyna Nowak',
 'katarzyna.nowak@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Economics and Management', 'Year 3',
 'Economics and Accounting tutor focused on exam preparation.'),

('Lukas Petrauskas',
 'lukas.petrauskas@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Natural Sciences', 'Year 4',
 'Physics and Mathematics tutor using practical real-world examples.'),

('Andrii Kovalenko',
 'andrii.kovalenko@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Informatics', 'Year 4',
 'Python and Machine Learning specialist with step-by-step guidance.'),

('Emilija Vaitkute',
 'emilija.vaitkute@student.edu',
 '$2y$10$xQ1c3qhoMoiTbVAFRhn7Iu0rrNYH8i38Ommb1OgHXqqgP9cgb5UfS',
 'tutor', 'Faculty of Humanities', 'Year 3',
 'Research Methods and Academic Writing tutor.');

-- Students  (password: Student@1234)
INSERT INTO users (name, email, password, role, department, year_of_study, bio) VALUES
('Oleksandr Kostenko',
 'oleksandr.kostenko@student.edu',
 '$2y$10$qE3TojIYkX6.RFB9Pg57jeHwZmq7ozzCPscsOSaIP.IYU8LC2IuJ6',
 'student', 'Faculty of Informatics', 'Year 2',
 'Informatics student looking for help with backend development.'),

('Tomas Vasiliauskas',
 'tomas.vasiliauskas@student.edu',
 '$2y$10$qE3TojIYkX6.RFB9Pg57jeHwZmq7ozzCPscsOSaIP.IYU8LC2IuJ6',
 'student', 'Faculty of Mathematics and Natural Sciences', 'Year 1',
 'First-year mathematics student.'),

('Anastasiia Shevchenko',
 'anastasiia.shevchenko@student.edu',
 '$2y$10$qE3TojIYkX6.RFB9Pg57jeHwZmq7ozzCPscsOSaIP.IYU8LC2IuJ6',
 'student', 'Faculty of Economics and Management', 'Year 2',
 'Economics student needing statistics and economics support.'),

('Dmytro Bondarenko',
 'dmytro.bondarenko@student.edu',
 '$2y$10$qE3TojIYkX6.RFB9Pg57jeHwZmq7ozzCPscsOSaIP.IYU8LC2IuJ6',
 'student', 'Faculty of Informatics', 'Year 3',
 'Looking for Python and Machine Learning help.');

-- ============================================================
--  SECTION 3 — TUTOR PROFILES
--  CRITICAL: This section was missing from the original seed.
--  Without these rows the tutors table is empty, all tutor_tags
--  foreign keys fail, and the recommendation engine returns nothing.
--
--  user_id values match the INSERT order above:
--    Mantas       = user_id 2  -> tutor_id 1
--    Ieva         = user_id 3  -> tutor_id 2
--    Oleksii      = user_id 4  -> tutor_id 3
--    Gabija       = user_id 5  -> tutor_id 4
--    Katarzyna    = user_id 6  -> tutor_id 5
--    Lukas        = user_id 7  -> tutor_id 6
--    Andrii       = user_id 8  -> tutor_id 7
--    Emilija      = user_id 9  -> tutor_id 8
-- ============================================================
INSERT INTO tutors (user_id, bio, availability_note, avg_rating, rating_count, is_available) VALUES
(2,
 'Final-year Informatics student specialising in PHP and MySQL. I break complex backend concepts into simple, practical lessons with real code examples.',
 'Mon–Fri, 4–8 PM',
 0.00, 0, 1),

(3,
 'Mathematics and Statistics tutor with undergraduate tutoring experience. Patient and thorough approach to quantitative subjects.',
 'Tue & Thu, 2–6 PM',
 0.00, 0, 1),

(4,
 'Java and Data Structures specialist. I use step-by-step visualisation techniques to make algorithms and OOP click.',
 'Wed & Fri, 5–9 PM',
 0.00, 0, 1),

(5,
 'Frontend developer teaching HTML, CSS and JavaScript. All sessions are project-based — you build something real every time.',
 'Mon, Wed, Fri, 3–7 PM',
 0.00, 0, 1),

(6,
 'Economics and Accounting tutor focused on exam preparation and understanding financial statements clearly.',
 'Mon–Thu, 1–5 PM',
 0.00, 0, 1),

(7,
 'Physics and Mathematics tutor using practical real-world examples and worked problems to build genuine understanding.',
 'Sat & Sun, 10 AM–2 PM',
 0.00, 0, 1),

(8,
 'Python and Machine Learning specialist. From basic scripting to building and evaluating models — step by step, no rush.',
 'Mon–Fri, 7–10 PM',
 0.00, 0, 1),

(9,
 'Research Methods and Academic Writing tutor. I help students produce clear, structured, well-argued academic work.',
 'Tue, Thu, Sat, 10 AM–1 PM',
 0.00, 0, 1);

-- ============================================================
--  SECTION 4 — TUTOR TAGS
--  tutor_id values (1-8) match the tutors rows above.
--  tag_id values match the tags INSERT order (1-23).
--
--  Mantas  (tutor_id 1): PHP(7), Database Systems(11), Programming(6), Web Development(12)
--  Ieva    (tutor_id 2): Mathematics(1), Statistics(2)
--  Oleksii (tutor_id 3): Programming(6), Java(9), Data Structures(14), Algorithms(15)
--  Gabija  (tutor_id 4): Web Development(12), HTML/CSS(13), JavaScript(10)
--  Katarzyna(tutor_id 5): Economics(18), Accounting(19), Business Studies(20)
--  Lukas   (tutor_id 6): Physics(3), Mathematics(1)
--  Andrii  (tutor_id 7): Python(8), Machine Learning(17), Data Structures(14), Algorithms(15)
--  Emilija (tutor_id 8): English Writing(21), Research Methods(22)
-- ============================================================
INSERT INTO tutor_tags (tutor_id, tag_id) VALUES
(1,7),(1,11),(1,6),(1,12),
(2,1),(2,2),
(3,6),(3,9),(3,14),(3,15),
(4,12),(4,13),(4,10),
(5,18),(5,19),(5,20),
(6,3),(6,1),
(7,8),(7,17),(7,14),(7,15),
(8,21),(8,22);

-- ============================================================
--  SECTION 5 — AVAILABILITY SLOTS
-- ============================================================
INSERT INTO availability (tutor_id, day_of_week, time_start, time_end) VALUES
-- Mantas (Mon-Fri 16-20)
(1,'Monday',   '16:00','20:00'),
(1,'Tuesday',  '16:00','20:00'),
(1,'Wednesday','16:00','20:00'),
(1,'Thursday', '16:00','20:00'),
(1,'Friday',   '16:00','20:00'),
-- Ieva (Tue & Thu 14-18)
(2,'Tuesday',  '14:00','18:00'),
(2,'Thursday', '14:00','18:00'),
-- Oleksii (Wed & Fri 17-21)
(3,'Wednesday','17:00','21:00'),
(3,'Friday',   '17:00','21:00'),
-- Gabija (Mon, Wed, Fri 15-19)
(4,'Monday',   '15:00','19:00'),
(4,'Wednesday','15:00','19:00'),
(4,'Friday',   '15:00','19:00'),
-- Katarzyna (Mon-Thu 13-17)
(5,'Monday',   '13:00','17:00'),
(5,'Tuesday',  '13:00','17:00'),
(5,'Wednesday','13:00','17:00'),
(5,'Thursday', '13:00','17:00'),
-- Lukas (Sat & Sun 10-14)
(6,'Saturday', '10:00','14:00'),
(6,'Sunday',   '10:00','14:00'),
-- Andrii (Mon-Fri 19-22)
(7,'Monday',   '19:00','22:00'),
(7,'Tuesday',  '19:00','22:00'),
(7,'Wednesday','19:00','22:00'),
(7,'Thursday', '19:00','22:00'),
(7,'Friday',   '19:00','22:00'),
-- Emilija (Tue, Thu, Sat 10-13)
(8,'Tuesday',  '10:00','13:00'),
(8,'Thursday', '10:00','13:00'),
(8,'Saturday', '10:00','13:00');

-- ============================================================
--  SECTION 6 — SAMPLE BOOKINGS
--  student_id values: Oleksandr=10, Tomas=11, Anastasiia=12, Dmytro=13
--  tutor_id values:   Mantas=1, Ieva=2, Gabija=4, Andrii=7
-- ============================================================
INSERT INTO bookings
  (student_id, tutor_id, session_date, session_time,
   duration_hrs, subject, notes, session_type, status)
VALUES
-- Past completed sessions (will receive ratings below)
(10, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '17:00', 1,
 'PHP', 'Need help with sessions and cookies', 'online', 'completed'),

(10, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  '17:00', 1,
 'Database Systems', 'Query optimisation', 'in-person', 'completed'),

(11, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  '14:00', 2,
 'Statistics', 'Hypothesis testing', 'online', 'completed'),

(12, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  '16:00', 1,
 'Web Development', 'Responsive layout with CSS Grid', 'in-person', 'completed'),

-- Recent confirmed (session in progress / soon)
(13, 7, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  '19:00', 1,
 'Python', 'Data visualisation with matplotlib', 'online', 'confirmed'),

-- Upcoming confirmed
(10, 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '15:00', 1,
 'JavaScript', 'DOM manipulation', 'online', 'confirmed'),

-- Pending (tutor has not yet responded)
(11, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY),  '16:00', 1,
 'PHP', 'Building a REST endpoint', 'online', 'pending'),

(12, 7, DATE_ADD(CURDATE(), INTERVAL 1 DAY),  '20:00', 2,
 'Python', 'Introduction to pandas', 'online', 'pending');

-- ============================================================
--  SECTION 7 — RATINGS
--  booking_id values match INSERT order above: 1,2,3,4 are completed.
--  student_id and tutor_id must match the booking exactly.
-- ============================================================
INSERT INTO ratings (booking_id, student_id, tutor_id, score, comment) VALUES
(1, 10, 1, 4, 'Very clear explanations. Helped me understand session handling completely.'),
(2, 10, 1, 5, 'Excellent session on query optimisation — covered indexes brilliantly.'),
(3, 11, 2, 5, 'Very patient and thorough statistics tutor.'),
(4, 12, 4, 4, 'Excellent walkthrough of responsive CSS layouts.');

-- ============================================================
--  SECTION 8 — RECALCULATE TUTOR AVERAGE RATINGS
--  Updates denormalised avg_rating and rating_count on tutors
--  table to reflect the ratings just inserted above.
-- ============================================================
UPDATE tutors t
SET
    avg_rating   = (SELECT ROUND(AVG(r.score), 2)
                    FROM ratings r
                    WHERE r.tutor_id = t.tutor_id),
    rating_count = (SELECT COUNT(*)
                    FROM ratings r
                    WHERE r.tutor_id = t.tutor_id)
WHERE EXISTS (
    SELECT 1 FROM ratings r WHERE r.tutor_id = t.tutor_id
);

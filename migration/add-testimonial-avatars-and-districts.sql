-- Adds profile pictures + Ugandan district to the 3 existing testimonial
-- authors, then seeds 7 more learner accounts (with published testimonials)
-- spread across different districts, so the Stories page reads as a
-- nationwide community instead of 3 bare quotes.
--
-- Avatars are illustrated/generated (DiceBear "personas" style), not real
-- photos of real people — deliberately, so no specific real person's face
-- is attached to a quote they never gave. Files already live at
-- public/assets/img/testimonials/*.png in the deploy repo.
--
-- Safe to run once against production. Re-running the UPDATEs is harmless
-- (idempotent); re-running the INSERTs would create duplicate accounts, so
-- don't run this file twice.

SET NAMES utf8mb4; -- guards against the em dash in Ibrahim's quote getting mangled by a client whose default connection charset isn't utf8mb4

-- Existing 3 authors: add avatar + district
UPDATE users SET avatar_url = 'assets/img/testimonials/mary-nabirye.png', headline = 'Jinja, Uganda' WHERE email = 'mary.nabirye@example.com';
UPDATE users SET avatar_url = 'assets/img/testimonials/john-mukasa.png', headline = 'Kampala, Uganda' WHERE email = 'john.mukasa@example.com';
UPDATE users SET avatar_url = 'assets/img/testimonials/grace-achieng.png', headline = 'Gulu, Uganda' WHERE email = 'grace.achieng@example.com';

-- 7 new learner accounts, each with a published testimonial
INSERT INTO users (name, email, phone, password_hash, role, headline, avatar_url, created_at) VALUES
('Patricia Namutebi', 'patricia.namutebi@example.com', '+256700000009', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Wakiso, Uganda', 'assets/img/testimonials/patricia-namutebi.png', NOW()),
('Moses Byaruhanga', 'moses.byaruhanga@example.com', '+256700000010', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Mbarara, Uganda', 'assets/img/testimonials/moses-byaruhanga.png', NOW()),
('Aisha Nakato', 'aisha.nakato@example.com', '+256700000011', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Kampala, Uganda', 'assets/img/testimonials/aisha-nakato.png', NOW()),
('Emmanuel Okello', 'emmanuel.okello@example.com', '+256700000012', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Lira, Uganda', 'assets/img/testimonials/emmanuel-okello.png', NOW()),
('Sarah Kobusingye', 'sarah.kobusingye@example.com', '+256700000013', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Kabale, Uganda', 'assets/img/testimonials/sarah-kobusingye.png', NOW()),
('Ibrahim Ssentamu', 'ibrahim.ssentamu@example.com', '+256700000014', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Masaka, Uganda', 'assets/img/testimonials/ibrahim-ssentamu.png', NOW()),
('Ritah Nansubuga', 'ritah.nansubuga@example.com', '+256700000015', '$2y$10$QzQjIpZBkXATP9/MOubGH.PzGk.B23xMSuwtaGCcEBf49Hz3qc6O2', 'LEARNER', 'Mukono, Uganda', 'assets/img/testimonials/ritah-nansubuga.png', NOW());

INSERT INTO testimonials (quote, rating, status, reviewed_at, author_id) VALUES
('I started the Personal Finance Mastery course knowing nothing about budgeting. A few months later I''ve saved my first 500,000 shillings and I actually check my bank balance without fear now.', 5, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'patricia.namutebi@example.com')),
('I run a small hardware shop here in Mbarara. The ecommerce course showed me how to list my products online, and now I get orders from customers I would never have reached otherwise.', 5, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'moses.byaruhanga@example.com')),
('I paid for the web development course with MTN Mobile Money and was writing my first working page by the weekend. No laptop excuses, no complicated sign-up, just straight into learning.', 5, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'aisha.nakato@example.com')),
('As a small business owner in Lira, keeping proper books always scared me. The accounting course broke it down so simply that I now do my own bookkeeping instead of paying someone else.', 4, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'emmanuel.okello@example.com')),
('I used to post on Instagram and get almost no sales. After the social media marketing course, my hair products page here in Kabale is finally getting orders every week.', 5, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'sarah.kobusingye@example.com')),
('Obin Academy is not just for learning — I applied to become a creator and now I teach phone repair skills to students all over Uganda, right from Masaka.', 5, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'ibrahim.ssentamu@example.com')),
('The React crash course finally made JavaScript click for me. I built my first interactive project during the course and I am now freelancing part-time from here in Mukono.', 4, 'PUBLISHED', NOW(), (SELECT id FROM users WHERE email = 'ritah.nansubuga@example.com'));

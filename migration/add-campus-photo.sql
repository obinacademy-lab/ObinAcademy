-- Each campus can have its own photo, uploaded in Admin > Campuses > (campus) > Details and prices.
-- Campuses without one keep the built-in picture (assets/img/campuses/{slug}.jpg). Run once in phpMyAdmin
-- with the Obin Academy database selected. The site works before it is run (the photo upload stays hidden).
ALTER TABLE campuses ADD COLUMN photo_url VARCHAR(500) NULL AFTER icon;

-- Backs the 16 industries skills.php already advertises ("26 Industries")
-- but that had no real category row yet — clicking them previously fell
-- through to an honest "no courses match" empty state instead of being a
-- real, assignable, filterable category. INSERT IGNORE makes this safe to
-- re-run (skips any slug that already exists).
INSERT IGNORE INTO categories (name, slug, icon) VALUES
  ('Medical', 'medical', 'stethoscope'),
  ('Food', 'food', 'utensils'),
  ('Law', 'law', 'scale'),
  ('Human Resources', 'human-resources', 'users'),
  ('Engineering', 'engineering', 'cog'),
  ('Construction', 'construction', 'hard-hat'),
  ('Hospitality', 'hospitality', 'concierge-bell'),
  ('Fashion & Beauty', 'fashion-beauty', 'shirt'),
  ('Music & Arts', 'music-arts', 'music'),
  ('Photography & Film', 'photography-film', 'camera'),
  ('Sports & Fitness', 'sports-fitness', 'dumbbell'),
  ('Logistics', 'logistics', 'truck'),
  ('Environment', 'environment', 'leaf'),
  ('Energy', 'energy', 'zap'),
  ('Automotive', 'automotive', 'car'),
  ('Media & Journalism', 'media-journalism', 'newspaper');

-- Lets a comment/reply carry an attached GIF sticker (via Giphy) alongside
-- or instead of text. See includes/giphy.php and includes/comments.php.
ALTER TABLE comments
  ADD COLUMN gif_url VARCHAR(500) NULL AFTER body;

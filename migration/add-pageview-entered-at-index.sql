-- Speeds up get_live_viewer_count() (includes/data.php), which now runs on
-- every published course page load to power the enroll panel's "N people
-- viewing right now" badge.
ALTER TABLE visitor_pageviews ADD INDEX idx_entered_at (entered_at);

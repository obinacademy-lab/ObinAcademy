<?php
require_once __DIR__ . '/user_notifications.php';

/**
 * Community module — Phase 2 (Feed & discussions): posts, comments, likes,
 * polls, hashtags, @mentions. See C:\Users\hp\.claude\plans\iterative-scribbling-turing.md.
 *
 * Denormalized counters (like_count, comment_count, member_count, vote_count)
 * are always moved with SET x = x + 1 / x - 1 in the same statement as the
 * related insert/delete, never a PHP read-then-write — this app has no
 * application-level locking, so a read-then-write races under concurrent
 * requests. Same convention as join_community()/leave_community() in
 * community.php.
 */

const COMMUNITY_POST_TYPES = ['post', 'question', 'success_story', 'poll'];

/** #tag tokens (2-50 word chars) from a post/comment body, lowercased, de-duplicated. */
function extract_hashtags(string $body): array {
    if (!preg_match_all('/#([a-zA-Z0-9_]{2,50})/', $body, $matches)) return [];
    $tags = array_unique(array_map('strtolower', $matches[1]));
    return array_values($tags);
}

/** ",tag1,tag2," CSV (see schema.sql note on community_posts.hashtags), or null if none. */
function hashtags_to_csv(array $tags): ?string {
    if (!$tags) return null;
    return ',' . implode(',', $tags) . ',';
}

function user_can_moderate_community(int $communityId, int $userId): bool {
    $member = db_one('SELECT role FROM community_members WHERE community_id = ? AND user_id = ?', [$communityId, $userId]);
    if ($member && in_array($member['role'], ['owner', 'moderator'], true)) return true;
    $user = db_one('SELECT role FROM users WHERE id = ?', [$userId]);
    return $user && $user['role'] === 'ADMIN';
}

/**
 * Creates a post (and its poll, if type is 'poll') and notifies any
 * @mentioned community members. $pollOptions is a plain list of option
 * labels (2-6 entries) used only when $type === 'poll'.
 *
 * @param int[] $mentionUserIds Candidate mentioned user ids from the composer's
 *   typeahead — filtered down to actual community members before notifying,
 *   so a stale/tampered id can't spam an outsider.
 */
function create_post(
    int $communityId,
    int $authorId,
    string $type,
    string $body,
    ?int $categoryId,
    ?string $imageUrl,
    ?string $linkUrl,
    array $pollOptions = [],
    array $mentionUserIds = []
): int {
    if (!in_array($type, COMMUNITY_POST_TYPES, true)) $type = 'post';
    $hashtags = hashtags_to_csv(extract_hashtags($body));

    $postId = db_insert(
        'INSERT INTO community_posts (community_id, category_id, author_id, type, body, image_url, link_url, hashtags)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$communityId, $categoryId, $authorId, $type, $body, $imageUrl, $linkUrl, $hashtags]
    );

    if ($type === 'poll') {
        $options = array_values(array_filter(array_map('trim', $pollOptions), fn($o) => $o !== ''));
        if (count($options) >= 2) {
            $pollId = db_insert('INSERT INTO community_polls (post_id) VALUES (?)', [$postId]);
            foreach (array_slice($options, 0, 6) as $label) {
                db_insert('INSERT INTO community_poll_options (poll_id, label) VALUES (?, ?)', [$pollId, $label]);
            }
        }
    }

    notify_mentions($communityId, $authorId, $mentionUserIds, 'post', $postId, 'mentioned you in a post');
    award_xp($authorId, 5);
    record_daily_activity($authorId);

    return $postId;
}

/** Validates candidate mention ids against real community membership, then writes one notification row per valid mention. */
function notify_mentions(int $communityId, int $actorId, array $candidateUserIds, string $context, int $relatedId, string $verb): void {
    $candidateUserIds = array_unique(array_filter(array_map('intval', $candidateUserIds)));
    if (!$candidateUserIds) return;

    $actor = db_one('SELECT name FROM users WHERE id = ?', [$actorId]);
    $community = db_one('SELECT slug FROM communities WHERE id = ?', [$communityId]);
    if (!$actor || !$community) return;

    $placeholders = implode(',', array_fill(0, count($candidateUserIds), '?'));
    $params = array_merge([$communityId], array_values($candidateUserIds));
    $validMembers = db_all("SELECT user_id FROM community_members WHERE community_id = ? AND user_id IN ($placeholders)", $params);

    $link = '/community/view.php?slug=' . $community['slug'] . ($context === 'post' ? "#post-$relatedId" : "#comment-$relatedId");
    foreach ($validMembers as $m) {
        $mentionedId = (int) $m['user_id'];
        if ($mentionedId === $actorId) continue;
        create_user_notification($mentionedId, 'mention', $actor['name'] . ' ' . $verb, $link, $relatedId);
    }
}

/**
 * Feed page for a community, newest first with pinned posts pinned to the
 * top. No time-window cutoff — a large/old community's feed still paginates
 * fully rather than silently hiding anything past an arbitrary window.
 */
function get_feed_posts(int $communityId, ?int $categoryId, ?int $viewerUserId, int $limit = 15, int $offset = 0): array {
    $limit = max(1, min(50, $limit));
    $offset = max(0, $offset);
    $where = 'p.community_id = ?';
    $params = [$communityId];
    if ($categoryId) {
        $where .= ' AND p.category_id = ?';
        $params[] = $categoryId;
    }

    $likedSql = $viewerUserId
        ? '(SELECT 1 FROM community_post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ' . (int) $viewerUserId . ') IS NOT NULL'
        : '0';

    $posts = db_all(
        "SELECT p.*, u.name AS author_name, u.avatar_url AS author_avatar_url, u.role AS author_role,
                cat.name AS category_name, cat.icon AS category_icon,
                $likedSql AS is_liked
         FROM community_posts p
         JOIN users u ON u.id = p.author_id
         LEFT JOIN community_categories cat ON cat.id = p.category_id
         WHERE $where
         ORDER BY p.is_pinned DESC, p.created_at DESC, p.id DESC
         LIMIT $limit OFFSET $offset",
        $params
    );

    attach_polls($posts, $viewerUserId);
    return $posts;
}

/** Attaches poll (options + vote counts + the viewer's chosen option, if any) onto each poll-type post in place. */
function attach_polls(array &$posts, ?int $viewerUserId): void {
    foreach ($posts as &$post) {
        if ($post['type'] !== 'poll') continue;
        $poll = db_one('SELECT * FROM community_polls WHERE post_id = ?', [$post['id']]);
        if (!$poll) continue;
        $options = db_all('SELECT * FROM community_poll_options WHERE poll_id = ? ORDER BY id ASC', [$poll['id']]);
        $totalVotes = array_sum(array_column($options, 'vote_count'));
        $myVote = $viewerUserId
            ? db_one('SELECT option_id FROM community_poll_votes WHERE poll_id = ? AND user_id = ?', [$poll['id'], $viewerUserId])
            : null;
        $post['poll'] = [
            'id' => (int) $poll['id'],
            'options' => $options,
            'total_votes' => $totalVotes,
            'my_option_id' => $myVote ? (int) $myVote['option_id'] : null,
        ];
    }
    unset($post);
}

function get_post(int $postId, ?int $viewerUserId = null): ?array {
    $likedSql = $viewerUserId
        ? '(SELECT 1 FROM community_post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ' . (int) $viewerUserId . ') IS NOT NULL'
        : '0';
    $post = db_one(
        "SELECT p.*, u.name AS author_name, u.avatar_url AS author_avatar_url, u.role AS author_role,
                cat.name AS category_name, cat.icon AS category_icon,
                $likedSql AS is_liked
         FROM community_posts p
         JOIN users u ON u.id = p.author_id
         LEFT JOIN community_categories cat ON cat.id = p.category_id
         WHERE p.id = ?",
        [$postId]
    );
    if (!$post) return null;
    $posts = [$post];
    attach_polls($posts, $viewerUserId);
    return $posts[0];
}

function toggle_post_pin(int $postId, int $communityId, int $actingUserId): bool {
    if (!user_can_moderate_community($communityId, $actingUserId)) return false;
    $post = db_one('SELECT is_pinned FROM community_posts WHERE id = ? AND community_id = ?', [$postId, $communityId]);
    if (!$post) return false;
    db_run('UPDATE community_posts SET is_pinned = ? WHERE id = ?', [$post['is_pinned'] ? 0 : 1, $postId]);
    return true;
}

function delete_post(int $postId, int $communityId, int $actingUserId): bool {
    $post = db_one('SELECT author_id FROM community_posts WHERE id = ? AND community_id = ?', [$postId, $communityId]);
    if (!$post) return false;
    if ((int) $post['author_id'] !== $actingUserId && !user_can_moderate_community($communityId, $actingUserId)) return false;
    db_run('DELETE FROM community_posts WHERE id = ?', [$postId]);
    return true;
}

/** Toggles the viewer's like on a post; returns the new liked state (true = now liked). */
function toggle_post_like(int $postId, int $userId): bool {
    $existing = db_one('SELECT id FROM community_post_likes WHERE post_id = ? AND user_id = ?', [$postId, $userId]);
    if ($existing) {
        db_run('DELETE FROM community_post_likes WHERE id = ?', [$existing['id']]);
        db_run('UPDATE community_posts SET like_count = like_count - 1 WHERE id = ?', [$postId]);
        return false;
    }

    db_insert('INSERT INTO community_post_likes (post_id, user_id) VALUES (?, ?)', [$postId, $userId]);
    db_run('UPDATE community_posts SET like_count = like_count + 1 WHERE id = ?', [$postId]);

    $post = db_one('SELECT author_id, community_id FROM community_posts WHERE id = ?', [$postId]);
    if ($post && (int) $post['author_id'] !== $userId) {
        $actor = db_one('SELECT name FROM users WHERE id = ?', [$userId]);
        $community = db_one('SELECT slug FROM communities WHERE id = ?', [$post['community_id']]);
        if ($actor && $community) {
            create_user_notification((int) $post['author_id'], 'like', $actor['name'] . ' liked your post', '/community/post.php?id=' . $postId, $postId);
        }
    }
    return true;
}

/** All comments on a post, oldest first, arranged into a tree (top-level comments carry a 'replies' key). */
function get_post_comments(int $postId, ?int $viewerUserId = null): array {
    $likedSql = $viewerUserId
        ? '(SELECT 1 FROM community_comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ' . (int) $viewerUserId . ') IS NOT NULL'
        : '0';
    $rows = db_all(
        "SELECT c.*, u.name AS author_name, u.avatar_url AS author_avatar_url, $likedSql AS is_liked_by_viewer
         FROM community_comments c JOIN users u ON u.id = c.author_id
         WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC",
        [$postId]
    );

    $byId = [];
    foreach ($rows as $row) {
        $row['replies'] = [];
        $byId[$row['id']] = $row;
    }
    $tree = [];
    foreach ($byId as $id => &$row) {
        if ($row['parent_comment_id'] && isset($byId[$row['parent_comment_id']])) {
            $byId[$row['parent_comment_id']]['replies'][] = &$row;
        } else {
            $tree[] = &$row;
        }
    }
    unset($row);
    return $tree;
}

/**
 * @param int[] $mentionUserIds
 */
function create_comment(int $postId, int $authorId, string $body, ?int $parentCommentId, array $mentionUserIds = []): int {
    $post = db_one('SELECT community_id, author_id FROM community_posts WHERE id = ?', [$postId]);
    if (!$post) throw new InvalidArgumentException("No post $postId");

    $commentId = db_insert(
        'INSERT INTO community_comments (post_id, parent_comment_id, author_id, body) VALUES (?, ?, ?, ?)',
        [$postId, $parentCommentId, $authorId, $body]
    );
    db_run('UPDATE community_posts SET comment_count = comment_count + 1 WHERE id = ?', [$postId]);

    $actor = db_one('SELECT name FROM users WHERE id = ?', [$authorId]);
    $notified = [$authorId];

    if ($parentCommentId) {
        $parent = db_one('SELECT author_id FROM community_comments WHERE id = ?', [$parentCommentId]);
        if ($parent && !in_array((int) $parent['author_id'], $notified, true)) {
            create_user_notification((int) $parent['author_id'], 'reply', $actor['name'] . ' replied to your comment', '/community/post.php?id=' . $postId, $commentId);
            $notified[] = (int) $parent['author_id'];
        }
    }
    if (!in_array((int) $post['author_id'], $notified, true)) {
        create_user_notification((int) $post['author_id'], 'comment', $actor['name'] . ' commented on your post', '/community/post.php?id=' . $postId, $commentId);
    }

    notify_mentions((int) $post['community_id'], $authorId, $mentionUserIds, 'comment', $commentId, 'mentioned you in a comment');
    award_xp($authorId, 2);
    record_daily_activity($authorId);

    return $commentId;
}

/** Size of the reply subtree rooted at $commentId (including itself), within one post's already-fetched comment rows. */
function count_comment_subtree(int $commentId, array $allComments): int {
    $count = 1;
    foreach ($allComments as $c) {
        if ((int) $c['parent_comment_id'] === $commentId) {
            $count += count_comment_subtree((int) $c['id'], $allComments);
        }
    }
    return $count;
}

/** Deleting a comment cascades (FK) to its replies too — comment_count is decremented by the whole subtree's size, not just 1. */
function delete_comment(int $commentId, int $communityId, int $actingUserId): bool {
    $comment = db_one('SELECT author_id, post_id FROM community_comments WHERE id = ?', [$commentId]);
    if (!$comment) return false;
    if ((int) $comment['author_id'] !== $actingUserId && !user_can_moderate_community($communityId, $actingUserId)) return false;

    $postId = (int) $comment['post_id'];
    $allComments = db_all('SELECT id, parent_comment_id FROM community_comments WHERE post_id = ?', [$postId]);
    $subtreeSize = count_comment_subtree($commentId, $allComments);

    db_run('DELETE FROM community_comments WHERE id = ?', [$commentId]);
    db_run('UPDATE community_posts SET comment_count = GREATEST(comment_count - ?, 0) WHERE id = ?', [$subtreeSize, $postId]);
    return true;
}

function toggle_comment_like(int $commentId, int $userId): bool {
    $existing = db_one('SELECT id FROM community_comment_likes WHERE comment_id = ? AND user_id = ?', [$commentId, $userId]);
    if ($existing) {
        db_run('DELETE FROM community_comment_likes WHERE id = ?', [$existing['id']]);
        db_run('UPDATE community_comments SET like_count = like_count - 1 WHERE id = ?', [$commentId]);
        return false;
    }
    db_insert('INSERT INTO community_comment_likes (comment_id, user_id) VALUES (?, ?)', [$commentId, $userId]);
    db_run('UPDATE community_comments SET like_count = like_count + 1 WHERE id = ?', [$commentId]);
    return true;
}

/** Casts (or, on a repeat call, silently no-ops — UNIQUE(poll_id,user_id) is one vote per person) a poll vote. Returns true if a vote was recorded. */
function vote_poll(int $pollId, int $optionId, int $userId): bool {
    $option = db_one('SELECT id FROM community_poll_options WHERE id = ? AND poll_id = ?', [$optionId, $pollId]);
    if (!$option) return false;

    $existing = db_one('SELECT id FROM community_poll_votes WHERE poll_id = ? AND user_id = ?', [$pollId, $userId]);
    if ($existing) return false;

    db_insert('INSERT INTO community_poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)', [$pollId, $optionId, $userId]);
    db_run('UPDATE community_poll_options SET vote_count = vote_count + 1 WHERE id = ?', [$optionId]);
    return true;
}

/** Posts tagged #$tag within one community, newest first — the within-community hashtag search for v1 (platform-wide search is a later phase). */
function get_posts_by_hashtag(int $communityId, string $tag, int $limit = 30): array {
    $limit = max(1, min(100, $limit));
    $tag = strtolower(ltrim(trim($tag), '#'));
    return db_all(
        "SELECT p.*, u.name AS author_name, u.avatar_url AS author_avatar_url
         FROM community_posts p JOIN users u ON u.id = p.author_id
         WHERE p.community_id = ? AND p.hashtags LIKE ?
         ORDER BY p.created_at DESC LIMIT $limit",
        [$communityId, "%,$tag,%"]
    );
}

// -----------------------------------------------------------------------
// Admin community-stats queries — Phase 6.
// -----------------------------------------------------------------------

/** Platform-wide community module totals for the admin stats page's top cards. */
function get_community_module_stats(): array {
    return [
        'communities' => (int) db_one('SELECT COUNT(*) AS n FROM communities')['n'],
        'members' => (int) db_one('SELECT COUNT(DISTINCT user_id) AS n FROM community_members')['n'],
        'posts' => (int) db_one('SELECT COUNT(*) AS n FROM community_posts')['n'],
        'pending_reports' => get_pending_report_count(),
    ];
}

/** Posts created per day for the last $days days, zero-filled — same shape as get_leads_daily_series(). */
function get_community_posts_daily_series(int $days = 30): array {
    $rows = db_all(
        'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM community_posts
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)',
        [$days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (int) $r['n'];

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $series[] = ['date' => $date, 'count' => $byDate[$date] ?? 0];
    }
    return $series;
}

/** Communities ranked by post count — the admin stats page's "Most Active" list. */
function get_most_active_communities(int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT c.name, c.slug, c.member_count, COUNT(p.id) AS post_count
         FROM communities c LEFT JOIN community_posts p ON p.community_id = c.id
         GROUP BY c.id ORDER BY post_count DESC, c.member_count DESC LIMIT $limit"
    );
}

/** Top members by XP within one community — the "Top Contributors" sidebar widget. A real, visible use of the XP a member earns by posting/commenting/completing courses, so it's a genuine engagement loop rather than a number nobody sees. */
function get_community_leaderboard(int $communityId, int $limit = 5): array {
    $limit = max(1, min(20, $limit));
    return db_all(
        "SELECT u.id, u.name, u.avatar_url, u.xp_points, u.current_streak
         FROM community_members cm JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id = ? AND u.xp_points > 0
         ORDER BY u.xp_points DESC, u.id ASC LIMIT $limit",
        [$communityId]
    );
}

/** Posts from the last 7 days ranked by engagement (comments weighted higher than likes) — the feed's "Trending This Week" strip. */
function get_trending_posts(int $communityId, int $limit = 3): array {
    $limit = max(1, min(10, $limit));
    return db_all(
        "SELECT p.id, p.body, p.like_count, p.comment_count, p.created_at,
                u.name AS author_name, u.avatar_url AS author_avatar_url,
                (p.like_count + p.comment_count * 2) AS score
         FROM community_posts p JOIN users u ON u.id = p.author_id
         WHERE p.community_id = ? AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND (p.like_count > 0 OR p.comment_count > 0)
         ORDER BY score DESC, p.created_at DESC LIMIT $limit",
        [$communityId]
    );
}

// -----------------------------------------------------------------------
// Render helpers — view-layer functions kept alongside the data functions
// above, same convention as render_bar_list()/render_logo() in functions.php.
// -----------------------------------------------------------------------

/** @param array<int,array{id:int|string,name:string}> $members */
function mentionable_members_json(array $members): string {
    return e(json_encode(array_map(fn($m) => ['id' => (int) $m['id'], 'name' => $m['name']], $members)));
}

function render_composer(array $community, array $categories, array $members): void {
    $me = current_user();
    if (!$me) return;
    ?>
    <div class="feed-composer" data-composer>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="create_post">
        <input type="hidden" name="type" value="post">
        <div class="feed-composer-head">
          <div class="avatar-circle" style="width:38px; height:38px; font-size:14px;">
            <?php if (!empty($me['avatar_url'])): ?><img src="<?= e(asset_src($me['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($me['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <textarea name="body" placeholder="Share something with the community… use #tags and @mention a member" data-mentionable data-members="<?= mentionable_members_json($members) ?>" required></textarea>
        </div>

        <div class="feed-type-tabs">
          <button type="button" class="chip active" data-composer-type-tab="post">Post</button>
          <button type="button" class="chip" data-composer-type-tab="question">Question</button>
          <button type="button" class="chip" data-composer-type-tab="poll">Poll</button>
        </div>

        <div class="feed-poll-builder hidden" data-poll-builder>
          <div data-poll-option-list style="display:flex; flex-direction:column; gap:8px;">
            <input type="text" name="poll_options[]" placeholder="Option 1" maxlength="120">
            <input type="text" name="poll_options[]" placeholder="Option 2" maxlength="120">
          </div>
          <button type="button" class="feed-icon-btn" data-add-poll-option style="width:fit-content;">+ Add option</button>
        </div>

        <div class="feed-composer-row">
          <?php if ($categories): ?>
            <select name="category_id">
              <option value="">No category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['icon'] . ' ' . $cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <label class="feed-icon-btn" style="cursor:pointer;">
            📷 Image
            <input type="file" name="image" accept="image/*" style="display:none;">
          </label>
          <input type="url" name="link_url" placeholder="Paste a link (optional)" style="max-width:220px; margin:0;">
          <button type="submit" class="btn btn-primary" style="margin-left:auto;">Post</button>
        </div>
        <div class="feed-image-preview hidden" data-image-preview></div>
      </form>
    </div>
    <?php
}

function render_poll(array $poll): void {
    $voted = $poll['my_option_id'] !== null;
    ?>
    <div class="feed-poll" data-poll data-poll-id="<?= (int) $poll['id'] ?>" data-voted="<?= $voted ? '1' : '0' ?>">
      <?php foreach ($poll['options'] as $opt):
        $pct = $poll['total_votes'] > 0 ? (int) round($opt['vote_count'] / $poll['total_votes'] * 100) : 0;
        $isMine = $voted && (int) $opt['id'] === (int) $poll['my_option_id'];
      ?>
        <div class="feed-poll-option<?= $voted ? ' voted' : '' ?><?= $isMine ? ' mine' : '' ?>" data-poll-option data-option-id="<?= (int) $opt['id'] ?>">
          <div class="fill" style="width:<?= $voted ? $pct : 0 ?>%;"></div>
          <div class="row">
            <span><?= e($opt['label']) ?><?= $isMine ? ' ✓' : '' ?></span>
            <span data-vote-pct><?= $voted ? $pct . '%' : '' ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="feed-poll-meta" data-poll-total><?= (int) $poll['total_votes'] ?> vote<?= (int) $poll['total_votes'] === 1 ? '' : 's' ?></div>
    </div>
    <?php
}

/** One post card. $onPermalink hides the "N comments" link (since the permalink page shows comments inline below instead). */
function render_post_card(array $post, int $communityId, ?array $user, bool $isModerator, bool $onPermalink = false): void {
    $bodyHtml = nl2br(e($post['body']));
    $bodyHtml = preg_replace('/#([a-zA-Z0-9_]{2,50})/', '<span class="tag">#$1</span>', $bodyHtml);
    ?>
    <article class="feed-post<?= $post['is_pinned'] ? ' is-pinned' : '' ?>" id="post-<?= (int) $post['id'] ?>">
      <div class="feed-post-head">
        <div class="avatar-circle" style="width:38px; height:38px; font-size:14px;">
          <?php if ($post['author_avatar_url']): ?><img src="<?= e(asset_src($post['author_avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($post['author_name'], 0, 1)) ?><?php endif; ?>
        </div>
        <div class="meta">
          <div class="name-row">
            <a class="author" href="<?= e(base_url('profile.php?id=' . $post['author_id'])) ?>"><?= e($post['author_name']) ?></a>
            <?php if (in_array($post['author_role'], ['CREATOR', 'ADMIN'], true)): ?>
              <span class="role-badge"><?= $post['author_role'] === 'ADMIN' ? 'Admin' : 'Creator' ?></span>
            <?php endif; ?>
          </div>
          <div class="sub"><?= $post['category_name'] ? e($post['category_icon'] . ' ' . $post['category_name']) . ' · ' : '' ?><?= time_ago($post['created_at']) ?></div>
        </div>
        <?php if ($post['is_pinned']): ?><span class="pin-flag">📌 Pinned</span><?php endif; ?>
      </div>

      <div class="feed-post-body"><?= $bodyHtml ?></div>
      <?php if (!empty($post['image_url'])): ?><div class="feed-post-image"><img src="<?= e(asset_src($post['image_url'])) ?>" alt=""></div><?php endif; ?>
      <?php if (!empty($post['link_url'])): ?><a href="<?= e($post['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="feed-post-link">🔗 <?= e($post['link_url']) ?></a><?php endif; ?>
      <?php if ($post['type'] === 'poll' && !empty($post['poll'])): render_poll($post['poll']); endif; ?>

      <div class="feed-actions">
        <?php if ($user): ?>
          <button type="button" class="feed-action-btn<?= $post['is_liked'] ? ' liked' : '' ?>" data-like-post data-post-id="<?= (int) $post['id'] ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
            <span data-like-count><?= (int) $post['like_count'] ?></span>
          </button>
        <?php else: ?>
          <span class="feed-action-btn">❤ <?= (int) $post['like_count'] ?></span>
        <?php endif; ?>
        <?php if (!$onPermalink): ?>
          <a class="feed-action-btn" href="<?= e(base_url('community/post.php?id=' . $post['id'])) ?>">💬 <?= (int) $post['comment_count'] ?></a>
        <?php else: ?>
          <span class="feed-action-btn">💬 <?= (int) $post['comment_count'] ?></span>
        <?php endif; ?>
        <span class="spacer"></span>
        <?php if ($user && (int) $post['author_id'] !== (int) $user['id']): ?>
          <a class="feed-action-btn" href="<?= e(base_url('community/report.php?type=post&id=' . $post['id'])) ?>">Report</a>
        <?php endif; ?>
        <?php if ($isModerator): ?>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="toggle_pin">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <button type="submit" class="feed-action-btn"><?= $post['is_pinned'] ? 'Unpin' : 'Pin' ?></button>
          </form>
        <?php endif; ?>
        <?php if ($user && ((int) $post['author_id'] === (int) $user['id'] || $isModerator)): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this post? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete_post">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <button type="submit" class="feed-action-btn">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
    <?php
}

/** One comment + its replies, recursively — schema allows unlimited nesting depth, the UI just indents once per level. */
function render_comment_node(array $comment, int $communityId, ?array $user, bool $isModerator, array $members): void {
    ?>
    <div class="feed-comment" id="comment-<?= (int) $comment['id'] ?>">
      <div class="avatar-circle" style="width:32px; height:32px; font-size:12.5px;">
        <?php if ($comment['author_avatar_url']): ?><img src="<?= e(asset_src($comment['author_avatar_url'])) ?>" alt="">
        <?php else: ?><?= e(mb_substr($comment['author_name'], 0, 1)) ?><?php endif; ?>
      </div>
      <div style="flex:1; min-width:0;">
        <div class="bubble">
          <a class="author" href="<?= e(base_url('profile.php?id=' . $comment['author_id'])) ?>"><?= e($comment['author_name']) ?></a>
          <div class="text"><?= nl2br(e($comment['body'])) ?></div>
        </div>
        <div class="comment-meta">
          <span><?= time_ago($comment['created_at']) ?></span>
          <?php if ($user): ?>
            <button type="button" class="<?= !empty($comment['is_liked_by_viewer']) ? 'liked' : '' ?>" data-like-comment data-comment-id="<?= (int) $comment['id'] ?>">
              Like (<span data-like-count><?= (int) $comment['like_count'] ?></span>)
            </button>
            <button type="button" data-reply-toggle="reply-form-<?= (int) $comment['id'] ?>">Reply</button>
          <?php else: ?>
            <span><?= (int) $comment['like_count'] ?> like<?= (int) $comment['like_count'] === 1 ? '' : 's' ?></span>
          <?php endif; ?>
          <?php if ($user && (int) $comment['author_id'] !== (int) $user['id']): ?>
            <a href="<?= e(base_url('community/report.php?type=comment&id=' . $comment['id'])) ?>">Report</a>
          <?php endif; ?>
          <?php if ($user && ((int) $comment['author_id'] === (int) $user['id'] || $isModerator)): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this comment?');">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="delete_comment">
              <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
              <button type="submit">Delete</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($user): ?>
          <form method="post" id="reply-form-<?= (int) $comment['id'] ?>" class="feed-comment-form hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="create_comment">
            <input type="hidden" name="parent_comment_id" value="<?= (int) $comment['id'] ?>">
            <textarea name="body" placeholder="Write a reply…" data-mentionable data-members="<?= mentionable_members_json($members) ?>" required></textarea>
            <button type="submit" class="btn btn-outline" style="flex-shrink:0;">Reply</button>
          </form>
        <?php endif; ?>

        <?php if ($comment['replies']): ?>
          <div class="feed-comment-replies">
            <?php foreach ($comment['replies'] as $reply): render_comment_node($reply, $communityId, $user, $isModerator, $members); endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

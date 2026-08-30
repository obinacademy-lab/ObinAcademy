<?php
// One-off local demo-data seeder. Run with: php migration/seed-demo-data.php
// Populates users, courses, modules/lessons, enrollments, payments, earnings,
// reviews, testimonials, a creator application, a withdrawal request and a
// few audit log entries so the app has something to look at end-to-end.
//
// Safe to re-run: it wipes and re-inserts everything except `categories`
// (which schema.sql already seeds) and any real accounts you've created by
// hand through the site — this script only ever touches rows it created
// itself, identified by the email addresses below.

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

echo "Seeding demo data into database \"" . DB_NAME . "\"...\n";

// -----------------------------------------------------------------------
// Wipe previous seed data (children first) so this script is re-runnable.
// -----------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'audit_log', 'reviews', 'testimonials', 'withdrawal_requests', 'earnings',
    'payments', 'enrollments', 'lessons', 'modules', 'courses',
    'creator_applications', 'password_reset_tokens', 'users',
] as $table) {
    $pdo->exec("TRUNCATE TABLE $table");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// -----------------------------------------------------------------------
// Users
// -----------------------------------------------------------------------
$DEMO_PASSWORD = 'Passw0rd!';
$hash = hash_password($DEMO_PASSWORD);

function insert_user(array $u, string $hash): int {
    return db_insert(
        'INSERT INTO users (name, email, phone, password_hash, role, headline, bio, avatar_url, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $u['name'], $u['email'], $u['phone'], $hash, $u['role'],
            $u['headline'] ?? null, $u['bio'] ?? null, $u['avatar_url'] ?? null,
            $u['created_at'],
        ]
    );
}

$now = time();
$daysAgo = fn(int $d) => date('Y-m-d H:i:s', $now - $d * 86400);

$adminId = insert_user([
    'name' => 'Obin Admin', 'email' => 'admin@obinacademy.com', 'phone' => '+256700000001',
    'role' => 'ADMIN', 'headline' => 'Platform Administrator',
    'created_at' => $daysAgo(120),
], $hash);

$sarahId = insert_user([
    'name' => 'Sarah Namuli', 'email' => 'sarah.namuli@obinacademy.com', 'phone' => '+256700000002',
    'role' => 'CREATOR', 'headline' => 'Financial Literacy Coach & Certified Accountant',
    'bio' => "I'm a certified accountant with 10+ years helping Ugandan families and small businesses take control of their money. I teach practical, no-jargon personal finance and bookkeeping.",
    'created_at' => $daysAgo(95),
], $hash);

$davidId = insert_user([
    'name' => 'David Okello', 'email' => 'david.okello@obinacademy.com', 'phone' => '+256700000003',
    'role' => 'CREATOR', 'headline' => 'Full-Stack Developer & Tech Educator',
    'bio' => "Software engineer building fintech products across East Africa. I teach practical web development so more Ugandans can build careers in tech.",
    'created_at' => $daysAgo(90),
], $hash);

$graceId = insert_user([
    'name' => 'Grace Atim', 'email' => 'grace.atim@obinacademy.com', 'phone' => '+256700000004',
    'role' => 'CREATOR', 'headline' => 'Digital Marketing Strategist',
    'bio' => "I help brands and small businesses grow on social media and build online stores that actually sell. Previously led marketing at two Kampala startups.",
    'created_at' => $daysAgo(80),
], $hash);

$johnId = insert_user([
    'name' => 'John Mukasa', 'email' => 'john.mukasa@example.com', 'phone' => '+256700000005',
    'role' => 'LEARNER', 'created_at' => $daysAgo(60),
], $hash);

$maryId = insert_user([
    'name' => 'Mary Nabirye', 'email' => 'mary.nabirye@example.com', 'phone' => '+256700000006',
    'role' => 'LEARNER', 'created_at' => $daysAgo(50),
], $hash);

$peterId = insert_user([
    'name' => 'Peter Ssekandi', 'email' => 'peter.ssekandi@example.com', 'phone' => '+256700000007',
    'role' => 'LEARNER', 'created_at' => $daysAgo(40),
], $hash);

$graceLearnerId = insert_user([
    'name' => 'Grace Achieng', 'email' => 'grace.achieng@example.com', 'phone' => '+256700000008',
    'role' => 'LEARNER', 'created_at' => $daysAgo(30),
], $hash);

echo "Created 8 users (1 admin, 3 creators, 4 learners).\n";

// -----------------------------------------------------------------------
// Creator applications (approved, matching the 3 creator accounts above)
// -----------------------------------------------------------------------
foreach ([
    [$sarahId, 'Certified accountant (ACCA), 10+ years in personal finance coaching and SME bookkeeping.', 'I want to help everyday Ugandans build better money habits through practical, local-context courses.'],
    [$davidId, 'Full-stack developer, 6 years building web apps for fintech and logistics companies in Kampala.', 'There is huge demand for practical dev skills here and not enough affordable, locally-relevant training.'],
    [$graceId, 'Digital marketing strategist, ran marketing for two Kampala startups, certified in Meta & Google Ads.', 'Small businesses in Uganda are leaving money on the table by not knowing how to market online — I want to fix that.'],
] as [$uid, $expertise, $motivation]) {
    db_insert(
        "INSERT INTO creator_applications (user_id, status, expertise, motivation, created_at, reviewed_at) VALUES (?, 'APPROVED', ?, ?, ?, ?)",
        [$uid, $expertise, $motivation, $daysAgo(90), $daysAgo(88)]
    );
}
echo "Created 3 approved creator applications.\n";

// -----------------------------------------------------------------------
// Categories lookup
// -----------------------------------------------------------------------
$categories = [];
foreach (db_all('SELECT id, slug FROM categories') as $c) $categories[$c['slug']] = (int) $c['id'];

$thumbs = [
    '/uploads/thumbnails/1e0f39f8-16e2-44f0-9d82-7f8d1154bb66.png',
    '/uploads/thumbnails/2763a404-9aa8-4cca-85bd-842187d5b86a.webp',
    '/uploads/thumbnails/5706bdc4-8794-4f67-9e74-790956858ec3.jpg',
    '/uploads/thumbnails/af6a781b-a4ba-4326-8028-618ad20899bb.jpeg',
    '/uploads/thumbnails/b6baa699-7009-4c49-aa35-a63d6571ca37.jpg',
    '/uploads/thumbnails/e9998068-ad07-4d16-aa6f-f2f6192c19c9.png',
];

$SAMPLE_VIDEO = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
$SAMPLE_PDF = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf';

// -----------------------------------------------------------------------
// Courses (+ modules + lessons)
// -----------------------------------------------------------------------
$courseDefs = [
    [
        'creator' => $sarahId, 'category' => 'finance', 'thumb' => $thumbs[0],
        'title' => 'Personal Finance Mastery for Beginners',
        'summary' => 'Take control of your money: budgeting, saving, and debt payoff using tools that work in Uganda.',
        'description' => "A practical, judgment-free course on managing money as a working adult in Uganda — building a budget you'll actually stick to, saving with mobile money, and getting out of debt without giving up everything you enjoy.",
        'price' => 50000, 'access_duration_days' => 365, 'premium_price' => null, 'status' => 'PUBLISHED',
        'modules' => [
            ['Getting Started with Budgeting', [
                ['Welcome & Course Overview', 'VIDEO', 6],
                ['Setting Up Your First Budget', 'VIDEO', 14],
                ['Budgeting Worksheet (Download)', 'PDF', null],
            ]],
            ['Saving & Investing Basics', [
                ['Why You Should Save Before You Invest', 'VIDEO', 11],
                ['Using Mobile Money to Save Automatically', 'VIDEO', 9],
            ]],
            ['Getting Out of Debt', [
                ['Good Debt vs Bad Debt', 'VIDEO', 8],
                ['Your Debt Payoff Plan', 'PDF', null],
            ]],
        ],
    ],
    [
        'creator' => $davidId, 'category' => 'technology-software-development', 'thumb' => $thumbs[1],
        'title' => 'Practical Web Development with PHP & MySQL',
        'summary' => 'Build and deploy real, database-backed websites from scratch using PHP and MySQL.',
        'description' => "Learn web development the practical way: PHP fundamentals, MySQL databases, forms, authentication, and deploying a real project to shared hosting — the same stack powering thousands of small business sites.",
        'price' => 120000, 'access_duration_days' => null, 'premium_price' => 180000, 'status' => 'PUBLISHED',
        'modules' => [
            ['PHP Fundamentals', [
                ['Course Introduction', 'VIDEO', 5],
                ['Variables, Loops & Functions', 'VIDEO', 22],
                ['Working with Forms', 'VIDEO', 18],
            ]],
            ['Databases with MySQL', [
                ['Designing Your First Schema', 'VIDEO', 16],
                ['Connecting PHP to MySQL with PDO', 'VIDEO', 20],
                ['Schema Cheat Sheet', 'PDF', null],
            ]],
            ['Shipping to Production', [
                ['Authentication & Sessions', 'VIDEO', 19],
                ['Deploying to Shared Hosting', 'VIDEO', 13],
            ]],
        ],
    ],
    [
        'creator' => $graceId, 'category' => 'marketing-digital-marketing', 'thumb' => $thumbs[2],
        'title' => 'Social Media Marketing That Sells',
        'summary' => 'Turn Instagram, Facebook and TikTok followers into paying customers — without a big budget.',
        'description' => "A hands-on guide to marketing on the platforms your customers actually use: content that converts, running your first ad, and building a simple sales funnel for a small budget.",
        'price' => 80000, 'access_duration_days' => 180, 'premium_price' => null, 'status' => 'PUBLISHED',
        'modules' => [
            ['Foundations', [
                ['Why Most Small Business Pages Fail', 'VIDEO', 9],
                ['Picking the Right Platform for Your Business', 'VIDEO', 12],
            ]],
            ['Content That Converts', [
                ['Content Planning Template', 'PDF', null],
                ['Shooting Product Photos on a Phone', 'VIDEO', 15],
                ['Writing Captions That Sell', 'VIDEO', 10],
            ]],
            ['Running Your First Ad', [
                ['Meta Ads Manager Walkthrough', 'VIDEO', 21],
                ['Reading Your Ad Results', 'VIDEO', 11],
            ]],
        ],
    ],
    [
        'creator' => $sarahId, 'category' => 'business', 'thumb' => $thumbs[3],
        'title' => 'Small Business Accounting Fundamentals',
        'summary' => 'Keep clean books, track profit, and prepare for tax season without hiring an accountant.',
        'description' => "For shop owners, freelancers and small business operators: bookkeeping basics, tracking income and expenses, and understanding your numbers well enough to make better decisions.",
        'price' => 60000, 'access_duration_days' => 365, 'premium_price' => null, 'status' => 'PUBLISHED',
        'modules' => [
            ['Bookkeeping Basics', [
                ['Why Bookkeeping Matters', 'VIDEO', 7],
                ['Setting Up a Simple Ledger', 'VIDEO', 13],
                ['Ledger Template', 'PDF', null],
            ]],
            ['Understanding Your Numbers', [
                ['Profit vs Cash Flow', 'VIDEO', 10],
                ['Preparing for Tax Season', 'VIDEO', 14],
            ]],
        ],
    ],
    [
        'creator' => $davidId, 'category' => 'technology-software-development', 'thumb' => $thumbs[4],
        'title' => 'Modern JavaScript & React Crash Course',
        'summary' => 'Go from JavaScript basics to building interactive React interfaces.',
        'description' => "A fast-paced, project-driven crash course covering modern JavaScript (ES6+), the DOM, and building your first interactive UIs with React.",
        'price' => 150000, 'access_duration_days' => null, 'premium_price' => 220000, 'status' => 'PUBLISHED',
        'modules' => [
            ['Modern JavaScript', [
                ['ES6+ Essentials', 'VIDEO', 24],
                ['Working with Arrays & Objects', 'VIDEO', 17],
            ]],
            ['React Basics', [
                ['Components & Props', 'VIDEO', 19],
                ['State & Events', 'VIDEO', 21],
                ['React Cheat Sheet', 'PDF', null],
            ]],
        ],
    ],
    [
        'creator' => $graceId, 'category' => 'ecommerce', 'thumb' => $thumbs[5],
        'title' => 'Building a Profitable Ecommerce Store in Uganda',
        'summary' => 'Launch and grow an online store using local delivery and mobile money payments.',
        'description' => "Everything you need to launch an online store selling to Ugandan customers: picking products, setting up payments and delivery, and your first month of marketing.",
        'price' => 90000, 'access_duration_days' => 365, 'premium_price' => null, 'status' => 'PUBLISHED',
        'modules' => [
            ['Setting Up Your Store', [
                ['Choosing Products That Sell', 'VIDEO', 12],
                ['Store Setup Walkthrough', 'VIDEO', 18],
            ]],
            ['Payments & Delivery', [
                ['Accepting Mobile Money Payments', 'VIDEO', 9],
                ['Working with Local Delivery Riders', 'VIDEO', 8],
                ['Delivery Cost Calculator', 'PDF', null],
            ]],
        ],
    ],
];

// A few extra courses in non-published states, for admin/creator dashboard demos.
$extraCourseDefs = [
    [
        'creator' => $sarahId, 'category' => 'finance', 'thumb' => null,
        'title' => 'Advanced Excel for Financial Analysts', 'status' => 'PENDING_REVIEW',
        'summary' => 'Build financial models and dashboards in Excel used by real analysts.',
        'description' => 'Formulas, pivot tables, and financial modelling techniques for anyone working with numbers.',
        'price' => 70000, 'access_duration_days' => 365, 'premium_price' => null,
        'submitted_at' => $daysAgo(2),
    ],
    [
        'creator' => $graceId, 'category' => 'design-creative', 'thumb' => null,
        'title' => 'Intro to Graphic Design', 'status' => 'DRAFT',
        'summary' => 'Learn design fundamentals using free tools like Canva and Figma.',
        'description' => 'A beginner-friendly introduction to layout, color and typography for social media and print.',
        'price' => 40000, 'access_duration_days' => 180, 'premium_price' => null,
    ],
    [
        'creator' => $davidId, 'category' => 'technology-software-development', 'thumb' => null,
        'title' => 'Crypto Trading Basics', 'status' => 'REJECTED',
        'summary' => 'An introduction to cryptocurrency trading concepts.',
        'description' => 'Covers wallets, exchanges and basic trading concepts.',
        'price' => 100000, 'access_duration_days' => 90, 'premium_price' => null,
        'submitted_at' => $daysAgo(10),
        'rejection_reason' => 'Financial-advice content needs a compliance review before we can publish trading courses. Please resubmit with risk disclaimers and remove specific buy/sell signals.',
    ],
];

$courseIds = []; // slug => id, for published courses only (used for enrollments)

foreach ($courseDefs as $i => $def) {
    $slug = slugify($def['title']);
    $courseId = db_insert(
        'INSERT INTO courses (title, slug, summary, description, thumbnail_url, price, access_duration_days, premium_price, status, submitted_at, reviewed_at, created_at, creator_id, category_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $def['title'], $slug, $def['summary'], $def['description'], $def['thumb'],
            $def['price'], $def['access_duration_days'], $def['premium_price'], $def['status'],
            $daysAgo(70 - $i * 5), $daysAgo(69 - $i * 5), $daysAgo(70 - $i * 5),
            $def['creator'], $categories[$def['category']],
        ]
    );
    $courseIds[$slug] = $courseId;

    foreach ($def['modules'] as $modOrder => [$modTitle, $lessons]) {
        $moduleId = db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?, ?, ?)', [$modTitle, $modOrder, $courseId]);
        foreach ($lessons as $lessonOrder => [$lessonTitle, $type, $duration]) {
            $fileUrl = $type === 'VIDEO' ? $SAMPLE_VIDEO : $SAMPLE_PDF;
            $fileName = $type === 'PDF' ? slugify($lessonTitle) . '.pdf' : null;
            db_insert(
                'INSERT INTO lessons (title, type, file_url, file_name, duration, sort_order, module_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$lessonTitle, $type, $fileUrl, $fileName, $duration ? $duration * 60 : null, $lessonOrder, $moduleId]
            );
        }
    }
}

foreach ($extraCourseDefs as $def) {
    $slug = slugify($def['title']);
    db_insert(
        'INSERT INTO courses (title, slug, summary, description, thumbnail_url, price, access_duration_days, premium_price, status, submitted_at, rejection_reason, created_at, creator_id, category_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $def['title'], $slug, $def['summary'], $def['description'], $def['thumb'],
            $def['price'], $def['access_duration_days'], $def['premium_price'], $def['status'],
            $def['submitted_at'] ?? null, $def['rejection_reason'] ?? null, $daysAgo(15),
            $def['creator'], $categories[$def['category']],
        ]
    );
}

echo "Created " . (count($courseDefs) + count($extraCourseDefs)) . " courses (" . count($courseDefs) . " published) with modules and lessons.\n";

// -----------------------------------------------------------------------
// Enrollments + matching payments + earnings (mirrors enroll_in_course()'s
// gross/fee/net split so creator earnings & admin revenue stats add up).
// -----------------------------------------------------------------------
function seed_enroll(int $userId, int $courseId, float $progress, ?int $daysAgoEnrolled, bool $premium = false): void {
    global $pdo;
    $course = db_one('SELECT price, creator_id, access_duration_days FROM courses WHERE id = ?', [$courseId]);
    $enrolledAt = $daysAgoEnrolled !== null ? date('Y-m-d H:i:s', time() - $daysAgoEnrolled * 86400) : date('Y-m-d H:i:s');
    $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);

    db_insert(
        'INSERT INTO enrollments (user_id, course_id, progress, enrolled_at, expires_at, is_premium) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $courseId, $progress, $enrolledAt, $expiresAt, $premium ? 1 : 0]
    );

    $split = split_sale((float) $course['price']);
    db_insert(
        'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$course['creator_id'], $courseId, $split['net'], $split['gross'], $split['fee'], $enrolledAt]
    );

    $txId = 'DEMO-' . strtoupper(bin2hex(random_bytes(6)));
    db_insert(
        'INSERT INTO payments (iotec_transaction_id, amount, phone, type, status, status_message, created_at, user_id, course_id) VALUES (?, ?, ?, "COURSE_PURCHASE", "SUCCESS", "Payment completed.", ?, ?, ?)',
        [$txId, $split['gross'], '+25670000000' . random_int(1, 9), $enrolledAt, $userId, $courseId]
    );
}

$c = fn(string $slug) => $courseIds[$slug];

seed_enroll($johnId, $c('personal-finance-mastery-for-beginners'), 45, 20);
seed_enroll($johnId, $c('practical-web-development-with-php-mysql'), 100, 35);
seed_enroll($johnId, $c('social-media-marketing-that-sells'), 10, 3);

seed_enroll($maryId, $c('personal-finance-mastery-for-beginners'), 100, 40);
seed_enroll($maryId, $c('small-business-accounting-fundamentals'), 60, 15);
seed_enroll($maryId, $c('modern-javascript-react-crash-course'), 0, 1, true);

seed_enroll($peterId, $c('practical-web-development-with-php-mysql'), 30, 8);
seed_enroll($peterId, $c('building-a-profitable-ecommerce-store-in-uganda'), 75, 22, true);

seed_enroll($graceLearnerId, $c('social-media-marketing-that-sells'), 100, 25);
seed_enroll($graceLearnerId, $c('building-a-profitable-ecommerce-store-in-uganda'), 20, 5);

echo "Created 10 enrollments with matching payments & earnings.\n";

// -----------------------------------------------------------------------
// Reviews (from learners who completed a course)
// -----------------------------------------------------------------------
$reviews = [
    [$c('practical-web-development-with-php-mysql'), $johnId, 5, "Best PHP course I've found for Ugandan developers — practical, no fluff, and David actually explains the 'why'. Deployed my first real site after this."],
    [$c('personal-finance-mastery-for-beginners'), $maryId, 5, 'Changed how I think about my salary. The mobile money saving trick alone paid for the course in a month.'],
    [$c('social-media-marketing-that-sells'), $graceLearnerId, 4, "Great practical content, especially the ad walkthrough. Would love more examples for service-based businesses next."],
];
foreach ($reviews as [$courseId, $authorId, $rating, $comment]) {
    db_insert('INSERT INTO reviews (course_id, author_id, rating, comment) VALUES (?, ?, ?, ?)', [$courseId, $authorId, $rating, $comment]);
}
echo "Created " . count($reviews) . " course reviews.\n";

// -----------------------------------------------------------------------
// Testimonials (published, shown on the homepage)
// -----------------------------------------------------------------------
$testimonials = [
    [$maryId, 'Obin Academy helped me finally get my budget under control. The lessons feel like they were made for how we actually live in Uganda, not a copy-paste American course.', 5],
    [$johnId, "I went from barely knowing HTML to shipping a working PHP site for my cousin's shop. David's course is worth way more than the price.", 5],
    [$graceLearnerId, "Enrolled in the marketing course to help my sister's boutique — within a month our Instagram orders doubled.", 5],
];
foreach ($testimonials as $order => [$authorId, $quote, $rating]) {
    db_insert(
        'INSERT INTO testimonials (author_id, quote, rating, status, reviewed_at, created_at) VALUES (?, ?, ?, "PUBLISHED", ?, ?)',
        [$authorId, $quote, $rating, $daysAgo(5 - $order), $daysAgo(6 - $order)]
    );
}
echo "Created " . count($testimonials) . " published testimonials.\n";

// -----------------------------------------------------------------------
// One pending withdrawal request, for the admin withdrawals screen
// -----------------------------------------------------------------------
db_insert(
    'INSERT INTO withdrawal_requests (creator_id, amount, phone, status, requested_at) VALUES (?, ?, ?, "PENDING", ?)',
    [$davidId, 150000, '+256700000003', $daysAgo(1)]
);
echo "Created 1 pending withdrawal request.\n";

// -----------------------------------------------------------------------
// Audit log entries
// -----------------------------------------------------------------------
$auditEntries = [
    ['CREATOR_APPLICATION_APPROVED', 'user', 'Sarah Namuli', 'Approved creator application', $daysAgo(88)],
    ['CREATOR_APPLICATION_APPROVED', 'user', 'David Okello', 'Approved creator application', $daysAgo(87)],
    ['CREATOR_APPLICATION_APPROVED', 'user', 'Grace Atim', 'Approved creator application', $daysAgo(86)],
    ['COURSE_PUBLISHED', 'course', 'Personal Finance Mastery for Beginners', 'Reviewed and published', $daysAgo(69)],
    ['COURSE_PUBLISHED', 'course', 'Practical Web Development with PHP & MySQL', 'Reviewed and published', $daysAgo(64)],
    ['COURSE_REJECTED', 'course', 'Crypto Trading Basics', 'Rejected pending compliance review', $daysAgo(9)],
];
foreach ($auditEntries as [$action, $targetType, $targetLabel, $detail, $when]) {
    db_insert(
        'INSERT INTO audit_log (action, target_type, target_label, detail, created_at, actor_id, actor_name) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$action, $targetType, $targetLabel, $detail, $when, $adminId, 'Obin Admin']
    );
}
echo "Created " . count($auditEntries) . " audit log entries.\n";

echo "\nDone. Demo accounts (all use password: $DEMO_PASSWORD):\n";
echo "  Admin:    admin@obinacademy.com\n";
echo "  Creator:  sarah.namuli@obinacademy.com\n";
echo "  Creator:  david.okello@obinacademy.com\n";
echo "  Creator:  grace.atim@obinacademy.com\n";
echo "  Learner:  john.mukasa@example.com\n";
echo "  Learner:  mary.nabirye@example.com\n";
echo "  Learner:  peter.ssekandi@example.com\n";
echo "  Learner:  grace.achieng@example.com\n";

<?php
/**
 * The public campus page (public/campus.php): the copy that sells each campus, and the professor's
 * social links. Each campus has its own hook, live demo (three short tasks typed in the hero console),
 * "is this you" cards, six skills, a planned year (shown until the professor writes the real twelve
 * stages) and questions. Everything else on the page (prices, professor, stages, pictures) comes from
 * the database. Requires includes/campuses.php.
 */

/** Social networks a professor can link, in display order: key => [label, SVG path]. */
const CAMPUS_PROFESSOR_SOCIALS = [
    'instagram' => ['Instagram', 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 8.2A3.2 3.2 0 1 1 12 8.8a3.2 3.2 0 0 1 0 6.4zM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zM21.9 7.1c-.1-1.6-.4-3-1.6-4.2S17.6 1.3 16 1.2C14.3 1 9.7 1 8 1.2 6.4 1.3 5 1.6 3.8 2.8S2.2 5.5 2.1 7.1C2 8.8 2 13.2 2.1 14.9c.1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.7.1 6.3.1 8 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.2-1.7.2-6.1.2-7.8zm-2.4 10.6a3.2 3.2 0 0 1-1.8 1.8c-1.3.5-4.3.4-5.7.4s-4.4.1-5.7-.4a3.2 3.2 0 0 1-1.8-1.8c-.5-1.3-.4-4.3-.4-5.7s-.1-4.4.4-5.7a3.2 3.2 0 0 1 1.8-1.8C7.6 4 10.6 4.1 12 4.1s4.4-.1 5.7.4a3.2 3.2 0 0 1 1.8 1.8c.5 1.3.4 4.3.4 5.7s.1 4.4-.4 5.7z'],
    'tiktok' => ['TikTok', 'M19.6 6.7a4.8 4.8 0 0 1-3.8-4.2V2h-3.4v13.7a2.9 2.9 0 1 1-2-2.7V9.5a6.3 6.3 0 1 0 5.4 6.2V8.8a8.2 8.2 0 0 0 4.8 1.5V6.9l-1-.2z'],
    'youtube' => ['YouTube', 'M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31.3 31.3 0 0 0 0 12a31.3 31.3 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.3 31.3 0 0 0 24 12a31.3 31.3 0 0 0-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z'],
    'x' => ['X', 'M18.2 2.3h3.4l-7.4 8.4 8.7 11.5h-6.8l-5.3-7-6.1 7H1.3l7.9-9L.9 2.3h7l4.8 6.4 5.5-6.4zm-1.2 17.9h1.9L7.1 4.2H5.1z'],
    'linkedin' => ['LinkedIn', 'M20.4 20.5h-3.6v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9v5.7H9.4V9h3.4v1.6h.1a3.8 3.8 0 0 1 3.4-1.9c3.6 0 4.3 2.4 4.3 5.5v6.3zM5.3 7.4a2.1 2.1 0 1 1 0-4.1 2.1 2.1 0 0 1 0 4.1zm1.8 13.1H3.6V9h3.5v11.5zM22.2 0H1.8C.8 0 0 .8 0 1.7v20.6c0 .9.8 1.7 1.8 1.7h20.4c1 0 1.8-.8 1.8-1.7V1.7C24 .8 23.2 0 22.2 0z'],
    'facebook' => ['Facebook', 'M24 12.1A12 12 0 1 0 10.1 24v-8.4H7.1v-3.5h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9V24A12 12 0 0 0 24 12.1z'],
    'whatsapp' => ['WhatsApp', 'M17.5 14.4c-.3-.2-1.8-.9-2-1-.3-.1-.5-.2-.7.1-.2.3-.8 1-.9 1.2-.2.2-.4.2-.6.1-.3-.2-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.4zM12 21.8a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.7-.2-.4A9.9 9.9 0 1 1 12 21.8zm8.4-18.3A11.8 11.8 0 0 0 .2 11.9c0 2.1.5 4.1 1.6 5.9L.1 24l6.3-1.7a11.9 11.9 0 0 0 5.7 1.4C18.6 23.8 24 18.5 24 11.9c0-3.2-1.2-6.2-3.6-8.4z'],
];

/** True once migration/add-professor-links.sql has been applied. */
function campus_links_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT professor_quote, professor_instagram, professor_whatsapp FROM campuses LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/**
 * A professor's link as typed (a full link, a handle like @name, or for WhatsApp a phone number) turned
 * into a safe https:// address, or null if it isn't usable.
 */
function campus_social_url(string $key, ?string $value): ?string {
    $v = trim((string) $value);
    if ($v === '') return null;
    if ($key === 'whatsapp') {
        $digits = preg_replace('/\D/', '', $v);
        if (str_starts_with($digits, '0')) $digits = '256' . substr($digits, 1);
        return strlen($digits) >= 10 ? 'https://wa.me/' . $digits : null;
    }
    if (preg_match('#^https?://#i', $v)) {
        $url = preg_replace('#^http://#i', 'https://', $v);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    $handle = ltrim(preg_replace('/\s+/', '', $v), '@');
    if ($handle === '' || !preg_match('/^[A-Za-z0-9._\-\/]+$/', $handle)) return null;
    return [
        'instagram' => 'https://instagram.com/', 'tiktok' => 'https://www.tiktok.com/@', 'youtube' => 'https://www.youtube.com/@',
        'x' => 'https://x.com/', 'linkedin' => 'https://www.linkedin.com/in/', 'facebook' => 'https://facebook.com/',
    ][$key] . $handle;
}

/** The professor's usable links for a campus: [key => [label, url, svg path]]. */
function campus_professor_links(array $campus): array {
    $out = [];
    foreach (CAMPUS_PROFESSOR_SOCIALS as $key => [$label, $path]) {
        $url = campus_social_url($key, $campus['professor_' . $key] ?? null);
        if ($url) $out[$key] = [$label, $url, $path];
    }
    return $out;
}

/** Saves the professor's quote and links from a posted form (fields quote, instagram, tiktok, ...). */
function campus_save_professor_links(int $campusId): void {
    if (!campus_links_ready()) throw new RuntimeException('Professor links are being set up. Try again shortly.');
    $vals = [mb_substr(trim(post('quote')), 0, 300) ?: null];
    $sets = ['professor_quote = ?'];
    foreach (array_keys(CAMPUS_PROFESSOR_SOCIALS) as $key) {
        $raw = trim(post($key));
        if ($raw !== '' && !campus_social_url($key, $raw)) {
            throw new RuntimeException(CAMPUS_PROFESSOR_SOCIALS[$key][0] . ': paste the full link (https://…)' . ($key === 'whatsapp' ? ' or a phone number like 0772 123 456' : ' or the @username') . '.');
        }
        $sets[] = 'professor_' . $key . ' = ?';
        $vals[] = $raw !== '' ? mb_substr($raw, 0, $key === 'whatsapp' ? 32 : 255) : null;
    }
    $vals[] = $campusId;
    db_run('UPDATE campuses SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
}

/** The words that sell one campus. Unknown campuses get a sensible general version. */
function campus_page_content(array $campus): array {
    $name = $campus['name'];
    $pages = [
        'ai-technology' => [
            'headline' => ['The AI skills businesses', 'are paying for.'],
            'lede' => 'Businesses everywhere need help using AI, and most people still don\'t know how. In this campus you become the one who does: twelve months, a professor who teaches you live every week, and a clear path from your first prompt to your first client.',
            'demo_title' => 'what you will do in month 2', 'demo_badge' => 'LIVE DEMO', 'demo_who' => 'AI',
            'tasks' => [
                ['Marketing copy', 'Write a short WhatsApp ad for a salon in Ntinda. Friendly, with a weekday offer.', "Fresh look, fresh week ✨\nThis Monday to Thursday, get 20% off braids and treatments at Glow Salon, Ntinda.\nBook on this number and come relax with us. Walk-ins welcome!"],
                ['Customer replies', 'A customer asks if we deliver to Entebbe and how much. Draft a polite reply.', "Hello! Yes, we deliver to Entebbe 🚚\nDelivery is UGX 10,000 and arrives the next working day.\nShare your location and we will confirm your order right away."],
                ['Business research', 'Give me 3 ideas a small bakery in Mbarara could sell on weekends.', "1. Breakfast boxes for offices, ordered by Friday noon.\n2. Birthday cupcake packs with names on top.\n3. A Sunday bread subscription, delivered to estates."],
            ],
            'fit_h2' => ['Made for people who want a', 'skill that pays.'],
            'fit_p' => 'You don\'t need to be technical. You need a phone, a few hours a week, and the will to practise what you learn on real work.',
            'fit' => [
                ['You want a skill you can sell this year', 'Not theory. Services local businesses already ask for, taught step by step.'],
                ['You run a business and want to do more with less', 'Write, design, answer customers and plan in minutes instead of days.'],
                ['You keep hearing about AI and feel left behind', 'Start from zero. Month one covers the basics with no jargon.'],
            ],
            'skills_label' => 'What you\'ll be able to offer', 'skills_h2' => ['Six services,', 'one campus.'],
            'skills_p' => 'Each one is taught as a real job: what the client asks for, how you do it with AI, how you check the work, and how you price it.',
            'skills' => [
                ['pen', 'Months 2–3', 'Writing and copy', 'Ads, product descriptions, captions, emails and WhatsApp messages that sell.'],
                ['image', 'Months 4–5', 'Design and visuals', 'Flyers, logos, social posts and product photos made with AI tools.'],
                ['video', 'Month 6', 'Video and content', 'Scripts, voice-overs and short videos for brands that need to post daily.'],
                ['chat', 'Months 7–8', 'Chatbots', 'Assistants that answer a shop\'s customers on WhatsApp and the web, day and night.'],
                ['spark', 'Month 9', 'Automations', 'Connect forms, sheets and messages so routine work runs by itself.'],
                ['search', 'Month 10', 'Research and reports', 'Market research, summaries and business plans, checked and clearly presented.'],
            ],
            'skills_note' => 'What you earn depends on the clients you find and the work you put in. We teach the skill and how to sell it; we don\'t promise income.',
            'year' => [['AI, explained simply', 'What AI can and cannot do, and your first useful prompts.'], ['Prompting that works', 'Get clear, reliable answers every time.'], ['Writing with AI', 'Ads, captions and messages that sell.'], ['Design with AI', 'Flyers, logos and product images.'], ['Your brand kit', 'A consistent look for every client.'], ['Video and voice', 'Scripts, voice-overs and short videos.'], ['Chatbots I', 'An assistant that answers customers.'], ['Chatbots II', 'Put it on WhatsApp and a website.'], ['Automations', 'Forms, sheets and messages that run themselves.'], ['Research and reports', 'Market research and business plans.'], ['Portfolio and pricing', 'Show your work and set your prices.'], ['Your first clients', 'Find, pitch and deliver for real clients.']],
            'faq' => [
                ['Do I need a laptop or coding skills?', 'No coding at all. You can start with a smartphone; a computer helps for some design and automation work later in the year, and your professor shows phone-friendly ways where possible.'],
                ['Which AI tools will I use?', 'The everyday tools businesses actually use, starting with ChatGPT and free alternatives. Where a paid tool is worth it, your professor explains when and why, and shows a free option first.'],
            ],
        ],
        'business-finance' => [
            'headline' => ['Run a business that', 'makes real profit.'],
            'lede' => 'Most small businesses don\'t fail for lack of customers. They fail on pricing, cash flow and records. In this campus your professor teaches you, live every week, to price right, track every shilling, and grow a business that lasts.',
            'demo_title' => 'what you will do in month 2', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Pricing', 'I sell chapati at UGX 500 and still struggle. How do I know my real cost?', "Add up everything for one batch: flour, oil, gas, transport and your time.\nDivide by the number you make. That is your cost per chapati.\nYour price must cover it plus profit. We build this sheet together in month 2."],
                ['Cash flow', 'My shop sells well but I never have money at the end of the month. Why?', "Usually it's mixing business and personal money, or stock you can't sell.\nOpen a separate business account and record every sale and expense daily.\nIn month 3 you build a simple cash book you can keep on your phone."],
                ['Growth', 'Should I open a second branch or grow the first one?', "First check if the current shop is profitable for 6 months in a row.\nThen compare: what extra sales could the first shop make with the same money?\nWe test both with real numbers in month 9."],
            ],
            'fit_h2' => ['Made for people who want a', 'business that grows.'],
            'fit_p' => 'Whether you are starting, running a shop or planning a side business, you learn the money skills nobody taught you at school.',
            'fit' => [
                ['You run a small business and feel stuck', 'Sales come in but profit doesn\'t. Learn where the money goes.'],
                ['You want to start a business the right way', 'Test the idea, plan the money and avoid the common mistakes.'],
                ['You want to manage money with confidence', 'Budgets, savings and records that make decisions easier.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'Each skill is taught with real Ugandan business examples, and you apply it to your own business or idea as you go.',
            'skills' => [
                ['tag', 'Months 1–2', 'Pricing for profit', 'Know your real costs and set prices that leave you a profit.'],
                ['book', 'Month 3', 'Records and cash flow', 'A simple cash book that shows what comes in and goes out.'],
                ['chart', 'Months 4–5', 'Selling and marketing', 'Find customers and sell more without wasting money on ads.'],
                ['users', 'Month 6', 'Staff and suppliers', 'Hire, pay and negotiate so the business works without you there all day.'],
                ['bank', 'Months 7–8', 'Loans and saving', 'When to borrow, from whom, and how to save for the next step.'],
                ['spark', 'Months 9–12', 'Growth and planning', 'A written plan for your next year, built on your real numbers.'],
            ],
            'skills_note' => 'Your results depend on your business and how you apply what you learn. We teach the skills; we don\'t promise income.',
            'year' => [['Your money picture', 'Where your business stands today.'], ['Pricing for profit', 'Real costs and the right price.'], ['Records and cash flow', 'A cash book you actually keep.'], ['Finding customers', 'Who buys and why.'], ['Selling better', 'Offers, follow-up and repeat buyers.'], ['People and suppliers', 'Staff, suppliers and negotiation.'], ['Borrowing wisely', 'Loans, SACCOs and when to say no.'], ['Saving and reinvesting', 'Grow from your own profit.'], ['Testing growth', 'Branch, product or market: compare with numbers.'], ['Systems', 'Run the business without being there all day.'], ['Your business plan', 'A plan for the next year.'], ['Present and grow', 'Pitch to partners, lenders and investors.']],
            'faq' => [['Do I need to have a business already?', 'No. If you have one, you apply each lesson to it. If you don\'t, you build and test an idea through the year.']],
        ],
        'crypto-trading' => [
            'headline' => ['Understand crypto before', 'you risk a shilling.'],
            'lede' => 'Crypto moves fast and most beginners lose money chasing tips. In this campus your professor teaches you, live every week, how the market really works, how to protect your money, and how to trade and invest with a written plan.',
            'demo_title' => 'what you will do in month 3', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Risk first', 'I have UGX 500,000. How much should I risk on one trade?', "A common rule is 1 to 2% per trade: UGX 5,000 to 10,000.\nThat way ten losses in a row don't wipe you out.\nYou set your position size from your stop loss, which we practise in month 3."],
                ['Avoiding scams', 'Someone on WhatsApp promises 30% a week if I send them USDT. Is it real?', "No legitimate investment guarantees 30% a week. This is the classic sign of a scam.\nNever send money to someone who promises fixed returns.\nMonth 1 covers how to spot scams before they cost you."],
                ['Reading the chart', 'Bitcoin dropped 8% today. Should I buy now?', "Not because it dropped. First check the bigger trend on the daily chart.\nThen decide your entry, your stop and how much you can lose.\nNo plan, no trade: that is the habit we build all year."],
            ],
            'fit_h2' => ['Made for people who want to', 'trade with a plan.'],
            'fit_p' => 'You don\'t need money to start learning. You need patience, discipline and the honesty to follow a plan.',
            'fit' => [
                ['You are curious about crypto but afraid of losing', 'Start with how it works, then practise without real money.'],
                ['You have traded and lost money', 'Learn the risk rules and the journal habit that change results.'],
                ['You want to invest for the long term', 'Build a simple, steady plan instead of chasing hype.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'Taught step by step, with practice accounts first and real charts every week.',
            'skills' => [
                ['shield', 'Month 1', 'Safety and scams', 'Wallets, exchanges and how to spot the scams that target beginners.'],
                ['chart', 'Months 2–3', 'Reading charts', 'Candles, trends, support and resistance on real coins.'],
                ['scale', 'Month 4', 'Risk management', 'Position size, stop losses and protecting your capital.'],
                ['book', 'Months 5–6', 'Your trading plan', 'Written rules for when you enter, exit and stay out.'],
                ['pen', 'Months 7–9', 'Journaling and review', 'Track every trade and improve from your own data.'],
                ['bank', 'Months 10–12', 'Long-term investing', 'Build and hold a portfolio with a clear plan.'],
            ],
            'skills_note' => 'Trading and investing carry a real risk of loss. Lessons are educational and are not financial advice. We don\'t promise any profit.',
            'year' => [['Crypto, explained', 'How it works, wallets and staying safe.'], ['Exchanges and orders', 'Buying, selling and order types.'], ['Reading charts', 'Candles, trends and levels.'], ['Risk first', 'Position size and stop losses.'], ['Strategies', 'Simple setups you can test.'], ['Your trading plan', 'Written rules you follow.'], ['Practice trading', 'Test the plan without real money.'], ['Journaling', 'Learn from every trade.'], ['Psychology', 'Fear, greed and discipline.'], ['Long-term investing', 'Portfolios and holding.'], ['Taxes and records', 'Keep clean records.'], ['Your edge', 'Review the year and set your rules.']],
            'faq' => [['Do I need money to start?', 'No. You learn and practise with demo accounts first. Only trade real money you can afford to lose, and only once you have a tested plan.']],
        ],
        'forex-trading' => [
            'headline' => ['Learn forex the', 'disciplined way.'],
            'lede' => 'Forex rewards discipline, not luck. In this campus your professor teaches you, live every week, to read charts, trade the right sessions, control your risk and test a strategy before real money is on the line.',
            'demo_title' => 'what you will do in month 3', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Sessions', 'What time should I trade EUR/USD from Uganda?', "The busiest time is the London and New York overlap, about 4 pm to 8 pm in Kampala.\nMore movement, tighter spreads.\nIn month 2 you map the sessions to your own day."],
                ['Risk', 'My account is $200. How big should my lot size be?', "Risk 1% per trade: \$2.\nIf your stop loss is 20 pips, that is about 0.01 lots on EUR/USD.\nWe calculate this together until it becomes automatic."],
                ['Testing', 'How do I know if my strategy works?', "Test it on past charts first: at least 50 trades, written in a journal.\nThen trade it on a demo account for a month.\nOnly then consider real money, small."],
            ],
            'fit_h2' => ['Made for people who want to', 'trade with discipline.'],
            'fit_p' => 'You don\'t need experience. You need time to practise, the patience to test, and the discipline to follow your rules.',
            'fit' => [
                ['You are new to forex and want the basics right', 'Pairs, pips, lots and sessions, explained clearly.'],
                ['You trade but your results are up and down', 'Build rules, a journal and risk control that bring consistency.'],
                ['You want a skill you can practise from your phone', 'Charts and demo trading work on a smartphone.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'Practice first on demo accounts, with live chart reviews every week.',
            'skills' => [
                ['globe', 'Month 1', 'How forex works', 'Pairs, pips, lots, brokers and sessions.'],
                ['chart', 'Months 2–3', 'Chart reading', 'Trends, levels and price action on real pairs.'],
                ['scale', 'Month 4', 'Risk control', 'Lot size, stop loss and protecting your account.'],
                ['book', 'Months 5–6', 'A tested strategy', 'One simple strategy, tested on past charts.'],
                ['pen', 'Months 7–9', 'Journaling', 'Review every trade and fix your mistakes.'],
                ['spark', 'Months 10–12', 'Trading psychology', 'Discipline, patience and your own trading rules.'],
            ],
            'skills_note' => 'Trading carries a real risk of loss. Lessons are educational and are not financial advice. We don\'t promise any profit.',
            'year' => [['Forex foundations', 'Pairs, pips and lots.'], ['Sessions and brokers', 'When and where to trade.'], ['Reading the chart', 'Candles, trends and levels.'], ['Risk first', 'Position sizing and stops.'], ['Price action', 'Setups you can see.'], ['Your strategy', 'Rules you can test.'], ['Backtesting', 'Prove it on past charts.'], ['Demo trading', 'Practise without real money.'], ['Journaling', 'Learn from every trade.'], ['Psychology', 'Fear, greed and discipline.'], ['Going live, small', 'Real money, tiny risk.'], ['Your edge', 'Review and refine.']],
            'faq' => [['Do I need money to start?', 'No. You learn and practise on a demo account first. Only trade real money you can afford to lose, and only once you have a tested plan.']],
        ],
        'content-creation' => [
            'headline' => ['Grow an audience', 'that pays you back.'],
            'lede' => 'Views don\'t pay bills unless you know what to do with them. In this campus your professor teaches you, live every week, to make content people watch, grow a loyal audience, and turn it into income from brands, products and services.',
            'demo_title' => 'what you will do in month 2', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Hooks', 'My TikToks get 200 views. How do I make people stop scrolling?', "The first 2 seconds decide everything.\nStart with the result or a bold question, not 'Hello guys'.\nIn month 2 you write 30 hooks and test which ones hold viewers."],
                ['Consistency', 'I don\'t know what to post every day.', "Pick 3 content pillars: teach, show, and behind the scenes.\nPlan a week in one sitting and batch-film on one day.\nYou leave month 3 with a 30-day content calendar."],
                ['Money', 'I have 5,000 followers. How do I start earning?', "Start where trust is: a small service or product your audience already asks about.\nThen pitch local brands with your numbers.\nMonth 9 covers brand deals and your rate card."],
            ],
            'fit_h2' => ['Made for people who want', 'content that earns.'],
            'fit_p' => 'Start with the phone you have. What matters is a plan, consistency and knowing what your audience wants.',
            'fit' => [
                ['You post but your audience isn\'t growing', 'Learn hooks, formats and timing that get content seen.'],
                ['You have followers but no income', 'Turn attention into brand deals, products and services.'],
                ['You run a business and need content', 'Make content that brings customers, not just likes.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'You create as you learn, with feedback on your real content every week.',
            'skills' => [
                ['spark', 'Months 1–2', 'Hooks and ideas', 'Ideas that fit your audience and openings that stop the scroll.'],
                ['video', 'Months 3–4', 'Filming and editing', 'Good video and sound on a phone, edited fast.'],
                ['users', 'Months 5–6', 'Growing an audience', 'Formats, timing and community that build loyal followers.'],
                ['chart', 'Month 7', 'Reading your numbers', 'Understand analytics and do more of what works.'],
                ['tag', 'Months 8–9', 'Brand deals', 'Pitch brands, set your rates and deliver well.'],
                ['bag', 'Months 10–12', 'Your own offer', 'Products and services your audience pays for.'],
            ],
            'skills_note' => 'Growth and income depend on your content and consistency. We teach the skills; we don\'t promise income.',
            'year' => [['Find your angle', 'Your niche and your audience.'], ['Hooks and ideas', 'Stop the scroll.'], ['Filming on a phone', 'Light, sound and framing.'], ['Fast editing', 'Edit quickly on your phone.'], ['Formats that grow', 'Series, trends and stories.'], ['Building community', 'Comments, lives and loyalty.'], ['Your numbers', 'Analytics that guide you.'], ['Brand deals', 'Pitch and price your work.'], ['Delivering for brands', 'Briefs, deadlines and reports.'], ['Your own product', 'What your audience will buy.'], ['Systems', 'A schedule you can keep.'], ['Your next year', 'Plan the growth ahead.']],
            'faq' => [['Do I need expensive equipment?', 'No. Everything is taught on a smartphone. Your professor shows cheap upgrades (light, mic) only when they make a real difference.']],
        ],
        'fitness-nutrition' => [
            'headline' => ['Get fit with food', 'you already eat.'],
            'lede' => 'You don\'t need a gym membership or imported diets. In this campus your professor teaches you, live every week, to train, eat and recover well with Ugandan food and a routine that fits your life, and, if you want, to coach others.',
            'demo_title' => 'what you will do in month 2', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Eating', 'I want to lose weight. Do I have to stop eating matooke and posho?', "No. It's about portions and what you eat with them.\nHalf the plate vegetables, a palm of beans or meat, a fist of matooke or posho.\nIn month 2 you plan a week of Ugandan meals that fit your goal."],
                ['Training', 'I have 20 minutes a day and no gym. What can I do?', "A lot. Squats, push-ups, lunges and planks at home, 4 days a week.\nAdd a little each week: one more round or a few more reps.\nYou get a full home plan in month 1."],
                ['Habits', 'I start every January and stop by February.', "Start smaller than you think: 3 workouts a week, not 7.\nTrack them where you will see it every day.\nMonth 4 is about habits that last past the first month."],
            ],
            'fit_h2' => ['Made for people who want', 'results that last.'],
            'fit_p' => 'Whatever your starting point, you get a plan that fits your body, your budget and your day.',
            'fit' => [
                ['You want to lose weight or get stronger', 'A clear plan for training and eating, adjusted as you progress.'],
                ['You are busy and can\'t live at a gym', 'Short home workouts and simple meals that fit a full day.'],
                ['You want to coach others', 'Learn to guide clients safely and start a coaching service.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'Practical from day one: you train and eat the plan while you learn why it works.',
            'skills' => [
                ['heart', 'Months 1–2', 'Training at home', 'Strength and fitness with your body weight and simple tools.'],
                ['leaf', 'Months 2–3', 'Eating well, locally', 'Balanced meals from Ugandan food, on any budget.'],
                ['chart', 'Month 4', 'Tracking progress', 'Measure what matters and adjust your plan.'],
                ['spark', 'Months 5–6', 'Habits and recovery', 'Sleep, rest and routines that stick.'],
                ['users', 'Months 7–9', 'Coaching others', 'Assess, plan and guide clients safely.'],
                ['bag', 'Months 10–12', 'A fitness service', 'Start group classes or online coaching.'],
            ],
            'skills_note' => 'Check with a doctor before starting a new programme if you have a health condition. Results depend on your consistency.',
            'year' => [['Where you start', 'Assess your fitness and goals.'], ['Eating well, locally', 'Ugandan meals for your goal.'], ['Home training', 'Strength with your body weight.'], ['Habits that last', 'Routines that stick.'], ['Cardio and endurance', 'Walking, running and circuits.'], ['Recovery', 'Sleep, rest and injuries.'], ['Body composition', 'Fat loss and muscle gain.'], ['Coaching basics', 'Guide someone else safely.'], ['Programme design', 'Plans for different people.'], ['Group classes', 'Run a class with confidence.'], ['Online coaching', 'Coach clients by phone.'], ['Your next year', 'Keep going on your own.']],
            'faq' => [['Do I need a gym?', 'No. Every programme has a home version using your body weight and simple items. A gym is optional.']],
        ],
        'e-commerce' => [
            'headline' => ['Build an online store', 'that delivers.'],
            'lede' => 'People across Uganda are buying online every day. In this campus your professor teaches you, live every week, to find products that sell, set up a store and social shop, get orders, and deliver them reliably.',
            'demo_title' => 'what you will do in month 2', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
            'tasks' => [
                ['Products', 'How do I know if a product will sell before I buy stock?', "Test demand first: post it on WhatsApp Status and TikTok with a price.\nCount the people who ask how to buy, not the likes.\nIn month 2 you test three products before spending on stock."],
                ['Pricing', 'I buy phone covers at UGX 4,000. What should I sell at?', "Add delivery, packaging, ads and returns to your cost first.\nIf your full cost is UGX 7,000, UGX 15,000 leaves room for profit.\nWe build your pricing sheet together."],
                ['Delivery', 'Customers complain about late deliveries. What can I do?', "Agree on delivery times with one or two reliable boda riders.\nConfirm every order by phone and send a message when it leaves.\nMonth 6 covers delivery and cash on delivery across Uganda."],
            ],
            'fit_h2' => ['Made for people who want to', 'sell online.'],
            'fit_p' => 'Start small with a phone and a few products. Grow as your orders do.',
            'fit' => [
                ['You want to start selling online', 'From your first product to your first orders, step by step.'],
                ['You have a shop and want online customers', 'Add WhatsApp, social and a website to reach more buyers.'],
                ['You sell online but struggle with delivery and profit', 'Fix pricing, orders and delivery so each sale pays.'],
            ],
            'skills_label' => 'What you\'ll be able to do', 'skills_h2' => ['Six skills,', 'one campus.'],
            'skills_p' => 'You build your own store through the year, with feedback every week.',
            'skills' => [
                ['search', 'Months 1–2', 'Finding products', 'Test demand and find reliable suppliers.'],
                ['bag', 'Month 3', 'Your store', 'A WhatsApp catalogue, social shop and simple website.'],
                ['image', 'Month 4', 'Photos and listings', 'Product photos and descriptions that sell.'],
                ['chart', 'Month 5', 'Marketing and ads', 'Get orders with content and small, tested ads.'],
                ['truck', 'Months 6–7', 'Orders and delivery', 'Confirm, pack and deliver across Uganda.'],
                ['tag', 'Months 8–12', 'Profit and growth', 'Pricing, repeat customers and scaling up.'],
            ],
            'skills_note' => 'Your results depend on your products, your marketing and your service. We teach the skills; we don\'t promise income.',
            'year' => [['How e-commerce works', 'Models that suit Uganda.'], ['Finding products', 'Test demand first.'], ['Suppliers', 'Buy well and reliably.'], ['Your store', 'WhatsApp, social and web.'], ['Photos and listings', 'Make products irresistible.'], ['Delivery and payments', 'Mobile Money and cash on delivery.'], ['Marketing', 'Content that brings orders.'], ['Ads, carefully', 'Small tests, then scale.'], ['Customer service', 'Reviews and repeat buyers.'], ['Profit', 'Know your numbers.'], ['Growing the range', 'More products, same quality.'], ['Your next year', 'A plan to scale.']],
            'faq' => [['Do I need money for stock?', 'You can start without stock by testing demand and selling to order. Your professor shows low-cost ways to begin.']],
        ],
    ];
    $generic = [
        'headline' => [$name . ', taught', 'by someone who does it.'],
        'lede' => $campus['description'] ?: ($campus['tagline'] ?: 'A year-long programme with a professor who teaches you live every week.'),
        'demo_title' => 'what you will learn', 'demo_badge' => 'ASK YOUR PROFESSOR', 'demo_who' => 'PRO',
        'tasks' => [['Getting started', 'Where do I start if I am a complete beginner?', "Month one starts from the basics.\nYou follow short lessons, join the live class and practise every week.\nYour professor answers your questions as you go."]],
        'fit_h2' => ['Made for people who want', 'real skills.'], 'fit_p' => 'Start from where you are, with a phone and a few hours a week.',
        'fit' => [['You are starting from zero', 'Month one covers the basics.'], ['You want structure', 'A clear path with a professor.'], ['You want to apply it', 'Practise on real work every week.']],
        'skills_label' => 'What you\'ll learn', 'skills_h2' => ['Twelve months,', 'step by step.'], 'skills_p' => '', 'skills' => [], 'skills_note' => '',
        'year' => [], 'faq' => [],
    ];
    $c = $pages[$campus['slug']] ?? $generic;
    // A description written in the admin replaces the built-in opening line.
    if (!empty($campus['description'])) $c['lede'] = $campus['description'];
    return $c + $generic;
}

/** Small line icons used on the campus page, by name. */
function campus_page_icon(string $name): string {
    $p = [
        'pen' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'video' => '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'spark' => '<path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.2 16.2l2.9 2.9M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.2 7.8l2.9-2.9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'bank' => '<path d="M3 21h18M5 21V10M19 21V10M9 21v-7M15 21v-7M12 3 2 8h20z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'scale' => '<path d="M12 3v18M5 21h14M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0zM4 7h16"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z"/>',
        'leaf' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10z"/><path d="M2 21c0-3 1.9-5.4 5.1-6"/>',
        'bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'truck' => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ][$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/**
 * The short opening for the campus page: three headline lines (the last in italic lime) and one
 * sentence. Kept deliberately short so the page stays clean and the join card stays the focus.
 */
function campus_page_intro(array $campus): array {
    $intros = [
        'ai-technology' => [['The AI skills', 'businesses', 'pay for.'], 'Twelve months with a professor who teaches you live every week, from your first prompt to your first client.'],
        'business-finance' => [['Run a business', 'that makes', 'real profit.'], 'Twelve months with a professor who teaches you live every week to price right, track every shilling and grow.'],
        'crypto-trading' => [['Understand crypto', 'before you', 'risk a shilling.'], 'Twelve months with a professor who teaches you live every week to read the market, manage risk and trade with a plan.'],
        'forex-trading' => [['Learn forex', 'the disciplined', 'way.'], 'Twelve months with a professor who teaches you live every week to read charts, control risk and test a strategy.'],
        'content-creation' => [['Grow an audience', 'that pays', 'you back.'], 'Twelve months with a professor who teaches you live every week to make content people watch, and earn from it.'],
        'fitness-nutrition' => [['Get fit with', 'food you', 'already eat.'], 'Twelve months with a professor who teaches you live every week to train, eat and recover well, the Ugandan way.'],
        'e-commerce' => [['Build an online', 'store that', 'delivers.'], 'Twelve months with a professor who teaches you live every week to find products, get orders and deliver across Uganda.'],
    ];
    return $intros[$campus['slug']] ?? [[$campus['name'], 'taught by someone', 'who does it.'], $campus['tagline'] ?: 'Twelve months with a professor who teaches you live every week.'];
}

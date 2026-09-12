<?php
/**
 * A best-effort profanity/abuse filter for user-submitted comments — plain
 * word-list matching, not a sentiment classifier. Genuine negative-but-civil
 * feedback ("this course was disappointing", "the audio was hard to hear")
 * is never touched; only clearly profane or abusive language is blocked.
 * Word-boundary matching keeps it from firing on words that merely contain
 * a blocked word as a substring (e.g. "class", "scrap").
 */
const BLOCKED_WORDS = [
    'fuck', 'fucking', 'fucker', 'motherfucker',
    'shit', 'bullshit', 'bitch', 'asshole', 'assh0le', 'bastard',
    'dick', 'piss', 'pissed', 'cunt', 'douche', 'douchebag',
    'twat', 'wanker', 'slut', 'whore', 'skank',
    'nigger', 'nigga', 'faggot', 'fag', 'retard', 'retarded',
    'chink', 'spic', 'kike', 'tranny',
];

/** @return ?string the specific blocked word found, or null if the text is clean. */
function comment_contains_blocked_language(string $text): ?string {
    foreach (BLOCKED_WORDS as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $text)) {
            return $word;
        }
    }
    return null;
}

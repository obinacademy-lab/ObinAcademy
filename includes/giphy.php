<?php

const GIPHY_SEARCH_URL = 'https://api.giphy.com/v1/gifs/search';
const GIPHY_TRENDING_URL = 'https://api.giphy.com/v1/gifs/trending';

/**
 * The only two hosts a stored/rendered gif_url is ever allowed to point at.
 * Enforced both when a search result is accepted into add_comment() and
 * again by the template right before it's dropped into an <img src>.
 */
const GIPHY_ALLOWED_HOSTS = ['media.giphy.com', 'media0.giphy.com', 'media1.giphy.com', 'media2.giphy.com', 'media3.giphy.com', 'media4.giphy.com'];

function gif_url_is_trusted(?string $url): bool {
    if (!$url) return false;
    $parts = parse_url($url);
    return ($parts['scheme'] ?? '') === 'https' && in_array($parts['host'] ?? '', GIPHY_ALLOWED_HOSTS, true);
}

class GiphyException extends Exception {}

/**
 * @return array<int, array{id: string, url: string, previewUrl: string, width: int, height: int}>
 */
function giphy_fetch(string $url, array $query): array {
    if (!GIPHY_API_KEY) {
        throw new GiphyException('Giphy is not configured — missing GIPHY_API_KEY.');
    }

    $query['api_key'] = GIPHY_API_KEY;
    $query['limit'] = 24;
    // G-rated only — this is a course-discussion feature on an education
    // platform, not a general chat app.
    $query['rating'] = 'g';

    $ch = curl_init($url . '?' . http_build_query($query));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new GiphyException("Giphy request failed: $error");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        throw new GiphyException("Giphy request failed ($status): $responseBody");
    }
    $data = json_decode($responseBody, true);
    if (!is_array($data['data'] ?? null)) {
        throw new GiphyException("Giphy response missing data: $responseBody");
    }

    $gifs = [];
    foreach ($data['data'] as $gif) {
        // "fixed_width_small" for the picker grid thumbnail, "fixed_width"
        // for the larger version actually attached to the comment — both
        // already served from the media.giphy.com CDN gif_url_is_trusted()
        // checks against.
        $full = $gif['images']['fixed_width']['url'] ?? null;
        $preview = $gif['images']['fixed_width_small']['url'] ?? $full;
        if (!$full || !gif_url_is_trusted($full)) continue;
        $gifs[] = [
            'id' => (string) $gif['id'],
            'url' => $full,
            'previewUrl' => $preview,
            'width' => (int) ($gif['images']['fixed_width']['width'] ?? 0),
            'height' => (int) ($gif['images']['fixed_width']['height'] ?? 0),
        ];
    }
    return $gifs;
}

function giphy_search(string $query): array {
    return giphy_fetch(GIPHY_SEARCH_URL, ['q' => $query]);
}

function giphy_trending(): array {
    return giphy_fetch(GIPHY_TRENDING_URL, []);
}

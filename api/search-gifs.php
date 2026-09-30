<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/giphy.php';

api_require_login();

$q = trim((string) ($_GET['q'] ?? ''));

try {
    $gifs = $q !== '' ? giphy_search($q) : giphy_trending();
    json_response(['gifs' => $gifs]);
} catch (GiphyException $e) {
    json_response(['error' => 'GIFs aren\'t available right now — please try again shortly.'], 502);
}

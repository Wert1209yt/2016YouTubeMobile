<?php
header('Content-Type: application/json; charset=utf-8');
$query = trim(@file_get_contents('http://localhost:3000/searchquery'));
if ($query === '') {
    echo json_encode(['status' => 'error', 'content' => [], 'signed_in_username' => '', 'build_id' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$key = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
$clientVersion = '2.20251010.01.00';
$url = 'https://www.youtube.com/youtubei/v1/search?key=' . $key;
$post = ['context' => ['client' => ['hl' => 'ru', 'gl' => 'RU', 'clientName' => 'WEB', 'clientVersion' => $clientVersion]], 'query' => $query];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
$response = curl_exec($ch);
curl_close($ch);
$newData = json_decode($response, true);
if (!is_array($newData)) {
    echo json_encode(['status' => 'error', 'content' => [], 'signed_in_username' => '', 'build_id' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function get_first_text($node) {
    if (is_string($node)) return $node;
    if (!is_array($node)) return '';
    if (isset($node['simpleText']) && is_string($node['simpleText'])) return $node['simpleText'];
    if (isset($node['runs']) && is_array($node['runs']) && isset($node['runs'][0]['text'])) return $node['runs'][0]['text'];
    return '';
}
function make_formatted_string($node) {
    $text = get_first_text($node);
    return ['runs' => [['text' => $text]], 'item_type' => 'formatted_string'];
}
function make_runs_preserve($node) {
    if (is_array($node) && isset($node['runs']) && is_array($node['runs'])) {
        return ['runs' => $node['runs'], 'item_type' => 'formatted_string'];
    }
    return make_formatted_string($node);
}
function length_accessibility_label($time) {
    if (!is_string($time) || $time === '') return '';
    $parts = explode(':', $time);
    $parts = array_map('intval', $parts);
    if (count($parts) === 3) {
        $h = $parts[0]; $m = $parts[1]; $s = $parts[2];
    } elseif (count($parts) === 2) {
        $h = 0; $m = $parts[0]; $s = $parts[1];
    } else {
        $h = 0; $m = 0; $s = $parts[0];
    }
    $pieces = [];
    if ($h > 0) $pieces[] = $h . ' hours';
    if ($m > 0) $pieces[] = $m . ' minutes';
    if ($s > 0) $pieces[] = $s . ' seconds';
    return $pieces ? implode(', ', $pieces) : '';
}
function convert_video_renderer($v) {
    $id = $v['videoId'] ?? ($v['encrypted_id'] ?? '');
    if ($id === '') return null;
    $title = isset($v['title']) ? make_runs_preserve($v['title']) : make_formatted_string($v['title'] ?? '');
    $owner = [];
    if (isset($v['ownerText'])) $owner = make_runs_preserve($v['ownerText']);
    elseif (isset($v['shortBylineText'])) $owner = make_runs_preserve($v['shortBylineText']);
    $view = '';
    if (isset($v['viewCountText']['simpleText'])) $view = $v['viewCountText']['simpleText'];
    elseif (isset($v['viewCountText']['runs'][0]['text'])) $view = $v['viewCountText']['runs'][0]['text'];
    elseif (isset($v['viewCountText'])) $view = get_first_text($v['viewCountText']);
    $length = '';
    if (isset($v['lengthText']['simpleText'])) $length = $v['lengthText']['simpleText'];
    elseif (isset($v['lengthText']['runs'][0]['text'])) $length = $v['lengthText']['runs'][0]['text'];
    elseif (isset($v['lengthText'])) $length = get_first_text($v['lengthText']);
    $thumbUrl = '';
    $thumbWidth = null;
    $thumbHeight = null;
    if (isset($v['thumbnail']['thumbnails']) && is_array($v['thumbnail']['thumbnails']) && isset($v['thumbnail']['thumbnails'][0]['url'])) {
        $thumbUrl = $v['thumbnail']['thumbnails'][0]['url'];
        $thumbWidth = $v['thumbnail']['thumbnails'][0]['width'] ?? null;
        $thumbHeight = $v['thumbnail']['thumbnails'][0]['height'] ?? null;
    } elseif (isset($v['thumbnailInfo']['url'])) {
        $thumbUrl = $v['thumbnailInfo']['url'];
    }
    $length_label = length_accessibility_label($length);
    $length_obj = ['runs' => [['text' => $length]], 'item_type' => 'formatted_string'];
    if ($length_label !== '') $length_obj['accessibility'] = ['label' => $length_label];
    $view_obj = ['runs' => [['text' => $view]], 'item_type' => 'formatted_string'];
    $thumbnail_info = ['url' => $thumbUrl];
    if ($thumbWidth !== null) $thumbnail_info['width'] = $thumbWidth;
    if ($thumbHeight !== null) $thumbnail_info['height'] = $thumbHeight;
    return [
        'item_type' => 'compact_video',
        'encrypted_id' => $id,
        'title' => $title,
        'short_byline' => $owner,
        'view_count' => $view_obj,
        'length' => $length_obj,
        'thumbnail_info' => $thumbnail_info
    ];
}
function convert_channel_renderer($c) {
    $id = $c['channelId'] ?? ($c['encrypted_id'] ?? '');
    if ($id === '') return null;
    $title = isset($c['title']) ? make_runs_preserve($c['title']) : make_formatted_string($c['title'] ?? $c['ownerText'] ?? '');
    $subscriber = '';
    if (isset($c['subscriberCountText'])) $subscriber = get_first_text($c['subscriberCountText']);
    return [
        'item_type' => 'compact_channel',
        'encrypted_id' => $id,
        'title' => $title,
        'subscriber_count' => ['runs' => [['text' => $subscriber]], 'item_type' => 'formatted_string']
    ];
}
function convert_playlist_renderer($p) {
    $id = $p['playlistId'] ?? '';
    if ($id === '') return null;
    $title = isset($p['title']) ? make_runs_preserve($p['title']) : make_formatted_string($p['title'] ?? '');
    $owner = '';
    if (isset($p['shortBylineText'])) $owner = get_first_text($p['shortBylineText']);
    return [
        'item_type' => 'compact_playlist',
        'encrypted_id' => $id,
        'title' => $title,
        'short_byline' => ['runs' => [['text' => $owner]], 'item_type' => 'formatted_string']
    ];
}
$oldItems = [];
function traverse_and_convert($node, &$out) {
    if (!is_array($node)) return;
    foreach ($node as $k => $v) {
        if ($k === 'videoRenderer' && is_array($v)) {
            $converted = convert_video_renderer($v);
            if ($converted !== null) $out[] = $converted;
            continue;
        }
        if ($k === 'channelRenderer' && is_array($v)) {
            $converted = convert_channel_renderer($v);
            if ($converted !== null) $out[] = $converted;
            continue;
        }
        if ($k === 'playlistRenderer' && is_array($v)) {
            $converted = convert_playlist_renderer($v);
            if ($converted !== null) $out[] = $converted;
            continue;
        }
        if (is_array($v)) traverse_and_convert($v, $out);
    }
}
traverse_and_convert($newData, $oldItems);
$output = ['status' => 'ok', 'content' => $oldItems, 'signed_in_username' => '', 'build_id' => 0];
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
?>

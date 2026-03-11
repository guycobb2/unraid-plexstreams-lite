<?php
include('/usr/local/emhttp/plugins/plexstreams-lite/includes/config.php');
header('Content-Type: application/json');

if (empty($cfg['TOKEN']) || empty($cfg['HOST'])) {
    echo json_encode(['error' => 'not_configured']);
    exit;
}

$url = rtrim($cfg['HOST'], '/') . '/status/sessions?X-Plex-Token=' . $cfg['TOKEN'];

$ctx = stream_context_create([
    'http' => ['timeout' => 10, 'method' => 'GET', 'ignore_errors' => true],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
]);

$xml = @file_get_contents($url, false, $ctx);
if ($xml === false) {
    echo json_encode(['error' => 'connect_failed']);
    exit;
}

$data = @simplexml_load_string($xml);
if ($data === false) {
    echo json_encode(['error' => 'parse_failed']);
    exit;
}

$streams = [];

foreach ($data->Video as $video) {
    $a = $video->attributes();
    $type = (string)$a['type'];

    // Build title
    if ($type === 'episode') {
        $s   = sprintf('%02d', (int)$a['parentIndex']);
        $e   = sprintf('%02d', (int)$a['index']);
        $title = (string)$a['grandparentTitle'] . ' · S' . $s . 'E' . $e . ' · ' . (string)$a['title'];
    } else {
        $year  = (string)$a['year'];
        $title = (string)$a['title'] . ($year ? ' (' . $year . ')' : '');
    }

    // Stream decision
    $decision = 'Direct Play';
    foreach ($video->Media as $media) {
        $mAttrs = $media->attributes();
        // Use selected media, or first if none marked selected
        if ((string)$mAttrs['selected'] !== '1' && count($video->Media) > 1) continue;
        $part = $media->Part;
        $pAttrs = $part->attributes();
        $partDecision = strtolower((string)$pAttrs['decision']);

        if ($partDecision === 'transcode') {
            $vSrc = ''; $vDst = ''; $aSrc = ''; $aDst = '';
            $ts = $video->TranscodeSession;
            if ($ts) {
                $tAttrs = $ts->attributes();
                $vDst = strtoupper((string)$tAttrs['videoCodec']);
                $aDst = strtoupper((string)$tAttrs['audioCodec']);
            }
            foreach ($part->Stream as $stream) {
                $sAttrs = $stream->attributes();
                $sType  = (string)$sAttrs['streamType'];
                $sDec   = strtolower((string)$sAttrs['decision']);
                if ($sType === '1' && $sDec === 'transcode') {
                    $vSrc = strtoupper((string)$sAttrs['codec']);
                }
                if ($sType === '2' && $sDec === 'transcode') {
                    $aSrc = strtoupper((string)$sAttrs['codec']);
                }
            }
            $parts = [];
            if ($vSrc && $vDst) $parts[] = $vSrc . '→' . $vDst;
            if ($aSrc && $aDst) $parts[] = $aSrc . '→' . $aDst;
            $decision = 'Transcode' . ($parts ? ' · ' . implode(' / ', $parts) : '');
        }
        break;
    }

    $duration   = max(1, (int)$a['duration']);
    $viewOffset = (int)$a['viewOffset'];
    $percent    = min(100, round(($viewOffset / $duration) * 100));

    $player  = $video->Player->attributes();
    $user    = $video->User->attributes();
    $session = $video->Session->attributes();

    $bw  = round((int)$session['bandwidth'] / 1000, 1);
    $loc = strtolower((string)$session['location']) === 'lan' ? 'LAN' : 'Remote';

    $streams[] = [
        'title'       => $title,
        'user'        => (string)$user['title'],
        'device'      => (string)$player['product'],
        'decision'    => $decision,
        'bandwidth'   => $bw,
        'location'    => $loc,
        'percent'     => $percent,
        'currentTime' => fmtTime($viewOffset),
        'totalTime'   => fmtTime($duration),
        'state'       => (string)$player['state'],
        'type'        => 'video',
    ];
}

foreach ($data->Track as $track) {
    $a = $track->attributes();
    $title = (string)$a['grandparentTitle'] . ' · ' . (string)$a['title'] . ' · ' . (string)$a['parentTitle'];

    $duration   = max(1, (int)$a['duration']);
    $viewOffset = (int)$a['viewOffset'];
    $percent    = min(100, round(($viewOffset / $duration) * 100));

    $player  = $track->Player->attributes();
    $user    = $track->User->attributes();
    $session = $track->Session->attributes();

    $bw  = round((int)$session['bandwidth'] / 1000, 1);
    $loc = strtolower((string)$session['location']) === 'lan' ? 'LAN' : 'Remote';

    $streams[] = [
        'title'       => $title,
        'user'        => (string)$user['title'],
        'device'      => (string)$player['product'],
        'decision'    => 'Direct Play',
        'bandwidth'   => $bw,
        'location'    => $loc,
        'percent'     => $percent,
        'currentTime' => fmtTime($viewOffset),
        'totalTime'   => fmtTime($duration),
        'state'       => (string)$player['state'],
        'type'        => 'audio',
    ];
}

echo json_encode($streams);

function fmtTime($ms) {
    $s = intval($ms / 1000);
    $h = floor($s / 3600);
    $m = floor(($s % 3600) / 60);
    $s = $s % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

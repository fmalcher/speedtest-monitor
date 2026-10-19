<?php
// List stored speedtest JSON files in chronological order.

declare(strict_types=1);

$DATA_DIR = __DIR__ . '/data';
// $WARNING_THRESHOLD = 10000000; // 80 Mbps
$WARNING_THRESHOLD = 9375000; // 75 Mbps
$DISPLAY_TIMEZONE = new DateTimeZone('Europe/Berlin');

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function human_bandwidth($bytesPerSec): string {
    if ($bytesPerSec === null || $bytesPerSec === '') return '-';
    $bytes = floatval($bytesPerSec);
    // Show in B/s and Mbps
    $units = ['B/s','KB/s','MB/s','GB/s'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) {
        $bytes /= 1024;
        $i++;
    }
    $mbps = floatval($bytesPerSec) * 8 / 1000 / 1000; // Mbps (decimal)
    return sprintf('%.2f Mbps (%.2f %s)', $mbps, $bytes, $units[$i]);
}

function bytes_to_mbps($bytesPerSec): float {
    return floatval($bytesPerSec) * 8 / 1000 / 1000;
}

function format_latency($lat): string {
    if ($lat === null || $lat === '') return '-';
    if (is_numeric($lat)) {
        return sprintf('%.3f ms', floatval($lat));
    }
    if (is_array($lat)) {
        // prefer iqm then low/high
        $parts = [];
        if (isset($lat['iqm'])) $parts[] = sprintf('iqm %.3f ms', floatval($lat['iqm']));
        if (isset($lat['low']) && isset($lat['high'])) $parts[] = sprintf('range %.3f-%.3f ms', floatval($lat['low']), floatval($lat['high']));
        if (isset($lat['jitter'])) $parts[] = sprintf('jitter %.3f ms', floatval($lat['jitter']));
        return implode(', ', $parts) ?: '-';
    }
    return h(json_encode($lat));
}

$files = [];
if (is_dir($DATA_DIR)) {
    foreach (glob($DATA_DIR . '/*.json') as $path) {
        $raw = file_get_contents($path);
        if ($raw === false) continue;
        $data = json_decode($raw, true);
        if ($data === null) continue;

        // Determine timestamp
        $ts = null;
        if (isset($data['timestamp']) && is_string($data['timestamp'])) {
            $dt = DateTime::createFromFormat(DateTime::ATOM, $data['timestamp']);
            if ($dt !== false) $ts = $dt;
            else {
                // try ISO8601 fallback
                try { $dt = new DateTime($data['timestamp']); $ts = $dt; } catch (Exception $e) { $ts = null; }
            }
        }
        if ($ts === null) {
            // fallback to file mtime
            $ts = (new DateTime())->setTimestamp(filemtime($path));
        }

        $files[] = [
            'path' => $path,
            'data' => $data,
            'ts' => $ts,
        ];
    }
}
// Sort by timestamp descending (chronological)
usort($files, function($a, $b) {
    return $b['ts'] <=> $a['ts'];
});

// Calculate summary statistics
$downloads = [];
$uploads = [];
$pings = [];

foreach ($files as $f) {
    $d = $f['data'];
    if (isset($d['download']['bandwidth']) && is_numeric($d['download']['bandwidth'])) {
        $downloads[] = floatval($d['download']['bandwidth']);
    }
    if (isset($d['upload']['bandwidth']) && is_numeric($d['upload']['bandwidth'])) {
        $uploads[] = floatval($d['upload']['bandwidth']);
    }
    $ping = $d['ping']['latency'] ?? ($d['ping'] ?? null);
    if (is_numeric($ping)) {
        $pings[] = floatval($ping);
    }
}

$summary = [
    'count' => count($files),
    'avg_download' => count($downloads) > 0 ? array_sum($downloads) / count($downloads) : null,
    'avg_upload' => count($uploads) > 0 ? array_sum($uploads) / count($uploads) : null,
    'avg_ping' => count($pings) > 0 ? array_sum($pings) / count($pings) : null,
    'min_download' => count($downloads) > 0 ? min($downloads) : null,
    'max_download' => count($downloads) > 0 ? max($downloads) : null,
    'min_upload' => count($uploads) > 0 ? min($uploads) : null,
    'max_upload' => count($uploads) > 0 ? max($uploads) : null,
    'min_ping' => count($pings) > 0 ? min($pings) : null,
    'max_ping' => count($pings) > 0 ? max($pings) : null,
];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Speedtest submissions</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:20px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #ddd;padding:8px;text-align:left}
    th{background:#f7f7f7}
    tr:nth-child(even){background:#fbfbfb}
    .mono{font-family:monospace;font-size:0.95em}
    .highlight{color:red;}
    .summary{background:#f0f7ff;border:1px solid #b8d4f0;border-radius:8px;padding:16px;margin-bottom:24px}
    .summary h2{margin-top:0;margin-bottom:12px}
    .summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
    .summary-item{background:#fff;border-radius:4px;padding:12px;border:1px solid #ddd}
    .summary-item .label{font-size:0.85em;color:#666;margin-bottom:4px}
    .summary-item .value{font-size:1.3em;font-weight:600}
    .summary-item .range{font-size:0.8em;color:#888;margin-top:4px}
    .server-id{color:#888;font-size:0.9em}
  </style>
</head>
<body>
  <h1>Speedtests</h1>

  <div class="summary">
    <h2>Summary</h2>
    <div class="summary-grid">
      <div class="summary-item">
        <div class="label">Total Tests</div>
        <div class="value"><?php echo $summary['count']; ?></div>
      </div>
      <div class="summary-item">
        <div class="label">Average Download</div>
        <div class="value"><?php echo $summary['avg_download'] !== null ? sprintf('%.2f Mbps', bytes_to_mbps($summary['avg_download'])) : '-'; ?></div>
        <?php if ($summary['min_download'] !== null): ?>
        <div class="range">Min: <?php echo sprintf('%.2f', bytes_to_mbps($summary['min_download'])); ?> / Max: <?php echo sprintf('%.2f', bytes_to_mbps($summary['max_download'])); ?> Mbps</div>
        <?php endif; ?>
      </div>
      <div class="summary-item">
        <div class="label">Average Upload</div>
        <div class="value"><?php echo $summary['avg_upload'] !== null ? sprintf('%.2f Mbps', bytes_to_mbps($summary['avg_upload'])) : '-'; ?></div>
        <?php if ($summary['min_upload'] !== null): ?>
        <div class="range">Min: <?php echo sprintf('%.2f', bytes_to_mbps($summary['min_upload'])); ?> / Max: <?php echo sprintf('%.2f', bytes_to_mbps($summary['max_upload'])); ?> Mbps</div>
        <?php endif; ?>
      </div>
      <div class="summary-item">
        <div class="label">Average Ping</div>
        <div class="value"><?php echo $summary['avg_ping'] !== null ? sprintf('%.2f ms', $summary['avg_ping']) : '-'; ?></div>
        <?php if ($summary['min_ping'] !== null): ?>
        <div class="range">Min: <?php echo sprintf('%.2f', $summary['min_ping']); ?> / Max: <?php echo sprintf('%.2f', $summary['max_ping']); ?> ms</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Date / Time</th>
        <th>Ping (ms)</th>
        <th>Download</th>
        <th>Upload</th>
        <th>Speedtest Server</th>
        <th>Server IP</th>
        <th>File</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($files as $f):
        $d = $f['data'];
        $ts = $f['ts'];
        $dtstr = $ts->setTimezone($DISPLAY_TIMEZONE)->format('Y-m-d H:i:s');
        $ping = $d['ping']['latency'] ?? ($d['ping'] ?? '-');
        $download_bw = $d['download']['bandwidth'] ?? null;
        $upload_bw = $d['upload']['bandwidth'] ?? null;
        $server_name = $d['server']['name'] ?? '';
        $server_loc = $d['server']['location'] ?? ($d['server']['city'] ?? '');
        $server_id = $d['server']['id'] ?? null;
        $server_combined = trim(($server_name !== '' ? $server_name : '') . ($server_loc !== '' ? ', ' . $server_loc : ''));
        if ($server_id !== null) {
            $server_combined = ($server_combined !== '' ? $server_combined : '-') . ' <span class="server-id">(' . h($server_id) . ')</span>';
        } elseif ($server_combined === '') {
            $server_combined = '-';
        }
        $server_ip = $d['server']['ip'] ?? '';
        if ($server_ip === '') {
            $server_ip = '-';
        }
        $filelink = basename($f['path']);
    ?>
      <tr>
        <td class="mono"><?php echo h($dtstr); ?></td>
        <td><?php echo is_numeric($ping) ? sprintf('%.3f ms', $ping) : h($ping); ?></td>
        <td<?php echo $download_bw < $WARNING_THRESHOLD ? ' class="highlight"' : '' ?>><?php echo h(human_bandwidth($download_bw)); ?></td>
        <td<?php echo $upload_bw < $WARNING_THRESHOLD ? ' class="highlight"' : '' ?>><?php echo h(human_bandwidth($upload_bw)); ?></td>
        <td><?php echo $server_combined; ?></td>
        <td class="mono"><?php echo h($server_ip); ?></td>
        <td><a href="data/<?php echo rawurlencode($filelink); ?>" target="_blank">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

</body>
</html>

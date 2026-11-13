<?php
// Visszatérő látogatók összesítése (csak admin). Forrás: api/data/returning.json (statisztikai azonosítót elfogadó böngészők).
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Nincs jogosultság'); }
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Budapest');
$days = isset($_GET['days']) ? max(1, min(120, (int) $_GET['days'])) : 30;
$to = date('Y-m-d');
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$raw = read_raw('returning');
$all = $raw !== null ? json_decode($raw, true) : array();
if (!is_array($all)) { $all = array(); }

$series = array();
for ($i = $days - 1; $i >= 0; $i--) { $dd = date('Y-m-d', strtotime('-' . $i . ' days')); $series[$dd] = array('date' => $dd, 'unique' => 0, 'new' => 0, 'returning' => 0); }
$active = 0; $new = 0; $returning = 0; $cameBack = 0; $totalDays = 0; $sessions = 0;
$freq = array('1' => 0, '2' => 0, '3-4' => 0, '5+' => 0);
$gap = array('1' => 0, '2-7' => 0, '8-30' => 0, '31+' => 0);
$everTracked = count($all);
foreach ($all as $e) {
    if (!isset($e['d']) || !is_array($e['d'])) { continue; }
    $in = array();
    foreach ($e['d'] as $x) { if ($x >= $from && $x <= $to) { $in[] = $x; } }
    if (!count($in)) { continue; }
    sort($e['d']);
    $first = isset($e['f']) ? $e['f'] : $e['d'][0];
    $active++;
    $isNew = ($first >= $from);
    if ($isNew) { $new++; } else { $returning++; }
    $n = count($in); $totalDays += $n; $sessions += isset($e['s']) ? (int) $e['s'] : 0;
    if ($isNew && $n >= 2) { $cameBack++; }
    $freq[$n >= 5 ? '5+' : ($n >= 3 ? '3-4' : (string) $n)]++;
    foreach ($in as $x) {
        $series[$x]['unique']++;
        if ($first === $x) { $series[$x]['new']++; } else { $series[$x]['returning']++; }
    }
    // két egymást követő látogatási nap közötti idő (a vizsgált időszakban érkező visszatérésekre)
    $prev = null;
    foreach ($e['d'] as $x) {
        if ($prev !== null && $x >= $from && $x <= $to) {
            $g = (int) round((strtotime($x) - strtotime($prev)) / 86400);
            $gap[$g <= 1 ? '1' : ($g <= 7 ? '2-7' : ($g <= 30 ? '8-30' : '31+'))]++;
        }
        $prev = $x;
    }
}
json_out(array('ok' => true, 'from' => $from, 'to' => $to, 'days' => $days, 'active' => $active, 'new' => $new, 'returning' => $returning, 'cameBack' => $cameBack,
    'avgDays' => $active ? round($totalDays / $active, 2) : 0, 'freq' => $freq, 'gap' => $gap, 'series' => array_values($series), 'tracked' => $everTracked));

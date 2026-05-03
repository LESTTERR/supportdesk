<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

// Stats counts
$open = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
$inProgress = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'in_progress'")->fetchColumn();
$resolvedToday = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()")->fetchColumn();

$responseTime = '—'; // optional, could be calculated from first reply timestamps

// Recent tickets (last 5)
$recent = $pdo->query("SELECT t.id, t.subject, t.priority, t.created_at, u.name as user_name FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

// Activity feed (just ticket creation events)
$activities = $pdo->query("SELECT u.name as user_name, t.id, t.created_at FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
$activityFeed = array_map(function($a) {
    return [
        'actor' => $a['user_name'],
        'description' => "created ticket #{$a['id']}",
        'time_ago' => 'just now',
        'type' => 'opened'
    ];
}, $activities);

echo json_encode([
    'stats' => [
        'open' => (int)$open,
        'in_progress' => (int)$inProgress,
        'resolved_today' => (int)$resolvedToday,
        'avg_response' => $responseTime
    ],
    'recent_tickets' => $recent,
    'activity' => $activityFeed
]);
?>

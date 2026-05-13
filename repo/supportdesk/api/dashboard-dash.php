<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user'];
$staffUser = in_array($currentUser['role'] ?? '', ['agent', 'admin'], true);
$scopeSql = $staffUser ? '' : ' AND user_id = ?';
$scopeParams = $staffUser ? [] : [$currentUser['id']];

$open = countByStatus($pdo, 'open', $scopeSql, $scopeParams);
$inProgress = countByStatus($pdo, 'in_progress', $scopeSql, $scopeParams);
$resolvedToday = countResolvedToday($pdo, $scopeSql, $scopeParams);
$avgResponse = calculateAverageResponse($pdo, $staffUser, (int) $currentUser['id']);
$recentTickets = recentTickets($pdo, $staffUser, (int) $currentUser['id']);
$activity = activityFeed($pdo, $staffUser, (int) $currentUser['id']);

echo json_encode([
    'success' => true,
    'stats' => [
        'open' => $open,
        'in_progress' => $inProgress,
        'resolved_today' => $resolvedToday,
        'avg_response' => $avgResponse,
    ],
    'recent_tickets' => $recentTickets,
    'activity' => $activity,
]);

function countByStatus(PDO $pdo, string $status, string $scopeSql, array $scopeParams): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = ?$scopeSql");
    $stmt->execute(array_merge([$status], $scopeParams));
    return (int) $stmt->fetchColumn();
}

function countResolvedToday(PDO $pdo, string $scopeSql, array $scopeParams): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()$scopeSql");
    $stmt->execute($scopeParams);
    return (int) $stmt->fetchColumn();
}

function calculateAverageResponse(PDO $pdo, bool $staffUser, int $currentUserId): string
{
    $scopeSql = $staffUser ? '' : ' WHERE t.user_id = ?';
    $params = $staffUser ? [] : [$currentUserId];

    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, first_reply.first_reply_at)) AS avg_minutes
        FROM tickets t
        JOIN (
            SELECT tr.ticket_id, MIN(tr.created_at) AS first_reply_at
            FROM ticket_replies tr
            JOIN users u ON tr.user_id = u.id
            WHERE u.role IN ('agent', 'admin')
            GROUP BY tr.ticket_id
        ) first_reply ON first_reply.ticket_id = t.id
        $scopeSql
    ");
    $stmt->execute($params);
    $minutes = $stmt->fetchColumn();

    if ($minutes === null) {
        return 'No replies yet';
    }

    return formatMinutes((int) round((float) $minutes));
}

function recentTickets(PDO $pdo, bool $staffUser, int $currentUserId): array
{
    $scopeSql = $staffUser ? '' : ' WHERE t.user_id = ?';
    $params = $staffUser ? [] : [$currentUserId];

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.subject,
            t.priority,
            t.status,
            t.created_at,
            requester.name AS user_name,
            assignee.name AS assignee_name
        FROM tickets t
        JOIN users requester ON t.user_id = requester.id
        LEFT JOIN users assignee ON t.assignee_id = assignee.id
        $scopeSql
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function activityFeed(PDO $pdo, bool $staffUser, int $currentUserId): array
{
    $ticketScope = $staffUser ? '' : ' AND t.user_id = ?';
    $replyScope = $staffUser ? '' : ' AND t.user_id = ?';
    $params = $staffUser ? [] : [$currentUserId, $currentUserId];

    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT
                requester.name AS actor,
                CONCAT('created ticket #', t.id) AS description,
                t.created_at AS event_time,
                'opened' AS type
            FROM tickets t
            JOIN users requester ON t.user_id = requester.id
            WHERE 1 = 1 $ticketScope

            UNION ALL

            SELECT
                replier.name AS actor,
                CONCAT('replied to ticket #', tr.ticket_id) AS description,
                tr.created_at AS event_time,
                'reply' AS type
            FROM ticket_replies tr
            JOIN tickets t ON tr.ticket_id = t.id
            JOIN users replier ON tr.user_id = replier.id
            WHERE 1 = 1 $replyScope
        ) events
        ORDER BY event_time DESC
        LIMIT 5
    ");
    $stmt->execute($params);

    return array_map(function (array $item): array {
        return [
            'actor' => $item['actor'],
            'description' => $item['description'],
            'time_ago' => timeAgo($item['event_time']),
            'type' => $item['type'],
        ];
    }, $stmt->fetchAll());
}

function formatMinutes(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . 'm';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours < 24) {
        return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
    }

    $days = intdiv($hours, 24);
    $remainingHours = $hours % 24;
    return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
}

function timeAgo(string $timestamp): string
{
    $seconds = max(0, time() - strtotime($timestamp));

    if ($seconds < 60) {
        return 'just now';
    }

    $units = [
        'year' => 31536000,
        'month' => 2592000,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
    ];

    foreach ($units as $label => $unitSeconds) {
        $value = intdiv($seconds, $unitSeconds);
        if ($value > 0) {
            return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
        }
    }

    return 'just now';
}
?>

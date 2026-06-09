<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
$admin  = require_admin_auth();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_admin_csrf();
}

try {
    /* ════════════════════════════════════════════════════
       USERS
       ════════════════════════════════════════════════════ */
    if ($action === 'users') {
        $sort   = $_GET['sort']   ?? 'date_desc';
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(10000, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Auto-create profiles for users that don't have one
        try {
            db()->exec('INSERT IGNORE INTO user_profiles (user_id) SELECT id FROM users');
        } catch (\Throwable $e) {}

        // Build ORDER BY
        $order = match ($sort) {
            'alpha_asc'   => 'u.username ASC',
            'alpha_desc'  => 'u.username DESC',
            'date_asc'    => 'u.created_at ASC',
            'date_desc'   => 'u.created_at DESC',
            'online_first' => 'is_online DESC, u.created_at DESC',
            'offline_first' => 'is_online ASC, u.created_at DESC',
            default        => 'u.created_at DESC',
        };

        $where  = $search ? 'AND (u.username LIKE ? OR u.email LIKE ?)' : '';
        $params = $search ? ["%$search%", "%$search%"] : [];

        // Count
        $countSql = "SELECT COUNT(*) FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE 1=1 $where";
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch with online status sub-select
        $sql = "SELECT
                  u.id, u.username AS fullname, u.email, u.created_at,
                  COALESCE(u.gender, 'Undefined') AS gender,
                  COALESCE(u.birthday, NULL) AS birthdate,
                  COALESCE(up.is_suspended, 0) AS is_suspended,
                  IF(us.last_activity IS NOT NULL AND us.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0) AS is_online
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN (
                    SELECT user_id, MAX(last_activity) AS last_activity
                    FROM user_sessions GROUP BY user_id
                ) us ON us.user_id = u.id
                WHERE 1=1 $where
                ORDER BY $order
                LIMIT $limit OFFSET $offset";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        echo json_encode([
            'ok'    => true,
            'users' => $users,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) throw new \RuntimeException('Invalid user ID.');

        $check = db()->prepare('SELECT id, username AS fullname, email FROM users WHERE id = ?');
        $check->execute([$id]);
        $u = $check->fetch();
        if (!$u) throw new \RuntimeException('User not found.');

        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        log_admin_action((int) $admin['id'], 'DELETE_USER', 'user', $id,
            "Deleted user: {$u['fullname']} ({$u['email']})");

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'toggle_suspend') {
        $id = (int) ($_POST['id'] ?? 0);
        $db = db();
        $db->prepare('INSERT IGNORE INTO user_profiles (user_id) VALUES (?)')->execute([$id]);
        $stmt = $db->prepare('SELECT is_suspended FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$id]);
        $current = (int) ($stmt->fetchColumn() ?? 0);
        $new     = $current ? 0 : 1;
        $db->prepare('UPDATE user_profiles SET is_suspended = ? WHERE user_id = ?')->execute([$new, $id]);
        log_admin_action((int) $admin['id'], $new ? 'SUSPEND_USER' : 'ACTIVATE_USER', 'user', $id);
        echo json_encode(['ok' => true, 'is_suspended' => $new]);
        exit;
    }

    /* ════════════════════════════════════════════════════
       ANALYTICS — OLTP (Real-time)
       ════════════════════════════════════════════════════ */
    if ($action === 'analytics_oltp') {
        ensure_feedback_schema();

        $totalUsers     = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $onlineUsers    = (int) db()->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        $newToday       = (int) db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $totalFeedback  = (int) db()->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
        $avgRating      = (float) (db()->query('SELECT COALESCE(AVG(rating),0) FROM feedback WHERE rating IS NOT NULL')->fetchColumn());
        $totalSessions  = (int) db()->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE DATE(last_activity) = CURDATE()")->fetchColumn();
        $totalLessons   = (int) db()->query('SELECT COUNT(*) FROM history')->fetchColumn();
        $totalQuizzes   = (int) db()->query('SELECT COUNT(*) FROM quiz_scores')->fetchColumn();
        $avgQuizScore   = (float) (db()->query('SELECT COALESCE(AVG(percentage),0) FROM quiz_scores')->fetchColumn());
        $aiRequests     = (int) db()->query('SELECT SUM(hourly_count) FROM usage_limits')->fetchColumn();
        $cachedTopics   = (int) db()->query('SELECT COUNT(*) FROM ai_cache')->fetchColumn();

        echo json_encode([
            'ok'           => true,
            'total_users'  => $totalUsers,
            'online_users' => $onlineUsers,
            'new_today'    => $newToday,
            'active_today' => $totalSessions,
            'total_feedback' => $totalFeedback,
            'avg_rating'   => round($avgRating, 1),
            'total_lessons' => $totalLessons,
            'total_quizzes' => $totalQuizzes,
            'avg_quiz_score' => round($avgQuizScore, 1),
            'ai_requests'  => $aiRequests,
            'cached_topics' => $cachedTopics,
        ]);
        exit;
    }

    /* ── Analytics — Charts ──────────────────────────────────── */
    if ($action === 'analytics_charts') {
        ensure_feedback_schema();

        // Ensure profiles exist
        try { db()->exec('INSERT IGNORE INTO user_profiles (user_id) SELECT id FROM users'); } catch (\Throwable $e) {}

        // Gender distribution
        $gStmt = db()->query("SELECT
                                  CASE
                                    WHEN LOWER(COALESCE(NULLIF(u.gender, ''), up.gender, '')) = 'male' THEN 'Male'
                                    WHEN LOWER(COALESCE(NULLIF(u.gender, ''), up.gender, '')) = 'female' THEN 'Female'
                                    ELSE 'Undefined'
                                  END AS gender,
                                  COUNT(*) AS cnt
                               FROM users u
                               LEFT JOIN user_profiles up ON up.user_id = u.id
                               GROUP BY gender");
        $genderRaw = $gStmt->fetchAll();
        $gender = ['Male' => 0, 'Female' => 0, 'Undefined' => 0];
        foreach ($genderRaw as $r) { $gender[$r['gender']] = (int) $r['cnt']; }
        $totalGender = array_sum($gender);

        // Age distribution (current year minus user's birth year)
        $ageData = ['18_below' => 0, '19_above' => 0, 'unknown' => 0];
        $ageStmt = db()->query("SELECT COALESCE(u.birthday, up.birthdate) AS birthdate
                                  FROM users u
                                  LEFT JOIN user_profiles up ON up.user_id = u.id
                                  WHERE COALESCE(u.birthday, up.birthdate) IS NOT NULL");
        $currentYear = (int) date('Y');
        foreach ($ageStmt->fetchAll() as $row) {
            $birthYear = (int) date('Y', strtotime($row['birthdate']));
            $age = $birthYear > 0 ? $currentYear - $birthYear : -1;
            if ($age < 0) { $ageData['unknown']++; continue; }
            if ($age <= 18) $ageData['18_below']++;
            else            $ageData['19_above']++;
        }
        // Count users with no birthdate
        $noBD = db()->query("SELECT COUNT(*)
                               FROM users u
                               LEFT JOIN user_profiles up ON up.user_id = u.id
                               WHERE COALESCE(u.birthday, up.birthdate) IS NULL")->fetchColumn();
        $ageData['unknown'] += (int) $noBD;

        // User growth (last 7 days)
        $growthStmt = db()->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                                    FROM users
                                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    GROUP BY day ORDER BY day");
        $growthRaw = $growthStmt->fetchAll();
        $growth7 = []; $g7labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $g7labels[] = date('M d', strtotime($d));
            $growth7[]  = 0;
        }
        foreach ($growthRaw as $r) {
            $idx = array_search(date('M d', strtotime($r['day'])), $g7labels);
            if ($idx !== false) $growth7[$idx] = (int) $r['cnt'];
        }

        // Monthly growth (last 6 months)
        $monthStmt = db()->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS mo, COUNT(*) AS cnt
                                   FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                   GROUP BY mo ORDER BY mo");
        $monthRaw = $monthStmt->fetchAll();
        $months = []; $monthCounts = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[]      = date('M Y', strtotime("-{$i} months"));
            $monthCounts[] = 0;
        }
        foreach ($monthRaw as $r) {
            $label = date('M Y', strtotime($r['mo'] . '-01'));
            $idx   = array_search($label, $months);
            if ($idx !== false) $monthCounts[$idx] = (int) $r['cnt'];
        }

        // Active hours (last 24h)
        $hourStmt = db()->query("SELECT HOUR(last_activity) AS hr, COUNT(*) AS cnt
                                  FROM user_sessions
                                  WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                  GROUP BY hr ORDER BY hr");
        $hours = array_fill(0, 24, 0);
        $hLabels = array_map(fn($h) => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00', range(0, 23));
        foreach ($hourStmt->fetchAll() as $r) { $hours[(int)$r['hr']] = (int)$r['cnt']; }

        // Quiz score distribution
        $scoreStmt = db()->query("SELECT
            SUM(CASE WHEN percentage >= 90 THEN 1 ELSE 0 END) AS excellent,
            SUM(CASE WHEN percentage >= 70 AND percentage < 90 THEN 1 ELSE 0 END) AS good,
            SUM(CASE WHEN percentage >= 31 AND percentage < 70 THEN 1 ELSE 0 END) AS fair,
            SUM(CASE WHEN percentage < 31 THEN 1 ELSE 0 END) AS poor
            FROM quiz_scores");
        $scores = $scoreStmt->fetch() ?? ['excellent'=>0,'good'=>0,'fair'=>0,'poor'=>0];

        // Rating distribution
        $ratingStmt = db()->query("SELECT rating, COUNT(*) AS cnt FROM feedback WHERE rating IS NOT NULL GROUP BY rating ORDER BY rating");
        $ratings = array_fill(1, 5, 0);
        foreach ($ratingStmt->fetchAll() as $r) { $ratings[(int)$r['rating']] = (int)$r['cnt']; }

        echo json_encode([
            'ok'           => true,
            'gender'       => $gender,
            'gender_total' => $totalGender,
            'age'          => $ageData,
            'growth_7'     => ['labels' => $g7labels, 'data' => $growth7],
            'growth_month' => ['labels' => $months,   'data' => $monthCounts],
            'active_hours' => ['labels' => $hLabels,  'data' => $hours],
            'quiz_scores'  => $scores,
            'ratings'      => array_values($ratings),
        ]);
        exit;
    }

    /* ── ETL stats ───────────────────────────────────────────── */
    if ($action === 'etl_stats') {
        $stmt = db()->query("SELECT * FROM etl_logs ORDER BY started_at DESC LIMIT 10");
        $logs = $stmt->fetchAll();

        $summary = db()->query("SELECT
            COUNT(*) AS total_runs,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='failed'    THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status='warning'   THEN 1 ELSE 0 END) AS warnings,
            SUM(records_extracted)  AS total_extracted,
            SUM(records_loaded)     AS total_loaded,
            SUM(records_failed)     AS total_failed,
            COALESCE(AVG(CASE WHEN status='completed' THEN duration_seconds END),0) AS avg_duration,
            COALESCE(SUM(data_volume_kb),0) AS total_volume
            FROM etl_logs")->fetch();

        echo json_encode(['ok' => true, 'logs' => $logs, 'summary' => $summary]);
        exit;
    }

    /* ════════════════════════════════════════════════════
       FEEDBACK
       ════════════════════════════════════════════════════ */
    if ($action === 'feedback') {
        ensure_feedback_schema();

        $ratingFilter = $_GET['rating'] ?? '';
        $search       = trim($_GET['search'] ?? '');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $limit        = min(10000, max(1, (int)($_GET['limit'] ?? 20)));
        $offset       = ($page - 1) * $limit;

        $where   = 'WHERE f.is_archived = 0';
        $params  = [];
        if ($ratingFilter !== '' && $ratingFilter !== 'all') {
            if ($ratingFilter === 'none') { $where .= ' AND f.rating IS NULL'; }
            else { $where .= ' AND f.rating = ?'; $params[] = (int)$ratingFilter; }
        }
        if ($search) { $where .= ' AND f.message LIKE ?'; $params[] = "%$search%"; }

        $countStmt = db()->prepare("SELECT COUNT(*) FROM feedback f $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = db()->prepare("SELECT f.id,
                                      CASE WHEN TRIM(f.message) = '' THEN CONCAT('the user rate ', f.rating, ' stars') ELSE f.message END AS message,
                                      f.rating, f.sentiment, f.is_reviewed,
                                      f.created_at, u.username AS fullname
                               FROM feedback f
                               LEFT JOIN users u ON u.id = f.user_id
                               $where
                               ORDER BY f.created_at DESC
                               LIMIT $limit OFFSET $offset");
        $stmt->execute($params);

        // Rating stats
        $stats = db()->query("SELECT
            COUNT(*) AS total,
            COALESCE(AVG(rating),0) AS avg_rating,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS positive,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS negative,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS neutral_count,
            SUM(CASE WHEN rating IS NULL THEN 1 ELSE 0 END) AS no_rating
            FROM feedback WHERE is_archived = 0")->fetch();

        echo json_encode([
            'ok'       => true,
            'items'    => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int)ceil($total / $limit),
            'stats'    => $stats,
        ]);
        exit;
    }

    if ($action === 'feedback_review') {
        ensure_feedback_schema();

        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE feedback SET is_reviewed = 1 WHERE id = ?')->execute([$id]);
        log_admin_action((int)$admin['id'], 'REVIEW_FEEDBACK', 'feedback', $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'feedback_archive') {
        ensure_feedback_schema();

        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE feedback SET is_archived = 1 WHERE id = ?')->execute([$id]);
        log_admin_action((int)$admin['id'], 'ARCHIVE_FEEDBACK', 'feedback', $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new \RuntimeException('Unknown action.');

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

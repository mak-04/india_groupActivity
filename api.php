<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');
$user   = require_login();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

try {
    /* ── Stats ───────────────────────────────────────────── */
    if ($action === 'stats') {
        echo json_encode(['ok' => true, 'stats' => dashboard_stats((int) $user['id'])]);
        exit;
    }

    /* ── History list (excludes pinned items) ────────────── */
    if ($action === 'history_list') {
        // FIX: pinned items are excluded so they only appear in the Pinned section
        $stmt = db()->prepare(
            'SELECT h.id, h.title, h.created_at
             FROM history h
             LEFT JOIN pinned p ON p.history_id = h.id AND p.user_id = h.user_id
             WHERE h.user_id = ? AND p.id IS NULL
             ORDER BY h.created_at DESC LIMIT 30'
        );
        $stmt->execute([$user['id']]);
        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'history_open') {
        $stmt = db()->prepare('SELECT id, title, content, created_at, generation_status FROM history WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_GET['id'] ?? 0), $user['id']]);
        echo json_encode(['ok' => true, 'item' => $stmt->fetch()]);
        exit;
    }

    /* ── history_delete now soft-deletes to Trash ────────── */
    if ($action === 'history_delete') {
        $id    = (int) ($_POST['id'] ?? 0);
        $check = db()->prepare('SELECT * FROM history WHERE id = ? AND user_id = ?');
        $check->execute([$id, $user['id']]);
        $item  = $check->fetch();
        if (!$item) throw new RuntimeException('Session not found.');

        db()->beginTransaction();
        try {
            // Move to trash
            db()->prepare('INSERT INTO trash (user_id, title, content) VALUES (?, ?, ?)')
               ->execute([$user['id'], $item['title'], $item['content']]);
            // Remove from pinned if pinned
            db()->prepare('DELETE FROM pinned WHERE user_id = ? AND history_id = ?')
               ->execute([$user['id'], $id]);
            // Remove from history
            db()->prepare('DELETE FROM history WHERE id = ? AND user_id = ?')
               ->execute([$id, $user['id']]);
            db()->commit();
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save_history') {
        $title   = mb_substr(trim($_POST['title'] ?? 'Study Session'), 0, 180);
        $content = trim($_POST['content'] ?? '');
        $id      = (int) ($_POST['id'] ?? 0);

        if ($content !== '') {
            if ($id > 0) {
                $check = db()->prepare('SELECT id FROM history WHERE id = ? AND user_id = ?');
                $check->execute([$id, $user['id']]);
                if ($check->fetch()) {
                    $stmt = db()->prepare('UPDATE history SET content = ? WHERE id = ?');
                    $stmt->execute([$content, $id]);
                } else {
                    // Fallback if ID invalid or unowned
                    db()->prepare('INSERT INTO history (user_id, title, content) VALUES (?, ?, ?)')
                       ->execute([$user['id'], $title, $content]);
                    $id = (int) db()->lastInsertId();
                }
                echo json_encode(['ok' => true, 'id' => $id]);
            } else {
                db()->prepare('INSERT INTO history (user_id, title, content) VALUES (?, ?, ?)')
                   ->execute([$user['id'], $title, $content]);
                $newId = (int) db()->lastInsertId();
                echo json_encode(['ok' => true, 'id' => $newId]);
            }
        } else {
            echo json_encode(['ok' => false, 'message' => 'Content is empty']);
        }
        exit;
    }

    if ($action === 'check_data_exists') {
        $stmt1 = db()->prepare('SELECT 1 FROM history WHERE user_id = ? LIMIT 1'); $stmt1->execute([$user['id']]); $c1 = $stmt1->fetchColumn();
        $stmt2 = db()->prepare('SELECT 1 FROM archive WHERE user_id = ? LIMIT 1'); $stmt2->execute([$user['id']]); $c2 = $stmt2->fetchColumn();
        $stmt3 = db()->prepare('SELECT 1 FROM trash WHERE user_id = ? LIMIT 1'); $stmt3->execute([$user['id']]); $c3 = $stmt3->fetchColumn();
        $stmt4 = db()->prepare('SELECT 1 FROM quiz_scores WHERE user_id = ? LIMIT 1'); $stmt4->execute([$user['id']]); $c4 = $stmt4->fetchColumn();
        echo json_encode(['hasData' => (bool)($c1 || $c2 || $c3 || $c4)]);
        exit;
    }

    if ($action === 'clear_data') {
        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM history WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM archive WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM trash WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM quiz_scores WHERE user_id = ?')->execute([$user['id']]);
            db()->commit();
        } catch (\Throwable $e) { 
            db()->rollBack(); 
            throw $e; 
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'history_rename') {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 180);
        if ($title === '') throw new RuntimeException('Title cannot be empty.');
        $stmt = db()->prepare('UPDATE history SET title = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $id, $user['id']]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Session not found or access denied.');
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── Pinned ──────────────────────────────────────────── */
    if ($action === 'pin_add') {
        $historyId = (int) ($_POST['id'] ?? 0);
        $check = db()->prepare('SELECT id FROM history WHERE id = ? AND user_id = ?');
        $check->execute([$historyId, $user['id']]);
        if (!$check->fetch()) throw new RuntimeException('Session not found.');
        db()->prepare('INSERT IGNORE INTO pinned (user_id, history_id) VALUES (?, ?)')->execute([$user['id'], $historyId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'pin_remove') {
        $historyId = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM pinned WHERE user_id = ? AND history_id = ?')->execute([$user['id'], $historyId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'pin_list') {
        $stmt = db()->prepare('SELECT h.id, h.title, h.created_at
            FROM pinned p JOIN history h ON h.id = p.history_id
            WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 20');
        $stmt->execute([$user['id']]);
        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()]);
        exit;
    }

    /* ── Archive ─────────────────────────────────────────── */
    if ($action === 'archive_add') {
        $histId = (int) ($_POST['id'] ?? 0);
        $check  = db()->prepare('SELECT * FROM history WHERE id = ? AND user_id = ?');
        $check->execute([$histId, $user['id']]);
        $item   = $check->fetch();
        if (!$item) throw new RuntimeException('Session not found.');
        if (($item['generation_status'] ?? '') === 'processing') {
            throw new RuntimeException('Cannot archive while AI is generating. Please wait for it to finish.');
        }

        $content = json_decode($item['content'] ?? '[]', true);
        if (is_array($content)) {
            foreach ($content as $msg) {
                if (($msg['type'] ?? '') === 'quiz') {
                    $isCompleted = !empty($msg['quizState']['completed']);
                    if (!$isCompleted) {
                        throw new RuntimeException('Cannot archive a session with an unfinished quiz. Please finish the quiz first.');
                    }
                }
            }
        }

        // Preserve generation_status: if still processing, mark as pending_retry
        // since the background worker references the old history ID which will be deleted
        $archiveStatus = ($item['generation_status'] ?? 'idle') === 'processing' ? 'pending_retry' : ($item['generation_status'] ?? 'idle');
        db()->beginTransaction();
        try {
            db()->prepare('INSERT INTO archive (user_id, title, content, generation_status) VALUES (?, ?, ?, ?)')->execute([$user['id'], $item['title'], $item['content'], $archiveStatus]);
            db()->prepare('DELETE FROM pinned WHERE user_id = ? AND history_id = ?')->execute([$user['id'], $histId]);
            db()->prepare('DELETE FROM history WHERE id = ? AND user_id = ?')->execute([$histId, $user['id']]);
            db()->commit();
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'archive_list') {
        $stmt = db()->prepare('SELECT id, title, archived_at FROM archive WHERE user_id = ? ORDER BY archived_at DESC LIMIT 30');
        $stmt->execute([$user['id']]);
        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'archive_rename') {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 180);
        if ($title === '') throw new RuntimeException('Title cannot be empty.');
        $stmt = db()->prepare('UPDATE archive SET title = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $id, $user['id']]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Session not found or access denied.');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'archive_open') {
        $stmt = db()->prepare('SELECT id, title, content, archived_at FROM archive WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_GET['id'] ?? 0), $user['id']]);
        echo json_encode(['ok' => true, 'item' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'archive_restore') {
        $archId = (int) ($_POST['id'] ?? 0);
        $check  = db()->prepare('SELECT * FROM archive WHERE id = ? AND user_id = ?');
        $check->execute([$archId, $user['id']]);
        $item   = $check->fetch();
        if (!$item) throw new RuntimeException('Archived session not found.');
        // Restore generation_status so frontend can detect interrupted generations
        $restoreStatus = $item['generation_status'] ?? 'idle';
        db()->beginTransaction();
        try {
            db()->prepare('INSERT INTO history (user_id, title, content, generation_status) VALUES (?, ?, ?, ?)')->execute([$user['id'], $item['title'], $item['content'], $restoreStatus]);
            $newHistoryId = (int) db()->lastInsertId();
            db()->prepare('DELETE FROM archive WHERE id = ? AND user_id = ?')->execute([$archId, $user['id']]);
            db()->commit();
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }
        echo json_encode(['ok' => true, 'new_id' => $newHistoryId]);
        exit;
    }

    if ($action === 'archive_delete') {
        db()->prepare('DELETE FROM archive WHERE id = ? AND user_id = ?')->execute([(int) ($_POST['id'] ?? 0), $user['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── Trash ───────────────────────────────────────────── */
    if ($action === 'trash_list') {
        $stmt = db()->prepare('SELECT id, title, deleted_at FROM trash WHERE user_id = ? ORDER BY deleted_at DESC LIMIT 50');
        $stmt->execute([$user['id']]);
        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'trash_open') {
        $stmt = db()->prepare('SELECT id, title, content, deleted_at FROM trash WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_GET['id'] ?? 0), $user['id']]);
        echo json_encode(['ok' => true, 'item' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'trash_restore') {
        $trashId = (int) ($_POST['id'] ?? 0);
        $check   = db()->prepare('SELECT * FROM trash WHERE id = ? AND user_id = ?');
        $check->execute([$trashId, $user['id']]);
        $item    = $check->fetch();
        if (!$item) throw new RuntimeException('Trashed session not found.');
        db()->beginTransaction();
        try {
            db()->prepare('INSERT INTO history (user_id, title, content) VALUES (?, ?, ?)')->execute([$user['id'], $item['title'], $item['content']]);
            db()->prepare('DELETE FROM trash WHERE id = ? AND user_id = ?')->execute([$trashId, $user['id']]);
            db()->commit();
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'trash_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM trash WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'trash_empty') {
        db()->prepare('DELETE FROM trash WHERE user_id = ?')->execute([$user['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── Save score ──────────────────────────────────────── */
    if ($action === 'save_score') {
        $score = max(0, (int) ($_POST['score'] ?? 0));
        $total = max(1, (int) ($_POST['total'] ?? 1));
        $pct   = round(($score / $total) * 100, 2);
        db()->prepare('INSERT INTO quiz_scores (user_id, score, total_questions, percentage) VALUES (?, ?, ?, ?)')->execute([$user['id'], $score, $total, $pct]);
        echo json_encode(['ok' => true, 'percentage' => $pct]);
        exit;
    }

    if ($action === 'submit_rating') {
        $input  = json_request_body();
        $rating = (int) ($input['rating'] ?? $_POST['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Please select a rating from 1 to 5 stars.');
        }

        ensure_feedback_schema();
        $stmt = db()->prepare('SELECT message FROM feedback WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            db()->prepare('UPDATE feedback SET rating = ?, sentiment = ?, is_reviewed = 0, is_archived = 0, created_at = CURRENT_TIMESTAMP WHERE user_id = ?')
               ->execute([$rating, feedback_sentiment($rating, (string)$existing['message']), $user['id']]);
        } else {
            db()->prepare('INSERT INTO feedback (user_id, message, rating, sentiment) VALUES (?, ?, ?, ?)')
               ->execute([$user['id'], '', $rating, feedback_sentiment($rating)]);
        }

        echo json_encode(['ok' => true, 'message' => 'Thank you for your rating!']);
        exit;
    }

    if ($action === 'submit_feedback') {
        $input   = json_request_body();
        $message = trim((string) ($input['message'] ?? $_POST['message'] ?? ''));
        $words   = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($message === '') {
            throw new RuntimeException('Feedback cannot be empty.');
        }
        if (count($words) > 300) {
            throw new RuntimeException('Feedback must be 300 words or fewer.');
        }

        ensure_feedback_schema();
        $stmt = db()->prepare('SELECT rating FROM feedback WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $r = isset($existing['rating']) ? (int)$existing['rating'] : null;
            db()->prepare('UPDATE feedback SET message = ?, sentiment = ?, is_reviewed = 0, is_archived = 0, created_at = CURRENT_TIMESTAMP WHERE user_id = ?')
               ->execute([$message, feedback_sentiment($r, $message), $user['id']]);
        } else {
            db()->prepare('INSERT INTO feedback (user_id, message, rating, sentiment) VALUES (?, ?, NULL, ?)')
               ->execute([$user['id'], $message, feedback_sentiment(null, $message)]);
        }

        echo json_encode(['ok' => true, 'message' => 'Thank you for your feedback!']);
        exit;
    }

    /* ── Settings / Profile ──────────────────────────────── */
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $gender   = trim($_POST['gender'] ?? '');
        
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($username === '' || $birthday === '' || $gender === '') {
            throw new RuntimeException('Username, birthday, and gender are required.');
        }

        // Validate username length and characters
        if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_ -]+$/', $username)) {
            throw new RuntimeException('Username must be 3-30 characters and contain only letters, numbers, spaces, hyphens, or underscores.');
        }

        // Check for duplicate username
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $user['id']]);
        if ($stmt->fetch()) {
            throw new RuntimeException('That username is already taken. Please choose another.');
        }

        $hashToSave = null;

        // If user wants to change password
        if ($newPass !== '') {
            if ($currentPass === '') {
                throw new RuntimeException('You must enter your current password to set a new one.');
            }
            
            // Verify current password
            $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $dbHash = $stmt->fetchColumn();
            
            if (!password_verify($currentPass, $dbHash)) {
                throw new RuntimeException('Current password is incorrect.');
            }
            
            if ($newPass !== $confirmPass) {
                throw new RuntimeException('New passwords do not match.');
            }
            
            if (!password_is_strong($newPass)) {
                throw new RuntimeException('New password must be 8-30 chars and contain upper, lower, number, and special character.');
            }
            
            if (password_verify($newPass, $dbHash)) {
                throw new RuntimeException('New password cannot be the same as your current password.');
            }

            $hashToSave = password_hash($newPass, PASSWORD_DEFAULT);
        }

        if ($hashToSave !== null) {
            db()->prepare('UPDATE users SET username = ?, birthday = ?, gender = ?, password = ? WHERE id = ?')
               ->execute([$username, $birthday, $gender, $hashToSave, $user['id']]);
        } else {
            db()->prepare('UPDATE users SET username = ?, birthday = ?, gender = ? WHERE id = ?')
               ->execute([$username, $birthday, $gender, $user['id']]);
        }

        // Refresh session data for frontend if needed
        $_SESSION['user_id'] = $user['id']; // just to keep alive

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_account') {
        $password = $_POST['password'] ?? '';
        
        if ($password === '') {
            throw new RuntimeException('Password is required to delete your account.');
        }
        
        // Verify current password
        $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $dbHash = $stmt->fetchColumn();
        
        if (!password_verify($password, $dbHash)) {
            throw new RuntimeException('Incorrect password.');
        }

        db()->beginTransaction();
        try {
            // Transactional deletion of all user data
            db()->prepare('DELETE FROM trash WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM archive WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM pinned WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM history WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM quiz_scores WHERE user_id = ?')->execute([$user['id']]);
            // Delete user last
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            throw $e;
        }
        
        // Destroy the session
        session_destroy();
        // Clear remember token cookie if exists
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── AI ──────────────────────────────────────────────── */
    if ($action === 'ai') {
        $mode      = (($_POST['mode'] ?? '') === 'quiz') ? 'quiz' : 'lesson';
        $topic     = trim($_POST['topic'] ?? '');
        $imageData = null;
        $fileText  = '';

        // Route uploaded file to correct extractor
        if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo(basename($_FILES['file']['name']), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
                $imageData = extract_uploaded_image();
            } else {
                $fileText = extract_uploaded_text();
            }
        }

        // Build text input
        $parts = [];
        if ($fileText !== '') $parts[] = $fileText;
        if ($topic    !== '') $parts[] = $topic;
        $input = implode("\n\n", $parts);

        if ($input === '' && $imageData === null) {
            throw new RuntimeException('Please enter a topic, keyword, paste notes, or upload a file/image.');
        }
        if ($input !== '' && !educational_guard($input)) {
            throw new RuntimeException('I can only help with studying, lessons, explanations, quizzes, and exam preparation.');
        }

        // Check limits before proceeding
        $limit = check_and_increment_limit((int) $user['id'], $mode);
        if (!$limit['allowed']) {
            echo json_encode(['ok' => false, 'limited' => true, 'message' => $limit['message'], 'resetAt' => $limit['resetAt'] ?? null]);
            exit;
        }

        // 1. Create or update session in DB
        $historyId = (int) ($_POST['history_id'] ?? 0);
        $messagesJson = $_POST['messages'] ?? '[]';
        $title = mb_substr(trim($_POST['title'] ?? 'Study Session'), 0, 180);

        if ($historyId > 0) {
            db()->prepare("UPDATE history SET title = ?, content = ?, generation_status = 'processing' WHERE id = ? AND user_id = ?")
               ->execute([$title, $messagesJson, $historyId, $user['id']]);
        } else {
            db()->prepare("INSERT INTO history (user_id, title, content, generation_status) VALUES (?, ?, ?, 'processing')")
               ->execute([$user['id'], $title, $messagesJson]);
            $historyId = (int) db()->lastInsertId();
        }

        // 2. Respond to client immediately
        ob_start();
        echo json_encode(['ok' => true, 'history_id' => $historyId, 'status' => 'processing', 'stats' => dashboard_stats((int) $user['id'])]);
        $size = ob_get_length();
        header("Connection: close");
        header("Content-Length: $size");
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // 3. Background execution
        if (session_id()) session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);

        try {
            $response = null;
            $cacheKey = null;

            // Cache (text inputs only)
            if ($imageData === null) {
                $cacheKey  = normalize_topic($input);
                $cacheStmt = db()->prepare('SELECT response FROM ai_cache WHERE topic = ? AND mode = ? LIMIT 1');
                $cacheStmt->execute([$cacheKey, $mode]);
                $response  = $cacheStmt->fetchColumn();
            }

            if (!$response) {
                $response = call_gemini($input, $mode, $imageData);
                if ($imageData === null) {
                    db()->prepare('INSERT INTO ai_cache (topic, response, mode) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response = VALUES(response), created_at = CURRENT_TIMESTAMP')
                       ->execute([$cacheKey, $response, $mode]);
                }
            }

            // Re-fetch current content
            $stmt = db()->prepare('SELECT content FROM history WHERE id = ?');
            $stmt->execute([$historyId]);
            $currentContent = $stmt->fetchColumn();
            $messages = json_decode($currentContent, true) ?: [];

            // Append AI message
            if ($mode === 'quiz') {
                $quizData = json_decode($response, true);
                if (!$quizData || empty($quizData['questions'])) {
                    throw new RuntimeException("Could not generate valid quiz questions. Please try again or provide a different topic.");
                }
                $messages[] = ['role' => 'ai', 'type' => 'quiz', 'quizData' => $quizData, 'text' => $response];
            } else {
                $messages[] = ['role' => 'ai', 'type' => 'lesson', 'text' => $response];
            }

            // Update DB
            db()->prepare("UPDATE history SET content = ?, generation_status = 'idle' WHERE id = ?")
               ->execute([json_encode($messages), $historyId]);

        } catch (Throwable $e) {
            // Refund tokens since generation failed
            $cost = ($mode === 'quiz') ? 3 : 2;
            db()->prepare('UPDATE usage_limits SET hourly_count = GREATEST(0, hourly_count - ?), daily_count = GREATEST(0, daily_count - ?) WHERE user_id = ?')
               ->execute([$cost, $cost, $user['id']]);

            db()->prepare("UPDATE history SET generation_status = 'idle' WHERE id = ?")
               ->execute([$historyId]);
        }
        exit;
    }

    throw new RuntimeException('Unknown action.');

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit;
}

/* ════════════════════════════════════════════════════════════
   FUNCTIONS
   ════════════════════════════════════════════════════════════ */

function json_request_body(): array
{
    static $body = null;
    if ($body !== null) { return $body; }

    $raw = file_get_contents('php://input');
    $decoded = $raw !== false && $raw !== '' ? json_decode($raw, true) : [];
    $body = is_array($decoded) ? $decoded : [];
    return $body;
}

function dashboard_stats(int $userId): array
{
    $lessonStmt = db()->prepare('SELECT COUNT(*) FROM history WHERE user_id = ?');
    $lessonStmt->execute([$userId]);
    $lessonCount = (int) $lessonStmt->fetchColumn();

    $quizStmt = db()->prepare('SELECT COUNT(*), COALESCE(AVG(percentage), 0) FROM quiz_scores WHERE user_id = ?');
    $quizStmt->execute([$userId]);
    [$quizCount, $avg] = array_values($quizStmt->fetch(PDO::FETCH_NUM));

    $usage    = current_usage($userId);
    $progress = min(100, ($lessonCount * 10) + ((int) $quizCount * 8));

    return [
        'progress'    => $progress,
        'lessons'     => $lessonCount,
        'quizzes'     => (int) $quizCount,
        'average'     => round((float) $avg, 1),
        'hourly'      => $usage['hourly_count'],
        'hourlyLimit' => HOURLY_AI_LIMIT,
        'daily'       => $usage['daily_count'],
        'dailyLimit'  => DAILY_AI_LIMIT,
    ];
}

function current_usage(int $userId): array
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('SELECT * FROM usage_limits WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    
    if (!$row) {
        db()->prepare('INSERT INTO usage_limits (user_id, last_reset_hour, last_reset_day) VALUES (?, ?, ?)')->execute([$userId, $now, $now]);
        return ['hourly_count' => 0, 'daily_count' => 0, 'last_reset_hour' => $now, 'last_reset_day' => $now];
    }
    
    $hourly = (int) $row['hourly_count'];
    $daily  = (int) $row['daily_count'];
    $last_hour = $row['last_reset_hour'];
    $last_day  = $row['last_reset_day'];
    $needsUpdate = false;
    
    if ($hourly > 0 && strtotime($now) >= strtotime($last_hour) + 3600) {
        $hourly = 0;
        $needsUpdate = true;
    }
    
    // Check exactly 24 hours (86400 seconds)
    if ($daily > 0 && strtotime($now) >= strtotime($last_day) + 86400) {
        $daily = 0;
        $needsUpdate = true;
    }

    if ($needsUpdate) {
        db()->prepare('UPDATE usage_limits SET hourly_count=?, daily_count=? WHERE user_id=?')->execute([$hourly, $daily, $userId]);
    }
    
    return ['hourly_count' => $hourly, 'daily_count' => $daily, 'last_reset_hour' => $last_hour, 'last_reset_day' => $last_day];
}

function check_and_increment_limit(int $userId, string $mode): array
{
    $usage = current_usage($userId);
    $cost = ($mode === 'quiz') ? 3 : 2;
    $now = date('Y-m-d H:i:s');
    
    $hourly = $usage['hourly_count'];
    $daily = $usage['daily_count'];
    $last_hour = $usage['last_reset_hour'];
    $last_day = $usage['last_reset_day'];
    
    if ($daily + $cost > DAILY_AI_LIMIT) {
        $resetTimeDaily = date('g:ia', strtotime($last_day) + 86400);
        if ($daily < DAILY_AI_LIMIT) {
            $left = DAILY_AI_LIMIT - $daily;
            if ($left >= 2 && $mode === 'quiz') {
                return ['allowed' => false, 'message' => "Sorry, you only have $left tokens left. But, you can still take a lesson. Please return at $resetTimeDaily."];
            } else {
                return ['allowed' => false, 'message' => "Sorry, you don't have enough token, please come back after 24 hr. Please return at $resetTimeDaily."];
            }
        } else {
            return ['allowed' => false, 'message' => "Sorry, you don't have enough token, please come back after 24 hr. Please return at $resetTimeDaily."];
        }
    }
    
    if ($hourly + $cost > HOURLY_AI_LIMIT) {
        $resetTime = date('g:ia', strtotime($last_hour) + 3600);
        if ($hourly < HOURLY_AI_LIMIT) {
            $left = HOURLY_AI_LIMIT - $hourly;
            if ($left >= 2 && $mode === 'quiz') {
                return ['allowed' => false, 'message' => "Sorry, you only have $left tokens left. But, you can still take a lesson. Please return at $resetTime."];
            } else {
                return ['allowed' => false, 'message' => "Sorry, you only have $left token left. I suggest you to wait 1 hour to reset your token. Please return at $resetTime."];
            }
        } else {
            return ['allowed' => false, 'message' => "You've reach your hourly limit. Please come back at $resetTime."];
        }
    }
    
    if ($hourly === 0) $last_hour = $now;
    if ($daily === 0) $last_day = $now;
    
    $newHourly = $hourly + $cost;
    $newDaily = $daily + $cost;
    
    db()->prepare('UPDATE usage_limits SET hourly_count=?, daily_count=?, last_reset_hour=?, last_reset_day=? WHERE user_id=?')
      ->execute([$newHourly, $newDaily, $last_hour, $last_day, $userId]);
      
    return ['allowed' => true];
}

/* ── File extractors ──────────────────────────────────────── */

function extract_uploaded_image(): ?array
{
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) return null;
    $name = basename($_FILES['file']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp'];
    if (!isset($mimeTypes[$ext])) return null;
    if ($_FILES['file']['size'] > 4 * 1024 * 1024) throw new RuntimeException('Image is too large. Please upload an image smaller than 4 MB.');
    $data = base64_encode(file_get_contents($_FILES['file']['tmp_name']));
    if (!$data) throw new RuntimeException('Could not read the uploaded image.');
    return ['mime_type' => $mimeTypes[$ext], 'data' => $data, 'name' => $name];
}

function extract_uploaded_text(): string
{
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) return '';
    $name = basename($_FILES['file']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'docx', 'txt'], true)) throw new RuntimeException('Only PDF, DOCX, TXT, and image files are supported.');

    if ($ext === 'txt') {
        $raw = file_get_contents($_FILES['file']['tmp_name']);
        return trim(mb_convert_encoding((string) $raw, 'UTF-8', 'auto'));
    }

    if ($ext === 'docx') {
        $text = extract_docx_php($_FILES['file']['tmp_name']);
        if ($text !== '') return $text;
    }

    if ($ext === 'pdf' || $ext === 'docx') {
        $safeName = uniqid('study_', true) . '.' . $ext;
        $target   = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $extractor = escapeshellarg(PYTHON_EXTRACTOR);
            $file      = escapeshellarg($target);
            $text      = @shell_exec("python3 {$extractor} {$file} 2>/dev/null");
            if (!$text) $text = @shell_exec("python {$extractor} {$file} 2>/dev/null");
            @unlink($target);
            $text = trim((string) $text);
            if ($text !== '') return $text;
        }
    }

    $baseName = pathinfo($name, PATHINFO_FILENAME);
    $topic    = trim(preg_replace('/[\-_]+/', ' ', $baseName));
    return $topic !== '' ? "Topic from uploaded file: {$topic}" : '';
}

function extract_docx_php(string $tmpPath): string
{
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) return '';
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) return '';
    $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim(mb_substr($text, 0, 30000));
}

function normalize_topic(string $input): string
{
    $normalized = preg_replace('/\s+/', ' ', strtolower(trim($input)));
    return mb_substr(hash('sha256', $normalized) . ':' . mb_substr($normalized, 0, 210), 0, 255);
}

/* ── Gemini call with model fallback ─────────────────────── */

function call_gemini(string $input, string $mode, ?array $imageData = null): string
{
    if (!GEMINI_API_KEY || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        return sample_response($input, $mode);
    }

    $fromImage = $imageData !== null;
    $prompt    = ($mode === 'quiz') ? quiz_prompt($input, $fromImage) : lesson_prompt($input, $fromImage);

    $models = array_values(array_unique(array_filter([
        GEMINI_MODEL,
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.5-flash-preview-05-20',
    ])));

    $lastErr   = 'Gemini is currently unavailable. Please try again in a moment.';
    $retryable = false;

    foreach ($models as $model) {
        $maxTries = 3;
        for ($try = 1; $try <= $maxTries; $try++) {
            try {
                return gemini_curl($prompt, $model, $imageData, $mode === 'quiz');
            } catch (RuntimeException $e) {
                $lastErr = $e->getMessage();
                $lower   = strtolower($lastErr);

                $isOverloaded = str_contains($lower, 'high demand') || str_contains($lower, 'overloaded') || str_contains($lower, '503') || str_contains($lower, 'service unavailable') || str_contains($lower, 'server error') || str_contains($lower, '500');
                $isQuota      = str_contains($lower, 'quota') || str_contains($lower, 'resource_exhausted') || str_contains($lower, 'rate limit') || str_contains($lower, '429');
                $isGone       = str_contains($lower, '404') || str_contains($lower, 'not found') || str_contains($lower, 'model_not_found');

                if ($isOverloaded && $try < $maxTries) { sleep($try * 2); $retryable = true; continue; }
                if ($isQuota || $isGone || $isOverloaded) { $retryable = true; break; }
                throw $e;
            }
        }
    }

    if ($retryable) {
        throw new RuntimeException('Gemini is very busy right now. Please wait 30 seconds and try again. (If this keeps happening, your free-tier quota may be exhausted for this hour.)');
    }
    throw new RuntimeException($lastErr);
}

function gemini_curl(string $prompt, string $model, ?array $imageData = null, bool $isJson = false): string
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode($model) . ':generateContent';

    // Build parts — image first (if present), then text prompt
    $parts = [];
    if ($imageData !== null) {
        $parts[] = ['inline_data' => ['mime_type' => $imageData['mime_type'], 'data' => $imageData['data']]];
    }
    $parts[] = ['text' => $prompt];

    $config = ['temperature' => 0.7, 'maxOutputTokens' => 8192];
    if ($isJson) {
        $config['responseMimeType'] = 'application/json';
        $config['responseSchema'] = [
            'type' => 'OBJECT',
            'properties' => [
                'questions' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'question' => ['type' => 'STRING'],
                            'choices' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                            'answer' => ['type' => 'INTEGER'],
                            'explanation' => ['type' => 'STRING']
                        ],
                        'required' => ['question', 'choices', 'answer', 'explanation']
                    ]
                ]
            ],
            'required' => ['questions']
        ];
    }

    $payload = json_encode([
        'contents'         => [['parts' => $parts]],
        'generationConfig' => $config,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($raw === false) {
        throw new RuntimeException($errno === CURLE_OPERATION_TIMEDOUT ? 'Gemini did not respond in time. Please try again.' : 'Network error contacting Gemini: ' . $cerr);
    }

    $json = json_decode($raw, true);

    if (!empty($json['error'])) {
        $msg  = $json['error']['message'] ?? ('HTTP ' . $http);
        $code = $json['error']['code']    ?? $http;
        if ($code === 429 || stripos($msg, 'quota') !== false || stripos($msg, 'RESOURCE_EXHAUSTED') !== false) throw new RuntimeException('quota: ' . $model . ' — ' . $msg);
        if ($code === 503 || $code === 500 || stripos($msg, 'high demand') !== false || stripos($msg, 'overloaded') !== false) throw new RuntimeException('503 high demand: ' . $model . ' — ' . $msg);
        if ($code === 401 || $code === 403) throw new RuntimeException('Invalid Gemini API key. Please check your configuration.');
        if ($code === 404 || stripos($msg, 'not found') !== false) throw new RuntimeException('404 — model not found: ' . $model);
        throw new RuntimeException('Gemini error (' . $model . '): ' . $msg);
    }

    if (!empty($json['promptFeedback']['blockReason'])) throw new RuntimeException('Request blocked by Gemini safety filter (' . $json['promptFeedback']['blockReason'] . '). Please rephrase your topic.');
    if (($json['candidates'][0]['finishReason'] ?? '') === 'SAFETY') throw new RuntimeException('Gemini blocked this response for safety reasons. Please rephrase your topic.');

    $text = '';
    foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) { $text .= ($part['text'] ?? ''); }
    $text = trim($text);
    if ($text === '') throw new RuntimeException('Gemini returned an empty response. Please try rephrasing your topic.');
    return $text;
}

/* ── Prompt builders ─────────────────────────────────────── */

function lesson_prompt(string $input, bool $fromImage = false): string
{
    if ($fromImage) {
        $context = <<<CTX
The student has uploaded an educational image (lecture slide, textbook page, handwritten notes, diagram, chart, infographic, or whiteboard photo).

OCR AND IMAGE UNDERSTANDING RULES:
1. Perform OCR extraction — READ every visible word, number, label, and symbol.
2. Analyze all diagrams, flowcharts, tables, charts, and visual relationships.
3. Analyze handwritten notes when possible.
4. IDENTIFY all important educational concepts shown.
5. COVER every major topic from the image — do NOT skip sections.
6. Generate lessons based on extracted content, NOT image filenames.

{$input}
CTX;
    } else {
        $context = "Study material / topic / uploaded document:\n{$input}";
    }

    return <<<PROMPT
You are an advanced Academic Study AI designed to create accurate, comprehensive lessons. Your primary objective is to maximize factual accuracy, content coverage, educational quality, and source fidelity.

YOU MUST ALWAYS GENERATE A COMPLETE LESSON. Never ask the user for a document. Never say you cannot generate a lesson. If the user provides a topic, keyword, or subject name — generate a full lesson on that topic using your knowledge.

═══════════════════════════════════════════════
INPUT MODE DETECTION
═══════════════════════════════════════════════

There are TWO modes of operation. Detect which one applies:

MODE A — DOCUMENT/FILE PROVIDED:
If the input below contains substantial text content (paragraphs, definitions, lecture notes, extracted document text), then:
- This is uploaded document content. Prioritize it over general knowledge.
- Read and extract ALL content from the material.
- Capture: definitions, key terms, concepts, explanations, examples, formulas, processes, theories, tables, important notes, citations, quotes, author statements ("According to..."), research findings, historical facts, and conclusions.
- NEVER generate lessons based solely on file title or document heading.
- CONTENT PRESERVATION: If the source contains "According to John Smith, motivation is the driving force behind human behavior." — the lesson MUST include that exact statement with attribution.
- Do NOT remove author attributions, skip definitions, or summarize away important statements.
- Every important piece of information found in the source must appear in the lesson.

MODE B — TOPIC/KEYWORD ONLY:
If the input below is a short topic, keyword, or subject name (e.g., "the cold war", "photosynthesis", "Newton's laws"), then:
- Generate a comprehensive, in-depth lesson on that topic using your academic knowledge.
- Research the topic thoroughly. Draw from: academic journals, peer-reviewed papers, educational institutions (Harvard, MIT, Stanford), government publications (WHO, UNESCO, NIH), university textbooks, and reputable educational resources.
- Do NOT use Wikipedia as a primary source. Wikipedia may only be used for basic verification.
- Include real historical facts, real people, real dates, real theories, and real research findings.
- Never fabricate information, authors, or citations.
- The lesson must be detailed enough that a student could pass an exam on the topic.

COMPLETENESS CHECK (apply to BOTH modes):
Before finalizing the lesson, verify:
- Did I include all definitions and key terms?
- Did I include all major concepts and theories?
- Did I include all important people mentioned or relevant to the topic?
- Did I include all author statements and research findings?
- Did I include all significant examples?
If any important information is missing, revise until coverage is complete.

═══════════════════════════════════════════════

{$context}

═══════════════════════════════════════════════
LESSON FORMAT
═══════════════════════════════════════════════

Generate the lesson using EXACTLY the following sections. Each section must have substantial content — do NOT skip any section.

1. Overview / Introduction
   - A concise explanation of the topic's purpose in simple terms.
   - Explain why it matters and its relevance.

2. Learning Objectives
   - List clear, measurable objectives the student should achieve after this lesson.

3. Background & Main Topics
   - List and explain the major topics and subtopics.
   - How the topic connects to related subjects.

4. Key Concepts, Definitions & Important People
   - List and explain the most important concepts students should remember.
   - Include important definitions exactly as they appear in source material (if uploaded), or provide accurate academic definitions (if topic-based).
   - Include inventors, scientists, historical figures, dates, and events with proper attribution.

5. Theories and Principles
   - Include ALL relevant theories.
   - Explain each theory with its originator and significance.

6. Step-by-Step Explanation
   - Break the topic into logical steps or stages.
   - Explain each step in detail. Assume the student is a beginner.

7. Author Statements & Research Findings
   - For uploaded documents: include all "According to..." statements and preserve exact attributions.
   - For topic-based lessons: include notable expert opinions, landmark research findings, and scholarly perspectives with proper attribution.

8. Worked Examples & Illustrations
   - For uploaded documents: extract and explain examples directly from the source.
   - For topic-based lessons: provide clear, practical examples that illustrate key concepts.
   - Emphasize any highlighted or important material.

9. Common Mistakes & How to Avoid Them
   - List mistakes students typically make on this topic, with correct approaches.

10. Tips & Tricks for Exams
    - Provide memory aids, mnemonics, or shortcuts relevant to this topic.
    - Emphasize likely exam material (definitions, formulas, names, dates).

11. Summary & Review
    - Concise bullet-point recap of everything covered.
    - The 5 most important things to remember.

12. References
    - For topic-based lessons: list the academic sources used (Author, Title, Source/URL, Year). Prioritize academic journals, university sources, and government publications. Do NOT cite Wikipedia. Never fabricate references.
    - For uploaded document lessons: skip this section.

End with exactly this line:
Would you like to take a quiz on this topic?
PROMPT;
}

function quiz_prompt(string $input, bool $fromImage = false): string
{
    $imageNote = $fromImage
        ? "\nIMPORTANT: All questions must be based directly on the content visible in the uploaded educational image. Perform OCR to extract all text, then generate questions from the extracted content."
        : "\nIMPORTANT: If the input is a topic or keyword, generate quiz questions based on comprehensive academic knowledge of that topic. If the input contains uploaded document text, base all questions ONLY on that content. Never ask for additional documents — always generate the quiz.";
    return <<<PROMPT
You are an advanced Academic Quiz Generator AI. Create EXACTLY 20 multiple-choice exam questions based on the study material below.{$imageNote}

Return ONLY valid JSON — no markdown, no code fences, no extra text before or after.
Exact format:
{"questions":[{"question":"...","choices":["choice text only","choice text only","choice text only","choice text only"],"answer":0,"explanation":"..."}]}

═══════════════════════════════════════════════
STRICT RULES
═══════════════════════════════════════════════

QUESTION GENERATION:
- Generate EXACTLY 20 questions (not fewer, not more).
- Each question must have exactly 4 choices.
- Choices must be PLAIN TEXT ONLY — do NOT include "A.", "B.", "C.", "D." or any letter prefix.
- "answer" is the 0-based index of the correct choice (0 = first, 1 = second, 2 = third, 3 = fourth).
- "explanation" briefly explains why the answer is correct and references the lesson section it came from (1–2 sentences).
- The quiz MUST be generated ONLY from the lesson/source content. Never create questions from external information.

DIFFICULTY DISTRIBUTION:
- Easy (20%): ~4 questions — basic recall of definitions and facts.
- Medium (50%): ~10 questions — understanding, interpretation, and application.
- Hard (30%): ~6 questions — analysis, critical thinking, and synthesis.
- Avoid obvious questions where the answer can be guessed immediately.

QUESTION COVERAGE:
The quiz must cover ALL of these when present in the source material:
- Definitions and key terms
- Key concepts and principles
- Important people and their contributions
- Author statements ("According to...")
- Processes and procedures
- Theories and frameworks
- Examples from the source material
Do NOT generate multiple questions from the same paragraph while ignoring other sections.
Coverage should be balanced across the ENTIRE lesson.

MULTIPLE CHOICE QUALITY:
- All options must appear plausible and believable.
- Only one correct answer per question.
- Distractors should be realistic — avoid silly or obviously wrong options.
- Avoid making the correct answer obviously longer than others.
- Avoid repeating answer position patterns.

ANSWER DISTRIBUTION (CRITICAL):
- Distribute correct answers evenly across positions: A(0)≈25%, B(1)≈25%, C(2)≈25%, D(3)≈25%.
- No single position should have more than 35% of correct answers.
- Randomize answer positions — never create predictable patterns.
- Before finalizing, check the distribution. If unbalanced, redistribute.

QUALITY VERIFICATION (perform before responding):
✓ All questions derived from source material only
✓ No hallucinated or external information
✓ Balanced difficulty distribution
✓ Balanced answer position distribution
✓ Plausible distractors on every question
✓ No obvious answers
✓ Coverage spans the entire source material
✓ Explanations reference the source

Study material / topic:
{$input}
PROMPT;
}

function sample_response(string $input, string $mode): string
{
    if ($mode === 'quiz') {
        $questions = [];
        for ($i = 1; $i <= 20; $i++) {
            $questions[] = ['question' => "Sample question {$i}: What is the main purpose of reviewing a lesson?", 'choices' => ['To memorize without understanding', 'To strengthen understanding before exams', 'To skip practice', 'To avoid asking questions'], 'answer' => 1, 'explanation' => 'Reviewing helps connect ideas and solidify understanding before exams.'];
        }
        return json_encode(['questions' => $questions]);
    }
    return "1. Introduction\nThis is a demo lesson — no Gemini API key is configured yet.\n\n2. Background & Context\nConfigure a Gemini API key in config/config.php to generate real lessons.\n\n3. Key Concepts & Terminology\n- Topic: The subject you want to study.\n- Lesson: AI-generated educational content.\n- Quiz: 20-question assessment based on the lesson.\n\n4. Step-by-Step Explanation\nStep 1: Choose a topic or upload a file/image.\nStep 2: Start a lesson.\nStep 3: After the lesson, click Quiz or More Lesson.\nStep 4: Complete the quiz and review your score.\n\n5. Worked Examples\nExample: Enter 'Photosynthesis' → Get a full lesson → Take a 20-question quiz.\n\n6. Common Mistakes\n- Skipping the lesson and going straight to quiz.\n- Not reviewing wrong answers.\n- Not using the More Lesson button for harder topics.\n\n7. Tips & Tricks\n- Use More Lesson for simpler explanations.\n- Take the quiz multiple times to improve your score.\n- Aim for 90%+ before your exam.\n\n8. Summary\n- Study Tracker uses AI to generate lessons and quizzes.\n- Score 90%+ means you are exam-ready.\n- Pin important sessions for quick access.\n\nWould you like to take a quiz on this topic?";
}

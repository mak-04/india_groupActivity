<?php
$_SERVER['DOCUMENT_ROOT'] = 'c:/xampp/htdocs';
require 'c:/xampp/htdocs/ai-study-tracker/admin/config.php';
require 'c:/xampp/htdocs/ai-study-tracker/config/config.php';

try {
    $db = db();
    $sort   = 'date_desc';
    $search = '';
    $limit  = 20;
    $offset = 0;
    $order = 'u.created_at DESC';
    $where = '';
    $params = [];

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

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    echo "SUCCESS\n";
    print_r($users);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

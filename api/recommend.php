<?php
// api/recommend.php — recommendation engine + free-text search
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/upload_helper.php';

header('Content-Type: application/json');
auth_require('student');

$tag_ids = array_filter(array_map('intval', (array)($_GET['tags'] ?? [])));
$query   = trim($_GET['q'] ?? '');  // free-text

// Build base query
if (!empty($tag_ids) && empty($query)) {
    // ── Tag-match mode ──
    $in  = implode(',', $tag_ids);
    $sql = "
        SELECT
            t.tutor_id, u.user_id, u.name, u.avatar, u.department, u.year_of_study,
            t.bio, t.availability_note, t.avg_rating, t.rating_count, t.is_available,
            COUNT(DISTINCT tt.tag_id)                                      AS match_count,
            ROUND(COUNT(DISTINCT tt.tag_id) + 0.5 * t.avg_rating, 4)      AS score,
            GROUP_CONCAT(DISTINCT tg_all.label ORDER BY tg_all.label SEPARATOR '||') AS tag_list,
            GROUP_CONCAT(DISTINCT CASE WHEN tt.tag_id IN ($in) THEN tg_all.label END SEPARATOR '||') AS matched_tags
        FROM tutors t
        JOIN users      u         ON u.user_id    = t.user_id
        JOIN tutor_tags tt        ON tt.tutor_id  = t.tutor_id AND tt.tag_id IN ($in)
        LEFT JOIN tutor_tags tt_a ON tt_a.tutor_id = t.tutor_id
        LEFT JOIN tags   tg_all   ON tg_all.tag_id = tt_a.tag_id
        GROUP BY t.tutor_id
        ORDER BY score DESC, t.avg_rating DESC, u.name ASC
        LIMIT 30";
    $rows = db()->query($sql)->fetchAll();

} elseif (!empty($query)) {
    // ── Free-text mode — match tutor name OR tag label ──
    $like  = '%' . $query . '%';
    $stmt  = db()->prepare("
        SELECT DISTINCT
            t.tutor_id, u.user_id, u.name, u.avatar, u.department, u.year_of_study,
            t.bio, t.availability_note, t.avg_rating, t.rating_count, t.is_available,
            0                                   AS match_count,
            ROUND(0.5 * t.avg_rating, 4)        AS score,
            GROUP_CONCAT(DISTINCT tg.label ORDER BY tg.label SEPARATOR '||') AS tag_list,
            '' AS matched_tags
        FROM tutors t
        JOIN users u      ON u.user_id   = t.user_id
        LEFT JOIN tutor_tags tt ON tt.tutor_id = t.tutor_id
        LEFT JOIN tags tg       ON tg.tag_id   = tt.tag_id
        WHERE u.name LIKE ?
           OR tg.label LIKE ?
           OR t.bio    LIKE ?
        GROUP BY t.tutor_id
        ORDER BY t.avg_rating DESC, u.name ASC
        LIMIT 30");
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll();

} else {
    // ── No filter — show all ──
    $sql = "
        SELECT
            t.tutor_id, u.user_id, u.name, u.avatar, u.department, u.year_of_study,
            t.bio, t.availability_note, t.avg_rating, t.rating_count, t.is_available,
            0                             AS match_count,
            ROUND(0.5 * t.avg_rating, 4) AS score,
            GROUP_CONCAT(DISTINCT tg.label ORDER BY tg.label SEPARATOR '||') AS tag_list,
            '' AS matched_tags
        FROM tutors t
        JOIN users u        ON u.user_id   = t.user_id
        LEFT JOIN tutor_tags tt ON tt.tutor_id = t.tutor_id
        LEFT JOIN tags tg       ON tg.tag_id   = tt.tag_id
        GROUP BY t.tutor_id
        ORDER BY t.avg_rating DESC, u.name ASC
        LIMIT 30";
    $rows = db()->query($sql)->fetchAll();
}

$result = [];
foreach ($rows as $i => $r) {
    $result[] = [
        'tutor_id'          => (int)$r['tutor_id'],
        'user_id'           => (int)$r['user_id'],
        'name'              => $r['name'],
        'avatar_url'        => avatar_url($r['avatar']),
        'department'        => $r['department'] ?? '',
        'year_of_study'     => $r['year_of_study'] ?? '',
        'bio'               => $r['bio'] ?? '',
        'availability_note' => $r['availability_note'] ?? '',
        'avg_rating'        => (float)$r['avg_rating'],
        'rating_count'      => (int)$r['rating_count'],
        'is_available'      => (bool)$r['is_available'],
        'match_count'       => (int)$r['match_count'],
        'score'             => (float)$r['score'],
        'rank'              => $i + 1,
        'tags'              => array_values(array_filter(explode('||', $r['tag_list']   ?? ''))),
        'matched_tags'      => array_values(array_filter(explode('||', $r['matched_tags'] ?? ''))),
    ];
}

echo json_encode(['status'=>'ok','data'=>$result,'query'=>$query,'tag_ids'=>array_values($tag_ids)]);

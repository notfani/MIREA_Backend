<?php
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/RedisClient.php';

class SetTheme
{
    public static function handle()
    {
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $theme = $d['theme'] ?? '';
        $allowed = ['light','dark','colorblind'];
        if (!in_array($theme, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid theme']);
            return;
        }

        $uid = (int)($_COOKIE['uid'] ?? 0);
        if ($uid <= 0) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'not authenticated']);
            return;
        }

        try {
            $db = DB::get();
            $st = $db->prepare('UPDATE users SET theme = ? WHERE id = ?');
            $st->execute([$theme, $uid]);
            setcookie('theme', $theme, time() + 3600*24*365, '/');

            $redis = RedisClient::get();
            $pattern = "content:$uid:*";
            $it = null;
            while ($keys = $redis->scan($it, $pattern, 100)) {
                foreach ($keys as $k) $redis->del($k);
                if ($it === 0) break;
            }

            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'server error']);
        }
    }
}

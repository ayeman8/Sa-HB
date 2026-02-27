<?php
/**
 * ============================================================
 * FOXA FAMILY SA-MP Website — REST API Backend
 * File    : api.php
 * Version : 2.0 Professional
 * Author  : FOXA FAMILY Dev Team
 *
 * Upload to: public_html/api.php  (same folder as index.html)
 * Requires : PHP 7.4+, PDO_MySQL extension
 * ============================================================
 *
 * ENDPOINTS (all via ?action=ACTION_NAME)
 * ────────────────────────────────────────
 * Auth     : login, register, verify
 * Profile  : get_profile, update_profile
 * Commands : get_commands, add_command, edit_command, delete_command
 * Users    : get_users, update_user
 * Sections : get_sections, update_section
 * Settings : get_settings, update_setting    [superadmin]
 * Announce : get_announcements, add_announcement, delete_announcement
 * Skills   : update_skill
 * Logs     : get_activity_log
 * ============================================================
 */

// ── Bootstrap ──────────────────────────────────────────────
define('FOXA_API', true);

require_once __DIR__ . '/config.php';

// ── Response headers ───────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');
header('X-Powered-By: FOXA FAMILY API v2.0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Database ────────────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    respond(503, false, 'Database connection failed. Check config.php');
}

// ── Helpers ─────────────────────────────────────────────────

/**
 * Send a JSON response and terminate.
 */
function respond(int $code, bool $success, string $message = '', array $data = []): void
{
    http_response_code($code);
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data, 'ts' => time()],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

/**
 * Get the authenticated user from the X-Token header / token parameter.
 * @param string $minRole Minimum required role.
 * @param bool   $required Terminate with 401/403 if not authenticated.
 */
function auth(PDO $pdo, string $minRole = 'player', bool $required = true): ?array
{
    $token = $_SERVER['HTTP_X_TOKEN']
          ?? ($_GET['token']  ?? null)
          ?? ($_POST['token'] ?? null);

    if (!$token) {
        if ($required) respond(401, false, 'Authentication required');
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.* FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token = :token
           AND s.expires_at > NOW()
           AND u.is_banned = 0
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($required) respond(401, false, 'Invalid or expired session. Please log in again.');
        return null;
    }

    $hierarchy = ['player' => 0, 'moderator' => 1, 'admin' => 2, 'superadmin' => 3];
    $userRank  = $hierarchy[$user['role']]  ?? 0;
    $minRank   = $hierarchy[$minRole]       ?? 0;

    if ($userRank < $minRank) {
        if ($required) respond(403, false, 'Insufficient permissions. Required: ' . $minRole);
        return null;
    }

    return $user;
}

/**
 * Write to activity_log.
 */
function log_action(PDO $pdo, ?int $userId, string $username, string $action, string $details = ''): void
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare(
        'INSERT INTO activity_log (user_id, username, action, details, ip_address)
         VALUES (:uid, :uname, :action, :details, :ip)'
    )->execute([':uid' => $userId, ':uname' => $username, ':action' => $action, ':details' => $details, ':ip' => $ip]);
}

/**
 * Get sanitized body (supports both JSON and form-data POST).
 */
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $json = $raw ? (json_decode($raw, true) ?? []) : [];
    $body = array_merge($_POST, $json);   // JSON overrides form fields
}

$action = $_GET['action'] ?? $body['action'] ?? '';

// ── Routes ──────────────────────────────────────────────────
switch ($action) {

    // ══════════════════════════════════════════════════════════
    // AUTH
    // ══════════════════════════════════════════════════════════

    case 'login': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');

        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if (!$username || !$password) respond(400, false, 'اسم المستخدم وكلمة السر مطلوبان');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(401, false, 'اسم المستخدم أو كلمة السر غلط');
        }

        if ($user['is_banned']) {
            respond(403, false, 'حسابك محظور: ' . ($user['ban_reason'] ?: 'تواصل مع الإدارة'));
        }

        // Create session token
        $token   = bin2hex(random_bytes(32));
        $days    = (int) ($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='session_days'")->fetchColumn() ?: 30);
        $expires = date('Y-m-d H:i:s', time() + $days * 86400);
        $ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $pdo->prepare(
            'INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
             VALUES (:uid, :token, :ip, :ua, :exp)'
        )->execute([':uid' => $user['id'], ':token' => $token, ':ip' => $ip, ':ua' => $ua, ':exp' => $expires]);

        // Update last login
        $pdo->prepare('UPDATE users SET last_login = NOW(), last_ip = :ip WHERE id = :id')
            ->execute([':ip' => $ip, ':id' => $user['id']]);

        // Fetch skills
        $skills = $pdo->prepare('SELECT skill_name, skill_value FROM player_skills WHERE user_id = :uid ORDER BY skill_value DESC');
        $skills->execute([':uid' => $user['id']]);

        log_action($pdo, $user['id'], $user['username'], 'login', 'تسجيل دخول ناجح');

        $safeUser = array_diff_key($user, array_flip(['password_hash']));
        respond(200, true, 'تم تسجيل الدخول بنجاح', [
            'token'      => $token,
            'user'       => $safeUser,
            'skills'     => $skills->fetchAll(),
            'expires_at' => $expires,
        ]);
    }

    case 'register': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');

        $regOpen = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='registration_open'")->fetchColumn();
        if (!(int) $regOpen) respond(403, false, 'التسجيل مغلق حالياً. تواصل مع الإدارة.');

        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');
        $minLen   = (int) ($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='min_password_len'")->fetchColumn() ?: 6);

        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) respond(400, false, 'اسم المستخدم يجب أن يكون بين 3 و50 حرف');
        if (!preg_match('/^[\w\-\.]+$/u', $username))               respond(400, false, 'اسم المستخدم يحتوي على رموز غير مسموح بها');
        if (mb_strlen($password) < $minLen)                         respond(400, false, "كلمة السر يجب أن تكون {$minLen} أحرف على الأقل");

        $exists = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $exists->execute([':u' => $username]);
        if ($exists->fetch()) respond(409, false, 'اسم المستخدم مستخدم بالفعل');

        $hash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $avatars = ['🦊','🎮','🔫','🏎️','🦁','🐺','🦅','⚡','🌟','💎'];
        $avatar  = $avatars[array_rand($avatars)];

        $pdo->prepare('INSERT INTO users (username, password_hash, avatar_emoji) VALUES (:u, :h, :a)')
            ->execute([':u' => $username, ':h' => $hash, ':a' => $avatar]);

        $userId = (int) $pdo->lastInsertId();

        // Default skills
        $defaultSkills = ['قيادة', 'قتال', 'تمثيل', 'تفاوض', 'ميكانيكا'];
        $skillStmt     = $pdo->prepare('INSERT INTO player_skills (user_id, skill_name, skill_value) VALUES (:uid, :s, :v)');
        foreach ($defaultSkills as $s) {
            $skillStmt->execute([':uid' => $userId, ':s' => $s, ':v' => rand(5, 25)]);
        }

        log_action($pdo, $userId, $username, 'register', 'تسجيل حساب جديد');
        respond(201, true, 'تم إنشاء الحساب بنجاح — يمكنك الآن تسجيل الدخول');
    }

    case 'verify': {
        $user   = auth($pdo, 'player', true);
        $skills = $pdo->prepare('SELECT skill_name, skill_value FROM player_skills WHERE user_id = :uid ORDER BY skill_value DESC');
        $skills->execute([':uid' => $user['id']]);
        respond(200, true, 'Token valid', [
            'user'   => array_diff_key($user, array_flip(['password_hash'])),
            'skills' => $skills->fetchAll(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // PROFILE
    // ══════════════════════════════════════════════════════════

    case 'get_profile': {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if (!$userId) {
            $me     = auth($pdo, 'player', true);
            $userId = $me['id'];
        }

        $stmt = $pdo->prepare(
            'SELECT id,username,role,avatar_emoji,level,score,money,warnings,
                    faction,gang,rank_title,bio,created_at,last_login
             FROM users WHERE id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $profile = $stmt->fetch();
        if (!$profile) respond(404, false, 'المستخدم غير موجود');

        $skills = $pdo->prepare('SELECT skill_name, skill_value FROM player_skills WHERE user_id = :uid ORDER BY skill_value DESC');
        $skills->execute([':uid' => $userId]);

        $logs = $pdo->prepare('SELECT action, details, created_at FROM activity_log WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20');
        $logs->execute([':uid' => $userId]);

        respond(200, true, '', [
            'profile'  => $profile,
            'skills'   => $skills->fetchAll(),
            'activity' => $logs->fetchAll(),
        ]);
    }

    case 'update_profile': {
        $user    = auth($pdo, 'player', true);
        $allowed = ['bio', 'avatar_emoji', 'faction', 'gang'];
        $sets    = [];
        $params  = [':id' => $user['id']];

        foreach ($allowed as $f) {
            if (isset($body[$f])) {
                $sets[]       = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (!$sets) respond(400, false, 'لا توجد بيانات للتحديث');

        $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        respond(200, true, 'تم تحديث الملف الشخصي بنجاح');
    }

    // ══════════════════════════════════════════════════════════
    // COMMANDS
    // ══════════════════════════════════════════════════════════

    case 'get_commands': {
        $cat = $_GET['category'] ?? null;
        if ($cat) {
            $stmt = $pdo->prepare('SELECT * FROM commands WHERE category = :cat AND is_active = 1 ORDER BY sort_order, id');
            $stmt->execute([':cat' => $cat]);
        } else {
            $stmt = $pdo->query('SELECT * FROM commands WHERE is_active = 1 ORDER BY category, sort_order, id');
        }
        respond(200, true, '', ['commands' => $stmt->fetchAll()]);
    }

    case 'add_command': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);

        foreach (['category', 'command_code', 'label'] as $req) {
            if (empty($body[$req])) respond(400, false, "الحقل «{$req}» مطلوب");
        }

        $pdo->prepare(
            'INSERT INTO commands (category, sub_category, command_code, label, description, requires_role, sort_order, added_by)
             VALUES (:cat, :sub, :code, :label, :desc, :role, :sort, :by)'
        )->execute([
            ':cat'   => $body['category'],
            ':sub'   => $body['sub_category'] ?? null,
            ':code'  => $body['command_code'],
            ':label' => $body['label'],
            ':desc'  => $body['description']  ?? null,
            ':role'  => $body['requires_role'] ?? 'player',
            ':sort'  => (int) ($body['sort_order'] ?? 0),
            ':by'    => $actor['id'],
        ]);

        $newId = $pdo->lastInsertId();
        log_action($pdo, $actor['id'], $actor['username'], 'add_command', 'أضاف: ' . $body['command_code']);
        respond(201, true, 'تم إضافة الأمر بنجاح', ['id' => $newId]);
    }

    case 'edit_command': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);
        $id    = (int) ($body['id'] ?? 0);
        if (!$id) respond(400, false, 'id مطلوب');

        $allowed = ['category', 'sub_category', 'command_code', 'label', 'description', 'requires_role', 'sort_order', 'is_active'];
        $sets    = [];
        $params  = [':id' => $id];
        foreach ($allowed as $f) {
            if (isset($body[$f])) { $sets[] = "$f = :$f"; $params[":$f"] = $body[$f]; }
        }
        if (!$sets) respond(400, false, 'لا توجد بيانات للتحديث');

        $pdo->prepare('UPDATE commands SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        log_action($pdo, $actor['id'], $actor['username'], 'edit_command', "عدّل الأمر #{$id}");
        respond(200, true, 'تم تعديل الأمر بنجاح');
    }

    case 'delete_command': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);
        $id    = (int) ($body['id'] ?? 0);
        if (!$id) respond(400, false, 'id مطلوب');

        $pdo->prepare('DELETE FROM commands WHERE id = :id')->execute([':id' => $id]);
        log_action($pdo, $actor['id'], $actor['username'], 'delete_command', "حذف الأمر #{$id}");
        respond(200, true, 'تم حذف الأمر بنجاح');
    }

    // ══════════════════════════════════════════════════════════
    // USERS MANAGEMENT (admin+)
    // ══════════════════════════════════════════════════════════

    case 'get_users': {
        auth($pdo, 'admin', true);
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $search = '%' . trim($_GET['search'] ?? '') . '%';

        $stmt = $pdo->prepare(
            'SELECT id,username,role,avatar_emoji,level,score,warnings,is_banned,ban_reason,
                    faction,gang,last_login,created_at
             FROM users WHERE username LIKE :s ORDER BY created_at DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':s',   $search,  PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE :s');
        $cntStmt->execute([':s' => $search]);

        respond(200, true, '', [
            'users'    => $stmt->fetchAll(),
            'total'    => (int) $cntStmt->fetchColumn(),
            'page'     => $page,
            'per_page' => $limit,
        ]);
    }

    case 'update_user': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);
        $id    = (int) ($body['id'] ?? 0);
        if (!$id)               respond(400, false, 'id مطلوب');
        if ($id === $actor['id']) respond(400, false, 'لا يمكنك تعديل حسابك الخاص من هنا');

        // Protect superadmin from admin edits
        $targetRole = $pdo->prepare('SELECT role FROM users WHERE id = :id');
        $targetRole->execute([':id' => $id]);
        $tRole = $targetRole->fetchColumn();
        if ($tRole === 'superadmin' && $actor['role'] !== 'superadmin') {
            respond(403, false, 'لا يمكنك تعديل حساب سوبر أدمن');
        }

        $allowed = ['role', 'is_banned', 'ban_reason', 'warnings', 'level', 'score', 'money', 'rank_title', 'faction', 'gang'];
        $sets    = [];
        $params  = [':id' => $id];
        foreach ($allowed as $f) {
            if (isset($body[$f])) { $sets[] = "$f = :$f"; $params[":$f"] = $body[$f]; }
        }
        if (!$sets) respond(400, false, 'لا توجد بيانات للتحديث');

        $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        log_action($pdo, $actor['id'], $actor['username'], 'update_user', "عدّل المستخدم #{$id}");
        respond(200, true, 'تم تحديث المستخدم بنجاح');
    }

    // ══════════════════════════════════════════════════════════
    // PAGE SECTIONS
    // ══════════════════════════════════════════════════════════

    case 'get_sections': {
        $page = $_GET['page'] ?? null;
        if ($page) {
            $stmt = $pdo->prepare('SELECT * FROM page_sections WHERE page = :p AND is_active = 1');
            $stmt->execute([':p' => $page]);
        } else {
            $stmt = $pdo->query('SELECT * FROM page_sections WHERE is_active = 1 ORDER BY page');
        }
        respond(200, true, '', ['sections' => $stmt->fetchAll()]);
    }

    case 'update_section': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);
        $key   = trim($body['section_key'] ?? '');
        if (!$key) respond(400, false, 'section_key مطلوب');

        $pdo->prepare(
            'INSERT INTO page_sections (section_key, section_title, content, content_type, page, updated_by)
             VALUES (:key, :title, :content, :type, :page, :by)
             ON DUPLICATE KEY UPDATE
               section_title = VALUES(section_title),
               content       = VALUES(content),
               content_type  = VALUES(content_type),
               updated_by    = VALUES(updated_by)'
        )->execute([
            ':key'     => $key,
            ':title'   => $body['section_title']  ?? '',
            ':content' => $body['content']         ?? '',
            ':type'    => $body['content_type']    ?? 'text',
            ':page'    => $body['page']             ?? 'home',
            ':by'      => $actor['id'],
        ]);

        log_action($pdo, $actor['id'], $actor['username'], 'update_section', "تحديث القسم: {$key}");
        respond(200, true, 'تم حفظ المحتوى بنجاح');
    }

    // ══════════════════════════════════════════════════════════
    // SITE SETTINGS (superadmin only)
    // ══════════════════════════════════════════════════════════

    case 'get_settings': {
        auth($pdo, 'superadmin', true);
        $rows = $pdo->query('SELECT * FROM site_settings ORDER BY setting_key')->fetchAll();
        respond(200, true, '', ['settings' => $rows]);
    }

    case 'update_setting': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'superadmin', true);
        $key   = trim($body['key'] ?? '');
        $val   = $body['value'] ?? '';
        if (!$key) respond(400, false, 'key مطلوب');

        $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value)
             VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2'
        )->execute([':k' => $key, ':v' => $val, ':v2' => $val]);

        log_action($pdo, $actor['id'], $actor['username'], 'update_setting', "الإعداد: {$key} = {$val}");
        respond(200, true, 'تم حفظ الإعداد بنجاح');
    }

    // ══════════════════════════════════════════════════════════
    // ANNOUNCEMENTS
    // ══════════════════════════════════════════════════════════

    case 'get_announcements': {
        $stmt = $pdo->query(
            'SELECT * FROM announcements WHERE is_active = 1
             ORDER BY is_pinned DESC, created_at DESC LIMIT 20'
        );
        respond(200, true, '', ['announcements' => $stmt->fetchAll()]);
    }

    case 'add_announcement': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);

        if (empty($body['title']) || empty($body['body'])) respond(400, false, 'العنوان والمحتوى مطلوبان');

        $pdo->prepare(
            'INSERT INTO announcements (title, body, type, is_pinned, created_by)
             VALUES (:title, :body, :type, :pin, :by)'
        )->execute([
            ':title' => $body['title'],
            ':body'  => $body['body'],
            ':type'  => $body['type']      ?? 'info',
            ':pin'   => (int) ($body['is_pinned'] ?? 0),
            ':by'    => $actor['id'],
        ]);

        log_action($pdo, $actor['id'], $actor['username'], 'add_announcement', $body['title']);
        respond(201, true, 'تم نشر الإعلان بنجاح');
    }

    case 'delete_announcement': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor = auth($pdo, 'admin', true);
        $id    = (int) ($body['id'] ?? 0);
        if (!$id) respond(400, false, 'id مطلوب');

        $pdo->prepare('UPDATE announcements SET is_active = 0 WHERE id = :id')->execute([':id' => $id]);
        log_action($pdo, $actor['id'], $actor['username'], 'delete_announcement', "حذف الإعلان #{$id}");
        respond(200, true, 'تم حذف الإعلان');
    }

    // ══════════════════════════════════════════════════════════
    // SKILLS
    // ══════════════════════════════════════════════════════════

    case 'update_skill': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'POST required');
        $actor  = auth($pdo, 'admin', true);
        $userId = (int) ($body['user_id']     ?? 0);
        $skill  = trim($body['skill_name']    ?? '');
        $value  = max(0, min(100, (int) ($body['skill_value'] ?? 0)));

        if (!$userId || !$skill) respond(400, false, 'user_id و skill_name مطلوبان');

        $pdo->prepare(
            'INSERT INTO player_skills (user_id, skill_name, skill_value)
             VALUES (:uid, :s, :v)
             ON DUPLICATE KEY UPDATE skill_value = :v2'
        )->execute([':uid' => $userId, ':s' => $skill, ':v' => $value, ':v2' => $value]);

        log_action($pdo, $actor['id'], $actor['username'], 'update_skill', "{$skill} → {$value} for user #{$userId}");
        respond(200, true, 'تم تحديث المهارة');
    }

    // ══════════════════════════════════════════════════════════
    // ACTIVITY LOG (admin+)
    // ══════════════════════════════════════════════════════════

    case 'get_activity_log': {
        auth($pdo, 'admin', true);
        $limit = min(200, max(10, (int) ($_GET['limit'] ?? 50)));
        $stmt  = $pdo->prepare('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        respond(200, true, '', ['logs' => $stmt->fetchAll()]);
    }

    // ══════════════════════════════════════════════════════════
    // FALLBACK
    // ══════════════════════════════════════════════════════════

    default:
        respond(404, false, 'Unknown action: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'));
}

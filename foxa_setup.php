<?php
/**
 * ============================================================
 * FOXA FAMILY — First-Time Setup Script
 * File : setup.php
 *
 * PURPOSE: Creates the first Superadmin account.
 *
 * INSTRUCTIONS:
 *  1. Upload this file to public_html/ alongside api.php
 *  2. Visit: https://YOUR_SITE.lemehost.com/setup.php
 *  3. Enter the setup secret key (default: FOXA_SETUP_2025)
 *  4. Create your superadmin account
 *  5. !! DELETE THIS FILE immediately after !!
 *
 * WARNING: Delete this file after use!
 *          Anyone with the URL can create admin accounts.
 * ============================================================
 */

define('FOXA_API', true);
require_once __DIR__ . '/config.php';

// ── Change this secret before uploading! ────────────────────
const SETUP_SECRET = 'FOXA_SETUP_2025';

$done  = false;
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret   = trim($_POST['secret']   ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if ($secret !== SETUP_SECRET) {
        $error = '❌ رمز الإعداد غير صحيح';
    } elseif (mb_strlen($username) < 3) {
        $error = '❌ اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
    } elseif (mb_strlen($password) < 8) {
        $error = '❌ كلمة السر يجب أن تكون 8 أحرف على الأقل';
    } elseif ($password !== $confirm) {
        $error = '❌ كلمتا السر غير متطابقتين';
    } else {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Check if superadmin already exists
            $count = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "superadmin"')->fetchColumn();
            if ((int) $count > 0) {
                $error = '⚠️ يوجد حساب سوبر أدمن بالفعل. احذف هذا الملف فوراً!';
            } else {
                $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare(
                    'INSERT INTO users (username, password_hash, role, avatar_emoji, level, score, rank_title)
                     VALUES (:u, :h, "superadmin", "👑", 99, 99999, "المطوّر")'
                )->execute([':u' => $username, ':h' => $hash]);

                $userId = (int) $pdo->lastInsertId();

                // Max skills for superadmin
                $skills = ['قيادة' => 100, 'قتال' => 100, 'تمثيل' => 100, 'تفاوض' => 100, 'ميكانيكا' => 100];
                $s      = $pdo->prepare('INSERT INTO player_skills (user_id, skill_name, skill_value) VALUES (:uid, :s, :v)');
                foreach ($skills as $name => $val) $s->execute([':uid' => $userId, ':s' => $name, ':v' => $val]);

                // Log
                $pdo->prepare('INSERT INTO activity_log (user_id, username, action, details, ip_address) VALUES (:uid, :u, "setup", "إنشاء حساب سوبر أدمن", :ip)')
                    ->execute([':uid' => $userId, ':u' => $username, ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

                $info = "✅ تم إنشاء حساب السوبر أدمن «{$username}» بنجاح!";
                $done = true;
            }
        } catch (PDOException $e) {
            $error = '❌ خطأ في قاعدة البيانات: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FOXA FAMILY — Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Russo+One&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{
    font-family:'Cairo',sans-serif;
    background:#04040a;color:#d8d8ee;
    display:flex;align-items:center;justify-content:center;
    min-height:100vh;padding:20px;
    background-image:radial-gradient(ellipse at 50% 0%,rgba(255,100,0,0.07),transparent 60%);
  }
  .box{
    background:rgba(255,153,0,0.04);
    border:1px solid rgba(255,153,0,0.25);
    border-radius:20px;padding:48px 40px;
    max-width:460px;width:100%;
    box-shadow:0 0 60px rgba(255,100,0,0.1);
  }
  h1{font-family:'Russo One',sans-serif;font-size:26px;letter-spacing:4px;text-align:center;margin-bottom:6px;
    background:linear-gradient(90deg,#FF9900,#ffcc44);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
  .sub{font-size:12px;color:#7070a0;text-align:center;letter-spacing:2px;margin-bottom:32px}
  label{display:block;font-size:12px;font-weight:700;color:#FF9900;letter-spacing:2px;margin-bottom:7px}
  input{
    width:100%;padding:11px 15px;border-radius:10px;
    background:rgba(255,153,0,0.05);border:1px solid rgba(255,153,0,0.2);
    color:#d8d8ee;font-family:'Cairo',sans-serif;font-size:14px;
    outline:none;transition:border-color .25s;margin-bottom:18px;
  }
  input:focus{border-color:#FF9900}
  button{
    width:100%;padding:13px;border-radius:12px;
    background:linear-gradient(135deg,#FF9900,#ff6a00);
    color:#fff;font-family:'Cairo',sans-serif;font-size:15px;font-weight:700;
    border:none;cursor:pointer;transition:all .3s;
    box-shadow:0 6px 25px rgba(255,100,0,0.3);
  }
  button:hover{transform:translateY(-2px);box-shadow:0 10px 35px rgba(255,100,0,0.5)}
  .alert{padding:13px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.7}
  .alert-err{background:rgba(255,50,50,0.08);border:1px solid rgba(255,50,50,0.25);color:#ff6666}
  .alert-ok{background:rgba(58,255,136,0.08);border:1px solid rgba(58,255,136,0.25);color:#3aff88}
  .warn-box{
    margin-top:24px;padding:14px;border-radius:10px;
    background:rgba(255,50,50,0.07);border:1px solid rgba(255,50,50,0.2);
    font-size:12px;color:#ff8888;line-height:1.8;text-align:center;
  }
  code{background:rgba(255,153,0,0.12);padding:2px 8px;border-radius:6px;font-size:12px;color:#FF9900}
  hr{border:none;border-top:1px solid rgba(255,153,0,0.1);margin:24px 0}
</style>
</head>
<body>
<div class="box">
  <h1>🦊 FOXA FAMILY</h1>
  <div class="sub">FIRST TIME SETUP — سيتاب أول مرة</div>
  <hr>

  <?php if ($error): ?>
    <div class="alert alert-err"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($info): ?>
    <div class="alert alert-ok"><?= $info ?></div>
    <div class="warn-box">
      🚨 <strong>احذف هذا الملف الآن!</strong><br>
      اذهب إلى cPanel → File Manager → public_html<br>
      واحذف الملف <code>setup.php</code> فوراً!<br><br>
      ثم توجّه إلى موقعك وسجّل الدخول بحساب: <code><?= htmlspecialchars($_POST['username'] ?? '') ?></code>
    </div>
  <?php elseif (!$done): ?>

    <p style="font-size:13px;color:#7070a0;margin-bottom:20px;line-height:1.8">
      هذا الملف ينشئ حساب السوبر أدمن الأول.<br>
      <strong style="color:#FF9900">استخدمه مرة واحدة فقط ثم احذفه!</strong>
    </p>

    <form method="POST" autocomplete="off">
      <label>🔑 رمز الإعداد (Setup Secret)</label>
      <input type="password" name="secret" placeholder="أدخل رمز الإعداد" required>

      <label>👤 اسم مستخدم السوبر أدمن</label>
      <input type="text" name="username" placeholder="مثال: FOXA_BOSS" required autocomplete="new-password">

      <label>🔒 كلمة السر (8 أحرف على الأقل)</label>
      <input type="password" name="password" placeholder="كلمة سر قوية" required autocomplete="new-password">

      <label>🔒 تأكيد كلمة السر</label>
      <input type="password" name="confirm" placeholder="أعد كتابة كلمة السر" required>

      <button type="submit">🚀 إنشاء حساب السوبر أدمن</button>
    </form>

    <div style="margin-top:20px;font-size:11px;color:#555;text-align:center">
      رمز الإعداد الافتراضي: <code>FOXA_SETUP_2025</code><br>
      غيّره في السطر: <code>const SETUP_SECRET = '...';</code> قبل الرفع
    </div>

  <?php endif; ?>
</div>
</body>
</html>

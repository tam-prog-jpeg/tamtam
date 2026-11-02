<?php
/**
 * database/emergency_reset_admin.php
 * -----------------------------------------------------------------
 * EMERGENCY USE ONLY. Lets you set a brand-new password for any
 * existing account by username — without needing to be logged in
 * at all. Use this if you're ever locked out of every account.
 *
 * DELETE THIS FILE immediately after you're done. Anyone who can
 * reach this URL can reset any account's password, so it must never
 * be left sitting on a live/public server.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '') {
        $message = 'Please enter a username.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirm) {
        $message = 'New password and confirmation do not match.';
        $messageType = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user) {
            $message = "No account found with username \"$username\".";
            $messageType = 'error';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, status = 'active' WHERE username = ?");
            mysqli_stmt_bind_param($upd, 'ss', $hash, $username);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $message = "Password for \"$username\" has been reset successfully. You can now log in with the new password. Please delete this file now.";
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; background:#0E2A52; min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center; }
        .box { background:#fff; border-radius:12px; padding:32px; width:380px; max-width:90vw; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        h2 { color:#0E2A52; margin-top:0; }
        .warn { background:#FDF1DF; color:#C97A0E; padding:10px 12px; border-radius:8px; font-size:12.5px; margin-bottom:18px; }
        label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#0E2A52; }
        input { width:100%; padding:9px 10px; margin-bottom:14px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-size:13.5px; }
        button { width:100%; padding:10px; background:#1D5FB8; color:#fff; border:none; border-radius:6px; font-size:14px; cursor:pointer; font-weight:600; }
        .msg { padding:10px 12px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .msg.success { background:#E7F6EE; color:#1E8E5A; }
        .msg.error { background:#FCE9E9; color:#D64545; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔑 Emergency Password Reset</h2>
        <div class="warn">This tool works without logging in. Delete this file as soon as you're done.</div>

        <?php if ($message): ?>
            <div class="msg <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" value="admin" required>

            <label>New Password</label>
            <input type="text" name="new_password" minlength="8" required placeholder="At least 8 characters">

            <label>Confirm New Password</label>
            <input type="text" name="confirm_password" minlength="8" required>

            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>
</html>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$message = '';
$message_type = '';
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['emf'] ?? '';
    $username = $_POST['unf'] ?? '';
    $pw1 = $_POST['pwf1'] ?? '';
    $pw2 = $_POST['pwf2'] ?? '';

    if ($pw1 !== $pw2) {
        $message = "パスワードが一致しません。";
        $message_type = "error";
    } elseif (!empty($email) && !empty($username) && !empty($pw1)) {
        $dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');
        
        // 既存ユーザーチェック
        $sql = "SELECT * FROM users WHERE email = $1";
        $result = pg_query_params($dbconn, $sql, array($email));

        if (pg_num_rows($result) == 0) {
            $hashed_pw = password_hash($pw1, PASSWORD_BCRYPT);
            $sql_ins = "INSERT INTO users (username, email, password_hash) VALUES ($1, $2, $3)";
            $res_ins = pg_query_params($dbconn, $sql_ins, array($username, $email, $hashed_pw));
            
            if ($res_ins) {
                $message = "ユーザー登録が完了しました！";
                $message_type = "success";
                $show_form = false;
            } else {
                $message = "登録に失敗しました: " . pg_last_error($dbconn);
                $message_type = "error";
            }
        } else {
            $message = "そのメールアドレスは既に登録されています。";
            $message_type = "error";
        }
    } else {
        $message = "すべての項目を入力してください。";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - Money Partner (マネ・パト)</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="register-page">
    <div class="register-container">
        <div class="logo">
            <div class="logo-icon">💰</div>
            <div class="logo-text">Money Partner (マネ・パト)</div>
        </div>
        
        <h2>新規会員登録</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_form): ?>
            <form method="post">
                <div class="form-group">
                    <label for="unf">ユーザー名</label>
                    <input type="text" id="unf" name="unf" placeholder="お名前" required>
                </div>
                
                <div class="form-group">
                    <label for="emf">メールアドレス</label>
                    <input type="email" id="emf" name="emf" placeholder="example@mail.com" required>
                </div>
                
                <div class="form-group">
                    <label for="pwf1">パスワード</label>
                    <input type="password" id="pwf1" name="pwf1" placeholder="••••••••" required>
                </div>
                
                <div class="form-group">
                    <label for="pwf2">パスワード（確認）</label>
                    <input type="password" id="pwf2" name="pwf2" placeholder="••••••••" required>
                </div>
                
                <button type="submit">アカウントを作成</button>
            </form>
        <?php else: ?>
            <div style="text-align: center;">
                <a href="login.php" style="display:inline-block; margin-top:1rem; color:#667eea; font-weight:600; text-decoration:none;">ログイン画面へ移動する</a>
            </div>
        <?php endif; ?>
        
        <div class="login-link">
            既にアカウントをお持ちの方は <a href="login.php">ログイン</a>
        </div>
    </div>
</body>
</html>
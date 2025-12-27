<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (isset($_POST['login'])) {
    $email = $_POST['email']; // form name="email"
    $password = $_POST['password']; // form name="password"
    
    $dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');
    
    // ゼミのサーバーのカラム名（user_id, email, password_hash）に合わせる
    $sql = "SELECT user_id, email, password_hash FROM users WHERE email = $1";
    $result = pg_query_params($dbconn, $sql, array($email));
    
    if (!$result) {
        $error = "データベースエラー: " . pg_last_error($dbconn);
    } else {
        if ($row = pg_fetch_assoc($result)) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['ems'] = $row['email'];
                header('Location: index.php');
                exit();
            } else {
                $error = "パスワードが正しくありません";
            }
        } else {
            $error = "ユーザーが見つかりません";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - Money Partner (マネ・パト)</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">💰</div>
            <div class="logo-text">Money Partner (マネ・パト)</div>
        </div>
        
        <h2>ログイン</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login">ログイン</button>
        </form>
        
        <div class="register-link">
            アカウントをお持ちでない方は <a href="register.php">新規登録</a>
        </div>
    </div>
</body>
</html>
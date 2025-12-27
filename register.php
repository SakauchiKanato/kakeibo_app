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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            font-size: 4rem;
            margin-bottom: 0.5rem;
        }
        
        .logo-text {
            font-size: 1.75rem;
            font-weight: 700;
            color: #667eea;
        }
        
        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 2rem;
            font-size: 1.5rem;
        }
        
        .message {
            padding: 0.875rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
        }
        
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .success {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9375rem;
        }
        
        input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Noto Sans JP', sans-serif;
            transition: all 0.2s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Noto Sans JP', sans-serif;
            margin-top: 1rem;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #7f8c8d;
            font-size: 0.9375rem;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
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
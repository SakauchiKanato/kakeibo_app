<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - 家計簿AIアドバイザー</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh; /* 長くなっても大丈夫なようにmin-height */
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .register-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h2 { margin-bottom: 25px; color: #2c3e50; }

        .input-group { text-align: left; margin-bottom: 15px; }

        label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: #666; font-weight: bold; }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        input:focus { outline: none; border-color: #667eea; }

        input[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(118, 75, 162, 0.3);
        }

        /* メッセージ表示用のスタイル */
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        p { margin-top: 20px; font-size: 0.85rem; color: #777; }
        a { color: #667eea; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="register-card">
    <h2>Create Account</h2>

    <?php
    $show_form = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $emf = $_POST['emf'] ?? '';
        $unf = $_POST['unf'] ?? '';
        $pwf1 = $_POST['pwf1'] ?? '';
        $pwf2 = $_POST['pwf2'] ?? '';

        if ($pwf1 !== $pwf2) {
            echo "<div class='message error'>パスワードが一致しません。</div>";
        } elseif (!empty($emf) && !empty($unf) && !empty($pwf1)) {
            $dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
                or die('接続失敗');
            
            $sql = "SELECT * FROM users WHERE email=$1";
            $result = pg_query_params($dbconn, $sql, array($emf));

            if (pg_num_rows($result) == 0) {
                $npwh = password_hash($pwf1, PASSWORD_BCRYPT);
                $sql_ins = "INSERT INTO users(username, email, password_hash) VALUES ($1, $2, $3)";
                pg_query_params($dbconn, $sql_ins, array($unf, $emf, $npwh));
                
                echo "<div class='message success'>ユーザ登録を完了しました！</div>";
                echo "<a href='./login.php' style='display:block; margin-top:10px;'>ログイン画面へ</a>";
                $show_form = false; // 登録完了時はフォームを隠す
            } else {
                echo "<div class='message error'>そのメールアドレスは既に登録されています。</div>";
            }
        }
    }
    ?>

    <?php if ($show_form): ?>
    <form method="POST" action="./register.php">
        <div class="input-group">
            <label>ユーザ名</label>
            <input type="text" name="unf" placeholder="お名前" required>
        </div>
        <div class="input-group">
            <label>メールアドレス</label>
            <input type="text" name="emf" placeholder="example@mail.com" required>
        </div>
        <div class="input-group">
            <label>パスワード</label>
            <input type="password" name="pwf1" placeholder="••••••••" required>
        </div>
        <div class="input-group">
            <label>パスワード（確認）</label>
            <input type="password" name="pwf2" placeholder="••••••••" required>
        </div>
        <input type="submit" value="登録する">
    </form>
    <p>アカウントをお持ちの方は <a href="./login.php">ログイン</a></p>
    <?php endif; ?>
</div>

</body>
</html>
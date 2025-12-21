<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 家計簿AIアドバイザー</title>
    <style>
        /* メイン画面と共通の基本スタイル */
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .login-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }

        h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .input-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #666;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box; /* 幅を100%に収める */
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

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
            transition: transform 0.2s, opacity 0.2s;
        }

        input[type="submit"]:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        p {
            margin-top: 25px;
            font-size: 0.85rem;
            color: #777;
        }

        a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<form method="POST" action="./login_action.php">

    <?php if(isset($_GET['error'])): ?>
        <p style="color: #e74c3c; font-weight: bold;">メールアドレスかパスワードが違います</p>
    <?php endif; ?>
email:<input type="text" name="emf" size="40"><br>
pw:<input type="password" name="pwf" size="40"><br>
<input type="submit">
</form>
<p>*はじめての方は<a href="./register.php">こちら</a>から登録してください。</p>
</body>
</html>
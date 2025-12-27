<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗');

// 現在の予算設定を取得
$sql = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res = pg_query_params($dbconn, $sql, array($_SESSION['user_id']));
$row = pg_fetch_row($res);
$current_limit = $row[0] ?? 30000; // 現在の設定がない場合は30,000を表示
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予算設定 - Money Partner (マネ・パト)</title>
    <style>
        body { font-family: sans-serif; background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 350px; text-align: center; }
        h2 { color: #2c3e50; margin-bottom: 20px; }
        input[type="number"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; font-size: 1.2rem; text-align: center; margin-bottom: 20px; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; }
        .back-link { display: block; margin-top: 20px; color: #777; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="card">
    <h2>月の予算設定</h2>
    <p style="font-size: 0.9rem; color: #666;">1ヶ月に使える合計金額を<br>入力してください。</p>
    
    <form action="settings_action.php" method="post">
        <input type="number" name="monthly_limit" value="<?php echo htmlspecialchars($current_limit); ?>" required>
        <button type="submit">設定を保存する</button>
    </form>

    <a href="index.php" class="back-link">キャンセルして戻る</a>
</div>

</body>
</html>
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
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="settings-page">

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
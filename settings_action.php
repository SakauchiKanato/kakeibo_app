<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗');

$user_id = $_SESSION['user_id'];
$monthly_limit = $_POST['monthly_limit'];

if (isset($monthly_limit)) {
    // 既存の設定があるか確認
    $sql_check = "SELECT 1 FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
    $res_check = pg_query_params($dbconn, $sql_check, array($user_id));

    if (pg_num_rows($res_check) > 0) {
        // 更新 (UPDATE)
        $sql = "UPDATE budget_settings SET setting_value = $1 WHERE user_id = $2 AND setting_key = 'monthly_limit'";
        pg_query_params($dbconn, $sql, array($monthly_limit, $user_id));
    } else {
        // 新規作成 (INSERT)
        $sql = "INSERT INTO budget_settings (user_id, setting_key, setting_value) VALUES ($1, 'monthly_limit', $2)";
        pg_query_params($dbconn, $sql, array($user_id, $monthly_limit));
    }
}

header("Location: index.php");
exit();
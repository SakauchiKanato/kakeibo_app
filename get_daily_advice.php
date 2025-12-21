<?php
session_start();
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP");

$user_id = $_SESSION['user_id'];

// 1. 今日の全支出を取得
$sql = "SELECT description, amount, satisfaction FROM transactions 
        WHERE user_id = $1 AND date(created_at) = current_date";
$res = pg_query_params($dbconn, $sql, array($user_id));

$items = [];
$total_spent = 0;
while ($row = pg_fetch_assoc($res)) {
    $items[] = "{$row['description']}({$row['amount']}円,満足度{$row['satisfaction']})";
    $total_spent += $row['amount'];
}

// データがない場合
if (empty($items)) {
    $_SESSION['ai_comment'] = "今日はまだ記録がないようです。";
    header("Location: index.php");
    exit();
}

// 2. Pythonに渡す文字列を作成
$all_items_str = implode(" / ", $items);

// 3. Python呼び出し（引数は まとめた文字列, 合計金額 の2つに簡略化）
$pythonPath = "/usr/bin/python3"; 
$scriptPath = __DIR__ . "/python/ask_ai.py";

$cmd = sprintf(
    '%s %s %s %s 2>&1',
    $pythonPath,
    $scriptPath,
    escapeshellarg($all_items_str),
    escapeshellarg($total_spent)
);

$_SESSION['ai_comment'] = shell_exec($cmd);
header("Location: index.php?slide=0");
exit();
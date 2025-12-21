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

// 3. Python呼び出し
$pythonPath = "/usr/bin/python3"; 
$scriptPath = __DIR__ . "/python/ask_ai.py";

$cmd = sprintf(
    '%s %s %s %s 2>&1',
    $pythonPath,
    $scriptPath,
    escapeshellarg($all_items_str),
    escapeshellarg($total_spent)
);

// AIの回答を取得
$ai_response = shell_exec($cmd);

// --- 4. 【追加】AIの回答をデータベースに保存 ---
if (!empty($ai_response)) {
    $sql_insert = "INSERT INTO ai_advice_history (user_id, advice) VALUES ($1, $2)";
    pg_query_params($dbconn, $sql_insert, array($user_id, $ai_response));
}

// セッションにも念のためセット（今の表示方式を壊さないため）
$_SESSION['ai_comment'] = $ai_response;

header("Location: index.php?slide=0");
exit();
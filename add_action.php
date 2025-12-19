<?php

session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// データベース接続（あなたの環境に合わせてください）
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

// フォームからデータを受け取る
$user_id = $_SESSION['user_id'];
$amount = $_POST['amount'];
$description = $_POST['description'];
$satisfaction = (int)$_POST['satisfaction'];

// SQL実行：データを保存する
$sql = "INSERT INTO transactions (user_id, amount, description, satisfaction) VALUES ($1, $2, $3, $4)";
$result = pg_query_params($dbconn, $sql, array($user_id, $amount, $description, $satisfaction));

if (!$result) {
    // 詳細なエラーを表示するように一時的に変更
    die("保存に失敗しました: " . pg_last_error($dbconn));
}

// 4. AIに渡すための「最新の残り予算」を計算
// (index.phpと同じ計算ロジックをここでも行います)
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($user_id));
$row_sum = pg_fetch_row($res_sum);
$total_spent = $row_sum[0] ?? 0;

$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($user_id));
$row_budget = pg_fetch_row($res_budget);
$monthly_limit = $row_budget[0] ?? 30000;

$remaining_days = (date('t') - date('j') + 1);
$today_budget = floor(($monthly_limit - $total_spent) / $remaining_days);

// 5. Pythonを呼び出してAIコメントを取得
// あなたの環境の仮想環境内のpythonパスを指定してください
$pythonPath = "/usr/bin/python3"; 
$scriptPath = "/home/h0/knt416/public_html/kakeibo_app/python/ask_ai.py";

// シンプルなコマンド
$cmd = sprintf(
    '%s %s %s %s %s %s 2>&1', // 引数を1つ増やしました
    $pythonPath,
    $scriptPath,
    escapeshellarg($amount),
    escapeshellarg($today_budget),
    escapeshellarg($description),
    escapeshellarg($satisfaction) // 満足度を渡す
);

// $ai_comment = shell_exec($cmd);
// $_SESSION['ai_comment'] = $ai_comment;

header("Location: index.php");
exit();
?>
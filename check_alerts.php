<?php
session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['alerts' => []]);
    exit();
}

$user_id = $_SESSION['user_id'];

// データベース接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

$alerts = [];

// 1. 月次予算のチェック
$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($user_id));
$monthly_limit = pg_fetch_row($res_budget)[0] ?? 30000;

$sql_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_spent = pg_query_params($dbconn, $sql_spent, array($user_id));
$total_spent = pg_fetch_row($res_spent)[0] ?? 0;

$usage_percentage = ($total_spent / $monthly_limit) * 100;

// 予算の80%を超えたら警告
if ($usage_percentage >= 80 && $usage_percentage < 100) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => '⚠️',
        'message' => "今月の予算の" . round($usage_percentage) . "%を使用しています。残り" . number_format($monthly_limit - $total_spent) . "円です。"
    ];
} elseif ($usage_percentage >= 100) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => '🚨',
        'message' => "予算を" . number_format($total_spent - $monthly_limit) . "円超過しています！"
    ];
}

// 2. カテゴリー別予算のチェック
$sql_category_budgets = "
    SELECT cb.category_id, cb.monthly_limit, c.name, c.icon, COALESCE(SUM(t.amount), 0) as spent
    FROM category_budgets cb
    JOIN categories c ON cb.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = cb.category_id 
        AND t.user_id = cb.user_id 
        AND date_trunc('month', t.created_at) = date_trunc('month', current_timestamp)
    WHERE cb.user_id = $1
    GROUP BY cb.category_id, cb.monthly_limit, c.name, c.icon
";
$res_cat_budgets = pg_query_params($dbconn, $sql_category_budgets, array($user_id));

while ($row = pg_fetch_assoc($res_cat_budgets)) {
    $cat_usage = ($row['spent'] / $row['monthly_limit']) * 100;
    
    if ($cat_usage >= 80 && $cat_usage < 100) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => $row['icon'],
            'message' => $row['name'] . "の予算" . round($cat_usage) . "%使用中（残り" . number_format($row['monthly_limit'] - $row['spent']) . "円）"
        ];
    } elseif ($cat_usage >= 100) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => $row['icon'],
            'message' => $row['name'] . "の予算を" . number_format($row['spent'] - $row['monthly_limit']) . "円超過！"
        ];
    }
}

// 3. 日次予算のチェック（今日使いすぎていないか）
$total_days = date('t');
$current_day = date('j');
$daily_allowance = $monthly_limit / $total_days;
$cumulative_budget = $daily_allowance * $current_day;
$today_remaining = floor($cumulative_budget - $total_spent);

if ($today_remaining < 0) {
    $alerts[] = [
        'type' => 'info',
        'icon' => '💡',
        'message' => "今日は予算オーバーです。明日から調整しましょう。"
    ];
}

header('Content-Type: application/json');
echo json_encode(['alerts' => $alerts]);
?>

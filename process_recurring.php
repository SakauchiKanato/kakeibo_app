<?php
// 定期支出の自動記録処理
// このスクリプトはcronジョブで毎日実行することを想定

require_once __DIR__ . '/db_connect.php';

// データベース接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

// 今日が次回発生日の定期支出を取得
$sql = "SELECT id, user_id, description, amount, category_id, satisfaction, frequency, next_occurrence
        FROM recurring_expenses
        WHERE is_active = true
        AND next_occurrence <= CURRENT_DATE
        AND (end_date IS NULL OR end_date >= CURRENT_DATE)";

$res = pg_query($dbconn, $sql);

$processed_count = 0;

while ($row = pg_fetch_assoc($res)) {
    // トランザクションに記録
    $sql_insert = "INSERT INTO transactions (user_id, amount, description, satisfaction, category_id, created_at)
                   VALUES ($1, $2, $3, $4, $5, $6)";
    $params = array(
        $row['user_id'],
        $row['amount'],
        $row['description'],
        $row['satisfaction'],
        $row['category_id'],
        $row['next_occurrence'] . ' 00:00:00'
    );
    pg_query_params($dbconn, $sql_insert, $params);
    
    // 次回発生日を更新
    $current_date = new DateTime($row['next_occurrence']);
    switch ($row['frequency']) {
        case 'daily':
            $current_date->modify('+1 day');
            break;
        case 'weekly':
            $current_date->modify('+1 week');
            break;
        case 'monthly':
            $current_date->modify('+1 month');
            break;
        case 'yearly':
            $current_date->modify('+1 year');
            break;
    }
    
    $next_occurrence = $current_date->format('Y-m-d');
    
    $sql_update = "UPDATE recurring_expenses 
                   SET next_occurrence = $1, updated_at = CURRENT_TIMESTAMP
                   WHERE id = $2";
    pg_query_params($dbconn, $sql_update, array($next_occurrence, $row['id']));
    
    $processed_count++;
}

echo "処理完了: {$processed_count}件の定期支出を記録しました。\n";

// cron設定例（毎日午前0時に実行）:
// 0 0 * * * /usr/bin/php /path/to/kakeibo_app/process_recurring.php >> /path/to/logs/recurring.log 2>&1
?>

<?php
session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// データベース接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

$action = $_POST['action'] ?? '';

// 次回発生日を計算する関数
function calculate_next_occurrence($start_date, $frequency) {
    $date = new DateTime($start_date);
    $now = new DateTime();
    
    while ($date < $now) {
        switch ($frequency) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
        }
    }
    
    return $date->format('Y-m-d');
}

// 定期支出の作成
if ($action === 'create') {
    $description = $_POST['description'];
    $amount = (int)$_POST['amount'];
    $category_id = (int)$_POST['category_id'];
    $frequency = $_POST['frequency'];
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $satisfaction = (int)$_POST['satisfaction'];
    
    $next_occurrence = calculate_next_occurrence($start_date, $frequency);
    
    $sql = "INSERT INTO recurring_expenses (user_id, description, amount, category_id, frequency, start_date, end_date, next_occurrence, satisfaction) 
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)";
    $params = array($user_id, $description, $amount, $category_id, $frequency, $start_date, $end_date, $next_occurrence, $satisfaction);
    pg_query_params($dbconn, $sql, $params);
    
    header("Location: recurring.php");
    exit();
}

// 一時停止
if ($action === 'pause') {
    $recurring_id = (int)$_POST['recurring_id'];
    
    $sql = "UPDATE recurring_expenses SET is_active = false, updated_at = CURRENT_TIMESTAMP 
            WHERE id = $1 AND user_id = $2";
    pg_query_params($dbconn, $sql, array($recurring_id, $user_id));
    
    header("Location: recurring.php");
    exit();
}

// 再開
if ($action === 'resume') {
    $recurring_id = (int)$_POST['recurring_id'];
    
    $sql = "UPDATE recurring_expenses SET is_active = true, updated_at = CURRENT_TIMESTAMP 
            WHERE id = $1 AND user_id = $2";
    pg_query_params($dbconn, $sql, array($recurring_id, $user_id));
    
    header("Location: recurring.php");
    exit();
}

// 削除
if ($action === 'delete') {
    $recurring_id = (int)$_POST['recurring_id'];
    
    $sql = "DELETE FROM recurring_expenses WHERE id = $1 AND user_id = $2";
    pg_query_params($dbconn, $sql, array($recurring_id, $user_id));
    
    header("Location: recurring.php");
    exit();
}

header("Location: recurring.php");
exit();
?>

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

// 目標の作成
if ($action === 'create') {
    $goal_name = $_POST['goal_name'];
    $target_amount = (int)$_POST['target_amount'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $icon = $_POST['icon'] ?? '🎯';
    
    $sql = "INSERT INTO savings_goals (user_id, goal_name, target_amount, deadline, icon) 
            VALUES ($1, $2, $3, $4, $5)";
    $params = array($user_id, $goal_name, $target_amount, $deadline, $icon);
    pg_query_params($dbconn, $sql, $params);
    
    header("Location: goals.php");
    exit();
}

// 入金処理
if ($action === 'add_fund') {
    $goal_id = (int)$_POST['goal_id'];
    $add_amount = (int)$_POST['add_amount'];
    
    // 現在の金額を取得
    $sql_get = "SELECT current_amount, target_amount FROM savings_goals WHERE id = $1 AND user_id = $2";
    $res = pg_query_params($dbconn, $sql_get, array($goal_id, $user_id));
    $goal = pg_fetch_assoc($res);
    
    if ($goal) {
        $new_amount = $goal['current_amount'] + $add_amount;
        $is_completed = $new_amount >= $goal['target_amount'];
        
        $sql_update = "UPDATE savings_goals SET current_amount = $1, is_completed = $2, updated_at = CURRENT_TIMESTAMP 
                       WHERE id = $3 AND user_id = $4";
        pg_query_params($dbconn, $sql_update, array($new_amount, $is_completed ? 't' : 'f', $goal_id, $user_id));
    }
    
    header("Location: goals.php");
    exit();
}

// 目標の削除
if ($action === 'delete') {
    $goal_id = (int)$_POST['goal_id'];
    
    $sql = "DELETE FROM savings_goals WHERE id = $1 AND user_id = $2";
    pg_query_params($dbconn, $sql, array($goal_id, $user_id));
    
    header("Location: goals.php");
    exit();
}

header("Location: goals.php");
exit();
?>

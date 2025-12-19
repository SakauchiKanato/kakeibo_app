<?php
session_start();

// 1. ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

// 2. 削除するIDを取得
$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

if (isset($id)) {
    // 3. データベースから削除
    // セキュリティのため、そのIDが本当にログイン中のユーザーのものか(user_id)もチェックします
    $sql = "DELETE FROM transactions WHERE id = $1 AND user_id = $2";
    $result = pg_query_params($dbconn, $sql, array($id, $user_id));

    if (!$result) {
        die("削除に失敗しました: " . pg_last_error());
    }
}

// 4. 元の画面に戻る
header("Location: index.php");
exit();
?>
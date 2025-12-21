<?php
// エラーを表示させる設定（500エラーの原因を特定するため）
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// 1. DB接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

// 2. フォームデータの受け取り
$email = $_POST['emf'] ?? '';
$password = $_POST['pwf'] ?? '';

if ($email && $password) {
    // 3. ユーザー検索
    // カラム名が 'email' であることを確認してください
    $sql = "SELECT user_id, email, password_hash FROM users WHERE email = $1";
    $res = pg_query_params($dbconn, $sql, array($email));

    // クエリ自体が失敗した場合（テーブル名やカラム名の間違いなど）
    if (!$res) {
        die("データベースクエリに失敗しました: " . pg_last_error($dbconn));
    }

    $user = pg_fetch_assoc($res);

    // 4. 照合ロジック
    if ($user) {
        // パスワード照合
        if (password_verify($password, $user['password_hash'])) {
            // 一致した場合：セッションをセットしてリダイレクト
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['ems'] = $user['email'];
            
            header("Location: index.php");
            exit();
        } else {
            // パスワード不一致
            header("Location: login.php?error=1");
            exit();
        }
    } else {
        // ユーザーが見つからない
        header("Location: login.php?error=1");
        exit();
    }
} else {
    // 入力が足りない場合
    header("Location: login.php");
    exit();
}
?>
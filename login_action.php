<?php
session_start();

// DB接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

// login.phpから送られてきたデータを受け取る
$email = $_POST['emf'] ?? '';
$password = $_POST['pwf'] ?? '';

if ($email && $password) {
    // ユーザーをメールアドレスで検索
    $sql = "SELECT id, email, password FROM users WHERE email = $1";
    $res = pg_query_params($dbconn, $sql, array($email));
    $user = pg_fetch_assoc($res);

    // ユーザーが存在し、パスワードが一致するか確認
    // ※登録時に password_hash() を使っている前提です
    if ($user && password_verify($password, $user['password'])) {
        // 一致したらセッションに情報を保存
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['ems'] = $user['email'];
        
        // メイン画面へ移動
        header("Location: index.php");
        exit();
    } else {
        // 間違っていたらエラーメッセージを持ってログイン画面へ戻る
        header("Location: login.php?error=1");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
<?php
// データベース接続情報
// ※ここはゼミのサーバーの情報に合わせて書き換えてください！
$host = 'localhost';      // サーバー名 (基本はlocalhost)
$dbname = 'knt416';   // データベース名 (自分で作ったDB名)
$user = 'knt416';  // ユーザー名
$password = 'nFb55bRP'; // パスワード

try {
    // PostgreSQLへの接続設定
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    
    // 接続オプション (エラーを見やすくする設定など)
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    // 接続実行！
    $pdo = new PDO($dsn, $user, $password, $options);

    // 成功したら何も表示しない（静かに成功させておく）

} catch (PDOException $e) {
    // 失敗したらエラーを表示して終了
    exit('データベース接続失敗: ' . $e->getMessage());
}
?>
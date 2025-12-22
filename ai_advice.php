<?php
// ai_advice.php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('Login required');
}

$user_id = $_SESSION['user_id'];

// 1. Pythonスクリプトを実行（引数にuser_idを渡す）
// お使いの環境に合わせて 'python3' やパスを調整してください
$command = "python3 ask_ai.py " . escapeshellarg($user_id);
$output = shell_exec($command);

// 2. 実行が終わったら index.php に戻る
// (Python側でDBへのINSERTまで行っている前提です)
header('Location: index.php');
exit();
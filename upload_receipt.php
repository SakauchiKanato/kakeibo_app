<?php
session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit();
}

$user_id = $_SESSION['user_id'];

// データベース接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗: ' . pg_last_error());

// アップロードディレクトリの設定
$upload_dir = __DIR__ . '/uploads/receipts/';

// ディレクトリが存在しない場合は作成
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode([
            'success' => false, 
            'error' => 'アップロードディレクトリの作成に失敗しました',
            'path' => $upload_dir
        ]);
        exit();
    }
    chmod($upload_dir, 0777);
}

// ディレクトリの書き込み権限をチェック
if (!is_writable($upload_dir)) {
    echo json_encode([
        'success' => false, 
        'error' => 'アップロードディレクトリに書き込み権限がありません',
        'path' => $upload_dir,
        'permissions' => substr(sprintf('%o', fileperms($upload_dir)), -4)
    ]);
    exit();
}

// ファイルアップロード処理
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['receipt_image']['tmp_name'];
    $file_name = uniqid('receipt_') . '_' . basename($_FILES['receipt_image']['name']);
    $file_path = $upload_dir . $file_name;
    
    // ファイルを保存
    if (move_uploaded_file($file_tmp, $file_path)) {
        // Pythonスクリプトを実行してOCR処理
        $python_script = __DIR__ . '/python/ocr_receipt.py';
        $command = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($file_path) . " 2>&1";
        $ocr_result = shell_exec($command);
        
        // OCR結果をパース
        $ocr_data = json_decode($ocr_result, true);
        
        if ($ocr_data && !isset($ocr_data['error'])) {
            // 抽出された情報を返す
            $response = [
                'success' => true,
                'amount' => $ocr_data['amount'] ?? '',
                'store' => $ocr_data['store'] ?? '',
                'date' => $ocr_data['date'] ?? '',
                'items' => $ocr_data['items'] ?? [],
                'description' => implode(', ', $ocr_data['items'] ?? []),
                'image_path' => 'uploads/receipts/' . $file_name,
                'debug' => $ocr_result
            ];
        } else {
            $response = [
                'success' => false,
                'error' => 'OCR処理に失敗しました',
                'details' => $ocr_data['error'] ?? 'JSONパースエラー',
                'raw_output' => $ocr_result,
                'image_path' => 'uploads/receipts/' . $file_name
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        $error_msg = error_get_last();
        echo json_encode([
            'success' => false, 
            'error' => 'ファイルの保存に失敗しました',
            'details' => $error_msg ? $error_msg['message'] : '不明なエラー',
            'upload_dir' => $upload_dir,
            'permissions' => substr(sprintf('%o', fileperms(dirname($upload_dir))), -4)
        ]);
    }
} else {
    $upload_error = $_FILES['receipt_image']['error'] ?? 'ファイルが送信されていません';
    echo json_encode([
        'success' => false, 
        'error' => 'ファイルアップロードエラー',
        'error_code' => $upload_error
    ]);
}
?>

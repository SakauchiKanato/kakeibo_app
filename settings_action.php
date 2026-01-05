<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗');

$user_id = $_SESSION['user_id'];

// 1. プロフィール（表示名）の更新
if (isset($_POST['username'])) {
    $new_name = trim($_POST['username']);
    if (!empty($new_name)) {
        $sql_nick = "UPDATE users SET username = $1 WHERE user_id = $2";
        pg_query_params($dbconn, $sql_nick, array($new_name, $user_id));
        $_SESSION['username'] = $new_name; // セッションも更新
    }
}

// 2. 全体予算の更新
if (isset($_POST['monthly_limit'])) {
    $monthly_limit = (int)$_POST['monthly_limit'];
    $sql = "
        INSERT INTO budget_settings (user_id, setting_key, setting_value) 
        VALUES ($1, 'monthly_limit', $2)
        ON CONFLICT (user_id, setting_key) 
        DO UPDATE SET setting_value = EXCLUDED.setting_value
    ";
    pg_query_params($dbconn, $sql, array($user_id, $monthly_limit));
}

// 3. デフォルトAIキャラの更新
if (isset($_POST['default_ai_char'])) {
    $ai_char = $_POST['default_ai_char'];
    $sql = "
        INSERT INTO budget_settings (user_id, setting_key, setting_value) 
        VALUES ($1, 'default_ai_char', $2)
        ON CONFLICT (user_id, setting_key) 
        DO UPDATE SET setting_value = EXCLUDED.setting_value
    ";
    pg_query_params($dbconn, $sql, array($user_id, $ai_char));
}

// 4. カテゴリー別予算の更新
if (isset($_POST['cat_budgets']) && is_array($_POST['cat_budgets'])) {
    foreach ($_POST['cat_budgets'] as $cat_id => $limit) {
        $cat_id = (int)$cat_id;
        
        if ($limit === "" || $limit === null) {
            // 空欄の場合は削除
            $sql_del = "DELETE FROM category_budgets WHERE user_id = $1 AND category_id = $2";
            pg_query_params($dbconn, $sql_del, array($user_id, $cat_id));
        } else {
            $limit = (int)$limit;
            $sql_upd = "
                INSERT INTO category_budgets (user_id, category_id, monthly_limit) 
                VALUES ($1, $2, $3)
                ON CONFLICT (user_id, category_id) 
                DO UPDATE SET monthly_limit = EXCLUDED.monthly_limit
            ";
            pg_query_params($dbconn, $sql_upd, array($user_id, $cat_id, $limit));
        }
    }
}

// 5. クイック入力プリセットの追加
if (isset($_POST['add_preset'])) {
    $label = trim($_POST['new_preset_label'] ?? '');
    $amount = (int)($_POST['new_preset_amount'] ?? 0);
    $cat_id = (int)($_POST['new_preset_category'] ?? 0);
    $icon = trim($_POST['new_preset_icon'] ?? '💰');
    $satisfaction = (int)($_POST['new_preset_satisfaction'] ?? 3);

    if (!empty($label) && $amount > 0 && $cat_id > 0) {
        $sql_add = "INSERT INTO quick_input_presets (user_id, label, amount, category_id, icon, satisfaction) VALUES ($1, $2, $3, $4, $5, $6)";
        pg_query_params($dbconn, $sql_add, array($user_id, $label, $amount, $cat_id, $icon, $satisfaction));
        
        // 追加後は設定画面に止まる
        header("Location: settings.php?t=" . time());
        exit();
    }
}

// 6. クイック入力プリセットの削除
if (isset($_POST['delete_preset'])) {
    $preset_id = (int)$_POST['delete_preset'];
    $sql_del = "DELETE FROM quick_input_presets WHERE id = $1 AND user_id = $2";
    pg_query_params($dbconn, $sql_del, array($preset_id, $user_id));
    
    // 削除後は設定画面に止まる
    header("Location: settings.php?t=" . time());
    exit();
}

header("Location: index.php?t=" . time());
exit();
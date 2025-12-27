<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// 1. DB接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

// 2. ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('location: ./login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ems = $_SESSION['ems'];

// --- 予算の更新処理 ---
if (isset($_POST['update_budget'])) {
    $new_limit = (int)$_POST['monthly_limit'];
    
    $sql_check = "SELECT 1 FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
    $res_check = pg_query_params($dbconn, $sql_check, array($user_id));

    if (pg_num_rows($res_check) > 0) {
        $sql_upd = "UPDATE budget_settings SET setting_value = $1 WHERE user_id = $2 AND setting_key = 'monthly_limit'";
    } else {
        $sql_upd = "INSERT INTO budget_settings (user_id, setting_key, setting_value) VALUES ($2, 'monthly_limit', $1)";
    }
    
    pg_query_params($dbconn, $sql_upd, array($new_limit, $user_id));
    header('Location: index.php?t=' . time());
    exit();
}

// --- AI相談ボタンが押された時の処理 ---
if (isset($_POST['run_ai'])) {
    $py_file = __DIR__ . '/python/ask_ai.py';
    $char_type = $_POST['char_type'] ?? 'default';

    $sql_today = "SELECT description, amount, satisfaction FROM transactions 
                  WHERE user_id = $1 AND date(created_at) = current_date";
    $res_today = pg_query_params($dbconn, $sql_today, array($user_id));

    $items_list = "";
    $total_spent_today = 0;

    if (pg_num_rows($res_today) > 0) {
        while ($row = pg_fetch_assoc($res_today)) {
            $items_list .= "・{$row['description']} ({$row['amount']}円, 満足度:{$row['satisfaction']}) \n";
            $total_spent_today += (int)$row['amount'];
        }
    } else {
        $items_list = "支出の記録はありません。";
    }

    $sql_sum_all = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
    $res_sum_all = pg_query_params($dbconn, $sql_sum_all, array($user_id));
    $all_spent = pg_fetch_row($res_sum_all)[0] ?? 0;
    
    $sql_limit = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
    $res_limit = pg_query_params($dbconn, $sql_limit, array($user_id));
    $mon_limit = pg_fetch_row($res_limit)[0] ?? 30000;
    
    $remaining_for_ai = floor(($mon_limit / date('t') * date('j')) - $all_spent);

    $command = "python3 " . escapeshellarg($py_file) . " " . 
               escapeshellarg($items_list) . " " . 
               escapeshellarg($total_spent_today) . " " . 
               escapeshellarg($char_type) . " " . 
               escapeshellarg($remaining_for_ai) . " 2>&1";
    
    $advice_text = shell_exec($command);

    if ($advice_text) {
        $sql_save = "INSERT INTO ai_advice_history (user_id, advice) VALUES ($1, $2)";
        pg_query_params($dbconn, $sql_save, array($user_id, trim($advice_text)));
    }

    header('Location: index.php?slide=0&t=' . time());
    exit();
}

// --- 3. カテゴリー一覧の取得 ---
$sql_categories = "SELECT id, name, icon, color FROM categories ORDER BY id";
$res_categories = pg_query($dbconn, $sql_categories);
$categories = pg_fetch_all($res_categories) ?: [];

// --- 4. AIアドバイス履歴の取得 ---
$sql_ai = "SELECT id, advice, to_char(created_at, 'MM/DD HH24:MI') as time_str FROM ai_advice_history WHERE user_id = $1 ORDER BY created_at DESC LIMIT 20";
$res_ai = pg_query_params($dbconn, $sql_ai, array($user_id));
$chat_logs = pg_fetch_all($res_ai) ?: [];

// --- 5. 計算ロジック（ホーム画面用） ---
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($user_id));
$total_spent = pg_fetch_row($res_sum)[0] ?? 0;

$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($user_id));
$monthly_limit = pg_fetch_row($res_budget)[0] ?? 30000;

$total_days = date('t');
$current_day = date('j');
$daily_allowance = $monthly_limit / $total_days;
$cumulative_budget = $daily_allowance * $current_day;
$today_remaining = floor($cumulative_budget - $total_spent);

$sql_today_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date(created_at) = current_date";
$res_today_spent = pg_query_params($dbconn, $sql_today_spent, array($user_id));
$today_spent = pg_fetch_row($res_today_spent)[0] ?? 0;

// --- 6. グラフデータ集計 ---
$sql_pie = "SELECT satisfaction, SUM(amount) as sum_amount FROM transactions WHERE user_id = $1 GROUP BY satisfaction";
$res_pie = pg_query_params($dbconn, $sql_pie, array($user_id));
$pie_data = [0, 0, 0, 0, 0];
while ($row = pg_fetch_assoc($res_pie)) {
    $idx = intval($row['satisfaction']) - 1;
    if ($idx >= 0 && $idx < 5) $pie_data[$idx] = intval($row['sum_amount']);
}

$sql_category_pie = "SELECT c.name, c.color, COALESCE(SUM(t.amount), 0) as total 
                     FROM categories c 
                     LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = $1 
                     GROUP BY c.id, c.name, c.color 
                     ORDER BY total DESC";
$res_category_pie = pg_query_params($dbconn, $sql_category_pie, array($user_id));
$category_labels = [];
$category_data = [];
$category_colors = [];
while ($row = pg_fetch_assoc($res_category_pie)) {
    if ($row['total'] > 0) {
        $category_labels[] = $row['name'];
        $category_data[] = intval($row['total']);
        $category_colors[] = $row['color'];
    }
}

$sql_bar = "SELECT to_char(created_at, 'MM/DD') as day_str, SUM(amount) as total FROM transactions WHERE user_id = $1 AND created_at > (current_date - interval '7 days') GROUP BY day_str ORDER BY day_str ASC";
$res_bar = pg_query_params($dbconn, $sql_bar, array($user_id));
$bar_labels = []; $bar_data = [];
while ($row = pg_fetch_assoc($res_bar)) {
    $bar_labels[] = $row['day_str'];
    $bar_data[] = intval($row['total']);
}

// カレンダーイベント
$sql_cal = "SELECT t.id, t.description, t.amount, t.satisfaction, t.category_id, c.name as category_name, c.icon as category_icon, to_char(t.created_at, 'YYYY-MM-DD') as date_str 
            FROM transactions t 
            LEFT JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = $1";
$res_cal = pg_query_params($dbconn, $sql_cal, array($user_id));
$cal_events = [];
if ($res_cal) {
    while ($row = pg_fetch_assoc($res_cal)) {
        $cal_events[] = [
            'id' => $row['id'],
            'title' => $row['amount'] . '円',
            'start' => $row['date_str'],
            'description' => $row['description'],
            'satisfaction' => $row['satisfaction'],
            'categoryId' => $row['category_id'],
            'category' => $row['category_name'],
            'categoryIcon' => $row['category_icon']
        ];
    }
}

// --- 7. 検索・フィルタリング処理 ---
$search_results = [];
$is_searching = false;

if (isset($_GET['search']) || isset($_GET['filter_category']) || isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $is_searching = true;
    
    $sql_search = "SELECT t.id, t.description, t.amount, t.satisfaction, c.name as category_name, c.icon as category_icon, 
                   to_char(t.created_at, 'YYYY-MM-DD HH24:MI') as created_str
                   FROM transactions t 
                   LEFT JOIN categories c ON t.category_id = c.id 
                   WHERE t.user_id = $1";
    
    $params = array($user_id);
    $param_count = 1;
    
    if (!empty($_GET['search'])) {
        $param_count++;
        $sql_search .= " AND t.description ILIKE $" . $param_count;
        $params[] = '%' . $_GET['search'] . '%';
    }
    
    if (!empty($_GET['filter_category']) && $_GET['filter_category'] != 'all') {
        $param_count++;
        $sql_search .= " AND t.category_id = $" . $param_count;
        $params[] = intval($_GET['filter_category']);
    }
    
    if (!empty($_GET['date_from'])) {
        $param_count++;
        $sql_search .= " AND t.created_at >= $" . $param_count;
        $params[] = $_GET['date_from'] . ' 00:00:00';
    }
    
    if (!empty($_GET['date_to'])) {
        $param_count++;
        $sql_search .= " AND t.created_at <= $" . $param_count;
        $params[] = $_GET['date_to'] . ' 23:59:59';
    }
    
    if (!empty($_GET['amount_min'])) {
        $param_count++;
        $sql_search .= " AND t.amount >= $" . $param_count;
        $params[] = intval($_GET['amount_min']);
    }
    
    if (!empty($_GET['amount_max'])) {
        $param_count++;
        $sql_search .= " AND t.amount <= $" . $param_count;
        $params[] = intval($_GET['amount_max']);
    }
    
    $sql_search .= " ORDER BY t.created_at DESC LIMIT 100";
    
    $res_search = pg_query_params($dbconn, $sql_search, $params);
    if ($res_search) {
        $search_results = pg_fetch_all($res_search) ?: [];
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 家計簿AI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4a90e2;
            --primary-dark: #357abd;
            --secondary: #50c878;
            --accent: #ff6b6b;
            --warning: #ffa500;
            --bg: #f5f7fa;
            --card-bg: #ffffff;
            --text: #2c3e50;
            --text-light: #7f8c8d;
            --border: #e1e8ed;
            --shadow: rgba(0, 0, 0, 0.08);
        }
        
        [data-theme="dark"] {
            --primary: #5b9def;
            --primary-dark: #4a90e2;
            --secondary: #5fd68a;
            --accent: #ff7b7b;
            --warning: #ffb733;
            --bg: #1a1d2e;
            --card-bg: #252837;
            --text: #e4e6eb;
            --text-light: #a8adb7;
            --border: #3a3f51;
            --shadow: rgba(0, 0, 0, 0.3);
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }
        
        /* ヘッダー */
        .header {
            background: var(--card-bg);
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
            border-radius: 0 0 16px 16px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .user-info {
            font-size: 0.875rem;
            color: var(--text-light);
        }
        
        .logout-btn {
            padding: 0.5rem 1.25rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .logout-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .theme-toggle {
            padding: 0.5rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
            font-size: 1.25rem;
            transition: all 0.2s;
            margin-left: 0.5rem;
        }
        
        .theme-toggle:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .info-btn {
            padding: 0.5rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
            font-size: 1.25rem;
            transition: all 0.2s;
            margin-left: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .info-btn:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        /* Swiper */
        .swiper {
            width: 100%;
            height: calc(100vh - 70px);
        }
        
        .swiper-slide {
            overflow-y: auto;
            padding: 1.5rem;
            padding-bottom: 100px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* カード */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px var(--shadow);
            transition: all 0.2s;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        /* 予算ボックス */
        .budget-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            position: relative;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            color: white;
        }
        
        .budget-label {
            font-size: 0.875rem;
            opacity: 0.95;
            margin-bottom: 0.5rem;
        }
        
        .budget-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .budget-info {
            font-size: 0.75rem;
            margin-top: 0.75rem;
            opacity: 0.9;
        }
        
        /* フォーム */
        input, select, button {
            width: 100%;
            padding: 0.75rem 1rem;
            margin: 0.5rem 0;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }
        
        button {
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        button:hover {
            background: var(--primary-dark);
        }
        
        /* チャット */
        .chat-bubble {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .chat-time {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        
        /* ナビゲーション */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 70px;
            background: var(--card-bg);
            border-top: 1px solid var(--border);
            display: flex;
            z-index: 1000;
            box-shadow: 0 -2px 8px var(--shadow);
        }
        
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 11000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            width: 90%;
            max-width: 420px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-content h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--text);
        }
        
        .modal-content label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* アラート */
        #alertContainer {
            margin-bottom: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        /* レシートアップロード */
        .receipt-upload {
            background: var(--bg);
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        
        .receipt-upload:hover {
            border-color: var(--primary);
        }
        
        .receipt-preview {
            margin-top: 1rem;
            display: none;
        }
        
        .receipt-preview img {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        
        .ocr-status {
            font-size: 0.875rem;
            padding: 0.5rem;
            border-radius: 8px;
            background: var(--bg);
        }
        
        /* クイックリンク */
        .quick-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .quick-link {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
        }
        
        .quick-link:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .quick-link-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .quick-link-text {
            font-weight: 600;
            font-size: 0.9375rem;
        }
        
        h2, h3 {
            margin-bottom: 1rem;
        }
        
        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }
        
        h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text);
        }
        
        /* FullCalendar カスタマイズ */
        .fc {
            background: var(--card-bg);
        }
        
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: var(--border);
        }
        
        .fc-col-header-cell {
            background: var(--bg);
            font-weight: 600;
            color: var(--text);
        }
        
        .fc-daygrid-day-number {
            color: var(--text);
        }
        
        .fc-event {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .fc-button {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        
        .fc-button:hover {
            background: var(--primary-dark) !important;
        }

        /* カレンダーの日付セルをインタラクティブに */
        .fc-daygrid-day {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .fc-daygrid-day:hover {
            background-color: var(--bg) !important;
        }
        .fc-day-today {
            background-color: rgba(74, 144, 226, 0.1) !important;
        }
    </style>
</head>
<body>


<div class="swiper">
    <div class="swiper-wrapper">
        <!-- AI相談画面 -->
        <div class="swiper-slide">
            <div class="header">
                <div class="header-left">
                    <div class="logo">💰 家計簿AI</div>
                    <div class="user-info"><?php echo htmlspecialchars($ems); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <button class="info-btn" onclick="openHelpModal()" title="使いかたガイド">❓</button>
                    <button class="theme-toggle" onclick="toggleTheme()" title="ダークモード切り替え">🌙</button>
                    <a href="logout.php" class="logout-btn">ログアウト</a>
                </div>
            </div>
            <div class="container">
                <h2>🤖 AI相談</h2>
                
                <div class="card">
                    <p style="margin-bottom: 1rem; font-weight: 500;">最新の状況をGeminiに相談</p>
                    <form action="" method="post">
                        <select name="char_type">
                            <option value="default">👤 標準（丁寧なアドバイス）</option>
                            <option value="strict">🔥 鬼コンサル（厳しい指摘）</option>
                            <option value="sister">🌸 優しいお姉さん（共感・褒める）</option>
                            <option value="detective">🔍 名探偵（鋭い分析）</option>
                        </select>
                        <button type="submit" name="run_ai">✨ AIにアドバイスを貰う</button>
                    </form>
                </div>
                
                <?php foreach ($chat_logs as $log): ?>
                    <div class="chat-time">🤖 AIアドバイス (<?php echo $log['time_str']; ?>)</div>
                    <?php 
                    $msgs = explode('---', $log['advice']);
                    foreach ($msgs as $m): 
                        if (!trim($m)) continue;
                    ?>
                        <div class="chat-bubble">
                            <?php echo nl2br(htmlspecialchars(trim($m))); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ホーム画面 -->
        <div class="swiper-slide">
            <div class="header">
                <div class="header-left">
                    <div class="logo">💰 家計簿AI</div>
                    <div class="user-info"><?php echo htmlspecialchars($ems); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <button class="info-btn" onclick="openHelpModal()" title="使いかたガイド">❓</button>
                    <button class="theme-toggle" onclick="toggleTheme()" title="ダークモード切り替え">🌙</button>
                    <a href="logout.php" class="logout-btn">ログアウト</a>
                </div>
            </div>
            <div class="container">
                <div id="alertContainer"></div>

                <div class="budget-box">
                    <button type="button" onclick="openBudgetModal()" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.2); width: auto; padding: 0.5rem; font-size: 1.25rem; border: none;">⚙️</button>
                    <div class="budget-label">今日使えるお金</div>
                    <div class="budget-amount"><?php echo number_format($today_remaining); ?>円</div>
                    
                    <?php 
                    $usage_pct = min(100, ($total_spent / $monthly_limit) * 100);
                    $bar_color = $usage_pct >= 100 ? '#ff6b6b' : ($usage_pct >= 80 ? '#ffa500' : '#50c878');
                    ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $usage_pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
                    </div>
                    <div class="budget-info">
                        今月の予算使用率: <?php echo round($usage_pct); ?>% (<?php echo number_format($total_spent); ?> / <?php echo number_format($monthly_limit); ?>円)
                    </div>
                </div>

                <div class="quick-links">
                    <a href="goals.php" class="quick-link">
                        <div class="quick-link-icon">🎯</div>
                        <div class="quick-link-text">目標設定</div>
                    </a>
                    <a href="recurring.php" class="quick-link">
                        <div class="quick-link-icon">🔄</div>
                        <div class="quick-link-text">定期支出</div>
                    </a>
                </div>

                <div class="card">
                    <h3>支出を記録</h3>
                    
                    <div class="receipt-upload">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">📸 レシートをスキャン（任意）</div>
                        <input type="file" id="receiptImage" accept="image/*" style="display: none;">
                        <button type="button" onclick="document.getElementById('receiptImage').click()">
                            📷 レシート画像を選択
                        </button>
                        <div id="receiptPreview" class="receipt-preview">
                            <img id="previewImg">
                            <div id="ocrStatus" class="ocr-status"></div>
                        </div>
                    </div>
                    
                    <form id="expenseForm" action="add_action.php" method="post">
                        <input type="text" id="descriptionInput" name="description" placeholder="何に使った？" required>
                        <input type="number" id="amountInput" name="amount" placeholder="金額" required>
                        <select name="category_id">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="satisfaction">
                            <option value="5">⭐⭐⭐⭐⭐ 最高！</option>
                            <option value="4">⭐⭐⭐⭐ 満足</option>
                            <option value="3" selected>⭐⭐⭐ 普通</option>
                            <option value="2">⭐⭐ 微妙</option>
                            <option value="1">⭐ 後悔...</option>
                        </select>
                        <button type="submit">記録する</button>
                    </form>
                </div>

                <div class="card"><div id="calendar"></div></div>
            </div>
        </div>

        <!-- 分析画面 -->
        <div class="swiper-slide">
            <div class="header">
                <div class="header-left">
                    <div class="logo">💰 家計簿AI</div>
                    <div class="user-info"><?php echo htmlspecialchars($ems); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <button class="info-btn" onclick="openHelpModal()" title="使いかたガイド">❓</button>
                    <button class="theme-toggle" onclick="toggleTheme()" title="ダークモード切り替え">🌙</button>
                    <a href="logout.php" class="logout-btn">ログアウト</a>
                </div>
            </div>
            <div class="container">
                <h2>📊 分析レポート</h2>
                
                <div class="card">
                    <h3>カテゴリー別支出</h3>
                    <div style="height:300px;"><canvas id="categoryPieChart"></canvas></div>
                </div>
                <div class="card">
                    <h3>満足度別支出</h3>
                    <div style="height:300px;"><canvas id="pieChart"></canvas></div>
                </div>
                <div class="card">
                    <h3>週間支出推移</h3>
                    <div style="height:300px;"><canvas id="barChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <div class="nav-item" onclick="mainSwiper.slideTo(0)">
        <div class="nav-icon">💬</div>
        <div>AI</div>
    </div>
    <div class="nav-item" onclick="mainSwiper.slideTo(1)">
        <div class="nav-icon">🏠</div>
        <div>ホーム</div>
    </div>
    <div class="nav-item" onclick="mainSwiper.slideTo(2)">
        <div class="nav-icon">📈</div>
        <div>分析</div>
    </div>
</nav>

<!-- 支出追加モーダル（日付指定） -->
<div id="addModal" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--secondary);">
        <div style="font-size: 3rem; text-align: center; margin-bottom: 1rem;">✍️</div>
        <h3 style="text-align: center; margin-bottom: 1.5rem;">支出を記録</h3>
        <form action="add_action.php" method="post">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>日付</label>
                <input type="date" name="date" id="addDateInput" required style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>内容</label>
                <input type="text" name="description" placeholder="何に使った？" required style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>金額</label>
                <input type="number" name="amount" placeholder="金額" required style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>カテゴリー</label>
                <select name="category_id" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>満足度</label>
                <select name="satisfaction" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="5">⭐⭐⭐⭐⭐</option>
                    <option value="4">⭐⭐⭐⭐</option>
                    <option value="3" selected>⭐⭐⭐</option>
                    <option value="2">⭐⭐</option>
                    <option value="1">⭐</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="closeAddModal()" style="flex: 1; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem; font-weight: 600;">キャンセル</button>
                <button type="submit" style="flex: 2; background: var(--secondary); color: white; border: none; border-radius: 12px; padding: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(80, 200, 120, 0.3);">追加する</button>
            </div>
        </form>
    </div>
</div>

<!-- 支出編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>支出の編集</h3>
        <form action="edit_action.php" method="post">
            <input type="hidden" name="id" id="edit-id">
            <label>内容</label>
            <input type="text" name="description" id="edit-desc" required>
            <label>金額</label>
            <input type="number" name="amount" id="edit-amount" required>
            <label>カテゴリー</label>
            <select name="category_id" id="edit-category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>">
                        <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>満足度</label>
            <select name="satisfaction" id="edit-sat">
                <option value="5">⭐⭐⭐⭐⭐</option>
                <option value="4">⭐⭐⭐⭐</option>
                <option value="3">⭐⭐⭐</option>
                <option value="2">⭐⭐</option>
                <option value="1">⭐</option>
            </select>
            <button type="submit">更新する</button>
        </form>
        
        <button type="button" onclick="
            const editId = document.getElementById('edit-id').value;
            if (editId) {
                window.currentDeleteId = editId;
                document.getElementById('deleteConfirmModal').style.display = 'flex';
            } else {
                alert('エラー: IDが取得できません');
            }
        " style="background: var(--accent); width: 100%; margin-top: 1rem;">🗑️ 削除する</button>
        
        <button type="button" onclick="closeModal()" style="background: var(--bg); color: var(--text); margin-top: 0.5rem; width: 100%;">キャンセル</button>
    </div>
</div>

<!-- スタイリッシュな削除確認モーダル -->
<div id="deleteConfirmModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 360px; text-align: center; border-top: 5px solid var(--accent);">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">🗑️</div>
        <h3 style="margin-bottom: 0.5rem; color: var(--text);">本当に削除しますか？</h3>
        <p style="color: var(--text-light); margin-bottom: 2rem; font-size: 0.9rem;">この操作は取り消せません。データは永久に失われます。</p>
        <div style="display: flex; gap: 1rem;">
            <button type="button" onclick="document.getElementById('deleteConfirmModal').style.display = 'none'" style="flex: 1; background: var(--bg); color: var(--text); border: 1px solid var(--border);">戻る</button>
            <button type="button" onclick="
                if (window.currentDeleteId) {
                    window.location.href = 'delete_action.php?id=' + window.currentDeleteId;
                }
            " style="flex: 1; background: var(--accent); color: white; box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);">削除する</button>
        </div>
    </div>
</div>

<!-- 予算設定モーダル -->
<div id="budgetModal" class="modal">
    <div class="modal-content">
        <h3>⚙️ 予算設定</h3>
        <form action="" method="post">
            <label>今月の総予算 (円)</label>
            <input type="number" name="monthly_limit" value="<?php echo $monthly_limit; ?>" required>
            <button type="submit" name="update_budget">予算を更新する</button>
            <button type="button" onclick="closeBudgetModal()" style="background: var(--bg); color: var(--text); margin-top: 0.5rem;">キャンセル</button>
        </form>
    </div>
</div>

<!-- 使い方ガイドモーダル -->
<div id="helpModal" class="modal">
    <div class="modal-content" style="max-width: 500px; max-height: 85vh; border-top: 5px solid var(--primary);">
        <h3 style="text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem;">📖 使いかたガイド</h3>
        
        <div style="overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">🤖 AIに相談する</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;">「AI」タブでは、Geminiがあなたの支出についてアドバイスをくれます。今日の支出に対する感想や、節約のヒントを気軽に聞いてみましょう！</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">📝 支出を記録（その場ですぐ）</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;">「ホーム」タブの「支出を記録」から、金額と内容を入力するだけでOK！レシートをカメラで撮れば、AIが自動で読み取ってくれます（OCR機能）。</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">📅 過去の支出を記録</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;">カレンダーの枠をタップすると、その日の支出を登録できます。昨日つけ忘れた！という時も安心です。</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">🎯 目標設定と定期支出</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;">「目標設定」で貯金のやる気をアップ！サブスクなどの「定期支出」を登録しておけば、毎月の管理がグッと楽になります。</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">🌗 ダークモード</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;">ヘッダーの🌙（または☀️）ボタンで、いつでも目に優しいダークモードに切り替えられます。</p>
            </div>
        </div>
        
        <button type="button" onclick="closeHelpModal()" style="width: 100%; background: var(--primary); color: white; border: none; border-radius: 12px; padding: 0.75rem; font-weight: 600; cursor: pointer;">閉じる</button>
    </div>
</div>

<div id="tooltip" style="position: fixed; background: rgba(0,0,0,0.8); color: white; padding: 8px 12px; border-radius: 6px; display: none; z-index: 10000; pointer-events: none; font-size: 0.875rem;"></div>

<script>
    window.APP_DATA = {
        events: <?php echo json_encode($cal_events); ?>,
        pie: <?php echo json_encode($pie_data); ?>,
        categoryLabels: <?php echo json_encode($category_labels); ?>,
        categoryData: <?php echo json_encode($category_data); ?>,
        categoryColors: <?php echo json_encode($category_colors); ?>,
        barLabels: <?php echo json_encode($bar_labels); ?>,
        barData: <?php echo json_encode($bar_data); ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="js/script.js"></script>

</body>
</html>
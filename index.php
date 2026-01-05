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

// 常に最新のユーザー名を取得するように強化
$sql_user = "SELECT username FROM users WHERE user_id = $1";
$res_user = pg_query_params($dbconn, $sql_user, array($user_id));
$row_user = pg_fetch_assoc($res_user);
$db_username = $row_user['username'] ?? '';

// セッションを更新し、表示用変数を設定（空文字の場合はメールアドレスをフォールバック）
$_SESSION['username'] = $db_username;
$username = (!empty($db_username)) ? $db_username : $ems;

// デフォルトのAIキャラ設定を取得
$sql_ai_pref = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'default_ai_char'";
$res_ai_pref = pg_query_params($dbconn, $sql_ai_pref, array($user_id));
$default_ai = pg_fetch_row($res_ai_pref)[0] ?? 'default';

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

    // AI向けの追加インサイト（浪費・カテゴリー超過）を取得
    $sql_waste_ai = "SELECT description, amount FROM transactions 
                     WHERE user_id = $1 AND satisfaction <= 2 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
    $res_waste_ai = pg_query_params($dbconn, $sql_waste_ai, array($user_id));
    $waste_list_ai = "";
    while($w = pg_fetch_assoc($res_waste_ai)) {
        $waste_list_ai .= "【後悔】" . $w['description'] . "(" . $w['amount'] . "円) ";
    }

    $sql_over_ai = "
        SELECT c.name FROM category_budgets cb
        JOIN categories c ON cb.category_id = c.id
        JOIN (SELECT category_id, SUM(amount) as s FROM transactions 
              WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)
              GROUP BY category_id) spent ON spent.category_id = cb.category_id
        WHERE cb.user_id = $1 AND spent.s > cb.monthly_limit
    ";
    $res_over_ai = pg_query_params($dbconn, $sql_over_ai, array($user_id));
    $over_list_ai = "";
    while($o = pg_fetch_assoc($res_over_ai)) {
        $over_list_ai .= "【超過】" . $o['name'] . " ";
    }
    
    $insights_string = $waste_list_ai . $over_list_ai;
    $remaining_for_ai = floor(($mon_limit / date('t') * date('j')) - $all_spent);

    // AIに渡すユーザー名を確定（念のため再確認）
    $current_username = (!empty($db_username)) ? $db_username : $ems;

    // 日本語（マルチバイト文字）を安全に渡すため、Base64エンコードを使用
    $encoded_items = base64_encode($items_list);
    $encoded_name = base64_encode($current_username);
    $encoded_insights = base64_encode($insights_string);

    $command = "python3 " . escapeshellarg($py_file) . " " . 
               escapeshellarg($encoded_items) . " " . 
               escapeshellarg($total_spent_today) . " " . 
               escapeshellarg($char_type) . " " . 
               escapeshellarg($remaining_for_ai) . " " . 
               escapeshellarg($encoded_name) . " " .
               escapeshellarg($encoded_insights) . " 2>&1";
    
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

// A. 固定費（定期支出）の合計を取得
$sql_fixed = "SELECT SUM(amount) FROM recurring_expenses WHERE user_id = $1 AND is_active = TRUE";
$res_fixed = pg_query_params($dbconn, $sql_fixed, array($user_id));
$fixed_costs = pg_fetch_row($res_fixed)[0] ?? 0;

// 8. クイック入力プリセットを取得
$sql_presets = "SELECT id, label, amount, category_id, icon, satisfaction FROM quick_input_presets WHERE user_id = $1 ORDER BY id";
$res_presets = pg_query_params($dbconn, $sql_presets, array($user_id));
$presets = pg_fetch_all($res_presets) ?: [];

// --- 今日の記録があるかチェック（未入力バッジ用） ---
$sql_check_today = "SELECT 1 FROM transactions WHERE user_id = $1 AND date(created_at) = current_date LIMIT 1";
$res_check_today = pg_query_params($dbconn, $sql_check_today, array($user_id));
$is_today_recorded = (pg_num_rows($res_check_today) > 0);

// B. 今月の総支出（実支出 + 発生済みの固定費は transactions に入っているべきだが、現状は transactions のみ集計）
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($user_id));
$total_spent = pg_fetch_row($res_sum)[0] ?? 0;

// C. 月間予算設定の取得
$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($user_id));
$monthly_limit = pg_fetch_row($res_budget)[0] ?? 30000;

// D. 「本当の自由枠」での日次予算計算
$total_days = date('t');
$current_day = date('j');
$variable_budget = $monthly_limit - $fixed_costs; // 固定費を引いた残り
$daily_allowance = ($variable_budget > 0) ? ($variable_budget / $total_days) : 0;
$cumulative_variable_budget = $daily_allowance * $current_day;
$today_remaining = floor($cumulative_variable_budget - $total_spent);

// E. カテゴリー別予算進捗の取得
$sql_cat_status = "
    SELECT c.id, c.name, c.icon, c.color, cb.monthly_limit, 
           COALESCE(SUM(t.amount), 0) as spent
    FROM categories c
    JOIN category_budgets cb ON c.id = cb.category_id AND cb.user_id = $1
    LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = $1 
         AND date_trunc('month', t.created_at) = date_trunc('month', current_timestamp)
    GROUP BY c.id, c.name, c.icon, c.color, cb.monthly_limit
";
$res_cat_status = pg_query_params($dbconn, $sql_cat_status, array($user_id));
$category_budgets_status = pg_fetch_all($res_cat_status) ?: [];

// F. 今日の支出（表示用）
$sql_today_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date(created_at) = current_date";
$res_today_spent = pg_query_params($dbconn, $sql_today_spent, array($user_id));
$today_spent = pg_fetch_row($res_today_spent)[0] ?? 0;

// G. 浪費（満足度1-2）の抽出
$sql_waste = "SELECT description, amount, created_at FROM transactions 
              WHERE user_id = $1 AND satisfaction <= 2 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)
              ORDER BY created_at DESC";
$res_waste = pg_query_params($dbconn, $sql_waste, array($user_id));
$waste_items = pg_fetch_all($res_waste) ?: [];

// --- 既読ロジック：AI相談後にアラートを消す ---
$show_waste_alert = false;
if (!empty($waste_items)) {
    $latest_waste_time = $waste_items[0]['created_at'];
    
    // 最新のAIアドバイスの日時を取得
    $sql_last_advice = "SELECT created_at FROM ai_advice_history WHERE user_id = $1 ORDER BY created_at DESC LIMIT 1";
    $res_last_advice = pg_query_params($dbconn, $sql_last_advice, array($user_id));
    $last_advice_time = pg_fetch_row($res_last_advice)[0] ?? '1970-01-01 00:00:00';
    
    // アドバイス後に新しい浪費が発生している場合のみ表示
    if (strtotime($latest_waste_time) > strtotime($last_advice_time)) {
        $show_waste_alert = true;
    }
}

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
    <title>Money Partner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="img/app_icon.jpg">
    <link rel="apple-touch-icon" href="img/app_icon.jpg">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="js/script.js" defer></script>
</head>
<body class="index-page">


<div class="swiper">
    <div class="swiper-wrapper">
        <!-- AI相談画面 -->
        <div class="swiper-slide">
            <div class="header">
                <div class="header-left">
                    <img src="img/app_icon.jpg" alt="Logo" style="width: 32px; height: 32px; border-radius: 8px;">
                    <div class="logo">Money Partner</div>
                    <div class="user-info"><?php echo htmlspecialchars($username); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <a href="settings.php" class="info-btn" title="詳細設定" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">⚙️</a>
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
                            <option value="default" <?php if($default_ai == 'default') echo 'selected'; ?>>👤 標準（丁寧なアドバイス）</option>
                            <option value="strict" <?php if($default_ai == 'strict') echo 'selected'; ?>>🔥 鬼コンサル（厳しい指摘）</option>
                            <option value="sister" <?php if($default_ai == 'sister') echo 'selected'; ?>>🌸 優しいお姉さん（共感・褒める）</option>
                            <option value="detective" <?php if($default_ai == 'detective') echo 'selected'; ?>>🔍 名探偵（鋭い分析）</option>
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
                    <img src="img/app_icon.jpg" alt="Logo" style="width: 32px; height: 32px; border-radius: 8px;">
                    <div class="logo">Money Partner</div>
                    <div class="user-info"><?php echo htmlspecialchars($username); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <a href="settings.php" class="info-btn" title="詳細設定" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">⚙️</a>
                    <button class="info-btn" onclick="openHelpModal()" title="使いかたガイド">❓</button>
                    <button class="theme-toggle" onclick="toggleTheme()" title="ダークモード切り替え">🌙</button>
                    <a href="logout.php" class="logout-btn">ログアウト</a>
                </div>
            </div>
            <div class="container">
                <div id="alertContainer"></div>

                <!-- 1. 予算サマリーセクション -->
                <div class="dashboard-section">
                    <div class="budget-box">
                        <div class="budget-label">今日使える自由なお金</div>
                        <div class="budget-amount"><?php echo number_format($today_remaining); ?>円</div>
                        
                        <?php 
                        $usage_pct = min(100, ($total_spent / ($monthly_limit - $fixed_costs > 0 ? $monthly_limit - $fixed_costs : 1)) * 100);
                        $bar_color = $usage_pct >= 100 ? '#ff6b6b' : ($usage_pct >= 80 ? '#ffa500' : '#50c878');
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $usage_pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
                        </div>
                        <div class="budget-info">
                            自由枠の使用率: <?php echo round($usage_pct); ?>% (<?php echo number_format($total_spent); ?> / <?php echo number_format($monthly_limit - $fixed_costs); ?>円)
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 0.5rem; text-align: center;">
                            ※月間予算から固定費（<?php echo number_format($fixed_costs); ?>円）を除いて計算しています
                        </div>
                    </div>

                    <!-- 浪費アラート（相談前かつ最新の浪費がある場合のみ表示） -->
                    <?php if ($show_waste_alert): ?>
                    <div class="waste-alert-card" onclick="mainSwiper.slideTo(0)" style="cursor: pointer;">
                        <div class="waste-alert-content">
                            <span class="waste-alert-icon">⚠️</span>
                            <div class="waste-alert-text">
                                <strong>振り返りが必要な支出があります</strong>
                                <p>今月、満足度の低い支出が <?php echo count($waste_items); ?> 件あります。AIのアドバイスを貰いませんか？</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 2. アクションセンター（記録エリア） -->
                <div class="action-center">
                    <div class="action-header">
                        <h3>支出を記録</h3>
                        <?php if (!$is_today_recorded): ?>
                            <span class="entry-missing-badge">⚠️ 未記録</span>
                        <?php else: ?>
                            <span class="entry-done-badge">✅ 記録済み</span>
                        <?php endif; ?>
                    </div>

                    <!-- クイック入力ボタンエリア -->
                    <?php if (!empty($presets)): ?>
                    <div class="quick-presets">
                        <?php foreach ($presets as $p): ?>
                            <form action="add_action.php" method="post" style="display: contents;">
                                <input type="hidden" name="description" value="<?php echo htmlspecialchars($p['label']); ?>">
                                <input type="hidden" name="amount" value="<?php echo $p['amount']; ?>">
                                <input type="hidden" name="category_id" value="<?php echo $p['category_id']; ?>">
                                <input type="hidden" name="satisfaction" value="<?php echo $p['satisfaction'] ?? 3; ?>">
                                <button type="submit" class="preset-btn">
                                    <span class="preset-icon"><?php echo $p['icon']; ?></span>
                                    <span class="preset-label"><?php echo htmlspecialchars($p['label']); ?></span>
                                    <span class="preset-amount"><?php echo number_format($p['amount']); ?>円</span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="background: var(--bg-main); padding: 1rem; border-radius: 12px; margin-bottom: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-light); border: 1px dashed var(--border);">
                        クイック入力を設定すると、1タップで記録できるようになります 🚀
                    </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <button class="action-btn primary" onclick="openAddModal()">
                            <span class="btn-icon">✍️</span> 手動で入力
                        </button>
                        <button class="action-btn secondary" onclick="document.getElementById('receiptImage').click()">
                            <span class="btn-icon">📸</span> レシート
                        </button>
                    </div>

                    <div id="receiptPreview" class="receipt-preview" style="display:none; margin-top:1rem;">
                        <img id="previewImg" style="max-height: 100px; border-radius: 8px;">
                        <div id="ocrStatus" class="ocr-status"></div>
                        <div style="margin-top: 0.5rem; text-align: center;">
                            <button onclick="openAddModal()" class="save-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem;">内容をフォームで調整する</button>
                        </div>
                    </div>
                    <input type="file" id="receiptImage" accept="image/*" style="display: none;">
                </div>

                <!-- 3. 管理・詳細セクション -->
                <div class="dashboard-section grid-2">
                    <!-- カテゴリー別予算進捗 -->
                    <?php if (!empty($category_budgets_status)): ?>
                    <div class="card mini-card">
                        <h3 style="margin-bottom: 1rem;">📊 今月の予算進捗</h3>
                        <div class="category-progress-list">
                            <?php foreach ($category_budgets_status as $cat): 
                                $pct = min(100, ($cat['spent'] / ($cat['monthly_limit'] > 0 ? $cat['monthly_limit'] : 1)) * 100);
                                $color = $pct >= 100 ? '#ff6b6b' : ($pct >= 80 ? '#ffa500' : $cat['color']);
                            ?>
                            <div class="mini-cat-item">
                                <div class="mini-cat-label">
                                    <span><?php echo $cat['icon']; ?> <?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="mini-cat-pct"><?php echo round($pct); ?>%</span>
                                </div>
                                <div class="mini-progress-bar">
                                    <div class="mini-progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="quick-links-v2">
                        <a href="goals.php" class="quick-link-v2 blue">
                            <span class="ql-icon">🎯</span>
                            <div class="ql-text">
                                <strong>目標設定</strong>
                                <small>貯金や買い物の目標</small>
                            </div>
                        </a>
                        <a href="recurring.php" class="quick-link-v2 orange">
                            <span class="ql-icon">🔄</span>
                            <div class="ql-text">
                                <strong>定期支出</strong>
                                <small>サブスクや固定費</small>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="card" style="margin-top: 1rem;">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>

        <!-- 分析画面 -->
        <div class="swiper-slide">
            <div class="header">
                <div class="header-left">
                    <img src="img/app_icon.jpg" alt="Logo" style="width: 32px; height: 32px; border-radius: 8px;">
                    <div class="logo">Money Partner</div>
                    <div class="user-info"><?php echo htmlspecialchars($username); ?> さん</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <a href="settings.php" class="info-btn" title="詳細設定" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">⚙️</a>
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
        <div style="font-size: 3rem; text-align: center; margin-bottom: 0.5rem;">✍️</div>
        <h3 style="text-align: center; margin-bottom: 1.5rem;">支出を記録</h3>
        <form action="add_action.php" method="post">
            <div class="form-group">
                <label>日付</label>
                <input type="date" name="date" id="addDateInput" required>
            </div>
            <div class="form-group">
                <label>内容</label>
                <input type="text" name="description" placeholder="何に使った？" required>
            </div>
            <div class="form-group">
                <label>金額</label>
                <input type="number" name="amount" placeholder="金額" required>
            </div>
            <div class="form-group">
                <label>カテゴリー</label>
                <select name="category_id">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>満足度</label>
                <select name="satisfaction">
                    <option value="5">⭐⭐⭐⭐⭐ 最高！</option>
                    <option value="4">⭐⭐⭐⭐ 満足</option>
                    <option value="3" selected>⭐⭐⭐ 普通</option>
                    <option value="2">⭐⭐ 微妙</option>
                    <option value="1">⭐ 後悔...</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="closeAddModal()" style="flex: 1; background: var(--bg-main); color: var(--text); border: 1px solid var(--border);">キャンセル</button>
                <button type="submit" style="flex: 2; background: var(--secondary); color: white; box-shadow: 0 4px 12px rgba(80, 200, 120, 0.3);">追加する</button>
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
        
        <button type="button" onclick="closeModal()" style="background: var(--bg-main); color: var(--text); margin-top: 0.5rem; width: 100%;">キャンセル</button>
    </div>
</div>

<!-- スタイリッシュな削除確認モーダル -->
<div id="deleteConfirmModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 360px; text-align: center; border-top: 5px solid var(--accent);">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">🗑️</div>
        <h3 style="margin-bottom: 0.5rem; color: var(--text);">本当に削除しますか？</h3>
        <p style="color: var(--text-light); margin-bottom: 2rem; font-size: 0.9rem;">この操作は取り消せません。データは永久に失われます。</p>
        <div style="display: flex; gap: 1rem;">
            <button type="button" onclick="document.getElementById('deleteConfirmModal').style.display = 'none'" style="flex: 1; background: var(--bg-main); color: var(--text); border: 1px solid var(--border);">戻る</button>
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
            <a href="settings.php" style="display: block; text-align: center; margin-top: 1rem; font-size: 0.9rem; color: var(--secondary); text-decoration: none; font-weight: bold;">➡️ すべての設定・プロフィール編集へ</a>
            <button type="button" onclick="closeBudgetModal()" style="background: var(--bg-main); color: var(--text); margin-top: 1rem;">キャンセル</button>
        </form>
    </div>
</div>

        <div id="helpModal" class="modal">
            <div class="modal-content" style="max-width: 500px; max-height: 85vh; border-top: 5px solid var(--primary);">
                <h3 style="text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem;">✨ 使いかたガイド</h3>
                
                <div style="overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1.5rem;">
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">1. 1タップで記録 🚀</h4>
                        <p style="font-size: 0.9rem; line-height: 1.6;">ホーム画面の「クイック入力」ボタンを押すだけで、いつものランチやカフェ代を即座に記録できます。満足度も自動でセットされます！</p>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">2. 付け忘れをチェック ⚠️</h4>
                        <p style="font-size: 0.9rem; line-height: 1.6;">記録がない日は黄色いバッジが教えてくれます。バッジが「✅ 記録済み」になるのを毎日の楽しみにしましょう。</p>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">3. AIに家計を相談 🤖</h4>
                        <p style="font-size: 0.9rem; line-height: 1.6;">「AI」タブでは、Geminiがあなたの支出を分析してアドバイスをくれます。設定画面から好きな性格のパートナーを選んでみてください。</p>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">4. 無駄遣いを退治 🌟</h4>
                        <p style="font-size: 0.9rem; line-height: 1.6;">「満足度の低い支出」は浪費のサイン。AIが自動で見つけ出し、あなたがもっとハッピーにお金を使えるよう手助けします。</p>
                    </div>
                </div>
                
                <button type="button" onclick="closeHelpModal()" style="width: 100%; background: var(--primary); color: white; border: none; border-radius: 12px; padding: 0.75rem; font-weight: 600; cursor: pointer;">ガイドを閉じる</button>
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
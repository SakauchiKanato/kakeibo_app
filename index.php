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
$ems = $_SESSION['ems']; // ログインユーザーのメールアドレス

// --- AI相談ボタンが押された時の処理 ---
if (isset($_POST['run_ai'])) {
    $py_file = __DIR__ . '/python/ask_ai.py';

    // 1. 今日の支出の詳細をDBから取得（user_id=9などのログインユーザー分）
    // ※テーブル名はご提示のデータに基づき「transactions」と仮定しています
    $sql_today = "SELECT description, amount, satisfaction FROM transactions 
                  WHERE user_id = $1 AND date(created_at) = current_date";
    $res_today = pg_query_params($dbconn, $sql_today, array($user_id));

    $items_list = "";
    $total_spent = 0;

    if (pg_num_rows($res_today) > 0) {
        while ($row = pg_fetch_assoc($res_today)) {
            // Pythonが読みやすいように「内容(金額円, 満足度:X)」という形式にまとめる
            $items_list .= "・{$row['description']} ({$row['amount']}円, 満足度:{$row['satisfaction']}) \n";
            $total_spent += (int)$row['amount'];
        }
    } else {
        $items_list = "支出の記録はありません。";
        $total_spent = 0;
    }

    // 2. Pythonを実行（第1引数：支出リスト、第2引数：合計金額）
    // これにより sys.argv[1] と sys.argv[2] に正しいデータが入ります
    $command = "python3 " . escapeshellarg($py_file) . " " . 
               escapeshellarg($items_list) . " " . 
               escapeshellarg($total_spent) . " 2>&1";
    
    $advice_text = shell_exec($command);

    // 3. AIのアドバイスを保存
    if ($advice_text) {
        $sql_save = "INSERT INTO ai_advice_history (user_id, advice) VALUES ($1, $2)";
        pg_query_params($dbconn, $sql_save, array($user_id, trim($advice_text)));
    }

    // AI画面を表示
    header('Location: index.php?slide=0&t=' . time());
    exit();
}

// --- 3. AIアドバイス履歴の取得 ---
$sql_ai = "SELECT id, advice, to_char(created_at, 'MM/DD HH24:MI') as time_str FROM ai_advice_history WHERE user_id = $1 ORDER BY created_at DESC LIMIT 20";
$res_ai = pg_query_params($dbconn, $sql_ai, array($user_id));
$chat_logs = pg_fetch_all($res_ai) ?: [];

// --- 4. 計算ロジック（ホーム画面用） ---
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($user_id));
$total_spent = pg_fetch_row($res_sum)[0] ?? 0;

$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($user_id));
$monthly_limit = pg_fetch_row($res_budget)[0] ?? 30000;

$remaining_days = date('t') - date('j') + 1;
$today_budget = floor(($monthly_limit - $total_spent) / $remaining_days);

$sql_today_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date(created_at) = current_date";
$res_today_spent = pg_query_params($dbconn, $sql_today_spent, array($user_id));
$today_spent = pg_fetch_row($res_today_spent)[0] ?? 0;
$today_remaining = $today_budget - $today_spent;

// --- 5. グラフデータ集計 ---
$sql_pie = "SELECT satisfaction, SUM(amount) as sum_amount FROM transactions WHERE user_id = $1 GROUP BY satisfaction";
$res_pie = pg_query_params($dbconn, $sql_pie, array($user_id));
$pie_data = [0, 0, 0, 0, 0];
while ($row = pg_fetch_assoc($res_pie)) {
    $idx = intval($row['satisfaction']) - 1;
    if ($idx >= 0 && $idx < 5) $pie_data[$idx] = intval($row['sum_amount']);
}

$sql_bar = "SELECT to_char(created_at, 'MM/DD') as day_str, SUM(amount) as total FROM transactions WHERE user_id = $1 AND created_at > (current_date - interval '7 days') GROUP BY day_str ORDER BY day_str ASC";
$res_bar = pg_query_params($dbconn, $sql_bar, array($user_id));
$bar_labels = []; $bar_data = [];
while ($row = pg_fetch_assoc($res_bar)) {
    $bar_labels[] = $row['day_str'];
    $bar_data[] = intval($row['total']);
}

// カレンダーイベント
$sql_cal = "SELECT id, description, amount, satisfaction, to_char(created_at, 'YYYY-MM-DD') as date_str FROM transactions WHERE user_id = $1";
$res_cal = pg_query_params($dbconn, $sql_cal, array($user_id));
$cal_events = [];
if ($res_cal) {
    while ($row = pg_fetch_assoc($res_cal)) {
        $cal_events[] = [
            'id' => $row['id'],
            'title' => $row['amount'] . '円',
            'start' => $row['date_str'],
            'description' => $row['description'],
            'satisfaction' => $row['satisfaction']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>家計簿AI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { font-family: 'Hiragino Kaku Gothic ProN', sans-serif; margin: 0; background: #f0f2f5; overflow: hidden; }
        .swiper { width: 100%; height: 100vh; }
        .swiper-slide { height: 100vh; overflow-y: auto; padding-bottom: 100px; box-sizing: border-box; }
        .container { padding: 20px; max-width: 600px; margin: 0 auto; }
        
        /* ★ユーザー情報ヘッダー */
        .header-info { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; background: white; font-size: 0.85rem; color: #666; }
        .logout-btn { color: #764ba2; text-decoration: none; font-weight: bold; border: 1px solid #764ba2; padding: 4px 10px; border-radius: 5px; }

        .card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .budget-box { text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 25px; margin-bottom: 25px; }
        
        /* チャット吹き出し */
        .chat-bubble { position: relative; padding: 15px; border-radius: 18px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 12px; max-width: 90%; line-height: 1.6; border-bottom-left-radius: 2px; font-size: 0.95rem; }
        .chat-time { font-size: 0.8rem; color: #888; margin: 20px 0 5px 5px; }

        #tooltip { position: fixed; background: rgba(0,0,0,0.9); color: white; padding: 10px; border-radius: 8px; display: none; z-index: 10000; pointer-events: none; font-size: 0.8rem; }
        #editModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 11000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 400px; }

        input, select, button { padding: 12px; margin: 8px 0; border-radius: 10px; border: 1px solid #ddd; width: 100%; box-sizing: border-box; }
        .bottom-nav { position: fixed; bottom: 0; width: 100%; height: 70px; background: white; display: flex; border-top: 1px solid #ddd; z-index: 1000; }
        .nav-item { flex: 1; border: none; background: none; color: #aaa; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.8rem; cursor: pointer; }
        .nav-item.active { color: #764ba2; font-weight: bold; }
    </style>
</head>
<body>

<div class="header-info">
    <span>👤 <?php echo htmlspecialchars($ems); ?> さん</span>
    <a href="logout.php" class="logout-btn">ログアウト</a>
</div>

<div class="swiper">
    <div class="swiper-wrapper">
        <div class="swiper-slide" style="background: #f8f9ff;">
            <div class="container">
                <h2 style="text-align:center;">🤖 AI相談履歴</h2>
                <div class="card" style="border: 2px solid #764ba2; text-align: center;">
                    <p style="margin:0 0 10px; font-weight:bold;">最新の状況をGeminiに相談</p>
                    <form action="" method="post"> <button type="submit" name="run_ai" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border:none; border-radius:25px; cursor:pointer;">✨ AIにアドバイスを貰う</button>
                    </form>
                </div>
                <div class="chat-container">
                    <?php foreach ($chat_logs as $log): ?>
                        <div class="chat-time">🤖 AIアドバイス (<?php echo $log['time_str']; ?>)</div>
                        <?php 
                        // アドバイス内の「---」を探して分割し、それぞれを吹き出しにする
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
        </div>

        <div class="swiper-slide">
            <div class="container">
                <div class="budget-box">
                    <div style="font-size: 1.1rem; opacity: 0.9;">今日使えるお金</div>
                    <div style="font-size: 3.5rem; font-weight: bold;"><?php echo number_format($today_remaining); ?>円</div>
                </div>
                <div class="card">
                    <h3 style="margin:0 0 10px;">支出を記録</h3>
                    <form action="add_action.php" method="post">
                        <input type="text" name="description" placeholder="何に使った？" required>
                        <input type="number" name="amount" placeholder="金額" required>
                        <select name="satisfaction">
                            <option value="5">星5：最高！</option><option value="4">星4：満足</option>
                            <option value="3" selected>星3：普通</option><option value="2">星2：微妙</option>
                            <option value="1">星1：後悔...</option>
                        </select>
                        <button type="submit" style="background: #764ba2; color:white; border:none;">記録する</button>
                    </form>
                </div>
                <div class="card"><div id="calendar"></div></div>
            </div>
        </div>

        <div class="swiper-slide" style="background: white;">
            <div class="container">
                <h2 style="text-align:center;">📊 分析レポート</h2>
                <div class="card" style="height:300px;"><canvas id="pieChart"></canvas></div>
                <div class="card" style="height:300px;"><canvas id="barChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <div class="nav-item" onclick="mainSwiper.slideTo(0)">💬 AI</div>
    <div class="nav-item active" onclick="mainSwiper.slideTo(1)">🏠 ホーム</div>
    <div class="nav-item" onclick="mainSwiper.slideTo(2)">📈 分析</div>
</nav>

<div id="tooltip"></div>
<div id="editModal">
    <div class="modal-content">
        <h3>支出の編集</h3>
        <form action="edit_action.php" method="post">
            <input type="hidden" name="id" id="edit-id">
            <label>内容</label>
            <input type="text" name="description" id="edit-desc" required>
            <label>金額</label>
            <input type="number" name="amount" id="edit-amount" required>
            <label>満足度</label>
            <select name="satisfaction" id="edit-sat">
                <option value="5">星5</option><option value="4">星4</option>
                <option value="3">星3</option><option value="2">星2</option>
                <option value="1">星1</option>
            </select>
            <button type="submit" style="background: #764ba2; color: white; border: none;">更新する</button>
            <button type="button" onclick="closeModal()" style="background: #eee; border: none;">キャンセル</button>
        </form>
    </div>
</div>

<script>
    window.APP_DATA = {
        events: <?php echo json_encode($cal_events); ?>,
        pie: <?php echo json_encode($pie_data); ?>,
        barLabels: <?php echo json_encode($bar_labels); ?>,
        barData: <?php echo json_encode($bar_data); ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="js/script.js"></script>

</body>
</html>
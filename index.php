<?php
session_start();

// 1. DB接続
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

// 2. ログインチェック (セッションがなければログイン画面へ)
if (!isset($_SESSION['user_id'])) {
    header('location: ./login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ems = $_SESSION['ems'];

// --- 3. チャット履歴の管理ロジック ---
if (isset($_SESSION['ai_comment'])) {
    // 履歴を保存する配列がなければ作成
    if (!isset($_SESSION['chat_log'])) {
        $_SESSION['chat_log'] = [];
    }
    // 新しいコメントを履歴の先頭に追加 [時間, 内容]
    array_unshift($_SESSION['chat_log'], [
        'time' => date('H:i'),
        'comment' => $_SESSION['ai_comment']
    ]);
    // セッションの元データは消去（リロードで増えないように）
    unset($_SESSION['ai_comment']);
}

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

// --- 5. グラフデータ集計（分析画面用） ---
// 満足度
$sql_pie = "SELECT satisfaction, SUM(amount) as sum_amount FROM transactions WHERE user_id = $1 GROUP BY satisfaction";
$res_pie = pg_query_params($dbconn, $sql_pie, array($user_id));
$pie_data = [0, 0, 0, 0, 0];
while ($row = pg_fetch_assoc($res_pie)) {
    $idx = intval($row['satisfaction']) - 1;
    if ($idx >= 0 && $idx < 5) $pie_data[$idx] = intval($row['sum_amount']);
}
$json_pie_data = json_encode($pie_data);

// 過去7日間
$sql_bar = "SELECT to_char(created_at, 'MM/DD') as day_str, SUM(amount) as total FROM transactions WHERE user_id = $1 AND created_at > (current_date - interval '7 days') GROUP BY day_str ORDER BY day_str ASC";
$res_bar = pg_query_params($dbconn, $sql_bar, array($user_id));
$bar_labels = []; $bar_data = [];
while ($row = pg_fetch_assoc($res_bar)) {
    $bar_labels[] = $row['day_str'];
    $bar_data[] = intval($row['total']);
}
$json_bar_labels = json_encode($bar_labels);
$json_bar_data = json_encode($bar_data);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>家計簿AI - PCモード</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Hiragino Kaku Gothic ProN', sans-serif; margin: 0; background: #f0f2f5; overflow: hidden; }
        .swiper { width: 100%; height: 100vh; }
        .swiper-slide { height: 100vh; overflow-y: auto; padding-bottom: 80px; box-sizing: border-box; }
        .container { padding: 40px 20px; max-width: 600px; margin: 0 auto; }
        
        /* パーツ設定 */
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .budget-box { text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 25px; margin-bottom: 25px; }
        
        /* チャットスタイル */
        .chat-container { display: flex; flex-direction: column; gap: 15px; }
        .chat-bubble { padding: 15px 20px; border-radius: 20px; max-width: 80%; line-height: 1.6; position: relative; }
        .ai-msg { background: #ffffff; color: #333; align-self: flex-start; border: 1px solid #e0e0e0; border-bottom-left-radius: 2px; }
        .chat-time { font-size: 0.7rem; color: #999; margin-bottom: 5px; }

        /* 入力フォーム */
        input, select, button { padding: 12px; margin: 8px 0; border-radius: 10px; border: 1px solid #ddd; width: 100%; box-sizing: border-box; font-size: 1rem; }
        button { background: #3498db; color: white; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        button:hover { opacity: 0.8; }

        /* ナビゲーション */
        .bottom-nav { position: fixed; bottom: 0; width: 100%; height: 70px; background: white; display: flex; border-top: 1px solid #ddd; z-index: 1000; }
        .nav-item { flex: 1; border: none; background: none; color: #aaa; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.8rem; }
        .nav-item.active { color: #764ba2; font-weight: bold; }
    </style>
</head>
<body>

<div class="swiper">
    <div class="swiper-wrapper">
        
        <div class="swiper-slide" style="background: #f8f9ff;">
            <div class="container">
                <h2 style="text-align:center; color: #2c3e50;">🤖 AI相談履歴</h2>
                <form action="get_daily_advice.php" method="post" style="margin-bottom: 30px;">
                    <button type="submit" style="background: #ff9800;">✨ 今日の支出を診断する</button>
                </form>

                <div class="chat-container">
                    <?php if (isset($_SESSION['chat_log']) && count($_SESSION['chat_log']) > 0): ?>
                        <?php foreach ($_SESSION['chat_log'] as $log): ?>
                            <div class="chat-time"><?php echo $log['time']; ?>のアドバイス</div>
                            <div class="chat-bubble ai-msg">
                                <?php echo nl2br(htmlspecialchars($log['comment'])); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; color: #bbb; margin-top: 50px;">
                            診断を受けると、ここにアドバイスが蓄積されます。
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="swiper-slide">
            <div class="container">
                <div style="text-align: right; margin-bottom: 10px;">
                    <small>ログイン中: <?php echo htmlspecialchars($ems); ?></small> | 
                    <a href="logout.php" style="color: #e74c3c; text-decoration: none; font-size: 0.8rem;">ログアウト</a>
                </div>

                <div class="budget-box">
                    <div style="font-size: 1rem; opacity: 0.9;">今日あと使えるお金</div>
                    <div style="font-size: 3.5rem; font-weight: bold; margin: 10px 0;"><?php echo number_format($today_remaining); ?>円</div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">1日の目標目安: <?php echo number_format($today_budget); ?>円</div>
                </div>

                <div class="card">
                    <h3 style="margin-top:0;">新しい支出を記録</h3>
                    <form action="add_action.php" method="post">
                        <input type="text" name="description" placeholder="例：カフェ代" required>
                        <input type="number" name="amount" placeholder="金額" required>
                        <select name="satisfaction">
                            <option value="5">星5：最高！</option>
                            <option value="4">星4：満足</option>
                            <option value="3" selected>星3：普通</option>
                            <option value="2">星2：微妙</option>
                            <option value="1">星1：後悔...</option>
                        </select>
                        <button type="submit" style="background: #764ba2;">記録する</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="swiper-slide" style="background: white;">
            <div class="container">
                <h2 style="text-align:center; color: #2c3e50;">📊 分析レポート</h2>
                
                <div class="card">
                    <h3 style="font-size: 1rem; color: #666; margin-top: 0;">満足度別の支出（合計額）</h3>
                    <div style="height: 250px;">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <h3 style="font-size: 1rem; color: #666; margin-top: 0;">直近1週間の支出推移</h3>
                    <div style="height: 250px;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<nav class="bottom-nav">
    <button class="nav-item" onclick="swiper.slideTo(0)" id="nav0">💬 AI履歴</button>
    <button class="nav-item active" onclick="swiper.slideTo(1)" id="nav1">🏠 ホーム</button>
    <button class="nav-item" onclick="swiper.slideTo(2)" id="nav2">📈 分析</button>
</nav>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    // スライドの設定
    const swiper = new Swiper('.swiper', {
        initialSlide: 1,
        speed: 400,
        on: {
            slideChange: function () {
                document.querySelectorAll('.nav-item').forEach((btn, i) => {
                    btn.classList.toggle('active', i === this.activeIndex);
                });
            }
        }
    });

    // ドーナツグラフ
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['星1', '星2', '星3', '星4', '星5'],
            datasets: [{
                data: <?php echo $json_pie_data; ?>,
                backgroundColor: ['#e0e0e0', '#90a4ae', '#4db6ac', '#ffca28', '#ff9800'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    // 棒グラフ
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $json_bar_labels; ?>,
            datasets: [{
                label: '支出(円)',
                data: <?php echo $json_bar_data; ?>,
                backgroundColor: '#667eea',
                borderRadius: 8
            }]
        },
        options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
</script>
</body>
</html>
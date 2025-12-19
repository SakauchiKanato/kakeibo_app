<?php
// 1. ログイン・DB接続・計算ロジック（今までのものをそのまま維持）
if (empty($_SERVER['HTTPS'])) {
    header("location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
session_start();
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

if (!isset($_SESSION['user_id'])) {
    header('location: ./login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$ems = $_SESSION['ems'];

// --- 計算ロジック ---
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

// 履歴取得
$sql_history = "SELECT id, description, amount, satisfaction, created_at FROM transactions WHERE user_id = $1 ORDER BY created_at DESC LIMIT 10";
$res_history = pg_query_params($dbconn, $sql_history, array($user_id));
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>家計簿AI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <style>
        /* CSS: アプリ全体のスタイル */
        body { font-family: sans-serif; margin: 0; background: #f0f2f5; overflow: hidden; }
        .swiper { width: 100%; height: 100vh; }
        .swiper-slide { height: 100vh; overflow-y: auto; padding-bottom: 80px; box-sizing: border-box; }
        .container { padding: 20px; max-width: 500px; margin: 0 auto; }
        
        /* 既存のオシャレパーツ */
        .card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .budget-box { text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 20px; margin-bottom: 20px; }
        .budget-amount { font-size: 2.5rem; font-weight: bold; margin: 10px 0; }
        input, select, button { padding: 12px; margin: 5px 0; font-size: 1rem; border-radius: 8px; border: 1px solid #ddd; width: 100%; box-sizing: border-box; }
        button { background-color: #3498db; color: white; border: none; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 12px 5px; border-bottom: 1px solid #eee; }
        .stars { color: #ffca28; }
        .delete-btn { color: #e74c3c; text-decoration: none; font-size: 0.8rem; border: 1px solid #e74c3c; padding: 2px 5px; border-radius: 4px; }

        /* ナビゲーション */
        .bottom-nav { position: fixed; bottom: 0; width: 100%; height: 70px; background: white; display: flex; border-top: 1px solid #ddd; z-index: 1000; }
        .nav-item { flex: 1; border: none; background: none; color: #888; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.7rem; }
        .nav-item.active { color: #764ba2; font-weight: bold; }
    </style>
</head>
<body>

<div class="swiper">
    <div class="swiper-wrapper">
        
        <div class="swiper-slide" style="background: #f9f9ff;">
            <div class="container">
                <h2 style="text-align:center;">🤖 AI相談</h2>
                <div class="card" style="text-align:center;">
                    <p>今日の支出からアドバイスをもらいましょう</p>
                    <form action="get_daily_advice.php" method="post">
                        <button type="submit" style="background: #ff9800; border-radius: 30px;">
                            🌙 今日の満足度診断を受ける
                        </button>
                    </form>
                </div>
                
                <?php if (isset($_SESSION['ai_comment'])): ?>
                <div class="card" style="background: #e3f2fd; border-left: 5px solid #2196f3;">
                    <strong>🤖 AIレポート:</strong><br>
                    <p style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($_SESSION['ai_comment'])); ?></p>
                    <?php unset($_SESSION['ai_comment']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="swiper-slide">
            <div class="container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <small>👤 <?php echo htmlspecialchars($ems); ?></small>
                    <a href="logout.php" style="color:#e74c3c; text-decoration:none; font-size: 0.8rem;">ログアウト</a>
                </div>

                <div class="budget-box">
                    <div style="font-size: 0.9rem; opacity: 0.9;">今日あと使えるお金</div>
                    <div class="budget-amount"><?php echo number_format($today_remaining); ?>円</div>
                    <div style="font-size: 0.7rem; opacity: 0.8;">目標予算: <?php echo number_format($today_budget); ?>円</div>
                </div>

                <div style="text-align: right; margin-bottom: 10px;">
                    <a href="settings.php" style="text-decoration: none; font-size: 0.8rem; color: #764ba2;">⚙️ 予算設定</a>
                </div>

                <div class="card">
                    <form action="add_action.php" method="post">
                        <input type="text" name="description" placeholder="何に使った？" required>
                        <input type="number" name="amount" placeholder="金額（円）" required>
                        <select name="satisfaction">
                            <option value="5">最高！(5)</option><option value="4">満足(4)</option>
                            <option value="3" selected>普通(3)</option><option value="2">微妙(2)</option><option value="1">後悔...(1)</option>
                        </select>
                        <button type="submit">記録する</button>
                    </form>
                </div>

                <div class="card">
                    <h3>最近の履歴</h3>
                    <table>
                        <?php while ($row = pg_fetch_assoc($res_history)): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['description']); ?></strong><br>
                                <span class="stars"><?php echo str_repeat("★", $row['satisfaction']); ?></span>
                            </td>
                            <td style="text-align:right;">
                                <?php echo number_format($row['amount']); ?>円<br>
                                <a href="delete_action.php?id=<?php echo $row['id']; ?>" onclick="return confirm('消去しますか？')" class="delete-btn">削除</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="swiper-slide" style="background: white;">
            <div class="container">
                <h2 style="text-align:center;">📊 分析レポート</h2>
                <div class="card" style="text-align:center; color:#999; padding: 50px 20px;">
                    ここには今後グラフが表示されます。<br>お楽しみに！
                </div>
            </div>
        </div>

    </div>
</div>

<nav class="bottom-nav">
    <button class="nav-item" onclick="swiper.slideTo(0)" id="nav0">💬<span>AI相談</span></button>
    <button class="nav-item active" onclick="swiper.slideTo(1)" id="nav1">🏠<span>ホーム</span></button>
    <button class="nav-item" onclick="swiper.slideTo(2)" id="nav2">📈<span>分析</span></button>
</nav>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    const swiper = new Swiper('.swiper', {
        initialSlide: 1,
        speed: 300,
        on: {
            slideChange: function () {
                document.querySelectorAll('.nav-item').forEach((btn, i) => {
                    btn.classList.toggle('active', i === this.activeIndex);
                });
            }
        }
    });
</script>
</body>
</html>
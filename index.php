<?php
// --- (中略：冒頭のHTTPSリダイレクト・セッション・ログインチェック部分はそのまま) ---
if (empty($_SERVER['HTTPS'])) {
  header("location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}
session_start();  

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('Could not connect: ' . pg_last_error());

// ...ログイン確認ロジック... (省略しますが、あなたのコードのままで大丈夫です)
if (isset($_SESSION['ems'])) { $ems=$_SESSION['ems']; }
if (isset($_SESSION['pws'])) { $pws=$_SESSION['pws']; }
if (isset($_POST['emf'])){$ems=$_POST['emf'];}
if (isset($_POST['pwf'])){$pws=$_POST['pwf'];}
$aflag=0;
if (isset($ems) &&isset($pws)){
  $sql="select * from users where email='". $ems . "';";
  $result = pg_query($dbconn, $sql) or die('Query failed: ' . pg_last_error());
  if(pg_num_rows($result)==1){
    $row = pg_fetch_row($result);
    if (password_verify($pws, $row[2])){
      $_SESSION['user_id'] = $row[0];
      $_SESSION['ems']=$ems;
      $_SESSION['pws']=$pws;
      $aflag=1;
    }
  }
}
if($aflag==0){ header('location: ./login.php'); }

// --- 予算計算ロジック ---
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($_SESSION['user_id']));
$row_sum = pg_fetch_row($res_sum);
$total_spent = $row_sum[0] ?? 0;

$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($_SESSION['user_id']));
$row_budget = pg_fetch_row($res_budget);
$monthly_limit = $row_budget[0] ?? 30000;

$days_in_month = date('t');
$today = date('j');
$remaining_days = $days_in_month - $today + 1;

$remaining_budget = $monthly_limit - $total_spent;
$today_budget = floor($remaining_budget / $remaining_days);

$sql_today_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date(created_at) = current_date";
$res_today_spent = pg_query_params($dbconn, $sql_today_spent, array($_SESSION['user_id']));
$row_today_spent = pg_fetch_row($res_today_spent);
$today_spent = $row_today_spent[0] ?? 0;
$today_remaining = $today_budget - $today_spent;

// ★【重要：ここが抜けていました】履歴データを取得する命令
$sql_history = "SELECT id, description, amount, satisfaction, created_at FROM transactions WHERE user_id = $1 ORDER BY created_at DESC LIMIT 10";
$res_history = pg_query_params($dbconn, $sql_history, array($_SESSION['user_id']));

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Budget</title>
    <style>
        /* オシャレCSSを統合 */
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f0f2f5; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .budget-box { text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 20px; margin-bottom: 20px; }
        .budget-amount { font-size: 3rem; font-weight: bold; margin: 10px 0; }
        input, select, button { padding: 12px; margin: 5px 0; font-size: 1rem; border-radius: 8px; border: 1px solid #ddd; width: 100%; box-sizing: border-box; }
        button { background-color: #3498db; color: white; border: none; cursor: pointer; font-weight: bold; }
        button:hover { opacity: 0.9; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; color: #777; font-size: 0.8rem; padding: 10px; border-bottom: 2px solid #eee; }
        td { padding: 12px 10px; border-bottom: 1px solid #eee; }
        .stars { color: #ffca28; }
        .delete-btn { color: #e74c3c; text-decoration: none; font-size: 0.8rem; border: 1px solid #e74c3c; padding: 2px 8px; border-radius: 4px; }
        #historySearch { margin-bottom: 15px; background: #f8f9fa; }
    </style>
</head>
<body>

    <div class="header">
        <span>👤 <?php echo htmlspecialchars($ems); ?></span>
        <a href="logout.php" style="color:#e74c3c; text-decoration:none;">ログアウト</a>
    </div>

    <div class="budget-box">
        <div style="font-size: 1.1rem; opacity: 0.9;">今日あと使えるお金</div>
        <div class="budget-amount"><?php echo number_format($today_remaining); ?>円</div>
        <div style="font-size: 0.8rem; opacity: 0.8;">目標: <?php echo number_format($today_budget); ?>円</div>
    </div>

    <div class="card">
        <h3>記録する</h3>
        <form action="add_action.php" method="post">
            <input type="text" name="description" placeholder="何に使った？" required>
            <input type="number" name="amount" placeholder="金額（円）" required>
            <div style="margin-top:10px;">
                <label style="font-size: 0.9rem; color: #666;">満足度：</label>
                <select name="satisfaction">
                    <option value="5">最高！(5)</option>
                    <option value="4">満足(4)</option>
                    <option value="3" selected>普通(3)</option>
                    <option value="2">微妙(2)</option>
                    <option value="1">後悔...(1)</option>
                </select>
            </div>
            <button type="submit">保存する</button>
        </form>
    </div>

    <div class="card">
        <h3>支出履歴</h3>
        <input type="text" id="historySearch" onkeyup="filterHistory()" placeholder="🔍 履歴を検索...">
        <table>
            <thead>
                <tr>
                    <th>内容</th>
                    <th>金額</th>
                    <th style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = pg_fetch_assoc($res_history)): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['description']); ?></strong><br>
                        <span class="stars"><?php echo str_repeat("★", $row['satisfaction']); ?></span>
                    </td>
                    <td><?php echo number_format($row['amount']); ?>円</td>
                    <td style="text-align:right;">
                        <a href="delete_action.php?id=<?php echo $row['id']; ?>" 
                           onclick="return confirm('削除しますか？')" class="delete-btn">削除</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div style="text-align: center; margin-bottom: 30px;">
        <form action="get_daily_advice.php" method="post">
            <button type="submit" style="background: #ff9800; width: auto; padding: 12px 30px; border-radius: 30px; box-shadow: 0 4px 15px rgba(255,152,0,0.3);">
                🌙 今日の満足度診断を受ける
            </button>
        </form>
    </div>

    <?php if (isset($_SESSION['ai_comment'])): ?>
        <div class="card" style="background: #e3f2fd; border-left: 5px solid #2196f3;">
            <strong>🤖 AIの診断レポート</strong><br>
            <p style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($_SESSION['ai_comment'])); ?></p>
            <?php unset($_SESSION['ai_comment']); ?>
        </div>
    <?php endif; ?>

    <script>
    function filterHistory() {
        const input = document.getElementById('historySearch');
        const filter = input.value.toLowerCase();
        const rows = document.querySelector('tbody').getElementsByTagName('tr');
        for (let row of rows) {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
        }
    }
    </script>
</body>
</html>
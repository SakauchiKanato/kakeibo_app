<?php

if (empty($_SERVER['HTTPS'])) {
  header("location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}
session_start();  

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('Could not connect: ' . pg_last_error());

if (isset($_SESSION['ems'])) {
  $ems=$_SESSION['ems'];
}
if (isset($_SESSION['pws'])) {
  $pws=$_SESSION['pws'];
}
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
if($aflag==0){
  header('location: ./login.php');
}


// ① 今月の合計支出を取得
$sql_sum = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date_trunc('month', created_at) = date_trunc('month', current_timestamp)";
$res_sum = pg_query_params($dbconn, $sql_sum, array($_SESSION['user_id']));
$row_sum = pg_fetch_row($res_sum);
$total_spent = $row_sum[0] ?? 0;

// ② 月の予算設定を取得
$sql_budget = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_budget = pg_query_params($dbconn, $sql_budget, array($_SESSION['user_id']));
$row_budget = pg_fetch_row($res_budget);
$monthly_limit = $row_budget[0] ?? 30000; // 設定がなければ3万円とする

// ③ 残り日数を計算
$days_in_month = date('t');    // 今月が何日あるか（30 or 31）
$today = date('j');            // 今日は何日か
$remaining_days = $days_in_month - $today + 1; // 今日を含めた残り日数

// ④ 「今日の予算」を計算！
$remaining_budget = $monthly_limit - $total_spent;
$today_budget = floor($remaining_budget / $remaining_days);

$sql_today_spent = "SELECT SUM(amount) FROM transactions WHERE user_id = $1 AND date(created_at) = current_date";
$res_today_spent = pg_query_params($dbconn, $sql_today_spent, array($_SESSION['user_id']));
$row_today_spent = pg_fetch_row($res_today_spent);
$today_spent = $row_today_spent[0] ?? 0;

// 「今日の予算（残り）」を計算
$today_remaining = $today_budget - $today_spent;

?>




<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Budget</title>
    <style>
        body { font-family: sans-serif; text-align: center; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .budget-box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .budget-label { font-size: 1.2rem; color: #666; }
        .budget-amount { font-size: 4rem; font-weight: bold; color: #2c3e50; margin: 10px 0; }
        .input-area { background: white; padding: 20px; border-radius: 15px; }
        input, button { padding: 10px; margin: 5px; font-size: 1rem; }
        button { background-color: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        a.logout { color: #e74c3c; text-decoration: none; }
    </style>
</head>
<body>

    <div class="header">
        <span>こんにちは、<?php echo htmlspecialchars($ems); ?>さん</span>
        <a href="logout.php" class="logout">ログアウト</a>
    </div>

  <div class="budget-box">
    <div class="budget-label">今日あと使えるお金</div>
    <div class="budget-amount"><?php echo number_format($today_remaining); ?>円</div>
    <div style="color: #666;">（今日の目標予算: <?php echo number_format($today_budget); ?>円）</div>
</div>

<div class="input-area">
    <form action="add_action.php" method="post">
        <input type="text" name="description" placeholder="何に使った？" required>
        <input type="number" name="amount" placeholder="金額（円）" required>
        <br>
        <label>満足度：</label>
        <select name="satisfaction">
            <option value="5">最高！(5)</option>
            <option value="4">満足(4)</option>
            <option value="3" selected>普通(3)</option>
            <option value="2">微妙(2)</option>
            <option value="1">後悔...(1)</option>
        </select>
        <button type="submit">記録する</button>
    </form>
</div>

<hr>

<h3>最近の支出履歴</h3>
<table border="1" style="width:100%; border-collapse: collapse; background: white;">
    <tr style="background: #eee;">
        <th>内容</th><th>金額</th><th>満足度</th><th>日時</th><th>操作</th> </tr>
    <?php
    // idも取得するようにSQLを変更します
    $sql_history = "SELECT id, description, amount, satisfaction, created_at FROM transactions WHERE user_id = $1 ORDER BY created_at DESC LIMIT 10";
    $res_history = pg_query_params($dbconn, $sql_history, array($_SESSION['user_id']));
    
    while ($row = pg_fetch_assoc($res_history)): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo number_format($row['amount']); ?>円</td>
            <td><?php echo str_repeat("⭐️", $row['satisfaction']); ?></td>
            <td><?php echo date('H:i', strtotime($row['created_at'])); ?></td>
            <td style="text-align: center;">
                <a href="delete_action.php?id=<?php echo $row['id']; ?>" 
                   onclick="return confirm('この記録を削除してもよろしいですか？')" 
                   style="color: red; text-decoration: none; font-size: 0.8em;">[削除]</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<div style="margin-top: 20px; text-align: center;">
    <form action="get_daily_advice.php" method="post">
        <button type="submit" style="background: #ff9800; color: white; padding: 10px 20px;">
            🌙 今日の満足度診断を受ける
        </button>
    </form>
</div>

<?php if (isset($_SESSION['ai_comment'])): ?>
    <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin-top: 20px;">
        <strong>🤖 今日のAI総評:</strong><br>
        <?php echo nl2br(htmlspecialchars($_SESSION['ai_comment'])); ?>
        <?php unset($_SESSION['ai_comment']); // 一度表示したら消す ?>
    </div>
<?php endif; ?>

</body>
</html>

<?php
session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

$user_id = $_SESSION['user_id'];
$ems = $_SESSION['ems'];

// 最新のユーザー名を取得
$sql_user = "SELECT username FROM users WHERE user_id = $1";
$res_user = pg_query_params($dbconn, $sql_user, array($user_id));
$row_user = pg_fetch_assoc($res_user);
$db_username = $row_user['username'] ?? '';

$_SESSION['username'] = $db_username;
$username = (!empty($db_username)) ? $db_username : $ems;

// 目標リスト取得
$sql_goals = "SELECT * FROM savings_goals 
              WHERE user_id = $1 
              ORDER BY is_completed ASC, deadline ASC";
$res_goals = pg_query_params($dbconn, $sql_goals, array($user_id));
$goals = pg_fetch_all($res_goals) ?: [];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>目標設定 - Money Partner (マネ・パト)</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/goals.css">
</head>
<body class="goals-page">


<div class="header">
    <div class="header-left">
        <a href="index.php" class="logo">💰 Money Partner (マネ・パト)</a>
        <div class="user-info"><?php echo htmlspecialchars($username); ?> さん</div>
    </div>
    <div style="display: flex; align-items: center;">
        <button class="info-btn" onclick="openHelpModal()" title="使いかたガイド">❓</button>
        <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
        <a href="logout.php" class="logout-btn">ログアウト</a>
    </div>
</div>

<div class="container">
    <a href="index.php" class="back-link">← ホームに戻る</a>
    <h2>🎯 貯金目標</h2>
    
    <?php if (count($goals) > 0): ?>
        <?php foreach ($goals as $goal): ?>
            <?php 
            $progress = $goal['target_amount'] > 0 ? ($goal['current_amount'] / $goal['target_amount']) * 100 : 0;
            $progress = min(100, $progress);
            $remaining = $goal['target_amount'] - $goal['current_amount'];
            
            $days_left = '期限なし';
            if ($goal['deadline']) {
                $target_date = new DateTime($goal['deadline']);
                $today = new DateTime();
                $diff = $today->diff($target_date);
                if ($diff->invert) {
                    $days_left = '期限切れ';
                } else {
                    $days_left = '残り' . $diff->days . '日';
                }
            }
            ?>
            <div class="card goal-item <?php echo $goal['is_completed'] ? 'completed' : ''; ?>">
                <div class="goal-header">
                    <div class="goal-title">
                        <span style="font-size: 1.5rem;"><?php echo htmlspecialchars($goal['icon'] ?: '🎯'); ?></span>
                        <span><?php echo htmlspecialchars($goal['goal_name']); ?></span>
                    </div>
                    <div class="goal-amount">
                        <div class="current-amount"><?php echo number_format($goal['current_amount']); ?>円</div>
                        <div class="target-amount">目標: <?php echo number_format($goal['target_amount']); ?>円</div>
                    </div>
                </div>
                
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                </div>
                
                <div class="goal-footer">
                    <span>進捗: <?php echo round($progress); ?>%</span>
                    <span class="deadline-badge"><?php echo $days_left; ?></span>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <form action="goal_action.php" method="post" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                        <input type="number" name="add_amount" placeholder="金額" style="width: 100px; margin-bottom: 0; padding: 0.5rem;">
                        <button type="submit" name="action" value="add_fund" class="primary" style="padding: 0.5rem 1rem;">貯金</button>
                    </form>
                    <form action="goal_action.php" method="post" onsubmit="return confirm('本当に削除しますか？');">
                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                        <button type="submit" name="action" value="delete" style="background: var(--accent); color: white; border: none; padding: 0.5rem 1rem; border-radius: 12px; cursor: pointer; font-weight: 600;">削除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <p style="color: var(--text-light); font-size: 1.1rem; margin-bottom: 1rem;">まだ目標がありません</p>
            <p style="color: var(--text-light); font-size: 0.9rem;">右下の + ボタンから新しい目標を追加しましょう！</p>
        </div>
    <?php endif; ?>
</div>

<button class="add-btn" onclick="openGoalModal()">+</button>

<div id="goalModal" class="modal">
    <div class="modal-content">
        <h3>新しい目標を追加</h3>
        <form action="goal_action.php" method="post">
            <label>目標の名前</label>
            <input type="text" name="goal_name" placeholder="例: 旅行、新しいPC" required>
            
            <label>目標金額 (円)</label>
            <input type="number" name="target_amount" placeholder="50000" required>
            
            <label>期限</label>
            <input type="date" name="deadline">
            
            <label>アイコン (絵文字など)</label>
            <input type="text" name="icon" placeholder="🎯" value="🎯">
            
            <div class="btn-group">
                <button type="button" onclick="closeGoalModal()" class="secondary">戻る</button>
                <button type="submit" name="action" value="create" class="primary">目標を作成</button>
            </div>
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
        
        <button type="button" onclick="openHelpModal()" style="display:none;"></button> <!-- ダミー -->
        <button type="button" onclick="closeHelpModal()" style="width: 100%; background: var(--primary); color: white; border: none; border-radius: 12px; padding: 0.75rem; font-weight: 600; cursor: pointer;">閉じる</button>
    </div>
</div>

<script src="js/script.js"></script>
<script src="js/goals.js"></script>

</body>
</html>

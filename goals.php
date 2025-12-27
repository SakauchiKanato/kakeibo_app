<?php
session_start();
require 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ems = $_SESSION['ems'];

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP") or die('接続失敗');

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
    <title>目標設定 - 家計簿AI</title>
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
            min-height: 100vh;
            padding-bottom: 2rem;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* ヘッダー */
        .header {
            background: var(--card-bg);
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
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
            text-decoration: none;
        }
        
        .logout-btn {
            padding: 0.4rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .theme-toggle {
            padding: 0.4rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .info-btn {
            padding: 0.4rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.2s;
            margin-left: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .info-btn:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            transition: transform 0.2s;
        }

        .goal-item.completed {
            opacity: 0.8;
            border-left: 5px solid var(--secondary);
        }

        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .goal-title {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .goal-amount {
            text-align: right;
        }

        .current-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .target-amount {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .progress-container {
            margin: 1rem 0;
        }

        .progress-bar {
            height: 10px;
            background: var(--bg);
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 5px;
            transition: width 0.5s ease-out;
        }

        .goal-footer {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        .deadline-badge {
            background: var(--bg);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            color: var(--text);
            font-weight: 500;
        }

        /* 浮遊追加ボタン */
        .add-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 28px;
            font-size: 24px;
            box-shadow: 0 4px 16px rgba(74, 144, 226, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            transition: transform 0.2s;
        }

        .add-btn:hover {
            transform: scale(1.1);
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 24px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .modal-content h3 {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        button.primary {
            flex: 2;
            padding: 0.8rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        button.secondary {
            flex: 1;
            padding: 0.8rem;
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-light);
            text-decoration: none;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <a href="index.php" class="logo">💰 家計簿AI</a>
        <div class="user-info"><?php echo htmlspecialchars($ems); ?> さん</div>
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
<script>
function openGoalModal() {
    document.getElementById('goalModal').style.display = 'flex';
}

function closeGoalModal() {
    document.getElementById('goalModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('goalModal');
    if (event.target == modal) closeGoalModal();
};
</script>

</body>
</html>

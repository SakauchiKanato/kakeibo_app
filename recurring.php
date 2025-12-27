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

// カテゴリー一覧取得
$sql_cats = "SELECT * FROM categories ORDER BY id";
$res_cats = pg_query($dbconn, $sql_cats);
$categories = pg_fetch_all($res_cats);

// 定期支出リスト取得
$sql_recurring = "SELECT r.*, c.name as category_name, c.icon as category_icon, c.color as category_color 
                  FROM recurring_expenses r
                  LEFT JOIN categories c ON r.category_id = c.id
                  WHERE r.user_id = $1
                  ORDER BY r.is_active DESC, r.next_occurrence ASC";
$res_recurring = pg_query_params($dbconn, $sql_recurring, array($user_id));
$recurring_expenses = pg_fetch_all($res_recurring) ?: [];

// 頻度の日本語変換
function frequency_to_japanese($freq) {
    $map = [
        'daily' => '毎日',
        'weekly' => '毎週',
        'monthly' => '毎月',
        'yearly' => '毎年'
    ];
    return $map[$freq] ?? $freq;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>定期支出管理 - Money Partner (マネ・パト)</title>
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

        .recurring-item.inactive {
            opacity: 0.6;
            filter: grayscale(0.5);
        }

        .expense-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .expense-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .category-icon {
            width: 48px;
            height: 48px;
            background: var(--bg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .expense-details h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .expense-meta {
            font-size: 0.85rem;
            color: var(--text-light);
            display: flex;
            gap: 0.75rem;
        }

        .expense-amount {
            text-align: right;
        }

        .amount-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .frequency-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            background: var(--bg);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text);
            margin-top: 0.25rem;
        }

        .next-occurrence {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .occurrence-date {
            font-weight: 600;
            color: var(--secondary);
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
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow-y: auto;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        input, select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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
    <h2>🔄 定期支出管理</h2>
    
    <?php if (count($recurring_expenses) > 0): ?>
        <?php foreach ($recurring_expenses as $expense): ?>
            <div class="card recurring-item <?php echo !$expense['is_active'] ? 'inactive' : ''; ?>">
                <div class="expense-header">
                    <div class="expense-info">
                        <div class="category-icon" style="background-color: <?php echo $expense['category_color']; ?>20; color: <?php echo $expense['category_color']; ?>;">
                            <?php echo $expense['category_icon']; ?>
                        </div>
                        <div class="expense-details">
                            <h3><?php echo htmlspecialchars($expense['description']); ?></h3>
                            <div class="expense-meta">
                                <span><?php echo htmlspecialchars($expense['category_name']); ?></span>
                                <span>満足度: <?php echo str_repeat('⭐', $expense['satisfaction']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="expense-amount">
                        <div class="amount-value"><?php echo number_format($expense['amount']); ?>円</div>
                        <div class="frequency-badge"><?php echo frequency_to_japanese($expense['frequency']); ?></div>
                    </div>
                </div>
                
                <div class="next-occurrence">
                    <span>次回予定日: <span class="occurrence-date"><?php echo $expense['next_occurrence']; ?></span></span>
                    <div style="display: flex; gap: 0.5rem;">
                        <form action="recurring_action.php" method="post">
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                            <?php if ($expense['is_active']): ?>
                                <button type="submit" name="action" value="toggle" class="secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">一時停止</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="toggle" class="primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">再開</button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="delete" style="background: var(--accent); color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 600;" onclick="return confirm('本当に削除しますか？');">削除</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <p style="color: var(--text-light); font-size: 1.1rem; margin-bottom: 1rem;">まだ定期支出がありません</p>
            <p style="color: var(--text-light); font-size: 0.9rem;">右下の + ボタンから追加しましょう！</p>
        </div>
    <?php endif; ?>
</div>

<button class="add-btn" onclick="openRecurringModal()">+</button>

<div id="recurringModal" class="modal">
    <div class="modal-content">
        <h3 style="text-align: center; margin-bottom: 1.5rem;">新しい定期支出を追加</h3>
        <form action="recurring_action.php" method="post">
            <label>内容</label>
            <input type="text" name="description" placeholder="例: Netflix月額料金" required>
            
            <label>金額 (円)</label>
            <input type="number" name="amount" placeholder="1200" required>
            
            <label>カテゴリー</label>
            <select name="category_id">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['icon'] . ' ' . $cat['name']; ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>頻度</label>
            <select name="frequency">
                <option value="monthly">毎月</option>
                <option value="weekly">毎週</option>
                <option value="daily">毎日</option>
                <option value="yearly">毎年</option>
            </select>
            
            <label>開始日</label>
            <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
            
            <label>満足度</label>
            <select name="satisfaction">
                <option value="5">⭐⭐⭐⭐⭐ (非常に満足)</option>
                <option value="4">⭐⭐⭐⭐</option>
                <option value="3" selected>⭐⭐⭐ (普通)</option>
                <option value="2">⭐⭐</option>
                <option value="1">⭐ (不満)</option>
            </select>
            
            <div class="btn-group">
                <button type="button" onclick="closeRecurringModal()" class="secondary">戻る</button>
                <button type="submit" name="action" value="create" class="primary">登録する</button>
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
        
        <button type="button" onclick="closeHelpModal()" style="width: 100%; background: var(--primary); color: white; border: none; border-radius: 12px; padding: 0.75rem; font-weight: 600; cursor: pointer;">閉じる</button>
    </div>
</div>

<script src="js/script.js"></script>
<script>
function openRecurringModal() {
    document.getElementById('recurringModal').style.display = 'flex';
}

function closeRecurringModal() {
    document.getElementById('recurringModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('recurringModal');
    if (event.target == modal) {
        closeRecurringModal();
    }
}
</script>

</body>
</html>

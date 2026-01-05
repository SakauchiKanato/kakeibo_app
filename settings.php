<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
    or die('接続失敗');

$user_id = $_SESSION['user_id'];

// 1. ユーザー情報の取得（最新のユーザー名）
$sql_user = "SELECT username, email FROM users WHERE user_id = $1";
$res_user = pg_query_params($dbconn, $sql_user, array($user_id));
$row_user = pg_fetch_assoc($res_user);
$display_name = $row_user['username'] ?? '';

// 2. 現在の全体予算設定を取得
$sql_limit = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'monthly_limit'";
$res_limit = pg_query_params($dbconn, $sql_limit, array($user_id));
$row_limit = pg_fetch_row($res_limit);
$current_limit = $row_limit[0] ?? 30000;

// 3. デフォルトAIキャラ設定を取得
$sql_ai = "SELECT setting_value FROM budget_settings WHERE user_id = $1 AND setting_key = 'default_ai_char'";
$res_ai = pg_query_params($dbconn, $sql_ai, array($user_id));
$row_ai = pg_fetch_row($res_ai);
$default_ai = $row_ai[0] ?? 'default';

// 4. カテゴリー一覧と各予算設定を取得
$sql_cats = "
    SELECT c.id, c.name, c.icon, cb.monthly_limit as cat_limit 
    FROM categories c
    LEFT JOIN category_budgets cb ON c.id = cb.category_id AND cb.user_id = $1
    ORDER BY c.id
";
$res_cats = pg_query_params($dbconn, $sql_cats, array($user_id));
$categories = pg_fetch_all($res_cats) ?: [];

// 5. クイック入力プリセットを取得
$sql_presets = "SELECT id, label, amount, category_id, icon, satisfaction FROM quick_input_presets WHERE user_id = $1 ORDER BY id";
$res_presets = pg_query_params($dbconn, $sql_presets, array($user_id));
$presets = pg_fetch_all($res_presets) ?: [];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定 - Money Partner (マネ・パト)</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .settings-container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .settings-section { background: var(--card-bg); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border); }
        .settings-section h3 { margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; color: var(--accent); display: flex; align-items: center; gap: 0.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-size: 0.9rem; font-weight: 500; opacity: 0.8; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1rem; }
        .category-budget-list { display: grid; gap: 0.8rem; }
        .category-budget-item { display: flex; align-items: center; gap: 0.8rem; background: rgba(255,255,255,0.03); padding: 0.6rem; border-radius: 8px; }
        .category-budget-item .cat-info { flex: 1; font-size: 0.9rem; }
        .category-budget-item input { width: 100px; padding: 0.4rem; margin-left: auto; }
        .save-btn { width: 100%; padding: 1rem; background: var(--accent); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.3s; margin-top: 1rem; }
        .save-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); opacity: 0.9; }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: var(--text); opacity: 0.6; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { opacity: 1; }
        .preset-item { display: flex; align-items: center; gap: 0.8rem; background: rgba(0,0,0,0.02); padding: 0.8rem; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid var(--border); }
        .preset-info { flex: 1; }
        .delete-preset-btn { color: var(--accent); background: none; border: none; font-size: 1.2rem; cursor: pointer; padding: 4px; }
        .add-preset-form { border-top: 1px dashed var(--border); margin-top: 1rem; padding-top: 1rem; }
    </style>
</head>
<body class="settings-page">

<div class="settings-container">
    <h2 style="text-align: center; margin-bottom: 2rem;">🛠 アプリ設定</h2>

    <form action="settings_action.php" method="post">
        
        <!-- プロフィール設定 -->
        <div class="settings-section">
            <h3>👤 プロフィール</h3>
            <div class="form-group">
                <label>あなたの名前（表示名）</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($display_name); ?>" placeholder="例: かなと" required>
            </div>
            <div class="form-group">
                <label>メールアドレス（ログイン用、変更不可）</label>
                <input type="text" value="<?php echo htmlspecialchars($row_user['email']); ?>" disabled style="opacity: 0.5;">
            </div>
        </div>

        <!-- AI設定 -->
        <div class="settings-section">
            <h3>🤖 AIパートナー設定</h3>
            <div class="form-group">
                <label>デフォルトの相談相手</label>
                <select name="default_ai_char">
                    <option value="default" <?php if($default_ai == 'default') echo 'selected'; ?>>👤 標準（丁寧なアドバイス）</option>
                    <option value="strict" <?php if($default_ai == 'strict') echo 'selected'; ?>>🔥 鬼コンサル（厳しい指摘）</option>
                    <option value="sister" <?php if($default_ai == 'sister') echo 'selected'; ?>>🌸 優しいお姉さん（共感・褒める）</option>
                    <option value="detective" <?php if($default_ai == 'detective') echo 'selected'; ?>>🔍 名探偵（鋭い分析）</option>
                </select>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.7;">AI相談画面で最初に選ばれるキャラクターです。</p>
            </div>
        </div>

        <!-- 予算設定 -->
        <div class="settings-section">
            <h3>💰 予算管理</h3>
            <div class="form-group">
                <label>全体の月間予算 (円)</label>
                <input type="number" name="monthly_limit" value="<?php echo htmlspecialchars($current_limit); ?>" step="1000" required>
            </div>

            <label style="display: block; margin-top: 1.5rem; margin-bottom: 0.8rem; font-size: 0.95rem; font-weight: bold;">カテゴリー別予算（任意）</label>
            <div class="category-budget-list">
                <?php foreach ($categories as $cat): ?>
                <div class="category-budget-item">
                    <span><?php echo $cat['icon']; ?></span>
                    <div class="cat-info"><?php echo htmlspecialchars($cat['name']); ?></div>
                    <input type="number" name="cat_budgets[<?php echo $cat['id']; ?>]" 
                           value="<?php echo htmlspecialchars($cat['cat_limit'] ?? ''); ?>" 
                           placeholder="無制限" step="100">
                    <span style="font-size: 0.8rem; opacity: 0.7;">円</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- クイック入力設定 -->
        <div class="settings-section">
            <h3>🚀 クイック入力の設定</h3>
            <p style="font-size: 0.85rem; opacity: 0.7; margin-bottom: 1rem;">よく使う支出をホーム画面にボタンとして配置できます。</p>
            
            <div class="presets-list">
                <?php foreach ($presets as $p): ?>
                <div class="preset-item">
                    <span style="font-size: 1.2rem;"><?php echo $p['icon']; ?></span>
                    <div class="preset-info">
                        <div style="font-weight: bold; font-size: 0.9rem;"><?php echo htmlspecialchars($p['label']); ?></div>
                        <div style="font-size: 0.8rem; opacity: 0.7;">
                            <?php echo number_format($p['amount']); ?>円 / 
                            <?php echo str_repeat('⭐', $p['satisfaction'] ?? 3); ?>
                        </div>
                    </div>
                    <button type="submit" name="delete_preset" value="<?php echo $p['id']; ?>" class="delete-preset-btn" onclick="return confirm('このボタンを削除しますか？')">🗑️</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="add-preset-form">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: bold;">➕ 新しいボタンを追加</label>
                <div class="form-group">
                    <input type="text" name="new_preset_label" placeholder="ラベル (例: ランチ)">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group">
                        <input type="number" name="new_preset_amount" placeholder="金額">
                    </div>
                    <div class="form-group">
                        <select name="new_preset_category">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['icon'] . ' ' . $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group">
                        <input type="text" name="new_preset_icon" placeholder="アイコン (例: 🍱)">
                    </div>
                    <div class="form-group">
                        <select name="new_preset_satisfaction">
                            <option value="5">⭐⭐⭐⭐⭐ 満足</option>
                            <option value="4">⭐⭐⭐⭐</option>
                            <option value="3" selected>⭐⭐⭐ 普通</option>
                            <option value="2">⭐⭐</option>
                            <option value="1">⭐ 微妙</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_preset" style="background: var(--secondary); margin-top: 0;" class="save-btn">ボタンを追加する</button>
            </div>
        </div>

        <button type="submit" class="save-btn">設定を保存する</button>
    </form>

    <a href="index.php" class="back-link">キャンセルして戻る</a>
</div>

</body>
</html>
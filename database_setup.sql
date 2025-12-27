-- ============================================
-- 家計簿アプリ 新機能用データベーステーブル
-- ============================================

-- 1. カテゴリーマスタテーブル
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(10) DEFAULT '📦',
    color VARCHAR(7) DEFAULT '#667eea',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- デフォルトカテゴリーの挿入
INSERT INTO categories (name, icon, color) VALUES
    ('食費', '🍔', '#FF6B6B'),
    ('交通費', '🚗', '#4ECDC4'),
    ('娯楽', '🎮', '#95E1D3'),
    ('日用品', '🛒', '#F38181'),
    ('医療', '💊', '#AA96DA'),
    ('教育', '📚', '#FCBAD3'),
    ('通信費', '📱', '#A8E6CF'),
    ('光熱費', '💡', '#FFD93D'),
    ('その他', '📦', '#667eea')
ON CONFLICT (name) DO NOTHING;

-- 2. transactionsテーブルにカテゴリーカラムを追加
-- 既存のテーブルがある場合は ALTER TABLE を使用
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS category_id INTEGER REFERENCES categories(id) DEFAULT 9;

-- 3. 予算アラート設定テーブル
CREATE TABLE IF NOT EXISTS budget_alerts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    alert_type VARCHAR(20) NOT NULL, -- 'daily', 'weekly', 'monthly', 'category'
    threshold_percentage INTEGER DEFAULT 80, -- 予算の何%で警告するか
    category_id INTEGER REFERENCES categories(id),
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, alert_type, category_id)
);

-- 4. 目標設定テーブル
CREATE TABLE IF NOT EXISTS savings_goals (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    goal_name VARCHAR(100) NOT NULL,
    target_amount INTEGER NOT NULL,
    current_amount INTEGER DEFAULT 0,
    deadline DATE,
    icon VARCHAR(10) DEFAULT '🎯',
    color VARCHAR(7) DEFAULT '#667eea',
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. レシート画像テーブル
CREATE TABLE IF NOT EXISTS receipt_images (
    id SERIAL PRIMARY KEY,
    transaction_id INTEGER REFERENCES transactions(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    ocr_text TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. 定期支出テーブル
CREATE TABLE IF NOT EXISTS recurring_expenses (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount INTEGER NOT NULL,
    category_id INTEGER REFERENCES categories(id),
    frequency VARCHAR(20) NOT NULL, -- 'daily', 'weekly', 'monthly', 'yearly'
    start_date DATE NOT NULL,
    end_date DATE,
    next_occurrence DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    satisfaction INTEGER DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. カテゴリー別予算設定テーブル
CREATE TABLE IF NOT EXISTS category_budgets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    category_id INTEGER REFERENCES categories(id),
    monthly_limit INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, category_id)
);

-- 8. 検索履歴テーブル（オプション：よく使う検索を保存）
CREATE TABLE IF NOT EXISTS search_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    search_query VARCHAR(255) NOT NULL,
    search_count INTEGER DEFAULT 1,
    last_searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- インデックスの作成（パフォーマンス向上のため）
CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_transactions_category ON transactions(category_id);
CREATE INDEX IF NOT EXISTS idx_recurring_user_active ON recurring_expenses(user_id, is_active);
CREATE INDEX IF NOT EXISTS idx_goals_user ON savings_goals(user_id);
CREATE INDEX IF NOT EXISTS idx_receipts_transaction ON receipt_images(transaction_id);

-- ============================================
-- 以下のコマンドをゼミサーバーで実行してください：
-- psql -h localhost -U knt416 -d knt416 -f database_setup.sql
-- ============================================

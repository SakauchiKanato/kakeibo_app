import os
import sys

# パスの追加（あなたの環境に合わせています）
sys.path.append("/home/h0/knt416/.local/lib/python3.12/site-packages")

from google import genai
from dotenv import load_dotenv

load_dotenv()

api_key = os.getenv("GEMINI_API_KEY")
if not api_key:
    print("エラー: .envファイルに GEMINI_API_KEY が設定されていません。")
    sys.exit(1)

client = genai.Client(api_key=api_key)

def get_daily_summary(items_list, total_spent, char_type):
    # --- キャラクターごとの性格設定（プロンプトの出し分け） ---
    if char_type == "strict":
        persona = "あなたは厳しい鬼コンサルタントです。"
    elif char_type == "sister":
        persona = "あなたは優しいお姉さんです。"
    elif char_type == "detective":
        persona = "あなたは名探偵です。支出のデータからユーザーの隠れた生活習慣を推理し、鋭い口調で指摘してください。"
    else:
        persona = "あなたは標準的なアドバイザーです。"

    prompt = f"""
    {persona}

    ユーザーの今日1日の支出リスト：
    {items_list}
    
    合計支出：{total_spent}円

    今月の残り予算：{remaining_budget}円
    
    【指示】
    1. リストを見て、満足度が低いのに高い買い物をしているものがあれば性格に合わせてコメントし、
       満足度が高い買い物には共感を持って接してください。
    2. 全体を通した「今日のお金の使い方の癖」を分析してください。
    3. 最後に明日への一言を添えて、3行〜5行程度で日本語で回答してください。
    """
    
    try:
        response = client.models.generate_content(
            model="gemini-2.5-flash", # または gemini-1.5-flash
            contents=prompt
        )
        return response.text
    except Exception as e:
        return f"AIとの通信でエラーが発生しました: {e}"

if __name__ == "__main__":
    # 引数の受け取り処理
    # sys.argv[1]: リスト
    # sys.argv[2]: 合計金額
    # sys.argv[3]: キャラ設定 (もし送られてこなかったら 'default')
    
    items = sys.argv[1] if len(sys.argv) > 1 else "データなし"
    total = sys.argv[2] if len(sys.argv) > 2 else "0"
    character = sys.argv[3] if len(sys.argv) > 3 else "default"
    remaining_budget = sys.argv[4] if len(sys.argv) > 4 else "不明"

    print(get_daily_summary(items, total, character))
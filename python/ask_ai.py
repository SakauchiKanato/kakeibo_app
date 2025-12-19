import os
import sys

sys.path.append("/home/h0/knt416/.local/lib/python3.12/site-packages")

from google import genai
from dotenv import load_dotenv

# .envファイルを読み込む
load_dotenv()

# APIキーの取得
api_key = os.getenv("GEMINI_API_KEY")
if not api_key:
    print("エラー: .envファイルに GEMINI_API_KEY が設定されていません。")
    sys.exit(1)

# 最新のクライアント初期化（google-genaiライブラリを使用）
client = genai.Client(api_key=api_key)

def get_daily_summary(items_list, total_spent):
    prompt = f"""
    あなたは家計簿の専属アドバイザーです。
    ユーザーの今日1日の支出リストは以下の通りです：
    {items_list}
    
    合計支出：{total_spent}円
    
    【指示】
    1. リストを見て、満足度が低いのに高い買い物をしているものがあれば厳しく、
       満足度が高い買い物には共感を持って接してください。
    2. 全体を通した「今日のお金の使い方の癖」を分析してください。
    3. 最後に明日への一言を添えて、3行程度で日本語で回答してください。
    """
    
    try:
        # 新しいライブラリでの呼び出し方
        response = client.models.generate_content(
            model="gemini-2.5-flash",
            contents=prompt
        )
        return response.text
    except Exception as e:
        return f"AIとの通信でエラーが発生しました: {e}"

if __name__ == "__main__":
    # 引数は [1]アイテムリスト [2]合計金額
    print(get_daily_summary(sys.argv[1], sys.argv[2]))
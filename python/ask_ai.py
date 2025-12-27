import os
import sys
import base64

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

def get_daily_summary(items_list, total_spent, char_type, username):
    # --- キャラクターごとの性格設定（プロンプトの出し分け） ---
    if char_type == "strict":
        persona = "あなたは厳しい鬼コンサルタントです。"
    elif char_type == "sister":
        persona = "あなたは優しいお姉さんです。"
    elif char_type == "detective":
        persona = "あなたは名探偵です。支出のデータからユーザーの隠れた生活習慣を推理し、鋭い口調で指摘してください。"
    else:
        persona = "あなたは標準的なアドバイザーです。"

    # 日本語名が正しく届いているか確認（Base64デコードを試行）
    try:
        decoded_name = base64.b64decode(username).decode('utf-8')
    except:
        decoded_name = username

    if not decoded_name or decoded_name.strip() in ["", "ユーザー", "UNKNOWN_USER"]:
        display_name = "あなた"
    else:
        display_name = decoded_name.strip()

    # アイテムリストもデコード試行
    try:
        decoded_items = base64.b64decode(items_list).decode('utf-8')
    except:
        decoded_items = items_list

    prompt = f"""
    {persona}

    【絶対に守るべき指示：最優先】
    1. あなたが今話している相手の名前は「{display_name}」です。
    2. 回答の中で、必ず「{display_name}さん」や「{display_name}ちゃん」（性格に合わせて）と名前を呼んでください。
    3. 決して「ユーザーさん」「お客様」「名無しさん」「ユーザーちゃん」などの汎用的な名前で呼ばないでください。
    4. 名前が「あなた」と指定されている場合は、不自然にならないよう「あなた」として接してください。
    5. 「〇〇」などの伏せ字やプレースホルダは、何があっても絶対に使用禁止です。

    【コンテキスト】
    現在の対象者名: {display_name}
    今日の支出リスト:
    {decoded_items}
    合計支出: {total_spent}円
    今月の残り予算: {remaining_budget}円

    【回答の指示】
    - {display_name}へのパーソナライズされたフィードバックを行ってください。
    - 支出の内容に基づき、具体的に言及してください。
    - 性格設定（{persona}）を最後まで維持してください。
    - 全体を3行〜5行程度で回答してください。
    """
    
    try:
        response = client.models.generate_content(
            model="gemini-2.5-flash",
            contents=prompt
        )
        return response.text
    except Exception as e:
        return f"AIとの通信でエラーが発生しました: {e}"

if __name__ == "__main__":
    # 引数の受け取り処理
    items = sys.argv[1] if len(sys.argv) > 1 else "データなし"
    total = sys.argv[2] if len(sys.argv) > 2 else "0"
    character = sys.argv[3] if len(sys.argv) > 3 else "default"
    remaining_budget = sys.argv[4] if len(sys.argv) > 4 else "不明"
    username = sys.argv[5] if len(sys.argv) > 5 else "UNKNOWN_USER"

    print(get_daily_summary(items, total, character, username))
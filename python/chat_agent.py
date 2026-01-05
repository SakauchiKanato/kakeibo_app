import os
import sys
import base64
import json

# パスの追加
sys.path.append("/home/h0/knt416/.local/lib/python3.12/site-packages")

from google import genai
from dotenv import load_dotenv

load_dotenv()

api_key = os.getenv("GEMINI_API_KEY")
if not api_key:
    print(json.dumps({"error": "API KEY MISSING"}))
    sys.exit(1)

client = genai.Client(api_key=api_key)

def handle_chat_input(user_input_raw, char_type, username, category_list_json):
    # キャラクター設定
    if char_type == "strict":
        persona = "厳格な鬼コンサルタント。無駄遣いには厳しく、節約には徹底的なアドバイスをします。口調は強め。"
    elif char_type == "sister":
        persona = "優しいお姉さん。ユーザーの頑張りを褒め、共感しながらアドバイスします。口調は優しく柔らかい。"
    elif char_type == "detective":
        persona = "名探偵。支出からユーザーの生活習慣を推理し、論理的かつ鋭い口調で指摘します。"
    else:
        persona = "標準的な家計アドバイザー。丁寧でわかりやすいアドバイスを提供します。"

    # 日本語名デコード
    try:
        display_name = base64.b64decode(username).decode('utf-8')
    except:
        display_name = username

    # カテゴリリスト
    categories = json.loads(category_list_json)
    cat_info = "\n".join([f"ID:{c['id']}, Name:{c['name']}" for c in categories])

    prompt = f"""
    あなたは以下のキャラクターとして振る舞ってください：{persona}
    ユーザー名: {display_name}さん

    【ミッション】
    ユーザーの入力内容を解析し、以下の2つのいずれかを行ってください。

    1. 支出の記録依頼である場合：
       - 店名や商品名、金額を抽出してください。
       - 以下のカテゴリリストから最も適切なカテゴリIDを選択してください。
       {cat_info}
       - 満足度は、推測される必要性に基づいて1〜5で設定してください（特に手がかりがなければ3）。
       - 記録したことを伝える、キャラクターに沿った短い返信を作成してください。

    2. 単なる雑談や相談である場合：
       - キャラクターに沿った適切なアドバイスや返信を作成してください。

    【出力フォーマット】
    必ず以下のJSON形式のみで出力してください。他のテキストは一切含めないでください。

    {{
      "action": "record" または "chat",
      "transaction": {{
        "description": "店名や項目名",
        "amount": 数値,
        "category_id": 数値ID,
        "satisfaction": 3
      }},
      "message": "キャラクターとしての返信メッセージ"
    }}

    ユーザーの入力内容: "{user_input_raw}"
    """

    try:
        response = client.models.generate_content(
            model="gemini-2.5-flash",
            contents=prompt,
            config={
                'response_mime_type': 'application/json'
            }
        )
        return response.text
    except Exception as e:
        return json.dumps({"error": str(e)})

if __name__ == "__main__":
    user_input = sys.argv[1] if len(sys.argv) > 1 else ""
    char_type = sys.argv[2] if len(sys.argv) > 2 else "default"
    username = sys.argv[3] if len(sys.argv) > 3 else "UNKNOWN"
    cat_list = sys.argv[4] if len(sys.argv) > 4 else "[]"

    print(handle_chat_input(user_input, char_type, username, cat_list))

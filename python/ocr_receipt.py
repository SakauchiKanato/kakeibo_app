import os
import sys
import json
import warnings

# 警告を抑制
warnings.filterwarnings('ignore')

# パスの追加
sys.path.append("/home/h0/knt416/.local/lib/python3.12/site-packages")

from google import genai
from dotenv import load_dotenv

load_dotenv()

api_key = os.getenv("GEMINI_API_KEY")
if not api_key:
    print(json.dumps({"error": "GEMINI_API_KEY が設定されていません"}))
    sys.exit(1)

client = genai.Client(api_key=api_key)

def analyze_receipt(image_path):
    """
    レシート画像を解析して、金額、店名、日付、商品名を抽出する
    """
    try:
        from PIL import Image
        
        # 画像を読み込む
        image = Image.open(image_path)
        
        # Gemini Vision APIを使用してレシートを解析
        prompt = """このレシート画像から以下の情報を抽出してください：
1. 合計金額（数字のみ）
2. 店名
3. 日付（YYYY-MM-DD形式）
4. 主な商品名（最大3つ）

以下のJSON形式で回答してください：
{
    "amount": 金額（数字のみ）,
    "store": "店名",
    "date": "YYYY-MM-DD",
    "items": ["商品1", "商品2", "商品3"]
}

情報が読み取れない場合は null を返してください。"""
        
        response = client.models.generate_content(
            model='gemini-2.5-flash',
            contents=[prompt, image]
        )
        
        # レスポンスからJSONを抽出
        result_text = response.text.strip()
        
        # JSONブロックを抽出（```json ... ``` の形式に対応）
        if "```json" in result_text:
            json_start = result_text.find("```json") + 7
            json_end = result_text.find("```", json_start)
            result_text = result_text[json_start:json_end].strip()
        elif "```" in result_text:
            json_start = result_text.find("```") + 3
            json_end = result_text.find("```", json_start)
            result_text = result_text[json_start:json_end].strip()
        
        # JSONパースを試行
        try:
            result = json.loads(result_text)
            return result
        except json.JSONDecodeError:
            # JSONブロックがない場合、レスポンス全体をJSONとしてパース
            return json.loads(result_text)
        
    except Exception as e:
        return {"error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "画像パスが指定されていません"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(json.dumps({"error": "画像ファイルが見つかりません"}))
        sys.exit(1)
    
    result = analyze_receipt(image_path)
    print(json.dumps(result, ensure_ascii=False))

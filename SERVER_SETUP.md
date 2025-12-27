# サーバーセットアップ手順

## レシートアップロード機能を動作させるために

ゼミサーバー上で以下のコマンドを実行してください：

### 1. アップロードディレクトリの作成と権限設定

```bash
cd /home/h0/knt416/public_html/kakeibo_app
mkdir -p uploads/receipts
chmod 777 uploads/receipts
```

### 2. Pythonパッケージのインストール確認

```bash
python3 -c "import google.generativeai; print('OK')"
```

もしエラーが出る場合：
```bash
pip3 install --user google-generativeai python-dotenv
```

### 3. 環境変数ファイルの作成

`.env` ファイルを作成してGemini APIキーを設定：

```bash
cd /home/h0/knt416/public_html/kakeibo_app
echo "GEMINI_API_KEY=あなたのAPIキー" > .env
chmod 600 .env
```

### 4. OCRスクリプトのパス確認

`upload_receipt.php` の33行目付近で、Pythonスクリプトのパスが正しいか確認：

```php
$python_script = __DIR__ . '/python/ocr_receipt.py';
```

### 5. 権限の確認

```bash
ls -la uploads/receipts/
# drwxrwxrwx と表示されればOK
```

### トラブルシューティング

もしまだエラーが出る場合は、以下を確認：

1. **PHPのアップロード設定**
   - `php.ini` で `upload_max_filesize` と `post_max_size` が十分か確認

2. **SELinux設定**（もし有効な場合）
   ```bash
   chcon -R -t httpd_sys_rw_content_t uploads/
   ```

3. **ディレクトリの所有者**
   ```bash
   chown -R knt416:knt416 uploads/
   ```

<html>
<head>
  <title>
    login form
  </title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
</head>
<body>
<form method="POST" action="./login_action.php">

    <?php if(isset($_GET['error'])): ?>
        <p style="color: #e74c3c; font-weight: bold;">メールアドレスかパスワードが違います</p>
    <?php endif; ?>
email:<input type="text" name="emf" size="40"><br>
pw:<input type="password" name="pwf" size="40"><br>
<input type="submit">
</form>
<p>*はじめての方は<a href="./register.php">こちら</a>から登録してください。</p>
</body>
</html>

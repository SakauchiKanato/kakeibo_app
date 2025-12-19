<html>
<head>
<title>registration</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">

</head>
<body>

<form method="POST" action="./register.php">
ユーザ名: <input type="text" name="unf" size="40"><br>
email:<input type="text" name="emf" size="40"><br>
password:<input type="password" name="pwf1" size="40"><br>
password:<input type="password" name="pwf2" size="40">(確認)<br>
<input type="submit" value="登録">
</form>

<?php
if (isset($_POST['emf'])){$emf=$_POST['emf'];}
if (isset($_POST['unf'])){$unf=$_POST['unf'];}
if (isset($_POST['pwf1'])){$pwf1=$_POST['pwf1'];}
if (isset($_POST['pwf2'])){$pwf2=$_POST['pwf2'];}
if ($pwf1 !== $pwf2){
  echo "<p>パスワードが一致しませんでした。</p>";
  echo "<a href=\"./regform.html\">戻る</p>";
}
elseif (isset($emf) && isset($unf)&& isset($pwf1)){
  $sql="select * from users where email='". $emf . "';";
  $dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP")
      or die('Could not connect: ' . pg_last_error());
  $result = pg_query($dbconn, $sql) or die('Query failed: ' . pg_last_error());
  if(pg_num_rows($result)==0){
    $npw=$pwf1;
    $npwh=password_hash($npw, PASSWORD_BCRYPT);
    $sql="insert into users(username,email, password_hash) values ('" .
      $unf . "','" . $emf . "','" . $npwh . "');";
    pg_query($dbconn, $sql) or die('Query failed: ' . pg_last_error());
    echo '<p>ユーザ登録を完了しました</p>';
#    $mailfr="naoki@gms.gdl.jp";
#    $mailsb="[phpua]ユーザ登録完了";
#    $mailms="下記のとおりユーザ登録を完了しました。\n\n" .
#      "   ユーザ名:" . $unf . "\n" .
#      "   email:" . $emf . "\n\n" .
#      "http://gms.gdl.jp/~naoki/phpuav3/\n\n";
#    if (mb_send_mail($emf, $mailsb,
#      $mailms, "From: " . $mailfr)) {
#      echo "<p>メールが送信されました。</p>";
#    } else {
#      echo "<p>メールの送信に失敗しました。</p>";
#    }
    echo "<a href=\"./index.php\">戻る</a>";
    #とうろく
  }
  else{
    echo "<p>そのメールアドレスはすでに登録されています。</p>";
    echo "<a href=\"./index.php\">戻る</a>";
  }
}
else{echo 'error';}
  ?>
 </body>
</html>

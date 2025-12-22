<?php
session_start();
$dbconn = pg_connect("host=localhost dbname=knt416 user=knt416 password=nFb55bRP");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $id = $_POST['id'];
    $desc = $_POST['description'];
    $amount = $_POST['amount'];
    $sat = $_POST['satisfaction'];
    $user_id = $_SESSION['user_id'];

    $sql = "UPDATE transactions SET description = $1, amount = $2, satisfaction = $3 WHERE id = $4 AND user_id = $5";
    pg_query_params($dbconn, $sql, array($desc, $amount, $sat, $id, $user_id));
}

header('Location: index.php');
exit();
<?php
session_start();
require_once '../classes/Auth.php';

$auth = new Auth(null);
$auth->logout();

header('Location: login.php?message=logout');
exit;
?>
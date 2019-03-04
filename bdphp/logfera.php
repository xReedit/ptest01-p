<?php
  session_id(uniqid());
  @session_start();
  @session_destroy();
  session_destroy();
  session_unset();
  
  //setcookie("PHPSESSID", "", 1);
//header('location: ../index.php');
?>

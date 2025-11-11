<?php
$mysqli = @new mysqli('127.0.0.1','root','', 'hootlearn', 3306);
if ($mysqli->connect_errno) {
  echo "FAIL | errno={$mysqli->connect_errno} | error={$mysqli->connect_error}";
} else {
  echo "OK | server=".$mysqli->server_info;
}
<?php
if (isset($_SESSION)) {
 if (isset($_SESSION['user_id'])) {
  session_regenerate_id();
 }
}

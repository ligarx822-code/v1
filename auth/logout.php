<?php
require_once '../config/config.php';

// Destroy session
session_destroy();

// Redirect to home
redirectTo('../index.php');
?>

<?php
require_once __DIR__ . '/auth.php';
logout_user();
header('Location: index.php');
exit;

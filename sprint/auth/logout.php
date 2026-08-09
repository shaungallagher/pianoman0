<?php
require_once '../config.php';
logout_user();
header("Location: " . url('sprint/public/index.php'));
exit;

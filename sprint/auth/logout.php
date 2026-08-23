<?php
require_once '../config.php';
logout_user();
header("Location: " . url('sprint/index.php'));
exit;

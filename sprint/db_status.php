<?php
// Admin-only: DB status moved under /admin/*
require_once 'config.php';
require_once 'roles.php';
require_role('admin');

header('Location: db_status.php');
exit;


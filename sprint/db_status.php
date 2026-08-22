<?php
// Admin-only: DB status moved under /admin/*
require_once '../config.php';
require_once '../includes/roles.php';
require_role('admin');

header('Location: ../admin/db_status.php');
exit;


<?php
require_once __DIR__ . '/../../app/Admin/auth.php';
admin_session_logout();
header('Location: login.php');
exit;

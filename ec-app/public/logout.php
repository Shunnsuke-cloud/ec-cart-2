<?php
require_once __DIR__ . '/../app/Auth/session.php';
app_session_logout();
header('Location: index.php?logged_out=1');
exit;

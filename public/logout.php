<?php
require __DIR__ . '/../app/web.php';
$_SESSION = [];
session_destroy();
redirect('/');

<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require_post();
put_cookie('bk_admin', '', 0);
json_out(array('ok' => true));

<?php
define('__XE__', true);
require_once('../../config/config.inc.php'); //XE config.inc.php 주소
$oContext = &Context::getInstance();
$oContext->init();

$oMember_expireController = getController('member_expire');
$output = $oMember_expireController->autoCrontabExpire();
var_dump($output);
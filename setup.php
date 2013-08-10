<?php

namespace html {
	$cwd = explode('/', getcwd());
	unset($cwd[(count($cwd)-1)]);
	$base = implode('/', $cwd);
	$settings = array(
			'host' => 'localhost',
			'user' => 'vezbo',
			'password' => 'WUx83C4PCfXT2VcK',
			'schema' => 'vezbo'
	);
	require_once "$base/helpers/HelperException.php";
	require_once "$base/helpers/Sandbox.php";
	require_once "$base/helpers/Storage.php";
	require_once "$base/base/BaseException.php";
	require_once "$base/base/Response.php";
	require_once "$base/base/Controller.php";
}

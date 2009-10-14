<?php

/*
Copyright (c) 2009, Joel Rosario
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the wiseowl nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY Joel Rosario 'AS IS' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Joel Rosario BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

function get_webapp_root() {
    static $webapp_root;

    if(!isset($webapp_root))
        $webapp_root = '/'.trim(dirname(dirname(str_replace('\\', '/', substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']))))), '/');

    return $webapp_root;
}

function with_root($relative_path)
{
	return get_webapp_root() . '/' . $relative_path;
}

function redirect_to($path)
{
	header("Location: $path");
	exit(0);
}

function get_useragent_type()
{
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);

	if (strstr($useragent, 'iphone') || strstr($useragent, 'ipod'))
		return 'iphone';
	else if (strstr($useragent, 'webkit'))
		return 'webkit';

    return 'default';
}

function get_filter_path($handler_path) {
    return dirname($handler_path).'/_filters.'.basename($handler_path);
}

function run_filter($filter_name, $resource_path, $handler_path) {
    return run_filter_in_file($filter_name, $resource_path, get_filter_path($handler_path));
}

function run_filter_in_file($filter_name, $resource_path, $filter_path) {
    if(load_file($filter_path, array(), '_require_once'))
        return run_filter_function($filter_name, $resource_path);
}

function run_filter_function($filter_name, $resource_path) {
    $filter_function = $filter_name.'_'.str_replace('/', '_', $resource_path);
    if(function_exists($filter_function))
        return $filter_function();
}

function get_requested_resource() {
    global $wiseowl_requested_resource;
    return $wiseowl_requested_resource;
}

function hand_over_to($resource) {
    global $wiseowl_requested_resource;
    $wiseowl_requested_resource = $resource;

    $handler_path = full_root_path('handlers', $resource);

	if (file_exists($handler_path))
    {
        push_file($handler_path);

        if(run_filter_in_file('_before', 'all/'.dirname($resource), full_home_path('', '_filters')) == 'stop')
            return 'stop';

		include($handler_path);

        pop_file();
    }
	else
	{
		header('HTTP/1.1 404 No handle');
		header('Content-Type: text/plain');
		echo "I couldn't locate a handler for $resource. I'm sorry, so sorry. What can I say.";
	}
}

function dispatch() {
    $resource = $_GET['_wiseowl_path'];
    hand_over_to($resource);
}

function content($relative_content_path) {
    return with_root('content/'.$relative_content_path);
}

function initialize_application() {
	session_start();
    initialize_filepath_stack();
    load_owl_config();
}

function initialize_filepath_stack() {
    global $filepath_stack;
    $filepath_stack = array();
}

function load_owl_config() {
    $config_path = dirname(dirname(__FILE__)).'/wiseowl.config.php';

    if (file_exists($config_path))
        include($config_path);
}

function load_($type, $path) {
    return find_and_load(trim($type, '\\/'), trim($path, '\\/'), array(), '_require_once');
}

function render_($type, $path, $data) {
    return find_and_load(trim($type, '\\/'), trim($path, '\\/'), $data, '_require');
}

function find_and_load($type, $resource_path, $data, $strategy) {
    $home_handler_path = full_home_path($type, $resource_path);

    if(!load_file($home_handler_path, $data, $strategy, curry('run_filter', '_before', $resource_path, $home_handler_path)))
    {
        $root_handler_path = full_root_path($type, $resource_path);
        return load_file($root_handler_path, $data, $strategy, curry('run_filter', '_before', $resource_path, $root_handler_path));
    }

    return true;
}

function full_home_path($type, $resource_path) {
    if (!last_file())
        return '';

    $dir = dirname(last_file());
    if (endswith($dir, $type))
        $type = '';

    if($type != '')
        $type.='/';

    return str_replace('\\', '/', $dir.'/'.$type.$resource_path).'.php';
}

function full_root_path($type, $resource_path) {
    if($type != '')
        $type.='/';

    return str_replace('\\', '/', dirname(dirname(__FILE__)).'/'.$type.$resource_path).'.php';
}

function endswith($str, $ending) {
	if(strlen($ending) > strlen($str) ||
        strcmp(substr($str, strlen($str) - strlen($ending)), $ending) != 0)
		return false;

	return true;
}

function load_file($filepath, $data, $strategy = '_require', $pre_processor = false, $post_processor = false) {
    if(file_exists($filepath))
    {
        push_file($filepath);

        if(execute_curried_func($pre_processor) == 'stop') {
            pop_file();
            return 'stop';
        }

        $strategy($filepath, $data);

        if(execute_curried_func($post_processor) == 'stop') {
            pop_file();
            return 'stop';
        }

        pop_file();

        return true;
    }

    return false;
}

function curry() {
    $args = func_get_args();

    $callable = array();
    $callable['function'] = $args[0];
    $callable['arguments'] = array_slice($args, 1);

    return $callable;
}

function execute_curried_func($curried_func = false) {
    if($curried_func)
        return call_user_func_array($curried_func['function'], $curried_func['arguments']);

    return false;
}

function push_file($filepath) {
    global $filepath_stack;
    $filepath_stack[] = $filepath;
}

function pop_file() {
    global $filepath_stack;
    array_pop($filepath_stack);
}

function last_file() {
    global $filepath_stack;
    if(count($filepath_stack) == 0)
        return false;

    return $filepath_stack[count($filepath_stack) - 1];
}

function _require_once ($__filepath, $__data) {
    if(is_array($__data))
        extract($__data);
    else
        $data = $__data;

    require_once($__filepath);
}

function _require ($__filepath, $__data) {
    if(is_array($__data))
        extract($__data);
    else
        $data = $__data;

    require($__filepath);
}

initialize_application();

?>
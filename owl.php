<?php

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

function render_single_data_element($webpart_name, $data)
{
    render_('webparts', $webpart_name, $data);
}

function render($webpart_name, $webpart_data = array()) {
    render_single_data_element($webpart_name, $webpart_data);
}

function _render_single_data_element($data, $key, $webpart_name) {
	render_single_data_element($webpart_name, $data);
}

function render_list($webpart_name, $webpart_data) {
	array_walk($webpart_data, '_render_single_data_element', $webpart_name);
}

function load_lib($lib_name) {
    return load_('libs', $lib_name);
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

        if(run_filter('_before', $resource, $handler_path) == 'stop')
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
    $config_path = dirname(dirname(__FILE__)).'/owl.config.php';

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
    if(!load_file(full_home_path($type, $resource_path), $data, $strategy))
        return load_file(full_root_path($type, $resource_path), $data, $strategy);

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

function load_file($filepath, $data, $strategy = '_require') {
    if(file_exists($filepath))
    {
        push_file($filepath);

        $strategy($filepath, $data);

        pop_file();

        return true;
    }

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
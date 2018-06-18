<?php

if (! function_exists('get_theme_option')) {
    function get_theme_option($key = null, $default = null)
    {
        $integration = \Dynamis\ThemeOptions\Integration::getInstance();
        $repository = $integration->getRepository();

        if (is_null($key)) {
            return $repository->all();
        }
        else {
            return $repository->get($key, $default);
        }
	}
}

if (! function_exists('theme')) {
    function theme($key = null, $default = null)
    {
        return get_theme_option($key, $default);
	}
}

<?php namespace Dynamis\ThemeOptions;

use Illuminate\Support\Arr;
use Tekton\Support\Repository as BaseRepository;

class Repository extends BaseRepository
{
    public function reset()
    {
        global $wpdb;
		$prefix = THEME_OPTIONS_PREFIX;
		$result = [];

 		$options = $wpdb->get_results("
 			SELECT *
 			FROM  {$wpdb->options}
 			WHERE  option_name LIKE  '{$prefix}%'
 		");

 		foreach ($options as $opt) {
			delete_option($opt->option_name);
 		}
    }

    public function all()
    {
        global $wpdb;
		$prefix = THEME_OPTIONS_PREFIX;
		$result = [];

        $options = $wpdb->get_results("
 			SELECT *
 			FROM  {$wpdb->options}
 			WHERE  option_name LIKE  '{$prefix}%'
 		");

 		foreach ($options as $opt) {
			$section = substr($opt->option_name, strlen($prefix));
			$result[$section] = $this->get($section);
 		}

        return $result;
    }

    public function exists(string $key)
    {
        $section = $this->getSection($key);
        $key = $this->getKey($key);
        $data = get_option($section, null);

        if (! is_null($data)) {
            return (is_null($key)) ? true : Arr::exists($data, $key);
        }

        return false;
    }

    public function get(string $key, $default = null)
    {
        $section = $this->getSection($key);
        $key = $this->getKey($key);
        $data = get_option($section, null);

        if (! is_null($data)) {
            return (is_null($key)) ? $data : Arr::get($data, $key, $default);
        }

        return $default;
    }

    public function set(string $key, $value = null)
    {
        $section = $this->getSection($key);
        $key = $this->getKey($key);
        $data = $this->get($section);

        if (is_null($key)) {
            update_option($section, $value);
        }
        else {
            Arr::set($data, $key, $value);
            update_option($section, $data);
        }

        return $this;
    }

    public function remove($key)
    {
        $section = $this->getSection($key);
        $key = $this->getKey($key);
        $data = $this->get($section);

        if (is_null($key)) {
            delete_option($section);
        }
        else {
            Arr::forget($data, $key);
            update_option($section, $data);
        }

        return null;
    }

    protected function getSection($key)
    {
        $segments = explode('.', $key);
        $section = array_shift($segments);
        return THEME_OPTIONS_PREFIX.$section;
    }

    protected function getKey($key)
    {
        $segments = explode('.', $key);
        $section = array_shift($segments);
        return (! empty($segments)) ? implode('.', $segments) : null;
    }
}

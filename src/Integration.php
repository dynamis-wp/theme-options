<?php namespace Dynamis\ThemeOptions;

use CMB2_Boxes;
use Tekton\Support\Repository;
use Tekton\Support\Contracts\Singleton;
use Dynamis\ThemeOptions\Repository as ThemeOptionsRepository;

class Integration implements Singleton
{
	use \Tekton\Support\Traits\Singleton;

	protected $config;
	protected $labels = [];
	protected $options_page;
	protected $repository;
	protected $screens = [];

	static $init = false;

	public function __construct()
	{
		if (! defined('THEME_OPTIONS_PREFIX')) {
			define('THEME_OPTIONS_PREFIX', 'theme_options_');
		}
	}

	public function getRepository()
	{
		// Only add actions once
		if ($this->repository) {
			return $this->repository;
		}

		return $this->repository = new ThemeOptionsRepository();
	}

	protected function __($str)
	{
		return __($str, $this->config['domain']);
	}

	public function init($config = [])
	{
		// Only add actions once
		if (self::$init) {
			return;
		}

		self::$init = true;

		// Abort init if we're not in WP Admin
		if (! is_admin() || ! current_user_can('manage_theme_options')) {
			return;
		}

		// WORKAROUND to Support running CMB2_Conditionals
		if (class_exists('CMB2_Conditionals')) {
			add_action('theme_options_screen', function() {
			    add_action('admin_footer', function() {
			        $original = $GLOBALS['pagenow'];
			        $GLOBALS['pagenow'] = 'post.php';

			        $plugin = new \CMB2_Conditionals();
			        $plugin->admin_footer();

			        $GLOBALS['pagenow'] = $original;
			    });
			});
		}

		// Create a hook for when the theme options page is loaded
		add_action('current_screen', function() {
			$current = get_current_screen();

			foreach ($this->screens as $screen) {
				if ($this->endsWith($current->id, $screen)) {
					do_action('theme_options_screen');
					break;
				}
			}
		});

		// Add body class
		add_action('theme_options_screen', function() {
			// Add style for separator
			add_action('admin_head', function() {
				echo '<style>';
				echo file_get_contents(__DIR__.'/../assets/dynamis-theme-options.css');
				echo '</style>';
	        });

			// Add class to page
			add_filter('admin_body_class', function($classes) {
				$classes .= ' theme-options';
				return $classes;
			});
		});

		add_action('cmb2_admin_init', function() use ($config) {
			// Set config
			$config = array_merge($menuItem = [
				'title' => 'Theme Options',
				'menu_title' => 'Theme',
				'icon' => 'dashicons-art',
				'domain' => 'theme',
			], $config);

			// Filter entire config
			$config = apply_filters('theme_options_config', $config);
			$config['sections'] = $config['sections'] ?? [];

			// Filter menu item only
			$menuItem = array_intersect_key($config, array_mirror(array_keys($menuItem)));
			$config = array_merge($config, apply_filters('theme_options_menu', $menuItem));

			// Filter sections
			$config['sections'] = apply_filters('theme_options_sections', $config['sections']);

			// Filter labels
			foreach ($config['sections'] as $key => $val) {
				if (isset($val['labels'])) {
					$this->labels[$key] = apply_filters('theme_options_labels_'.$key, $val['labels']);
				}
			}

			// Create config repository
			$this->config = new Repository($config);

			// Modify main menu entry
			if ($this->config['menu_title'] != $this->config['title']) {
				add_action('admin_menu', function() {
					global $menu;

					foreach ($menu as $key => $item) {
						if ($item[2] == $this->options_page) {
							$menu[$key][0] = $this->config['menu_title'];
							$menu[$key][3] = $this->config['menu_title'];
							$menu[$key][6] = $this->config['icon'];
						}
					}
				}, 11);
			}
		});

		// Add options page
		add_action('cmb2_admin_init', [$this, 'addOptionsPage'], PHP_INT_MAX);
	}

	private function endsWith($haystack, $needle)
	{
	    $length = strlen($needle);

	    return $length === 0 ||
	    (substr($haystack, -$length) === $needle);
	}

	protected function processFields(array $fields)
	{
		// Supporting either defining the fields in the typical CMB2 style
		// but also by having the keys as id's instead of as a field for
		// greater readability
		if ($this->is_assoc($fields)) {
			foreach ($fields as $id => $field) {
				$fields[$id]['id'] = $id;
			}
		}
		else {
			foreach ($fields as $key => $field) {
				unset($fields[$key]);
				$fields[$field['id']] = $field;
			}
		}

		return $fields;
	}

	public function addOptionsPage()
	{
		$parent = false;
		$sections = $this->config->get('sections', []);

		foreach ($sections as $name => $config) {
			$fields = $sections[$name]['fields'] ?? [];

			// Metabox ID
			$id = $this->getSectionKey($name);
			$this->screens[] = $id;

			// Supporting either defining the fields in the typical CMB2 style
			// but also by having the keys as id's instead of as a field for
			// greater readability
			$fields = apply_filters('theme_options_fields_'.$name, $fields, $name);
			$fields = $this->processFields($fields);

			// Labels
			$pageLabel = $this->getLabel($name, 'title') ?? $fields['id'];
			$menuLabel = $this->getLabel($name, 'menu_title') ?? $fields['id'];
			$tabLabel = $this->getLabel($name, 'tab_title') ?? $fields['id'];

			// CMB2 args
			$args = [
				'id' => $id,
				'title' => $pageLabel,
				'tab_title' => $tabLabel,
				'menu_title' => $menuLabel,
				'object_types' => ['options-page'],
				'option_key'   => $id,
			];

			if (! $parent) {
				$parent = $this->options_page = $id;
				$args['tab_group'] = $id;
			}
			else {
				$args['tab_group'] = $parent;
				$args['parent_slug'] = $parent;
			}

			// 'tab_group' property is supported in > 2.4.0.
			if (version_compare(CMB2_VERSION, '2.4.0')) {
				$args['display_cb'] = [$this, 'displayWithTabs'];
			}

			// Add fields
			$cmb = new_cmb2_box(apply_filters('cmb2_theme_options_metabox_'.$name, $args));

			foreach ($fields as $field) {
				$fieldId = $cmb->add_field($field);

				// Support defining group fields within group definition
				if ($field['type'] == 'group') {
					$groupFields = $this->processFields($field['fields']);

					foreach ($groupFields as $groupField) {
						$cmb->add_group_field($fieldId, $groupField);
					}
				}
				else {
					$cmb->add_field($field);
				}
			}
		}
	}

	public function displayWithTabs($cmb_options)
	{
		$tabs = $this->getTabs($cmb_options);

		// WORKAROUND: We set id to #post so that CMB2_Conditionals still works
		?>
		<div id="post" class="wrap cmb2-options-page option-<?php echo $cmb_options->option_key; ?>">
			<h2><?php echo $this->config['title']; ?></h2>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $option_key => $tab_title) : ?>
					<a class="nav-tab<?php if (isset($_GET['page']) && $option_key === $_GET['page']) : ?> nav-tab-active<?php endif; ?>" href="<?php menu_page_url($option_key); ?>"><?php echo wp_kses_post($tab_title); ?></a>
				<?php endforeach; ?>
			</h2>
			<form class="cmb-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST" id="<?php echo $cmb_options->cmb->cmb_id; ?>" enctype="multipart/form-data" encoding="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr($cmb_options->option_key); ?>">
				<?php $cmb_options->options_page_metabox(); ?>
				<?php submit_button(esc_attr($cmb_options->cmb->prop('save_button')), 'primary', 'submit-cmb'); ?>
			</form>
		</div>
		<?php
	}

	public function getTabs($cmb_options)
	{
		$tab_group = $cmb_options->cmb->prop('tab_group');
		$tabs = [];

		foreach (CMB2_Boxes::get_all() as $cmb_id => $cmb) {
			if ($tab_group === $cmb->prop('tab_group')) {
				$tabs[$cmb->options_page_keys()[0]] = $cmb->prop('tab_title')
					? $cmb->prop('tab_title')
					: $cmb->prop('title');
			}
		}

		return $tabs;
	}

	private function is_assoc(array $arr)
	{
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	protected function getLabel($key, $type = 'menu_title')
	{
		$label = $this->labels[$key] ?? null;

		if (is_array($label)) {
		 	return $label[$type] ?? $label['menu_title'] ?? $label['tab_title'];
		}
		else {
			return $label;
		}
	}

	protected function getSectionKey($section)
	{
		return THEME_OPTIONS_PREFIX.$section;
	}
}

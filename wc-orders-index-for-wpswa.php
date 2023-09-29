<?php
/**
 * Plugin Name:       WC Orders Index for WPSWA
 * Plugin URI:        https://ilnp.com
 * Description:       Custom WooCommerce Orders index for WP Search with Algolia plugin
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            ILNP
 * Author URI:        https://ilnp.com
 * License:           GNU General Public License v2.0 / MIT License
 * Text Domain:       wp-search-with-algolia-orders-index
 * Domain Path:       /languages
 */

if ( ! defined('WPINC')) {
    die;
}

define('WCOI_WPSA_PATH', plugin_dir_path(__FILE__));
define('WCOI_WPSA_URL', plugin_dir_url(__FILE__));
define('WCOI_WPSA_VER', '1.0.0');

class WC_Algolia_Orders_Index {

	private $path;
	private $url;
	private $ver;

	public function __construct($path, $url, $ver) {

		$this->path = $path;
		$this->url = $url;
		$this->ver = $ver;

		require $this->path . 'vendor/autoload.php';

		add_action('plugins_loaded', [$this, 'load']);
	}

	public function load() {

		if ( ! in_array( 'wp-search-with-algolia/algolia.php', (array) get_option( 'active_plugins', array() ) )) {
			return;
		}

		add_filter('algolia_indices', [$this, 'filter_indices'], 10, 1);
		add_filter('algolia_changes_watchers', [$this, 'filter_watchers'], 10, 2);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

	}

	public function filter_indices($indices) {
		$indices[] = new \MiraSuite\AlgoliaOrdersIndex\Indices\OrdersIndex();
		return $indices;
	}

	public function filter_watchers($watchers, $indices) {

		foreach($indices as $index) {
			if($index instanceof \MiraSuite\AlgoliaOrdersIndex\Indices\OrdersIndex) {
				$watchers[] = new \MiraSuite\AlgoliaOrdersIndex\Watchers\OrdersWatcher($index);
				break;
			}
		}

		return $watchers;
	}

	public function enqueue_scripts() {

		if(!$this->is_orders_page()) {
			return;
		}

		wp_enqueue_style('algolia-theme-classic', 'https://cdn.jsdelivr.net/npm/@algolia/autocomplete-theme-classic@1.8.3/dist/theme.min.css', [], '1.8.3', 'all');
		wp_enqueue_script('algolia-core', 'https://cdn.jsdelivr.net/npm/algoliasearch@4.20.0/dist/algoliasearch.umd.min.js', [], '4.20.0');
		wp_enqueue_script('algolia-autocomplete', 'https://cdn.jsdelivr.net/npm/@algolia/autocomplete-js@1.11.0/dist/umd/index.production.min.js', [], '1.11.0' );

		wp_enqueue_style('wcoi', $this->url.'assets/style.css',[], filemtime($this->path.'assets/style.css') );

		wp_enqueue_script('wcoi', $this->url.'assets/script.js', ['algolia-core', 'algolia-autocomplete'], filemtime($this->path.'assets/script.js') );
		wp_localize_Script('wcoi', 'WCOI', [
			'algolia_app_id' => get_option('algolia_application_id'),
			'algolia_index' => get_option('algolia_index_name_prefix').'orders',
			'algolia_search_key' => get_option('algolia_search_api_key'),
			'debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
			'is_hpos' => '',
		]);
	}

	private function is_orders_page() {
		if(isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
			// hpos support.
			return true;
		} else {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}
			$screen = get_current_screen();

			return ( ! is_null( $screen ) && 'edit-shop_order' === $screen->id );

		}
	}
}


new WC_Algolia_Orders_Index(
	WCOI_WPSA_PATH,
	WCOI_WPSA_URL,
	WCOI_WPSA_VER
);
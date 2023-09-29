<?php

namespace MiraSuite\AlgoliaOrdersIndex\Watchers;

class OrdersWatcher implements \Algolia_Changes_Watcher {

	/**
	 * The relevant index
	 *
	 * @var \Algolia_Index
	 */
	private $index;

	/**
	 * The constructor
	 *
	 * @param \Algolia_Index $index
	 */
	public function __construct( \Algolia_Index $index ) {
		$this->index = $index;
	}

	/**
	 * Watch WordPress events.
	 */
	public function watch() {
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'process_shop_order_meta' ], 10, 2 );
		add_action( 'woocommerce_new_order', [ $this, 'new_order' ], 10, 2 );
		add_action( 'woocommerce_before_delete_order', [ $this, 'delete_order' ], 10, 2 );
		add_action( 'before_delete_post', [ $this, 'delete_order_legacy' ], 10, 2 );
	}

	/**
	 * Triggered once order is updated from admin UI
	 *
	 * @param int       $order_id
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function process_shop_order_meta( $order_id, $order ) {
		$this->sync_item( $order );
		error_log( 'Triggered: woocommerce_process_shop_order_meta' . PHP_EOL );
	}

	/**
	 * Triggered once a new order is created
	 *
	 * @param int       $order_id
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function new_order( $order_id, $order ) {
		$this->sync_item( $order );
		error_log( 'Triggered: woocommerce_new_order' . PHP_EOL );
	}

	/**
	 * Triggered once order is deleted from database
	 *
	 * @param int       $order_id
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function delete_order( $order_id, $order ) {
		$this->sync_item( $order );
		error_log( 'Triggered: woocommerce_before_delete_order' . PHP_EOL );
	}

	/**
	 * Triggered once order is deleted from database
	 *
	 * @param $order_id
	 * @param $order_post
	 *
	 * @return void
	 */
	public function delete_order_legacy( $order_id, $order_post ) {
		$order = wc_get_order( $order_id );
		$this->sync_item( $order );
		error_log( 'Triggered: delete_order_legacy' . PHP_EOL );
	}

	/**
	 * Sync item
	 *
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function sync_item( $order ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $this->index->supports( $order ) ) {
			return;
		}

		try {
			$this->index->sync( $order );
		} catch ( \Exception $exception ) {
			error_log( $exception->getMessage() ); // phpcs:ignore -- Legacy.
		}
	}


	/**
	 * Delete item
	 *
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function delete_item( $order ) {

		if ( ! $this->index->supports( $order ) ) {
			return;
		}

		try {
			$this->index->delete_item( $order );
		} catch ( \Exception $exception ) {
			error_log( $exception->getMessage() ); // phpcs:ignore -- Legacy.
		}
	}
}
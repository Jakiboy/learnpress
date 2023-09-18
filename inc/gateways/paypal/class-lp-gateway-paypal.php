<?php
/**
 * Class Paypal Payment gateway.
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @since   3.0.0
 * @version 3.0.1
 */

use LearnPress\Helpers\Config;
use LearnPress\Helpers\Singleton;

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Gateway_Paypal' ) ) {
	/**
	 * Class LP_Gateway_Paypal.
	 */
	class LP_Gateway_Paypal extends LP_Gateway_Abstract {
		use Singleton;
		/**
		 * @var string
		 */
		public $id = 'paypal';
		/**
		 * @var null|string
		 */
		protected $paypal_live_url = 'https://www.paypal.com/';
		/**
		 * @var null|string
		 */
		protected $paypal_payment_live_url = 'https://www.paypal.com/cgi-bin/webscr';
		/**
		 * @var null|string
		 */
		protected $paypal_nvp_api_live_url = 'https://api-3t.paypal.com/nvp';

		/**
		 * @var null|string
		 */
		protected $paypal_sandbox_url = 'https://www.sandbox.paypal.com/';
		/**
		 * @var null|string
		 */
		protected $paypal_payment_sandbox_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		/**
		 * @var null
		 */
		protected $paypal_nvp_api_sandbox_url = 'https://api-3t.sandbox.paypal.com/nvp';

		/**
		 * @var string
		 */
		protected $api_sandbox_url = 'https://api-m.sandbox.paypal.com/';

		/**
		 * @var string
		 */
		protected $api_live_url = 'https://api-m.paypal.com/';

		/**
		 * @var string|null
		 */
		protected $api_url = null;

		/**
		 * @var string
		 */
		protected $method = '';

		/**
		 * @var null
		 */
		protected $paypal_url = null;

		/**
		 * @var null
		 */
		protected $paypal_payment_url = null;

		/**
		 * @var null
		 */
		protected $paypal_nvp_api_url = null;

		/**
		 * @var null
		 */
		protected $paypal_email = '';

		/**
		 * @var null
		 */
		protected $settings = null;

		/**
		 * @var null
		 */
		protected $client_id = null;

		/**
		 * @var null
		 */
		protected $client_secret = null;

		/**
		 * LP_Gateway_Paypal constructor.
		 */
		public function __construct() {
			$this->id = 'paypal';

			$this->method_title       = esc_html__( 'PayPal', 'learnpress' );
			$this->method_description = esc_html__( 'Make a payment via Paypal.', 'learnpress' );
			$this->icon               = LP_PLUGIN_URL . 'assets/images/paypal-logo-preview.png';

			$this->title       = esc_html__( 'PayPal', 'learnpress' );
			$this->description = esc_html__( 'Pay with PayPal', 'learnpress' );

			// get settings
			$this->settings = LP_Settings::instance()->get_group( 'paypal', '' );

			$this->enabled = $this->settings->get( 'enable', 'no' );

			$this->init();

			parent::__construct();
		}

		/**
		 * Init.
		 */
		public function init() {
			if ( $this->is_enabled() ) {
				if ( $this->settings->get( 'paypal_sandbox', 'no' ) === 'no' ) {
					$this->paypal_url         = $this->paypal_live_url;
					$this->paypal_payment_url = $this->paypal_payment_live_url;
					$this->paypal_nvp_api_url = $this->paypal_nvp_api_live_url;
					$this->paypal_email       = $this->settings->get( 'paypal_email' );
					$this->api_url            = $this->api_live_url;
				} else {
					$this->paypal_url         = $this->paypal_sandbox_url;
					$this->paypal_payment_url = $this->paypal_payment_sandbox_url;
					$this->paypal_nvp_api_url = $this->paypal_nvp_api_sandbox_url;
					$this->paypal_email       = $this->settings->get( 'paypal_sandbox_email' );
					$this->api_url            = $this->api_sandbox_url;
				}
				$this->client_id     = $this->settings->get( 'app_client_id' );
				$this->client_secret = $this->settings->get( 'app_client_secret' );
			}

			add_filter( 'learn-press/payment-gateway/' . $this->id . '/available', array( $this, 'paypal_available' ), 10, 2 );
		}

		/**
		 * Check payment gateway available.
		 *
		 * @param bool $default
		 * @param $payment
		 *
		 * @return bool
		 */
		public function paypal_available( bool $default, $payment ): bool {
			// Empty live email and Sandbox mode also disabled
			if ( $this->settings->get( 'paypal_sandbox' ) != 'yes' && ! $this->settings->get( 'paypal_email' ) ) {
				return false;
			}

			// Enable Sandbox mode but it's email is empty
			if ( ! $this->settings->get( 'paypal_sandbox_email' ) && $this->settings->get( 'paypal_sandbox' ) == 'yes' ) {
				return false;
			}

			return $default;
		}

		/**
		 * https://developer.paypal.com/api/nvp-soap/ipn/IPNImplementation/#link-ipnlistenerrequestresponseflow
		 * Check validate IPN.
		 *
		 * @return bool
		 */
		public function validate_ipn():bool {
			$validate_ipn  = array( 'cmd' => '_notify-validate' );
			$validate_ipn += wp_unslash( $_POST );

			$params = array(
				'body'    => $validate_ipn,
				'timeout' => 60,
			);

			// Post back to get a response
			$response = wp_remote_post( $this->paypal_payment_url, $params );

			if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				$body = wp_remote_retrieve_body( $response );
				if ( 'VERIFIED' === $body ) {
					return true;
				}
			} else {
				error_log( 'Error code paypal validate ipn: ' . $response['response']['code'] );
				error_log( 'Error code paypal validate ipn: ' . $response->get_error_message() );
			}

			return false;
		}

		/**
		 * Handle payment.
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment( $order_id = 0 ): array {
			$paypal_payment_url = '';

			try {
				$order       = new LP_Order( $order_id );
				$paypal_args = $this->get_paypal_args( $order );

				$paypal_payment_url = $this->paypal_url . '?' . http_build_query( $paypal_args );
			} catch ( Throwable $e ) {
				error_log( $e->getMessage() );
			}

			return array(
				'result'   => ! empty( $paypal_payment_url ) ? 'success' : 'fail',
				'redirect' => $paypal_payment_url,
			);
		}

		/**
		 * Prepare args to send to PayPal
		 *
		 * @param LP_Order $order
		 *
		 * @return array
		 * @since 3.0.0
		 * @version 1.0.1
		 */
		public function get_paypal_args( LP_Order $order ): array {
			$checkout   = LearnPress::instance()->checkout();
			$custom     = array(
				'order_id'       => $order->get_id(),
				'order_key'      => $order->get_order_key(),
				'checkout_email' => $checkout->get_checkout_email(),
			);
			$lp_cart    = LearnPress::instance()->get_cart();
			$cart_total = $lp_cart->calculate_totals();
			$item_arg   = [
				'item_name_1' => $order->get_order_number(),
				'quantity_1'  => 1,
				'amount_1'    => $cart_total->total,
			];
			$args       = array_merge(
				array(
					'cmd'           => '_cart',
					'business'      => $this->paypal_email,
					'no_note'       => 1,
					'currency_code' => learn_press_get_currency(),
					'charset'       => 'utf-8',
					'rm'            => is_ssl() ? 2 : 1,
					'upload'        => 1,
					'return'        => esc_url_raw( $this->get_return_url( $order ) ),
					'cancel_return' => esc_url_raw( learn_press_is_enable_cart() ? learn_press_get_page_link( 'cart' ) : get_home_url() ),
					'bn'            => 'LearnPress_Cart',
					'custom'        => json_encode( $custom ),
					'notify_url'    => get_home_url() . '/?paypal_notify=1',
				),
				$item_arg
			);

			return apply_filters( 'learn-press/paypal/args', $args );
		}

		public function get_access_token() {
			$client_id     = $this->client_id;
			$client_secret = $this->client_secret;
			if ( ! $client_id ) {
				throw new Exception( 'Paypal Client id is required.', 'learnpress' );
			}
			if ( ! $client_secret ) {
				throw new Exception( 'Paypal Client secret is required', 'learnpress' );
			}
			$data     = array( 'grant_type' => 'client_credentials' );
			$response = wp_remote_post(
				$this->api_url . 'v1/oauth2/token',
				array(
					'body'    => $data,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					),
					'timeout' => 60,
				)
			);
			return $response['response']['code'] == 200 ? wp_remote_retrieve_body( $response ) : false;
		}

		public function create_payment_url( LP_Order $order ) {
			$lp_cart          = LearnPress::instance()->get_cart();
			$cart_total       = $lp_cart->calculate_totals();
			$data             = [
				'intent'         => 'CAPTURE',
				'purchase_units' => [
					[
						'amount'    => [
							'currency_code' => learn_press_get_currency(),
							'value'         => $cart_total,
						],
						'custom_id' => $order->get_id(),
					],
				],
				'payment_source' => [
					'paypal' => [
						'experience_context' => [
							'payment_method_preference' => 'UNRESTRICTED',
							'brand_name'                => get_bloginfo(),
							// "locale" => "en-US",
							'landing_page'              => 'LOGIN',
							'user_action'               => 'PAY_NOW',
							'return_url'                => esc_url_raw( $this->get_return_url( $order ) ),
							'cancel_url'                => esc_url_raw( learn_press_is_enable_cart() ? learn_press_get_page_link( 'cart' ) : get_home_url() ),
						],
					],
				],
			];
			$access_token_obj = $this->get_access_token();
			if ( ! $access_token_obj ) {
				throw new Exception( 'Invalid Paypal access token', 'learnpress' );
			}
			$access_token_obj = json_decode( $access_token_obj );
			$response         = wp_remote_post(
				$this->api_url . 'v2/checkout/orders',
				array(
					'body'    => $data,
					'headers' => array(
						'Authorization' => $access_token_obj->token_type . ' ' . $access_token_obj->access_token,
						'Content-Type'  => 'application/json',
					),
					'timeout' => 60,
				)
			);
			$checkout_url     = '';
			if ( $response['response']['code'] === 200 ) {
				$body        = wp_remote_retrieve_body( $response );
				$transaction = json_decode( $body );
				if ( $transaction->links ) {
					foreach ( $transaction->links as $obj ) {
						if ( $obj->rel == 'payer-action' ) {
							$checkout_url = $obj->href;
							break;
						}
					}
				}
			} else {
				throw new Exception( 'Cannot create order', 'learnpress' );
			}
			return $checkout_url;
		}

		/**
		 * Settings form fields for this gateway
		 *
		 * @return array
		 */
		public function get_settings(): array {
			return Config::instance()->get( $this->id, 'settings/gateway' );
		}
	}
}

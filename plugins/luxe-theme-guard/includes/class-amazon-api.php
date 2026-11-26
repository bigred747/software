<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Official Amazon Creators API client. PA-API v5 is retired (May 2026).
 * Read-only GetItems. Never writes post_content. Never scrapes amazon.com.
 */
class Luxe_Theme_Guard_Amazon_API {

	const TOKEN_URL = 'https://api.amazon.com/auth/o2/token';
	const GET_ITEMS = 'https://creatorsapi.amazon/catalog/v1/getItems';
	const OPTION_TOKEN = 'luxe_theme_guard_amazon_token';

	/**
	 * @return bool
	 */
	public static function has_credentials() {
		$settings = class_exists( 'Luxe_Theme_Guard_Plugin' ) ? Luxe_Theme_Guard_Plugin::instance()->settings() : array();
		$id       = isset( $settings['amazon_client_id'] ) ? trim( (string) $settings['amazon_client_id'] ) : '';
		$secret   = isset( $settings['amazon_client_secret'] ) ? trim( (string) $settings['amazon_client_secret'] ) : '';
		return ( strlen( $id ) >= 8 && strlen( $secret ) >= 8 );
	}

	/**
	 * Official catalog facts for one ASIN. Empty array on skip/failure.
	 *
	 * @param string $asin ASIN.
	 * @param string $tag  Partner tag.
	 * @return array
	 */
	public static function get_item( $asin, $tag ) {
		$asin = Luxe_Theme_Guard_Amazon::extract_asin( $asin );
		if ( ! $asin || ! self::has_credentials() ) {
			return array();
		}
		if ( ! Luxe_Theme_Guard_Amazon::may_request( self::GET_ITEMS ) || ! Luxe_Theme_Guard_Amazon::may_request( self::TOKEN_URL ) ) {
			return array();
		}
		$token = self::token();
		if ( '' === $token ) {
			return array();
		}
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return array();
		}
		$response = wp_remote_post(
			self::GET_ITEMS,
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'x-marketplace' => Luxe_Theme_Guard_Amazon::MARKET,
				),
				'body'    => wp_json_encode(
					array(
						'itemIds'     => array( $asin ),
						'itemIdType'  => 'ASIN',
						'marketplace' => Luxe_Theme_Guard_Amazon::MARKET,
						'partnerTag'  => Luxe_Theme_Guard_Amazon::sanitize_tag( $tag ),
						'resources'   => array(
							'itemInfo.title',
							'images.primary.small',
							'parentASIN',
						),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$item = array();
		if ( ! empty( $data['itemsResult']['items'][0] ) && is_array( $data['itemsResult']['items'][0] ) ) {
			$item = $data['itemsResult']['items'][0];
		}
		$title = '';
		if ( ! empty( $item['itemInfo']['title']['displayValue'] ) ) {
			$title = (string) $item['itemInfo']['title']['displayValue'];
		}
		return array(
			'asin'  => $asin,
			'title' => $title,
			'mode'  => 'creators',
		);
	}

	/**
	 * @return string
	 */
	private static function token() {
		$cached = get_option( self::OPTION_TOKEN, array() );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && ! empty( $cached['exp'] ) && (int) $cached['exp'] > ( time() + 60 ) ) {
			return (string) $cached['token'];
		}
		$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
		$id       = isset( $settings['amazon_client_id'] ) ? trim( (string) $settings['amazon_client_id'] ) : '';
		$secret   = isset( $settings['amazon_client_secret'] ) ? trim( (string) $settings['amazon_client_secret'] ) : '';
		if ( '' === $id || '' === $secret || ! function_exists( 'wp_remote_post' ) ) {
			return '';
		}
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'grant_type'    => 'client_credentials',
						'client_id'     => $id,
						'client_secret' => $secret,
						'scope'         => 'creatorsapi::default',
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return '';
		}
		$ttl = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		if ( $ttl < 120 ) {
			$ttl = 120;
		}
		update_option(
			self::OPTION_TOKEN,
			array(
				'token' => (string) $data['access_token'],
				'exp'   => time() + $ttl,
			),
			false
		);
		return (string) $data['access_token'];
	}
}

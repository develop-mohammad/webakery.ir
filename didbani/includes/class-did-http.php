<?php
defined( 'ABSPATH' ) || exit;

/**
 * GET محدود برای کرول صفحات عمومی.
 */
class DID_Http {

	/** @var callable|null function(string $url, array $args): array */
	public static $transport = null;

	/**
	 * @param string $url
	 * @param array  $args
	 * @return array{ok:bool,status:int,body:string,error:string,final_url:string,content_type:string}
	 */
	public static function get( $url, array $args = array() ) {
		$url = DID_Url::canonical( $url );
		$out = array(
			'ok'           => false,
			'status'       => 0,
			'body'         => '',
			'error'        => '',
			'final_url'    => $url,
			'content_type' => '',
		);
		if ( '' === $url ) {
			$out['error'] = 'آدرس نامعتبر است.';
			return $out;
		}

		$headers = array( 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' );
		if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
			$headers = array_merge( $headers, $args['headers'] );
		}

		$req = array(
			'timeout'     => isset( $args['timeout'] ) ? (int) $args['timeout'] : 15,
			'redirection' => 4,
			'user-agent'  => DID_UA,
			'headers'     => $headers,
			'sslverify'   => true,
		);

		if ( is_callable( self::$transport ) ) {
			$res = call_user_func( self::$transport, $url, $req );
		} elseif ( function_exists( 'wp_remote_get' ) ) {
			$res = wp_remote_get( $url, $req );
		} else {
			$out['error'] = 'کلاینت HTTP در دسترس نیست.';
			return $out;
		}

		if ( is_array( $res ) && isset( $res['did_http'] ) ) {
			return $res;
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $res ) ) {
			$out['error'] = $res->get_error_message();
			return $out;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$ct   = (string) wp_remote_retrieve_header( $res, 'content-type' );
		$max  = isset( $args['max_bytes'] ) ? (int) $args['max_bytes'] : 512000;
		if ( strlen( $body ) > $max ) {
			$body = substr( $body, 0, $max );
		}

		$out['status']       = $code;
		$out['body']         = $body;
		$out['content_type'] = $ct;
		$out['final_url']    = $url;
		if ( isset( $res['http_response'] ) && is_object( $res['http_response'] ) && method_exists( $res['http_response'], 'get_response_object' ) ) {
			$resp = $res['http_response']->get_response_object();
			if ( is_object( $resp ) && ! empty( $resp->url ) ) {
				$out['final_url'] = $resp->url;
			}
		}
		$out['ok'] = $code >= 200 && $code < 400;
		if ( ! $out['ok'] ) {
			$out['error'] = 'کد پاسخ HTTP: ' . $code;
		}
		return $out;
	}
}

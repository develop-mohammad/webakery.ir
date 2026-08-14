<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_Video_Shortcode {
	public static function init(): void {
		add_shortcode( 'protected_video', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style( 'svac-frontend', SVAC_URL . 'assets/css/frontend.css', array(), SVAC_VERSION );
	}

	public static function register_block(): void {
		wp_register_script(
			'svac-protected-video-block',
			SVAC_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			SVAC_VERSION,
			true
		);
		register_block_type(
			'svac/protected-video',
			array(
				'api_version'     => '2',
				'render_callback' => array( __CLASS__, 'render_block' ),
				'editor_script'   => 'svac-protected-video-block',
				'attributes'      => array(
					'videoId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	public static function render_block( array $attributes ): string {
		return self::render( absint( $attributes['videoId'] ?? 0 ) );
	}

	public static function render_shortcode( array $attributes ): string {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'protected_video' );
		return self::render( absint( $attributes['id'] ) );
	}

	private static function render( int $video_id ): string {
		$result = SVAC_Access_Control::can_access( $video_id );
		if ( ! $result['allowed'] ) {
			if ( 'not_found' !== $result['reason'] ) {
				SVAC_Access_Logs::log( $video_id, false );
			}
			return '<div class="svac-access-message" role="alert">' . SVAC_Access_Control::message( $result['reason'] ) . '</div>';
		}
		$url = SVAC_Video_Post_Type::get_meta( $video_id )['url'];
		if ( '' === $url ) {
			return '<div class="svac-access-message" role="alert">' . esc_html__( 'برای این ویدیو نشانی معتبری ثبت نشده است.', 'smart-video-access-control' ) . '</div>';
		}
		SVAC_Access_Logs::log( $video_id, true );
		return '<div class="svac-protected-video" data-video-id="' . esc_attr( (string) $video_id ) . '">' . self::player_markup( $url, get_the_title( $video_id ) ) . '</div>';
	}

	private static function player_markup( string $url, string $title ): string {
		$youtube_id = self::youtube_id( $url );
		if ( $youtube_id ) {
			return sprintf( '<iframe src="https://www.youtube-nocookie.com/embed/%1$s" title="%2$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', esc_attr( $youtube_id ), esc_attr( $title ) );
		}
		if ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#i', $url, $matches ) ) {
			return sprintf( '<iframe src="https://player.vimeo.com/video/%1$s" title="%2$s" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>', esc_attr( $matches[1] ), esc_attr( $title ) );
		}
		if ( preg_match( '/\.mp4(?:\?.*)?$/i', $url ) ) {
			return sprintf( '<video controls controlsList="nodownload" preload="metadata"><source src="%1$s" type="video/mp4">%2$s</video>', esc_url( $url ), esc_html__( 'مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.', 'smart-video-access-control' ) );
		}
		return sprintf( '<iframe src="%1$s" title="%2$s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', esc_url( $url ), esc_attr( $title ) );
	}

	private static function youtube_id( string $url ): string {
		if ( preg_match( '#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})#', $url, $matches ) ) {
			return $matches[1];
		}
		return '';
	}
}

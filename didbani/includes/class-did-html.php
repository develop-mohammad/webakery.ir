<?php
defined( 'ABSPATH' ) || exit;

/**
 * استخراج عنوان، متا، H1، canonical، متن و لینک‌های داخلی از HTML عمومی.
 */
class DID_Html {

	/**
	 * @param string $html
	 * @param string $base_url
	 * @return array<string,mixed>
	 */
	public static function parse( $html, $base_url = '' ) {
		$out = array(
			'title'             => '',
			'meta_description'  => '',
			'h1'                => '',
			'canonical'         => '',
			'robots'            => '',
			'body_text'         => '',
			'word_count'        => 0,
			'links'             => array(),
		);

		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return $out;
		}

		$xpath = new DOMXPath( $dom );

		$title_node = $xpath->query( '//title' );
		if ( $title_node && $title_node->length ) {
			$out['title'] = self::text( $title_node->item( 0 ) );
		}

		$out['meta_description'] = self::meta_content( $xpath, 'description' );
		$out['robots']           = self::meta_content( $xpath, 'robots' );

		$canon = $xpath->query( '//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]/@href' );
		if ( $canon && $canon->length ) {
			$out['canonical'] = DID_Url::canonical( self::text( $canon->item( 0 ) ) );
		}

		$h1 = $xpath->query( '//h1' );
		if ( $h1 && $h1->length ) {
			$out['h1'] = self::text( $h1->item( 0 ) );
		}

		foreach ( array( 'script', 'style', 'noscript', 'svg' ) as $tag ) {
			$nodes = $dom->getElementsByTagName( $tag );
			$remove = array();
			foreach ( $nodes as $node ) {
				$remove[] = $node;
			}
			foreach ( $remove as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$out['body_text'] = $body ? self::text( $body ) : self::text( $dom->documentElement );
		$out['word_count'] = self::word_count( $out['body_text'] );

		$base_host = DID_Url::host( $base_url );
		$links     = array();
		$seen      = array();
		$a_nodes   = $dom->getElementsByTagName( 'a' );
		foreach ( $a_nodes as $a ) {
			if ( ! $a instanceof DOMElement ) {
				continue;
			}
			$href = $a->getAttribute( 'href' );
			$abs  = DID_Url::absolute( $href, $base_url );
			if ( '' === $abs || ! DID_Url::is_htmlish( $abs ) ) {
				continue;
			}
			if ( $base_host && ! DID_Url::host_matches( DID_Url::host( $abs ), $base_host ) ) {
				continue;
			}
			if ( isset( $seen[ $abs ] ) ) {
				continue;
			}
			$seen[ $abs ] = true;
			$links[]      = $abs;
		}
		$out['links'] = $links;

		return $out;
	}

	/**
	 * @param DOMXPath $xpath
	 * @param string   $name
	 * @return string
	 */
	private static function meta_content( DOMXPath $xpath, $name ) {
		$query = sprintf(
			'//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@content',
			$name
		);
		$nodes = $xpath->query( $query );
		if ( $nodes && $nodes->length ) {
			return self::text( $nodes->item( 0 ) );
		}
		return '';
	}

	/**
	 * @param DOMNode|null $node
	 * @return string
	 */
	private static function text( $node ) {
		if ( ! $node ) {
			return '';
		}
		$t = $node->textContent;
		$t = html_entity_decode( (string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$t = preg_replace( '/\s+/u', ' ', $t );
		return trim( (string) $t );
	}

	/**
	 * @param string $text
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = trim( DID_Text::normalize( $text ) );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( $parts ) : 0;
	}
}

<?php
/**
 * کرول زندهٔ محدود روی example.com (بدون وردپرس).
 *   php tests/live-smoke.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DID_UA', 'Didbani/1.0 (+https://webakery.ir)' );

require_once dirname( __DIR__ ) . '/includes/class-did-text.php';
require_once dirname( __DIR__ ) . '/includes/class-did-url.php';
require_once dirname( __DIR__ ) . '/includes/class-did-html.php';
require_once dirname( __DIR__ ) . '/includes/class-did-robots.php';
require_once dirname( __DIR__ ) . '/includes/class-did-http.php';

function did_fetch( $url ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_USERAGENT      => DID_UA,
			CURLOPT_HTTPHEADER     => array( 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' ),
		)
	);
	$body = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$ct   = (string) curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
	$err  = curl_error( $ch );
	curl_close( $ch );
	return array(
		'ok'           => $body !== false && $code >= 200 && $code < 400,
		'status'       => $code,
		'body'         => is_string( $body ) ? $body : '',
		'error'        => $err,
		'content_type' => $ct,
		'final_url'    => $url,
	);
}

$fails = 0;
$home  = did_fetch( 'https://example.com/' );
if ( empty( $home['ok'] ) ) {
	fwrite( STDERR, "live fetch failed: {$home['status']} {$home['error']}\n" );
	exit( 1 );
}

$parsed = DID_Html::parse( $home['body'], 'https://example.com/' );
echo "status={$home['status']}\n";
echo "title={$parsed['title']}\n";
echo "h1={$parsed['h1']}\n";
echo "words={$parsed['word_count']}\n";
echo "links=" . count( $parsed['links'] ) . "\n";

if ( '' === $parsed['title'] ) {
	echo "FAIL empty title\n";
	$fails++;
} else {
	echo "ok  live title\n";
}
if ( $parsed['word_count'] < 1 ) {
	echo "FAIL no words\n";
	$fails++;
} else {
	echo "ok  live word count\n";
}

$robots = did_fetch( 'https://example.com/robots.txt' );
if ( ! empty( $robots['ok'] ) ) {
	$rules = DID_Robots::parse( $robots['body'] );
	echo 'ok  robots fetched, disallow=' . count( $rules['disallow'] ) . "\n";
} else {
	echo "ok  robots missing (non-fatal)\n";
}

$hits = DID_Text::keyword_hits( 'example', $parsed['title'], $parsed['h1'], $parsed['body_text'] );
echo 'keyword example in_title=' . $hits['in_title'] . ' in_body=' . $hits['in_body'] . "\n";

exit( $fails ? 1 : 0 );

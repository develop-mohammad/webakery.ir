<?php
/**
 * دانلود امن ZIP — GET: product, license_key, domain
 */
if ( file_exists( __DIR__ . '/../config.php' ) ) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/LicenseManager.php';
require_once __DIR__ . '/../includes/UpdateManager.php';

$product     = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', trim( (string) ( $_GET['product'] ?? '' ) ) ) );
$license_key = trim( (string) ( $_GET['license_key'] ?? $_GET['license'] ?? '' ) );
$domain      = UpdateManager::clean_domain( (string) ( $_GET['domain'] ?? '' ) );

if ( $product === '' || $license_key === '' || $domain === '' ) {
    http_response_code( 400 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'product, license_key, domain required';
    exit;
}

$val = LicenseManager::validate( $license_key, $domain, $product );
if ( empty( $val['valid'] ) ) {
    http_response_code( 403 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'Invalid license';
    exit;
}

$zip = UpdateManager::zip_path( $product );
if ( ! $zip ) {
    http_response_code( 404 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'ZIP not found on server';
    exit;
}

header( 'Content-Type: application/zip' );
header( 'Content-Disposition: attachment; filename="' . basename( $zip ) . '"' );
header( 'Content-Length: ' . filesize( $zip ) );
readfile( $zip );
exit;

<?php
/**
 * Entry point for the Intuition web API.
 *
 * @copyright 2017 Timo Tijhof
 * @license MIT
 */

/**
 * Set up
 * -------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Don't output HTML-formatted errors
ini_set( 'display_errors', 0 );

$I18N = new Intuition( 'web' );

if ( file_exists( __DIR__ . '/../config.php' ) ) {
	require_once __DIR__ . '/../config.php';
};

/**
 * Request
 * -------------------------------------------------
 */

/**
 * @param array $data
 */
function i18nApiResp( array $data ) {
	global $kgReq;

	$callback = $kgReq->getVal( 'callback' );

	// Allow CORS (to avoid having to use JSON-P with cache busting callback)
	$kgReq->setHeader( 'Access-Control-Allow-Origin', '*' );

	// We don't yet support retrieval of when the localisation was last updated,
	// so default to unconditionally caching for 5 minutes.
	$maxAge = 5 * 60;
	$kgReq->setHeader( 'Last-Modified', gmdate( 'D, d M Y H:i:s', time() ) . ' GMT' );
	$kgReq->setHeader( 'Cache-Control', 'public, max-age=' . intval( $maxAge ) );
	$kgReq->setHeader( 'Expires', gmdate( 'D, d M Y H:i:s', time() + $maxAge ) . ' GMT' );

	if ( $kgReq->tryLastModified( time() - $maxAge ) ) {
		exit;
	}

	// Serve as JSON or JSON-P
	if ( $callback === null ) {
		$kgReq->setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		echo json_encode( $data );
	} else {
		$kgReq->setHeader( 'Content-Type', 'text/javascript; charset=utf-8' );

		// Sanatize callback
		$callback = kfSanatizeJsCallback( $callback );
		echo $callback . '(' . json_encode( $data ) .');';
	}

	exit;
}

$domains = $kgReq->getVal( 'domains', false );
$lang = $kgReq->getVal( 'lang', $I18N->getLang() );

/**
 * Response
 * -------------------------------------------------
 */

$resp = [];

if ( !$domains ) {
	// HTTP 400 Bad Request
	http_response_code( 400 );
	$resp['error'] = 'Parameter "domains" is required';
	i18nApiResp( $resp );
}

$domains = explode( '|', $domains );

$resp['messages'] = [];

foreach ( $domains as $domain ) {
	$exists = $I18N->getDomainInfo( $domain );

	if ( !$exists ) {
		$resp['messages'][$domain] = false;
		continue;
	}

	$keys = $I18N->listMsgs( $domain );

	foreach ( $keys as $key ) {
		$resp['messages'][$domain][$key] = $I18N->rawMsg( $domain, $lang, $key );
	}
}

i18nApiResp( $resp );

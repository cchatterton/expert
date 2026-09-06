<?php
/** Public-web fetching, robots policy and safe extraction. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_public_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ( isset( $parts['port'] ) && ! in_array( $parts['port'], array( 80, 443 ), true ) ) ) {
		return false;
	}
	$host = strtolower( trim( $parts['host'] ?? '', '[]' ) );
	if ( ! $host || 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
		return false;
	}
	$addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : array_merge( gethostbynamel( $host ) ?: array(), array_column( dns_get_record( $host, DNS_AAAA ) ?: array(), 'ipv6' ) );
	if ( ! $addresses ) {
		return false;
	}
	foreach ( $addresses as $address ) {
		if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || str_starts_with( strtolower( $address ), '::ffff:' ) ) {
			return false;
		}
	}
	return (bool) wp_http_validate_url( $url );
}
function expert_web_get( $url ) {
	if ( ! expert_public_url( $url ) ) {
		return expert_error( 'unsafe_url' );
	}
	$remaining = isset( $GLOBALS['expert_budget'] ) ? $GLOBALS['expert_budget']['deadline'] - microtime( true ) : 10;
	if ( $remaining <= 0 ) {
		return expert_error( 'loop_timeout' );
	}
	// Redirects are deliberately disabled: no implicit second network destination.
	return wp_safe_remote_get(
		$url,
		array(
			'timeout'             => min( 10, $remaining ),
			'redirection'         => 0,
			'limit_response_size' => expert_limits()['bytes'],
			'user-agent'          => 'Expert/' . EXPERT_VERSION . ' (bounded research; ' . home_url( '/' ) . ')',
		)
	);
}
function expert_robots_allowed( $text, $path ) {
	$applies   = false;
	$seen_rule = false;
	$best      = -1;
	$allowed   = true;
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = trim( preg_replace( '/#.*/', '', $line ) );
		if ( ! str_contains( $line, ':' ) ) {
			continue;
		}
		list( $key, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
		$key                 = strtolower( $key );
		if ( 'user-agent' === $key ) {
			if ( $seen_rule ) {
				$applies   = false;
				$seen_rule = false;
			}
			$applies = $applies || '*' === $value || str_contains( strtolower( $value ), 'expert' );
		} elseif ( in_array( $key, array( 'allow', 'disallow' ), true ) ) {
			$seen_rule = true;
			if ( ! $applies || '' === $value ) {
				continue;
			}
			$pattern = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $value, '#' ) ) . '#';
			if ( preg_match( $pattern, $path ) && strlen( $value ) >= $best ) {
				$best    = strlen( $value );
				$allowed = 'allow' === $key;
			}
		}
	}
	return $allowed;
}
function expert_fetch_source( $url ) {
	expert_budget( 'fetches' );
	if ( ! expert_public_url( $url ) ) {
		return expert_error( 'unsafe_url' );
	}
	$parts      = wp_parse_url( $url );
	$origin     = $parts['scheme'] . '://' . $parts['host'];
	$robots_key = 'expert_robots_' . md5( $origin );
	$robots     = get_transient( $robots_key );
	if ( false === $robots ) {
		$response = expert_web_get( $origin . '/robots.txt' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status, array( 200, 404 ), true ) ) {
			return expert_error( 'robots_unavailable' );
		}
		$robots = 404 === $status ? '' : wp_remote_retrieve_body( $response );
		set_transient( $robots_key, $robots, 3600 );
	}
	if ( ! expert_robots_allowed( $robots, ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) ) ) {
		return expert_error( 'robots_disallowed' );
	}
	$response = expert_web_get( $url );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return expert_error( 'source_http' );
	}
	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	if ( ! str_contains( $content_type, 'text/html' ) && ! str_contains( $content_type, 'text/plain' ) ) {
		return expert_error( 'source_type' );
	}
	$html = wp_remote_retrieve_body( $response );
	if ( strlen( $html ) >= expert_limits()['bytes'] ) {
		return expert_error( 'source_too_large' );
	}
	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$xpath = new DOMXPath( $doc );
	foreach ( $xpath->query( '//script|//style|//form|//iframe|//nav|//footer|//header|//noscript' ) as $node ) {
		$node->parentNode->removeChild( $node );
	}
	$meta      = static function ( $query ) use ( $xpath ) {
		$nodes = $xpath->query( $query );
		return $nodes->length ? trim( $nodes->item( 0 )->nodeValue ) : '';
	};
	$canonical = $meta( '//link[@rel="canonical"]/@href' );
	if ( ! $canonical || ! expert_public_url( $canonical ) || wp_parse_url( $canonical, PHP_URL_HOST ) !== $parts['host'] ) {
		$canonical = $url;
	}
	$text = trim( preg_replace( '/\s+/u', ' ', $doc->textContent ) );
	if ( strlen( $text ) < 300 ) {
		return expert_error( 'source_too_short' );
	}
	return array(
		'url'       => esc_url_raw( $url ),
		'canonical' => expert_normalise_url( $canonical ),
		'title'     => sanitize_text_field( $meta( '//title' ) ?: $parts['host'] ),
		'publisher' => sanitize_text_field( $meta( '//meta[@property="og:site_name"]/@content' ) ?: $parts['host'] ),
		'author'    => sanitize_text_field( $meta( '//meta[@name="author"]/@content' ) ),
		'published' => sanitize_text_field( $meta( '//meta[@property="article:published_time"]/@content' ) ),
		'retrieved' => gmdate( 'c' ),
		'type'      => 'Web page',
		'hash'      => hash( 'sha256', $text ),
		'text'      => mb_substr( $text, 0, 10000 ),
	);
}
function expert_normalise_url( $url ) {
	$parts = wp_parse_url( $url );
	parse_str( $parts['query'] ?? '', $query );
	foreach ( array_keys( $query ) as $key ) {
		if ( str_starts_with( $key, 'utm_' ) || in_array( $key, array( 'fbclid', 'gclid' ), true ) ) {
			unset( $query[ $key ] );
		}
	}
	ksort( $query );
	return strtolower( $parts['scheme'] . '://' . $parts['host'] ) . ( $parts['path'] ?? '/' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}
function expert_discover( $topic ) {
	expert_budget( 'searches' );
	if ( function_exists( 'expert_browser_ai_enabled' ) && expert_browser_ai_enabled() ) {
		return expert_browser_web_discover( $topic );
	}
	$base = expert_config()['discovery_url'];
	if ( ! expert_loopback_url( $base ) ) {
		return expert_error( 'discovery_not_configured' );
	}
	$base     = preg_replace( '#://localhost(?=[:/]|$)#i', '://127.0.0.1', $base );
	$response = wp_remote_get(
		untrailingslashit( $base ) . '/search?q=' . rawurlencode( $topic ) . '&format=json',
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => 65536,
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return expert_error( 'discovery_unavailable' );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data['results'] ?? null ) ) {
		return expert_error( 'discovery_shape' );
	}
	return array_slice( $data['results'], 0, expert_limits()['candidates'] );
}

/** Free public discovery for browser-local mode; inference remains on the member device. */
function expert_browser_web_discover( $topic ) {
	$url      = 'https://www.bing.com/search?format=rss&q=' . rawurlencode( $topic );
	$response = wp_safe_remote_get( $url, array( 'timeout' => 12, 'redirection' => 0, 'limit_response_size' => 131072, 'user-agent' => 'Expert/' . EXPERT_VERSION ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return expert_error( 'discovery_unavailable' );
	}
	$xml = simplexml_load_string( wp_remote_retrieve_body( $response ), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	if ( ! $xml || empty( $xml->channel->item ) ) {
		return expert_error( 'discovery_shape' );
	}
	$results = array();
	foreach ( $xml->channel->item as $item ) {
		$link = esc_url_raw( (string) $item->link );
		if ( expert_public_url( $link ) ) {
			$results[] = array( 'url' => $link, 'title' => sanitize_text_field( (string) $item->title ) );
		}
		if ( count( $results ) >= expert_limits()['candidates'] ) {
			break;
		}
	}
	return $results;
}

<?php
/**
 * Generate an OpenAPI 3.1 spec for the WP Sell Services REST API from the LIVE route
 * registrations, scoped to the WP Sell Services namespaces only.
 *
 * Reusable across the portfolio: the logic is generic — everything
 * plugin-specific lives in docs/api/openapi.config.json (namespaces, title,
 * auth schemes, tags). Point it at a different plugin's config and it produces
 * that plugin's spec. Because it reads only the namespaces in the config, the
 * output NEVER contains routes from other plugins on the same install.
 *
 * Each path carries `x-wpss-tier: free|pro` so a Free-only site knows which
 * routes it will not find; Pro is detected by the handler's file living outside
 * the Free plugin directory.
 *
 * RUN:   wp eval-file wp-content/plugins/wp-sell-services/bin/gen-openapi.php
 *        (run on a COMBO install so both wpss/v1 + wpss-pro/v1 register,
 *        with EVERY Pro extension ENABLED — a disabled extension never boots,
 *        so its routes never register and the spec silently loses them. The
 *        enterprise-standards family (h5p/lti/qti/scorm/xapi) is toggled off
 *        on many dev boxes, which kept 10 real endpoints out of the published
 *        catalogue until 2026-07-21.)
 * OUT:   docs/api/openapi.json   (+ merges docs/api/openapi.overlay.json if present)
 *
 * @package WPSellServices
 */

namespace WPSellServices\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file (WordPress must be loaded).\n" );
	exit( 1 );
}

$plugin_dir = dirname( __DIR__ );
$api_dir    = $plugin_dir . '/docs/api';
$config     = json_decode( (string) @file_get_contents( $api_dir . '/openapi.config.json' ), true );

if ( ! is_array( $config ) || empty( $config['namespaces'] ) ) {
	fwrite( STDERR, "Missing or invalid docs/api/openapi.config.json (need at least namespaces[]).\n" );
	exit( 1 );
}

/*
 * Refuse to generate on the wrong rail.
 *
 * A route only exists in the spec if it is registered on the site the generator
 * runs against, and whole controllers are rail-gated: PaymentController
 * registers only when wpss_uses_standalone_payments() is true, because a cart
 * adapter owns all payment when one is active. Generating on a WooCommerce site
 * therefore produces a spec with every /payments route silently missing - and
 * the mobile client builds itself from this document.
 *
 * The config has always DECLARED the required rail in _generation.rail and
 * explained why. Nothing read it, so the declaration was prose: it was possible
 * to regenerate on the wrong rail, commit an 11-route regression, and have every
 * gate pass. That is exactly what happened producing the 1.7.2 spec (184 paths
 * down to 173).
 *
 * Fail loudly instead, and say how to fix it.
 */
$required_rail = (string) ( $config['_generation']['rail'] ?? '' );

if ( '' !== $required_rail && function_exists( 'wpss_get_option' ) ) {
	$active_rail = (string) wpss_get_option( 'general', 'ecommerce_platform' );

	if ( $active_rail !== $required_rail ) {
		fwrite(
			STDERR,
			sprintf(
				"Refusing to generate: this spec must be built on the '%s' rail, but the site is on '%s'.
"
				. "Rail-gated controllers do not register on the wrong rail, so the spec would silently lose routes.
"
				. "Set Settings > General > E-commerce Platform to '%s', regenerate, then set it back.
",
				$required_rail,
				'' === $active_rail ? '(unset)' : $active_rail,
				$required_rail
			)
		);
		exit( 1 );
	}
}

/**
 * Turn a WP route regex into an OpenAPI path template + its path-parameter names.
 * `/wpss/v1/orders/(?P<id>[\d]+)` -> ['/wpss/v1/orders/{id}', ['id']]
 */
$to_openapi_path = static function ( string $route ): array {
	$params = array();
	$path   = preg_replace_callback(
		'/\(\?P<([a-zA-Z0-9_]+)>[^)]*\)/',
		static function ( $m ) use ( &$params ) {
			$params[] = $m[1];
			return '{' . $m[1] . '}';
		},
		$route
	);
	// Drop any remaining bare regex groups (optional trailing slashes etc.).
	$path = preg_replace( '/\(\?[^)]*\)\??/', '', (string) $path );
	return array( rtrim( (string) $path, '/' ) ?: '/', $params );
};

/** Map a WP arg definition to an OpenAPI schema object. */
$arg_to_schema = static function ( array $arg ) use ( &$arg_to_schema ): array {
	$schema = array();
	$type   = $arg['type'] ?? null;
	if ( is_array( $type ) ) {
		$type = $type[0] ?? 'string';
	}
	if ( $type ) {
		$schema['type'] = $type;
	}
	foreach ( array( 'format', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern' ) as $k ) {
		if ( isset( $arg[ $k ] ) ) {
			$schema[ $k ] = $arg[ $k ];
		}
	}
	if ( isset( $arg['enum'] ) && is_array( $arg['enum'] ) ) {
		$schema['enum'] = array_values( $arg['enum'] );
	}
	if ( array_key_exists( 'default', $arg ) ) {
		$schema['default'] = $arg['default'];
	}
	if ( 'array' === ( $schema['type'] ?? '' ) ) {
		$schema['items'] = isset( $arg['items'] ) && is_array( $arg['items'] )
			? $arg_to_schema( $arg['items'] )
			: array( 'type' => 'string' );
	}
	if ( 'object' === ( $schema['type'] ?? '' ) && isset( $arg['properties'] ) && is_array( $arg['properties'] ) ) {
		$schema['properties'] = array();
		foreach ( $arg['properties'] as $pname => $pdef ) {
			$schema['properties'][ $pname ] = is_array( $pdef ) ? $arg_to_schema( $pdef ) : array();
		}
	}
	return $schema ?: array( 'type' => 'string' );
};

/** First path segment after the namespace, mapped to a display tag. */
$tag_for = static function ( string $ns, string $route ) use ( $config ): string {
	$rest = trim( substr( $route, strlen( '/' . $ns ) ), '/' );
	$seg  = strtok( $rest, '/' );
	$seg  = $seg ? preg_replace( '/[^a-z0-9-].*$/', '', strtolower( $seg ) ) : 'misc';
	return $config['tags'][ $seg ] ?? ( ucfirst( $seg ) ?: 'General' );
};

/**
 * Free or Pro, decided by where the handler's code lives: anything outside the
 * loaded Free plugin directory is Pro. Pro registers most of its routes under
 * the Free namespace (wpss/v1), so the namespace alone cannot tell them apart.
 */
$free_dir = wp_normalize_path( WPSS_PLUGIN_DIR );
$tier_for = static function ( $callback ) use ( $free_dir ): string {
	try {
		if ( is_array( $callback ) ) {
			$file = ( new \ReflectionClass( $callback[0] ) )->getFileName();
		} elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
			$file = ( new \ReflectionFunction( $callback ) )->getFileName();
		} else {
			return 'free';
		}
	} catch ( \ReflectionException $e ) {
		return 'free';
	}
	return $file && 0 === strpos( wp_normalize_path( $file ), $free_dir ) ? 'free' : 'pro';
};

$server = rest_get_server();
$paths  = array();
$tagset = array();
$counts = array( 'routes' => 0, 'operations' => 0 );

foreach ( $config['namespaces'] as $ns ) {
	$routes = $server->get_routes( $ns );
	if ( empty( $routes ) ) {
		fwrite( STDERR, "  (namespace {$ns} has no routes on this install — is the plugin active?)\n" );
		continue;
	}

	foreach ( $routes as $route => $handlers ) {
		// Skip the namespace index route itself.
		if ( rtrim( $route, '/' ) === '/' . $ns ) {
			continue;
		}

		list( $oapi_path, $path_params ) = $to_openapi_path( $route );
		$tag = $tag_for( $ns, $route );
		$tagset[ $tag ] = true;
		++$counts['routes'];

		foreach ( (array) $handlers as $handler ) {
			$methods = array();
			foreach ( (array) ( $handler['methods'] ?? array() ) as $m => $on ) {
				if ( $on ) {
					$methods[] = strtoupper( (string) $m );
				}
			}
			$args = is_array( $handler['args'] ?? null ) ? $handler['args'] : array();
			$tier = $tier_for( $handler['callback'] ?? null );

			// A path is Free as soon as any handler on it is Free; Pro only adds.
			if ( ! isset( $paths[ $oapi_path ]['x-wpss-tier'] ) || 'free' === $tier ) {
				$paths[ $oapi_path ]['x-wpss-tier'] = $tier;
			}

			// Public when the permission callback is the literal __return_true;
			// anything else (a real gate or a closure we can't introspect) is
			// documented as requiring auth — a safe over-statement the overlay
			// can relax per route.
			$is_public = ( '__return_true' === ( $handler['permission_callback'] ?? null ) );

			foreach ( $methods as $method ) {
				if ( in_array( $method, array( 'OPTIONS', 'HEAD' ), true ) ) {
					continue;
				}
				$verb = strtolower( $method );

				// No auto "METHOD /path" summary — it just duplicates the path Swagger
				// already shows, in low-contrast grey. Real summaries come from the
				// overlay for the endpoints that have one.
				$op = array(
					'tags'        => array( $tag ),
					'operationId' => $verb . '_' . trim( preg_replace( '/[^a-zA-Z0-9]+/', '_', $oapi_path ), '_' ),
					'security'    => $is_public ? array() : ( $config['default_security'] ?? array() ),
					'parameters'  => array(),
					'responses'   => array(
						'200' => array( 'description' => 'Success' ),
						'401' => array( 'description' => 'Authentication required or invalid.' ),
						'403' => array( 'description' => 'Not permitted.' ),
						'404' => array( 'description' => 'Not found.' ),
					),
				);

				// Path parameters.
				foreach ( $path_params as $pname ) {
					$pschema = isset( $args[ $pname ] ) ? $arg_to_schema( $args[ $pname ] ) : array( 'type' => 'string' );
					$op['parameters'][] = array(
						'name'     => $pname,
						'in'       => 'path',
						'required' => true,
						'schema'   => $pschema,
						'description' => $args[ $pname ]['description'] ?? '',
					);
				}

				$body_args = array();
				foreach ( $args as $aname => $adef ) {
					if ( in_array( $aname, $path_params, true ) || ! is_array( $adef ) ) {
						continue;
					}
					if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
						$body_args[ $aname ] = $adef;
					} else {
						$op['parameters'][] = array(
							'name'        => $aname,
							'in'          => 'query',
							'required'    => ! empty( $adef['required'] ),
							'schema'      => $arg_to_schema( $adef ),
							'description' => $adef['description'] ?? '',
						);
					}
				}

				if ( $body_args ) {
					$props    = array();
					$required = array();
					foreach ( $body_args as $aname => $adef ) {
						$props[ $aname ] = $arg_to_schema( $adef );
						if ( ! empty( $adef['description'] ) ) {
							$props[ $aname ]['description'] = $adef['description'];
						}
						if ( ! empty( $adef['required'] ) ) {
							$required[] = $aname;
						}
					}
					$schema = array( 'type' => 'object', 'properties' => $props );
					if ( $required ) {
						$schema['required'] = $required;
					}
					$op['requestBody'] = array(
						'required' => (bool) $required,
						'content'  => array( 'application/json' => array( 'schema' => $schema ) ),
					);
				}

				if ( empty( $op['parameters'] ) ) {
					unset( $op['parameters'] );
				}

				$paths[ $oapi_path ][ $verb ] = $op;
				++$counts['operations'];
			}
		}
	}
}

ksort( $paths );
$tags = array();
foreach ( array_keys( $tagset ) as $t ) {
	$tags[] = array( 'name' => $t );
}
sort( $tags );

/*
 * The spec's version is DERIVED, never a literal in the config.
 *
 * It used to read $config['version'], and the freshness gate cannot catch that:
 * the gate regenerates the spec and diffs it against the committed copy, and
 * both sides read the same key, so a stale stamp matches itself and passes.
 * That is how this spec sat at 1.7.1 while the plugin shipped 1.7.2 - the
 * mobile client generates against this document, so it was being told the wrong
 * API version by a check designed to prove the document was current.
 *
 * Order matters: the plugin's main-file header first, because that is the
 * version WordPress itself reads for update checks and therefore the canonical
 * one; the WPSS_VERSION constant second, which covers a directory not named
 * after its plugin file; the config key last, and only so a spec could be
 * deliberately pinned.
 */
$wpss_spec_version = '';
$wpss_main_file    = dirname( __DIR__ ) . '/wp-sell-services.php';

if ( is_readable( $wpss_main_file ) ) {
	$wpss_header = get_file_data( $wpss_main_file, array( 'Version' => 'Version' ) );
	$wpss_spec_version = trim( (string) ( $wpss_header['Version'] ?? '' ) );
}

if ( '' === $wpss_spec_version && defined( 'WPSS_VERSION' ) ) {
	$wpss_spec_version = (string) WPSS_VERSION;
}

if ( '' === $wpss_spec_version ) {
	$wpss_spec_version = (string) ( $config['version'] ?? '1.0.0' );
}

$spec = array(
	'openapi' => '3.1.0',
	'info'    => array_filter(
		array(
			'title'       => $config['title'] ?? 'REST API',
			'version'     => $wpss_spec_version,
			'description' => $config['description'] ?? '',
			'contact'     => $config['contact'] ?? null,
			'license'     => $config['license'] ?? null,
		)
	),
	'servers'    => $config['servers'] ?? array(),
	'tags'       => $tags,
	'security'   => $config['default_security'] ?? array(),
	'components' => array( 'securitySchemes' => $config['security_schemes'] ?? new \stdClass() ),
	'paths'      => $paths,
);

// Merge an optional hand-authored overlay (response bodies, richer descriptions,
// public-route corrections) deeply on top of the generated spec.
$overlay_file = $api_dir . '/openapi.overlay.json';
if ( is_readable( $overlay_file ) ) {
	$overlay = json_decode( (string) file_get_contents( $overlay_file ), true );
	if ( is_array( $overlay ) ) {
		$deep_merge = static function ( array $base, array $over ) use ( &$deep_merge ): array {
			foreach ( $over as $k => $v ) {
				$base[ $k ] = ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) )
					? $deep_merge( $base[ $k ], $v )
					: $v;
			}
			return $base;
		};
		$spec = $deep_merge( $spec, $overlay );
	}
}

if ( ! is_dir( $api_dir ) ) {
	mkdir( $api_dir, 0755, true );
}
file_put_contents(
	$api_dir . '/openapi.json',
	wp_json_encode( $spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

fwrite(
	STDOUT,
	sprintf(
		"OpenAPI written: docs/api/openapi.json\n  namespaces: %s\n  routes: %d  operations: %d  tags: %d\n",
		implode( ', ', $config['namespaces'] ),
		$counts['routes'],
		$counts['operations'],
		count( $tags )
	)
);

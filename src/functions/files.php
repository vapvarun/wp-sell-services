<?php
/**
 * Order file storage and access.
 *
 * ONE seam for every file a buyer or vendor attaches to an order. Before this,
 * five call sites each ran wp_handle_upload() and wrote the resulting PUBLIC
 * filesystem URL into the database, which produced two defects at once:
 *
 * - Cloud storage never received anything, because nothing routed to a provider
 *   (Basecamp 10239805812).
 * - "Private" hid the row, not the file: anyone holding the URL could read
 *   another buyer's brief forever (Basecamp 10239807824).
 *
 * A stored URL cannot fix both - a public cloud URL is not private, and a
 * signed URL expires and rots in the row. So nothing stores a URL any more.
 * Files are written outside the web root, optionally pushed to the configured
 * cloud provider, and addressed by a stable id that
 * wpss_get_order_file_url() turns into a permission-checked endpoint link at
 * render time.
 *
 * @package WPSellServices
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the configured cloud storage provider.
 *
 * Deliberately does NOT substitute a different provider when the configured one
 * is missing, and deliberately returns null rather than silently falling back to
 * local. Its wallet twin does exactly that and swaps in a different LEDGER
 * (Basecamp 10239808430); the same reasoning applies to a bucket - quietly
 * writing somewhere other than where the owner configured is not a graceful
 * degradation, it is a bug that hides itself.
 *
 * @since 1.7.0
 *
 * @return object|null Provider implementing StorageProviderInterface, or null.
 */
function wpss_get_active_storage_provider(): ?object {
	$active_id = (string) get_option( 'wpss_active_storage_provider', '' );

	if ( '' === $active_id || 'local' === $active_id ) {
		return null;
	}

	$provider = wpss_get_storage_provider( $active_id );

	if ( ! $provider ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is configured but not registered; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	if ( ! method_exists( $provider, 'is_configured' ) || ! $provider->is_configured() ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is registered but not configured; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	return $provider;
}

/**
 * Look up one registered storage provider by id.
 *
 * A pure registry read, deliberately separate from the ACTIVE provider: a
 * file record names the provider that holds it, and that provider must keep
 * resolving after the owner switches buckets - the file did not move.
 *
 * @since 1.7.1
 *
 * @param string $id Provider id as registered on `wpss_storage_providers` ('s3', 'gcs', 'do', ...).
 * @return object|null Provider implementing StorageProviderInterface, or null when not registered.
 */
function wpss_get_storage_provider( string $id ): ?object {
	if ( '' === $id || 'local' === $id ) {
		return null;
	}

	$providers = (array) apply_filters( 'wpss_storage_providers', array() );
	$provider  = $providers[ $id ] ?? null;

	return $provider instanceof \WPSellServices\Integrations\Contracts\StorageProviderInterface ? $provider : null;
}

/**
 * Directory that order files are written to.
 *
 * Prefers a location OUTSIDE the document root, because the guards that make an
 * uploads subdirectory private are not reliable: `.htaccess` is read by Apache
 * and ignored entirely by nginx. On the first host this was tested against
 * (nginx), a file written under uploads with a correct deny file was served
 * happily at HTTP 200 with its contents - which is the very bug this is meant
 * to fix (Basecamp 10239807824).
 *
 * Falls back to uploads when the parent of ABSPATH is not writable, which is
 * common on shared hosting. In that case the guards go down AND
 * wpss_order_files_are_public() is consulted so the owner is told rather than
 * left believing the files are private.
 *
 * @since 1.7.0
 *
 * @return string Absolute path with a trailing slash.
 */
function wpss_get_order_files_dir(): string {
	$outside = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'wpss-order-files/';

	// Only worth using if it is genuinely not under the WordPress root. On a
	// host where ABSPATH's parent IS the document root this is false and we go
	// to the fallback, which at least knows it needs checking.
	$is_outside_root = 0 !== strpos( $outside, trailingslashit( ABSPATH ) );

	if ( $is_outside_root && ( is_dir( $outside ) || wp_mkdir_p( $outside ) ) && wp_is_writable( $outside ) ) {
		// No guard file here on purpose: nothing serves this directory, so a
		// deny rule would be decoration. The guards below exist because the
		// fallback IS served.
		return $outside;
	}

	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	wpss_guard_files_dir( $dir );

	return $dir;
}

/**
 * Belt-and-braces guards on a file directory.
 *
 * Neither is sufficient on its own and one of them does nothing on nginx, which
 * is why the directory choice above matters more than either.
 *
 * @since 1.7.0
 *
 * @param string $dir Absolute path with a trailing slash.
 * @return void
 */
function wpss_guard_files_dir( string $dir ): void {
	if ( ! file_exists( $dir . '.htaccess' ) ) {
		file_put_contents( $dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	// IIS honours none of the above. Without this a Windows host had no
	// directory denial at all - the .htaccess is inert there, and the guard
	// only ever covered Apache.
	if ( ! file_exists( $dir . 'web.config' ) ) {
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$dir . 'web.config',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n"
		);
	}
}

/**
 * Are order files reachable straight from the browser?
 *
 * Writes a canary and asks the web server for it. This is the only way to know:
 * whether a deny rule is honoured depends on server software and configuration
 * that PHP cannot read. Cached, because it is an HTTP round trip and the answer
 * only changes when the server does.
 *
 * @since 1.7.0
 *
 * @param bool $force Re-probe instead of using the cached answer.
 * @return bool|null True if publicly readable, false if not, null if unknown.
 */
function wpss_order_files_are_public( bool $force = false ): ?bool {
	$cached = get_transient( 'wpss_order_files_public' );

	if ( ! $force && false !== $cached ) {
		return 'unknown' === $cached ? null : ( 'yes' === $cached );
	}

	$dir = wpss_get_order_files_dir();

	// Outside the document root there is no URL to probe.
	$uploads = wp_upload_dir();
	$base    = trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/';

	if ( trailingslashit( $dir ) !== trailingslashit( $base ) ) {
		set_transient( 'wpss_order_files_public', 'no', WEEK_IN_SECONDS );
		return false;
	}

	$name = 'canary-' . wp_generate_password( 12, false, false ) . '.txt';

	if ( false === file_put_contents( $dir . $name, 'canary' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		set_transient( 'wpss_order_files_public', 'unknown', HOUR_IN_SECONDS );
		return null;
	}

	$response = wp_remote_get(
		trailingslashit( $uploads['baseurl'] ) . 'wpss-order-files/' . $name,
		array(
			'timeout' => 5,

			/*
			 * sslverify left at its default (true). This is same-host with a
			 * URL we build and a body compared against a generated nonce, so
			 * disabling it bought nothing and it was the only such call in
			 * either plugin. A bad certificate now fails into the 'unknown'
			 * branch below, which is the correct answer to "can I reach my own
			 * uploads dir" when the transport is broken (Basecamp 10321653509).
			 */
		)
	);

	wp_delete_file( $dir . $name );

	if ( is_wp_error( $response ) ) {
		set_transient( 'wpss_order_files_public', 'unknown', HOUR_IN_SECONDS );
		return null;
	}

	$public = 200 === wp_remote_retrieve_response_code( $response )
		&& false !== strpos( (string) wp_remote_retrieve_body( $response ), 'canary' );

	set_transient( 'wpss_order_files_public', $public ? 'yes' : 'no', WEEK_IN_SECONDS );

	return $public;
}

/**
 * The file extensions an upload may use: the owner's Settings > Advanced list,
 * through the wpss_allowed_file_types filter.
 *
 * Read by wpss_check_upload() and by wpss_upload_accept(), so a file picker
 * offers exactly what the server will take. The pickers carried their own
 * hardcoded lists and offered .txt, .zip and .rar the server then refused
 * (Basecamp 10337171525).
 *
 * @since 1.8.0
 *
 * @param string              $context Upload context (see the filter).
 * @param array<string,mixed> $file    The $_FILES entry, when checking one.
 * @return string[] Lower-case extensions, no dots.
 */
function wpss_get_allowed_file_types( string $context = '', array $file = array() ): array {
	$allowed = array_map( 'trim', explode( ',', strtolower( (string) wpss_get_option( 'advanced', 'allowed_file_types' ) ) ) );

	/**
	 * Filter the file extensions any WPSS upload may use.
	 *
	 * The owner's Settings > Advanced list is the default. This filter is the
	 * seam for a site that needs a type the settings screen does not offer, or
	 * needs to narrow the list for one context - $context tells you which
	 * surface is asking.
	 *
	 * @since 1.7.1
	 *
	 * @param string[]            $allowed Lower-case extensions, no dots.
	 * @param array<string,mixed> $file    The $_FILES entry being checked.
	 * @param string              $context Upload context: requirements, delivery,
	 *                                     message, dispute, media, portfolio, ''.
	 */
	return (array) apply_filters( 'wpss_allowed_file_types', $allowed, $file, $context );
}

/**
 * The accept="" value for a file input in an upload context.
 *
 * @since 1.8.0
 *
 * @param string $context Upload context.
 * @return string e.g. ".jpg,.png,.pdf".
 */
function wpss_upload_accept( string $context ): string {
	return implode( ',', array_map( static fn( $ext ) => '.' . $ext, array_filter( wpss_get_allowed_file_types( $context ) ) ) );
}

/**
 * Validate an upload against the plugin's size and type settings.
 *
 * ONE reading of `wpss_max_file_size` and `wpss_allowed_file_types`. Message,
 * contact and dispute uploads each carried a private copy of these limits
 * (and dispute evidence had no size cap at all), so the owner's settings
 * governed some uploads and not others (Basecamp 10264291163).
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $file    One entry from $_FILES.
 * @param string              $context Which surface is uploading - passed to the
 *                                     filters so a site can vary the rule per
 *                                     context rather than globally.
 * @return WP_Error|null Error describing the refusal, or null when acceptable.
 */
function wpss_check_upload( array $file, string $context = '' ): ?WP_Error {
	$max_mb = (int) wpss_get_option( 'advanced', 'max_file_size' );

	/**
	 * Filter the maximum upload size, in megabytes.
	 *
	 * @since 1.7.1
	 *
	 * @param int                 $max_mb  Maximum size in MB.
	 * @param array<string,mixed> $file    The $_FILES entry being checked.
	 * @param string              $context Upload context.
	 */
	$max_mb = (int) apply_filters( 'wpss_max_upload_size_mb', $max_mb, $file, $context );

	if ( (int) ( $file['size'] ?? 0 ) > $max_mb * MB_IN_BYTES ) {
		return new WP_Error(
			'file_too_large',
			/* translators: %s: maximum file size */
			sprintf( __( 'File size exceeds the maximum of %s.', 'wp-sell-services' ), size_format( $max_mb * MB_IN_BYTES ) ),
			array( 'status' => 400 )
		);
	}

	// Real bytes, not the client-supplied extension or mime.
	$checked = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ) );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		return new WP_Error( 'invalid_type', __( 'File type could not be verified.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	$allowed = wpss_get_allowed_file_types( $context, $file );

	/*
	 * Public profile media is capped to what it is: images, plus video for a
	 * portfolio. The owner's list is written for PRIVATE order files (pdf, zip,
	 * psd...), and applying it here let any member host an archive publicly as
	 * an "avatar" (Basecamp 10336370704). Intersected, so an owner who narrows
	 * the list narrows these too, but nothing can widen them past the cap.
	 */
	$images        = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
	$public_limits = array(
		'avatar'    => $images,
		'profile'   => $images,
		'service'   => $images,
		'portfolio' => array_merge( $images, array( 'mp4', 'webm', 'mov' ) ),
	);

	if ( isset( $public_limits[ $context ] ) ) {
		$allowed = array_values( array_intersect( $allowed, $public_limits[ $context ] ) );
	}

	/*
	 * The per-flow filters from before uploads shared one check.
	 *
	 * Delivery and requirements each used to build their own list and pass it
	 * through its own filter. Folding both into this function removed those
	 * filters, so a site restricting what vendors may deliver, or widening what
	 * buyers may attach to requirements, silently lost that rule. They fire
	 * again here, with the same single argument they always took, after the
	 * general filter so either one can have the last word for its own flow.
	 */
	// Written out literally, not as a computed hook name: the docs gate and
	// any developer grepping for a hook both search for the literal string.
	if ( 'delivery' === $context ) {
		/**
		 * Filter the file extensions allowed for order deliveries.
		 *
		 * @since 1.0.0
		 * @since 1.7.1 Fired from wpss_check_upload(), after wpss_allowed_file_types. Same signature.
		 *
		 * @param string[] $allowed Lower-case extensions, no dots.
		 */
		$allowed = (array) apply_filters( 'wpss_delivery_allowed_file_types', $allowed );
	} elseif ( 'requirements' === $context ) {
		/**
		 * Filter the file extensions allowed for buyer requirement attachments.
		 *
		 * @since 1.0.0
		 * @since 1.7.1 Fired from wpss_check_upload(), after wpss_allowed_file_types. Same signature.
		 *
		 * @param string[] $allowed Lower-case extensions, no dots.
		 */
		$allowed = (array) apply_filters( 'wpss_requirements_allowed_file_types', $allowed );
	}

	if ( ! in_array( strtolower( (string) $checked['ext'] ), $allowed, true ) ) {
		return new WP_Error( 'invalid_type', __( 'File type not allowed.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	return null;
}

/**
 * A filename that is safe to show to the other party.
 *
 * The stored `name` on an attachment record is what the uploader's browser
 * sent, kept verbatim so the recipient sees the file they were given -
 * `sanitize_file_name()` is applied to the name on disk, not to this one. It is
 * therefore text written by the other side of a dispute or an order, and it is
 * rendered next to a download link, which makes it worth neutralising before
 * display:
 *
 *   - Bidi overrides (U+202A-U+202E, U+2066-U+2069 and friends) can reverse how
 *     the tail of a name reads, the long-standing trick for making an
 *     executable look like a PDF in a file listing.
 *   - Control characters and newlines can push the visible label away from the
 *     link it belongs to.
 *   - An unbounded name can run past the row and hide what follows it.
 *
 * Escaping is not enough on its own: esc_html() stops markup, not a character
 * whose whole purpose is to change the direction the rest of the string reads
 * in. Callers still escape - this only decides what the string says.
 *
 * @since 1.7.1
 *
 * @param string $name Stored attachment name.
 * @param int    $max  Longest label to show before eliding the middle.
 * @return string Display-safe name, never empty.
 */
function wpss_format_attachment_name( string $name, int $max = 80 ): string {
	// Bidi control and other invisible formatting characters.
	$name = (string) preg_replace( '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{061C}]/u', '', $name );

	// C0/C1 controls, including the newlines that would split the label.
	$name = (string) preg_replace( '/[\x00-\x1F\x7F-\x9F]/u', '', $name );

	$name = trim( (string) preg_replace( '/\s+/u', ' ', $name ) );

	if ( '' === $name ) {
		return __( 'Attachment', 'wp-sell-services' );
	}

	// Keep the head and the tail: the extension is the part a reader checks.
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $name ) > $max ) {
		$head = mb_substr( $name, 0, $max - 15 );
		$tail = mb_substr( $name, -10 );
		$name = $head . '...' . $tail;
	} elseif ( ! function_exists( 'mb_strlen' ) && strlen( $name ) > $max ) {
		$name = substr( $name, 0, $max - 15 ) . '...' . substr( $name, -10 );
	}

	/**
	 * Filter the display form of an attachment filename.
	 *
	 * @since 1.7.1
	 *
	 * @param string $name Display-safe name.
	 */
	return (string) apply_filters( 'wpss_attachment_display_name', $name );
}

/**
 * Neutralise a spreadsheet formula in one CSV cell.
 *
 * Excel, LibreOffice and Google Sheets execute a cell whose first character is
 * `=`, `+`, `-` or `@` (and treat a leading tab or CR as a continuation of the
 * same trick). Every export in this plugin writes values a vendor or buyer
 * typed - display names, service titles, bank names, PayPal addresses - so a
 * vendor could set their display name to `=cmd|'/c calc'!A1` and wait for an
 * admin to open the payout export (Basecamp 10321653478, finding 1).
 *
 * A leading apostrophe is the standard remedy: the spreadsheet stores the text
 * and shows it without the quote. Numeric strings are returned untouched, so a
 * negative amount stays a number the sheet can sum rather than becoming text.
 *
 * @since 1.7.2
 *
 * @param mixed $value Cell value.
 * @return string Safe cell value.
 */
function wpss_csv_cell( $value ): string {
	$value = (string) $value;

	if ( '' === $value || is_numeric( $value ) ) {
		return $value;
	}

	return false !== strpbrk( $value[0], "=+-@\t\r" ) ? "'" . $value : $value;
}

/**
 * Write a CSV row with every cell run through wpss_csv_cell() first.
 *
 * Exports live in five files across both plugins; escaping at each call site
 * would be five chances to forget, and the next export added would be a sixth.
 * Write rows through this and the escaping is not something anyone has to
 * remember.
 *
 * @since 1.7.2
 *
 * @param resource          $handle Open stream.
 * @param array<int, mixed> $row    Row values.
 * @return void
 */
function wpss_fputcsv( $handle, array $row ): void {
	fputcsv( $handle, array_map( 'wpss_csv_cell', $row ) );
}

/**
 * Let a member upload an image through the media modal.
 *
 * The dashboard (avatar, cover, portfolio) and the service wizard open the
 * WordPress media modal, which uploads to wp-admin/async-upload.php. Two things
 * stopped that (Basecamp 10340613689):
 *
 * - Buyers have no upload_files. The dashboard granted it only while its own
 *   page rendered, so the upload request itself was refused and no buyer could
 *   ever set a profile photo. It is now granted for that one request too, for
 *   images only - a member who does not already hold upload_files gets the
 *   image types and nothing else.
 * - WooCommerce sends anyone without edit_posts away from wp-admin to My
 *   Account, so on every WooCommerce site the upload got that page back instead
 *   of JSON ("An error occurred in the upload") - vendors included, so no
 *   vendor could add the main image a service needs to go live.
 *
 * Only async-upload.php; the rest of wp-admin stays closed. admin-ajax (the
 * modal's library) is already outside WooCommerce's check.
 *
 * @since 1.8.0
 *
 * @param array<string, bool> $allcaps All capabilities of the user.
 * @return array<string, bool>
 */
function wpss_grant_member_image_upload( array $allcaps ): array {
	if ( empty( $allcaps['upload_files'] ) && is_user_logged_in() ) {
		$allcaps['upload_files'] = true;
		add_filter( 'upload_mimes', 'wpss_image_mimes_only' );
	}

	return $allcaps;
}

/**
 * The image types from the site's allowed list, for members granted uploads.
 *
 * @since 1.8.0
 *
 * @param array<string, string> $mimes Allowed mime types.
 * @return array<string, string>
 */
function wpss_image_mimes_only( array $mimes ): array {
	return array_filter( $mimes, static fn( $type ) => str_starts_with( $type, 'image/' ) );
}

add_action(
	'init',
	static function () {
		global $pagenow;

		// The modal's Library tab asks admin-ajax for query-attachments, which
		// checks upload_files too, so a buyer could upload a photo but never
		// pick one they uploaded before. That listing is limited to their own
		// files by wpss_limit_media_library_to_own().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; core checks the request.
		$is_library = 'admin-ajax.php' === $pagenow && isset( $_REQUEST['action'] ) && 'query-attachments' === $_REQUEST['action'];

		if ( 'async-upload.php' !== $pagenow && ! $is_library ) {
			return;
		}

		add_filter( 'user_has_cap', 'wpss_grant_member_image_upload' );
		add_filter(
			'woocommerce_prevent_admin_access',
			static fn( $prevent ) => $prevent && ! current_user_can( 'upload_files' )
		);
	}
);

/**
 * The media library a member browses holds only their own uploads.
 *
 * The wizard and dashboard open the WordPress media modal, whose Library tab
 * listed every attachment on the site - other vendors' service images,
 * buyers' delivery files - to anyone who could upload (Basecamp 10340613689).
 * Someone who cannot edit other people's posts now sees their own files only;
 * editors and admins keep the whole library.
 *
 * @since 1.8.0
 *
 * @param array<string, mixed> $query Attachment query args.
 * @return array<string, mixed>
 */
function wpss_limit_media_library_to_own( array $query ): array {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$query['author'] = get_current_user_id();
	}

	return $query;
}

add_filter( 'ajax_query_attachments_args', 'wpss_limit_media_library_to_own' );

/**
 * Get max upload size in bytes.
 *
 * @return int
 */
function wpss_get_max_upload_size(): int {
	$upload_max = wp_max_upload_size();

	/**
	 * Filter the max upload size for requirements files.
	 *
	 * @param int $max_size Max size in bytes.
	 */
	return (int) apply_filters( 'wpss_max_upload_size', $upload_max );
}

/**
 * Validate + store conversation message file attachments.
 *
 * Single source of truth for message, contact and dispute-reply attachments,
 * used by REST and admin-ajax alike so the limits and the storage are the
 * same on every transport.
 *
 * With an order id the file goes through wpss_store_order_file() - outside
 * the web root, behind the order read gate, no URL stored - because a
 * message on an order carries the same briefs and proofs a requirement does
 * (Basecamp 10264291163). Without one (a pre-sale contact) there is no
 * order to gate on, so it stays a media-library attachment marked private.
 *
 * @since 1.2.0
 * @since 1.7.1 Order-scoped files use the private order store; limits come from settings.
 *
 * @param array<string, mixed> $files    A single $_FILES['attachments'] entry (PHP's grouped
 *                                       multi-file shape: name[], type[], tmp_name[], etc.).
 * @param int                  $order_id Order the conversation belongs to, 0 for none.
 * @param string               $kind     message|contact|dispute|receipt - grouping only.
 * @return array{attachments: array<int, array<string,mixed>>, skipped: array<int,string>}
 */
function wpss_handle_message_attachments( array $files, int $order_id = 0, string $kind = 'message' ): array {
	$attachments = array();
	$skipped     = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return array(
			'attachments' => $attachments,
			'skipped'     => $skipped,
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$file_count = count( $files['name'] );
	for ( $i = 0; $i < $file_count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$file_name = sanitize_file_name( $file['name'] );
		$refused   = wpss_check_upload( $file );

		if ( $refused ) {
			$skipped[] = $file_name . ': ' . $refused->get_error_message();
			continue;
		}

		if ( $order_id > 0 ) {
			$record = wpss_store_order_file( $file, $order_id, $kind );

			if ( $record ) {
				$attachments[] = $record;
			} else {
				$skipped[] = $file_name . ': ' . __( 'upload failed', 'wp-sell-services' );
			}
			continue;
		}

		// ponytail: pre-sale contact files stay in the media library; the URL is
		// unlisted, not gated. Move them behind a conversation read gate if
		// pre-sale inquiries start carrying sensitive documents.
		$_FILES['upload_file'] = $file;

		// Unlisted means unguessable, or it means nothing - see
		// wpss_obfuscate_public_upload_name().
		add_filter( 'wp_handle_upload_prefilter', 'wpss_obfuscate_public_upload_name' );
		$attachment_id = media_handle_upload( 'upload_file', 0, array( 'post_status' => 'private' ) );
		remove_filter( 'wp_handle_upload_prefilter', 'wpss_obfuscate_public_upload_name' );

		if ( ! is_wp_error( $attachment_id ) ) {
			$attachments[] = array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'name' => $files['name'][ $i ],
				'type' => (string) get_post_mime_type( $attachment_id ), // Server-verified MIME, not client-provided.
			);
		} else {
			$skipped[] = $file_name . ': ' . $attachment_id->get_error_message();
		}
	}

	return array(
		'attachments' => $attachments,
		'skipped'     => $skipped,
	);
}

/**
 * Normalize PHP's grouped $_FILES entry into a list of per-file specs.
 *
 * Turns the `name[]/type[]/tmp_name[]/error[]/size[]` shape PHP produces for a
 * multi-file field into a flat array of single-file specs (name/type/tmp_name/
 * error/size), skipping empty slots and sanitizing the client-supplied name +
 * mime. tmp_name/error/size are not user-controlled. Shared by the REST
 * deliverables endpoint and the legacy admin-ajax delivery handler so both
 * feed DeliveryService::submit() the same shape.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single grouped $_FILES['field'] entry.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function wpss_normalize_uploaded_files( array $files ): array {
	$out = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $out;
	}

	$count = count( $files['name'] );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}
		$out[] = array(
			'name'     => sanitize_file_name( $files['name'][ $i ] ),
			'type'     => sanitize_mime_type( $files['type'][ $i ] ),
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => (int) $files['error'][ $i ],
			'size'     => (int) $files['size'][ $i ],
		);
	}

	return $out;
}

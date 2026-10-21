<?php
/**
 * Time helpers: store UTC, show in one timezone.
 *
 * Every time the plugin stores is UTC. Showing it goes through wpss_date_label(),
 * which uses wpss_display_timezone(), so an admin table, a member page, an email
 * and an edit field all print the same moment the same way. wp_date(),
 * date_i18n(), mysql2date() and get_date_from_gmt() each pick their own timezone;
 * bin/check-time-formatter.php fails on a new call outside this file.
 *
 * Standard: docs/standards/time.md (Wbcom Time Standard, reference Learnomy 2.1.2).
 * Pro and add-ons call these helpers and never add their own.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * The timezone a time is shown in: the site's, unless a site filters it.
 *
 * @since 1.9.0
 *
 * @param int $user_id The person the time is shown to (the recipient for an email); 0 means the current user.
 * @return \DateTimeZone
 */
function wpss_display_timezone( int $user_id = 0 ): \DateTimeZone {
	/**
	 * The timezone times are shown in. Return a member's own timezone to show each
	 * member their local time; every screen, email and edit field follows.
	 *
	 * @since 1.9.0
	 *
	 * @param \DateTimeZone $timezone The site's timezone.
	 * @param int           $user_id  Who the time is shown to.
	 */
	$timezone = apply_filters( 'wpss_display_timezone', wp_timezone(), $user_id > 0 ? $user_id : get_current_user_id() );

	return $timezone instanceof \DateTimeZone ? $timezone : wp_timezone();
}

/**
 * A vendor's own time zone, when they chose one on their profile.
 *
 * Hooked on wpss_display_timezone so every screen, email and edit field shows the vendor their
 * local time without a second formatter. An empty or unknown value keeps the site's zone.
 *
 * @since 1.9.1
 *
 * @param \DateTimeZone $timezone The zone so far (the site's).
 * @param int           $user_id  Who the time is shown to.
 * @return \DateTimeZone
 */
function wpss_vendor_display_timezone( $timezone, $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return $timezone;
	}

	$name = wp_cache_get( $user_id, 'wpss_vendor_timezone' );
	if ( false === $name ) {
		$profile = ( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->get_by_user( $user_id );
		$name    = (string) ( $profile->timezone ?? '' );
		wp_cache_set( $user_id, $name, 'wpss_vendor_timezone' );
	}

	return in_array( $name, timezone_identifiers_list(), true ) ? new \DateTimeZone( $name ) : $timezone;
}
add_filter( 'wpss_display_timezone', 'wpss_vendor_display_timezone', 10, 2 );
add_action( 'wpss_vendor_profile_updated', static fn( $user_id ) => wp_cache_delete( (int) $user_id, 'wpss_vendor_timezone' ) );

/**
 * A stored UTC time shown to a person, or a fallback when there is no time.
 *
 * THE formatter for every time the plugin shows. A NULL, an empty string, the
 * zero date and an unparseable value all give the fallback.
 *
 * @since 1.9.0
 *
 * @param string|int|null $utc      A stored UTC datetime (a naive string is read as UTC), or a Unix timestamp.
 * @param string          $fallback Shown when there is no usable time.
 * @param string|null     $format   PHP date format; the site's date format by default.
 * @param int             $user_id  Who it is shown to (see wpss_display_timezone()).
 * @return string
 */
function wpss_date_label( $utc, string $fallback = '—', ?string $format = null, int $user_id = 0 ): string {
	$timestamp = wpss_utc_timestamp( $utc );

	if ( null === $timestamp ) {
		return $fallback;
	}

	return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, wpss_display_timezone( $user_id ) );
}

/**
 * A stored UTC time as a Unix timestamp; null for NULL, empty, the zero date and anything unparseable.
 *
 * A naive datetime string is read as UTC. Used by wpss_date_label() and its siblings so
 * every one of them agrees on what "no usable time" means.
 *
 * @since 1.9.0
 *
 * @param string|int|null $utc A stored UTC datetime, or a Unix timestamp.
 * @return int|null
 */
function wpss_utc_timestamp( $utc ): ?int {
	if ( is_int( $utc ) ) {
		return $utc > 0 ? $utc : null;
	}

	$utc = null === $utc ? '' : trim( (string) $utc );
	if ( '' === $utc || 0 === strncmp( $utc, '0000-00-00', 10 ) ) {
		return null;
	}

	$timestamp = strtotime( $utc . ( preg_match( '/(Z|[+-]\d{2}:?\d{2}|UTC|GMT)$/i', $utc ) ? '' : ' UTC' ) );

	return false === $timestamp || $timestamp <= 0 ? null : $timestamp;
}

/**
 * A stored UTC time shown as date and time, in the site's date and time formats.
 *
 * @since 1.9.0
 *
 * @param string|int|null $utc      A stored UTC datetime or Unix timestamp.
 * @param string          $fallback Shown when there is no usable time.
 * @param int             $user_id  Who it is shown to (see wpss_display_timezone()).
 * @return string
 */
function wpss_datetime_label( $utc, string $fallback = '—', int $user_id = 0 ): string {
	return wpss_date_label( $utc, $fallback, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $user_id );
}

/**
 * The display timezone as the buyer and seller read it: "UTC", "UTC+5:30", "UTC-4".
 *
 * The offset at that moment, so a time in daylight saving shows the offset in force then.
 *
 * @since 1.9.0
 *
 * @param string|int|null $utc     The moment the zone applies to; now by default.
 * @param int             $user_id Who it is shown to.
 * @return string
 */
function wpss_timezone_label( $utc = null, int $user_id = 0 ): string {
	$timestamp = wpss_utc_timestamp( $utc ) ?? time();
	$offset    = wpss_display_timezone( $user_id )->getOffset( new \DateTimeImmutable( '@' . $timestamp ) );

	if ( 0 === $offset ) {
		return 'UTC';
	}

	$abs     = abs( $offset );
	$minutes = intdiv( $abs % 3600, 60 );

	return sprintf( 'UTC%s%d%s', $offset < 0 ? '-' : '+', intdiv( $abs, 3600 ), $minutes ? ':' . sprintf( '%02d', $minutes ) : '' );
}

/**
 * A deadline: date, time and the zone it is in ("Oct 21, 3:00 PM (UTC+5:30)").
 *
 * Time standard decision 15: a deadline is the one time two people must agree on, so it
 * always names its zone. Everyday timestamps stay plain (wpss_datetime_label()).
 *
 * @since 1.9.0
 *
 * @param string|int|null $utc      A stored UTC datetime or Unix timestamp.
 * @param string          $fallback Shown when there is no usable time.
 * @param int             $user_id  Who it is shown to.
 * @return string
 */
function wpss_deadline_label( $utc, string $fallback = '—', int $user_id = 0 ): string {
	if ( null === wpss_utc_timestamp( $utc ) ) {
		return $fallback;
	}

	return sprintf( '%s (%s)', wpss_datetime_label( $utc, $fallback, $user_id ), wpss_timezone_label( $utc, $user_id ) );
}

/**
 * A calendar date with no time of day (a vacation return date, a birthday), printed as stored.
 *
 * A date such as 2026-10-21 is not an instant, so it must not move with the display
 * timezone; wpss_date_label() would show the 20th west of UTC.
 *
 * @since 1.9.0
 *
 * @param string|null $ymd      `Y-m-d` (a time part is ignored).
 * @param string      $fallback Shown when there is no usable date.
 * @param string|null $format   PHP date format; the site's date format by default.
 * @return string
 */
function wpss_calendar_date_label( ?string $ymd, string $fallback = '—', ?string $format = null ): string {
	$timestamp = null === $ymd || '' === trim( $ymd ) || 0 === strncmp( trim( $ymd ), '0000-00-00', 10 ) ? false : strtotime( substr( trim( $ymd ), 0, 10 ) . ' 00:00:00 UTC' );

	if ( false === $timestamp ) {
		return $fallback;
	}

	return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, new \DateTimeZone( 'UTC' ) );
}

/**
 * Convert what an editor typed (in the display timezone) to a UTC storage string.
 *
 * The exact inverse of wpss_datetime_local_input(). Keep the pair together: if
 * one stops agreeing with the other, a saved time walks by the UTC offset on
 * every round trip.
 *
 * @since 1.9.0
 *
 * @param string|null $local `Y-m-d\TH:i` (or any parseable datetime) in the display timezone.
 * @return string|null UTC `Y-m-d H:i:s`; null when blank or unparseable, so a bad value clears the field instead of storing a wrong instant.
 */
function wpss_datetime_input_to_utc( ?string $local ): ?string {
	if ( null === $local || '' === trim( $local ) ) {
		return null;
	}

	$dt = date_create( $local, wpss_display_timezone() );
	if ( false === $dt ) {
		return null;
	}

	return $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
}

/**
 * Format a stored UTC time for a datetime-local field, in the display timezone.
 *
 * Take substr( …, 0, 10 ) for a date input.
 *
 * @since 1.9.0
 *
 * @param string|null $utc Stored UTC datetime, or null/empty for an unset field.
 * @return string `Y-m-d\TH:i`, or '' when there is nothing to show.
 */
function wpss_datetime_local_input( ?string $utc ): string {
	if ( empty( $utc ) ) {
		return '';
	}

	try {
		return ( new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) ) )->setTimezone( wpss_display_timezone() )->format( 'Y-m-d\TH:i' );
	} catch ( \Exception $e ) {
		return '';
	}
}

/**
 * SQL for a stored UTC column moved into the display timezone, to group a report
 * by day, week or month: wrap it in DATE() or DATE_FORMAT(). A numeric offset
 * needs no MySQL timezone tables.
 *
 * Shortcut: it uses today's offset for the whole range, so near a daylight-saving
 * change, rows within an hour of midnight can land in the next or previous day.
 * Group in PHP with wpss_date_label() when a report needs exact days.
 *
 * @since 1.9.0
 *
 * @param string $column Column or SQL expression (trusted, never user input).
 * @return string
 */
function wpss_display_time_sql( string $column ): string {
	$offset = wpss_display_timezone()->getOffset( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
	$abs    = abs( $offset );

	return sprintf( "CONVERT_TZ( %s, '+00:00', '%s%02d:%02d' )", $column, $offset < 0 ? '-' : '+', intdiv( $abs, 3600 ), intdiv( $abs % 3600, 60 ) );
}

/**
 * SQL for the display timezone's calendar day of a UTC datetime column.
 *
 * Grouping a report by DATE( column ) buckets by the UTC day, so an order placed
 * at 00:30 on a +05:30 site lands on the previous day.
 *
 * @since 1.8.0
 *
 * @param string $column Column or SQL expression (trusted, never user input).
 * @return string SQL expression yielding a DATE.
 */
function wpss_site_day_sql( string $column ): string {
	return 'DATE( ' . wpss_display_time_sql( $column ) . ' )';
}

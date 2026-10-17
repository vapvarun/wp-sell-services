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
	if ( is_int( $utc ) ) {
		$timestamp = $utc;
	} else {
		$utc = null === $utc ? '' : trim( (string) $utc );
		if ( '' === $utc || 0 === strncmp( $utc, '0000-00-00', 10 ) ) {
			return $fallback;
		}
		$timestamp = strtotime( $utc . ( preg_match( '/(Z|[+-]\d{2}:?\d{2}|UTC|GMT)$/i', $utc ) ? '' : ' UTC' ) );
	}

	if ( false === $timestamp || $timestamp <= 0 ) {
		return $fallback;
	}

	return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, wpss_display_timezone( $user_id ) );
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
 * shortcut: uses today's offset for the whole range, so near a daylight-saving
 * change rows within an hour of midnight can land in the next or previous day;
 * group in PHP with wpss_date_label() when a report needs exact days.
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

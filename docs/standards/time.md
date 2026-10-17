# Time standard

WP Sell Services follows the Wbcom Time Standard (reference: Learnomy 2.1.2). One rule: **store UTC, show one way.**

## Rules

1. Every stored time is UTC. Write it with `gmdate()` or `current_time( 'mysql', true )`, never the site clock.
2. Compare in UTC. Build the bound in PHP; do not rely on the database session zone.
3. Show a time with `wpss_date_label( $utc, $fallback, $format, $user_id )`. Admin tables, member pages, emails, PDFs and exports all use it, so the same moment reads the same everywhere. A deadline is shown with `wpss_deadline_label()`, which appends the zone as `(UTC+5:30)` so a buyer in another country is not misled; everyday times use `wpss_datetime_label()` and stay plain. A calendar date with no time of day (a vacation return date) uses `wpss_calendar_date_label()` so it never moves with the zone. Typed ranges and inputs go through `wpss_datetime_input_to_utc()`, never `get_gmt_from_date()`. Every `wpss/` REST response is normalised to ISO 8601 by one `rest_post_dispatch` filter (`API::normalise_response_dates()`); a controller never formats its own dates.
4. Edit fields round-trip through `wpss_datetime_input_to_utc()` and `wpss_datetime_local_input()`. They are an exact pair; change both or neither.
5. Report days use `wpss_display_time_sql( $column )` or `wpss_site_day_sql( $column )`, never `DATE( column )` on a UTC value.
6. REST dates use `wpss_rest_date()` (ISO 8601 with offset). One normaliser covers every route.
7. JavaScript never uses the browser zone. PHP passes `timeZone`; the script formats with it.
8. Emails show the time for the recipient: pass their user id to `wpss_date_label()`.
9. A one-time data conversion uses `wp_timezone()` (the zone the old values were typed in), not the display filter.

## Display timezone

`wpss_display_timezone( $user_id )` returns the site timezone. A site can show each member their own time:

```php
add_filter( 'wpss_display_timezone', function ( $tz, $user_id ) {
	$zone = get_user_meta( $user_id, 'my_timezone', true );
	return $zone ? new DateTimeZone( $zone ) : $tz;
}, 10, 2 );
```

## Gate

`php bin/check-time-formatter.php` fails when a file gains a raw `wp_date()`, `date_i18n()`, `mysql2date()` or `get_date_from_gmt()`. Existing calls are counted in `bin/time-formatter-baseline.json`; the number only goes down (`--update` after converting). A call that is correct on purpose (a `post_date`, which is site-local) carries a `time-ok:` comment.

`wp eval-file tests/test-time-helpers.php` runs the helpers on Asia/Kolkata and America/New_York.

## QA

Every time-bearing journey is walked on a non-UTC site (Asia/Kolkata and America/New_York), with an order near local midnight: deadline on the order, email, REST payload and report day must agree.

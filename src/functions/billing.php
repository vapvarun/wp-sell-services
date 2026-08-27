<?php
/**
 * Billing: field definitions, countries and the stored billing address.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The billing address fields, in display order.
 *
 * THE canonical field list — the profile form, the checkout prefill, the order
 * snapshot, the admin screen and any invoice all read it, so a field is added
 * in exactly one place.
 *
 * Keys are WooCommerce's, deliberately. On a site running WooCommerce the
 * buyer's address is ALREADY stored under these keys, so we prefill from it and
 * they never type it twice; on a standalone site we own the same keys and a
 * later Woo install inherits them. One address per user, whichever plugin
 * captured it.
 *
 * `billing_gst` is the exception — it has no Woo-core equivalent, so it is
 * ours. It is the general tax-registration field (GSTIN in India, VAT ID in the
 * EU), not one key per jurisdiction.
 *
 * @since 1.2.3
 *
 * @return array<string, array{label:string, required:bool, type:string, autocomplete:string}>
 */
function wpss_get_billing_fields(): array {
	$fields = wpss_get_all_billing_field_definitions();

	// Drop fields the owner has switched off.
	//
	// Twelve billing fields, most of them required, is a physical-goods
	// checkout. A marketplace selling logo design has no use for street
	// address, apartment, city, state or postcode, and every one of them is a
	// reason to abandon (Basecamp #10159633185).
	//
	// Applied BEFORE the filter below so code can still override the owner's
	// choice - a tax plugin that genuinely needs the address can put it back.
	$fields = wpss_filter_enabled_billing_fields( $fields );

	/**
	 * Filter the billing address fields.
	 *
	 * @since 1.2.3
	 *
	 * @param array $fields Field definitions keyed by meta key.
	 */
	return apply_filters( 'wpss_billing_fields', $fields );
}

/**
 * Every billing field this plugin knows about, before any owner setting.
 *
 * The settings screen needs the unfiltered list so a field that has been
 * switched off can still be shown - and switched back on.
 *
 * @since 1.6.0
 *
 * @return array<string, array{label:string, required:bool, type:string, autocomplete:string}>
 */
function wpss_get_all_billing_field_definitions(): array {
	$fields = array(
		'billing_first_name' => array(
			'label'        => __( 'First name', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'given-name',
		),
		'billing_last_name'  => array(
			'label'        => __( 'Last name', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'family-name',
		),
		'billing_company'    => array(
			'label'        => __( 'Company', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'organization',
		),
		'billing_gst'        => array(
			// GSTIN / VAT / tax registration number. A B2B buyer needs this on
			// the invoice to claim input credit, so an invoice without it is
			// unusable to them.
			'label'        => __( 'GST / VAT number', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'off',
		),
		'billing_address_1'  => array(
			'label'        => __( 'Street address', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'address-line1',
		),
		'billing_address_2'  => array(
			'label'        => __( 'Apartment, suite, etc.', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'address-line2',
		),
		'billing_city'       => array(
			'label'        => __( 'Town / City', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'address-level2',
		),
		'billing_state'      => array(
			'label'        => __( 'State / County', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'text',
			'autocomplete' => 'address-level1',
		),
		'billing_postcode'   => array(
			'label'        => __( 'Postcode / ZIP', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'text',
			'autocomplete' => 'postal-code',
		),
		'billing_country'    => array(
			'label'        => __( 'Country', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'country',
			'autocomplete' => 'country',
		),
		'billing_email'      => array(
			'label'        => __( 'Email', 'wp-sell-services' ),
			'required'     => true,
			'type'         => 'email',
			'autocomplete' => 'email',
		),
		'billing_phone'      => array(
			'label'        => __( 'Phone', 'wp-sell-services' ),
			'required'     => false,
			'type'         => 'tel',
			'autocomplete' => 'tel',
		),
	);

	return $fields;
}

/**
 * Billing fields that cannot be switched off.
 *
 * An order has to be attributable to a person who can be contacted about it.
 * Everything else is a preference; these three are the record.
 *
 * @since 1.6.0
 *
 * @return array<int, string> Field keys.
 */
function wpss_get_required_billing_fields(): array {
	/**
	 * Filters the billing fields that cannot be disabled.
	 *
	 * @since 1.6.0
	 *
	 * @param array<int, string> $keys Field keys.
	 */
	return (array) apply_filters(
		'wpss_locked_billing_fields',
		array( 'billing_first_name', 'billing_last_name', 'billing_email' )
	);
}

/**
 * Remove billing fields the site owner has disabled.
 *
 * DEFAULT IS EVERY FIELD ON. A site upgrading into this release keeps
 * collecting exactly what it collected yesterday - silently dropping address
 * fields from a live checkout would leave invoices and tax records incomplete
 * with nothing to say why. Owners opt into the shorter form.
 *
 * @since 1.6.0
 *
 * @param array<string, array<string, mixed>> $fields Full field definitions.
 * @return array<string, array<string, mixed>> Enabled field definitions.
 */
function wpss_filter_enabled_billing_fields( array $fields ): array {
	$settings = get_option( 'wpss_billing_field_settings', array() );

	if ( ! is_array( $settings ) || ! isset( $settings['enabled'] ) || ! is_array( $settings['enabled'] ) ) {
		return $fields;
	}

	$enabled = array_map( 'strval', $settings['enabled'] );
	$locked  = wpss_get_required_billing_fields();

	foreach ( array_keys( $fields ) as $key ) {
		if ( in_array( $key, $locked, true ) ) {
			continue;
		}

		if ( ! in_array( (string) $key, $enabled, true ) ) {
			unset( $fields[ $key ] );
		}
	}

	return $fields;
}

/**
 * The shorter field set that suits a marketplace selling digital work.
 *
 * Offered as a one-click preset rather than imposed as a default, so the choice
 * stays the owner's and is visible in the settings after they make it.
 *
 * @since 1.6.0
 *
 * @return array<int, string> Field keys.
 */
function wpss_get_digital_billing_field_preset(): array {
	return array(
		'billing_first_name',
		'billing_last_name',
		'billing_email',
		'billing_phone',
		'billing_company',
		'billing_country',
	);
}

/**
 * Country list for the billing country selector.
 *
 * ISO-3166 alpha-2 => display name. Defers to WooCommerce's list when Woo is
 * active so the two never disagree on a country name or code; otherwise falls
 * back to WordPress's own translated list, and finally to a minimal set.
 *
 * @since 1.2.3
 *
 * @return array<string, string>
 */
function wpss_get_countries(): array {
	static $countries = null;

	if ( null !== $countries ) {
		return $countries;
	}

	// Woo is a rail we already integrate with; reuse its list when present.
	if ( function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
		$countries = WC()->countries->get_countries();
	}

	if ( empty( $countries ) ) {
		// Complete ISO 3166-1 alpha-2 list. A partial list silently blocks
		// checkout for every country left out, so this is the whole set.
		$countries = array(
			'AF' => __( 'Afghanistan', 'wp-sell-services' ),
			'AX' => __( 'Åland Islands', 'wp-sell-services' ),
			'AL' => __( 'Albania', 'wp-sell-services' ),
			'DZ' => __( 'Algeria', 'wp-sell-services' ),
			'AS' => __( 'American Samoa', 'wp-sell-services' ),
			'AD' => __( 'Andorra', 'wp-sell-services' ),
			'AO' => __( 'Angola', 'wp-sell-services' ),
			'AI' => __( 'Anguilla', 'wp-sell-services' ),
			'AQ' => __( 'Antarctica', 'wp-sell-services' ),
			'AG' => __( 'Antigua and Barbuda', 'wp-sell-services' ),
			'AR' => __( 'Argentina', 'wp-sell-services' ),
			'AM' => __( 'Armenia', 'wp-sell-services' ),
			'AW' => __( 'Aruba', 'wp-sell-services' ),
			'AU' => __( 'Australia', 'wp-sell-services' ),
			'AT' => __( 'Austria', 'wp-sell-services' ),
			'AZ' => __( 'Azerbaijan', 'wp-sell-services' ),
			'BS' => __( 'Bahamas', 'wp-sell-services' ),
			'BH' => __( 'Bahrain', 'wp-sell-services' ),
			'BD' => __( 'Bangladesh', 'wp-sell-services' ),
			'BB' => __( 'Barbados', 'wp-sell-services' ),
			'BY' => __( 'Belarus', 'wp-sell-services' ),
			'BE' => __( 'Belgium', 'wp-sell-services' ),
			'BZ' => __( 'Belize', 'wp-sell-services' ),
			'BJ' => __( 'Benin', 'wp-sell-services' ),
			'BM' => __( 'Bermuda', 'wp-sell-services' ),
			'BT' => __( 'Bhutan', 'wp-sell-services' ),
			'BO' => __( 'Bolivia', 'wp-sell-services' ),
			'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'wp-sell-services' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wp-sell-services' ),
			'BW' => __( 'Botswana', 'wp-sell-services' ),
			'BV' => __( 'Bouvet Island', 'wp-sell-services' ),
			'BR' => __( 'Brazil', 'wp-sell-services' ),
			'IO' => __( 'British Indian Ocean Territory', 'wp-sell-services' ),
			'BN' => __( 'Brunei', 'wp-sell-services' ),
			'BG' => __( 'Bulgaria', 'wp-sell-services' ),
			'BF' => __( 'Burkina Faso', 'wp-sell-services' ),
			'BI' => __( 'Burundi', 'wp-sell-services' ),
			'CV' => __( 'Cabo Verde', 'wp-sell-services' ),
			'KH' => __( 'Cambodia', 'wp-sell-services' ),
			'CM' => __( 'Cameroon', 'wp-sell-services' ),
			'CA' => __( 'Canada', 'wp-sell-services' ),
			'KY' => __( 'Cayman Islands', 'wp-sell-services' ),
			'CF' => __( 'Central African Republic', 'wp-sell-services' ),
			'TD' => __( 'Chad', 'wp-sell-services' ),
			'CL' => __( 'Chile', 'wp-sell-services' ),
			'CN' => __( 'China', 'wp-sell-services' ),
			'CX' => __( 'Christmas Island', 'wp-sell-services' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'wp-sell-services' ),
			'CO' => __( 'Colombia', 'wp-sell-services' ),
			'KM' => __( 'Comoros', 'wp-sell-services' ),
			'CG' => __( 'Congo', 'wp-sell-services' ),
			'CD' => __( 'Congo (DRC)', 'wp-sell-services' ),
			'CK' => __( 'Cook Islands', 'wp-sell-services' ),
			'CR' => __( 'Costa Rica', 'wp-sell-services' ),
			'CI' => __( "Côte d'Ivoire", 'wp-sell-services' ),
			'HR' => __( 'Croatia', 'wp-sell-services' ),
			'CU' => __( 'Cuba', 'wp-sell-services' ),
			'CW' => __( 'Curaçao', 'wp-sell-services' ),
			'CY' => __( 'Cyprus', 'wp-sell-services' ),
			'CZ' => __( 'Czechia', 'wp-sell-services' ),
			'DK' => __( 'Denmark', 'wp-sell-services' ),
			'DJ' => __( 'Djibouti', 'wp-sell-services' ),
			'DM' => __( 'Dominica', 'wp-sell-services' ),
			'DO' => __( 'Dominican Republic', 'wp-sell-services' ),
			'EC' => __( 'Ecuador', 'wp-sell-services' ),
			'EG' => __( 'Egypt', 'wp-sell-services' ),
			'SV' => __( 'El Salvador', 'wp-sell-services' ),
			'GQ' => __( 'Equatorial Guinea', 'wp-sell-services' ),
			'ER' => __( 'Eritrea', 'wp-sell-services' ),
			'EE' => __( 'Estonia', 'wp-sell-services' ),
			'SZ' => __( 'Eswatini', 'wp-sell-services' ),
			'ET' => __( 'Ethiopia', 'wp-sell-services' ),
			'FK' => __( 'Falkland Islands', 'wp-sell-services' ),
			'FO' => __( 'Faroe Islands', 'wp-sell-services' ),
			'FJ' => __( 'Fiji', 'wp-sell-services' ),
			'FI' => __( 'Finland', 'wp-sell-services' ),
			'FR' => __( 'France', 'wp-sell-services' ),
			'GF' => __( 'French Guiana', 'wp-sell-services' ),
			'PF' => __( 'French Polynesia', 'wp-sell-services' ),
			'TF' => __( 'French Southern Territories', 'wp-sell-services' ),
			'GA' => __( 'Gabon', 'wp-sell-services' ),
			'GM' => __( 'Gambia', 'wp-sell-services' ),
			'GE' => __( 'Georgia', 'wp-sell-services' ),
			'DE' => __( 'Germany', 'wp-sell-services' ),
			'GH' => __( 'Ghana', 'wp-sell-services' ),
			'GI' => __( 'Gibraltar', 'wp-sell-services' ),
			'GR' => __( 'Greece', 'wp-sell-services' ),
			'GL' => __( 'Greenland', 'wp-sell-services' ),
			'GD' => __( 'Grenada', 'wp-sell-services' ),
			'GP' => __( 'Guadeloupe', 'wp-sell-services' ),
			'GU' => __( 'Guam', 'wp-sell-services' ),
			'GT' => __( 'Guatemala', 'wp-sell-services' ),
			'GG' => __( 'Guernsey', 'wp-sell-services' ),
			'GN' => __( 'Guinea', 'wp-sell-services' ),
			'GW' => __( 'Guinea-Bissau', 'wp-sell-services' ),
			'GY' => __( 'Guyana', 'wp-sell-services' ),
			'HT' => __( 'Haiti', 'wp-sell-services' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'wp-sell-services' ),
			'HN' => __( 'Honduras', 'wp-sell-services' ),
			'HK' => __( 'Hong Kong', 'wp-sell-services' ),
			'HU' => __( 'Hungary', 'wp-sell-services' ),
			'IS' => __( 'Iceland', 'wp-sell-services' ),
			'IN' => __( 'India', 'wp-sell-services' ),
			'ID' => __( 'Indonesia', 'wp-sell-services' ),
			'IR' => __( 'Iran', 'wp-sell-services' ),
			'IQ' => __( 'Iraq', 'wp-sell-services' ),
			'IE' => __( 'Ireland', 'wp-sell-services' ),
			'IM' => __( 'Isle of Man', 'wp-sell-services' ),
			'IL' => __( 'Israel', 'wp-sell-services' ),
			'IT' => __( 'Italy', 'wp-sell-services' ),
			'JM' => __( 'Jamaica', 'wp-sell-services' ),
			'JP' => __( 'Japan', 'wp-sell-services' ),
			'JE' => __( 'Jersey', 'wp-sell-services' ),
			'JO' => __( 'Jordan', 'wp-sell-services' ),
			'KZ' => __( 'Kazakhstan', 'wp-sell-services' ),
			'KE' => __( 'Kenya', 'wp-sell-services' ),
			'KI' => __( 'Kiribati', 'wp-sell-services' ),
			'KW' => __( 'Kuwait', 'wp-sell-services' ),
			'KG' => __( 'Kyrgyzstan', 'wp-sell-services' ),
			'LA' => __( 'Laos', 'wp-sell-services' ),
			'LV' => __( 'Latvia', 'wp-sell-services' ),
			'LB' => __( 'Lebanon', 'wp-sell-services' ),
			'LS' => __( 'Lesotho', 'wp-sell-services' ),
			'LR' => __( 'Liberia', 'wp-sell-services' ),
			'LY' => __( 'Libya', 'wp-sell-services' ),
			'LI' => __( 'Liechtenstein', 'wp-sell-services' ),
			'LT' => __( 'Lithuania', 'wp-sell-services' ),
			'LU' => __( 'Luxembourg', 'wp-sell-services' ),
			'MO' => __( 'Macao', 'wp-sell-services' ),
			'MG' => __( 'Madagascar', 'wp-sell-services' ),
			'MW' => __( 'Malawi', 'wp-sell-services' ),
			'MY' => __( 'Malaysia', 'wp-sell-services' ),
			'MV' => __( 'Maldives', 'wp-sell-services' ),
			'ML' => __( 'Mali', 'wp-sell-services' ),
			'MT' => __( 'Malta', 'wp-sell-services' ),
			'MH' => __( 'Marshall Islands', 'wp-sell-services' ),
			'MQ' => __( 'Martinique', 'wp-sell-services' ),
			'MR' => __( 'Mauritania', 'wp-sell-services' ),
			'MU' => __( 'Mauritius', 'wp-sell-services' ),
			'YT' => __( 'Mayotte', 'wp-sell-services' ),
			'MX' => __( 'Mexico', 'wp-sell-services' ),
			'FM' => __( 'Micronesia', 'wp-sell-services' ),
			'MD' => __( 'Moldova', 'wp-sell-services' ),
			'MC' => __( 'Monaco', 'wp-sell-services' ),
			'MN' => __( 'Mongolia', 'wp-sell-services' ),
			'ME' => __( 'Montenegro', 'wp-sell-services' ),
			'MS' => __( 'Montserrat', 'wp-sell-services' ),
			'MA' => __( 'Morocco', 'wp-sell-services' ),
			'MZ' => __( 'Mozambique', 'wp-sell-services' ),
			'MM' => __( 'Myanmar', 'wp-sell-services' ),
			'NA' => __( 'Namibia', 'wp-sell-services' ),
			'NR' => __( 'Nauru', 'wp-sell-services' ),
			'NP' => __( 'Nepal', 'wp-sell-services' ),
			'NL' => __( 'Netherlands', 'wp-sell-services' ),
			'NC' => __( 'New Caledonia', 'wp-sell-services' ),
			'NZ' => __( 'New Zealand', 'wp-sell-services' ),
			'NI' => __( 'Nicaragua', 'wp-sell-services' ),
			'NE' => __( 'Niger', 'wp-sell-services' ),
			'NG' => __( 'Nigeria', 'wp-sell-services' ),
			'NU' => __( 'Niue', 'wp-sell-services' ),
			'NF' => __( 'Norfolk Island', 'wp-sell-services' ),
			'KP' => __( 'North Korea', 'wp-sell-services' ),
			'MK' => __( 'North Macedonia', 'wp-sell-services' ),
			'MP' => __( 'Northern Mariana Islands', 'wp-sell-services' ),
			'NO' => __( 'Norway', 'wp-sell-services' ),
			'OM' => __( 'Oman', 'wp-sell-services' ),
			'PK' => __( 'Pakistan', 'wp-sell-services' ),
			'PW' => __( 'Palau', 'wp-sell-services' ),
			'PS' => __( 'Palestine', 'wp-sell-services' ),
			'PA' => __( 'Panama', 'wp-sell-services' ),
			'PG' => __( 'Papua New Guinea', 'wp-sell-services' ),
			'PY' => __( 'Paraguay', 'wp-sell-services' ),
			'PE' => __( 'Peru', 'wp-sell-services' ),
			'PH' => __( 'Philippines', 'wp-sell-services' ),
			'PN' => __( 'Pitcairn', 'wp-sell-services' ),
			'PL' => __( 'Poland', 'wp-sell-services' ),
			'PT' => __( 'Portugal', 'wp-sell-services' ),
			'PR' => __( 'Puerto Rico', 'wp-sell-services' ),
			'QA' => __( 'Qatar', 'wp-sell-services' ),
			'RE' => __( 'Réunion', 'wp-sell-services' ),
			'RO' => __( 'Romania', 'wp-sell-services' ),
			'RU' => __( 'Russia', 'wp-sell-services' ),
			'RW' => __( 'Rwanda', 'wp-sell-services' ),
			'BL' => __( 'Saint Barthélemy', 'wp-sell-services' ),
			'SH' => __( 'Saint Helena', 'wp-sell-services' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wp-sell-services' ),
			'LC' => __( 'Saint Lucia', 'wp-sell-services' ),
			'MF' => __( 'Saint Martin', 'wp-sell-services' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'wp-sell-services' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wp-sell-services' ),
			'WS' => __( 'Samoa', 'wp-sell-services' ),
			'SM' => __( 'San Marino', 'wp-sell-services' ),
			'ST' => __( 'Sao Tome and Principe', 'wp-sell-services' ),
			'SA' => __( 'Saudi Arabia', 'wp-sell-services' ),
			'SN' => __( 'Senegal', 'wp-sell-services' ),
			'RS' => __( 'Serbia', 'wp-sell-services' ),
			'SC' => __( 'Seychelles', 'wp-sell-services' ),
			'SL' => __( 'Sierra Leone', 'wp-sell-services' ),
			'SG' => __( 'Singapore', 'wp-sell-services' ),
			'SX' => __( 'Sint Maarten', 'wp-sell-services' ),
			'SK' => __( 'Slovakia', 'wp-sell-services' ),
			'SI' => __( 'Slovenia', 'wp-sell-services' ),
			'SB' => __( 'Solomon Islands', 'wp-sell-services' ),
			'SO' => __( 'Somalia', 'wp-sell-services' ),
			'ZA' => __( 'South Africa', 'wp-sell-services' ),
			'GS' => __( 'South Georgia', 'wp-sell-services' ),
			'KR' => __( 'South Korea', 'wp-sell-services' ),
			'SS' => __( 'South Sudan', 'wp-sell-services' ),
			'ES' => __( 'Spain', 'wp-sell-services' ),
			'LK' => __( 'Sri Lanka', 'wp-sell-services' ),
			'SD' => __( 'Sudan', 'wp-sell-services' ),
			'SR' => __( 'Suriname', 'wp-sell-services' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'wp-sell-services' ),
			'SE' => __( 'Sweden', 'wp-sell-services' ),
			'CH' => __( 'Switzerland', 'wp-sell-services' ),
			'SY' => __( 'Syria', 'wp-sell-services' ),
			'TW' => __( 'Taiwan', 'wp-sell-services' ),
			'TJ' => __( 'Tajikistan', 'wp-sell-services' ),
			'TZ' => __( 'Tanzania', 'wp-sell-services' ),
			'TH' => __( 'Thailand', 'wp-sell-services' ),
			'TL' => __( 'Timor-Leste', 'wp-sell-services' ),
			'TG' => __( 'Togo', 'wp-sell-services' ),
			'TK' => __( 'Tokelau', 'wp-sell-services' ),
			'TO' => __( 'Tonga', 'wp-sell-services' ),
			'TT' => __( 'Trinidad and Tobago', 'wp-sell-services' ),
			'TN' => __( 'Tunisia', 'wp-sell-services' ),
			'TR' => __( 'Türkiye', 'wp-sell-services' ),
			'TM' => __( 'Turkmenistan', 'wp-sell-services' ),
			'TC' => __( 'Turks and Caicos Islands', 'wp-sell-services' ),
			'TV' => __( 'Tuvalu', 'wp-sell-services' ),
			'UG' => __( 'Uganda', 'wp-sell-services' ),
			'UA' => __( 'Ukraine', 'wp-sell-services' ),
			'AE' => __( 'United Arab Emirates', 'wp-sell-services' ),
			'GB' => __( 'United Kingdom', 'wp-sell-services' ),
			'US' => __( 'United States', 'wp-sell-services' ),
			'UM' => __( 'United States Minor Outlying Islands', 'wp-sell-services' ),
			'UY' => __( 'Uruguay', 'wp-sell-services' ),
			'UZ' => __( 'Uzbekistan', 'wp-sell-services' ),
			'VU' => __( 'Vanuatu', 'wp-sell-services' ),
			'VA' => __( 'Vatican City', 'wp-sell-services' ),
			'VE' => __( 'Venezuela', 'wp-sell-services' ),
			'VN' => __( 'Vietnam', 'wp-sell-services' ),
			'VG' => __( 'Virgin Islands (British)', 'wp-sell-services' ),
			'VI' => __( 'Virgin Islands (U.S.)', 'wp-sell-services' ),
			'WF' => __( 'Wallis and Futuna', 'wp-sell-services' ),
			'EH' => __( 'Western Sahara', 'wp-sell-services' ),
			'YE' => __( 'Yemen', 'wp-sell-services' ),
			'ZM' => __( 'Zambia', 'wp-sell-services' ),
			'ZW' => __( 'Zimbabwe', 'wp-sell-services' ),
		);
	}

	/**
	 * Filter the billing country list.
	 *
	 * @since 1.2.3
	 *
	 * @param array $countries ISO-2 code => country name.
	 */
	$countries = apply_filters( 'wpss_countries', $countries );

	return $countries;
}

/**
 * Resolve any stored country value to an ISO-3166 alpha-2 code.
 *
 * Country was a FREE-TEXT field on the vendor profile before 1.2.3, so stored
 * values are a mix of codes ("IN"), full names ("India") and whatever else was
 * typed. Switching that input to a select without this would render blank for
 * every existing vendor and silently drop their country on the next save.
 *
 * @since 1.2.3
 *
 * @param string $value Stored country value.
 * @return string ISO-2 code, or '' when it cannot be resolved.
 */
function wpss_resolve_country_code( string $value ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	$countries = wpss_get_countries();

	// Already a valid code.
	$upper = strtoupper( $value );
	if ( isset( $countries[ $upper ] ) ) {
		return $upper;
	}

	// Legacy free text — match on name, case-insensitively.
	foreach ( $countries as $code => $name ) {
		if ( 0 === strcasecmp( $name, $value ) ) {
			return $code;
		}
	}

	return '';
}

/**
 * Display name for a stored country value.
 *
 * Read-side counterpart of {@see wpss_resolve_country_code()}. Every surface
 * that SHOWS a country goes through this, so the vendor card, the public
 * profile and the admin screen can never disagree. Falls back to the raw value
 * when it cannot be resolved, so nothing a vendor typed simply vanishes.
 *
 * @since 1.2.3
 *
 * @param string $value Stored country value (code or legacy free text).
 * @return string Display name.
 */
function wpss_get_country_name( string $value ): string {
	$code = wpss_resolve_country_code( $value );

	if ( '' === $code ) {
		return trim( $value );
	}

	$countries = wpss_get_countries();

	return $countries[ $code ] ?? trim( $value );
}

/**
 * Read a user's saved billing address.
 *
 * Reads the WooCommerce-compatible user meta, so on a Woo site this returns the
 * address the buyer already gave WooCommerce — no re-entry, no migration.
 *
 * @since 1.2.3
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return array<string, string> Field key => value. Missing fields are ''.
 */
function wpss_get_billing_address( int $user_id = 0 ): array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return array();
	}

	$address = array();

	$user = null;

	foreach ( array_keys( wpss_get_billing_fields() ) as $key ) {
		$value = get_user_meta( $user_id, $key, true );

		/*
		 * Fall back to what WordPress already knows about this person, so a
		 * signed-in buyer is never asked to retype it.
		 *
		 * The email fallback shipped first and the name fields were left out,
		 * which is why the checkout screenshot on Basecamp 10240017373 shows an
		 * empty email and the same screen today shows an empty first and last
		 * name: same defect, different fields. Handling all three here rather
		 * than in the checkout template means the account screen and the
		 * pay-order checkout get it too, instead of three prefill rules
		 * drifting apart.
		 */
		if ( '' === $value ) {
			if ( null === $user ) {
				$user = get_userdata( $user_id );
			}

			if ( $user ) {
				if ( 'billing_email' === $key ) {
					$value = $user->user_email;
				} elseif ( 'billing_first_name' === $key || 'billing_last_name' === $key ) {
					$meta = get_user_meta( $user_id, str_replace( 'billing_', '', $key ), true );

					// display_name is the last resort and is split on the first
					// space only: "Maria del Carmen Ruiz" keeps everything after
					// the first token as the surname rather than losing it.
					if ( '' === $meta && '' !== (string) $user->display_name ) {
						$parts = explode( ' ', trim( (string) $user->display_name ), 2 );
						$meta  = 'billing_first_name' === $key ? $parts[0] : ( $parts[1] ?? '' );
					}

					$value = $meta;
				}
			}
		}

		$address[ $key ] = is_string( $value ) ? $value : '';
	}

	/**
	 * Filter a user's billing address after it is read.
	 *
	 * @since 1.2.3
	 *
	 * @param array $address Field key => value.
	 * @param int   $user_id User ID.
	 */
	return apply_filters( 'wpss_billing_address', $address, $user_id );
}

/**
 * Save a user's billing address to their profile.
 *
 * Writes the same WooCommerce keys it reads, so the address stays shared with
 * WooCommerce rather than forking into a WPSS-only copy that drifts.
 *
 * @since 1.2.3
 *
 * @param int                   $user_id User ID.
 * @param array<string, string> $address Raw field values.
 * @return bool True when something was written.
 */
function wpss_save_billing_address( int $user_id, array $address ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	$fields  = wpss_get_billing_fields();
	$written = false;

	foreach ( $fields as $key => $definition ) {
		if ( ! array_key_exists( $key, $address ) ) {
			continue;
		}

		$value = $address[ $key ];

		switch ( $definition['type'] ) {
			case 'email':
				$value = sanitize_email( (string) $value );
				break;
			case 'country':
				// ISO-3166 alpha-2, upper-cased.
				$value = strtoupper( substr( sanitize_text_field( (string) $value ), 0, 2 ) );
				break;
			default:
				$value = sanitize_text_field( (string) $value );
		}

		update_user_meta( $user_id, $key, $value );
		$written = true;
	}

	if ( $written ) {
		/**
		 * Fires after a user's billing address is saved.
		 *
		 * @since 1.2.3
		 *
		 * @param int   $user_id User ID.
		 * @param array $address Sanitized values that were written.
		 */
		do_action( 'wpss_billing_address_saved', $user_id, $address );
	}

	return $written;
}

/**
 * Save billing fields posted with a checkout submission to the buyer's profile.
 *
 * Gateway-agnostic on purpose: any checkout completion handler — Stripe,
 * PayPal, Razorpay, offline — calls this with its own request payload, so an
 * address the buyer corrected at checkout is remembered for next time no matter
 * how they paid.
 *
 * MUST run BEFORE the order is marked paid. mark_as_paid() snapshots the
 * address from the profile, so saving afterwards would stamp the order with the
 * OLD address and silently discard the correction the buyer just made.
 *
 * Only writes keys actually present in the request, so a gateway that posts a
 * partial payload cannot blank the rest of the profile.
 *
 * @since 1.2.3
 *
 * @param array<string, mixed> $request Raw request data ($_POST or a REST payload).
 * @param int                  $user_id Optional. Defaults to the current user.
 * @return bool True when something was written.
 */
function wpss_save_billing_from_request( array $request, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return false;
	}

	$posted = array();

	foreach ( array_keys( wpss_get_billing_fields() ) as $key ) {
		if ( isset( $request[ $key ] ) ) {
			$posted[ $key ] = wp_unslash( $request[ $key ] );
		}
	}

	if ( empty( $posted ) ) {
		return false;
	}

	// wpss_save_billing_address() sanitises per field type.
	return wpss_save_billing_address( $user_id, $posted );
}

/**
 * Whether a user's billing address has everything required.
 *
 * Drives the checkout decision: complete means the address block collapses and
 * the buyer only has to enter card details.
 *
 * @since 1.2.3
 *
 * @param int|array<string, mixed> $user_or_address User ID, or an address array to test directly.
 * @return bool
 */
function wpss_is_billing_address_complete( $user_or_address = 0 ): bool {
	$address = is_array( $user_or_address )
		? $user_or_address
		: wpss_get_billing_address( (int) $user_or_address );

	if ( empty( $address ) ) {
		return false;
	}

	foreach ( wpss_get_billing_fields() as $key => $definition ) {
		if ( ! empty( $definition['required'] ) && empty( $address[ $key ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether a logged-out buyer may complete checkout, getting an account created.
 *
 * There is no guest order in this product, and that is a decision rather than an
 * omission (owner, 2026-08-17). After paying, a buyer has to submit requirements,
 * message the seller, review a delivery, request revisions and possibly open a
 * dispute - every one of which needs an identity. An order with `customer_id = 0`
 * could not be fulfilled, and worse: order access is checked as
 * `customer_id === get_current_user_id()`, and a logged-out visitor IS user 0, so
 * such an order would be readable by every logged-out visitor on the internet.
 *
 * So this setting does not enable guest checkout. It removes the sign-in WALL and
 * creates the account from the billing name and email the buyer is already
 * entering, signing them in before the order is inserted. `customer_id` is real
 * from the first moment and no schema or second ownership model is involved.
 *
 * Off by default: it changes who is able to transact on the site.
 *
 * @since 1.6.0
 *
 * @return bool
 */
function wpss_checkout_creates_accounts(): bool {
	$settings = get_option( 'wpss_general', array() );
	$enabled  = ! empty( $settings['checkout_account_creation'] );

	/**
	 * Filter whether checkout creates an account for a logged-out buyer.
	 *
	 * @since 1.6.0
	 *
	 * @param bool $enabled Whether the flow is enabled.
	 */
	return (bool) apply_filters( 'wpss_checkout_creates_accounts', $enabled );
}

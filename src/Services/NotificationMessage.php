<?php
/**
 * Notification Message
 *
 * A notification body authored once and rendered twice: as plain text for the
 * `wpss_notifications` row (which the REST API and the mobile app read) and as
 * HTML for the transactional email.
 *
 * Before this existed, notification bodies were authored as HTML strings and
 * the same string was both stored and emailed, so `GET /wpss/v1/notifications`
 * shipped `<strong>`, `<br>` and inline `style=""` to API clients, and the
 * translator strings themselves carried markup. Authoring against this builder
 * keeps markup out of `__()` entirely — the structure lives in the method call,
 * not in the translatable sentence.
 *
 * @package WPSellServices\Services
 * @since   1.2.3
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Structured notification body with plain-text and HTML renderers.
 *
 * @since 1.2.3
 */
final class NotificationMessage {

	/**
	 * Block kind: a run of lines separated by a single break.
	 */
	private const BLOCK_LINES = 'lines';

	/**
	 * Block kind: a boxed callout (used for quoted message previews).
	 */
	private const BLOCK_CALLOUT = 'callout';

	/**
	 * Block kind: a labelled quotation without the box.
	 */
	private const BLOCK_QUOTE = 'quote';

	/**
	 * Block kind: a stand-alone emphasised note.
	 */
	private const BLOCK_NOTE = 'note';

	/**
	 * Array key that marks an argument for emphasis.
	 */
	private const EMPHASIS_KEY = '__wpss_strong';

	/**
	 * Inline style applied to callout boxes in the HTML rendering.
	 */
	private const CALLOUT_STYLE = 'background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #1e3a5f; margin: 10px 0;';

	/**
	 * Body blocks.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $blocks = array();

	/**
	 * Whether the next line must open a new block.
	 *
	 * @var bool
	 */
	private bool $break_pending = false;

	/**
	 * Create an empty message.
	 *
	 * @return self
	 */
	public static function make(): self {
		return new self();
	}

	/**
	 * Mark a value for emphasis: bold in HTML, unchanged in plain text.
	 *
	 * Values are passed raw — escaping happens in the HTML renderer, never at
	 * the call site, so the plain-text rendering stays free of entities.
	 *
	 * @param string $text Raw value.
	 * @return array{__wpss_strong: string}
	 */
	public static function strong( string $text ): array {
		return array( self::EMPHASIS_KEY => $text );
	}

	/**
	 * Force the next line to start a new block (a blank line).
	 *
	 * @return self
	 */
	public function block(): self {
		$this->break_pending = true;

		return $this;
	}

	/**
	 * Append a line to the current block.
	 *
	 * @param string $format Markup-free sprintf format (usually a __() string).
	 * @param mixed  ...$args Raw values; wrap in self::strong() to emphasise.
	 * @return self
	 */
	public function line( string $format, mixed ...$args ): self {
		$last = array_key_last( $this->blocks );

		if ( $this->break_pending || null === $last || self::BLOCK_LINES !== $this->blocks[ $last ]['kind'] ) {
			$this->blocks[]      = array(
				'kind'  => self::BLOCK_LINES,
				'lines' => array(),
			);
			$last                = array_key_last( $this->blocks );
			$this->break_pending = false;
		}

		$this->blocks[ $last ]['lines'][] = array(
			'format' => $format,
			'args'   => $args,
		);

		return $this;
	}

	/**
	 * Start a new block and append a line to it.
	 *
	 * @param string $format Markup-free sprintf format.
	 * @param mixed  ...$args Raw values.
	 * @return self
	 */
	public function paragraph( string $format, mixed ...$args ): self {
		return $this->block()->line( $format, ...$args );
	}

	/**
	 * Append an emphasised stand-alone line, starting a new block.
	 *
	 * @param string $text Heading text.
	 * @return self
	 */
	public function heading( string $text ): self {
		return $this->paragraph( '%s', self::strong( $text ) );
	}

	/**
	 * Append a "Label: value" line with an emphasised label.
	 *
	 * @param string $label Label including its trailing colon.
	 * @param string $value Raw value.
	 * @return self
	 */
	public function field( string $label, string $value ): self {
		return $this->line( '%1$s %2$s', self::strong( $label ), $value );
	}

	/**
	 * Append a boxed, quoted excerpt (message previews).
	 *
	 * @param string $label Label including its trailing colon.
	 * @param string $text  Raw excerpt.
	 * @return self
	 */
	public function callout( string $label, string $text ): self {
		$this->blocks[]      = array(
			'kind'  => self::BLOCK_CALLOUT,
			'label' => $label,
			'text'  => $text,
		);
		$this->break_pending = false;

		return $this;
	}

	/**
	 * Append a labelled quotation without the box.
	 *
	 * @param string $label Label including its trailing colon.
	 * @param string $text  Raw quoted text.
	 * @return self
	 */
	public function quote( string $label, string $text ): self {
		$this->blocks[]      = array(
			'kind'  => self::BLOCK_QUOTE,
			'label' => $label,
			'text'  => $text,
		);
		$this->break_pending = false;

		return $this;
	}

	/**
	 * Append a stand-alone emphasised note.
	 *
	 * @param string $text Raw note text.
	 * @return self
	 */
	public function note( string $text ): self {
		$this->blocks[]      = array(
			'kind' => self::BLOCK_NOTE,
			'text' => $text,
		);
		$this->break_pending = false;

		return $this;
	}

	/**
	 * Whether the message has no content.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->blocks;
	}

	/**
	 * Render for storage and for API clients: plain text, real newlines.
	 *
	 * @return string
	 */
	public function to_plain_text(): string {
		return $this->render( false, "\n", "\n\n" );
	}

	/**
	 * Render for email: the same body with its formatting.
	 *
	 * @return string
	 */
	public function to_html(): string {
		return $this->render( true, '<br>', '<br><br>' );
	}

	/**
	 * Render all blocks.
	 *
	 * @param bool   $html          Whether to emit HTML.
	 * @param string $line_break    Separator between lines of one block.
	 * @param string $block_break   Separator between blocks.
	 * @return string
	 */
	private function render( bool $html, string $line_break, string $block_break ): string {
		$rendered = array();

		foreach ( $this->blocks as $block ) {
			$chunk = $this->render_block( $block, $html, $line_break );

			if ( '' !== trim( $chunk ) ) {
				$rendered[] = $chunk;
			}
		}

		return implode( $block_break, $rendered );
	}

	/**
	 * Render a single block.
	 *
	 * @param array<string, mixed> $block      Block definition.
	 * @param bool                 $html       Whether to emit HTML.
	 * @param string               $line_break Separator between lines.
	 * @return string
	 */
	private function render_block( array $block, bool $html, string $line_break ): string {
		$kind  = (string) ( $block['kind'] ?? self::BLOCK_LINES );
		$label = (string) ( $block['label'] ?? '' );
		$text  = (string) ( $block['text'] ?? '' );

		switch ( $kind ) {
			case self::BLOCK_CALLOUT:
				$inner = $this->render_quotation( $label, $text, $html, $line_break );

				return $html
					? '<div style="' . self::CALLOUT_STYLE . '">' . $inner . '</div>'
					: $inner;

			case self::BLOCK_QUOTE:
				return $this->render_quotation( $label, $text, $html, $line_break );

			case self::BLOCK_NOTE:
				return $html ? '<em>' . esc_html( $text ) . '</em>' : $text;
		}

		$lines = array();

		/**
		 * Lines belonging to this block.
		 *
		 * @var array<int, array<string, mixed>> $block_lines
		 */
		$block_lines = is_array( $block['lines'] ?? null ) ? $block['lines'] : array();

		foreach ( $block_lines as $line ) {
			$lines[] = $this->render_line( $line, $html );
		}

		return implode( $line_break, $lines );
	}

	/**
	 * Render a labelled quotation shared by callout and quote blocks.
	 *
	 * @param string $label      Label including its trailing colon.
	 * @param string $text       Raw quoted text.
	 * @param bool   $html       Whether to emit HTML.
	 * @param string $line_break Separator between lines.
	 * @return string
	 */
	private function render_quotation( string $label, string $text, bool $html, string $line_break ): string {
		$parts = array();

		if ( '' !== $label ) {
			$parts[] = $html ? '<strong>' . esc_html( $label ) . '</strong>' : $label;
		}

		$parts[] = $html
			? '<em>"' . esc_html( $text ) . '"</em>'
			: '"' . $text . '"';

		return implode( $line_break, $parts );
	}

	/**
	 * Render one line, escaping only for the HTML rendering.
	 *
	 * @param array<string, mixed> $line Line definition.
	 * @param bool                 $html Whether to emit HTML.
	 * @return string
	 */
	private function render_line( array $line, bool $html ): string {
		$format = (string) ( $line['format'] ?? '' );
		$args   = is_array( $line['args'] ?? null ) ? $line['args'] : array();
		$values = array();

		foreach ( $args as $arg ) {
			if ( is_array( $arg ) && isset( $arg[ self::EMPHASIS_KEY ] ) ) {
				$value    = (string) $arg[ self::EMPHASIS_KEY ];
				$values[] = $html ? '<strong>' . esc_html( $value ) . '</strong>' : $value;
				continue;
			}

			$value    = is_scalar( $arg ) ? (string) $arg : '';
			$values[] = $html ? esc_html( $value ) : $value;
		}

		if ( $html ) {
			$format = esc_html( $format );
		}

		if ( array() === $values ) {
			return $format;
		}

		return vsprintf( $format, $values );
	}

	/**
	 * Reduce a legacy HTML notification body to readable plain text.
	 *
	 * Rows written before notification bodies were stored as plain text still
	 * contain markup. Readers run them through this so old notifications read
	 * as several lines rather than one run-on line: structural tags become
	 * newlines BEFORE tags are stripped, and entities are decoded last so that
	 * an escaped `&lt;b&gt;` in the original stays literal text.
	 *
	 * @param string $message Stored message, plain or legacy HTML.
	 * @return string
	 */
	public static function to_plain( string $message ): string {
		if ( '' === $message ) {
			return '';
		}

		$text = str_replace( array( "\r\n", "\r" ), "\n", $message );

		if ( false === strpos( $text, '<' ) && false === strpos( $text, '&' ) ) {
			return trim( $text );
		}

		// Structural tags carry the line breaks — turn them into real newlines
		// before the tags are thrown away.
		$text = (string) preg_replace( '#<(?:br|hr)\s*/?>#i', "\n", $text );
		$text = (string) preg_replace( '#</(?:p|div|li|tr|h[1-6]|blockquote|table)\s*>#i', "\n", $text );
		$text = (string) preg_replace( '#<(?:p|div|li|tr|h[1-6]|blockquote|table)\b[^>]*>#i', "\n", $text );

		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xc2\xa0", ' ', $text );

		$text = (string) preg_replace( '#[ \t]+\n#', "\n", $text );
		$text = (string) preg_replace( '#\n[ \t]+#', "\n", $text );
		$text = (string) preg_replace( '#\n{3,}#', "\n\n", $text );

		return trim( $text );
	}
}

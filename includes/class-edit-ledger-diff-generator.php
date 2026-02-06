<?php
/**
 * Diff generator for Edit Ledger.
 *
 * @package Edit_Ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements LCS-based word-level diff generation between two text strings.
 */
class Edit_Ledger_Diff_Generator {

	/**
	 * Generate an HTML diff between two strings.
	 *
	 * @param string $from The original text.
	 * @param string $to   The new text.
	 * @return string HTML diff output.
	 */
	public function generate( $from, $to ) {
		if ( $from === $to ) {
			return '';
		}

		if ( empty( $from ) ) {
			return '<ins class="edit-ledger-diff-ins">' . esc_html( $to ) . '</ins>';
		}
		if ( empty( $to ) ) {
			return '<del class="edit-ledger-diff-del">' . esc_html( $from ) . '</del>';
		}

		$diff = $this->compute_word_diff( $from, $to );

		return $diff;
	}

	/**
	 * Compute word-level diff.
	 *
	 * @param string $from Original text.
	 * @param string $to   New text.
	 * @return string HTML diff.
	 */
	private function compute_word_diff( $from, $to ) {
		$from_words = $this->tokenize( $from );
		$to_words   = $this->tokenize( $to );

		$diff = $this->diff_arrays( $from_words, $to_words );

		return $this->render_diff( $diff );
	}

	/**
	 * Tokenize text into words and whitespace.
	 *
	 * @param string $text The text to tokenize.
	 * @return array
	 */
	private function tokenize( $text ) {
		preg_match_all( '/\S+|\s+/', $text, $matches );
		return $matches[0];
	}

	/**
	 * Compute diff between two arrays using longest common subsequence.
	 *
	 * @param array $from Original array.
	 * @param array $to   New array.
	 * @return array Diff operations.
	 */
	private function diff_arrays( $from, $to ) {
		$from_len = count( $from );
		$to_len   = count( $to );

		$lcs = array();
		for ( $i = 0; $i <= $from_len; $i++ ) {
			$lcs[ $i ] = array();
			for ( $j = 0; $j <= $to_len; $j++ ) {
				$lcs[ $i ][ $j ] = 0;
			}
		}

		for ( $i = 1; $i <= $from_len; $i++ ) {
			for ( $j = 1; $j <= $to_len; $j++ ) {
				if ( $from[ $i - 1 ] === $to[ $j - 1 ] ) {
					$lcs[ $i ][ $j ] = $lcs[ $i - 1 ][ $j - 1 ] + 1;
				} else {
					$lcs[ $i ][ $j ] = max( $lcs[ $i - 1 ][ $j ], $lcs[ $i ][ $j - 1 ] );
				}
			}
		}

		$diff = array();
		$i    = $from_len;
		$j    = $to_len;

		while ( $i > 0 || $j > 0 ) {
			if ( $i > 0 && $j > 0 && $from[ $i - 1 ] === $to[ $j - 1 ] ) {
				array_unshift( $diff, array( 'equal', $from[ $i - 1 ] ) );
				--$i;
				--$j;
			} elseif ( $j > 0 && ( 0 === $i || $lcs[ $i ][ $j - 1 ] >= $lcs[ $i - 1 ][ $j ] ) ) {
				array_unshift( $diff, array( 'insert', $to[ $j - 1 ] ) );
				--$j;
			} elseif ( $i > 0 && ( 0 === $j || $lcs[ $i ][ $j - 1 ] < $lcs[ $i - 1 ][ $j ] ) ) {
				array_unshift( $diff, array( 'delete', $from[ $i - 1 ] ) );
				--$i;
			}
		}

		return $diff;
	}

	/**
	 * Render diff array to HTML.
	 *
	 * @param array $diff The diff operations.
	 * @return string HTML output.
	 */
	private function render_diff( $diff ) {
		$html         = '';
		$current_type = null;
		$current_text = '';

		foreach ( $diff as $op ) {
			list( $type, $text ) = $op;

			if ( $type === $current_type ) {
				$current_text .= $text;
			} else {
				$html        .= $this->wrap_segment( $current_type, $current_text );
				$current_type = $type;
				$current_text = $text;
			}
		}

		$html .= $this->wrap_segment( $current_type, $current_text );

		return $html;
	}

	/**
	 * Wrap a text segment with appropriate HTML tags.
	 *
	 * @param string|null $type The segment type (equal, insert, delete).
	 * @param string      $text The text content.
	 * @return string HTML output.
	 */
	private function wrap_segment( $type, $text ) {
		if ( empty( $text ) ) {
			return '';
		}

		$escaped = esc_html( $text );

		switch ( $type ) {
			case 'insert':
				return '<ins class="edit-ledger-diff-ins">' . $escaped . '</ins>';
			case 'delete':
				return '<del class="edit-ledger-diff-del">' . $escaped . '</del>';
			case 'equal':
			default:
				return $escaped;
		}
	}
}

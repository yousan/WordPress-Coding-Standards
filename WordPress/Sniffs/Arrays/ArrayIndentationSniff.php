<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

/**
 * Enforces WordPress array indentation for multi-line arrays.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 */
class WordPress_Sniffs_Arrays_ArrayIndentationSniff extends WordPress_Sniff {

	/**
	 * The --tab-width CLI value that is being used.
	 *
	 * @var int
	 */
	private $tab_width;

	/**
	 * Tokens to ignore for subsequent lines in a multi-line array item.
	 *
	 * Property is set in the register() method.
	 *
	 * @var array
	 */
	private $ignore_tokens = array();


	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		// Set the $ignore_tokens property.
		$this->ignore_tokens = PHP_CodeSniffer_Tokens::$heredocTokens;
		unset( $this->ignore_tokens[ T_START_HEREDOC ], $this->ignore_tokens[ T_START_NOWDOC ] );

		return array(
			T_ARRAY,
			T_OPEN_SHORT_ARRAY,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {
		if ( ! isset( $this->tab_width ) ) {
			$cli_values = $this->phpcsFile->phpcs->cli->getCommandLineValues();
			if ( ! isset( $cli_values['tabWidth'] ) || 0 === $cli_values['tabWidth'] ) {
				// We have no idea how wide tabs are, so assume 4 spaces for fixing.
				$this->tab_width = 4;
			} else {
				$this->tab_width = $cli_values['tabWidth'];
			}
		}

		/*
		 * Determine the array opener & closer.
		 */
		if ( T_ARRAY === $this->tokens[ $stackPtr ]['code'] ) {
			if ( ! isset( $this->tokens[ $stackPtr ]['parenthesis_opener'] ) ) {
				return; // Live coding.
			}
			$opener = $this->tokens[ $stackPtr ]['parenthesis_opener'];

			if ( ! isset( $this->tokens[ $opener ]['parenthesis_closer'] ) ) {
				return; // Live coding.
			}
			$closer = $this->tokens[ $opener ]['parenthesis_closer'];
		} else {
			// Short array syntax.
			$opener = $stackPtr;

			if ( ! isset( $this->tokens[ $stackPtr ]['bracket_closer'] ) ) {
				return; // Live coding.
			}
			$closer = $this->tokens[ $stackPtr ]['bracket_closer'];
		}

		if ( $this->tokens[ $opener ]['line'] === $this->tokens[ $closer ]['line'] ) {
			// Not interested in single line arrays.
			return;
		}

		/*
		 * Determine the indentation of the line containing the array opener.
		 */
		$indentation = '';
		$column      = 1;
		for ( $i = $stackPtr; $i >= 0; $i-- ) {
			if ( $this->tokens[ $i ]['line'] === $this->tokens[ $stackPtr ]['line'] ) {
				continue;
			}

			if ( T_WHITESPACE === $this->tokens[ ( $i + 1 ) ]['code'] ) {
				// If the tokenizer replaced tabs with spaces, use the original content.
				$indentation = $this->tokens[ ( $i + 1 ) ]['content'];
				if ( isset( $this->tokens[ ( $i + 1 ) ]['orig_content'] ) ) {
					$indentation = $this->tokens[ ( $i + 1 ) ]['orig_content'];
				}
				$column = $this->tokens[ ( $i + 2 ) ]['column'];
			}
			break;
		}
		unset( $i );

		/*
		 * Check the closing bracket is lined up with the start of the content on the line
		 * containing the array opener.
		 */
		if ( $this->tokens[ $closer ]['column'] !== $column ) {

			$this->add_array_alignment_error(
				$closer,
				'Array closer not aligned correctly; expected %s space(s) but found %s',
				'CloseBraceNotAligned',
				( $column - 1 ),
				( $this->tokens[ $closer ]['column'] - 1 ),
				$indentation
			);
		}

		/*
		 * Verify & correct the array item indentation.
		 */
		$array_items = $this->get_function_call_parameters( $stackPtr );
		if ( empty( $array_items ) ) {
			// Strange, no array items found.
			return;
		}

		$expected_indent      = "\t" . $indentation;
		$expected_spaces      = ( ( $column + $this->tab_width ) - 1 );
		$end_of_previous_item = $opener;

		foreach ( $array_items as $item ) {
			// Find the line on which the item starts.
			$first_content    = $this->phpcsFile->findNext( array( T_WHITESPACE, T_DOC_COMMENT_WHITESPACE ), $item['start'], ( $item['end'] + 1 ), true );
			$end_of_this_item = ( $item['end'] + 1 );
			if ( false === $first_content ) {
				$end_of_previous_item = $end_of_this_item;
				continue;
			}

			// Bow out from reporting and fixing mixed multi-line/single-line arrays.
			// That is handled by the ArrayDeclarationSniff.
			if ( $this->tokens[ $first_content ]['line'] === $this->tokens[ $end_of_previous_item ]['line'] ) {
				return $closer;
			}

			$found_indent = $this->determine_whitespace( $first_content );
			$found_spaces = ( $this->tokens[ $first_content ]['column'] - 1 );

			if ( $found_indent !== $expected_indent ) {
				$this->add_array_alignment_error(
					$first_content,
					'Array item not aligned correctly; expected %s spaces but found %s',
					'ItemNotAligned',
					$expected_spaces,
					$found_spaces,
					$expected_indent
				);
			}

			// No need for further checking if this is a one-line array item.
			if ( $this->tokens[ $first_content ]['line'] === $this->tokens[ $item['end'] ]['line'] ) {
				$end_of_previous_item = $end_of_this_item;
				continue;
			}

			/*
			 * Multi-line array item. Verify & if needed, correct the indentation of subsequent lines.
			 * Subsequent lines may be indented more or less than the mimimum expected indent,
			 * but the "first line after" should be indented - at least - as much as the very first line
			 * of the array item.
			 * Indentation correction for subsequent lines will be based on that diff.
			 */

			// Find first content on second line of the array item.
			// If the second line is a heredoc/nowdoc, continue on until we find a line with a different token.
			for ( $ptr = ( $first_content + 1 ); $ptr <= $item['end']; $ptr++ ) {
				if ( $this->tokens[ $first_content ]['line'] !== $this->tokens[ $ptr ]['line']
					&& ! isset( $this->ignore_tokens[ $this->tokens[ $ptr ]['code'] ] )
				) {
					break;
				}
			}

			if ( false === $ptr ) {
				$end_of_previous_item = $end_of_this_item;
				continue;
			}

			$first_content_on_line2 = $this->phpcsFile->findNext( array( T_WHITESPACE, T_DOC_COMMENT_WHITESPACE ), $ptr, ( $item['end'] + 1 ), true );

			if ( false === $first_content_on_line2 ) {
				$end_of_previous_item = $end_of_this_item;
				continue;
			}

			$found_indent_on_line2    = $this->determine_whitespace( $first_content_on_line2 );
			$found_spaces_on_line2    = strlen(
				str_replace( "\t", str_repeat( ' ', $this->tab_width ), $found_indent_on_line2 )
			); // Can't rely on column for space determination because of multi-line non-docblock comments.
			$line2_indent_diff        = 0;
			$expected_spaces_on_line2 = $expected_spaces;

			if ( $found_spaces < $found_spaces_on_line2 ) {
				$line2_indent_diff        = ( $found_spaces_on_line2 - $found_spaces );
				$expected_spaces_on_line2 = $expected_spaces + $line2_indent_diff;
			}
			$expected_indent_on_line2 = $this->spaces_to_indent( $expected_spaces_on_line2 );

			if ( $found_indent_on_line2 !== $expected_indent_on_line2 ) {

				$fix = $this->phpcsFile->addFixableError(
					'Multi-line array item not aligned correctly; expected %s spaces, but found %s',
					$first_content_on_line2,
					'MultiLineArrayItemNotAligned',
					array(
						$expected_spaces_on_line2,
						$found_spaces_on_line2,
					)
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();

					// Fix second line for the array item.
					if ( 1 === $this->tokens[ $first_content_on_line2 ]['column']
						&& T_COMMENT === $this->tokens[ $first_content_on_line2 ]['code']
					) {
						$actual_comment = ltrim( $this->tokens[ $first_content_on_line2 ]['content'] );
						$replacement    = $expected_indent_on_line2 . $actual_comment;

						$this->phpcsFile->fixer->replaceToken( $first_content_on_line2, $replacement );

					} else {
						$this->fix_alignment_error( $first_content_on_line2, $expected_indent_on_line2 );
					}

					// Fix subsequent lines.
					for ( $i = ( $first_content_on_line2 + 1 ); $i <= $item['end']; $i++ ) {
						// We're only interested in the first token on each line.
						if ( 1 !== $this->tokens[ $i ]['column'] ) {
							if ( $this->tokens[ $i ]['line'] === $this->tokens[ $item['end'] ]['line'] ) {
								// We might as well quit if we're past the first token on the last line.
								break;
							}
							continue;
						}

						$first_content_on_line = $this->phpcsFile->findNext(
							array( T_WHITESPACE, T_DOC_COMMENT_WHITESPACE ),
							$i,
							( $item['end'] + 1 ),
							true
						);

						if ( false === $first_content_on_line ) {
							break;
						}

						// Ignore lines with heredoc and nowdoc tokens.
						if ( isset( $this->ignore_tokens[ $this->tokens[ $first_content_on_line ]['code'] ] ) ) {
							$i = $first_content_on_line;
							continue;
						}

						$found_indent_on_line = $this->determine_whitespace( $first_content_on_line );
						$found_spaces_on_line = strlen(
							str_replace( "\t", str_repeat( ' ', $this->tab_width ), $found_indent_on_line )
						);

						$expected_spaces_on_line = ( $expected_spaces_on_line2 + ( $found_spaces_on_line - $found_spaces_on_line2 ) );
						$expected_spaces_on_line = max( $expected_spaces_on_line, 0 ); // Can't be below 0.
						$expected_indent_on_line = $this->spaces_to_indent( $expected_spaces_on_line );

						if ( $found_indent_on_line !== $expected_indent_on_line ) {
							if ( 1 === $this->tokens[ $first_content_on_line ]['column']
								&& T_COMMENT === $this->tokens[ $first_content_on_line ]['code']
							) {
								$actual_comment = ltrim( $this->tokens[ $first_content_on_line ]['content'] );
								$replacement    = $expected_indent_on_line . $actual_comment;

								$this->phpcsFile->fixer->replaceToken( $first_content_on_line, $replacement );
							} else {
								$this->fix_alignment_error( $first_content_on_line, $expected_indent_on_line );
							}
						}

						// Move passed any potential empty lines between the previous item and this one.
						// No need to do the fixes twice.
						$i = $first_content_on_line;
					}

					$this->phpcsFile->fixer->endChangeset();
				}
			}

			$end_of_previous_item = $end_of_this_item;
		}

	} // End process_token().


	/**
	 * Determine the line indentation whitespace.
	 *
	 * @param int $ptr Stack pointer to the first non-whitespace token on the line.
	 *
	 * @return string
	 */
	protected function determine_whitespace( $ptr ) {
		$whitespace = '';
		if ( 1 !== $this->tokens[ $ptr ]['column']
			&& ( T_WHITESPACE === $this->tokens[ ( $ptr - 1 ) ]['code']
				|| T_DOC_COMMENT_WHITESPACE === $this->tokens[ ( $ptr - 1 ) ]['code'] )
		) {
			$whitespace = $this->tokens[ ( $ptr - 1 ) ]['content'];

			// If tabs are being converted to spaces by the tokenizer, the
			// original content should be checked instead of the converted content.
			if ( isset( $this->tokens[ ( $ptr - 1 ) ]['orig_content'] ) ) {
				$whitespace = $this->tokens[ ( $ptr - 1 ) ]['orig_content'];
			}
		}

		/*
		 * Special case for multi-line, non-docblock comments.
		 * Only applicable for subsequent lines in an array item.
		 *
		 * First/Single line is tokenized as T_WHITESPACE + T_COMMENT
		 * Subsequent lines are tokenized as T_COMMENT including the indentation whitespace.
		 */
		if ( 1 === $this->tokens[ $ptr ]['column']
			&& T_COMMENT === $this->tokens[ $ptr ]['code']
		) {
			$content = $this->tokens[ $ptr ]['content'];
			if ( isset( $this->tokens[ $ptr ]['orig_content'] ) ) {
				$content = $this->tokens[ $ptr ]['orig_content'];
			}

			$actual_comment = ltrim( $content );
			$whitespace     = str_replace( $actual_comment, '', $content );
		}

		return $whitespace;
	}

	/**
	 * Create a tab-based indentation string.
	 *
	 * @param int $nr Number of "spaces" the indentation should be.
	 *
	 * @return string
	 */
	protected function spaces_to_indent( $nr ) {
		if ( 0 >= $nr ) {
			return '';
		}

		$num_tabs  = (int) floor( $nr / $this->tab_width );
		$remaining = ( $nr % $this->tab_width );
		$indent    = str_repeat( "\t", $num_tabs );
		$indent   .= str_repeat( ' ', $remaining );

		return $indent;
	}

	/**
	 * Throw an error and fix incorrect array alignment.
	 *
	 * @param int    $ptr        Stack pointer to the first content on the line.
	 * @param string $error      Error message.
	 * @param string $error_code Error code.
	 * @param int    $expected   Expected nr of spaces (tabs translated to space value).
	 * @param int    $found      Found nr of spaces (tabs translated to space value).
	 * @param string $new_indent Whitespace indent replacement value.
	 */
	protected function add_array_alignment_error( $ptr, $error, $error_code, $expected, $found, $new_indent ) {
		$data  = array(
			$expected,
			$found,
		);

		$fix = $this->phpcsFile->addFixableError( $error, $ptr, $error_code, $data );
		if ( true === $fix ) {
			$this->fix_alignment_error( $ptr, $new_indent );
		}
	}

	/**
	 * Fix incorrect array alignment.
	 *
	 * @param int    $ptr        Stack pointer to the first content on the line.
	 * @param string $new_indent Whitespace indent replacement value.
	 */
	protected function fix_alignment_error( $ptr, $new_indent ) {
		if ( 0 === ( $this->tokens[ $ptr ]['column'] - 1 ) ) {
			$this->phpcsFile->fixer->addContent( ( $ptr - 1 ), $new_indent );
		} else {
			$this->phpcsFile->fixer->replaceToken( ( $ptr - 1 ), $new_indent );
		}
	}

} // End class.

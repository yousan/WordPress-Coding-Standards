<?php
/**
 * Unit test class for WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

/**
 * Unit test class for the WP_DeprecatedFunctions sniff.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.11.0
 */
class WordPress_Tests_WP_DeprecatedFunctionsUnitTest extends AbstractSniffUnitTest {

	/**
	 * Returns the lines where errors should occur.
	 *
	 * @return array <int line number> => <int number of errors>
	 */
	public function getErrorList() {

		$errors = array_fill( 8, 269, 1 );

		// Unset the lines related to version comments.
		unset(
			$errors[10],  $errors[12],  $errors[14],  $errors[16],  $errors[28],
			$errors[54],  $errors[56],  $errors[58],  $errors[71],  $errors[74],
			$errors[78],  $errors[115], $errors[119], $errors[141], $errors[153],
			$errors[161], $errors[188], $errors[204], $errors[218], $errors[222],
			$errors[229], $errors[241], $errors[248], $errors[252], $errors[257],
			$errors[262], $errors[270]
		);

		return $errors;
	}

	/**
	 * Returns the lines where warnings should occur.
	 *
	 * @return array <int line number> => <int number of warnings>
	 */
	public function getWarningList() {

		$warnings = array_fill( 282, 19, 1 );

		// Unset the lines related to version comments.
		unset(
			$warnings[289], $warnings[292], $warnings[299]
		);

		return $warnings;
	}

} // End class.

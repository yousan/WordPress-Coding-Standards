<?php
/**
 * Unit test class for WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

/**
 * Unit test class for the ArrayIndentation sniff.
 *
 * @package WPCS\WordPressCodingStandards
 * @since   0.12.0
 */
class WordPress_Tests_Arrays_ArrayIndentationUnitTest extends AbstractSniffUnitTest {

	/**
	 * Returns the lines where errors should occur.
	 *
	 * @return array <int line number> => <int number of errors>
	 */
	public function getErrorList() {
		return array(
			23  => 1,
			24  => 1,
			25  => 1,
			28  => 1,
			29  => 1,
			30  => 1,
			33  => 1,
			34  => 2,
			36  => 1,
			38  => 1,
			39  => 1,
			40  => 1,
			44  => 1,
			45  => 1,
			46  => 1,
			50  => 1,
			51  => 1,
			52  => 1,
			55  => 1,
			56  => 1,
			57  => 1,
			58  => 1,
			60  => 1,
			61  => 1,
			66  => 1,
			80  => 1,
			96  => 1,
			97  => 1,
			98  => 1,
			104 => 1,
			105 => 1,
			106 => 1,
			113 => 1,
			114 => 1,
			115 => 1,
			125 => 1,
			126 => 1,
			127 => 1,
			133 => 1,
			134 => 1,
			135 => 1,
			144 => 1,
			145 => 1,
			146 => 1,
			149 => 1,
			150 => 1,
			168 => 1,
			181 => 1,
			185 => 1,
			192 => 1,
			201 => 1,
			202 => 1,
			234 => 1,
			235 => 1,
			236 => 1,
			237 => 1,
			238 => 1,
			243 => 1,
			244 => 1,
			245 => 1,
			246 => 1,
			247 => 1,
			252 => 1,
			254 => 1,
			256 => 1,
			262 => 1,
			263 => 1,
			269 => 1,
			270 => 1,
			276 => 1,
			277 => 1,
			283 => 1,
			284 => 1,
			290 => 1,
			291 => 1,
			298 => 1,
			299 => 1,
			306 => 1,
			307 => 1,
			314 => 1,
			315 => 1,
		);
	}

	/**
	 * Returns the lines where warnings should occur.
	 *
	 * @return array <int line number> => <int number of warnings>
	 */
	public function getWarningList() {
		return array();

	}

} // End class.

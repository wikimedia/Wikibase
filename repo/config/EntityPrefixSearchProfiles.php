<?php

/**
 * Profile defining weights for query matching options.
 * any - match in any language
 * lang-exact - exact match in specific language
 * lang-folded - casefolded/asciifolded match in specific language
 * lang-prefix - prefix match in specific language
 * fallback-exact - exact match in fallback language
 * fallback-folded - casefolded/asciifolded match in fallback language
 * fallback-prefix - prefix match in fallback language
 * fallback-discount - multiplier for each following fallback
 */
return [
	// FIXME: right now these weights are completely arbitrary. We need
	// to do some work to validate them.
	'default' => [
		'any' => 0.01,
		'lang-exact' => 2.5,
		'lang-folded' => 1.1,
		'lang-prefix' => 0.9,
		'fallback-exact' => 1.7,
		'fallback-folded' => 1.1,
		'fallback-prefix' => 0.9,
		'fallback-discount' => 0.9,
	]
];

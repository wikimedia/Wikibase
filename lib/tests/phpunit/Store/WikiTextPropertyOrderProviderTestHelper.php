<?php

namespace Wikibase\Lib\Tests\Store;

/**
 * Helper class for tests for WikiTextPropertyOrderProvider subclasses.
 *
 * @license GPL-2.0+
 * @author Lucie-Aimée Kaffee
 * @author Marius Hoch
 */
class WikiTextPropertyOrderProviderTestHelper {

	public static function provideGetPropertyOrder() {
		return [
			'empty page' => [
				'',
				[]
			],
			'syntax that is not accepted' => [
				"*\nP1\n"
				. "* P2P\n"
				. "# P3\n"
				. " * P4\n"
				. "* Property:P5\n"
				. "* [[d:P6]]\n"
				. "* d:P7\n"
				. "* {{p|8}}\n"
				. "* {{P|P9}}\n"
				. "* {{Q|P10}}\n"
				. "* {{P|Q11}}",
				[]
			],

			'simple match' => [
				"* P1 \n"
				. "*P133 \n"
				. "* p5", // Testing for lower case property IDs
				[ 'P1' => 0, 'P133' => 1, 'P5' => 2 ]
			],
			'strip multiline comment' => [
				"* P1 \n"
				. "<!-- * P133 \n"
				. "* P5 -->",
				[ 'P1' => 0 ]
			],
			'muliple comments' => [
				"* P1 \n"
				. "<!-- * P133 --> \n"
				. "* <!-- P5 -->",
				[ 'P1' => 0 ]
			],
			'bullet point glibberish' => [
				"* P1 \n"
				. "* P133 \n"
				. "* P5 Unicorns are all \n"
				. "*  very beautiful!\n"
				. "** This is a subheading",
				[ 'P1' => 0, 'P133' => 1, 'P5' => 2 ]
			],
			'additional text' => [
				"* P1 \n"
				. "* P133 \n"
				. "* P5 Unicorns are all \n"
				. "very beautiful!",
				[ 'P1' => 0, 'P133' => 1, 'P5' => 2 ]
			],
			'wiki links' => [
				"*\t[[Property:P9]]\n"
				. "* [[Property:P8|P1008]]\n"
				. "* [[d:Property:P7]]\n"
				. "* [[Q6|P1006]]",
				[ 'P9' => 0, 'P8' => 1, 'P7' => 2 ]
			],
		];
	}

}

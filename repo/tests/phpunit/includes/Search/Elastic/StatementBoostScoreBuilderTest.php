<?php
namespace Wikibase\Repo\Search\Elastic\Tests;

use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\FunctionScoreDecorator;
use CirrusSearch\Search\SearchContext;
use MediaWikiTestCase;
use Wikibase\Repo\Search\Elastic\StatementBoostScoreBuilder;

/**
 * @covers \Wikibase\Repo\Search\Elastic\StatementBoostScoreBuilder
 */
class StatementBoostScoreBuilderTest extends MediaWikiTestCase {

	public function statementBoostProvider() {
		return [
			"one statement" => [
				1.5,
				[ 'P31=Q123' => 2 ],
				[[
					'weight' => 3.0,
					'filter' => [ 'term' => [ 'statement_keywords' => 'P31=Q123' ] ]
				]]
			],
			"nothing" => [
				2,
				[],
				[]
			],
			"multiple statements" => [
				0.1,
				[ 'P31=Q1234' => -2, 'P279=Q345' => -7 ],
				[
					[
					 'weight' => -0.2,
					 'filter' => [ 'term' => [ 'statement_keywords' => 'P31=Q1234' ] ]
					],
					[
						'weight' => -0.7,
						'filter' => [ 'term' => [ 'statement_keywords' => 'P279=Q345' ] ]
					],
				]

			],
		];
	}

	/**
	 * @dataProvider statementBoostProvider
	 * @param float $weight
	 * @param array $settings
	 * @param array $functions
	 */
	public function testStatementBoosts( $weight, $settings, $functions ) {
		$config = new HashSearchConfig( [] );
		$context = new SearchContext( $config, null );
		$builder = new StatementBoostScoreBuilder( $context, $weight, $settings );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$array = $fScore->toArray();
		if ( empty( $functions ) ) {
			$this->assertTrue( $fScore->isEmptyFunction() );
		} else {
			$this->assertFalse( $fScore->isEmptyFunction() );
			$this->assertEquals( $functions, $array['function_score']['functions'] );
		}
	}

}

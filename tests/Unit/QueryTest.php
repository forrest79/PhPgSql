<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Unit;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Tests;
use Tester;

require_once __DIR__ . '/../TestCase.php';

/**
 * @testCase
 */
final class QueryTest extends Tests\TestCase
{

	public function testPrepareQuery(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table')->createQuery();
		Tester\Assert::same('SELECT * FROM table', $query->getSql());
		Tester\Assert::same([], $query->getParams());
	}


	public function testPrepareQueryWithParams(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = $1', 1)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([1], $query->getParams());

		$query = Db\Sql\Query::createArgs('SELECT * FROM table WHERE column = $1', [1])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([1], $query->getParams());

		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ?', 1)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([1], $query->getParams());

		$query = Db\Sql\Query::createArgs('SELECT * FROM table WHERE column = ?', [1])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([1], $query->getParams());
	}


	public function testPrepareQueryWithMissingParam(): void
	{
		Tester\Assert::exception(static function (): void {
			$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ? AND column2 = ?', 1)->createQuery();
			$query->getSql();
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::MISSING_PARAM);
	}


	public function testPrepareQueryWithExtraParam(): void
	{
		Tester\Assert::exception(static function (): void {
			$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ?', 1, 2)->createQuery();
			$query->getSql();
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::EXTRA_PARAM);
	}


	public function testPrepareQueryWithLiteral(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM ? WHERE column = ?', Db\Sql\Literal::create('table'), 1)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([1], $query->getParams());
	}


	public function testPrepareQueryWithLiteralWithParams(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM ? WHERE column = ?', Db\Sql\Expression::create('function(?, ?)', 'param1', 2), 1)->createQuery();
		Tester\Assert::same('SELECT * FROM function($1, $2) WHERE column = $3', $query->getSql());
		Tester\Assert::same(['param1', 2, 1], $query->getParams());
	}


	public function testPrepareQueryWithArray(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column IN (?)', [1, 2])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column IN ($1, $2)', $query->getSql());
		Tester\Assert::same([1, 2], $query->getParams());
	}


	public function testPrepareQueryWithArrayNotList(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column1 = ? AND column2 IN (?)', 3, [10 => 2, 20 => 1])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column1 = $1 AND column2 IN ($2, $3)', $query->getSql());
		Tester\Assert::same([3, 2, 1], $query->getParams());
	}


	public function testPrepareQueryWithBlankArray(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column IN (?)', [])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column IN ()', $query->getSql());
		Tester\Assert::same([], $query->getParams());
	}


	public function testPrepareQueryWithArrayAsOneAnyParameter(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ANY(?)', Db\Helper::createPgArray([1, 2]))->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = ANY($1)', $query->getSql());
		Tester\Assert::same(['{1,2}'], $query->getParams());
	}


	public function testPrepareQueryWithQuery(): void
	{
		$subquery = Db\Sql\Query::create('SELECT id FROM subtable WHERE column = ?', 1);
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE id IN (?)', $subquery)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE id IN (SELECT id FROM subtable WHERE column = $1)', $query->getSql());
		Tester\Assert::same([1], $query->getParams());
	}


	public function testPrepareQueryWithEnum(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ?', Tests\TestEnum::One)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1', $query->getSql());
		Tester\Assert::same([Tests\TestEnum::One->value], $query->getParams());
	}


	public function testPrepareQueryWithArrayOfEnums(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column IN (?)', [Tests\TestEnum::Two, Tests\TestEnum::One])->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column IN ($1, $2)', $query->getSql());
		Tester\Assert::same([Tests\TestEnum::Two->value, Tests\TestEnum::One->value], $query->getParams());
	}


	public function testPrepareQueryEscapeQuestionmark(): void
	{
		$query = Db\Sql\Query::create('SELECT * FROM table WHERE column = ? AND text ILIKE \'What\?\'', 1)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1 AND text ILIKE \'What?\'', $query->getSql());
		Tester\Assert::same([1], $query->getParams());
	}


	public function testPrepareQueryCondition(): void
	{
		$subquery = Db\Sql\Query::create(
			'SELECT id FROM subtable WHERE when = ? AND text ILIKE \'When\?\' AND year > ?',
			Db\Sql\Literal::create('now()'),
			2005,
		);
		$query = Db\Sql\Query::create(
			'SELECT * FROM table WHERE column = ? OR id IN (?) OR type IN (?)',
			'yes',
			$subquery,
			[3, 2, 1],
		)->createQuery();
		Tester\Assert::same('SELECT * FROM table WHERE column = $1 OR id IN (SELECT id FROM subtable WHERE when = now() AND text ILIKE \'When?\' AND year > $2) OR type IN ($3, $4, $5)', $query->getSql());
		Tester\Assert::same(['yes', 2005, 3, 2, 1], $query->getParams());
	}


	public function testPrepareStatementQueryAndParams(): void
	{
		$preparedStatementClass = new class extends Db\PreparedStatementHelper
		{

			public function __construct()
			{
			}


			public function publicPrepareQuery(string $query): string
			{
				return self::prepareQuery($query);
			}


			/**
			 * @param list<mixed> $params
			 * @return list<mixed>
			 */
			public function publicPrepareParams(array $params): array
			{
				return self::prepareParams($params);
			}

		};

		$query = $preparedStatementClass->publicPrepareQuery('SELECT * FROM table WHERE column = ? AND text ILIKE \'What\?\'');
		Tester\Assert::same('SELECT * FROM table WHERE column = $1 AND text ILIKE \'What?\'', $query);

		$params = $preparedStatementClass->publicPrepareParams([NULL, 1, TRUE, FALSE, 'test']);
		Tester\Assert::same([NULL, 1, 'TRUE', 'FALSE', 'test'], $params);
	}

}

(new QueryTest())->run();

<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Unit;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Fluent;
use Forrest79\PhPgSql\Tests;
use Tester;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class FluentQueryTest extends Tests\TestCase
{

	public function testSelect(): void
	{
		$query = $this->query()
			->select(['column'])
			->distinct()
			->from('table', 't')
			->where('column', 100)
			->where('text', NULL)
			->groupBy('column')
			->orderBy('column')
			->limit(10)
			->offset(20)
			->toDbQuery();

		Tester\Assert::same('SELECT DISTINCT column FROM table AS t WHERE (column = $1) AND (text IS NULL) GROUP BY column ORDER BY column LIMIT $2 OFFSET $3', $query->sql);
		Tester\Assert::same([100, 10, 20], $query->params);
	}


	public function testSelectDistinctOn(): void
	{
		$query = $this->query()
			->select(['t.column'])
			->distinctOn('t.column')
			->from('table', 't')
			->where('t.column', 100)
			->toDbQuery();

		Tester\Assert::same('SELECT DISTINCT ON (t.column) t.column FROM table AS t WHERE t.column = $1', $query->sql);
		Tester\Assert::same([100], $query->params);
	}


	public function testSelectCombineDistinctAndDistinctOn(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->select(['t.column'])
				->distinct()
				->distinctOn('t.column')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::CANT_COMBINE_DISTINCT_AND_DISTINCT_ON);
	}


	public function testSelectWithFluentQuery(): void
	{
		$query = $this->query()
			->select(['column' => $this->query()->select([1])])
			->toDbQuery();

		Tester\Assert::same('SELECT (SELECT 1) AS "column"', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectWithQuery(): void
	{
		$query = $this->query()
			->select(['column' => Db\Sql\Query::create('SELECT 1')])
			->toDbQuery();

		Tester\Assert::same('SELECT (SELECT 1) AS "column"', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectColumnAliases(): void
	{
		$query = $this->query()
			->select(['column' => 'another', 'next', 10 => 'column_with_integer_key', '1' => 'column_with_integer_in_string_key', 'a' => 'b'])
			->toDbQuery();

		Tester\Assert::same('SELECT another AS "column", next, column_with_integer_key, column_with_integer_in_string_key, b AS "a"', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectBoolNull(): void
	{
		$query = $this->query()
			->select(['is_true' => TRUE, 'is_false' => FALSE, 'is_null' => NULL])
			->toDbQuery();

		Tester\Assert::same('SELECT TRUE AS "is_true", FALSE AS "is_false", NULL AS "is_null"', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectEnum(): void
	{
		$query = $this->query()
			->select([Tests\TestEnum::One, 'column' => Tests\TestEnum::One])
			->toDbQuery();

		Tester\Assert::same('SELECT 1, 1 AS "column"', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testFromWithFluentQuery(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from($this->query()->select(['column' => 1]), 'x')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM (SELECT 1 AS "column") AS x', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testFromWithQuery(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from(Db\Sql\Query::create('SELECT 1 AS column'), 'x')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM (SELECT 1 AS column) AS x', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testFromWithParameter(): void
	{
		$query = $this->query()
			->select(['gs'])
			->from(Db\Sql\Expression::create('generate_series(?::integer, ?::integer, ?::integer)', 2, 1, -1), 'gs')
			->toDbQuery();

		Tester\Assert::same('SELECT gs FROM generate_series($1::integer, $2::integer, $3::integer) AS gs', $query->sql);
		Tester\Assert::same([2, 1, -1], $query->params);
	}


	public function testMoreFrom(): void
	{
		$query = $this->query()
			->select(['column'])
			->from('table1', 't1')
			->from('table2', 't2')
			->toDbQuery();

		Tester\Assert::same('SELECT column FROM table1 AS t1, table2 AS t2', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testWhereSimple(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->where('x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE x.column = t.id', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testWhereParameters(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->where('x.column', 1)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE x.column = $1', $query->sql);
		Tester\Assert::same([1], $query->params);
	}


	public function testWhereWithCondition(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.column = t.id')
			->add('x.id', [1, 2]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->where($condition)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE (x.column = t.id) AND (x.id IN ($1, $2))', $query->sql);
		Tester\Assert::same([1, 2], $query->params);
	}


	public function testWhereWithSql(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->where(Db\Sql\Expression::create('x.id', [1, 2]))
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE x.id IN ($1, $2)', $query->sql);
		Tester\Assert::same([1, 2], $query->params);
	}


	public function testWhereWithBadTypeWithParameters(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()->where(Db\Sql\Expression::create('x.column = ?'), 1);
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::ONLY_STRING_CONDITION_CAN_HAVE_PARAMS);
	}


	public function testWhereIf(): void
	{
		$queryWithTrueIfCondition = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->whereIf(TRUE, 'x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE x.column = t.id', $queryWithTrueIfCondition->sql);
		Tester\Assert::same([], $queryWithTrueIfCondition->params);

		$queryWithFalseIfCondition = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->whereIf(FALSE, 'x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t', $queryWithFalseIfCondition->sql);
		Tester\Assert::same([], $queryWithFalseIfCondition->params);
	}


	public function testWhereAnd(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.type = t.id')
			->add('x.test', [3, 5]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->whereAnd([
				'x.column = t.id',
				['x.column', 1],
				$condition,
				Db\Sql\Expression::create('x.id', 7),
			])
			->query()
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE (x.column = t.id) AND (x.column = $1) AND ((x.type = t.id) AND (x.test IN ($2, $3))) AND (x.id = $4)', $query->sql);
		Tester\Assert::same([1, 3, 5, 7], $query->params);
	}


	public function testWhereAndContinue(): void
	{
		$sourceQuery = $this->query()
			->select(['x.column'])
			->from('table', 't');

		$condition = $sourceQuery->whereAnd([
			'x.column = t.id',
			Db\Sql\Expression::create('x.id', 7),
		]);

		$condition
			->add(Fluent\Condition::createAnd()->add('x.type = t.id')->add('x.test', [3, 5]))
			->add('x.column', 1);

		$query = $sourceQuery->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE (x.column = t.id) AND (x.id = $1) AND ((x.type = t.id) AND (x.test IN ($2, $3))) AND (x.column = $4)', $query->sql);
		Tester\Assert::same([7, 3, 5, 1], $query->params);
	}


	public function testWhereOr(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.type = t.id')
			->add('x.test', [3, 5]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->whereOr([
				'x.column = t.id',
				['x.column', 1],
				$condition,
				Db\Sql\Expression::create('x.id', 7),
			])
			->query()
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t WHERE (x.column = t.id) OR (x.column = $1) OR ((x.type = t.id) AND (x.test IN ($2, $3))) OR (x.id = $4)', $query->sql);
		Tester\Assert::same([1, 3, 5, 7], $query->params);
	}


	public function testHavingSimple(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->having('x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING x.column = t.id', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testHavingParameters(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->having('x.column', 1)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING x.column = $1', $query->sql);
		Tester\Assert::same([1], $query->params);
	}


	public function testHavingWithCondition(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.column = t.id')
			->add('x.id', [1, 2]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->having($condition)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING (x.column = t.id) AND (x.id IN ($1, $2))', $query->sql);
		Tester\Assert::same([1, 2], $query->params);
	}


	public function testHavingWithSql(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->having(Db\Sql\Expression::create('x.id', [1, 2]))
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING x.id IN ($1, $2)', $query->sql);
		Tester\Assert::same([1, 2], $query->params);
	}


	public function testHavingWithBadTypeWithParameters(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()->having(Db\Sql\Expression::create('x.column = ?'), 1);
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::ONLY_STRING_CONDITION_CAN_HAVE_PARAMS);
	}


	public function testHavingAnd(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.type = t.id')
			->add('x.test', [3, 5]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->havingAnd([
				'x.column = t.id',
				['x.column', 1],
				$condition,
				Db\Sql\Expression::create('x.id', 7),
			])
			->query()
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING (x.column = t.id) AND (x.column = $1) AND ((x.type = t.id) AND (x.test IN ($2, $3))) AND (x.id = $4)', $query->sql);
		Tester\Assert::same([1, 3, 5, 7], $query->params);
	}


	public function testHavingOr(): void
	{
		$condition = Fluent\Condition::createAnd()
			->add('x.type = t.id')
			->add('x.test', [3, 5]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->havingOr([
				'x.column = t.id',
				['x.column', 1],
				$condition,
				Db\Sql\Expression::create('x.id', 7),
			])
			->query()
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING (x.column = t.id) OR (x.column = $1) OR ((x.type = t.id) AND (x.test IN ($2, $3))) OR (x.id = $4)', $query->sql);
		Tester\Assert::same([1, 3, 5, 7], $query->params);
	}


	public function testHavingOrContinue(): void
	{
		$sourceQuery = $this->query()
			->select(['x.column'])
			->from('table', 't');

		$condition = $sourceQuery->havingOr([
			'x.column = t.id',
			Db\Sql\Expression::create('x.id', 7),
		]);

		$condition
			->add(Fluent\Condition::createAnd()->add('x.type = t.id')->add('x.test', [3, 5]))
			->add('x.column', 1);

		$query = $sourceQuery
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t HAVING (x.column = t.id) OR (x.id = $1) OR ((x.type = t.id) AND (x.test IN ($2, $3))) OR (x.column = $4)', $query->sql);
		Tester\Assert::same([7, 3, 5, 1], $query->params);
	}


	public function testJoinWithFluentQuery(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join($this->query()->select(['column' => 1]), 'x', 'x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN (SELECT 1 AS "column") AS x ON x.column = t.id', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testJoinWithQuery(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join(Db\Sql\Query::create('SELECT 1 AS column'), 'x', 'x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN (SELECT 1 AS column) AS x ON x.column = t.id', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testJoinWithStringOn(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join('another', 'x', 'x.column = t.id')
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another AS x ON x.column = t.id', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testJoinWithConditionOn(): void
	{
		$conditionOn = Fluent\Condition::createAnd()
			->add('x.column = t.id')
			->add('x.id', [1, 2]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join('another', 'x', $conditionOn)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another AS x ON (x.column = t.id) AND (x.id IN ($1, $2))', $query->sql);
		Tester\Assert::same([1, 2], $query->params);
	}


	public function testJoinWithSqlOn(): void
	{
		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join('another', 'x', Db\Sql\Expression::create('(x.column = t.id) AND (x.id = ?)', 2))
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another AS x ON (x.column = t.id) AND (x.id = $1)', $query->sql);
		Tester\Assert::same([2], $query->params);
	}


	public function testJoinWithAddOn(): void
	{
		$conditionOn = Fluent\Condition::createOr()
			->add('x.condition_id = t.id')
			->add('x.condition_id', [3, 4]);

		$query = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join('another', 'x', 'x.column = t.id')
				->on('x', 'x.id = 1')
				->on('x', 'x.id = ?', 2)
				->on('x', Db\Sql\Expression::create('x.type_id = ?', 'test'))
				->on('x', $conditionOn)
			->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another AS x ON (x.column = t.id) AND (x.id = 1) AND (x.id = $1) AND (x.type_id = $2) AND ((x.condition_id = t.id) OR (x.condition_id IN ($3, $4)))', $query->sql);
		Tester\Assert::same([2, 'test', 3, 4], $query->params);
	}


	public function testJoinWithBadTypeWithParameters(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()->on('x', Db\Sql\Expression::create('x.column = ?'), 1);
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::ONLY_STRING_CONDITION_CAN_HAVE_PARAMS);
	}


	public function testJoinNoOn(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->select(['x.column'])
				->from('table', 't')
				->join('another', 'x')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_ON_CONDITION);
	}


	public function testLateralFrom(): void
	{
		$query = $this->query()
			->select(['t1.column1', 't2.column2'])
			->from('table1', 't1')
			->from($this->query()->select(['column2'])->from('table2'), 't2')
			->lateral('t2')
			->toDbQuery();

		Tester\Assert::same('SELECT t1.column1, t2.column2 FROM table1 AS t1, LATERAL (SELECT column2 FROM table2) AS t2', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testLateralJoinWithSqlQuery(): void
	{
		$query = $this->query()
			->select(['column1' => 't1.column', 'column2' => 't2.column'])
			->from('table1', 't1')
			->join(Db\Sql\Query::create('SELECT column FROM table2'), 't2', 't2.column = t1.column')
			->lateral('t2')
			->toDbQuery();

		Tester\Assert::same('SELECT t1.column AS "column1", t2.column AS "column2" FROM table1 AS t1 INNER JOIN LATERAL (SELECT column FROM table2) AS t2 ON t2.column = t1.column', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testLateralJoinWithFluentQuery(): void
	{
		$query = $this->query()
			->select(['column1' => 't1.column', 'column2' => 't2.column'])
			->from('table1', 't1')
			->join($this->query()->select(['column'])->from('table2'), 't2', 't2.column = t1.column')
			->lateral('t2')
			->toDbQuery();

		Tester\Assert::same('SELECT t1.column AS "column1", t2.column AS "column2" FROM table1 AS t1 INNER JOIN LATERAL (SELECT column FROM table2) AS t2 ON t2.column = t1.column', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectCombine(): void
	{
		$query = $this->query()
			->from('table', 't')
			->select(['column'])
			->union('SELECT column FROM table2 AS t2')
			->toDbQuery();

		Tester\Assert::same('(SELECT column FROM table AS t) UNION (SELECT column FROM table2 AS t2)', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectCombineFluent(): void
	{
		$query = $this->query()
			->from('table', 't')
			->select(['column'])
			->union(
				$this->query()
					->select(['column'])
					->from('table2', 't2'),
			)
			->toDbQuery();

		Tester\Assert::same('(SELECT column FROM table AS t) UNION (SELECT column FROM table2 AS t2)', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectCombineQuery(): void
	{
		$query = $this->query()
			->from('table', 't')
			->select(['column'])
			->union(Db\Sql\Query::create('SELECT column FROM table2 AS t2'))
			->toDbQuery();

		Tester\Assert::same('(SELECT column FROM table AS t) UNION (SELECT column FROM table2 AS t2)', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSelectNoColumns(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->from('table')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_COLUMNS_TO_SELECT);
	}


	public function testOrderByFluent(): void
	{
		$query = $this->query()
			->select(['column'])
			->from('table', 't')
			->orderBy($this->query()->select(['sort_by_value(column)']))
			->toDbQuery();

		Tester\Assert::same('SELECT column FROM table AS t ORDER BY (SELECT sort_by_value(column))', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testOrderByQuery(): void
	{
		$query = $this->query()
			->select(['column'])
			->from('table', 't')
			->orderBy(Db\Sql\Expression::create('sort_by_value(column)'))
			->toDbQuery();

		Tester\Assert::same('SELECT column FROM table AS t ORDER BY sort_by_value(column)', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testInsertRow(): void
	{
		$query = $this->query()
			->values([
				'column' => 1,
				'column_from' => Db\Sql\Literal::create('3'),
				'column_fluent_query' => $this->query()->select(['\'test_fluent\''])->where('4', 4),
				'column_query' => Db\Sql\Query::create('SELECT \'test\' WHERE 5 = ?', 5),
			])
			->insert('table')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column, column_from, column_fluent_query, column_query) VALUES($1, 3, (SELECT \'test_fluent\' WHERE 4 = $2), (SELECT \'test\' WHERE 5 = $3)) RETURNING column', $query->sql);
		Tester\Assert::same([1, 4, 5], $query->params);
	}


	public function testInsertMergeData(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'column1' => 3,
				'column2' => -2, // this will be ignored, last added column have bigger priority
			])
			->values([
				'column2' => 2,
				'column3' => 1,
			])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column2, column3, column1) VALUES($1, $2, $3)', $query->sql);
		Tester\Assert::same([2, 1, 3], $query->params);
	}


	public function testInsertRows(): void
	{
		$query = $this->query()
			->rows([
				['column' => 1],
				['column' => 2],
				['column' => 3],
				['column' => Db\Sql\Literal::create('4')],
				['column' => $this->query()->select(['\'test_fluent\''])->where('6', 6)],
				['column' => Db\Sql\Query::create('SELECT \'test\' WHERE 7 = ?', 7)],
			])
			->insert('table')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column) VALUES($1), ($2), ($3), (4), ((SELECT \'test_fluent\' WHERE 6 = $4)), ((SELECT \'test\' WHERE 7 = $5)) RETURNING column', $query->sql);
		Tester\Assert::same([1, 2, 3, 6, 7], $query->params);
	}


	public function testInsertRowsMergeData(): void
	{
		$query = $this->query()
			->rows([
				['column' => 1],
				['column' => 2],
			])
			->rows([
				['column' => 3],
				['column' => 4],
			])
			->insert('table')
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column) VALUES($1), ($2), ($3), ($4)', $query->sql);
		Tester\Assert::same([1, 2, 3, 4], $query->params);
	}


	public function testInsertSelect(): void
	{
		$query = $this->query()
			->insert('table', columns: ['name'])
			->select(['column'])
			->from('table2', 't2')
			->returning(['name'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name) SELECT column FROM table2 AS t2 RETURNING name', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testInsertSelectDetectColumnsFromSelect(): void
	{
		$query = $this->query()
			->insert('table')
			->select(['column', 'name' => 'column2'])
			->from('table2', 't2')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column, name) SELECT column, column2 AS "name" FROM table2 AS t2 RETURNING column', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testInsertSelectAllColumns(): void
	{
		$query = $this->query()
			->insert('table1')
			->select(['*'])
			->from('table2')
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table1 SELECT * FROM table2', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testInsertSelectNoColumn(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table1')
				->select([])
				->from('table2')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_DATA_TO_INSERT);
	}


	public function testInsertSelectMissingColumnAlias(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table1')
				->select([1])
				->from('table2')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::MISSING_COLUMN_ALIAS);

		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table1')
				->select([Tests\TestEnum::One])
				->from('table2')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::MISSING_COLUMN_ALIAS);
	}


	public function testInsertOnConflictDoUpdate(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'info' => 'Text',
			])
			->onConflict(['name'])
			->doUpdate(['info'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, info) VALUES($1, $2) ON CONFLICT (name) DO UPDATE SET info = EXCLUDED.info', $query->sql);
		Tester\Assert::same(['Bob', 'Text'], $query->params);
	}


	public function testInsertOnConflictWithWhereDoUpdate(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'age' => 20,
				'info' => 'Text',
			])
			->onConflict(['name', 'age'], Fluent\Condition::createAnd()->add('age < ?', 30))
			->doUpdate(['info'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, age, info) VALUES($1, $2, $3) ON CONFLICT (name, age) WHERE age < $4 DO UPDATE SET info = EXCLUDED.info', $query->sql);
		Tester\Assert::same(['Bob', 20, 'Text', 30], $query->params);
	}


	public function testInsertOnConflictConstraintDoUpdate(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'age' => 20,
				'info' => 'Text',
			])
			->onConflict('name_ukey')
			->doUpdate(['info'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, age, info) VALUES($1, $2, $3) ON CONFLICT ON CONSTRAINT name_ukey DO UPDATE SET info = EXCLUDED.info', $query->sql);
		Tester\Assert::same(['Bob', 20, 'Text'], $query->params);
	}


	public function testInsertOnConflictConstraintWithWhereDoUpdate(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->values([
					'name' => 'Bob',
					'age' => 20,
					'info' => 'Text',
				])
				->onConflict('name_ukey', Fluent\Condition::createAnd()->add('age < ?', 30))
				->doUpdate(['info'])
				->toDbQuery();
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::ON_CONFLICT_WHERE_NOT_FOR_CONSTRAINT);
	}


	public function testInsertOnConflictDoUpdateWithConditionSet(): void
	{
		$query = $this->query()
			->insert('table', 't')
			->values([
				'name' => 'Bob',
				'info' => 'Text',
			])
			->onConflict(['name'])
			->doUpdate(['info', 'name' => 'EXCLUDED.name || t.age'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table AS t (name, info) VALUES($1, $2) ON CONFLICT (name) DO UPDATE SET info = EXCLUDED.info, name = EXCLUDED.name || t.age', $query->sql);
		Tester\Assert::same(['Bob', 'Text'], $query->params);
	}


	public function testInsertOnConflictDoUpdateWithExpressionSet(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'info' => 'Text',
			])
			->onConflict(['name'])
			->doUpdate(['info', 'name' => Db\Sql\Expression::create('EXCLUDED.name || ?', 'Jimmy')])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, info) VALUES($1, $2) ON CONFLICT (name) DO UPDATE SET info = EXCLUDED.info, name = EXCLUDED.name || $3', $query->sql);
		Tester\Assert::same(['Bob', 'Text', 'Jimmy'], $query->params);
	}


	public function testInsertOnConflictDoUpdateWithWhere(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'age' => 20,
				'info' => 'Text',
			])
			->onConflict(['name', 'age'])
			->doUpdate(['info'], Fluent\Condition::createAnd()->add('age < ?', 30))
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, age, info) VALUES($1, $2, $3) ON CONFLICT (name, age) DO UPDATE SET info = EXCLUDED.info WHERE age < $4', $query->sql);
		Tester\Assert::same(['Bob', 20, 'Text', 30], $query->params);
	}


	public function testInsertOnConflictDoNothing(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'info' => 'Text',
			])
			->onConflict()
			->doNothing()
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, info) VALUES($1, $2) ON CONFLICT DO NOTHING', $query->sql);
		Tester\Assert::same(['Bob', 'Text'], $query->params);
	}


	public function testInsertOnConflictWithReturning(): void
	{
		$query = $this->query()
			->insert('table')
			->values([
				'name' => 'Bob',
				'info' => 'Text',
			])
			->onConflict(['name'])
			->doUpdate(['info'])
			->returning(['id'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (name, info) VALUES($1, $2) ON CONFLICT (name) DO UPDATE SET info = EXCLUDED.info RETURNING id', $query->sql);
		Tester\Assert::same(['Bob', 'Text'], $query->params);
	}


	public function testInsertOnConflictDoUpdateWithoutOnConflictDefinition(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->values([
					'name' => 'Bob',
					'age' => 20,
					'info' => 'Text',
				])
				->doUpdate(['info'])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::ON_CONFLICT_DO_WITHOUT_DEFINITION);
	}


	public function testInsertOnConflictWithoutDoStatement(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->values([
					'name' => 'Bob',
					'age' => 20,
					'info' => 'Text',
				])
				->onConflict(['name'])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::ON_CONFLICT_NO_DO);
	}


	public function testInsertOnConflictDoUpdateWithExpressionAsSimpleColumn(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->values([
					'name' => 'Bob',
					'age' => 20,
					'info' => 'Text',
				])
				->onConflict(['name'])
				->doUpdate([Db\Sql\Expression::create('EXCLUDED.name || ?', 'Jimmy')])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::ON_CONFLICT_DO_UPDATE_SET_SINGLE_COLUMN_CAN_BE_ONLY_STRING);
	}


	public function testInsertSelectAllColumnsWithConcrete(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table1')
				->select(['*', 'id'])
				->from('table2')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::SELECT_ALL_COLUMNS_CANT_BE_COMBINED_WITH_CONCRETE_COLUMN_FOR_INSERT_SELECT_WITH_COLUMN_DETECTION);
	}


	public function testInsertRowWithArray(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->values([
					'column' => [1, 2],
				])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::DATA_CANT_CONTAIN_ARRAY);
	}


	public function testInsertRowsWithArray(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->rows([
					['column' => [1, 2]],
				])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::DATA_CANT_CONTAIN_ARRAY);
	}


	public function testInsertNoData(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->insert('table')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_DATA_TO_INSERT);
	}


	public function testUpdate(): void
	{
		$query = $this->query()
			->update('table', 't')
			->set([
				'column' => 1,
				'column_from' => Db\Sql\Literal::create('t2.id'),
				'column_fluent_query' => $this->query()->select(['\'test_fluent\''])->where('2', 2),
				'column_query' => Db\Sql\Query::create('SELECT \'test\' WHERE 3 = ?', 3),
			])
			->from('table2', 't2')
			->where('t2.column', 100)
			->returning(['t.column'])
			->toDbQuery();

		Tester\Assert::same('UPDATE table AS t SET column = $1, column_from = t2.id, column_fluent_query = (SELECT \'test_fluent\' WHERE 2 = $2), column_query = (SELECT \'test\' WHERE 3 = $3) FROM table2 AS t2 WHERE t2.column = $4 RETURNING t.column', $query->sql);
		Tester\Assert::same([1, 2, 3, 100], $query->params);
	}


	public function testUpdateMergeData(): void
	{
		$query = $this->query()
			->update('table', 't')
			->set([
				'column1' => 3,
				'column2' => -2, // this will be ignored, last added column have bigger priority
			])
			->set([
				'column2' => 2,
				'column3' => 1,
			])
			->toDbQuery();

		Tester\Assert::same('UPDATE table AS t SET column2 = $1, column3 = $2, column1 = $3', $query->sql);
		Tester\Assert::same([2, 1, 3], $query->params);
	}


	public function testUpdateWithArray(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->update('table')
				->set([
					'column1' => [1, 2],
				])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::DATA_CANT_CONTAIN_ARRAY);
	}


	public function testUpdateNoData(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->update('table')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_DATA_TO_UPDATE);
	}


	public function testNoMainTable(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->update()
				->set(['column' => 1])
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_MAIN_TABLE);
	}


	public function testDelete(): void
	{
		$query = $this->query()
			->delete('table', 't')
			->where('column', 100)
			->returning(['c' => 'column'])
			->toDbQuery();

		Tester\Assert::same('DELETE FROM table AS t WHERE column = $1 RETURNING column AS "c"', $query->sql);
		Tester\Assert::same([100], $query->params);
	}


	public function testReturningFluentQuery(): void
	{
		$query = $this->query()
			->delete('table', 't')
			->where('column', 100)
			->returning(['c' => $this->query()->select(['to_value(column)'])])
			->toDbQuery();

		Tester\Assert::same('DELETE FROM table AS t WHERE column = $1 RETURNING (SELECT to_value(column)) AS "c"', $query->sql);
		Tester\Assert::same([100], $query->params);
	}


	public function testReturningQuery(): void
	{
		$query = $this->query()
			->delete('table', 't')
			->where('column', 100)
			->returning(['c' => Db\Sql\Expression::create('to_value(column)')])
			->toDbQuery();

		Tester\Assert::same('DELETE FROM table AS t WHERE column = $1 RETURNING (to_value(column)) AS "c"', $query->sql);
		Tester\Assert::same([100], $query->params);
	}


	public function testMerge(): void
	{
		$query = $this->query()
			->merge('customer_account', 'ca')
			->using('recent_transactions', 't', 't.customer_id = ca.customer_id')
			->whenMatched('UPDATE SET balance = balance + transaction_value')
			->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO customer_account AS ca USING recent_transactions AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testMergeUsingFluentQuery(): void
	{
		$query = $this->query()
			->merge('customer_account', 'ca')
			->using($this->query()->select(['customer_id', 'transaction_value'])->from('recent_transactions')->where('customer_id > ?', 10), 't', 't.customer_id = ca.customer_id')
			->whenMatched('UPDATE SET balance = balance + transaction_value')
			->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO customer_account AS ca USING (SELECT customer_id, transaction_value FROM recent_transactions WHERE customer_id > $1) AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)', $query->sql);
		Tester\Assert::same([10], $query->params);
	}


	public function testMergeUsingSql(): void
	{
		$query = $this->query()
			->merge('customer_account', 'ca')
			->using(Db\Sql\Query::create('SELECT customer_id, transaction_value FROM recent_transactions WHERE customer_id > ?', 10), 't', 't.customer_id = ca.customer_id')
			->whenMatched('UPDATE SET balance = balance + transaction_value')
			->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO customer_account AS ca USING (SELECT customer_id, transaction_value FROM recent_transactions WHERE customer_id > $1) AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)', $query->sql);
		Tester\Assert::same([10], $query->params);
	}


	public function testMergeOnCondition(): void
	{
		$query = $this->query()
			->merge('customer_account', 'ca')
			->using('(SELECT customer_id, transaction_value FROM recent_transactions)', 't', Fluent\Condition::createAnd()->add('t.customer_id = ca.customer_id')->add('t.customer_id > ?', 10))
			->whenMatched('UPDATE SET balance = balance + transaction_value')
			->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO customer_account AS ca USING (SELECT customer_id, transaction_value FROM recent_transactions) AS t ON (t.customer_id = ca.customer_id) AND (t.customer_id > $1) WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)', $query->sql);
		Tester\Assert::same([10], $query->params);
	}


	public function testMergeWhenOn(): void
	{
		$query = $this->query()
			->merge('wines', 'w')
			->using('wine_stock_changes', 's', 's.winename = w.winename')
			->whenNotMatched('INSERT VALUES(s.winename, s.stock_delta)', 's.stock_delta > 0')
			->whenMatched('UPDATE SET stock = w.stock + s.stock_delta', Fluent\Condition::createAnd()->add('w.stock + s.stock_delta > ?', 0))
			->whenMatched('UPDATE SET stock = w.stock - s.stock_delta', Db\Sql\Expression::create('w.stock + s.stock_delta < ?', 0))
			->whenMatched('DELETE')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO wines AS w USING wine_stock_changes AS s ON s.winename = w.winename WHEN NOT MATCHED AND s.stock_delta > 0 THEN INSERT VALUES(s.winename, s.stock_delta) WHEN MATCHED AND w.stock + s.stock_delta > $1 THEN UPDATE SET stock = w.stock + s.stock_delta WHEN MATCHED AND w.stock + s.stock_delta < $2 THEN UPDATE SET stock = w.stock - s.stock_delta WHEN MATCHED THEN DELETE', $query->sql);
		Tester\Assert::same([0, 0], $query->params);
	}


	public function testMergeDoNothing(): void
	{
		$query = $this->query()
			->merge('wines', 'w')
			->using('wine_stock_changes', 's', 's.winename = w.winename')
			->whenNotMatched('INSERT VALUES(s.winename, s.stock_delta)')
			->whenMatched('DO NOTHING')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO wines AS w USING wine_stock_changes AS s ON s.winename = w.winename WHEN NOT MATCHED THEN INSERT VALUES(s.winename, s.stock_delta) WHEN MATCHED THEN DO NOTHING', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testMergeCommonUpsert(): void
	{
		$query = $this->query()
			->merge('wines', 'w')
			->using('(SELECT 1)', 's', 'w.winename = $1')
			->whenNotMatched(Db\Sql\Query::create('INSERT (winename, balance) VALUES($1, $2)', 'Red wine', 10))
			->whenMatched('UPDATE SET balance = $2')
			->toDbQuery();

		Tester\Assert::same('MERGE INTO wines AS w USING (SELECT 1) AS s ON w.winename = $1 WHEN NOT MATCHED THEN INSERT (winename, balance) VALUES($1, $2) WHEN MATCHED THEN UPDATE SET balance = $2', $query->sql);
		Tester\Assert::same(['Red wine', 10], $query->params);
	}


	public function testMergeNoUsing(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->merge('customer_account', 'ca')
				->whenMatched('UPDATE SET balance = balance + transaction_value')
				->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::MERGE_NO_USING);
	}


	public function testMergeMoreUsings(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->merge('customer_account', 'ca')
				->using('recent_transactions1', 't1')
				->using('recent_transactions2', 't2')
				->whenMatched('UPDATE SET balance = balance + transaction_value')
				->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
				->toDbQuery();
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::MERGE_ONLY_ONE_USING);
	}


	public function testMergeNoOn(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->merge('customer_account', 'ca')
				->using('recent_transactions', 't')
				->whenMatched('UPDATE SET balance = balance + transaction_value')
				->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::NO_ON_CONDITION);
	}


	public function testMergeNoWhen(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->merge('customer_account', 'ca')
				->using('recent_transactions', 't', 't.customer_id = ca.customer_id')
				->toDbQuery();
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::MERGE_NO_WHEN);
	}


	public function testMergeReturning(): void
	{
		$query = $this->query()
			->merge('customer_account', 'ca')
			->using('recent_transactions', 't', 't.customer_id = ca.customer_id')
			->whenMatched('UPDATE SET balance = balance + transaction_value')
			->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
			->returning(['merge_action()', 'ca.*'])
			->toDbQuery();

		Tester\Assert::same('MERGE INTO customer_account AS ca USING recent_transactions AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value) RETURNING merge_action(), ca.*', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testTruncate(): void
	{
		$query = $this->query()->truncate('table')->toDbQuery();
		Tester\Assert::same('TRUNCATE table', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testWith(): void
	{
		$query = $this->query()
			->with('regional_sales', 'SELECT region, SUM(amount) AS total_sales FROM orders GROUP BY region')
			->with('top_regions', Db\Sql\Query::create('SELECT region FROM regional_sales WHERE total_sales > (?) AND total_sales < ?', Db\Sql\Expression::create('SELECT SUM(total_sales) / 10 FROM regional_sales'), 10000))
			->select(['region', 'product', 'product_units' => 'SUM(quantity)', 'product_sales' => 'SUM(amount)'])
			->from('orders')
			->where('region', Db\Sql\Query::create('SELECT region FROM top_regions'))
			->where('region != ?', 'Prague')
			->groupBy('region', 'product')
			->toDbQuery();

		Tester\Assert::same('WITH regional_sales AS (SELECT region, SUM(amount) AS total_sales FROM orders GROUP BY region), top_regions AS (SELECT region FROM regional_sales WHERE total_sales > (SELECT SUM(total_sales) / 10 FROM regional_sales) AND total_sales < $1) SELECT region, product, SUM(quantity) AS "product_units", SUM(amount) AS "product_sales" FROM orders WHERE (region IN (SELECT region FROM top_regions)) AND (region != $2) GROUP BY region, product', $query->sql);
		Tester\Assert::same([10000, 'Prague'], $query->params);
	}


	public function testWithRecursive(): void
	{
		$query = $this->query()
			->with('t(n)', 'VALUES (1) UNION ALL SELECT n + 1 FROM t WHERE n < 100')
			->recursive()
			->select(['sum(n)'])
			->from('t')
			->toDbQuery();

		Tester\Assert::same('WITH RECURSIVE t(n) AS (VALUES (1) UNION ALL SELECT n + 1 FROM t WHERE n < 100) SELECT sum(n) FROM t', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testWithSuffix(): void
	{
		$query = $this->query()
			->with(
				'search_tree(id, link, data)',
				$this->query()
					->select(['t.id', 't.link', 't.data'])
					->from('tree', 't')
					->unionAll(
						$this->query()
							->select(['t.id', 't.link', 't.data'])
							->from('tree', 't')
							->from('search_tree', 'st')
							->where('t.id = st.link'),
					),
				'SEARCH BREADTH FIRST BY id SET ordercol',
			)
			->select(['*'])
			->from('search_tree')
			->orderBy('ordercol')
			->toDbQuery();

		Tester\Assert::same('WITH search_tree(id, link, data) AS ((SELECT t.id, t.link, t.data FROM tree AS t) UNION ALL (SELECT t.id, t.link, t.data FROM tree AS t, search_tree AS st WHERE t.id = st.link)) SEARCH BREADTH FIRST BY id SET ordercol SELECT * FROM search_tree ORDER BY ordercol', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testWithNotMaterialized(): void
	{
		$query = $this->query()
			->with('w', 'SELECT * FROM big_table', notMaterialized: TRUE)
			->select(['*'])
			->from('w', 'w1')
			->join('w', 'w2', 'w1.key = w2.ref')
			->where('w2.key', 123)
			->toDbQuery();

		Tester\Assert::same('WITH w AS NOT MATERIALIZED (SELECT * FROM big_table) SELECT * FROM w AS w1 INNER JOIN w AS w2 ON w1.key = w2.ref WHERE w2.key = $1', $query->sql);
		Tester\Assert::same([123], $query->params);
	}


	public function testWithInsert(): void
	{
		$query = $this->query()
			->with(
				'moved_rows',
				$this->query()
					->delete('products')
					->where('date >= ?', '2010-10-01')
					->where('date < ?', '2010-11-01')
					->returning(['*']),
			)
			->insert('products_log')
			->select(['*'])
			->from('moved_rows')
			->toDbQuery();

		Tester\Assert::same('WITH moved_rows AS (DELETE FROM products WHERE (date >= $1) AND (date < $2) RETURNING *) INSERT INTO products_log SELECT * FROM moved_rows', $query->sql);
		Tester\Assert::same(['2010-10-01', '2010-11-01'], $query->params);
	}


	public function testWithDelete(): void
	{
		$query = $this->query()
			->with(
				'included_parts(sub_part, part)',
				$this->query()
					->select(['sub_part', 'part'])
					->from('parts')
					->where('part', 'our_product')
					->unionAll(
						$this->query()
							->select(['p.sub_part', 'p.part'])
							->from('included_parts', 'pr')
							->from('parts', 'p')
							->where('p.part = pr.sub_part'),
					),
			)
			->delete('parts')
			->where('part', $this->query()->select(['part'])->from('included_parts'))
			->toDbQuery();

		Tester\Assert::same('WITH included_parts(sub_part, part) AS ((SELECT sub_part, part FROM parts WHERE part = $1) UNION ALL (SELECT p.sub_part, p.part FROM included_parts AS pr, parts AS p WHERE p.part = pr.sub_part)) DELETE FROM parts WHERE part IN (SELECT part FROM included_parts)', $query->sql);
		Tester\Assert::same(['our_product'], $query->params);
	}


	public function testWithUpdate(): void
	{
		$query = $this->query()
			->with(
				't',
				$this->query()
					->update('products')
					->set(['price' => Db\Sql\Literal::create('price * 1.05')])
					->returning(['*']),
			)
			->select(['*'])
			->from('t')
			->toDbQuery();

		Tester\Assert::same('WITH t AS (UPDATE products SET price = price * 1.05 RETURNING *) SELECT * FROM t', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testParamsPrefix(): void
	{
		$withQuery = $this->query()
			->select(['columnWith'])
			->from('tableWith')
			->where('columnWith > ?', 5);

		$query = $this->query()
			->select(['column'])
			->from('table')
			->where('column', 100)
			->prefix('WITH cte AS (?)', $withQuery)
			->toDbQuery();

		Tester\Assert::same('WITH cte AS (SELECT columnWith FROM tableWith WHERE columnWith > $1) SELECT column FROM table WHERE column = $2', $query->sql);
		Tester\Assert::same([5, 100], $query->params);
	}


	public function testSimpleSuffix(): void
	{
		$query = $this->query()
			->select(['column'])
			->from('table')
			->where('column', 100)
			->suffix('FOR UPDATE')
			->toDbQuery();

		Tester\Assert::same('SELECT column FROM table WHERE column = $1 FOR UPDATE', $query->sql);
		Tester\Assert::same([100], $query->params);

		$query = $this->query()
			->truncate('table')
			->suffix('CASCADE')
			->toDbQuery();

		Tester\Assert::same('TRUNCATE table CASCADE', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testSuffixWithReturning(): void
	{
		$query = $this->query()
			->insert('table')
			->values(['column' => 'value'])
			->suffix('ON CONFLICT (column) DO NOTHING')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('INSERT INTO table (column) VALUES($1) ON CONFLICT (column) DO NOTHING RETURNING column', $query->sql);
		Tester\Assert::same(['value'], $query->params);

		$query = $this->query()
			->update('table')
			->set(['column' => 'value'])
			->suffix('WHERE CURRENT OF cursor_name')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('UPDATE table SET column = $1 WHERE CURRENT OF cursor_name RETURNING column', $query->sql);
		Tester\Assert::same(['value'], $query->params);

		$query = $this->query()
			->delete('table')
			->suffix('WHERE CURRENT OF cursor_name')
			->returning(['column'])
			->toDbQuery();

		Tester\Assert::same('DELETE FROM table WHERE CURRENT OF cursor_name RETURNING column', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testConditionWhere(): void
	{
		$conditionOr = $this->query()->whereOr();
		$conditionOr->add('column', 1);
		$conditionOr->add('column2', [2, 3]);
		$conditionAnd = $conditionOr->addAndBranch();
		$conditionAnd->add('column', $this->query()->select([1]));
		$conditionAnd->add('column2 = ANY(?)', Db\Sql\Query::create('SELECT 2'));
		$conditionOr->add('column3 IS NOT NULL');

		$query = $conditionOr->query()
			->select(['*'])
			->from('table')
			->toDbQuery();

		Tester\Assert::same('SELECT * FROM table WHERE (column = $1) OR (column2 IN ($2, $3)) OR ((column IN (SELECT 1)) AND (column2 = ANY(SELECT 2))) OR (column3 IS NOT NULL)', $query->sql);
		Tester\Assert::same([1, 2, 3], $query->params);
	}


	public function testConditionHaving(): void
	{
		$conditionOr = $this->query()->havingOr();
		$conditionOr->add('column', 1);
		$conditionOr->add('column2', [2, 3]);
		$conditionAnd = $conditionOr->addAndBranch();
		$conditionAnd->add('column', $this->query()->select([1]));
		$conditionAnd->add('column2 = ANY(?)', Db\Sql\Query::create('SELECT 2'));
		$conditionOr->add('column3 IS NOT NULL');

		$query = $conditionOr->query()
			->select(['*'])
			->from('table')
			->groupBy('column', 'column2')
			->toDbQuery();

		Tester\Assert::same('SELECT * FROM table GROUP BY column, column2 HAVING (column = $1) OR (column2 IN ($2, $3)) OR ((column IN (SELECT 1)) AND (column2 = ANY(SELECT 2))) OR (column3 IS NOT NULL)', $query->sql);
		Tester\Assert::same([1, 2, 3], $query->params);
	}


	public function testConditionBadParams(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->whereOr()
					->add('columns = ? AND column2 = ?', 1, 2, 3)
				->query()
				->select(['*'])
				->toDbQuery();
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::BAD_PARAMS_COUNT);
	}


	public function testOnlyOneMainTable(): void
	{
		$query = $this->query()->table('table');

		Tester\Assert::exception(static function () use ($query): void {
			$query->table('another');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::ONLY_ONE_MAIN_TABLE);
	}


	public function testTableAliasAlreadyExists(): void
	{
		$query = $this->query()->table('table', 't');

		Tester\Assert::exception(static function () use ($query): void {
			$query->from('another', 't');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::TABLE_ALIAS_ALREADY_EXISTS);
	}


	public function testHas(): void
	{
		$query = $this->query();

		Tester\Assert::false($query->has($query::PARAM_SELECT));
		Tester\Assert::false($query->has($query::PARAM_DISTINCT));
		Tester\Assert::false($query->has($query::PARAM_DISTINCTON));
		Tester\Assert::false($query->has($query::PARAM_TABLES));
		Tester\Assert::false($query->has($query::PARAM_TABLE_TYPES));
		Tester\Assert::false($query->has($query::PARAM_ON_CONDITIONS));
		Tester\Assert::false($query->has($query::PARAM_WHERE));
		Tester\Assert::false($query->has($query::PARAM_GROUPBY));
		Tester\Assert::false($query->has($query::PARAM_HAVING));
		Tester\Assert::false($query->has($query::PARAM_ORDERBY));
		Tester\Assert::false($query->has($query::PARAM_LIMIT));
		Tester\Assert::false($query->has($query::PARAM_OFFSET));
		Tester\Assert::false($query->has($query::PARAM_COMBINE_QUERIES));
		Tester\Assert::false($query->has($query::PARAM_INSERT_COLUMNS));
		Tester\Assert::false($query->has($query::PARAM_RETURNING));
		Tester\Assert::false($query->has($query::PARAM_DATA));
		Tester\Assert::false($query->has($query::PARAM_ROWS));
		Tester\Assert::false($query->has($query::PARAM_PREFIX));
		Tester\Assert::false($query->has($query::PARAM_SUFFIX));

		$query
			->select(['column'])
			->distinct()
			->from('table', 't')
			->leftJoin('join_table', 'j', 'j.id = t.join_id')
			->where('t.column', 100)
			->where('t.text', NULL)
			->groupBy('t.column')
			->having('COUNT(*) > 1')
			->orderBy('t.column')
			->limit(10)
			->offset(20)
			->prefix('some SQL prefix')
			->suffix('some SQL suffix')
			->union('SELECT 1');

		Tester\Assert::true($query->has($query::PARAM_SELECT));
		Tester\Assert::true($query->has($query::PARAM_DISTINCT));
		Tester\Assert::false($query->has($query::PARAM_DISTINCTON));
		Tester\Assert::true($query->has($query::PARAM_TABLES));
		Tester\Assert::true($query->has($query::PARAM_TABLE_TYPES));
		Tester\Assert::true($query->has($query::PARAM_ON_CONDITIONS));
		Tester\Assert::true($query->has($query::PARAM_WHERE));
		Tester\Assert::true($query->has($query::PARAM_GROUPBY));
		Tester\Assert::true($query->has($query::PARAM_HAVING));
		Tester\Assert::true($query->has($query::PARAM_ORDERBY));
		Tester\Assert::true($query->has($query::PARAM_LIMIT));
		Tester\Assert::true($query->has($query::PARAM_OFFSET));
		Tester\Assert::true($query->has($query::PARAM_COMBINE_QUERIES));
		Tester\Assert::true($query->has($query::PARAM_PREFIX));
		Tester\Assert::true($query->has($query::PARAM_SUFFIX));

		$query = $this->query()->select(['column'])->distinctOn('column');

		Tester\Assert::false($query->has($query::PARAM_DISTINCT));
		Tester\Assert::true($query->has($query::PARAM_DISTINCTON));

		$query = $this->query()->insert('table', columns: ['column'])->select(['1'])->returning(['column']);

		Tester\Assert::true($query->has($query::PARAM_INSERT_COLUMNS));
		Tester\Assert::true($query->has($query::PARAM_RETURNING));

		$query = $this->query()->insert('table')->rows([
			['column' => 1],
		]);

		Tester\Assert::true($query->has($query::PARAM_ROWS));

		$query = $this->query()->update('table')->set(['column' => 'data']);

		Tester\Assert::true($query->has($query::PARAM_DATA));
	}


	public function testHasBadParam(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->select([1])
				->has('non-existing-param');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::NON_EXISTING_QUERY_PARAM);
	}


	public function testGet(): void
	{
		$query = new class(new Fluent\QueryBuilder()) extends Fluent\Query
		{

			public function testGet(string $param): mixed
			{
				return $this->get($param);
			}

		};

		Tester\Assert::same([], $query->testGet($query::PARAM_SELECT));

		$query->select(['column']);

		Tester\Assert::same(['column'], $query->testGet($query::PARAM_SELECT));

		Tester\Assert::exception(static function () use ($query): void {
			$query->testGet('non-existing-param');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::NON_EXISTING_QUERY_PARAM);
	}


	public function testReset(): void
	{
		$query = $this->query()
			->select([1]);

		$sql = $query->toDbQuery();

		Tester\Assert::same('SELECT 1', $sql->sql);
		Tester\Assert::same([], $sql->params);

		$query2 = $query
			->reset(Fluent\Query::PARAM_SELECT)
			->select([2])
			->toDbQuery();

		Tester\Assert::same('SELECT 2', $query2->sql);
		Tester\Assert::same([], $query2->params);
	}


	public function testResetBadParam(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->select([1])
				->reset('table');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::NON_EXISTING_QUERY_PARAM);
	}


	public function testTableQueryableMustHaveAlias(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->table($this->query()->select([1]));
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::SQL_MUST_HAVE_ALIAS);
	}


	public function testColumQueryableMustHaveAlias(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()
				->select([$this->query()->select([1])]);
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::SQL_MUST_HAVE_ALIAS);
	}


	public function testColumnMustBeScalarOrEnumOrExpression(): void
	{
		Tester\Assert::exception(function (): void {
			$this->query()->select(['t' => ['table']]);
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::PARAM_MUST_BE_SCALAR_OR_ENUM_OR_EXPRESSION);
	}


	public function testBadQueryBuilderType(): void
	{
		Tester\Assert::exception(static function (): void {
			(new Fluent\QueryBuilder())->createSqlDefinition('table', [Fluent\Query::PARAM_WITH => [Fluent\Query::WITH_QUERIES => []]]);
		}, Fluent\Exceptions\QueryBuilderException::class, NULL, Fluent\Exceptions\QueryBuilderException::BAD_QUERY_TYPE);
	}


	public function testCloneQuery(): void
	{
		$baseQuery = $this->query()
			->select(['x.column'])
			->from('table', 't')
			->join('another_table', 'at', 'at.id = t.another_table_id')
				->on('at', 'at.type_id', 2)
			->where('x.column', 1)
			->having('count(*) > ?', 10);

		$clonedQuery = clone $baseQuery;
		$clonedQuery
			->on('at', 'at.another_type', 3)
			->where('x.another_column', 4)
			->having('avg(column)', 1);

		$testBaseQuery = $baseQuery->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another_table AS at ON (at.id = t.another_table_id) AND (at.type_id = $1) WHERE x.column = $2 HAVING count(*) > $3', $testBaseQuery->sql);
		Tester\Assert::same([2, 1, 10], $testBaseQuery->params);

		$testClonedQuery = $clonedQuery->toDbQuery();

		Tester\Assert::same('SELECT x.column FROM table AS t INNER JOIN another_table AS at ON (at.id = t.another_table_id) AND (at.type_id = $1) AND (at.another_type = $2) WHERE (x.column = $3) AND (x.another_column = $4) HAVING (count(*) > $5) AND (avg(column) = $6)', $testClonedQuery->sql);
		Tester\Assert::same([2, 3, 1, 4, 10, 1], $testClonedQuery->params);
	}


	private function query(): Fluent\Query
	{
		return new Fluent\Query(new Fluent\QueryBuilder());
	}

}

(new FluentQueryTest())->run();

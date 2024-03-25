<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Unit;

use Forrest79\PhPgSql\Fluent;
use Forrest79\PhPgSql\Tests;
use Tester;

require_once __DIR__ . '/../TestCase.php';

/**
 * @testCase
 */
final class FluentConditionTest extends Tests\TestCase
{

	public function testParent(): void
	{
		$parentCondition = Fluent\Condition::createAnd();

		Tester\Assert::same($parentCondition, $parentCondition->addOrBranch()->parent());
	}


	public function testNoParent(): void
	{
		Tester\Assert::exception(static function (): void {
			Fluent\Condition::createAnd()->parent();
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::NO_PARENT);
	}


	public function testFluent(): void
	{
		$query = new Fluent\Query(new Fluent\QueryBuilder());
		$parentCondition = Fluent\Condition::createAnd([], NULL, $query);

		Tester\Assert::same($query, $parentCondition->addOrBranch()->query());
	}


	public function testNoFluent(): void
	{
		Tester\Assert::exception(static function (): void {
			Fluent\Condition::createAnd()->query();
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::NO_QUERY);
	}


	public function testAddConditionWithParams(): void
	{
		Tester\Assert::exception(static function (): void {
			Fluent\Condition::createAnd()->add(Fluent\Condition::createAnd(), 'param1');
		}, Fluent\Exceptions\ConditionException::class, NULL, Fluent\Exceptions\ConditionException::ONLY_STRING_CONDITION_CAN_HAVE_PARAMS);
	}


	public function testAdd(): void
	{
		$condition = Fluent\Condition::createAnd();

		Tester\Assert::same(Fluent\Condition::TYPE_AND, $condition->getType());

		$condition->add('column = ?', 1);
		$conditionAnd = $condition->addAndBranch();
		$conditionOr = $condition->addOrBranch();

		$conditions = $condition->getConditions();

		Tester\Assert::same(3, \count($conditions));
		Tester\Assert::same(['column = ?', 1], $conditions[0]);
		Tester\Assert::same($conditionAnd, $conditions[1]);
		Tester\Assert::same($conditionOr, $conditions[2]);
	}


	public function testArrayAcces(): void
	{
		$condition = Fluent\Condition::createOr();

		Tester\Assert::same(Fluent\Condition::TYPE_OR, $condition->getType());

		Tester\Assert::false(isset($condition[0]));
		Tester\Assert::null($condition[0]);

		$condition[] = ['column = ?', 1];

		Tester\Assert::true(isset($condition[0]));
		Tester\Assert::same(['column = ?', 1], $condition[0]);

		$conditionAnd = Fluent\Condition::createAnd();
		$conditionOr = Fluent\Condition::createOr();

		$condition[] = $conditionAnd;

		Tester\Assert::same([$conditionAnd], $condition[1]);

		$condition[1] = $conditionOr;

		Tester\Assert::same([$conditionOr], $condition[1]);

		unset($condition[1]);

		Tester\Assert::false(isset($condition[1]));
		Tester\Assert::null($condition[1]);

		$conditions = $condition->getConditions();

		Tester\Assert::same(1, \count($conditions));
		Tester\Assert::same([['column = ?', 1]], $conditions);
	}

}

(new FluentConditionTest())->run();

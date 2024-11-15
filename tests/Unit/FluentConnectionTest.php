<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Unit;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Fluent;
use Forrest79\PhPgSql\Tests;
use Tester;

require_once __DIR__ . '/../TestCase.php';

/**
 * @testCase
 */
final class FluentConnectionTest extends Tests\TestCase
{
	private Fluent\Connection $fluentConnection;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fluentConnection = new Fluent\Connection();
	}


	public function testCreateQuery(): void
	{
		$query = $this->fluentConnection
			->createQuery()
			->select(['column'])
			->from('table')
			->toDbQuery();

		Tester\Assert::same('SELECT column FROM table', $query->sql);
		Tester\Assert::same([], $query->params);
	}


	public function testCustomQueryBuilder(): void
	{
		$customQueryBuilder = new class extends Fluent\QueryBuilder
		{

			/**
			 * @param list<mixed> $params
			 */
			protected function prepareSql(string $sql, array $params): Db\SqlDefinition
			{
				return parent::prepareSql('SELECT custom_column FROM ?', ['custom_table']);
			}

		};

		$this->fluentConnection->setQueryBuilder($customQueryBuilder);

		$query = $this->fluentConnection
			->createQuery()
			->select(['column'])
			->from('table')
			->toDbQuery();

		Tester\Assert::same('SELECT custom_column FROM $1', $query->sql);
		Tester\Assert::same(['custom_table'], $query->params);
	}

}

(new FluentConnectionTest())->run();

<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Fluent;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 * @property-read Fluent\Connection $connection
 */
final class FluentQueryExecuteFetchTest extends TestCase
{

	public function testFetch(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$query = $this->connection
			->createQuery()
			->select(['id', 'name'])
			->from('test');

		$row = $query->fetch();
		if ($row === NULL) {
			throw new \RuntimeException('No data from database were returned');
		}

		Tester\Assert::same(1, $row->id);
		Tester\Assert::same('phpgsql', $row->name);

		$query->free();
	}


	public function testFetchSingle(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id integer
			);
		');

		$this->connection->query('INSERT INTO test(id) VALUES(?)', 999);

		$query = $this->connection
			->createQuery()
			->select(['id'])
			->from('test')
			->limit(1);

		$id = $query->fetchSingle();

		$query->free();

		Tester\Assert::same(999, $id);
	}


	public function testFetchAll(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');

		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$query = $this->connection
			->createQuery()
			->select(['id', 'type', 'name'])
			->from('test')
			->orderBy('id');

		Tester\Assert::same(3, $query->count());

		$rows = $query->fetchAll();

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows[0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[1]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows[2]->toArray());

		$query->free();
	}


	public function testFetchAssocSimple(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');

		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$query = $this->connection
			->createQuery()
			->select(['id', 'type', 'name'])
			 ->from('test')
			 ->orderBy('id');

		$rows = $query->fetchAssoc('type');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows[3]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[2]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows[1]->toArray());

		$query->free();
	}


	public function testFetchPairs(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				name character varying
			);
		');

		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$query = $this->connection
			->createQuery()
			->select(['id', 'name'])
			->from('test')
			->orderBy('id');

		$rows = $query->fetchPairs();

		Tester\Assert::same([1 => 'name3', 2 => 'name2', 3 => 'name1'], $rows);

		$query->free();
	}


	public function testAsyncExecute(): void
	{
		$asyncQuery = $this->connection->createQuery()->select(['1'])->asyncExecute();

		$result = $asyncQuery->getNextResult();
		$data = $result->fetchSingle();

		Tester\Assert::same(1, $data);
	}


	public function testFetchIterator(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				name character varying
			);
		');

		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$query = $this->connection
			->createQuery()
			->select(['id', 'name'])
			->from('test')
			->orderBy('id');

		Tester\Assert::same(3, \count($query));

		$expected = [
			['id' => 1, 'name' => 'name3'],
			['id' => 2, 'name' => 'name2'],
			['id' => 3, 'name' => 'name1'],
		];

		foreach ($query->fetchIterator() as $i => $row) {
			Tester\Assert::same($expected[$i], $row->toArray());
		}

		$query->free();
	}


	public function testAffectedRows(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');

		$query = $this->connection
			->createQuery()
			->insert('test', columns: ['name'])
			->select(['\'name\' || generate_series FROM generate_series(3, 1, -1)']);

		Tester\Assert::same(3, $query->getAffectedRows());

		$query->free();
	}


	public function testReexecute(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id integer
			);
		');

		$this->connection->query('INSERT INTO test(id) VALUES(?)', 999);

		$query = $this->connection
			->createQuery()
			->select(['id'])
			->from('test')
			->limit(1);

		Tester\Assert::same(999, $query->fetchSingle());

		$this->connection->query('UPDATE test SET id = ? WHERE id = ?', 888, 999);

		Tester\Assert::same(888, $query->reexecute()->fetchSingle());

		$query->free();
	}


	public function testFreeWithoutResult(): void
	{
		Tester\Assert::exception(function (): void {
			$this->connection->createQuery()->select([1])->free();
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::YOU_MUST_EXECUTE_QUERY_BEFORE_THAT);
	}


	public function testUpdateExecuted(): void
	{
		$query = $this->connection->createQuery()->select([1]);

		$query->fetchSingle();

		Tester\Assert::exception(static function () use ($query): void {
			$query->from('table');
		}, Fluent\Exceptions\QueryException::class, NULL, Fluent\Exceptions\QueryException::CANT_UPDATE_QUERY_AFTER_EXECUTE);
	}


	public function testCloneExecuted(): void
	{
		$originalQuery = $this->connection->createQuery()->select([1]);

		$result1 = $originalQuery->execute();

		Tester\Assert::same(1, $result1->fetchSingle());

		$clonedQuery = clone $originalQuery;

		$result2 = $clonedQuery->where('FALSE')->execute();

		Tester\Assert::null($result2->fetch());

		Tester\Assert::notSame($result1, $result2);
	}


	protected function createConnection(): Db\Connection
	{
		return new Fluent\Connection($this->getTestConnectionConfig());
	}

}

(new FluentQueryExecuteFetchTest())->run();

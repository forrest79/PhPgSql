<?php declare(strict_types=1);

namespace Tests\Integration\Forrest79\PhPgSql\Db;

use Forrest79\PhPgSql\Db;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
class FetchTest extends TestCase
{
	/** @var Db\Connection */
	private $connection;


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = new Db\Connection(sprintf('%s dbname=%s', $this->getConfig(), $this->getDbName()));
		$this->connection->connect();
	}


	public function testFetch(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		$row = $result->fetch();

		$result->free();

		Tester\Assert::same(1, $row->id);
		Tester\Assert::same('phpgsql', $row->name);
	}


	public function testFetchSingle(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id integer
			);
		');
		$this->connection->query('INSERT INTO test(id) VALUES(?)', 999);

		$result = $this->connection->query('SELECT id FROM test');

		$id = $result->fetchSingle();

		$result->free();

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

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		Tester\Assert::same(3, $result->count());

		$rows = $result->fetchAll();

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows[0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[1]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows[2]->toArray());

		$rows = $result->fetchAll(1);

		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[0]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows[1]->toArray());

		$rows = $result->fetchAll(1, 1);

		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[0]->toArray());

		$result->free();
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

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('type');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows[3]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows[2]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows[1]->toArray());

		$result->free();
	}


	public function testFetchAssocArray(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'test\' FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('name[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows['test'][0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows['test'][1]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows['test'][2]->toArray());

		$result->free();
	}


	public function testFetchAssocPipe(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'test\' FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('name|type');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows['test'][3]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows['test'][2]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows['test'][1]->toArray());

		$result->free();

		// ---

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('name|type[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows['test'][3][0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows['test'][2][0]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows['test'][1][0]->toArray());

		$result->free();
	}


	public function testFetchAssocValue(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'test\' || generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('type|id=name');

		Tester\Assert::same('test3', $rows[3][1]);
		Tester\Assert::same('test2', $rows[2][2]);
		Tester\Assert::same('test1', $rows[1][3]);

		$result->free();
	}


	public function testFetchAssocBlank(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(type, name) SELECT generate_series, \'test\' || generate_series FROM generate_series(2, 1, -1)');

		$result = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows = $result->fetchAssoc('');

		Tester\Assert::same(2, count($rows));

		Tester\Assert::same(2, $rows[0]->type);
		Tester\Assert::same('test2', $rows[0]->name);
		Tester\Assert::same(1, $rows[1]->type);
		Tester\Assert::same('test1', $rows[1]->name);

		$result->free();
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

		$result = $this->connection->query('SELECT id, name FROM test ORDER BY id');

		$rows = $result->fetchPairs();

		Tester\Assert::same([1 => 'name3', 2 => 'name2', 3 => 'name1'], $rows);

		$rows = $result->fetchPairs('name', 'id');

		Tester\Assert::same(['name3' => 1, 'name2' => 2, 'name1' => 3], $rows);

		$result->free();
	}


	public function testFetchPairsOnlyOneColumn(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, name FROM test ORDER BY id');

		Tester\Assert::exception(function() use ($result): void {
			$result->fetchPairs('name');
		}, \InvalidArgumentException::class);

		$result->free();
	}


	public function testFetchPairsIndexedArray(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer
			);
		');
		$this->connection->query('INSERT INTO test(type) SELECT generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT type FROM test ORDER BY id');

		$rows = $result->fetchPairs();

		Tester\Assert::same([3, 2, 1], $rows);

		$rows = $result->fetchPairs(NULL, 'type');

		Tester\Assert::same([3, 2, 1], $rows);

		$result->free();
	}


	public function testFetchPairsBadKeyOrValue(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, name FROM test ORDER BY id');

		// Bad key
		Tester\Assert::exception(function() use ($result): void {
			$result->fetchPairs('type', 'name');
		}, \InvalidArgumentException::class);

		// Bad value
		Tester\Assert::exception(function() use ($result): void {
			$result->fetchPairs('id', 'type');
		}, \InvalidArgumentException::class);

		$result->free();
	}


	public function testFetchNoColumn(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		$row = $result->fetch();

		Tester\Assert::exception(function() use ($row): void {
			$row->cnt;
		}, Db\Exceptions\RowException::class);

		$result->free();
	}


	public function testResultIterator(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				name character varying
			);
		');
		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, name FROM test ORDER BY id');

		Tester\Assert::same(3, count($result));

		$expected = [
			['id' => 1, 'name' => 'name3'],
			['id' => 2, 'name' => 'name2'],
			['id' => 3, 'name' => 'name1'],
		];
		foreach ($result as $i => $row) {
			Tester\Assert::same($expected[$i], $row->toArray());
		}

		$result->free();
	}


	public function testAffectedRows(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$result = $this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(3, 1, -1)');

		Tester\Assert::same(3, $result->getAffectedRows());

		$result->free();
	}


	public function testGetColumns(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		Tester\Assert::same(['id', 'name'], $result->getColumns());

		$result->free();
	}


	public function testResultColumnType(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		Tester\Assert::same('text', $result->getColumnType('name'));

		$result->free();
	}


	public function testResultNoColumnForType(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		Tester\Assert::exception(function() use ($result): void {
			$result->getColumnType('count');
		}, Db\Exceptions\ResultException::class);

		$result->free();
	}


	public function testCustomRowFactoryOnConnection(): void
	{
		$this->connection->setRowFactory($this->createCustomRowFactory());

		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		$row = $result->fetch();

		$result->free();

		Tester\Assert::same('custom', $row->test);
	}


	public function testCustomRowFactoryOnResult(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');
		$result->setRowFactory($this->createCustomRowFactory());

		$row = $result->fetch();

		$result->free();

		Tester\Assert::same('custom', $row->test);
	}


	public function testNoResults(): void
	{
		Tester\Assert::null($this->connection->query('SELECT 1 WHERE FALSE')->fetchSingle());
		Tester\Assert::same([], $this->connection->query('SELECT 1 WHERE FALSE')->fetchAll());
		Tester\Assert::same([], $this->connection->query('SELECT 1 WHERE FALSE')->fetchAssoc('column'));
		Tester\Assert::same([], $this->connection->query('SELECT 1 WHERE FALSE')->fetchPairs());
	}


	public function testRowValues(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');
		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test');

		$row = $result->fetch();

		Tester\Assert::same('phpgsql', $row->name);

		Tester\Assert::false(isset($row->type));

		Tester\Assert::false(isset($row['another_type']));

		Tester\Assert::exception(function() use ($row): void {
			$row->type;
		}, Db\Exceptions\RowException::class);

		Tester\Assert::exception(function() use ($row): void {
			$row['another_type'];
		}, Db\Exceptions\RowException::class);

		$row->type = 'test';

		Tester\Assert::true(isset($row->type));

		Tester\Assert::same('test', $row->type);

		$row['another_type'] = 'another_test';

		Tester\Assert::true(isset($row['another_type']));

		Tester\Assert::same('another_test', $row['another_type']);

		unset($row->type);

		Tester\Assert::false(isset($row->type));

		Tester\Assert::exception(function() use ($row): void {
			$row->type;
		}, Db\Exceptions\RowException::class);

		unset($row['another_type']);

		Tester\Assert::false(isset($row['another_type']));

		Tester\Assert::exception(function() use ($row): void {
			$row['another_type'];
		}, Db\Exceptions\RowException::class);

		unset($row->name);

		foreach ($row as $key => $value) {
			Tester\Assert::same('id', $key);
			Tester\Assert::same(1, $value);
		}

		$result->free();
	}


	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}


	private function createCustomRowFactory(): Db\RowFactory
	{
		return new class implements Db\RowFactory {

			public function createRow(array $values, array $columnsDataTypes, Db\DataTypeParsers\DataTypeParser $dataTypeParser): Db\Row
			{
				return new Db\Row(['test' => 'custom'], ['test' => 'text'], $dataTypeParser);
			}

		};
	}

}

(new FetchTest())->run();

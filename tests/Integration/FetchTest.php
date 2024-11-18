<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class FetchTest extends TestCase
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

		$result = $this->connection->query('SELECT id, name FROM test');

		$row = $this->fetch($result);

		Tester\Assert::same(1, $row->id);
		Tester\Assert::same('phpgsql', $row->name);

		$result->free();
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

		Tester\Assert::same(999, $id);

		$result->free();
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

		$result1 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows1 = $result1->fetchAssoc('type');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows1[3]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows1[2]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows1[1]->toArray());

		$result1->free();

		// ---

		$result2 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows2 = $result2->fetchAssoc('type=[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'name3'], $rows2[3]);
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'name2'], $rows2[2]);
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'name1'], $rows2[1]);

		$result2->free();
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

		$result1 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows1 = $result1->fetchAssoc('name[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows1['test'][0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows1['test'][1]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows1['test'][2]->toArray());

		$result1->free();

		// ---

		$result2 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows2 = $result2->fetchAssoc('name[]=[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows2['test'][0]);
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows2['test'][1]);
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows2['test'][2]);

		$result2->free();

		// ---

		$result3 = $this->connection->query('SELECT id, type, name || id AS name FROM test ORDER BY id');

		$rows3 = $result3->fetchAssoc('[]name');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test1'], $rows3[0]['test1']->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test2'], $rows3[1]['test2']->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test3'], $rows3[2]['test3']->toArray());

		$result3->free();

		// ---

		$result4 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows4 = $result4->fetchAssoc('[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows4[0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows4[1]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows4[2]->toArray());

		$result4->free();
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

		$result1 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows1 = $result1->fetchAssoc('name|type');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows1['test'][3]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows1['test'][2]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows1['test'][1]->toArray());

		$result1->free();

		// ---

		$result2 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows2 = $result2->fetchAssoc('name|type=[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows2['test'][3]);
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows2['test'][2]);
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows2['test'][1]);

		$result2->free();

		// ---

		$result3 = $this->connection->query('SELECT id, type, name FROM test ORDER BY id');

		$rows3 = $result3->fetchAssoc('name|type[]');

		Tester\Assert::same(['id' => 1, 'type' => 3, 'name' => 'test'], $rows3['test'][3][0]->toArray());
		Tester\Assert::same(['id' => 2, 'type' => 2, 'name' => 'test'], $rows3['test'][2][0]->toArray());
		Tester\Assert::same(['id' => 3, 'type' => 1, 'name' => 'test'], $rows3['test'][1][0]->toArray());

		$result3->free();
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


	public function testFetchAssocBadDescriptor(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer
			);
		');

		$this->connection->query('INSERT INTO test(type) SELECT generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, type FROM test ORDER BY id');

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_BAD_DESCRIPTOR);

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('=types');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_BAD_DESCRIPTOR);

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('|types');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_BAD_DESCRIPTOR);

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('types=');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_BAD_DESCRIPTOR);

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('types|');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_BAD_DESCRIPTOR);

		$result->free();
	}


	public function testFetchAssocNoColumn(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				type integer
			);
		');

		$this->connection->query('INSERT INTO test(type) SELECT generate_series FROM generate_series(3, 1, -1)');

		$result = $this->connection->query('SELECT id, type FROM test ORDER BY id');

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('id=types');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_NO_COLUMN);

		$result->free();
	}


	public function testFetchAssocObjectAsNull(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				test_column text
			);
		');

		$this->connection->query('INSERT INTO test(test_column) VALUES(NULL)');

		$result = $this->connection->query('SELECT id, test_column FROM test');

		$row = $result->fetchAssoc('test_column=id');

		Tester\Assert::same('', \key($row));
		Tester\Assert::same(1, $row[NULL]);

		$result->free();
	}


	public function testFetchAssocObjectAsKey(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				test_date date
			);
		');

		$this->connection->query('INSERT INTO test(test_date) VALUES(CURRENT_DATE)');

		$result = $this->connection->query('SELECT id, test_date FROM test');

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchAssoc('test_date=id');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_ASSOC_ONLY_SCALAR_AS_KEY);

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

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchPairs('name');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_PAIRS_BAD_COLUMNS);

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


	public function testFetchPairsObjectAsKey(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
				test_date date
			);
		');

		$this->connection->query('INSERT INTO test(test_date) VALUES(CURRENT_DATE)');

		$result = $this->connection->query('SELECT id, test_date FROM test');

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchPairs('test_date', 'id');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::FETCH_PAIRS_ONLY_SCALAR_AS_KEY);

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
		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchPairs('type', 'name');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::NO_COLUMN);

		// Bad value
		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchPairs('id', 'type');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::NO_COLUMN);

		$result->free();
	}


	public function testFetchRowAsJson(): void
	{
		$result = $this->connection->query('SELECT 1 AS number_column, \'test\'::text AS text_column, TRUE AS boolean_column, NULL AS null_column');

		$row = $result->fetch();

		Tester\Assert::same(
			'{"number_column":1,"text_column":"test","boolean_column":true,"null_column":null}',
			\json_encode($row),
		);

		$result->free();
	}


	public function testFetchBadOffset(): void
	{
		$result = $this->connection->query('SELECT 1');

		$row = $result->fetch();
		if ($row === NULL) {
			throw new \RuntimeException('No data from database were returned');
		}

		Tester\Assert::exception(static function () use ($row): void {
			$row[1];
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NOT_STRING_KEY);

		Tester\Assert::exception(static function () use ($row): void {
			$row[1] = 'value';
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NOT_STRING_KEY);

		Tester\Assert::exception(static function () use ($row): void {
			isset($row[1]);
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NOT_STRING_KEY);

		Tester\Assert::exception(static function () use ($row): void {
			unset($row[1]);
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NOT_STRING_KEY);

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

		$row = $this->fetch($result);

		Tester\Assert::exception(static function () use ($row): void {
			$row->cnt;
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NO_COLUMN);

		$result->free();
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

		$result = $this->connection->query('SELECT id, name FROM test ORDER BY ? DESC', Db\Sql\Expression::create('id = ?', 1));

		Tester\Assert::same(3, \count($result));

		$expected = [
			['id' => 1, 'name' => 'name3'],
			['id' => 2, 'name' => 'name2'],
			['id' => 3, 'name' => 'name1'],
		];
		foreach ($result->fetchIterator() as $i => $row) {
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
		Tester\Assert::true($result->hasAffectedRows());

		$result->free();
	}


	public function testNoAffectedRows(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');

		$result = $this->connection->query('UPDATE test SET name = \'name\' WHERE id = 1');

		Tester\Assert::same(0, $result->getAffectedRows());
		Tester\Assert::false($result->hasAffectedRows());

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

		$row = $this->fetch($result);

		Tester\Assert::same(['id', 'name'], $row->getColumns());

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

		Tester\Assert::exception(static function () use ($result): void {
			$result->getColumnType('count');
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::NO_COLUMN);

		$result->free();
	}


	public function testResultColumnIsAlreadyInUse(): void
	{
		$result = $this->connection->query('SELECT 1 AS column, 2 AS column');

		Tester\Assert::exception(static function () use ($result): void {
			$row = $result->fetch();
			\assert($row !== NULL);
			$row->column;
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::COLUMN_NAME_IS_ALREADY_IN_USE);

		$result->free();
	}


	public function testResultHasRows(): void
	{
		Tester\Assert::true($this->connection->query('SELECT 1 WHERE TRUE')->hasRows());
		Tester\Assert::false($this->connection->query('SELECT 1 WHERE FALSE')->hasRows());
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

		$row = $this->fetch($result);

		Tester\Assert::same('custom', $row->name);

		$result->free();
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

		$row = $this->fetch($result);

		Tester\Assert::same('custom', $row->name);

		$result->free();
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

		$row = $this->fetch($result);

		Tester\Assert::same('phpgsql', $row->name);

		Tester\Assert::false(isset($row->type));

		Tester\Assert::false(isset($row['another_type']));

		Tester\Assert::exception(static function () use ($row): void {
			$row->type;
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NO_COLUMN);

		Tester\Assert::exception(static function () use ($row): void {
			$row['another_type'];
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NO_COLUMN);

		$row->type = 'test';

		Tester\Assert::true(isset($row->type));

		Tester\Assert::same('test', $row->type);

		$row['another_type'] = 'another_test';

		Tester\Assert::true(isset($row['another_type']));

		Tester\Assert::same('another_test', $row['another_type']);

		unset($row->type);

		Tester\Assert::false(isset($row->type));

		Tester\Assert::exception(static function () use ($row): void {
			$row->type;
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NO_COLUMN);

		unset($row['another_type']);

		Tester\Assert::false(isset($row['another_type']));

		Tester\Assert::exception(static function () use ($row): void {
			$row['another_type'];
		}, Db\Exceptions\RowException::class, NULL, Db\Exceptions\RowException::NO_COLUMN);

		unset($row->name);

		foreach ($row as $key => $value) {
			Tester\Assert::same('id', $key);
			Tester\Assert::same(1, $value);
		}

		$result->free();
	}


	private function fetch(Db\Result $result): Db\Row
	{
		return $result->fetch() ?? throw new \RuntimeException('No data from database were returned');
	}


	private function createCustomRowFactory(): Db\RowFactory
	{
		return new class implements Db\RowFactory {

			/**
			 * @param array<string, string|NULL> $rawValues
			 */
			public function create(Db\ColumnValueParser $columnValueParser, array $rawValues): Db\Row
			{
				return new Db\Row($columnValueParser, ['name' => 'custom']);
			}

		};
	}

}

(new FetchTest())->run();

<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Fluent;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class DocsTest extends TestCase
{
	private const DOCS_DIRECTORY = __DIR__ . '/../../docs';


	protected function setUp(): void
	{
		parent::setUp();

		$this->connection->execute((string) \file_get_contents(self::DOCS_DIRECTORY . '/test-data.sql'));
	}


	/**
	 * Fluent\Connection can be used to both Db and Fluent examples.
	 */
	protected function createConnection(): Db\Connection
	{
		return new Fluent\Connection($this->getTestConnectionConfig());
	}


	/**
	 * @dataProvider getPhpExamples
	 */
	public function testDocs(string $filename, string $source): void
	{
		$filenameTitle = \sprintf('Filename: "%s"', $filename);
		echo \sprintf("%s\n%s\n%s\n", $filenameTitle, \str_repeat('=', \strlen($filenameTitle)), $source);

		Tester\Assert::noError(function () use ($source): void {
			$connection = $this->connection;

			$tempFile = \tempnam(\sys_get_temp_dir(), 'PhPgSql');
			\assert(\is_string($tempFile));

			// Check dump()
			$source = \preg_replace(
				'#dump\((.+?)\); // (.+)#',
				'Tester\Assert::same("\2", Forrest79\PhPgSql\Tests\Integration\DocsTest::dump(\1));',
				$source,
			);
			\assert(\is_string($source));

			// Check table()
			$source = \preg_replace(
				'#table\((\$.+?)\);\n\/\*\*\n(.+?)\n\*\/#s',
				'Tester\Assert::same("\2", Forrest79\PhPgSql\Tests\Integration\DocsTest::table(\1));',
				$source,
			);

			\file_put_contents($tempFile, '<?php declare(strict_types=1);' . \PHP_EOL . \PHP_EOL . $source);

			echo 'Source file: ' . $tempFile . \PHP_EOL;

			require $tempFile;
		});
	}


	/**
	 * @return \Traversable<array{0: string, 1: string}>
	 */
	public function getPhpExamples(): \Traversable
	{
		$markdownFiles = \glob(self::DOCS_DIRECTORY . '/*.md');
		\assert(\is_array($markdownFiles));

		foreach ($markdownFiles as $filename) {
			$filename = \realpath($filename);
			\assert(\is_string($filename));

			$docSource = \file_get_contents($filename);
			\assert(\is_string($docSource));

			if ((bool) \preg_match_all('#```php(?<php>.*?)```#s', $docSource, $sources) === FALSE) {
				continue;
			}

			foreach ($sources['php'] as $source) {
				yield [$filename, $source];
			}
		}
	}


	public static function dump(mixed $var): string
	{
		if ($var === NULL) {
			return '(NULL)';
		} else if (\is_string($var)) {
			return \sprintf('(string) \'%s\'', str_replace(\PHP_EOL, ' ', $var));
		} else if (\is_bool($var)) {
			return \sprintf('(bool) %s', $var ? 'TRUE' : 'FALSE');
		} else if (\is_numeric($var)) {
			return \sprintf('(%s) %s', \gettype($var), $var);
		} if ($var instanceof \DateTimeImmutable) {
			return \sprintf('(Date) %s', $var->format('Y-m-d H:i:s'));
		} else if (\is_array($var) || ($var instanceof Db\Row)) {
			if ($var instanceof Db\Row) {
				$type = 'Row';
				$var = $var->toArray();
			} else {
				$type = 'array';
			}
			$list = [];
			$array = [];

			$i = 0;
			$isList = TRUE;
			foreach ($var as $key => $value) {
				if ($value instanceof \DateTimeImmutable) {
					$value = \sprintf('\'%s\'', $value->format('Y-m-d H:i:s'));
				} else if (\is_string($value)) {
					$value = '\'' . $value . '\'';
				} else if (\is_bool($value)) {
					$value = $value ? 'TRUE' : 'FALSE';
				} else if (\is_array($value)) {
					$value = '[' . \implode(', ', $value) . ']';
				} else if ($value === NULL) {
					$value = '(NULL)';
				}

				assert(\is_scalar($value));

				$list[] = $value;
				$array[] = (\is_string($key) ? ('\'' . $key . '\'') : $key) . ' => ' . $value;

				if ($isList && ($key !== $i)) {
					$isList = FALSE;
				}

				$i++;
			}

			return \sprintf('(%s) [%s]', $type, \implode(', ', $isList ? $list : $array));
		} else if ($var instanceof Fluent\Query) {
			$query = $var->toDbQuery();
			return '(Query) ' . $query->sql . ($query->params === [] ? '' : \sprintf(' [Params: %s]', self::dump($query->params)));
		}

		throw new \InvalidArgumentException(\sprintf('Unknown type: \'%s\'', (\gettype($var) === 'object') ? $var::class : \gettype($var)));
	}


	/**
	 * @param list<Db\Row> $rows
	 */
	public static function table(array $rows): string
	{
		if (\count($rows) === 0) {
			return self::dump($rows);
		}

		$columns = \array_keys($rows[0]->toArray());

		// Compute max chars for columns

		$columnsMaxChars = [];

		foreach ($columns as $column) {
			$columnsMaxChars[$column] = \strlen($column);
		}

		foreach ($rows as $row) {
			foreach ($columns as $column) {
				$length = \strlen(self::dump($row[$column]));
				if ($length > $columnsMaxChars[$column]) {
					$columnsMaxChars[$column] = $length;
				}
			}
		}

		// Dump table

		$line1 = \str_repeat('-', (\count($columns) * 3) + \array_sum($columnsMaxChars) + 1);
		$line2 = '|' . \str_repeat('=', (\count($columns) * 3) + \array_sum($columnsMaxChars) - 1) . '|';

		$table = $line1 . \PHP_EOL . '|';
		foreach ($columns as $column) {
			$table .= ' ' . $column . \str_repeat(' ', $columnsMaxChars[$column] - \strlen($column)) . ' |';
		}
		$table .= \PHP_EOL . $line2;

		foreach ($rows as $row) {
			$table .= \PHP_EOL . '|';
			foreach ($columns as $column) {
				$dump = self::dump($row[$column]);
				$table .= ' ' . $dump . \str_repeat(' ', $columnsMaxChars[$column] - \strlen($dump)) . ' |';
			}
		}

		return $table . \PHP_EOL . $line1;
	}

}

(new DocsTest())->run();

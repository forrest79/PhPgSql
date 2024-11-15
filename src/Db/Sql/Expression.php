<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Sql;

use Forrest79\PhPgSql\Db;

class Expression implements Db\Sql
{
	private Db\SqlDefinition $sqlDefinition;


	/**
	 * @param list<mixed> $params
	 */
	final public function __construct(string $value, array $params)
	{
		$this->sqlDefinition = new Db\SqlDefinition($value, $params);
	}


	public function getSqlDefinition(): Db\SqlDefinition
	{
		return $this->sqlDefinition;
	}


	public static function create(string $value, mixed ...$params): static
	{
		\assert(\array_is_list($params));
		return new static($value, $params);
	}


	/**
	 * @param list<mixed> $params
	 */
	public static function createArgs(string $value, array $params): static
	{
		return new static($value, $params);
	}

}

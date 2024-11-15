<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class Query
{
	public readonly string $sql;

	/** @var list<mixed> */
	public readonly array $params;


	/**
	 * @param list<mixed> $params
	 */
	public function __construct(string $sql, array $params)
	{
		$this->sql = $sql;
		$this->params = $params;
	}


	/**
	 * @param list<mixed> $params
	 */
	public static function from(string|self|Sql $query, array $params = []): self
	{
		if (is_string($query)) {
			$query = new Sql\Query($query, $params);
		} else if ($params !== []) {
			throw Exceptions\QueryException::cantPassParams();
		}

		return $query instanceof Sql ? SqlDefinition::createQuery($query->getSqlDefinition()) : $query;
	}

}

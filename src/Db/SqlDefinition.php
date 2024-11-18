<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class SqlDefinition
{
	public readonly string $sql;

	/** @var list<mixed> */
	public readonly array $params;


	/**
	 * @param list<mixed> $params
	 */
	public function __construct(string $value, array $params)
	{
		$this->sql = $value;
		$this->params = $params;
	}


	public static function createQuery(self $sqlDefinition): Query
	{
		return self::processSqlDefinition($sqlDefinition, 0);
	}


	private static function processSqlDefinition(self $sqlDefinition, int $paramIndex): Query
	{
		$params = $sqlDefinition->params;
		$origParamIndex = 0;
		$parsedParams = [];

		$sql = \preg_replace_callback(
			'/([\\\\]?)\?/',
			static function ($matches) use (&$params, &$parsedParams, &$origParamIndex, &$paramIndex): string {
				if ($matches[1] === '\\') {
					return '?';
				}

				if (!\array_key_exists($origParamIndex, $params)) {
					throw Exceptions\QueryException::missingParam($origParamIndex);
				}

				$param = $params[$origParamIndex];
				unset($params[$origParamIndex]);
				$origParamIndex++;

				if (\is_array($param)) {
					$keys = [];
					foreach ($param as $value) {
						$keys[] = '$' . ++$paramIndex;
						$parsedParams[] = ($value instanceof \BackedEnum) ? $value->value : $value;
					}
					return \implode(', ', $keys);
				} else if ($param instanceof Sql) {
					$subquerySql = self::processSqlDefinition($param->getSqlDefinition(), $paramIndex);
					$paramIndex += \count($subquerySql->params);
					$parsedParams = \array_merge($parsedParams, $subquerySql->params);
					return $subquerySql->sql;
				}

				if (\is_bool($param)) {
					$parsedParams[] = $param ? 't' : 'f';
				} else if ($param instanceof \BackedEnum) {
					$parsedParams[] = $param->value;
				} else {
					$parsedParams[] = $param;
				}

				return '$' . ++$paramIndex;
			},
			$sqlDefinition->sql,
		);

		\assert(\is_string($sql));

		if (($origParamIndex > 0) && ($params !== [])) {
			throw Exceptions\QueryException::extraParam(\array_values($params));
		}

		if ($parsedParams === []) {
			$parsedParams = \array_values($params);
		}

		return new Query($sql, $parsedParams);
	}

}

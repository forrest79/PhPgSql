<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

/**
 * @implements \ArrayAccess<int, string|list<mixed>|Db\Sql>
 */
class Condition implements Db\Sql, \ArrayAccess
{
	public const TYPE_AND = 'AND';
	public const TYPE_OR = 'OR';

	private string $type;

	/** @var list<self|list<mixed>> */
	private array $conditions;

	private self|NULL $parent;

	private Query|NULL $query;

	private string|NULL $sql = NULL;

	/** @var list<mixed> */
	private array $params = [];


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 */
	public function __construct(string $type, array $conditions, self|NULL $parent = NULL, Query|NULL $query = NULL)
	{
		$this->type = $type;
		$this->conditions = self::normalizeConditions($conditions);
		$this->parent = $parent;
		$this->query = $query;
	}


	/**
	 * @throws Exceptions\ConditionException
	 */
	public function add(string|Db\Sql $condition, mixed ...$params): static
	{
		if (($condition instanceof Db\Sql) && $params !== []) {
			throw Exceptions\ConditionException::onlyStringConditionCanHaveParams();
		}

		if ($condition instanceof self) {
			$this->conditions[] = $condition;
		} else if ($condition instanceof Db\Sql) {
			$this->conditions[] = \array_merge([$condition->getSql()], $condition->getParams());
		} else {
			\assert(\array_is_list($params));

			\array_unshift($params, $condition);
			$this->conditions[] = $params;
		}

		$this->reset();

		return $this;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 */
	public function addAndBranch(array $conditions = []): static
	{
		$branchAnd = static::createAnd($conditions, $this, $this->query);

		$this->add($branchAnd);

		$this->reset();

		return $branchAnd;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 */
	public function addOrBranch(array $conditions = []): static
	{
		$branchOr = static::createOr($conditions, $this, $this->query);

		$this->add($branchOr);

		$this->reset();

		return $branchOr;
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return list<self|list<mixed>>
	 */
	public function getConditions(): array
	{
		return $this->conditions;
	}


	/**
	 * @throws Exceptions\ConditionException
	 */
	public function parent(): self
	{
		if ($this->parent === NULL) {
			throw Exceptions\ConditionException::noParent();
		}

		return $this->parent;
	}


	/**
	 * @throws Exceptions\ConditionException
	 */
	public function query(): Query
	{
		if ($this->query === NULL) {
			throw Exceptions\ConditionException::noQuery();
		}

		return $this->query;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 */
	public static function createAnd(array $conditions = [], self|NULL $parent = NULL, Query|NULL $query = NULL): static
	{
		return new static(self::TYPE_AND, $conditions, $parent, $query);
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 */
	public static function createOr(array $conditions = [], self|NULL $parent = NULL, Query|NULL $query = NULL): static
	{
		return new static(self::TYPE_OR, $conditions, $parent, $query);
	}


	/**
	 * @param int $offset
	 */
	public function offsetExists($offset): bool
	{
		return isset($this->conditions[$offset]);
	}


	/**
	 * @param int $offset
	 * @return string|list<mixed>|Db\Sql|NULL
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet(mixed $offset): string|array|Db\Sql|NULL
	{
		return $this->conditions[$offset] ?? NULL;
	}


	/**
	 * @param int|NULL $offset
	 * @param string|list<mixed>|Db\Sql $value
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!\is_array($value)) {
			$value = [$value];
		}

		if ($offset === NULL) {
			$this->conditions[] = $value;
		} else {
			if (!isset($this->conditions[$offset])) {
				throw new \InvalidArgumentException(sprintf('Can\'t set non-existing offset \'%s\'.', $offset));
			}

			$this->conditions[$offset] = $value;
		}

		$this->reset();
	}


	/**
	 * @param int $offset
	 */
	public function offsetUnset(mixed $offset): void
	{
		$conditions = $this->conditions;
		unset($conditions[$offset]);
		$this->conditions = array_values($conditions);

		$this->reset();
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 * @return list<self|list<mixed>>
	 */
	private static function normalizeConditions(array $conditions): array
	{
		foreach ($conditions as $i => $value) {
			if (!($value instanceof self)) {
				if ($value instanceof Db\Sql) {
					$conditions[$i] = \array_merge([$value->getSql()], $value->getParams());
				} else if (!\is_array($value)) {
					$conditions[$i] = [$value];
				}
			}
		}

		/** @phpstan-var list<self|list<mixed>> */
		return $conditions;
	}


	public function getSql(): string
	{
		$this->prepareSql();
		\assert($this->sql !== NULL);
		return $this->sql;
	}


	/**
	 * @return list<mixed>
	 */
	public function getParams(): array
	{
		$this->prepareSql();
		return $this->params;
	}


	private function reset(): void
	{
		$this->sql = NULL;
		$this->params = [];
	}


	private function prepareSql(): void
	{
		if ($this->sql === NULL) {
			$this->sql = self::process($this, $this->params);
		}
	}


	/**
	 * @param list<mixed> $params
	 * @throws Exceptions\ConditionException
	 */
	private static function process(self $condition, array &$params): string
	{
		$conditions = $condition->getConditions();
		$withoutParentheses = \count($conditions) === 1;
		$processedConditions = [];
		foreach ($conditions as $conditionParams) {
			if ($conditionParams instanceof self) {
				$conditionExpression = \sprintf($withoutParentheses === TRUE ? '%s' : '(%s)', self::process($conditionParams, $params));
			} else {
				$conditionExpression = \array_shift($conditionParams);
				\assert(\is_string($conditionExpression)); // first array item is SQL, next are mixed params
				$cnt = \preg_match_all('/(?<!\\\\)\?/', $conditionExpression);
				$cntParams = \count($conditionParams);
				if (($cnt === 0) && ($cntParams === 1)) {
					$param = \reset($conditionParams);
					if (\is_array($param) || ($param instanceof Db\Sql)) {
						$conditionExpression .= ' IN (?)';
					} else if ($param === NULL) {
						$conditionExpression .= ' IS NULL';
						\array_shift($conditionParams);
					} else {
						$conditionExpression .= ' = ?';
					}
					$cnt = 1;
				}

				if ($cnt !== $cntParams) {
					throw Exceptions\ConditionException::badParamsCount($conditionExpression, $cnt, $cntParams);
				}

				if ($withoutParentheses === FALSE) {
					$conditionExpression = '(' . $conditionExpression . ')';
				}

				foreach ($conditionParams as $param) {
					$params[] = $param;
				}
			}

			$processedConditions[] = $conditionExpression;
		}

		return \implode(' ' . $condition->getType() . ' ', $processedConditions);
	}

}

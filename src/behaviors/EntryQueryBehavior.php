<?php

declare(strict_types=1);

namespace studioespresso\daterange\behaviors;

use Carbon\CarbonImmutable;
use Craft;
use craft\base\FieldInterface;
use craft\commerce\Plugin as Commerce;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\helpers\Db;
use DateTimeInterface;
use GraphQL\Exception\InvalidArgument;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

use function count;
use function is_array;
use function is_null;
use function is_object;
use function is_string;

/**
 * Class EntryQueryBehavior
 *
 * @property EntryQuery $owner
 */
class EntryQueryBehavior extends Behavior
{
    public $handle;

    public FieldInterface|bool $field = false;

    public bool $isFuture = false;
    public bool $isPast = false;
    public bool $isNotPast = false;
    public bool $isOnGoing = false;
    public bool $includeToday = false;

    public string|array|object|null $entryTypeHandle = null;

    public bool $isAfter = false;
    public bool $isBefore = false;
    public bool $isBetween = false;

    public DateTimeInterface|string|null $afterDate = null;
    public DateTimeInterface|string|null $beforeDate = null;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            ElementQuery::EVENT_AFTER_PREPARE => 'onAfterPrepare',
        ];
    }

    public function isFuture(
        mixed $value,
        string|array|object|bool|null $entryTypeHandle = null,
        bool $includeToday = false,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue($value, $entryTypeHandle, $includeToday);

        $this->handle = $value['handle'];
        $this->isFuture = true;
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->includeToday = $value['includeToday'];

        return $this->owner;
    }

    public function isPast(
        mixed $value,
        string|array|object|bool|null $entryTypeHandle = null,
        bool $includeToday = false,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue($value, $entryTypeHandle, $includeToday);

        $this->handle = $value['handle'];
        $this->isPast = true;
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->includeToday = $value['includeToday'];

        return $this->owner;
    }

    public function isNotPast(
        mixed $value,
        string|array|object|bool|null $entryTypeHandle = null,
        bool $includeToday = false,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue($value, $entryTypeHandle, $includeToday);

        $this->handle = $value['handle'];
        $this->isNotPast = true;
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->includeToday = $value['includeToday'];

        return $this->owner;
    }

    public function isOnGoing(
        mixed $value,
        string|array|object|bool|null $entryTypeHandle = null,
        bool $includeToday = false,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue($value, $entryTypeHandle, $includeToday);

        $this->handle = $value['handle'];
        $this->isOnGoing = true;
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->includeToday = $value['includeToday'];

        return $this->owner;
    }

    public function isAfter(
        mixed $value,
        string|array|object|null $entryTypeHandle = null,
        DateTimeInterface|string|null $date = null,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue2($value, $entryTypeHandle, $date);

        if (empty($value['date'])) {
            throw new InvalidArgumentException('Date argument is required for isAfter()');
        }

        $this->handle = $value['handle'];
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->isAfter = true;
        $this->afterDate = CarbonImmutable::parse($value['date']);

        return $this->owner;
    }

    public function isBefore(
        mixed $value,
        string|array|object|null $entryTypeHandle = null,
        DateTimeInterface|string|null $date = null,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue2($value, $entryTypeHandle, $date);

        if (empty($value['date'])) {
            throw new InvalidArgument('Date argument is required for isBefore()');
        }

        $this->handle = $value['handle'];
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->isBefore = true;
        $this->beforeDate = CarbonImmutable::parse($value['date']);

        return $this->owner;
    }

    public function isBetween(
        mixed $value,
        string|array|object|null $entryTypeHandle = null,
        DateTimeInterface|string|null $date = null,
        DateTimeInterface|string|null $date2 = null,
    ): EntryQuery {
        if (is_null($value)) {
            return $this->owner;
        }

        $value = $this->parseArgumentValue3($value, $entryTypeHandle, $date, $date2);

        if (empty($value['date']) || empty($value['date2'])) {
            throw new InvalidArgument('Two date arguments are required for isBetween()');
        }

        $this->handle = $value['handle'];
        $this->entryTypeHandle = $value['entryTypeHandle'];
        $this->isBetween = true;
        $this->beforeDate = CarbonImmutable::parse($value['date']);
        $this->afterDate = CarbonImmutable::parse($value['date2']);

        return $this->owner;
    }

    /**
     * @throws InvalidConfigException
     */
    public function onAfterPrepare(): void
    {
        if ($this->handle && !$this->entryTypeHandle) {
            throw new InvalidConfigException(
                'entryType not specified, see the Craft 5 upgrade guide on the changes required.'
            );
        }

        if ($this->handle && $this->entryTypeHandle) {
            $fieldsForTypes = [];
            $entryTypes = $this->getEntryTypes();

            foreach ($entryTypes as $typeHandle => $entryType) {
                $layout = Craft::$app->getFields()->getLayoutById($entryType->fieldLayoutId);
                $field = $layout?->getFieldByHandle($this->handle);
                if ($field) {
                    $fieldsForTypes[$typeHandle] = $field;
                }
            }

            // If we have fields to work with
            if (!empty($fieldsForTypes)) {
                $this->processDateQueries($fieldsForTypes);
            }
        }
    }

    /**
     * Get entry types from either handles or objects
     *
     * @return array Array of entry type objects indexed by handle
     * @throws InvalidConfigException
     */
    protected function getEntryTypes(): array
    {
        $entryTypes = [];

        // Convert to array if single value
        $types = is_array($this->entryTypeHandle) ? $this->entryTypeHandle : [$this->entryTypeHandle];

        foreach ($types as $key => $type) {
            // If it's an object with fieldLayoutId property, use it directly
            if (is_object($type) && property_exists($type, 'fieldLayoutId')) {
                $handle = property_exists($type, 'handle') ? $type->handle : "type_{$key}";
                $entryTypes[$handle] = $type;
            } elseif (is_string($type)) {
                // If it's a string, try to get the entry type
                $trimmedType = trim($type);
                // Try to get from Entry Types first
                $entryType = Craft::$app->entries->getEntryTypeByHandle($trimmedType);

                if ($entryType) {
                    $entryTypes[$trimmedType] = $entryType;
                } elseif (class_exists('craft\commerce\Plugin')) {
                    // If not found, try Commerce Product Types
                    $productType = Commerce::getInstance()->getProductTypes()->getProductTypeByHandle($trimmedType);

                    if ($productType) {
                        $entryTypes[$trimmedType] = $productType;
                    } else {
                        throw new InvalidConfigException("Invalid type specified: {$trimmedType}");
                    }
                }
            }
        }

        if (empty($entryTypes)) {
            throw new InvalidConfigException('No valid entry types were found');
        }

        return $entryTypes;
    }

    /**
     * @param FieldInterface[] $fieldsForTypes
     * @return void
     */
    protected function processDateQueries(array $fieldsForTypes): void
    {
        if (Craft::$app->db->getIsPgsql() || Craft::$app->db->getIsMysql()) {
            $or = ['or'];

            foreach ($fieldsForTypes as $field) {
                if ($this->isFuture) {
                    $or[] = Db::parseDateParam(
                        $field->getValueSql('start'),
                        date('Y-m-d'),
                        $this->includeToday ? '>=' : '>'
                    );
                }

                if ($this->isPast) {
                    $or[] = Db::parseDateParam(
                        $field->getValueSql('end'),
                        date('Y-m-d'),
                        $this->includeToday ? '<=' : '<'
                    );
                }

                if ($this->isNotPast) {
                    $or[] = Db::parseDateParam(
                        $field->getValueSql('end'),
                        date('Y-m-d'),
                        $this->includeToday ? '>=' : '>'
                    );
                }

                if ($this->isOnGoing) {
                    $and = ['and',
                        Db::parseDateParam(
                            $field->getValueSql('start'),
                            date('Y-m-d'),
                            $this->includeToday ? '<=' : '<'
                        ),
                        Db::parseDateParam(
                            $field->getValueSql('end'),
                            date('Y-m-d'),
                            $this->includeToday ? '>=' : '>'
                        ),
                    ];

                    $or[] = $and;
                }

                if ($this->isAfter) {
                    $or[] = Db::parseDateParam(
                        $field->getValueSql('start'),
                        $this->afterDate,
                        '>='
                    );

                    $or[] = Db::parseDateParam(
                        $field->getValueSql('end'),
                        $this->afterDate,
                        '>='
                    );
                }

                if ($this->isBefore) {
                    $or[] = Db::parseDateParam(
                        $field->getValueSql('start'),
                        $this->beforeDate,
                        '<='
                    );

                    $or[] = Db::parseDateParam(
                        $field->getValueSql('end'),
                        $this->beforeDate,
                        '<='
                    );
                }

                if ($this->isBetween) {
                    $betweenAnd = ['and'];

                    $betweenAnd[] = [
                        'or',
                        Db::parseDateParam(
                            $field->getValueSql('start'),
                            $this->beforeDate->format('c'),
                            '>='
                        ),
                        Db::parseDateParam(
                            $field->getValueSql('end'),
                            $this->beforeDate->format('c'),
                            '>='
                        ),
                    ];

                    $betweenAnd[] = [
                        'or',
                        Db::parseDateParam(
                            $field->getValueSql('start'),
                            $this->afterDate->format('c'),
                            '<='
                        ),
                        Db::parseDateParam(
                            $field->getValueSql('end'),
                            $this->afterDate->format('c'),
                            '<='
                        ),
                    ];

                    $or[] = $betweenAnd;
                }
            }

            // Only add the OR condition if we have more than just the 'or' element
            if (count($or) > 1) {
                $this->owner->subQuery->andWhere($or);
            }
        }
    }

    /**
     * @param string|array $value
     * @param string|array|object|bool|null $entryTypeHandle
     * @param bool $includeToday
     * @return array
     */
    protected function parseArgumentValue(
        string|array $value,
        string|array|object|bool|null $entryTypeHandle = null,
        bool $includeToday = false,
    ): array {
        if (is_array($value)) {
            $handle = $value[0] ?? null;
            $arg2 = $value[1] ?? null;

            if (is_string($arg2) || is_array($arg2) || is_object($arg2)) {
                $entryTypeHandle = $arg2;
            } elseif ($arg2 !== null) {
                $includeToday = $arg2;
            }
        } else {
            $handle = $value;
        }

        // If entryTypeHandle is a comma-separated string, convert it to an array
        if (is_string($entryTypeHandle) && str_contains($entryTypeHandle, ',')) {
            $entryTypeHandle = array_map('trim', explode(',', $entryTypeHandle));
        }

        return [
            'handle' => $handle,
            'entryTypeHandle' => $entryTypeHandle,
            'includeToday' => $includeToday,
        ];
    }

    /**
     * @param string|array $value
     * @param string|array|object|bool|null $entryTypeHandle
     * @param string|DateTimeInterface|null $date
     * @return array
     */
    protected function parseArgumentValue2(
        string|array $value,
        string|array|object|bool|null $entryTypeHandle = null,
        string|DateTimeInterface|null $date = null,
    ): array {
        if (is_array($value)) {
            $handle = $value[0] ?? null;
            $entryTypeHandle = $value[1] ?? null;
            $date = $value[2] ?? null;
        } else {
            $handle = $value;
        }

        // If entryTypeHandle is a comma-separated string, convert it to an array
        if (is_string($entryTypeHandle) && str_contains($entryTypeHandle, ',')) {
            $entryTypeHandle = array_map('trim', explode(',', $entryTypeHandle));
        }

        return [
            'handle' => $handle,
            'entryTypeHandle' => $entryTypeHandle,
            'date' => $date,
        ];
    }

    /**
     * @param string|array $value
     * @param string|array|object|bool|null $entryTypeHandle
     * @param string|DateTimeInterface|null $date
     * @param string|DateTimeInterface|null $date2
     * @return array
     */
    protected function parseArgumentValue3(
        string|array $value,
        string|array|object|bool|null $entryTypeHandle = null,
        string|DateTimeInterface|null $date = null,
        string|DateTimeInterface|null $date2 = null,
    ): array {
        if (is_array($value)) {
            $handle = $value[0] ?? null;
            $entryTypeHandle = $value[1] ?? null;
            $date = $value[2] ?? null;
            $date2 = $value[3] ?? null;
        } else {
            $handle = $value;
        }

        // If entryTypeHandle is a comma-separated string, convert it to an array
        if (is_string($entryTypeHandle) && str_contains($entryTypeHandle, ',')) {
            $entryTypeHandle = array_map('trim', explode(',', $entryTypeHandle));
        }

        return [
            'handle' => $handle,
            'entryTypeHandle' => $entryTypeHandle,
            'date' => $date,
            'date2' => $date2,
        ];
    }
}

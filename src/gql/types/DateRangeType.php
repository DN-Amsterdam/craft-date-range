<?php

declare(strict_types=1);

namespace studioespresso\daterange\gql\types;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use studioespresso\daterange\fields\data\DateRangeData;

/**
 * @author    Studio Espresso
 * @package   DateRange
 * @since     1.3.0
 */
class DateRangeType extends ObjectType
{
    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var DateRangeData $source */
        $fieldName = $resolveInfo->fieldName;

        return match ($fieldName) {
            'start' => $source->start,
            'end' => $source->end,
            'isPast' => $source->isPast,
            'isNotPast' => $source->isNotPast,
            'isFuture' => $source->isFuture,
            'isOnGoing' => $source->isOngoing,
            default => $source->{$fieldName},
        };
    }
}

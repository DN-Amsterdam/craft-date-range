<?php

declare(strict_types=1);

namespace studioespresso\daterange\validators;

use Craft;
use yii\validators\Validator;

class EndDateValidator extends Validator
{
    /**
     * @param mixed $value
     * @return array|null
     */
    protected function validateValue(mixed $value): ?array
    {
        if ($value->start->format('U') > $value->end->format('U')) {
            return [Craft::t('date-range', 'End date must be after start date'), []];
        }

        return null;
    }
}

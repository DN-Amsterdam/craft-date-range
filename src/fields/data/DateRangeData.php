<?php

declare(strict_types=1);

namespace studioespresso\daterange\fields\data;

use Craft;
use craft\base\Serializable;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use DateTime;
use DateTimeInterface;
use yii\base\BaseObject;

use function is_array;

class DateRangeData extends BaseObject implements Serializable
{
    public DateTimeInterface $start;

    public DateTimeInterface $end;

    public bool $isFuture;

    public bool $isOngoing;

    public bool $isPast;

    public bool $isNotPast;

    /**
     * @throws \Exception
     */
    public function __construct($value = null, $config = [])
    {
        $this->start = $value['start'];
        $this->end = $value['end'] ?: $value['start'];
        $this->isFuture = $this->getIsFuture();
        $this->isOngoing = $this->getIsOngoing();
        $this->isPast = $this->getIsPast();
        $this->isNotPast = $this->getIsNotPast();

        parent::__construct($config);
    }

    public function serialize(): array
    {
        return [$this->start, $this->end];
    }

    public function getFormatted($format = 'd/m/Y', $seperator = ' - ', $locale = null)
    {
        $dateFormat = "d/m/Y";
        $timeFormat = "H:i:s";

        if (is_array($format)) {
            if (isset($format['date'])) {
                $dateFormat = $format['date'];
            }

            if (isset($format['time'])) {
                $timeFormat = $format['time'];
            }

            $format = (
                $dateFormat
                ) . ' ' . (
                $timeFormat
                );
        } else {
            $format = $format;
        }
        $string = '';
        $formatter = $locale ? (new Locale($locale))->getFormatter() : Craft::$app->getFormatter();

        if ($this->start->format('U') === $this->end->format('U')) {
            $string = $formatter->asDate($this->start, "php:$format");
        } elseif ($this->start->format('dmy') === $this->end->format('dmy') && $timeFormat) {
            $string .= $formatter->asDate($this->start, "php:$dateFormat");
            $string .= " ";
            $string .= $formatter->asTime($this->start, "php:$timeFormat");
            $string .= " " . trim($seperator) . " ";
            $string .= $formatter->asTime($this->end, "php:$timeFormat");
        } else {
            $string .= $formatter->asDate($this->start, "php:$format");
            $string .= " " . trim($seperator) . " ";
            $string .= $formatter->asDate($this->end, "php:$format");
        }
        return $string;
    }


    /**
     * @return bool
     * @throws \Exception
     */
    public function getIsFuture(): bool
    {
        $now = new DateTime();

        if ($this->start->format('U') > $now->format('U')) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function getIsOngoing(): bool
    {
        $now = new DateTime();

        if (
            $this->start->format('U') < $now->format('U')
            && $this->end->format('U') > $now->format('U')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function getIsPast(): bool
    {
        $now = new DateTime();

        if ($this->end->format('U') < $now->format('U')) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function getIsNotPast(): bool
    {
        $now = new DateTime();

        if ($this->end->format('U') > $now->format('U')) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public static function normalize($value): false|array
    {
        if (!is_array($value)) {
            $value = Json::decode($value);
        }

        if (isset($value['start']['date']) && !$value['start']['date']) {
            return false;
        }

        if (isset($value['end']['date']) && !$value['end']['date']) {
            $value['end']['date'] = $value['start']['date'];
            $value['end']['time'] = '23:59';
        }

        $start = DateTimeHelper::toDateTime($value['start']);
        $end = DateTimeHelper::toDateTime($value['end']);

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}

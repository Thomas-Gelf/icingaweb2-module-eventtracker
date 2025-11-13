<?php

namespace Icinga\Module\Eventtracker\Reporting;

use gipfl\Format\LocalDateFormat;
use gipfl\Translation\TranslationHelper;
use RuntimeException;

class AggregationPeriodTitle
{
    use TranslationHelper;

    protected static ?LocalDateFormat $dateFormatter = null;
    protected string $aggregation;

    public function __construct(string $aggregation)
    {
        $this->aggregation = $aggregation;
    }

    public function getTranslated($key): string
    {
        switch ($this->aggregation) {
            case AggregationPeriod::HOURLY:
                return sprintf('%02d:00 - %02d-00', (int) $key - 1, (int) $key);
            case AggregationPeriod::WEEKLY:
                return sprintf($this->translate('CW %s'), (int) $key);
            case AggregationPeriod::WEEKDAY:
                return $this->getWeekdayName((int) $key);
            case AggregationPeriod::DAILY:
                return (self::$dateFormatter ??= new LocalDateFormat())->getFullDay(new \DateTimeImmutable($key));
            case AggregationPeriod::MONTHLY:
                return $this->getMonthName($key);
            default:
                throw new RuntimeException("Invalid aggregation: $this->aggregation");
        }
    }

    protected function getWeekdayName(int $key): string
    {
        switch ($key) {
            case 1:
                return $this->translate('Monday');
            case 2:
                return $this->translate('Tuesday');
            case 3:
                return $this->translate('Wednesday');
            case 4:
                return $this->translate('Thursday');
            case 5:
                return $this->translate('Friday');
            case 6:
                return $this->translate('Saturday');
            case 7:
                return $this->translate('Sunday');
            default:
                throw new RuntimeException("Invalid weekday: $key");
        }
    }

    protected function getMonthName(string $key): string
    {
        $year = substr($key, 0, 4);
        $key = (int) substr($key, 4);
        switch ($key) {
            case 1:
                $month = $this->translate('January');
                break;
            case 2:
                $month = $this->translate('February');
                break;
            case 3:
                $month = $this->translate('March');
                break;
            case 4:
                $month = $this->translate('April');
                break;
            case 5:
                $month = $this->translate('May');
                break;
            case 6:
                $month = $this->translate('June');
                break;
            case 7:
                $month = $this->translate('July');
                break;
            case 8:
                $month = $this->translate('August');
                break;
            case 9:
                $month = $this->translate('September');
                break;
            case 10:
                $month = $this->translate('October');
                break;
            case 11:
                $month = $this->translate('November');
                break;
            case 12:
                $month = $this->translate('December');
                break;
            default:
                throw new RuntimeException("Invalid month: $key");
        }

        return sprintf('%s %s', $month, $year);
    }
}

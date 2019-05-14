<?php

namespace Icinga\Module\Eventtracker;

use Countable;
use gipfl\IcingaWeb2\Url;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use InvalidArgumentException;
use Zend_Db_Adapter_Abstract as DbAdapter;

class SetOfIncidents implements Countable
{
    /** DbAdapter $db */
    protected $db;

    /** @var Incident[] */
    protected $incidents = [];

    public function __construct(DbAdapter $db, $incidents = [])
    {
        $this->db = $db;
        foreach ($incidents as $incident) {
            $this->addIncident($incident);
        }
    }

    public function getIncidents()
    {
        return $this->incidents;
    }

    public function addIncident(Incident $incident)
    {
        $this->incidents[] = $incident;

        return $this;
    }

    protected function addFromUrl(Url $url)
    {
        $filter = Filter::fromQueryString($url->getQueryString());
        if ($filter instanceof FilterOr && $filter->listFilteredColumns() === ['uuid']) {
            foreach ($filter->filters() as $part) {
                if ($part instanceof FilterAnd) {
                    $sub = $part->filters()[0];
                    if ($sub instanceof FilterExpression) {
                        $expression = $sub->getExpression();
                        $this->incidents[] = Incident::load(Uuid::toBinary($expression), $this->db);
                    } else {
                        throw new InvalidArgumentException('Could not extract Incident Set from URL');
                    }
                } else {
                    throw new InvalidArgumentException('Could not extract Incident Set from URL');
                }
            }
        } else {
            throw new InvalidArgumentException('Could not extract Incident Set from URL');
        }
    }

    public static function fromUrl(Url $url, DbAdapter $db)
    {
        $set = new static($db);
        $set->addFromUrl($url);

        return $set;
    }

    public function count()
    {
        return count($this->incidents);
    }
}

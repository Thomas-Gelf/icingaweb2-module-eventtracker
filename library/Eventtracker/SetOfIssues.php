<?php

namespace Icinga\Module\Eventtracker;

use Countable;
use gipfl\IcingaWeb2\Url;
use gipfl\ZfDb\Adapter\Adapter as DbAdapter;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use InvalidArgumentException;

class SetOfIssues implements Countable
{
    /** DbAdapter $db */
    protected $db;

    /** @var Issue[] */
    protected $issues = [];

    public function __construct(DbAdapter $db, $issues = [])
    {
        $this->db = $db;
        foreach ($issues as $issue) {
            $this->addIssue($issue);
        }
    }

    public function getIssues()
    {
        return $this->issues;
    }

    public function addIssue(Issue $issue)
    {
        $this->issues[] = $issue;

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
                        $this->issues[] = Issue::load(Uuid::toBinary($expression), $this->db);
                    } else {
                        throw new InvalidArgumentException('Could not extract Issue Set from URL');
                    }
                } else {
                    throw new InvalidArgumentException('Could not extract Issue Set from URL');
                }
            }
        } else {
            throw new InvalidArgumentException('Could not extract Issue Set from URL');
        }
    }

    public function getUuids(): array
    {
        $uuids = [];

        foreach ($this->issues as $issue) {
            $uuids[] = $issue->getUuid();
        }

        return $uuids;
    }

    public function getWorstSeverity()
    {
        $severity = Severity::DEBUG;
        foreach ($this->issues as $issue) {
            $severity = Severity::max($severity, $issue->get('severity'));
        }

        return $severity;
    }

    public static function fromUrl(Url $url, DbAdapter $db)
    {
        $set = new static($db);
        $set->addFromUrl($url);

        return $set;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->issues);
    }
}

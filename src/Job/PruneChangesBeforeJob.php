<?php

namespace Symbiote\DataChange\Job;

use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\DataChange\Model\DataChangeRecord;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

/**
 * A scheduled regular prune of _old_ data change records
 *
 * @author marcus
 */
class PruneChangesBeforeJob extends AbstractQueuedJob
{

    protected ?DBDatetime $pruneBefore = null;

    protected string $priorTo = '-3 months';

    public function __construct(string $priorTo = '-3 months', protected int $repeatAfter = 86400)
    {
        $pruneBefore = DBDatetime::now();
        $this->priorTo = trim($priorTo);
        $this->pruneBefore = $this->priorTo !== '' ? $pruneBefore->modify($this->priorTo) : null;

    }

    public function getTitle()
    {
        if($this->pruneBefore instanceof DBDatetime) {
            return "Prune data change track entries before " . $this->pruneBefore->Format(DBDatetime::ISO_DATETIME);
        } else {
            return "Prune data change track entries - specify a date!";
        }
    }

    public function process()
    {
        if($this->pruneBefore instanceof DBDatetime) {
            $this->addMessage("Pruning datachange records before " . $this->pruneBefore->Format(DBDatetime::ISO_DATETIME));
            $affectedRows = DataChangeRecord::pruneChangesBefore($this->pruneBefore);
            $this->addMessage("Pruned {$affectedRows} datachange record(s)");
            $this->isComplete = true;
        } else {
            throw new \RuntimeException("Specify a valid first argument to the job as a value strotime() can parse");
        }
    }

    public function afterComplete()
    {
        if($this->repeatAfter > 0) {
            $job = new PruneChangesBeforeJob($this->priorTo, $this->repeatAfter);
            $next = DBDatetime::now();
            $next = $next->Modify("+{$this->repeatAfter} seconds");
            Injector::inst()->get(QueuedJobService::class)->queueJob(
                $job, $next->Format(DBDatetime::ISO_DATETIME)
            );
        }
    }
}

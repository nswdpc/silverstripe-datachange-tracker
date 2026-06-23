<?php

namespace Symbiote\DataChange\Job;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use Symbiote\DataChange\Model\DataChangeRecord;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 *
 *
 * @author <marcus@symbiote.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CleanupDataChangeHistoryTask extends BuildTask
{

    private static bool $is_enabled = false;

    protected static string $commandName = 'CleanupDataChangeHistoryTask';

    protected string $title = 'Remove old datachange records';

    protected static string $description = 'Remove old datachange records';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        try {
            $confirm = (bool) $input->getOption('run');
            $since = $input->getOption('older');

            if (!$since || !is_string($since)) {
                throw new \RuntimeException("Please specify an 'older' param with a date older than which to prune (in strtotime friendly format)");
            }

            /** @var DBDatetime $olderThan */
            $olderThan = DBField::create_field(DBDatetime::class, $since);
            $output->writeln("Pruning records older than " . $olderThan->Format(DBDatetime::ISO_DATETIME));

            if ($confirm) {
                $affectedRows = DataChangeRecord::pruneChangesBefore($olderThan);
                $output->writeln("Pruned {$affectedRows} records");
            } else {
                $output->writeln("Dry run performed, please supply the run=1 parameter to actually execute the deletion!");
            }

            return Command::SUCCESS;
        } catch (\RuntimeException $runtimeException) {
            $output->writeln($runtimeException->getMessage());
            return Command::FAILURE;
        } catch (\Exception) {
            $output->writeln("General exception error");
            return Command::FAILURE;
        }
    }
}

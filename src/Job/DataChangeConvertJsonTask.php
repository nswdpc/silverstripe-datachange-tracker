<?php

namespace Symbiote\DataChange\Job;

use Symbiote\DataChange\Model\DataChangeRecord;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author marcus
 */
class DataChangeConvertJsonTask extends BuildTask
{

    private static bool $is_enabled = false;

    protected static string $commandName = 'DataChangeConvertJsonTask';

    protected string $title = 'Convert datachange records to JSON format';

    protected static string $description = 'Convert datachange records to JSON format';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $confirm = (bool) $input->getOption('run');
        if ($confirm) {
            try {
                // load all items and convert 'before' and 'after' to json if their serialize returns a value
                $records = DataChangeRecord::get();
                foreach ($records as $record) {
                    $before = @unserialize($record->Before);
                    $after =  @unserialize($record->After);

                    if ($before || $after) {
                        $record->Before = json_encode($before);
                        $record->After = json_encode($after);
                        $record->write();
                        $output->writeln("Updated {$record->Title} (#{$record->ID})");
                    }
                }

                return Command::SUCCESS;
            } catch (\Exception) {
                $output->writeln("Failed - general exception thrown");
                return Command::FAILURE;
            }
        } else {
            $output->writeln("Pass a 'run' param to confirm you want to do this");
            return Command::FAILURE;
        }
    }
}

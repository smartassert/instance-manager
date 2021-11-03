<?php

namespace App\Command;

use App\Model\AssignedIp;
use App\Services\FloatingIpRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpGetCommand::NAME,
    description: 'Get the current floating IP',
)]
class IpGetCommand extends Command
{
    public const NAME = 'app:ip:get';
    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;

    public function __construct(
        private FloatingIpRepository $floatingIpRepository,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_COLLECTION_TAG,
                null,
                InputOption::VALUE_REQUIRED,
                'Tag applied to all instances'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collectionTag = $this->getStringOption(self::OPTION_COLLECTION_TAG, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . self::OPTION_COLLECTION_TAG . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $assignedIp = $this->floatingIpRepository->find($collectionTag);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($assignedIp->getIp());
        }

        return Command::SUCCESS;
    }

    private function getStringOption(string $name, InputInterface $input): string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            $value = '';
        }

        return trim($value);
    }
}

<?php

namespace App\Command;

use App\Services\InstanceRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceActionCommand extends Command
{
    private const OPTION_ID = 'id';
    private ?int $id;

    public function __construct(
        protected InstanceRepository $instanceRepository,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_ID, null, InputOption::VALUE_REQUIRED, 'ID of the instance.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption(self::OPTION_ID);
        $this->id = is_int($id) || is_string($id) && ctype_digit($id) ? (int) $id : null;

        return Command::SUCCESS;
    }

    protected function getId(): ?int
    {
        return $this->id;
    }
}

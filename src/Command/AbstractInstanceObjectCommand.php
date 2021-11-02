<?php

namespace App\Command;

use App\Model\Instance;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstanceObjectCommand extends AbstractInstanceActionCommand
{
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    private Instance $instance;

    public function __construct(
        InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
    ) {
        parent::__construct($instanceRepository);
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $id = $this->getId();
        if (null === $id) {
            $output->write($this->outputFactory->createErrorOutput('id-invalid'));

            return self::EXIT_CODE_ID_INVALID;
        }

        $instance = $this->instanceRepository->find($id);
        if ($instance instanceof Instance) {
            $this->instance = $instance;
        } else {
            $output->write($this->outputFactory->createErrorOutput('not-found', ['id' => $id]));

            return self::EXIT_CODE_NOT_FOUND;
        }

        $this->instance = $instance;

        return Command::SUCCESS;
    }

    protected function getInstance(): Instance
    {
        return $this->instance;
    }
}

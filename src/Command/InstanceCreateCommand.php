<?php

namespace App\Command;

use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceCreateCommand::NAME,
    description: 'Create an instance',
)]
class InstanceCreateCommand extends Command
{
    public const NAME = 'app:instance:create';
    public const OPTION_POST_CREATE_SCRIPT = 'post-create-script';

    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;
    public const EXIT_CODE_EMPTY_TAG = 4;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator
            ->addCollectionTagOption($this)
            ->addImageIdOption($this)
        ;

        $this
            ->addOption(
                self::OPTION_POST_CREATE_SCRIPT,
                null,
                InputOption::VALUE_REQUIRED,
                'Script to call once creation is complete'
            )
        ;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collectionTag = $this->inputReader->getTrimmedStringOption(CommandConfigurator::OPTION_COLLECTION_TAG, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . CommandConfigurator::OPTION_COLLECTION_TAG . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $imageId = $this->inputReader->getTrimmedStringOption(CommandConfigurator::OPTION_IMAGE_ID, $input);
        if ('' === $imageId) {
            $output->writeln('"' . CommandConfigurator::OPTION_IMAGE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_TAG;
        }

        $postCreateScript = $this->inputReader->getTrimmedStringOption(self::OPTION_POST_CREATE_SCRIPT, $input);

        $instance = $this->instanceRepository->findCurrent($collectionTag, $imageId);
        if (null === $instance) {
            $instance = $this->instanceRepository->create($collectionTag, $imageId, $postCreateScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }
}

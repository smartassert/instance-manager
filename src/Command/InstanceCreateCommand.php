<?php

namespace App\Command;

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
    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const OPTION_IMAGE_ID = 'image-id';
    public const OPTION_POST_CREATE_SCRIPT = 'post-create-script';

    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;
    public const EXIT_CODE_EMPTY_TAG = 4;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
    ) {
        parent::__construct();
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
            ->addOption(
                self::OPTION_IMAGE_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'ID of image (snapshot) to create from'
            )
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
        $collectionTag = $this->getStringOption(self::OPTION_COLLECTION_TAG, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . self::OPTION_COLLECTION_TAG . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $imageId = $this->getStringOption(self::OPTION_IMAGE_ID, $input);
        if ('' === $imageId) {
            $output->writeln('"' . self::OPTION_IMAGE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_TAG;
        }

        $postCreateScript = $this->getStringOption(self::OPTION_POST_CREATE_SCRIPT, $input);

        $instance = $this->instanceRepository->findCurrent($collectionTag, $imageId);
        if (null === $instance) {
            $instance = $this->instanceRepository->create($collectionTag, $imageId, $postCreateScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

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

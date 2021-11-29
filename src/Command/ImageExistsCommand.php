<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\ImageRepository;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: ImageExistsCommand::NAME,
    description: 'Check if an image exists',
)]
class ImageExistsCommand extends Command
{
    public const NAME = 'app:image:exists';

    public const EXIT_CODE_EMPTY_SERVICE_ID = 3;
    public const EXIT_CODE_MISSING_IMAGE_ID = 4;

    public function __construct(
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
        private ImageRepository $imageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $serviceId) {
            $output->writeln('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_SERVICE_ID;
        }

        $imageId = $this->serviceConfiguration->getImageId($serviceId);
        if (null === $imageId) {
            $output->writeln('image_id missing');

            return self::EXIT_CODE_MISSING_IMAGE_ID;
        }

        return $this->imageRepository->exists($imageId) ? Command::SUCCESS : Command::FAILURE;
    }
}

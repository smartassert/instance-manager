<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ServiceIdMissingException;
use App\Services\CommandConfigurator;
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
class ImageExistsCommand extends AbstractServiceCommand
{
    public const NAME = 'app:image:exists';

    public const EXIT_CODE_MISSING_IMAGE_ID = 4;

    public function __construct(
        CommandConfigurator $configurator,
        private ServiceConfiguration $serviceConfiguration,
        private ImageRepository $imageRepository,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
        $imageId = $this->serviceConfiguration->getImageId($serviceId);
        if (null === $imageId) {
            $output->writeln('image_id missing');

            return self::EXIT_CODE_MISSING_IMAGE_ID;
        }

        return $this->imageRepository->exists($imageId) ? Command::SUCCESS : Command::FAILURE;
    }
}

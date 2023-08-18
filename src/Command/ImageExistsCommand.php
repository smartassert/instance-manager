<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\RequiredOptionMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\CommandConfigurator;
use App\Services\ImageIdLoaderInterface;
use App\Services\ImageRepository;
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

    public function __construct(
        CommandConfigurator $configurator,
        private ImageRepository $imageRepository,
        private ImageIdLoaderInterface $imageIdLoader,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws RequiredOptionMissingException
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
        $imageId = $this->imageIdLoader->load($serviceId);

        return $this->imageRepository->exists($imageId) ? Command::SUCCESS : Command::FAILURE;
    }
}

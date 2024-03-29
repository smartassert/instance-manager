<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\RequiredOptionMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\BootScriptFactory;
use App\Services\CommandConfigurator;
use App\Services\ImageIdLoaderInterface;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use App\Services\ServiceEnvironmentVariableRepository;
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
class InstanceCreateCommand extends AbstractServiceCommand
{
    public const NAME = 'app:instance:create';
    public const OPTION_FIRST_BOOT_SCRIPT = 'first-boot-script';
    public const OPTION_SECRETS_JSON = 'secrets-json';

    public const EXIT_CODE_FIRST_BOOT_SCRIPT_INVALID = 5;

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
        private BootScriptFactory $bootScriptFactory,
        private ServiceEnvironmentVariableRepository $environmentVariableRepository,
        private ImageIdLoaderInterface $imageIdLoader,
    ) {
        parent::__construct($configurator);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                self::OPTION_FIRST_BOOT_SCRIPT,
                null,
                InputOption::VALUE_REQUIRED,
                'Script to call once creation is complete'
            )
            ->addOption(
                self::OPTION_SECRETS_JSON,
                null,
                InputOption::VALUE_REQUIRED,
                'JSON-object of key:value secrets'
            )
        ;
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

        $instance = $this->instanceRepository->findCurrent($serviceId, $imageId);
        if (null === $instance) {
            $secretsOption = $input->getOption(self::OPTION_SECRETS_JSON);
            $secretsOption = is_string($secretsOption) ? $secretsOption : '';
            $environmentVariables = $this->environmentVariableRepository->getCollection($serviceId, $secretsOption);

            $firstBootScriptOption = $input->getOption(self::OPTION_FIRST_BOOT_SCRIPT);
            $firstBootScriptOption = is_string($firstBootScriptOption) ? trim($firstBootScriptOption) : '';

            $firstBootScript = $this->bootScriptFactory->create($environmentVariables, $firstBootScriptOption);

            if (false === $this->bootScriptFactory->validate($firstBootScript)) {
                $output->writeln('First boot script is invalid:');
                $output->write($firstBootScript);

                return self::EXIT_CODE_FIRST_BOOT_SCRIPT_INVALID;
            }

            $instance = $this->instanceRepository->create($serviceId, $imageId, $firstBootScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }
}

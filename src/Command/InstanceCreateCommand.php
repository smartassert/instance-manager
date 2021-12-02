<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\MissingSecretException;
use App\Model\EnvironmentVariable;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceRepository;
use App\Services\KeyValueCollectionFactory;
use App\Services\OutputFactory;
use App\Services\SecretHydrator;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Exception\ExceptionInterface;
use Doctrine\Common\Collections\Collection;
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
    public const OPTION_FIRST_BOOT_SCRIPT = 'first-boot-script';
    public const OPTION_SECRETS_JSON = 'secrets-json';

    public const EXIT_CODE_EMPTY_SERVICE_ID = 3;
    public const EXIT_CODE_MISSING_IMAGE_ID = 4;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
        private KeyValueCollectionFactory $keyValueCollectionFactory,
        private SecretHydrator $secretHydrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configurator->addServiceIdOption($this);

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

        $instance = $this->instanceRepository->findCurrent($serviceId, $imageId);
        if (null === $instance) {
            $environmentVariables = $this->serviceConfiguration->getEnvironmentVariables($serviceId);
            $secretsOption = $input->getOption(self::OPTION_SECRETS_JSON);

            if (is_string($secretsOption) && '' !== $secretsOption) {
                $secrets = $this->keyValueCollectionFactory->createFromJsonForKeysMatchingPrefix(
                    strtoupper($serviceId),
                    $secretsOption
                );

                foreach ($environmentVariables as $index => $environmentVariable) {
                    $mutatedEnvironmentVariable = $this->secretHydrator->hydrate($environmentVariable, $secrets);

                    if (
                        $mutatedEnvironmentVariable instanceof EnvironmentVariable
                        && false === $mutatedEnvironmentVariable->equals($environmentVariable)
                    ) {
                        $environmentVariables->set($index, $mutatedEnvironmentVariable);
                    }
                }
            }

            foreach ($environmentVariables as $environmentVariable) {
                $secretPlaceholder = $environmentVariable->getSecretPlaceholder();

                if (null !== $secretPlaceholder) {
                    throw new MissingSecretException($secretPlaceholder);
                }
            }

            $firstBootScript = $this->createFirstBootScript(
                $environmentVariables,
                $this->inputReader->getTrimmedStringOption(self::OPTION_FIRST_BOOT_SCRIPT, $input)
            );

            $instance = $this->instanceRepository->create($serviceId, $imageId, $firstBootScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }

    /**
     * @param Collection<int, EnvironmentVariable> $environmentVariables
     */
    private function createFirstBootScript(Collection $environmentVariables, string $serviceFirstBootScript): string
    {
        $script = '#!/usr/bin/env bash' . "\n";

        foreach ($environmentVariables as $environmentVariable) {
            $script .= 'export ' . $environmentVariable . "\n";
        }
        $script = trim($script);

        if ('' !== $script && '' !== $serviceFirstBootScript) {
            $script .= "\n";
        }

        return $script . $serviceFirstBootScript;
    }
}

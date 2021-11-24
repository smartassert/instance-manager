<?php

namespace App\Command;

use App\Exception\MissingSecretException;
use App\Model\EnvironmentVariableList;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\InstanceRepository;
use App\Services\KeyValueCollectionFactory;
use App\Services\OutputFactory;
use App\Services\SecretCollectionHydrator;
use App\Services\ServiceConfiguration;
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
    public const OPTION_FIRST_BOOT_SCRIPT = 'first-boot-script';
    public const OPTION_SECRETS_JSON = 'secrets-json';

    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 3;
    public const EXIT_CODE_EMPTY_TAG = 4;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
        private KeyValueCollectionFactory $keyValueCollectionFactory,
        private SecretCollectionHydrator $secretCollectionHydrator,
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
        $collectionTag = $this->inputReader->getTrimmedStringOption(Option::OPTION_SERVICE_ID, $input);
        if ('' === $collectionTag) {
            $output->writeln('"' . Option::OPTION_SERVICE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_COLLECTION_TAG;
        }

        $imageId = $this->inputReader->getTrimmedStringOption(Option::OPTION_IMAGE_ID, $input);
        if ('' === $imageId) {
            $output->writeln('"' . Option::OPTION_IMAGE_ID . '" option empty');

            return self::EXIT_CODE_EMPTY_TAG;
        }

        $instance = $this->instanceRepository->findCurrent($collectionTag, $imageId);
        if (null === $instance) {
            $environmentVariables = $this->serviceConfiguration->getEnvironmentVariables($collectionTag);
            $secretsOption = $input->getOption(self::OPTION_SECRETS_JSON);

            if (is_string($secretsOption) && '' !== $secretsOption) {
                $secrets = $this->keyValueCollectionFactory->createFromJsonForKeysMatchingPrefix(
                    strtoupper($collectionTag),
                    $secretsOption
                );

                $environmentVariables = $this->secretCollectionHydrator->hydrate(
                    $environmentVariables,
                    $secrets
                );
            }

            foreach ($environmentVariables as $environmentVariable) {
                $secretPlaceholder = $environmentVariable->getSecretPlaceholder();

                if (null !== $secretPlaceholder) {
                    throw new MissingSecretException($secretPlaceholder);
                }
            }

            if (false === $environmentVariables instanceof EnvironmentVariableList) {
                $environmentVariables = new EnvironmentVariableList([]);
            }

            $firstBootScript = $this->createFirstBootScript(
                $environmentVariables,
                $this->inputReader->getTrimmedStringOption(self::OPTION_FIRST_BOOT_SCRIPT, $input)
            );

            $instance = $this->instanceRepository->create($collectionTag, $imageId, $firstBootScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }

    private function createFirstBootScript(
        EnvironmentVariableList $environmentVariables,
        string $serviceFirstBootScript
    ): string {
        $script = '';

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

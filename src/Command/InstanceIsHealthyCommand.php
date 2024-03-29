<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UrlKey;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\InstanceNotFoundException;
use App\Exception\RequiredOptionMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Services\CommandActionRunner;
use App\Services\CommandConfigurator;
use App\Services\CommandInstanceRepository;
use App\Services\InstanceClient;
use App\Services\UrlLoaderInterface;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsHealthyCommand::NAME,
    description: 'Perform instance health check',
)]
class InstanceIsHealthyCommand extends AbstractServiceCommand
{
    use RetryableCommandTrait;

    public const NAME = 'app:instance:is-healthy';

    public const OPTION_RETRY_LIMIT = 'retry-limit';
    public const OPTION_RETRY_DELAY = 'retry-delay';
    public const DEFAULT_RETRY_LIMIT = 5;
    public const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceClient $instanceClient,
        private CommandActionRunner $commandActionRunner,
        private CommandInstanceRepository $commandInstanceRepository,
        private UrlLoaderInterface $urlLoader,
    ) {
        parent::__construct($configurator);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->configurator
            ->addId($this)
            ->addRetryLimitOption($this, self::DEFAULT_RETRY_LIMIT)
            ->addRetryDelayOption($this, self::DEFAULT_RETRY_DELAY)
        ;
    }

    /**
     * @throws ExceptionInterface
     * @throws RequiredOptionMissingException
     * @throws ServiceConfigurationMissingException
     * @throws ConfigurationFileValueMissingException
     * @throws InstanceNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
        $healthCheckUrl = $this->urlLoader->load($serviceId, UrlKey::HEALTH_CHECK);
        if ('' === $healthCheckUrl) {
            return Command::SUCCESS;
        }

        $instance = $this->commandInstanceRepository->get($input);

        $result = $this->commandActionRunner->run(
            $this->getRetryLimit($input),
            $this->getRetryDelay($input),
            $output,
            function (bool $isLastAttempt) use ($healthCheckUrl, $output, $instance): bool {
                $response = $this->instanceClient->getHealth($healthCheckUrl, $instance);
                $isHealthy = 200 === $response->getStatusCode();

                $output->write($response->getBody()->getContents());

                if (false === $isHealthy && false === $isLastAttempt) {
                    $output->writeln('');
                }

                return $isHealthy;
            }
        );

        return true === $result ? Command::SUCCESS : Command::FAILURE;
    }
}

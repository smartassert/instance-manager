<?php

declare(strict_types=1);

namespace App\Command;

use App\ActionHandler\ActionHandler;
use App\Exception\ActionTimeoutException;
use App\Model\Instance;
use App\Services\ActionRepository;
use App\Services\ActionRunner;
use App\Services\CommandConfigurator;
use App\Services\CommandInputReader;
use App\Services\FloatingIpManager;
use App\Services\FloatingIpRepository;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use App\Services\ServiceConfiguration;
use DigitalOceanV2\Entity\Action as ActionEntity;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: FooIpAssignCommand::NAME,
    description: 'Assign a floating IP to current instance',
)]
class FooIpAssignCommand extends Command
{
    public const NAME = 'foo:ip:assign';

    public const EXIT_CODE_NO_CURRENT_INSTANCE = 3;
    public const EXIT_CODE_ACTION_TIMED_OUT = 5;
    public const EXIT_CODE_EMPTY_SERVICE_ID = 6;
    public const EXIT_CODE_MISSING_IMAGE_ID = 7;

    private const MICROSECONDS_PER_SECOND = 1000000;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private FloatingIpManager $floatingIpManager,
        private ActionRepository $actionRepository,
        private FloatingIpRepository $floatingIpRepository,
        private ActionRunner $actionRunner,
        private OutputFactory $outputFactory,
        private CommandConfigurator $configurator,
        private CommandInputReader $inputReader,
        private ServiceConfiguration $serviceConfiguration,
        private int $assigmentTimeoutInSeconds,
        private int $assignmentRetryInSeconds,
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

        $instance = $this->instanceRepository->findCurrent($serviceId, $imageId);
        if (null === $instance) {
            $output->write($this->outputFactory->createErrorOutput('no-instance'));

            return self::EXIT_CODE_NO_CURRENT_INSTANCE;
        }

        $target = $instance->getId();

        $assignedIp = $this->floatingIpRepository->find($serviceId);
        if (null === $assignedIp) {
            $assignedIp = $this->floatingIpManager->create($instance);
            $ip = $assignedIp->getIp();

            try {
                $this->actionRunner->run(
                    new ActionHandler(
                        function (mixed $actionResult) use ($ip) {
                            return $actionResult instanceof Instance && $actionResult->hasIp($ip);
                        },
                        function () use ($instance) {
                            return $this->instanceRepository->find($instance->getId());
                        },
                    ),
                    $this->assigmentTimeoutInSeconds * self::MICROSECONDS_PER_SECOND,
                    $this->assignmentRetryInSeconds * self::MICROSECONDS_PER_SECOND
                );

                $output->write($this->createAssignmentSuccessOutput('create', $ip, null, $target));

                return Command::SUCCESS;
            } catch (ActionTimeoutException) {
                $output->write($this->createAssignmentTimeoutOutput('create', $ip, null, $target));

                return self::EXIT_CODE_ACTION_TIMED_OUT;
            }
        }

        $ip = $assignedIp->getIp();
        $source = $assignedIp->getInstance()->getId();

        if ($instance->hasIp($ip)) {
            $output->write($this->createAssignmentSuccessOutput('assign', $ip, $target, $target));

            return Command::SUCCESS;
        }

        $actionEntity = $this->floatingIpManager->reAssign($instance, $ip);

        try {
            $this->actionRunner->run(
                new ActionHandler(
                    function (mixed $actionResult): bool {
                        return $actionResult instanceof ActionEntity && 'completed' === $actionResult->status;
                    },
                    function () use ($actionEntity) {
                        return $this->actionRepository->update($actionEntity);
                    },
                ),
                $this->assigmentTimeoutInSeconds * self::MICROSECONDS_PER_SECOND,
                $this->assignmentRetryInSeconds * self::MICROSECONDS_PER_SECOND
            );

            $output->write($this->createAssignmentSuccessOutput('assign', $ip, $source, $target));

            return Command::SUCCESS;
        } catch (ActionTimeoutException) {
            $output->write($this->createAssignmentTimeoutOutput('assign', $ip, $source, $target));

            return self::EXIT_CODE_ACTION_TIMED_OUT;
        }
    }

    private function createAssignmentSuccessOutput(string $outcome, string $ip, ?int $source, int $target): string
    {
        return $this->outputFactory->createSuccessOutput([
            'outcome' => $outcome,
            'ip' => $ip,
            'source-instance' => $source,
            'target-instance' => $target,
        ]);
    }

    private function createAssignmentTimeoutOutput(string $action, string $ip, ?int $source, int $target): string
    {
        return $this->outputFactory->createErrorOutput(
            $action . '-timed-out',
            [
                'ip' => $ip,
                'source-instance' => $source,
                'target-instance' => $target,
                'timeout-in-seconds' => $this->assigmentTimeoutInSeconds,
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\ActionHandler\ActionHandler;
use App\Exception\ActionTimeoutException;
use App\Exception\ConfigurationFileValueMissingException;
use App\Exception\ServiceConfigurationMissingException;
use App\Exception\ServiceIdMissingException;
use App\Model\AssignedIp;
use App\Model\Instance;
use App\Services\ActionRepository;
use App\Services\ActionRunner;
use App\Services\CommandConfigurator;
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
    name: IpAssignCommand::NAME,
    description: 'Assign a floating IP to current instance',
)]
class IpAssignCommand extends AbstractServiceCommand
{
    public const NAME = 'app:ip:assign';

    public const EXIT_CODE_NO_CURRENT_INSTANCE = 3;
    public const EXIT_CODE_ACTION_TIMED_OUT = 5;

    private const MICROSECONDS_PER_SECOND = 1000000;

    public function __construct(
        CommandConfigurator $configurator,
        private InstanceRepository $instanceRepository,
        private FloatingIpManager $floatingIpManager,
        private ActionRepository $actionRepository,
        private FloatingIpRepository $floatingIpRepository,
        private ActionRunner $actionRunner,
        private OutputFactory $outputFactory,
        private ServiceConfiguration $serviceConfiguration,
        private int $assigmentTimeoutInSeconds,
        private int $assignmentRetryInSeconds,
    ) {
        parent::__construct($configurator);
    }

    /**
     * @throws ExceptionInterface
     * @throws ServiceIdMissingException
     * @throws ConfigurationFileValueMissingException
     * @throws ServiceConfigurationMissingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceId = $this->getServiceId($input);
        $imageId = $this->serviceConfiguration->getImageId($serviceId);

        $instance = $this->instanceRepository->findCurrent($serviceId, $imageId);
        if (null === $instance) {
            $output->write($this->outputFactory->createErrorOutput('no-instance'));

            return self::EXIT_CODE_NO_CURRENT_INSTANCE;
        }

        $target = $instance->getId();

        $assignedIp = $this->floatingIpRepository->find($serviceId);

        if ($assignedIp instanceof AssignedIp && $instance->hasIp($assignedIp->getIp())) {
            $output->write($this->createAssignmentSuccessOutput('assign', $assignedIp->getIp(), $target, $target));

            return Command::SUCCESS;
        }

        if ($assignedIp instanceof AssignedIp) {
            $action = 'assign';
            $ip = $assignedIp->getIp();
            $source = $assignedIp->getInstance()->getId();
            $actionEntity = $this->floatingIpManager->reAssign($instance, $ip);
            $actionHandler = new ActionHandler(
                function (mixed $actionResult): bool {
                    return $actionResult instanceof ActionEntity && 'completed' === $actionResult->status;
                },
                function () use ($actionEntity) {
                    return $this->actionRepository->update($actionEntity);
                },
            );
        } else {
            $action = 'create';
            $assignedIp = $this->floatingIpManager->create($instance);
            $ip = $assignedIp->getIp();
            $source = null;
            $actionHandler = new ActionHandler(
                function (mixed $actionResult) use ($ip) {
                    return $actionResult instanceof Instance && $actionResult->hasIp($ip);
                },
                function () use ($instance) {
                    return $this->instanceRepository->find($instance->getId());
                },
            );
        }

        try {
            $this->actionRunner->run(
                $actionHandler,
                $this->assigmentTimeoutInSeconds * self::MICROSECONDS_PER_SECOND,
                $this->assignmentRetryInSeconds * self::MICROSECONDS_PER_SECOND
            );
        } catch (ActionTimeoutException) {
            $output->write($this->outputFactory->createErrorOutput(
                $action . '-timed-out',
                [
                    'ip' => $ip,
                    'source-instance' => $source,
                    'target-instance' => $target,
                    'timeout-in-seconds' => $this->assigmentTimeoutInSeconds,
                ]
            ));

            return self::EXIT_CODE_ACTION_TIMED_OUT;
        }

        $output->write($this->createAssignmentSuccessOutput($action, $ip, $source, $target));

        return Command::SUCCESS;
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
}

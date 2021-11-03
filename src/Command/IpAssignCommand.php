<?php

namespace App\Command;

use App\ActionHandler\ActionHandler;
use App\Exception\ActionTimeoutException;
use App\Services\ActionRepository;
use App\Services\ActionRunner;
use App\Services\FloatingIpManager;
use App\Services\FloatingIpRepository;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Entity\Action as ActionEntity;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpAssignCommand::NAME,
    description: 'Assign current floating IP to current instance',
)]
class IpAssignCommand extends Command
{
    public const NAME = 'app:ip:assign';

    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const OPTION_IMAGE_ID = 'image-id';

    public const EXIT_CODE_NO_CURRENT_INSTANCE = 3;
    public const EXIT_CODE_NO_IP = 4;
    public const EXIT_CODE_ASSIGNMENT_TIMED_OUT = 5;
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 6;
    public const EXIT_CODE_EMPTY_TAG = 7;

    private const MICROSECONDS_PER_SECOND = 1000000;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private FloatingIpManager $floatingIpManager,
        private ActionRepository $actionRepository,
        private FloatingIpRepository $floatingIpRepository,
        private ActionRunner $actionRunner,
        private OutputFactory $outputFactory,
        private int $assigmentTimeoutInSeconds,
        private int $assignmentRetryInSeconds,
    ) {
        parent::__construct(null);
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

        $instance = $this->instanceRepository->findCurrent($collectionTag, $imageId);
        if (null === $instance) {
            $output->write($this->outputFactory->createErrorOutput('no-instance'));

            return self::EXIT_CODE_NO_CURRENT_INSTANCE;
        }

        $assignedIp = $this->floatingIpRepository->find();
        if (null === $assignedIp) {
            $output->write($this->outputFactory->createErrorOutput('no-ip'));

            return self::EXIT_CODE_NO_IP;
        }

        $ip = $assignedIp->getIp();
        $sourceInstanceId = $assignedIp->getInstance()->getId();
        $targetInstanceId = $instance->getId();

        if ($instance->hasIp($ip)) {
            $output->write($this->outputFactory->createSuccessOutput([
                'outcome' => 'already-assigned',
                'ip' => $ip,
                'source-instance' => $targetInstanceId,
                'target-instance' => $targetInstanceId,
            ]));

            return Command::SUCCESS;
        }

        $actionEntity = $this->floatingIpManager->reAssign($instance, $ip);

        try {
            $this->actionRunner->run(
                new ActionHandler(
                    function (ActionEntity $actionEntity): bool {
                        return 'completed' === $actionEntity->status;
                    },
                    function () use ($actionEntity) {
                        return $this->actionRepository->update($actionEntity);
                    },
                ),
                $this->assigmentTimeoutInSeconds * self::MICROSECONDS_PER_SECOND,
                $this->assignmentRetryInSeconds * self::MICROSECONDS_PER_SECOND
            );

            $output->write($this->outputFactory->createSuccessOutput([
                'outcome' => 're-assigned',
                'ip' => $ip,
                'source-instance' => $sourceInstanceId,
                'target-instance' => $targetInstanceId,
            ]));

            return Command::SUCCESS;
        } catch (ActionTimeoutException) {
            $output->write($this->outputFactory->createErrorOutput(
                'assignment-timed-out',
                [
                    'ip' => $ip,
                    'source-instance' => $sourceInstanceId,
                    'target-instance' => $targetInstanceId,
                    'timeout-in-seconds' => $this->assigmentTimeoutInSeconds,
                ]
            ));

            return self::EXIT_CODE_ASSIGNMENT_TIMED_OUT;
        }
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

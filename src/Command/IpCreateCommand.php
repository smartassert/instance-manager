<?php

namespace App\Command;

use App\ActionHandler\ActionHandler;
use App\Exception\ActionTimeoutException;
use App\Model\AssignedIp;
use App\Model\Instance;
use App\Services\ActionRunner;
use App\Services\FloatingIpManager;
use App\Services\FloatingIpRepository;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: IpCreateCommand::NAME,
    description: 'Create a new floating IP for the current instance',
)]
class IpCreateCommand extends Command
{
    public const NAME = 'app:ip:create';

    public const OPTION_COLLECTION_TAG = 'collection-tag';
    public const OPTION_IMAGE_ID = 'image-id';

    public const EXIT_CODE_NO_CURRENT_INSTANCE = 3;
    public const EXIT_CODE_HAS_IP = 4;
    public const EXIT_CODE_EMPTY_COLLECTION_TAG = 5;
    public const EXIT_CODE_EMPTY_TAG = 6;

    private const MICROSECONDS_PER_SECOND = 1000000;

    public function __construct(
        private InstanceRepository $instanceRepository,
        private FloatingIpManager $floatingIpManager,
        private FloatingIpRepository $floatingIpRepository,
        private ActionRunner $actionRunner,
        private OutputFactory $outputFactory,
        private int $assigmentTimeoutInSeconds,
        private int $assignmentRetryInSeconds,
    ) {
        parent::__construct();
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
     * @throws ActionTimeoutException
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

        $instanceId = $instance->getId();

        $assignedIp = $this->floatingIpRepository->find($collectionTag);
        if ($assignedIp instanceof AssignedIp) {
            $output->write($this->outputFactory->createErrorOutput('has-ip', ['ip' => $assignedIp->getIp()]));

            return self::EXIT_CODE_HAS_IP;
        }

        $assignedIp = $this->floatingIpManager->create($instance);
        $ip = $assignedIp->getIp();

        $this->actionRunner->run(
            new ActionHandler(
                function (Instance $instance) use ($ip) {
                    return $instance->hasIp($ip);
                },
                function () use ($instance) {
                    return $this->instanceRepository->find($instance->getId());
                },
            ),
            $this->assigmentTimeoutInSeconds * self::MICROSECONDS_PER_SECOND,
            $this->assignmentRetryInSeconds * self::MICROSECONDS_PER_SECOND
        );

        $output->write($this->outputFactory->createSuccessOutput(['ip' => $ip, 'target-instance' => $instanceId]));

        return Command::SUCCESS;
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

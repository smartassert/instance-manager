<?php

namespace App\Command;

use App\Services\InstanceRepository;
use App\Services\OutputFactory;
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
    public const OPTION_SERVICE_TOKEN = 'service-token';
    public const OPTION_POST_CREATE_SCRIPT = 'post-create-script';

    public function __construct(
        private InstanceRepository $instanceRepository,
        private OutputFactory $outputFactory,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_SERVICE_TOKEN,
                null,
                InputOption::VALUE_REQUIRED,
                'Service token to allow secure post-create calls to instance'
            )
            ->addOption(
                self::OPTION_POST_CREATE_SCRIPT,
                null,
                InputOption::VALUE_REQUIRED,
                'Script to call once creation is complete'
            )
        ;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceToken = $input->getOption(self::OPTION_SERVICE_TOKEN);
        $serviceToken = (is_string($serviceToken)) ? trim($serviceToken) : '';

        if ('' === $serviceToken) {
            $output->write($this->outputFactory->createErrorOutput('service-token-missing'));

            return Command::FAILURE;
        }

        $postCreateScript = $input->getOption(self::OPTION_POST_CREATE_SCRIPT);
        $postCreateScript = is_string($postCreateScript) ? trim($postCreateScript) : '';

        $instance = $this->instanceRepository->findCurrent();
        if (null === $instance) {
            $instance = $this->instanceRepository->create($serviceToken, $postCreateScript);
        }

        $output->write($this->outputFactory->createSuccessOutput(['id' => $instance->getId()]));

        return Command::SUCCESS;
    }
}

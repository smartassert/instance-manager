<?php

namespace App\Command;

use App\Services\InstanceClient;
use App\Services\InstanceRepository;
use App\Services\OutputFactory;
use DigitalOceanV2\Exception\ExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: InstanceIsPostCreateCompleteCommand::NAME,
    description: 'Check if the post-create steps of an instance are complete',
)]
class InstanceIsPostCreateCompleteCommand extends AbstractInstanceActionCommand
{
    public const NAME = 'app:instance:is-post-create-complete';
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    public function __construct(
        InstanceRepository $instanceRepository,
        private InstanceClient $instanceClient,
        private OutputFactory $outputFactory,
    ) {
        parent::__construct($instanceRepository);
    }

    /**
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $id = $this->getId();
        if (null === $id) {
            $output->write($this->outputFactory->createErrorOutput('id-invalid'));

            return self::EXIT_CODE_ID_INVALID;
        }

        $instance = $this->instanceRepository->find($id);
        if (null === $instance) {
            $output->write($this->outputFactory->createErrorOutput('not-found', ['id' => $id]));

            return self::EXIT_CODE_NOT_FOUND;
        }

        $response = $this->instanceClient->getState($instance);

        $postCreateCompleteState = $response['post-create-complete'] ?? null;
        $postCreateCompleteState = is_bool($postCreateCompleteState) ? $postCreateCompleteState : true;

        $output->write($this->outputFactory->createSuccessOutput([
            'post-create-complete' => $postCreateCompleteState,
        ]));

        return Command::SUCCESS;
    }
}

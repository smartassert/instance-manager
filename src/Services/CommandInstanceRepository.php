<?php

namespace App\Services;

use App\Command\Option;
use App\Model\Instance;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;

class CommandInstanceRepository
{
    public const EXIT_CODE_ID_INVALID = 3;
    public const EXIT_CODE_NOT_FOUND = 4;

    private int $errorCode = 0;
    private string $errorMessage = '';

    public function __construct(
        private InstanceRepository $instanceRepository,
        private CommandInputReader $commandInputReader,
        private OutputFactory $outputFactory,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function get(InputInterface $input): ?Instance
    {
        $this->errorCode = 0;
        $this->errorMessage = '';

        $id = $this->commandInputReader->getIntegerOption(Option::OPTION_ID, $input);
        if (null === $id) {
            $this->errorMessage = $this->outputFactory->createErrorOutput('id-invalid');
            $this->errorCode = self::EXIT_CODE_ID_INVALID;

            return null;
        }

        $instance = $this->instanceRepository->find($id);
        if (null === $instance) {
            $this->errorMessage = $this->outputFactory->createErrorOutput('not-found', ['id' => $id]);
            $this->errorCode = self::EXIT_CODE_NOT_FOUND;

            return null;
        }

        return $instance;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}

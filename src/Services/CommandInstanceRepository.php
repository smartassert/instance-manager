<?php

declare(strict_types=1);

namespace App\Services;

use App\Command\Option;
use App\Exception\InstanceNotFoundException;
use App\Exception\RequiredOptionMissingException;
use App\Model\Instance;
use DigitalOceanV2\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;

class CommandInstanceRepository
{
    private ?int $id = null;

    public function __construct(
        private readonly InstanceRepository $instanceRepository,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws InstanceNotFoundException
     * @throws RequiredOptionMissingException
     */
    public function get(InputInterface $input): Instance
    {
        $this->id = null;

        $id = $input->getOption(Option::OPTION_ID);
        $id = is_int($id) || is_string($id) && ctype_digit($id) ? (int) $id : null;

        if (null === $id) {
            throw new RequiredOptionMissingException(Option::OPTION_ID);
        }

        $this->id = $id;

        $instance = $this->instanceRepository->find((int) $this->id);
        if (null === $instance) {
            throw new InstanceNotFoundException($id);
        }

        return $instance;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}

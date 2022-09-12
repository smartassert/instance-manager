<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\MissingSecretException;
use App\Exception\ServiceConfigurationMissingException;
use App\Model\EnvironmentVariable;
use Doctrine\Common\Collections\Collection;

class ServiceEnvironmentVariableRepository
{
    public const NAME_DOMAIN = 'DOMAIN';
    public const SECRET_PREFIX_COMMON = 'COMMON';

    public function __construct(
        private ServiceConfiguration $serviceConfiguration,
        private readonly SecretFactory $secretFactory,
        private readonly EnvironmentVariableSecretHydrator $secretHydrator,
    ) {
    }

    /**
     * @return Collection<int, EnvironmentVariable>
     *
     * @throws ServiceConfigurationMissingException
     * @throws MissingSecretException
     */
    public function getCollection(string $serviceId, string $secretsJson): Collection
    {
        $environmentVariables = $this->serviceConfiguration->getEnvironmentVariables($serviceId);

        $secrets = $this->secretFactory->createFromJsonForKeysMatchingPrefix(
            [
                strtoupper($serviceId),
                self::SECRET_PREFIX_COMMON,
            ],
            $secretsJson
        );

        $environmentVariables = $this->secretHydrator->hydrateCollection($environmentVariables, $secrets);

        foreach ($environmentVariables as $environmentVariable) {
            $secretPlaceholder = $environmentVariable->getSecretPlaceholder();

            if (null !== $secretPlaceholder) {
                throw new MissingSecretException($secretPlaceholder);
            }
        }

        $environmentVariables->add(new EnvironmentVariable(
            self::NAME_DOMAIN,
            $this->serviceConfiguration->getDomain($serviceId)
        ));

        return $environmentVariables;
    }
}

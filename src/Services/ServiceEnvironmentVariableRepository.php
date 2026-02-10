<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\MissingSecretException;
use App\Exception\ServiceConfigurationMissingException;
use App\Model\EnvironmentVariable;
use App\Model\EnvironmentVariableCollection;

class ServiceEnvironmentVariableRepository
{
    public const NAME_DOMAIN = 'DOMAIN';
    public const SECRET_PREFIX_COMMON = 'COMMON';

    public function __construct(
        private readonly SecretFactory $secretFactory,
        private readonly EnvironmentVariableSecretHydrator $secretHydrator,
        private DomainLoaderInterface $domainLoader,
        private EnvironmentVariableCollectionLoaderInterface $environmentVariableCollectionLoader,
    ) {}

    /**
     * @throws ServiceConfigurationMissingException
     * @throws MissingSecretException
     */
    public function getCollection(string $serviceId, string $secretsJson): EnvironmentVariableCollection
    {
        $environmentVariables = $this->environmentVariableCollectionLoader->load($serviceId);

        $secrets = $this->secretFactory
            ->create($secretsJson)
            ->filterByKeyPrefixes([strtoupper($serviceId), self::SECRET_PREFIX_COMMON])
        ;

        $environmentVariables = $this->secretHydrator->hydrateCollection($environmentVariables, $secrets);

        foreach ($environmentVariables as $environmentVariable) {
            $secretPlaceholder = $environmentVariable->getSecretPlaceholder();

            if (null !== $secretPlaceholder) {
                throw new MissingSecretException($secretPlaceholder);
            }
        }

        return $environmentVariables->add(new EnvironmentVariable(
            self::NAME_DOMAIN,
            $this->domainLoader->load($serviceId)
        ));
    }
}

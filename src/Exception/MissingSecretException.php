<?php

namespace App\Exception;

use App\Model\SecretPlaceholderInterface;

class MissingSecretException extends \RuntimeException
{
    public function __construct(
        private SecretPlaceholderInterface $placeholder,
    ) {
        parent::__construct(sprintf(
            'Secret "%s" not found',
            $placeholder->getSecretName()
        ));
    }

    public function getPlaceholder(): SecretPlaceholderInterface
    {
        return $this->placeholder;
    }
}

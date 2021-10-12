<?php

namespace App\ActionHandler;

class ActionHandler
{
    /**
     * @param \Closure(mixed    $actionResult): bool $decider
     * @param \Closure(): mixed $action
     */
    public function __construct(
        private \Closure $decider,
        private \Closure $action,
    ) {
    }

    public function __invoke(): bool
    {
        return ($this->decider)(
            ($this->action)()
        );
    }
}

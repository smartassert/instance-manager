<?php

namespace App\Model;

/**
 * @extends \Iterator<int, SecretPlaceholderContainerInterface>
 */
interface SecretPlaceholderContainerCollectionInterface extends \Iterator
{
    public function current(): SecretPlaceholderContainerInterface;

    public function next(): void;

    public function key(): int;

    public function valid(): bool;

    public function rewind(): void;

    public function set(SecretPlaceholderContainerInterface $placeholderContainer): self;
}

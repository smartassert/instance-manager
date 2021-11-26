<?php

namespace App\Model;

interface KeyValueInterface
{
    public function getKey(): string;
    public function getValue(): string;
}

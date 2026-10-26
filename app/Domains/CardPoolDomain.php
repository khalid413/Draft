<?php

namespace App\Domains;

interface CardPoolDomain extends DomainContract
{
    /** Draw a complete, unique group for one allocation turn. */
    public function drawCards(array $excludeCardIds, int $count, ?string $position = null): array;
}

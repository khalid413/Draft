<?php

namespace App\Domains;

use DomainException;

class DomainRegistry
{
    private array $domains = [];

    private array $enabled = [];

    public function register(DomainContract $domain, bool $enabled = true): void
    {
        $this->domains[$domain->key()] = $domain;
        $this->enabled[$domain->key()] = $enabled;
    }

    public function get(string $key): DomainContract
    {
        if (! ($this->enabled[$key] ?? false)) {
            throw new DomainException(__('This domain is not available yet.'));
        }

        return $this->domains[$key];
    }

    public function catalog(): array
    {
        return array_values(array_map(fn ($domain) => ['key' => $domain->key(), 'label' => $domain->label(),
            'rules' => $domain->rulesText(), 'enabled' => $this->enabled[$domain->key()]], $this->domains));
    }
}

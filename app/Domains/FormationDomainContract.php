<?php

namespace App\Domains;

interface FormationDomainContract extends DomainContract
{
    /** Ordered picks, each with a distinct slot key and a natural position. */
    public function slots(): array;
}

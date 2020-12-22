<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

interface ContactDoubletCheck
{
    /**
     * @return array|mixed[]|null
     */
    public function find(
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $mobile,
        ?string $landline
    ): ?array;
}

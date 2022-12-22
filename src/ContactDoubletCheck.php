<?php
declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

interface ContactDoubletCheck
{
    public function find(
        string $firstName,
        string $lastName,
        string|null $email,
        string|null $mobile,
        string|null $landline,
        callable|null $qualifier = null,
    ): array|null;
}

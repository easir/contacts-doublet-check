<?php declare(strict_types=1);

namespace Tests;

class ZombieQualifier
{
    private const ZOMBIE_FIELD = 'pks_konflikt';
    public function __invoke(array $contact): bool
    {
        foreach ($contact['custom_fields'] as $field) {
            if ($field['name'] === self::ZOMBIE_FIELD) {
                return $field['value'];
            }
        }

        return false;
    }
}

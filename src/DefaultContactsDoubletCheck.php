<?php
declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsFilter\B2BContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\B2CContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\ContactsFilter;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use GuzzleHttp\Exception\GuzzleException;

final class DefaultContactsDoubletCheck implements ContactDoubletCheck
{
    private const ZOMBIE = 'pks_konflikt';

    public function __construct(
        private RestApiPaginator $paginator
    ) {
    }

    /**
     * @return array|mixed[]|null
     * @throws GuzzleException|ValidationException
     */
    public function find(
        string $firstName,
        string $lastName,
        string|null $email,
        string|null $mobile,
        string|null $landline
    ): array|null {
        $contacts = array_merge(
            $this->fetchContacts(new B2CContactsFilter($firstName, $lastName, $email, $mobile, $landline)),
            $this->fetchContacts(new B2BContactsFilter($firstName, $lastName, $email, $mobile, $landline))
        );

        if (empty($contacts)) {
            return null;
        }

        if (count($contacts) === 1) {
            return $contacts[0];
        }

        $contact = $this->getNewestByCase($contacts);
        if ($contact !== null) {
            return $contact;
        }

        return $this->getNewestByUpdatedAt($contacts);
    }

    /**
     * @param array|mixed[] $contacts
     * @return array|mixed[]|null
     * @throws GuzzleException
     */
    private function getNewestByCase(array $contacts): array|null
    {
        $contactsWithCases = [];
        foreach ($contacts as $contact) {
            $newestCaseDate = $this->getNewestDateByCase($contact['account']['id'], $contact['id']);

            if ($newestCaseDate === null) {
                continue;
            }

            $contactsWithCases[(string) $newestCaseDate] = $contact;
        }

        if (empty($contactsWithCases)) {
            return null;
        }

        ksort($contactsWithCases);

        return array_pop($contactsWithCases);
    }

    /**
     * @throws GuzzleException
     */
    private function getNewestDateByCase(string $accountId, string $contactId): Carbon|null
    {
        $newestDate = null;

        $url = sprintf(
            '/accounts/%s/contacts/%s/cases',
            $accountId,
            $contactId
        );
        foreach ($this->paginator->get($url) as $case) {
            $checkDate = Carbon::parse($case['updated_at']);
            $newestDate = $checkDate->gt($newestDate) ? $checkDate : $newestDate;
        }

        return $newestDate;
    }

    /**
     * @return array|mixed[]
     * @throws GuzzleException
     */
    private function fetchContacts(ContactsFilter $filter): array
    {
        $contacts = [];

        foreach ($this->paginator->post('/contacts/filter', $filter->buildFilter()) as $contact) {
            if ($this->isZombie($contact['custom_fields'])) {
                continue;
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    /**
     * @param array|mixed[] $contacts
     * @return array|mixed[]
     */
    private function getNewestByUpdatedAt(array $contacts): array
    {
        $newest = current($contacts) ?: [];

        foreach ($contacts as $contact) {
            if (!Carbon::parse($newest['updated_at'])->lt(Carbon::parse($contact['updated_at']))) {
                continue;
            }

            $newest = $contact;
        }

        return $newest;
    }

    /**
     * @param array|mixed[] $fields
     */
    private function isZombie(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field['name'] === self::ZOMBIE) {
                return $field['value'];
            }
        }

        return false;
    }
}

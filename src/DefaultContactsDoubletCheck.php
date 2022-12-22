<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsFilter\B2BContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\B2CContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\ContactsFilter;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

final class DefaultContactsDoubletCheck implements ContactDoubletCheck
{
    public function __construct(
        private readonly RestApiPaginator $paginator
    ) {
    }

    /**
     * @return array|mixed[]|null
     * @throws GuzzleException|ValidationException|Throwable
     */
    public function find(
        string $firstName,
        string $lastName,
        string|null $email,
        string|null $mobile,
        string|null $landline,
        callable|null $qualifier = null,
    ): array|null {
        $contacts = [];

        try {
            $contacts = array_merge(
                $this->fetchContacts(
                    new B2CContactsFilter($firstName, $lastName, $email, $mobile, $landline),
                    $qualifier,
                ),
                $this->fetchContacts(
                    new B2BContactsFilter($firstName, $lastName, $email, $mobile, $landline),
                    $qualifier,
                )
            );
        } catch (Throwable $exception) {
            if (in_array($exception->getCode(), [500, 502, 503, 504])) {
                foreach ($this->buildQueryUrls($firstName, $lastName, $email, $mobile, $landline) as $url) {
                    foreach ($this->paginator->get($url) as $contact) {
                        if ($qualifier !== null && $qualifier($contact)) {
                            continue;
                        }

                        $contacts[] = $contact;
                    }
                    if (!empty($contacts)) {
                        break;
                    }
                }
            } else {
                throw $exception;
            }
        }

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

        $url = sprintf('/accounts/%s/contacts/%s/cases', $accountId, $contactId);
        foreach ($this->paginator->get($url) as $case) {
            $checkDate = Carbon::parse($case['updated_at']);
            if ($newestDate === null || $checkDate->gt($newestDate)) {
                $newestDate = $checkDate;
            }
        }

        return $newestDate;
    }

    /**
     * @return array|mixed[]
     * @throws GuzzleException
     */
    private function fetchContacts(ContactsFilter $filter, callable|null $qualifier = null): array
    {
        $contacts = [];

        foreach ($this->paginator->post('/contacts/filter', $filter->buildFilter()) as $contact) {
            if ($qualifier !== null && $qualifier($contact)) {
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
     * @return array|string[]
     */
    private function buildQueryUrls(
        string $firstName,
        string $lastName,
        string|null $email,
        string|null $mobile,
        string|null $landline
    ): array {
        $query = '/contacts?q=%s';
        $queries = [];
        if ($email !== null) {
            $queries[] = sprintf($query, $email);
        }

        $queries[] = sprintf($query, sprintf('%s %s', $firstName, $lastName));

        if ($mobile !== null) {
            $queries[] = sprintf($query, $mobile);
        }

        if ($landline !== null) {
            $queries[] = sprintf($query, $landline);
        }

        return $queries;
    }
}

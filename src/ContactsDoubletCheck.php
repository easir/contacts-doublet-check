<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsFilter\B2BContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\B2CContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\ContactsFilter;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final class ContactsDoubletCheck
{
    /** @var GuzzleClient */
    private $client;

    public function __construct(GuzzleClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array|mixed[]|null
     * @throws GuzzleException|ValidationException
     */
    public function find(
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $mobile,
        ?string $landline
    ): ?array {
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
    private function getNewestByCase(array $contacts): ?array
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
    private function getNewestDateByCase(string $accountId, string $contactId): ?Carbon
    {
        $newestDate = null;

        $url = sprintf(
            '/accounts/%s/contacts/%s/cases',
            $accountId,
            $contactId
        );
        foreach ($this->backendFetcherGet($url) as $case) {
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

        foreach ($this->backendFetcherPost('/contacts/filter', $filter->buildFilter()) as $contact) {
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
        $newest = current($contacts) ? current($contacts) : [];

        foreach ($contacts as $contact) {
            if (!Carbon::parse($newest['updated_at'])->lt(Carbon::parse($contact['updated_at']))) {
                continue;
            }

            $newest = $contact;
        }

        return $newest;
    }

    /**
     * @param array|mixed[] $payload
     * @throws GuzzleException
     */
    private function backendFetcherGet(string $url): Generator
    {
        return $this->backendFetcher('GET', $url);
    }

    /**
     * @param array|mixed[] $payload
     * @throws GuzzleException
     */
    private function backendFetcherPost(string $url, array $payload): Generator
    {
        return $this->backendFetcher('POST', $url, $payload);
    }

    /**
     * @param array|mixed[] $payload
     * @throws GuzzleException
     */
    private function backendFetcher(string $method, string $url, array $payload = []): Generator
    {
        $page = 0;
        do {
            $page++;
            $response = $this->client->request(
                $method,
                sprintf('%s?page=%d', $url, $page),
                ['json' => $payload]
            );
            $result = json_decode($response->getBody()->getContents(), true);

            foreach ((array) $result['data'] as $record) {
                yield $record;
            }
        } while ($result['pagination']['urls']['next'] !== null);
    }
}

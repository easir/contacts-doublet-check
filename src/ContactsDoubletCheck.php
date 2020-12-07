<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsFilter\B2BContactsFilter;
use Easir\ContactsDoubletCheck\ContactsFilter\B2CContactsFilter;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class ContactsDoubletCheck
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
     * @return array|mixed[]
     * @throws GuzzleException
     */
    protected function fetchContacts(ContactsFilter $filter): array
    {
        $contacts = [];
        $numberPages = 0;
        do {
            $numberPages++;

            $response = $this->client->post(sprintf('/contacts/filter?per_page=50&page=%d', $numberPages), [
                'json' => $filter->buildFilter(),
            ]);
            $results = json_decode($response->getBody()->getContents(), true);

            $contacts = array_merge($contacts, $results['data']);
        } while ($results['pagination']['urls']['next'] !== null);

        return $contacts;
    }

    /**
     * @param array|mixed[] $contacts
     * @return array|mixed[]|null
     * @throws GuzzleException
     */
    public function getNewestByCase(array $contacts): ?array
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
    public function getNewestDateByCase(string $accountId, string $contactId): ?Carbon
    {
        $newest = null;
        $numberPages = 0;
        do {
            $numberPages++;

            $response = $this->client->get(sprintf('/accounts/%s/contacts/%s/cases?per_page=50&page=%d', $accountId, $contactId, $numberPages));
            $results = json_decode($response->getBody()->getContents(), true);

            if (empty($results['data'])) {
                return null;
            }

            foreach ($results['data'] as $case) {
                if (empty($newest)) {
                    $newest = Carbon::parse($case['updated_at']);
                }

                $checkDate = Carbon::parse($case['updated_at']);
                if (!$newest->lt($checkDate)) {
                    continue;
                }

                $newest = $checkDate;
            }
        } while ($results['pagination']['urls']['next'] !== null);

        return $newest;
    }

    /**
     * @param array|mixed[] $contacts
     * @return array|mixed[]
     */
    private function getNewestByUpdatedAt(array $contacts): array
    {
        $newest = [];
        foreach ($contacts as $contact) {
            if (empty($newest)) {
                $newest = $contact;
            }

            if (!Carbon::parse($newest['updated_at'])->lt(Carbon::parse($contact['updated_at']))) {
                continue;
            }

            $newest = $contact;
        }

        return $newest;
    }
}

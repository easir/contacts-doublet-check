<?php declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\DefaultContactsDoubletCheck;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use Easir\ContactsDoubletCheck\RestApiPaginator;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ContactsDoubletCheckTest extends TestCase
{
    /**
     * @dataProvider generateWrongPayload
     * @throws ValidationException
     * @throws GuzzleException
     */
    public function testValidationException(
        string $firstName,
        string $lastName,
        string|null $email,
        string|null $mobile,
        string|null $landline
    ): void {
        $this->expectException(ValidationException::class);

        $contactsDoubletCheck = new DefaultContactsDoubletCheck(new RestApiPaginator(new Client()));
        $contactsDoubletCheck->find($firstName, $lastName, $email, $mobile, $landline);
    }

    /**
     * @dataProvider generateData
     * @param array|mixed[] $payload
     */
    public function testDoubletCheck(array $payload): void
    {
        [$contact, $newestDate] = $payload;

        if ($contact === null) {
            $this->assertNull($newestDate);

            return;
        }

        $this->assertSame((string) $newestDate, $contact['updated_at']);
    }

    /**
     * @return array|mixed[]
     * @throws Exception|GuzzleException
     */
    public function generateData(): array
    {
        return [
            'No result found' => [
                $this->prepareDependencies(0, 0, false, false),
            ],
            'One result found' => [
                $this->prepareDependencies(1, 0, false, false),
            ],
            'One result found but zombie' => [
                $this->prepareDependencies(1, 0, false, true),
            ],
            'More than one result found, no Cases' => [
                $this->prepareDependencies(2, 2, false, false),
            ],
            'More than one result found, with Cases' => [
                $this->prepareDependencies(3, 4, true, false),
            ],
        ];
    }

    /**
     * @return array|mixed[]
     */
    public function generateWrongPayload(): array
    {
        return [
            ['FirstName', 'LastName', null, null, null],
            ['FirstName', '', 'email@test.com', null, null],
            ['', 'LastName', '', '12312312', null],
            ['FirstName', 'LastName', '', '', ''],
        ];
    }

    /**
     * @return array|mixed[]
     * @throws Exception|GuzzleException
     */
    private function prepareDependencies(
        int $privateContactsAmount,
        int $businessContactsAmount,
        bool $withCases,
        bool $zombie,
    ): array {
        [
            $newestDatePrivateContact,
            $realPrivateContactDate,
            $mockedPrivateContactResponse,
            $mockedPrivateContactCases,
        ] = $this->populateContacts(true, $privateContactsAmount, $withCases, $zombie);
        [
            $newestDateBusinessContact,
            $realBusinessContactDate,
            $mockedBusinessContactResponse,
            $mockedBusinessContactCases,
        ] = $this->populateContacts(false, $businessContactsAmount, $withCases, $zombie);

        if ($newestDatePrivateContact === null) {
            $realContactDate = $realBusinessContactDate;
        } elseif ($newestDateBusinessContact === null) {
            $realContactDate = $realPrivateContactDate;
        } elseif ($newestDateBusinessContact->gt($newestDatePrivateContact)) {
            $realContactDate = $realBusinessContactDate;
        } else {
            $realContactDate = $realPrivateContactDate;
        }

        $guzzleMock = new MockHandler(array_merge(
            $mockedPrivateContactResponse,
            $mockedBusinessContactResponse,
            $mockedPrivateContactCases,
            $mockedBusinessContactCases
        ));

        $stack = HandlerStack::create($guzzleMock);
        $guzzleClient = new Client(['handler' => $stack]);

        $contactsDoubletCheck = new DefaultContactsDoubletCheck(new RestApiPaginator($guzzleClient));
        $contact = $contactsDoubletCheck->find(
            'Rachael',
            'Armstrong',
            'rachael@test.com',
            '932-807-0673',
            null
        );

        return [$contact, $realContactDate];
    }

    /**
     * @return array|mixed[]
     * @throws Exception
     */
    private function populateContacts(bool $b2c, int $amount, bool $withCases, bool $zombie): array
    {
        $newestDate = null;
        $realContactDate = null;
        $mockedContacts = [];
        $mockedCasesCalls = [];

        for ($i = 0; $i < $amount; $i++) {
            $updatedAt = Carbon::now()->subDays(random_int(1, 100))->subHours(random_int(1, 23));
            $contact = $this->generateContact($b2c, (string) $updatedAt, $zombie);
            if ($zombie) {
                continue;
            }

            $mockedContacts[] = $contact;

            if ($withCases) {
                [$newestCaseDate, $mockedCases] = $this->populateCases(random_int(0, 3), $contact);
                $mockedCasesCalls = array_merge($mockedCasesCalls, $mockedCases);

                if ($newestCaseDate !== null && ($newestDate === null || $newestCaseDate->gt($newestDate))) {
                    $newestDate = $newestCaseDate;
                    $realContactDate = $updatedAt;
                }

                continue;
            }

            if ($newestDate === null || $newestDate->lt($updatedAt)) {
                $realContactDate = $newestDate = $updatedAt;
            }

            $mockedCasesCalls = array_merge($mockedCasesCalls, [$this->mockCall([])]);
        }

        return [$newestDate, $realContactDate, [$this->mockCall($mockedContacts)], $mockedCasesCalls];
    }

    /**
     * @return array|mixed[]
     */
    private function generateContact(bool $b2c, string $updatedAt, bool $zombie): array
    {
        return [
            'id' => 'c21aff80-af63-3385-a3eb-867b8a7cf5bd',
            'b2c' => $b2c,
            'account' => [
                'id' => 'a0b90d42-9a02-3199-ade3-20586b7113c4',
                'name' => 'Albina Aufderhar IV',
            ],
            'fixed_fields' => [
                [
                    'name' => 'first_name',
                    'value' => 'Rachael',
                ],
                [
                    'name' => 'last_name',
                    'value' => 'Armstrong',
                ],
                [
                    'name' => 'email',
                    'value' => 'rachael@test.com',
                ],
                [
                    'name' => 'mobile_phone_number',
                    'value' => '932-807-0673',
                ],
                [
                    'name' => 'landline_phone_number',
                    'value' => '+1-372-862-1467',
                ],
            ],
            'custom_fields' => [
                [
                    'name' => 'something',
                    'value' => 'another',
                ],
                [
                    'name' => 'pks_konflikt',
                    'value' => $zombie,
                ],
            ],
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param array|mixed[] $contact
     * @return array|mixed[]
     * @throws Exception
     */
    private function populateCases(int $amount, array $contact): array
    {
        $newestDate = null;
        $payload = [];

        for ($i = 0; $i < $amount; $i++) {
            $updatedAt = Carbon::parse($contact['updated_at'])->addDays(random_int(1, 10))->addHours(random_int(1, 23));

            if ($newestDate === null || $newestDate->lt($updatedAt)) {
                $newestDate = $updatedAt;
            }

            $payload[] = $this->generateCase((string) $updatedAt, $contact);
        }

        return [$newestDate, [$this->mockCall($payload)]];
    }

    /**
     * @param array|mixed[] $contact
     * @return array|string[]
     */
    private function generateCase(string $updatedAt, array $contact): array
    {
        return [
            'id' => '8148861b-b991-31c6-afd2-a84a2e8741ee',
            'updated_at' => $updatedAt,
            'account' => [
                'id' => $contact['account']['id'],
            ],
            'contact' => $contact,
        ];
    }

    /**
     * @param array|mixed[] $payload
     */
    private function mockCall(array $payload): Response
    {
        return new Response(200, [], json_encode([
            'data' => $payload,
            'pagination' => [
                'total' => count($payload),
                'page' => 1,
                'per_page' => 50,
                'urls' => [
                    'previous' => null,
                    'next' => null,
                ],
            ],
        ]));
    }
}

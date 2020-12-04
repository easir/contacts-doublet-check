<?php declare(strict_types=1);

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsDoubletCheck;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Easir\ContactsDoubletCheck\Exception\ValidationException;

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
        ?string $email,
        ?string $mobile,
        ?string $landline
    ): void {
        $this->expectException(ValidationException::class);

        $contactsDoubletCheck = new ContactsDoubletCheck(new Client());
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
                $this->prepareDependencies(0, 0, false),
            ],
            'One result found' => [
                $this->prepareDependencies(1, 0, false),
            ],
            'More than one result found, no Cases' => [
                $this->prepareDependencies(3, 2, false),
            ],
            'More than one result found, with Cases' => [
                $this->prepareDependencies(3, 2, true),
            ],
        ];
    }

    /**
     * @return array|mixed[]
     * @throws Exception|GuzzleException
     */
    private function prepareDependencies(int $b2cContacts, int $b2bContacts, bool $withCases): array
    {
        [
            $newestDateB2C,
            $realB2CContactDate,
            $mockedB2CResponse,
            $mockedB2CCases
        ] = $this->populateContacts(true, $b2cContacts, $withCases);
        [
            $newestDateB2B,
            $realB2BContactDate,
            $mockedB2BResponse,
            $mockedB2BCases
        ] = $this->populateContacts(false, $b2bContacts, $withCases);

        if ($newestDateB2C === null) {
            $realContactDate = $realB2BContactDate;
        } else if ($newestDateB2B === null) {
            $realContactDate = $realB2CContactDate;
        } else {
            if ($newestDateB2C->lt($newestDateB2B)) {
                $realContactDate = $realB2BContactDate;
            } else {
                $realContactDate = $realB2CContactDate;
            }
        }

        $guzzleMock = new MockHandler(array_merge(
            $mockedB2CResponse,
            $mockedB2BResponse,
            $mockedB2CCases,
            $mockedB2BCases
        ));

        $stack = HandlerStack::create($guzzleMock);
        $guzzleClient = new Client(['handler' => $stack]);

        $contactsDoubletCheck = new ContactsDoubletCheck($guzzleClient);
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
    private function populateContacts(bool $b2c, int $amount, bool $withCases): array
    {
        $newestDate = null;
        $payload = [];
        $mockedCasesCalls = [];
        $realContactDate = null;
        for ($i=0; $i<$amount; $i++) {
            $updatedAt = Carbon::now()->subDays(random_int(1, 100))->subHours(random_int(1, 23));
            if (!$withCases) {
                if ($newestDate === null || $newestDate->lt($updatedAt)) {
                    $newestDate = $updatedAt;
                    $realContactDate = $newestDate;
                }
            }

            $contact = $this->generateContact($b2c, (string) $updatedAt,);

            $payload[] = $contact;

            if ($withCases) {
                [$newestCaseDate, $mockedCases] = $this->populateCases(random_int(0, 3), $contact);
                $mockedCasesCalls = array_merge($mockedCasesCalls, $mockedCases);

                if ($newestDate === null || ($newestCaseDate !== null && $newestDate->lt($newestCaseDate))) {
                    $newestDate = $newestCaseDate;
                    $realContactDate = $updatedAt;
                }
            } else {
                $mockedEmptyCall[] = $this->mockCall([]);
                $mockedCasesCalls = array_merge($mockedCasesCalls, $mockedEmptyCall);
            }
        }

        return [$newestDate, $realContactDate, [$this->mockCall($payload)], $mockedCasesCalls];
    }

    /**
     * @return array|string[]
     */
    private function generateContact(bool $b2c, string $updatedAt): array
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

        for ($i=0; $i<$amount; $i++) {
            $updatedAt = Carbon::parse($contact['updated_at'])->addDays(random_int(1, 10))->addHours(random_int(1, 23));

            if ($newestDate === null || $newestDate->lt($updatedAt)) {
                $newestDate = $updatedAt;
            }

            $payload[] = $this->generateCase((string) $updatedAt, $contact);
        }

        return [$newestDate, [$this->mockCall($payload)]];
    }

    /**
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

    /**
     * @return array|mixed[]
     */
    public function generateWrongPayload(): array {
        return [
            ['FirstName', 'LastName', null, null, null],
            ['FirstName', '', 'email@test.com', null, null],
            ['', 'LastName', '', '12312312', null],
            ['FirstName', 'LastName', '', '', ''],
        ];
    }
}

<?php declare(strict_types=1);

use Carbon\Carbon;
use Easir\ContactsDoubletCheck\ContactsDoubletCheck;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Easir\ContactsDoubletCheck\Exception\ValidationException;
use Faker\Factory;

class ContactsDoubletCheckTest extends TestCase
{
    /** @var Factory */
    private $faker;
    public function setUp() : void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    /**
     * @throws ValidationException
     * @throws GuzzleException
     */
    public function testValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $container = [];
        $history = Middleware::history($container);

        $guzzleMock = new MockHandler([]);
        $stack = HandlerStack::create($guzzleMock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);
        $contactsDoubletCheck = new ContactsDoubletCheck($guzzleClient);
        $contactsDoubletCheck->find('FirstName', 'LastName', null, null, null);
        $contactsDoubletCheck->find('FirstName', '', 'email@test.com', null, null);
        $contactsDoubletCheck->find('FirstName', 'LastName', '', '12312312', null);
    }

    /**
     * @throws Exception
     */
    public function testNoOneFound(): void
    {
        [$contactsDoubletCheck, $newestContactDate, $parameters] = $this->prepareDependencies(0, 0, false);
        $contact = $contactsDoubletCheck->find(
            $parameters['firstName'],
            $parameters['lastName'],
            $parameters['email'],
            $parameters['mobile'],
            $parameters['landline']
        );

        $this->assertNull($contact);
        $this->assertNull($newestContactDate);
    }

    /**
     * @throws Exception
     */
    public function testOneFound(): void
    {
        [$contactsDoubletCheck, $newestContactDate, $parameters] = $this->prepareDependencies(1, 0, false);
        $this->checkResults(
            $parameters,
            $contactsDoubletCheck->find(
                $parameters['firstName'],
                $parameters['lastName'],
                $parameters['email'],
                $parameters['mobile'],
                $parameters['landline']
            ),
            $newestContactDate
        );
    }

    /**
     * @throws Exception
     */
    public function testMoreThanOneFoundNoCases(): void
    {
        [$contactsDoubletCheck, $newestContactDate, $parameters] = $this->prepareDependencies(3, 2, false);
        $this->checkResults(
            $parameters,
            $contactsDoubletCheck->find(
                $parameters['firstName'],
                $parameters['lastName'],
                $parameters['email'],
                $parameters['mobile'],
                $parameters['landline']
            ),
            $newestContactDate
        );
    }

    /**
     * @throws Exception
     */
    public function testMoreThanOneFoundWithCases(): void
    {
        [$contactsDoubletCheck, $newestContactDate, $parameters] = $this->prepareDependencies(3, 2, true);
        $this->checkResults(
            $parameters,
            $contactsDoubletCheck->find(
                $parameters['firstName'],
                $parameters['lastName'],
                $parameters['email'],
                $parameters['mobile'],
                $parameters['landline']
            ),
            $newestContactDate
        );
    }

    /**
     * @return array|mixed[]
     * @throws Exception
     */
    private function prepareDependencies(int $b2cContacts, int $b2bContacts, bool $withCases): array
    {
        $container = [];
        $history = Middleware::history($container);

        $parameters = [
            'firstName' => $this->faker->firstName,
            'lastName' => $this->faker->lastName,
            'email' => $this->faker->email,
            'mobile' => $this->faker->phoneNumber,
            'landline' => $this->faker->phoneNumber,
        ];

        [
            $newestDateB2C,
            $realB2CContactDate,
            $mockedB2CResponse,
            $mockedB2CCases
        ] = $this->populateContacts(true, $b2cContacts, $parameters, $withCases);
        [
            $newestDateB2B,
            $realB2BContactDate,
            $mockedB2BResponse,
            $mockedB2BCases
        ] = $this->populateContacts(false, $b2bContacts, $parameters, $withCases);

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
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);
        return [new ContactsDoubletCheck($guzzleClient), $realContactDate, $parameters];
    }

    /**
     * @return array|mixed[]
     * @throws Exception
     */
    private function populateContacts(bool $b2c, int $amount, array $parameters, bool $withCases): array
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

            $contact = $this->generateContact(
                $b2c,
                $parameters['firstName'],
                $parameters['lastName'],
                $parameters['email'],
                $parameters['mobile'],
                $parameters['landline'],
                (string) $updatedAt,
            );

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
    private function generateContact(
        bool $b2c,
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $mobile,
        ?string $landline,
        string $updatedAt
    ): array {
        return [
            'id' => $this->faker->uuid,
            'b2c' => $b2c,
            'account' => [
                'id' => $this->faker->uuid,
                'name' => $this->faker->name,
            ],
            'fixed_fields' => [
                [
                    'name' => 'first_name',
                    'value' => $firstName,
                ],
                [
                    'name' => 'last_name',
                    'value' => $lastName,
                ],
                [
                    'name' => 'email',
                    'value' => $email,
                ],
                [
                    'name' => 'mobile_phone_number',
                    'value' => $mobile,
                ],
                [
                    'name' => 'landline_phone_number',
                    'value' => $landline,
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
            'id' => $this->faker->uuid,
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
     * @param array|string[] $parameters
     * @param array|mixed[] $contact
     */
    public function checkResults(array $parameters, array $contact, Carbon $newestDate): void
    {
        $this->assertSame($parameters['firstName'], $contact['fixed_fields'][0]['value']);
        $this->assertSame($parameters['lastName'], $contact['fixed_fields'][1]['value']);
        $this->assertSame($parameters['email'], $contact['fixed_fields'][2]['value']);
        $this->assertSame($parameters['mobile'], $contact['fixed_fields'][3]['value']);
        $this->assertSame($parameters['landline'], $contact['fixed_fields'][4]['value']);
        $this->assertSame((string) $newestDate, $contact['updated_at']);
    }
}

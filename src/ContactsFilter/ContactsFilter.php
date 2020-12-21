<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck\ContactsFilter;

use Easir\ContactsDoubletCheck\Exception\ValidationException;
use OutOfBoundsException;

abstract class ContactsFilter
{
    /** @var string */
    protected $firstName;
    /** @var string */
    protected $lastName;
    /** @var string|null */
    protected $email;
    /** @var string|null */
    protected $mobile;
    /** @var string|null */
    protected $landline;

    /**
     * @throws ValidationException
     */
    public function __construct(
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $mobile,
        ?string $landline
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->mobile = $mobile;
        $this->landline = $landline;

        $this->validateParameters();
    }

    /**
     * @return array|mixed[]
     */
    public function buildFilter(): array
    {
        $filter = [
            [
                'field' => $this->getFieldName('firstName'),
                'operator' => 'ct',
                'value' => $this->firstName,
            ],
            [
                'field' => $this->getFieldName('lastName'),
                'operator' => 'ct',
                'value' => $this->lastName,
            ],
        ];

        $orCondition = [];
        if (!empty($this->landline)) {
            $orCondition[] = [
                'field' => $this->getFieldName('landline'),
                'operator' => 'ct',
                'value' => $this->landline,
            ];
        }

        if (!empty($this->mobile)) {
            $orCondition[] = [
                'field' => $this->getFieldName('mobile'),
                'operator' => 'ct',
                'value' => $this->mobile,
            ];
        }

        if (!empty($this->email)) {
            $orCondition[] = [
                'field' => $this->getFieldName('email'),
                'operator' => 'ct',
                'value' => $this->email,
            ];
        }

        $filter[]['or'] = $orCondition;

        return ['filter' => $filter];
    }

    /**
     * @throws OutOfBoundsException
     */
    abstract protected function getFieldName(string $fieldName): string;

    /**
     * @throws ValidationException
     */
    private function validateParameters(): void
    {
        if (empty($this->firstName)) {
            throw new ValidationException('FirstName is Mandatory!');
        }

        if (empty($this->lastName)) {
            throw new ValidationException('LastName is Mandatory!');
        }

        if (empty($this->email) && empty($this->mobile) && empty($this->landline)) {
            throw new ValidationException('At least one of email/mobile/landline must be set!');
        }
    }
}

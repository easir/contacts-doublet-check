<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck\ContactsFilter;

use Easir\ContactsDoubletCheck\GetFieldNames;

final class B2CContactsFilter extends ContactsFilter
{
    use GetFieldNames;

    /** @var array|string[] */
    protected $fields = [
        'firstName' => 'b2c_contact.fixed_fields.first_name',
        'lastName' => 'b2c_contact.fixed_fields.last_name',
        'mobile' => 'b2c_contact.fixed_fields.mobile_phone_number',
        'email' => 'b2c_contact.fixed_fields.email',
        'landline' => 'b2c_contact.fixed_fields.landline_phone_number',
    ];
}

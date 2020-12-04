<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck\ContactsFilter;

use Easir\ContactsDoubletCheck\ContactsFilter;
use Easir\ContactsDoubletCheck\GetFieldNames;

class B2BContactsFilter extends ContactsFilter
{
    use GetFieldNames;

    /** @var array|string[] */
    protected $fields = [
        'firstName' => 'contact.fixed_fields.first_name',
        'lastName' => 'contact.fixed_fields.last_name',
        'mobile' => 'contact.fixed_fields.mobile_phone_number',
        'email' => 'contact.fixed_fields.email',
        'landline' => 'contact.fixed_fields.landline_phone_number',
    ];
}

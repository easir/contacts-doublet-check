# Doublet (double entry search) check for EASI'R Now Contacts

Current version `1.0.0`
Supported API version `2.40.66`

To run tests `vendor/bin/phpunit`

## Usage
Tool requires a Guzzle Client that handles access token requirements to access Easi'r Now.
First and Last Name of the Contact are required, and at least one of the following : 
email, mobile and/or landline phone number.

```
$firstName = 'Jane';
$lastName = 'Doe';
$email = 'janedoe@email.com';
$mobile = '932-807-0673';
$landline = null;

$contactsDoubletCheck = new ContactsDoubletCheck($guzzleClient);
$contact = $contactsDoubletCheck->find(
    $firstName,
    $lastName,
    $email,
    $mobile,
    $landline
);
```

The tool returns the Contact payload from Easi'r Now if found or null otherwise.

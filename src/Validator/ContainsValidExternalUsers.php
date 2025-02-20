<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ContainsValidExternalUsers extends Constraint
{
    public string $message =
        'Contains unknown or duplicated {{ provider }} users. '.
        'Submitted: {{ submittedUsers }}, of which known: {{ knownUsers }}.';

    public string $messageAlternate =
        'Please correct {{ userToCorrect }} to appropriate {{ provider }} username(s): {{ knownUsers }}.';

    public function __construct()
    {
        parent::__construct();
    }
}

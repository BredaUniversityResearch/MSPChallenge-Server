<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ContainsValidExternalUsers extends Constraint
{
    public string $message =
        'Contains unknown {{ provider }} users. Submitted: {{ submittedUsers }}, of which known: {{ knownUsers }}.';

    // all configurable options must be passed to the constructor
    public function __construct()
    {
        parent::__construct([]);
    }
}

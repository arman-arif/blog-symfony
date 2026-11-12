<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class StrongPassword extends Constraint
{
    public string $message = 'Choose a password of at least 12 characters that is not a commonly used password.';
}

<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class StrongPasswordValidator extends ConstraintValidator
{
    private const array COMMON = [
        'password', 'password123', 'password1234', '123456789012', 'qwertyuiop',
        'qwerty123456', 'letmein123', 'welcome123', 'admin123456', 'iloveyou123',
        'fieldnotes123', 'changeme123', 'abc123456789', 'football123', 'monkey123456',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $password = (string) $value;
        if (strlen($password) < 12 || in_array(strtolower($password), self::COMMON, true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

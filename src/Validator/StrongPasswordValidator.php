<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        try {
            $this->context->getValidator()->inContext($this->context)->validate($value, new Assert\Sequentially([
                new Assert\Type('string'),
                new Assert\Length(min: 12, max: 4096),
                new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM, message: $constraint->message),
                new Assert\NotCompromisedPassword(skipOnError: false),
            ]));
        } catch (ExceptionInterface) {
            // The real breach validator throws on lookup failure. Fail closed,
            // but present a correctable form/CLI error rather than an HTTP 500.
            $this->context->buildViolation('Password safety could not be verified. Please try again later.')->addViolation();
        }
    }
}

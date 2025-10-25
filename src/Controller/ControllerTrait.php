<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

use function count;

trait ControllerTrait
{
    private ValidatorInterface $validator;

    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    private function jsonValidated(mixed $value): Response
    {
        $violations = $this->validator->validate($value);

        if (count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'success' => false,
                'errors'  => $messages,
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'data'    => $value,
        ]);
    }

    private function doesClientAccept(Request $request, string $contentType): bool
    {
        return AcceptHeader::fromString($request->headers->get('Accept'))->has($contentType);
    }
}

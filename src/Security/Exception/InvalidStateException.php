<?php
namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class InvalidStateException extends AuthenticationException
{
}
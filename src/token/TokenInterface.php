<?php

namespace Simp\Commerce\token;

interface TokenInterface
{
    /**
     * Constructor method.
     *
     * @param mixed ...$values A variable-length list of arguments to initialize the instance.
     * @return void
     */
    public function __construct(...$values);

    /**
     * Replaces the target content with the provided token.
     *
     * @param string $token The token to replace the target content with.
     * @return mixed The result of the replacement operation, type may vary depending on implementation.
     */
    public function replace(string $token): mixed;
}
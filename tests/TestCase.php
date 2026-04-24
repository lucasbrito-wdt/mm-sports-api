<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tymon\JWTAuth\JWTGuard;

/**
 * O JWTGuard mantém $user em memória. Em testes com vários $this->getJson/$this->postJson
 * na mesma instância da app, o segundo request ainda "é" o utilizador do primeiro.
 * Limpa o cache do guard após cada sub-request.
 */
abstract class TestCase extends BaseTestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if ($this->app->bound('auth')) {
            $guard = $this->app->make('auth')->guard('api');
            if ($guard instanceof JWTGuard) {
                $r = new \ReflectionObject($guard);
                foreach ($r->getProperties() as $prop) {
                    $prop->setAccessible(true);
                    if ($prop->getName() === 'user') {
                        $prop->setValue($guard, null);
                    }
                    if ($prop->getName() === 'jwt') {
                        $jwt = $prop->getValue($guard);
                        if ($jwt !== null && \is_object($jwt) && method_exists($jwt, 'unsetToken')) {
                            $jwt->unsetToken();
                        }
                    }
                }
            }
        }

        return $response;
    }
}

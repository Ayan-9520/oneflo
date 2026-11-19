<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests must use the in-memory sqlite database.');
        }

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withCredentials();
    }

    public function actingAs($user, $guard = null)
    {
        $guard = $guard ?: 'web';
        $session = $this->app['session.store'];

        if (! $session->isStarted()) {
            $session->start();
        }

        $this->app['auth']->guard($guard)->login($user);
        $session->save();
        $this->withCookie((string) config('session.cookie'), $session->getId());

        return $this;
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie instanceof Cookie) {
                $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
            }
        }

        return $response;
    }
}

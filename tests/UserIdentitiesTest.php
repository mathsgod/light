<?php

namespace Light\Tests;

use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Auth\Service;
use Light\Model\User;

class UserIdentitiesTest extends TestCase
{
    private function app(bool $logged): App
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = getcwd() . '/index.php';
        $_SERVER['HTTPS'] = '';
        $app = new App();
        $auth = $this->createStub(Service::class);
        $auth->method('isLogged')->willReturn($logged);
        $auth->method('isAllowed')->willReturn(false);
        $app->setAuthServiceFactory(fn () => $auth);
        $app->process(new ServerRequest(), new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new \Laminas\Diactoros\Response\EmptyResponse();
            }
        });
        return $app;
    }

    private function query(App $app, string $query): array
    {
        return $app->execute((new ServerRequest())->withParsedBody(['query' => $query]))->toArray();
    }

    public function testLoggedUserWithoutListPermissionCanResolveOnlyRequestedIdentities(): void
    {
        $app = $this->app(true);
        $user = User::Create([
            'username' => 'identity_' . uniqid(),
            'first_name' => 'Audit',
            'last_name' => 'Actor',
            'password' => password_hash('test', PASSWORD_DEFAULT),
            'status' => 0,
            'join_date' => date('Y-m-d'),
            'language' => 'en',
            'password_dt' => date('Y-m-d H:i:s'),
        ]);
        $user->save();
        $id = (int) $user->user_id;
        $out = $this->query($app, "{ app { userIdentities(user_ids: [$id, $id, -1]) { user_id name } } }");
        $this->assertArrayNotHasKey('errors', $out, json_encode($out));
        $this->assertSame([['user_id' => $id, 'name' => 'Audit Actor']], $out['data']['app']['userIdentities']);
        $empty = $this->query($app, '{ app { userIdentities(user_ids: []) { user_id name } } }');
        $this->assertSame([], $empty['data']['app']['userIdentities']);
        $denied = $this->query($app, '{ app { listUser { data { user_id } } } }');
        $this->assertArrayHasKey('errors', $denied);
    }

    public function testAnonymousLookupIsDenied(): void
    {
        $out = $this->query($this->app(false), '{ app { userIdentities(user_ids: [1]) { user_id name } } }');
        $this->assertArrayHasKey('errors', $out);
    }

    public function testIdentityDoesNotExposePrivateUserFields(): void
    {
        $out = $this->query($this->app(true), '{ app { userIdentities(user_ids: [1]) { email } } }');
        $this->assertArrayHasKey('errors', $out);
        $this->assertStringContainsString('email', $out['errors'][0]['message']);
    }
}

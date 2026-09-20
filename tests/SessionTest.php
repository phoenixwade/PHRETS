<?php

use PHRETS\Configuration;
use PHRETS\Session;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class SessionTest extends PHPUnit_Framework_TestCase {

    /** @test **/
    public function it_builds()
    {
        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);
        $this->assertSame($c, $s->getConfiguration());
    }

    /**
     * @test
     * @expectedException \PHRETS\Exceptions\MissingConfiguration
     */
    public function it_detects_invalid_configurations()
    {
        $this->expectException(\PHRETS\Exceptions\MissingConfiguration::class);

        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);
        $s->Login();
    }

    /** @test **/
    public function it_gives_back_the_login_url()
    {
        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);

        $this->assertSame('http://www.reso.org/login', $s->getLoginUrl());
    }

    /** @test **/
    public function it_tracks_capabilities()
    {
        $login_url = 'http://www.reso.org/login';
        $c = new Configuration;
        $c->setLoginUrl($login_url);

        $s = new Session($c);
        $capabilities = $s->getCapabilities();
        $this->assertInstanceOf('PHRETS\Capabilities', $capabilities);
        $this->assertSame($login_url, $capabilities->get('Login'));
    }

    /** @test **/
    public function it_disables_redirects_when_desired()
    {
        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');
        $c->setOption('disable_follow_location', true);

        $s = new Session($c);

        $this->assertFalse($s->getDefaultOptions()['allow_redirects']);
    }

    /** @test **/
    public function it_uses_the_set_logger()
    {
        $logger = $this->createMock(\Monolog\Logger::class);

        // expect that the string 'Context' will be changed into an array
        $logger->expects($this->atLeastOnce())->method('debug')->withConsecutive(
            [$this->anything()],
            [$this->equalTo('Message'), $this->equalTo(['Context'])]
        );

        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);
        $s->setLogger($logger);

        $s->debug('Message', 'Context');
    }

    /** @test **/
    public function it_fixes_the_logger_context_automatically()
    {
        $logger = $this->createMock(\Monolog\Logger::class);
        // just expect that a debug message is spit out
        $logger->expects($this->atLeastOnce())->method('debug')->with($this->matchesRegularExpression('/logger/'));

        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);
        $s->setLogger($logger);
    }

    /** @test **/
    public function it_loads_a_cookie_jar()
    {
        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);
        $this->assertInstanceOf('\GuzzleHttp\Cookie\CookieJarInterface', $s->getCookieJar());
        $this->assertSame($s->getCookieJar(), $s->getDefaultOptions()['cookies']);
        $this->assertArrayNotHasKey('curl', $s->getDefaultOptions());
    }

    /** @test **/
    public function it_allows_overriding_the_cookie_jar()
    {
        $c = new Configuration;
        $c->setLoginUrl('http://www.reso.org/login');

        $s = new Session($c);

        $jar = new \GuzzleHttp\Cookie\CookieJar;
        $s->setCookieJar($jar);

        $this->assertSame($jar, $s->getCookieJar());
        $this->assertSame($jar, $s->getDefaultOptions()['cookies']);
    }

    /** @test **/
    public function it_sends_login_cookies_on_follow_up_requests()
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [
                'Content-Type' => 'text/xml',
                'Set-Cookie' => 'RETS-Session-ID=session-123; Path=/',
            ], '<RETS ReplyCode="0" ReplyText="Success"><RETS-RESPONSE>Action=http://rets.example.test/action</RETS-RESPONSE></RETS>'),
            new Response(200, ['Content-Type' => 'text/plain'], 'action response'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        \PHRETS\Http\Client::set(new Client(['handler' => $stack]));

        $config = (new Configuration)
            ->setLoginUrl('http://rets.example.test/login')
            ->setUsername('test-user')
            ->setPassword('test-password');

        $session = new Session($config);
        $session->Login();

        $this->assertCount(2, $history);
        $this->assertSame('RETS-Session-ID=session-123', $history[1]['request']->getHeaderLine('Cookie'));
        $this->assertSame('session-123', $session->getRetsSessionId());
    }
}

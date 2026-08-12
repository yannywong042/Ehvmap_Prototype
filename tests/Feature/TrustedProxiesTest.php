<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_proto_header_is_honored_with_the_proxy_config_bootstrap_uses(): void
    {
        // Mirrors bootstrap/app.php's `$middleware->trustProxies(...)` call: the
        // container only ever sees the reverse proxy's IP directly, and that
        // proxy's own address isn't fixed, so '*' trusts whichever IP the
        // connection actually comes from.
        TrustProxies::at('*');
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
        );

        $request = Request::create('http://staging.eindhovenmapped.com/livewire/upload-file', 'POST');
        $request->headers->set('X-Forwarded-Proto', 'https');

        (new TrustProxies)->handle($request, function (Request $request) {
            $this->assertTrue($request->isSecure());
            $this->assertSame('https', $request->getScheme());
            $this->assertSame('https://staging.eindhovenmapped.com/livewire/upload-file', $request->url());

            return $request;
        });
    }

    public function test_signed_route_validation_only_succeeds_when_the_forwarded_proto_header_is_trusted(): void
    {
        TrustProxies::at('*');
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_PROTO);

        URL::forceScheme('https');
        $signedPath = URL::signedRoute('livewire.upload-file');
        URL::forceScheme(null);

        $rawHttpUrl = str_replace('https://', 'http://', $signedPath);

        // Without the forwarded-proto header, the container has no way to know
        // the original request was https, so the reconstructed URL used for
        // signature validation stays http and never matches — this is the 401
        // from the bug report.
        $untrustedRequest = Request::create($rawHttpUrl, 'GET');
        (new TrustProxies)->handle($untrustedRequest, fn (Request $r) => $r);
        $this->assertFalse($untrustedRequest->hasValidSignature());

        $trustedRequest = Request::create($rawHttpUrl, 'GET');
        $trustedRequest->headers->set('X-Forwarded-Proto', 'https');
        (new TrustProxies)->handle($trustedRequest, fn (Request $r) => $r);
        $this->assertTrue($trustedRequest->hasValidSignature());
    }
}

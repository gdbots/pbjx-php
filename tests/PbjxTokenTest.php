<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\PbjxToken;
use PHPUnit\Framework\TestCase;

class PbjxTokenTest extends TestCase
{
    public function testCreate()
    {
        $content = 'content';
        $aud = 'https://local.dev/pbjx';
        $kid = 'kid';
        $secret = 'secret';

        $now = 1509836741;
        PbjxToken::$timestamp = $now;

        $exp = $now + 5;
        $iat = $now;

        // note that this token matches the one in the pbjx-js unit test
        // this is intentional as we're often generating tokens with the
        // pbjx-js lib and validating them with the php version.
        $expectedJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtpZCJ9.eyJhdWQiOiJodHRwczovL2xvY2FsLmRldi9wYmp4IiwiZXhwIjoxNTA5ODM2NzQ2LCJpYXQiOjE1MDk4MzY3NDEsImp0aSI6IjZjYTk1OTRmNDQyZmY4YWFhNTUxNWJlMDFiMjRmZDE1MGIwYTI1ODdiNGI4ZWQwYTE1NzQ3YzQ0ZTk0MmIwZWYifQ.GgSB7ckv558HDKSgpSu_ZXv_uibu6J7qUAE38f8BOGg';
        $expectedJti = '6ca9594f442ff8aaa5515be01b24fd150b0a2587b4b8ed0a15747c44e942b0ef';
        $signature = substr($expectedJwt, strrpos($expectedJwt, '.') + 1);

        $token = PbjxToken::create($content, $aud, $kid, $secret, ['iat' => $iat]);

        $this->assertSame($aud, $token->getAud());
        $this->assertSame($exp, $token->getExp());
        $this->assertSame($iat, $token->getIat());
        $this->assertSame($expectedJti, $token->getJti());
        $this->assertSame($kid, $token->getKid());
        $this->assertSame(PbjxToken::urlsafeB64Decode($signature), $token->getSignature());

        $this->assertTrue($token->verify($secret), 'should verify with correct secret');
        $this->assertFalse($token->verify('invalid'), 'should NOT verify with incorrect secret');

        $this->assertSame($expectedJwt, $token->toString());
        $this->assertSame($expectedJwt, trim(json_encode($token), '"'));

        $this->assertSame($expectedJwt, PbjxToken::fromString($token->toString())->toString());
        $this->assertTrue($token->equals(PbjxToken::fromString($token->toString())));
        $this->assertFalse($token->equals(PbjxToken::create('different', $aud, $kid, $secret)));
    }

    public function testExpiredToken()
    {
        $content = 'content';
        $aud = 'https://local.dev/pbjx';
        $kid = 'kid';
        $secret = 'secret';

        PbjxToken::$timestamp = null;
        $token = PbjxToken::create($content, $aud, $kid, $secret);

        try {
            PbjxToken::$timestamp = $token->getExp() + 6;
            PbjxToken::fromString($token->toString());
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Did not allow expired token.');
            return;
        }

        $this->fail('able to create expired token');
    }

    public function testEarlyIat()
    {
        $content = 'content';
        $aud = 'https://local.dev/pbjx';
        $kid = 'kid';
        $secret = 'secret';

        PbjxToken::$timestamp = null;
        $token = PbjxToken::create($content, $aud, $kid, $secret);

        try {
            PbjxToken::$timestamp = $token->getIat() - 6;
            PbjxToken::fromString($token->toString());
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Did not allow early iat.');
            return;
        }

        $this->fail('able to create token too early');
    }

    /**
     * @dataProvider getInvalidSamples
     *
     * @param string $token
     */
    public function testInvalidSamples(string $token)
    {
        try {
            PbjxToken::fromString($token);
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Did not allow early iat.');
            return;
        }

        $this->fail("Created invalid token from: {$token}");
    }

    /**
     * @return array
     */
    public function getInvalidSamples(): array
    {
        return [
            ['not.a.token'],
            ['nope'],
            ['still.not'],
            ['1111'],
            ['{}'],
            // missing jti
            ['eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtpZCJ9.eyJhdWQiOiJodHRwczovL2xvY2FsLmRldi9wYmp4IiwiZXhwIjoxNTA5ODM2NzQ2LCJpYXQiOjE1MDk4MzY3NDF9.ghCMM6Wf3Fez2gaAw1DHCfbJNwk0Y0ON8c6a7FXYO4A'],
            // missing iat
            ['eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtpZCJ9.eyJhdWQiOiJodHRwczovL2xvY2FsLmRldi9wYmp4IiwiZXhwIjoxNTA5ODM2NzQ2LCJqdGkiOiI2Y2E5NTk0ZjQ0MmZmOGFhYTU1MTViZTAxYjI0ZmQxNTBiMGEyNTg3YjRiOGVkMGExNTc0N2M0NGU5NDJiMGVmIn0.s0wdhWrN-ElMV8LbXwWCjbDq8VIIsCpoBjtLEFhbk2M'],
            // missing exp
            ['eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtpZCJ9.eyJhdWQiOiJodHRwczovL2xvY2FsLmRldi9wYmp4IiwiaWF0IjoxNTA5ODM2NzQxLCJqdGkiOiI2Y2E5NTk0ZjQ0MmZmOGFhYTU1MTViZTAxYjI0ZmQxNTBiMGEyNTg3YjRiOGVkMGExNTc0N2M0NGU5NDJiMGVmIn0.qsIeDKNpyuCv6ovUVM_dGiNUN5T6TtcI4KMEnCXN6hc'],
            // missing aud
            ['eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtpZCJ9.eyJleHAiOjE1MDk4MzY3NDYsImlhdCI6MTUwOTgzNjc0MSwianRpIjoiNmNhOTU5NGY0NDJmZjhhYWE1NTE1YmUwMWIyNGZkMTUwYjBhMjU4N2I0YjhlZDBhMTU3NDdjNDRlOTQyYjBlZiJ9.IjgPv9TrwFf6lW5nxy_CLRILMzQ0r4n8aPiJw02-4B8'],
            // missing kid
            ['eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1MDk4MzY3NDYsImlhdCI6MTUwOTgzNjc0MSwianRpIjoiNmNhOTU5NGY0NDJmZjhhYWE1NTE1YmUwMWIyNGZkMTUwYjBhMjU4N2I0YjhlZDBhMTU3NDdjNDRlOTQyYjBlZiJ9.urh5pYLfDKub7XftrR4IqBu144serXr1lgYFq_DxVoY'],
            // missing typ
            ['eyJhbGciOiJIUzI1NiIsImtpZCI6ImtpZCJ9.eyJleHAiOjE1MDk4MzY3NDYsImlhdCI6MTUwOTgzNjc0MSwianRpIjoiNmNhOTU5NGY0NDJmZjhhYWE1NTE1YmUwMWIyNGZkMTUwYjBhMjU4N2I0YjhlZDBhMTU3NDdjNDRlOTQyYjBlZiJ9.cP7r-81rGBtbgZNwFy6rE73rle6JG4Tio6bVNTO4-30'],
            // missing alg
            ['eyJ0eXAiOiJKV1QiLCJraWQiOiJraWQiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE1MDk4MzY3NDYsImlhdCI6MTUwOTgzNjc0MSwianRpIjoiNmNhOTU5NGY0NDJmZjhhYWE1NTE1YmUwMWIyNGZkMTUwYjBhMjU4N2I0YjhlZDBhMTU3NDdjNDRlOTQyYjBlZiJ9.qB5sNdXKD3SsFVVCGcE3oA733r1lu95oyY_43uUQFSk'],
        ];
    }
}

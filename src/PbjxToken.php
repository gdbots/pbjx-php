<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Util\StringUtil;
use Gdbots\Pbjx\Exception\InvalidArgumentException;

/**
 * PbjxTokens are JWT
 * @link https://tools.ietf.org/html/rfc7519
 *
 * We enforce a certain structure and ttl for PbjxTokens
 * as they are intended to be one time use tokens (nonce)
 * and are unique per message payload being sent.
 */
final class PbjxToken implements \JsonSerializable
{
    /**
     * Allow the current timestamp to be specified.
     * Useful for fixing a value within unit testing.
     *
     * Will default to PHP time() value if null.
     *
     * @var int|null
     */
    public static ?int $timestamp = null;

    /**
     * The algorithm and type of encryption scheme to use when signing
     * Currently only HS256 (HMAC-SHA-256) is supported or allowed.
     */
    private const ALGO = 'HS256';

    /**
     * The hash_hmac algo used to generate the signature.
     * @link http://php.net/manual/en/function.hash-hmac.php
     */
    private const HASH_HMAC_ALGO = 'sha256';

    /**
     * The ttl (time to live), in seconds, for a token.
     * Used to create the "exp" claim.
     */
    private const TTL = 10;

    /**
     * Seconds to allow time skew for time sensitive signatures
     */
    private const LEEWAY = 300;

    /**
     * The token JWT in string format.
     *
     * @var string
     */
    private string $token;
    private array $header;
    private array $payload;
    private string $signature;

    private function __construct()
    {
    }

    /**
     * PbjxTokens are JWT so the arguments are used to create the payload
     * of the JWT with our own requirements/conventions.
     *
     * @param string $content Pbjx content (combined with aud and iat then hashed to create a jti)
     * @param string $aud     Pbjx endpoint this token will be sent to (or was sent to).
     * @param string $kid     Key ID used to sign the JWT.
     * @param string $secret  Secret used to sign the JWT.
     * @param array  $options Additional options for JWT creation (exp,iat)
     *
     * @return self
     */
    public static function create(string $content, string $aud, string $kid, string $secret, array $options = []): self
    {
        $header = [
            'alg' => self::ALGO,
            'typ' => 'JWT',
            'kid' => $kid,
        ];

        $now = self::$timestamp ?: time();
        $iat = $options['iat'] ?? $now;

        $payload = [
            'aud' => $aud,
            'exp' => $options['exp'] ?? $now + self::TTL,
            'iat' => $iat,
            'jti' => hash_hmac(self::HASH_HMAC_ALGO, "{$aud}{$iat}{$content}", $secret),
        ];

        $stringToSign = implode('.', [
            StringUtil::urlsafeB64Encode(json_encode($header)),
            StringUtil::urlsafeB64Encode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ]);

        $binarySignature = hash_hmac(self::HASH_HMAC_ALGO, $stringToSign, $secret, true);
        $signature = StringUtil::urlsafeB64Encode($binarySignature);
        $instance = new self();
        $instance->token = "{$stringToSign}.{$signature}";
        $instance->header = $header;
        $instance->payload = $payload;
        $instance->signature = $binarySignature;
        $instance->checkClaims();

        return $instance;
    }

    public static function fromString(string $token): self
    {
        $instance = new self();
        $instance->token = $token;

        $parsed = explode('.', $token);
        if (count($parsed) !== 3) {
            throw new InvalidArgumentException('PbjxToken string is invalid.');
        }

        $instance->header = json_decode(StringUtil::urlsafeB64Decode($parsed[0]), true);
        $instance->payload = json_decode(StringUtil::urlsafeB64Decode($parsed[1]), true);
        $instance->signature = StringUtil::urlsafeB64Decode($parsed[2]);
        $instance->checkClaims();

        $instance->payload['exp'] = (int)$instance->payload['exp'];
        $instance->payload['iat'] = (int)$instance->payload['iat'];

        return $instance;
    }

    public function getHeader(): array
    {
        return $this->header;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getKid(): string
    {
        return $this->header['kid'];
    }

    /**
     * @link https://tools.ietf.org/html/rfc7519#section-4.1.3
     *
     * @return string
     */
    public function getAud(): string
    {
        return $this->payload['aud'];
    }

    /**
     * @link https://tools.ietf.org/html/rfc7519#section-4.1.4
     *
     * @return int unix timestamp in seconds
     */
    public function getExp(): int
    {
        return $this->payload['exp'];
    }

    /**
     * @link https://tools.ietf.org/html/rfc7519#section-4.1.6
     *
     * @return int unix timestamp in seconds
     */
    public function getIat(): int
    {
        return $this->payload['iat'];
    }

    /**
     * @link https://tools.ietf.org/html/rfc7519#section-4.1.7
     *
     * @return string
     */
    public function getJti(): string
    {
        return $this->payload['jti'];
    }

    /**
     * Verify the token signature matches when signed with the given secret.
     *
     * @param string $secret
     *
     * @return bool
     */
    public function verify(string $secret): bool
    {
        try {
            $withoutSig = substr($this->token, 0, strrpos($this->token, '.'));
            $expected = hash_hmac(self::HASH_HMAC_ALGO, $withoutSig, $secret, true);
            return hash_equals($expected, $this->signature);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function equals(PbjxToken $token): bool
    {
        return $token->token === $this->token;
    }

    public function toString(): string
    {
        return $this->token;
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private function checkClaims(): void
    {
        if (!is_array($this->header)) {
            throw new InvalidArgumentException('PbjxToken header encoding is invalid.');
        }

        if (!isset($this->header['alg'])
            || $this->header['alg'] !== self::ALGO
            || !isset($this->header['typ'])
            || $this->header['typ'] !== 'JWT'
            || !isset($this->header['kid'])
        ) {
            throw new InvalidArgumentException('PbjxToken header is invalid.');
        }

        if (!is_array($this->payload)) {
            throw new InvalidArgumentException('PbjxToken payload encoding is invalid.');
        }

        if (!isset($this->payload['aud'])
            || !isset($this->payload['exp'])
            || !isset($this->payload['iat'])
            || !isset($this->payload['jti'])
        ) {
            throw new InvalidArgumentException('PbjxToken payload is invalid.');
        }

        $now = self::$timestamp ?: time();
        if ($this->payload['iat'] > ($now + self::LEEWAY)) {
            throw new InvalidArgumentException('PbjxToken cannot be handled prior to iat.');
        }

        if (($now - self::LEEWAY) >= $this->payload['exp']) {
            throw new InvalidArgumentException('PbjxToken has expired.');
        }
    }
}

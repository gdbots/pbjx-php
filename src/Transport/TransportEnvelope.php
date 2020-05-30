<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Serializer\JsonSerializer;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbj\Serializer\Serializer;

final class TransportEnvelope implements \JsonSerializable
{
    const SERIALIZER_JSON = 'json';
    const SERIALIZER_PHP = 'php';

    /** @var Serializer[] */
    private static array $serializers = [];
    private Message $message;
    private string $serializer;

    public function __construct(Message $message, string $serializer)
    {
        $this->message = $message;
        $this->serializer = $serializer;
    }

    public static function getSerializer(string $serializer): Serializer
    {
        if (!isset(self::$serializers[$serializer])) {
            switch ($serializer) {
                case self::SERIALIZER_PHP:
                    self::$serializers[$serializer] = new PhpSerializer();
                    break;

                case self::SERIALIZER_JSON:
                    self::$serializers[$serializer] = new JsonSerializer();
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('Serializer [%s] is not supported.', $serializer));
            }
        }

        return self::$serializers[$serializer];
    }

    /**
     * Recreates the envelope from a json string.
     *
     * @param string $envelope
     *
     * @return self
     */
    public static function fromString(string $envelope): self
    {
        $envelope = json_decode($envelope, true);
        if (!is_array($envelope)) {
            throw new \InvalidArgumentException('Envelope is invalid. ' . json_last_error_msg());
        }

        $serializer = isset($envelope['serializer']) ? $envelope['serializer'] : 'php';
        $isReplay = isset($envelope['is_replay']) ? filter_var($envelope['is_replay'], FILTER_VALIDATE_BOOLEAN) : false;
        $message = self::getSerializer($serializer)->deserialize(isset($envelope['message']) ? $envelope['message'] : '');
        $message->isReplay($isReplay);

        return new self($message, $serializer);
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function isReplay(): bool
    {
        return $this->message->isReplay();
    }

    public function getSerializerUsed(): string
    {
        return $this->serializer;
    }

    /**
     * Returns a json string version of the envelope.
     *
     * @return string
     */
    public function toString(): string
    {
        return json_encode($this);
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function jsonSerialize()
    {
        return [
            'serializer' => $this->serializer,
            'is_replay'  => $this->message->isReplay(),
            'message'    => self::getSerializer($this->serializer)->serialize($this->message),
        ];
    }
}

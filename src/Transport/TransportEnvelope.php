<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Serializer\JsonSerializer;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbj\Serializer\Serializer;
use Gdbots\Pbj\Serializer\YamlSerializer;

final class TransportEnvelope
{
    /** @var Serializer[] */
    private static $serializers = [];

    /** @var Message */
    private $message;

    /** @var string */
    private $serializer;

    /**
     * @param Message $message
     * @param string  $serializer
     */
    public function __construct(Message $message, string $serializer)
    {
        $this->message = $message;
        $this->serializer = $serializer;
    }

    /**
     * @param string $serializer
     *
     * @return Serializer
     */
    public static function getSerializer(string $serializer): Serializer
    {
        if (!isset(self::$serializers[$serializer])) {
            switch ($serializer) {
                case 'php':
                    self::$serializers[$serializer] = new PhpSerializer();
                    break;

                case 'json':
                    self::$serializers[$serializer] = new JsonSerializer();
                    break;

                case 'yaml':
                    self::$serializers[$serializer] = new YamlSerializer();
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

        /*
         * don't attempt to set replay if it's the php serializer because it already knows
         * if it's a replay as the entire object is serialized with its internal state.
         *
         * this is a PHP feature only, all other serializers have no notion of the
         * language specific in memory object.  we use php serializer because it's
         * very fast to un/de-serialize the objects.
         */
        if ($isReplay && 'php' !== $serializer) {
            $message->isReplay(true);
        }

        return new self($message, $serializer);
    }

    /**
     * Returns a json string version of the envelope.
     *
     * @return string
     */
    public function toString(): string
    {
        return json_encode([
            'serializer' => $this->serializer,
            'is_replay'  => $this->message->isReplay(),
            'message'    => self::getSerializer($this->serializer)->serialize($this->message),
        ]);
    }

    /**
     * @return Message
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    public function isReplay(): bool
    {
        return $this->message->isReplay();
    }

    /**
     * @return string
     */
    public function getSerializerUsed(): string
    {
        return $this->serializer;
    }
}

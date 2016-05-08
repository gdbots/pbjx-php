<?php

namespace Gdbots\Pbjx;

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
     * @param string $serializer
     */
    public function __construct(Message $message, $serializer)
    {
        $this->message = $message;
        $this->serializer = $serializer;
    }

    /**
     * @param string $serializer
     * @return Serializer
     */
    public static function getSerializer($serializer)
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
     * @return self
     */
    public static function fromString($envelope)
    {
        $envelope = json_decode($envelope, true);
        if (!is_array($envelope)) {
            throw new \InvalidArgumentException('Envelope is invalid.');
        }

        $serializer = isset($envelope['serializer']) ? $envelope['serializer'] : 'php';
        $isReplay = isset($envelope['replay']) ? filter_var($envelope['replay'], FILTER_VALIDATE_BOOLEAN) : false;
        $data = isset($envelope['data']) ? $envelope['data'] : '';
        $message = self::getSerializer($serializer)->deserialize($data);

        if ($isReplay) {
            $message->isReplay(true);
        }

        return new self($message, $serializer);
    }

    /**
     * Returns a json string version of the envelope.
     *
     * @return string
     */
    public function toString()
    {
        return json_encode([
            'serializer' => $this->serializer,
            'replay' => $this->message->isReplay(),
            'data' => self::getSerializer($this->serializer)->serialize($this->message)
        ]);
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    public function isReplay()
    {
        return $this->message->isReplay();
    }

    /**
     * @return string
     */
    public function getSerializerUsed()
    {
        return $this->serializer;
    }
}

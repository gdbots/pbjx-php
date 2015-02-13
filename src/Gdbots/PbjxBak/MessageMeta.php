<?php

namespace Gdbots\PbjxBack;

use Rhumsaa\Uuid\Uuid;
use Symfony\Component\HttpFoundation\ParameterBag;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Common\Util\StringUtils;

final class MessageMeta implements \JsonSerializable
{
    const META       = '_meta';
    const CURIE      = 'curie';
    const MESSAGE_ID = 'id';
    const CORREL_ID  = 'correl_id';
    const VERSION    = 'version';
    const TIMESTAMP  = 'ts';
    const CREATED_AT = 'created';

    /**
     * Array of reserved properties that cannot be modified with the "set" method.
     *
     * @var array
     */
    private static $reserved = array(
            self::META,
            self::CURIE,
            self::MESSAGE_ID,
            self::CORREL_ID,
            self::VERSION,
            self::TIMESTAMP,
            self::CREATED_AT,
        );

    /* @var ParameterBag */
    private $meta;

    /**
     * Flag to indicate this message is being replayed.  A consumer might be
     * rebuilding/restoring data and handlers/subscribers may need to operate
     * differently in the case of a replayed message.
     *
     * @var bool
     */
    private $isReplay;

    /**
     * @param MessageInterface $message
     * @param array $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(MessageInterface $message, array $data = array())
    {
        $this->meta = new ParameterBag();

        foreach ($data as $key => $val) {
            switch ($key) {
                case self::CURIE:
                    if (!$val instanceof MessageCurie) {
                        $this->meta->set(self::CURIE, MessageCurie::fromString($val));
                    }
                    break;

                case self::MESSAGE_ID:
                    if (!$val instanceof Uuid) {
                        $val = Uuid::fromString($val);
                    }
                    $this->setMessageId($val);
                    break;

                case self::CORREL_ID:
                    if (!$val instanceof Uuid) {
                        $val = Uuid::fromString($val);
                    }
                    $this->setCorrelId($val);
                    break;

                case self::VERSION:
                    $val = (int) $val;
                    if ($val > 1) {
                        $this->meta->set(self::VERSION, (int) $val);
                    }
                    break;

                case self::TIMESTAMP:
                    $this->setTimestamp($val);
                    break;

                case self::CREATED_AT:
                    $this->setCreatedAt($val);
                    break;

                default:
                    $this->set($key, $val);
                    break;
            }
        }

        if (!$this->meta->has(self::CURIE)) {
            $this->meta->set(self::CURIE, MessageCurie::fromMessage($message));
        }

        if (!$this->meta->has(self::MESSAGE_ID)) {
            $this->setMessageId(Uuid::uuid1());
        }

        if (!$this->meta->has(self::VERSION)) {
            $version = (int) $message->getMessageVersion();
            if ($version > 1) {
                $this->meta->set(self::VERSION, $version);
            }
        }

        if (!$this->meta->has(self::TIMESTAMP)) {
            $this->meta->set(self::TIMESTAMP, time());
        }
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->meta->all();
    }

    /**
     * Returns true if this message is being replayed.  Providing a value
     * will set the flag but this can only be done once.
     *
     * @param bool $replay
     * @return bool
     *
     * @throws \LogicException
     */
    public function isReplay($replay = null)
    {
        if (null === $replay) {
            if (null === $this->isReplay) {
                $this->isReplay = false;
            }

            return $this->isReplay;
        }

        if (null === $this->isReplay) {
            $this->isReplay = (bool) $replay;
            return $this->isReplay;
        }

        throw new \LogicException('You can only set the replay mode one time.');
    }

    /**
     * Returns the message curie.
     *
     * @link http://en.wikipedia.org/wiki/CURIE
     *
     * @return MessageCurie
     */
    public function getCurie()
    {
        return $this->meta->get(self::CURIE);
    }

    /**
     * Returns the message id.
     *
     * @return Uuid
     */
    public function getMessageId()
    {
        return Uuid::fromString($this->meta->get(self::MESSAGE_ID));
    }

    /**
     * Sets the message id.
     *
     * @param Uuid $uuid
     * @return self
     */
    public function setMessageId(Uuid $uuid)
    {
        $this->meta->set(self::MESSAGE_ID, (string) $uuid);
        return $this;
    }

    /**
     * Returns true if the message has a correlation id.
     *
     * @return boolean
     */
    public function hasCorrelId()
    {
        return $this->meta->has(self::CORREL_ID);
    }

    /**
     * Returns the correlation id.
     *
     * @return Uuid|null
     */
    public function getCorrelId()
    {
        if ($this->hasCorrelId()) {
            return Uuid::fromString($this->meta->get(self::CORREL_ID));
        }
        return null;
    }

    /**
     * Sets the correlation id.
     *
     * @param Uuid $uuid
     * @return self
     */
    public function setCorrelId(Uuid $uuid)
    {
        $this->meta->set(self::CORREL_ID, (string) $uuid);
        return $this;
    }

    /**
     * Returns the message version.
     *
     * @return Uuid
     */
    public function getVersion()
    {
        return $this->meta->get(self::VERSION, 1);
    }

    /**
     * Returns the timestamp.
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->meta->get(self::TIMESTAMP);
    }

    /**
     * Sets the timestamp
     *
     * @param integer $ts
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setTimestamp($ts)
    {
        if (!DateUtils::isValidTimestamp($ts)) {
            throw new \InvalidArgumentException(sprintf('Timestamp provided [%s] is invalid.', $ts));
        }
        $this->meta->set(self::TIMESTAMP, (int) $ts);
        return $this;
    }

    /**
     * Returns the created at timestamp.
     *
     * @return int
     */
    public function getCreatedAt()
    {
        return $this->meta->get(self::CREATED_AT);
    }

    /**
     * Sets the created at timestamp.
     *
     * @param integer $ts
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setCreatedAt($ts)
    {
        if (!DateUtils::isValidTimestamp($ts)) {
            throw new \InvalidArgumentException(sprintf('Created at timestamp provided [%s] is invalid.', $ts));
        }
        $this->meta->set(self::CREATED_AT, (int) $ts);
        return $this;
    }

    /**
     * Returns a meta by name.
     *
     * @param string  $key
     * @param mixed   $default The default value if the meta key does not exist
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        return $this->meta->get($this->formatKey($key), $default);
    }

    /**
     * Sets a meta by name.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     *
     * @throws \LogicException
     */
    public function set($key, $value)
    {
        $key = $this->formatKey($key);
        if ($this->isReserved($key)) {
            throw new \LogicException(sprintf('The [%s] property cannot be changed with the set method.', $key));
        }

        // todo: remove casting once Uuid implements JsonSerializable
        if ($value instanceof Uuid) {
            $value = (string) $value;
        }

        $this->meta->set($key, $value);
        return $this;
    }

    /**
     * Returns true if the meta is defined.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->meta->has($this->formatKey($key));
    }

    /**
     * Returns a formatted key that is snaked_cased.
     *
     * @param string $key
     *
     * @return string
     */
    private function formatKey($key)
    {
        return StringUtils::toSnakeCaseFromCamelCase(preg_replace('/[^a-z0-9_]/i', '_', $key));
    }

    /**
     * Returns true if the meta is reserved.
     *
     * @param string $key
     *
     * @return bool
     */
    private function isReserved($key)
    {
        return in_array($key, self::$reserved);
    }
}

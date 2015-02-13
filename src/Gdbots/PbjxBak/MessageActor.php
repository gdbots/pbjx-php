<?php

namespace Gdbots\PbjxBack;

use Rhumsaa\Uuid\Uuid;
use Symfony\Component\HttpFoundation\ParameterBag;
use Gdbots\Common\Util\StringUtils;

final class MessageActor implements \JsonSerializable
{
    const ACTOR    = '_actor';
    const ACTOR_ID = 'id';

    /**
     * Array of reserved properties that cannot be modified with the "set" method.
     *
     * @var array
     */
    private static $reserved = array(
            self::ACTOR,
            self::ACTOR_ID,
        );

    /* @var ParameterBag */
    private $actor;

    /**
     * @param array $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data = array())
    {
        $this->actor = new ParameterBag();

        foreach ($data as $key => $val) {
            switch ($key) {
                case self::ACTOR_ID:
                    if (!$val instanceof Uuid) {
                        $val = Uuid::fromString($val);
                    }
                    $this->setActorId($val);
                    break;

                default:
                    $this->set($key, $val);
                    break;
            }
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
        return $this->actor->all();
    }

    /**
     * Returns true if the message has an actor id.
     *
     * @return boolean
     */
    public function hasActorId()
    {
        return $this->actor->has(self::ACTOR_ID);
    }

    /**
     * Returns the actor id.
     *
     * @return Uuid|null
     */
    public function getActorId()
    {
        if ($this->hasActorId()) {
            return Uuid::fromString($this->actor->get(self::ACTOR_ID));
        }
        return null;
    }

    /**
     * Sets the actor id.
     *
     * @param Uuid $uuid
     * @return self
     */
    public function setActorId(Uuid $uuid)
    {
        $this->actor->set(self::ACTOR_ID, (string) $uuid);
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
        return $this->actor->get($this->formatKey($key), $default);
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

        $this->actor->set($key, $value);
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
        return $this->actor->has($this->formatKey($key));
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

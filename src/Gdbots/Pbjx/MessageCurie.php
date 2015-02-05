<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\StringUtils;

final class MessageCurie implements \JsonSerializable
{
    const COMMAND = 'cmd';
    const EVENT   = 'evt';
    const REQUEST = 'req';

    /**
     * Regular expression pattern for matching a valid message curie.
     * Format = namespace:type:service:message
     *
     * @constant string
     */
    const VALID_PATTERN = '^[a-z]+[0-9a-z-]+:(cmd|evt|req):[a-z]+[0-9a-z\.-]+:[a-z]+[0-9a-z-]+$';

    /**
     * Map of message type short names to their package name / class suffix
     *
     * @var array
     */
    private static $types = array('cmd' => 'Command', 'evt' => 'Event', 'req' => 'Request');

    /**
     * Local cache of all curies generated.
     *
     * @var MessageCurie[]
     */
    private static $curies = array();

    /**
     * Local cache of all curie to class name lookups.
     *
     * @var array
     */
    private static $curieToClass = array();

    /**
     * Local cache of all class name to curie lookups.
     *
     * @var array
     */
    private static $classToCurie = array();

    /**
     * The root namespace for this message which is typically your application
     * or organization name.  It should translate to the root php namespace
     * when it is camelized.
     *
     * Examples:
     *      my-app -> MyApp
     *      mycompany -> Mycompany
     *
     * @var string
     */
    private $namespace;

    /**
     * Type of message (cmd, evt, req)
     *
     * @var string
     */
    private $type;

    /**
     * Service the message belongs to.  This will translate to the php namespace
     * the message exists in.
     *
     * Examples:
     *      some-service -> SomeService
     *      some-service.sub-thing -> SomeService\SubThing
     *
     * @var string
     */
    private $service;

    /**
     * Name of the message.  e.g. create-video, add-image-to-video, video-created
     *
     * @var string
     */
    private $message;

    /**
     * @param string $namespace
     * @param string $type
     * @param string $service
     * @param string $message
     *
     * @throws \InvalidArgumentException
     */
    private function __construct($namespace, $type, $service, $message)
    {
        $this->namespace = $namespace;
        $this->type = $type;
        $this->service = $service;
        $this->message = $message;

        if (!self::isValid($this->toString())) {
            throw new \InvalidArgumentException('Invalid MessageCurie: ' . $this);
        }

        self::$curies[$this->toString()] = $this;
    }

    /**
     * Converts this object to a string when the object is used in any
     * string context.  This is the fully qualified message curie.
     *
     * @link http://www.php.net/manual/en/language.oop5.magic.php#object.tostring
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return sprintf('%s:%s:%s:%s', $this->namespace, $this->type, $this->service, $this->message);
    }

    /**
     * Check if a string is a valid message curie
     *
     * @param string $curie
     * @return boolean
     */
    public static function isValid($curie)
    {
        $curie = trim((string) $curie);
        if (empty($curie)) {
            return false;
        }

        return preg_match('/' . self::VALID_PATTERN . '/', $curie);
    }

    /**
     * Creates a MessageCurie from the message.
     *
     * @param MessageInterface $message
     * @return MessageCurie
     *
     * @throws \LogicException
     */
    public static function fromMessage(MessageInterface $message)
    {
        $class = get_class($message);
        if (isset(self::$classToCurie[$class])) {
            return self::$curies[self::$classToCurie[$class]];
        }

        $type = null;
        $longType = null;

        if ($message instanceof CommandBus\CommandInterface) {
            $longType = 'Command';
        } elseif ($message instanceof EventBus\DomainEventInterface) {
            $longType = 'Event';
        } elseif ($message instanceof RequestBus\RequestInterface) {
            $longType = 'Request';
        }

        $type = array_search($longType, self::$types);

        if (null === $type) {
            throw new \LogicException(sprintf('Class [%s] must be a command, event or request.', $class));
        }

        $parts = explode('\\', $class);
        if (count($parts) < 4) {
            throw new \LogicException(sprintf('Class [%s] does not follow Vendor\\Package[\\SubPackage]\\%s\\Something%s convention.', $class, $type, $type));
        }

        $namespace = StringUtils::toSlugFromCamelCase(array_shift($parts));
        $message = StringUtils::toSlugFromCamelCase(str_replace($longType, '', array_pop($parts)));

        $service = [];
        foreach ($parts as $part) {
            if ($longType === $part) {
                continue;
            }

            $service[] = StringUtils::toSlugFromCamelCase($part);
        }

        $curie = new self($namespace, $type, implode('.', $service), $message);
        self::$classToCurie[$class] = $curie->toString();

        return $curie;
    }

    /**
     * Creates a MessageCurie from the string representation.
     *
     * @param string $curie
     * @return MessageCurie
     */
    public static function fromString($curie)
    {
        if (isset(self::$curies[$curie])) {
            return self::$curies[$curie];
        }

        list($namespace, $type, $service, $message) = explode(':', $curie);
        return new self($namespace, $type, $service, $message);
    }

    /**
     * Converts the message curie to a php class name.  This ONLY works if the
     * class name follows the PSR0 convention with classes existing
     * in the provided type namespace of the service and the message class itself
     * ending in "Command|DomainEvent|Request".
     *
     * Examples:
     *      - Namespace\Service\Command\DoSomethingCommand
     *      - Namespace\Service\DomainEvent\DidSomethingDomainEvent
     *      - Namespace\Service\Request\GetSomethingRequest
     *
     * @param string $curie
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function getClassName($curie)
    {
        if (isset(self::$curieToClass[$curie])) {
            return self::$curieToClass[$curie];
        }

        if (!self::isValid($curie)) {
            throw new \InvalidArgumentException('Invalid MessageCurie: ' . $curie);
        }

        list($namespace, $type, $service, $message) = explode(':', $curie);

        $namespace = StringUtils::toCamelCaseFromSlug($namespace);
        $longType = self::$types[$type];
        $service = str_replace('.', '\\', StringUtils::toCamelCaseFromSlug($service));
        $message = StringUtils::toCamelCaseFromSlug($message);

        $class = sprintf('%s\%s\%s\%s%s', $namespace, $service, $longType, $message, $longType);
        self::$curieToClass[$curie] = $class;
        return $class;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}

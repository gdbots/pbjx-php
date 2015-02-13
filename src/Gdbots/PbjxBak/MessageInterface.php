<?php

namespace Gdbots\PbjxBack;

interface MessageInterface
{
    /**
     * Returns the version number for this message.
     * Default should be 1.
     *
     * Refers to the schema version and is used to initiate
     * transformation when consumers receive messages with
     * different versions.
     *
     * @return int
     */
    public function getMessageVersion();

    /**
     * @return MessageMeta
     */
    public function meta();

    /**
     * @return MessageActor
     */
    public function actor();

    /**
     * Returns a new message from the provided array.  The array
     * should be data returned from toArray or at least match
     * that signature.
     *
     * @param array $data
     * @return MessageInterface
     */
    public static function fromArray(array $data = array());

    /**
     * Returns the message as an associative array.  Array keys
     * should be snake_case and setters should be CamelCase.
     *
     * All setters are ideally protected so your message is immutable.
     *
     * For example, if your toArray returns the following:
     *      [
     *          'first_name'   => 'John Doe',
     *          'email'        => 'john@domain.com',
     *          'a_third_prop' => ['some', 'things', 'andstuff']
     *      ]
     *
     * You would have the following setters on your message:
     *      setFirstName($val)
     *      setEmail($val)
     *      setAThirdProp(array $array = array())
     *
     * @return array
     * @throws \LogicException
     */
    public function toArray();
}
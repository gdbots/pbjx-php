<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Util;

final class ShardUtils
{
    /**
     * Determines what shard the provided string should be on.
     *
     * @param string $string String input use to determine shard.
     * @param int    $shards Size of shard pool.
     *
     * @return int  Returns an integer between 0 and ($shards-1), i.e. 0-255
     */
    public static function determineShard($string, $shards = 256): int
    {
        // first 4 chars of md5 give us a 16 bit keyspace (0-65535)
        $num = hexdec(substr(md5($string), 0, 4));
        return $num % $shards;
    }
}

<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Propagation;

use Google\Cloud\PubSub\Message;
use Illuminate\Support\Arr;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Exceptions\InvalidPropagationKey;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Setter;

class GooglePubSub implements Getter, Setter
{
    /**
     * Gets the first value of the given propagation key or returns null
     *
     * @param $carrier
     * @param  string  $key
     * @return string|null
     */
    public function get($carrier, string $key): ?string
    {
        if ($carrier instanceof Message) {
            return $carrier->attribute(strtolower($key));
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }

    /**
     * Replaces a propagated key with the given value
     *
     * @param $carrier
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function put(&$carrier, string $key, string $value): void
    {
        if ($key === '') {
            throw InvalidPropagationKey::forEmptyKey();
        }

        if (is_array($carrier)) {
            $lKey = strtolower($key);
            Arr::set($carrier, "attributes.$lKey", $value);
            return;
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }
}

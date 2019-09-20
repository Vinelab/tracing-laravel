<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Propagation;

use Illuminate\Http\Request;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Exceptions\InvalidPropagationKey;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Setter;

class IlluminateHttp implements Getter, Setter
{
    /**
     * Gets the first value of the given propagation key or returns null
     *
     * @param \Illuminate\Http\Request $carrier
     * @param  string  $key
     * @return string|null
     */
    public function get($carrier, $key)
    {
        if ($carrier instanceof Request) {
            return $carrier->header(strtolower($key));
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }

    /**
     * Replaces a propagated key with the given value
     *
     * @param  mixed  $carrier
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function put(&$carrier, $key, $value)
    {
        if ($key === '') {
            throw InvalidPropagationKey::forEmptyKey();
        }

        if ($carrier instanceof Request) {
            $carrier->headers->set(strtolower($key), $value);
            return;
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }
}

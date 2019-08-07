<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Propagation;

use Psr\Http\Message\RequestInterface;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Exceptions\InvalidPropagationKey;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Setter;

class PsrRequest implements Getter, Setter
{
    /**
     * Gets the first value of the given propagation key or returns null
     *
     * @param \Illuminate\Http\Request $carrier
     * @param  string  $key
     * @return string|null
     */
    public function get($carrier, string $key): ?string
    {
        if ($carrier instanceof RequestInterface) {
            $headers = $carrier->getHeader(strtolower($key));
            return $headers ? $headers[0] : null;
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
    public function put(&$carrier, string $key, string $value): void
    {
        if ($key === '') {
            throw InvalidPropagationKey::forEmptyKey();
        }

        if ($carrier instanceof RequestInterface) {
            $carrier = $carrier->withHeader(strtolower($key), $value);
            return;
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }
}

<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Propagation;

use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Exceptions\InvalidPropagationKey;
use Zipkin\Propagation\Setter;

class VinelabHttp implements Setter
{
    /**
     * Replaces a propagated key with the given value
     *
     * @param $carrier
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function put(&$carrier, $key, $value)
    {
        if ($key === '') {
            throw InvalidPropagationKey::forEmptyKey();
        }

        if (is_array($carrier)) {
            if (!isset($carrier['headers'])) {
                $carrier['headers'] = [];
            }

            array_push($carrier['headers'], sprintf('%s: %s', strtolower($key), $value));
            return;
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }
}

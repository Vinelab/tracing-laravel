<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Propagation;

use Illuminate\Support\Arr;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Exceptions\InvalidPropagationKey;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Setter;

class AMQP implements Getter, Setter
{
    /**
     * Gets the first value of the given propagation key or returns null
     *
     * @param $carrier
     * @param  string  $key
     * @return string|null
     */
    public function get($carrier, $key)
    {
        if ($carrier instanceof AMQPMessage) {
            /** @var AMQPTable $amqpTable */
            $amqpTable = Arr::get($carrier->get_properties(), 'application_headers', new AMQPTable);

            return Arr::get($amqpTable->getNativeData(), strtolower($key));
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
    public function put(&$carrier, $key, $value)
    {
        if ($key === '') {
            throw InvalidPropagationKey::forEmptyKey();
        }

        if ($carrier instanceof AMQPMessage) {
            /** @var AMQPTable $amqpTable */
            $amqpTable = Arr::get($carrier->get_properties(), 'application_headers', new AMQPTable);

            $amqpTable->set(strtolower($key), $value);

            $carrier->set('application_headers', $amqpTable);

            return;
        }

        throw InvalidPropagationCarrier::forCarrier($carrier);
    }
}

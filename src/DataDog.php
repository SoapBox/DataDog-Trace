<?php

namespace SoapBox\DataDogTrace;

class DataDog
{
    /**
     * Trace class and method
     *
     * @param string $class
     * @param string $method
     *
     * @return \SoapBox\DataDog\Trace\Trace
     */
    public static function trace(string $class, string $method): Trace
    {
        return new Trace($class, $method);
    }

    /**
     * Trace a class and static method
     *
     * @param string $class
     * @param string $method
     *
     * @return \SoapBox\DataDog\Trace\Trace
     */
    public static function traceStatic(string $class, string $method): Trace
    {
        return new Trace($class, $method, true);
    }
}

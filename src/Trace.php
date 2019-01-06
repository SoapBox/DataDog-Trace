<?php

namespace SoapBox\DataDogTrace;

use Exception;
use OpenTracing\GlobalTracer;

class Trace
{
    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var array
     */
    private $tagCallbacks = [];

    /**
     * Create a new trace for the given class and method
     *
     * @param string $class
     * @param string $method
     * @param bool $static
     */
    public function __construct(string $class, string $method, bool $static = false)
    {
        $this->class = $class;
        $this->method = $method;
        $this->static = $static;
        $this->setResource("$this->class.$this->method");
        $this->setServiceName(config('app.name'));
        $this->asWebType();
    }

    /**
     * Register this trace with the datadog tracer
     */
    public function __destruct()
    {
        $class = $this->class;
        $method = $this->method;
        $tagCallbacks = $this->tagCallbacks;
        $static = $this->static;

        $tagCallbacks[] = function () {
            return $this->tags;
        };

        dd_trace($this->class, $this->method, function (...$args) use ($class, $method, $tagCallbacks, $static) {
            $scope = GlobalTracer::get()->startActiveSpan("$class.$method");
            $span = $scope->getSpan();

            foreach ($tagCallbacks as $callback) {
                foreach (call_user_func_array($callback, $args) as $key => $value) {
                    $span->setTag($key, $value);
                }
            }

            try {
                if ($static) {
                    return call_user_func_array([$class, $method], $args);
                }

                return call_user_func_array([$this, $method], $args);
            } catch (Exception $e) {
                $span->setError($e);
                throw $e;
            } finally {
                $span->finish();
            }
        });
    }

    /**
     * Set the resource tag for this trace
     *
     * @param string $resource
     *
     * @return self
     */
    public function setResource(string $resource)
    {
        $this->withTags([\DDTrace\Tags\RESOURCE_NAME => $resource]);
        return $this;
    }

    /**
     * Set the service name tag for this trace
     *
     * @param string $name
     *
     * @return self
     */
    public function setServiceName(string $name)
    {
        $this->withTags([\DDTrace\Tags\SERVICE_NAME => $name]);
        return $this;
    }

    /**
     * Mark the span type as cache for this trace
     *
     * @return self
     */
    public function asCacheType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\CACHE]);
        return $this;
    }

    /**
     * Mark the span type as http client for this trace
     *
     * @return self
     */
    public function asHttpType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\HTTP_CLIENT]);
        return $this;
    }

    /**
     * Mark the span type as web for this trace
     *
     * @return self
     */
    public function asWebType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\WEB_SERVLET]);
        return $this;
    }

    /**
     * Mark the span type as sql for this trace
     *
     * @return self
     */
    public function asSqlType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\SQL]);
        return $this;
    }

    /**
     * Add th given tags to this trace
     *
     * @param array|callable $tags
     *
     * @return self
     */
    public function withTags($tags)
    {
        if (is_callable($tags)) {
            $this->tagCallbacks[] = $tags;
        } else {
            $this->tags = array_merge($this->tags, $tags);
        }
        return $this;
    }
}

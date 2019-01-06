<?php

namespace SoapBox\DataDogTrace;

use Exception;
use OpenTracing\GlobalTracer;

class Trace
{
    private $tags = [];
    private $tagCallbacks = [];

    public function __construct(string $class, string $method, bool $static = false)
    {
        $this->class = $class;
        $this->method = $method;
        $this->static = $static;
        $this->setResource("$this->class.$this->method");
        $this->setServiceName(config('app.name'));
        $this->asWebType();
    }

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

    public function setResource(string $resource)
    {
        $this->withTags([\DDTrace\Tags\RESOURCE_NAME => $resource]);
        return $this;
    }

    public function setServiceName(string $name)
    {
        $this->withTags([\DDTrace\Tags\SERVICE_NAME => $name]);
        return $this;
    }

    public function asCacheType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\CACHE]);
        return $this;
    }

    public function asHttpType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\HTTP_CLIENT]);
        return $this;
    }

    public function asWebType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\WEB_SERVLET]);
        return $this;
    }

    public function asSqlType()
    {
        $this->withTags([\DDTrace\Tags\SPAN_TYPE => \DDTrace\Types\SQL]);
        return $this;
    }

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

<?php


namespace PhalconRest\Mvc;

use \Phalcon\Mvc\Micro\Collection;

/**
 * Class AtomicCollection
 * @package PhalconRest\Libraries\AtomicCollection
 *
 * send all atomic calls through a proxy (atomicMethod) where all the appropriate flags are set and a new transaction is started
 *
 */
class AtomicCollection extends Collection
{
    public function atomicPost($routePattern, $handler) {
        $this->post($routePattern, 'atomicMethod', $handler);
        return $this;
    }

    public function atomicPut($routePattern, $handler) {
        $this->put($routePattern, 'atomicMethod', $handler);
        return $this;
    }

    public function atomicDelete($routePattern, $handler) {
        $this->delete($routePattern, 'atomicMethod', $handler);
        return $this;
    }

    public function atomicPatch($routePattern, $handler) {
        $this->patch($routePattern, 'atomicMethod', $handler);
        return $this;
    }
}
<?php
$catchAll = new \Phalcon\Mvc\Micro\Collection;
$catchAll->setHandler(new class
{
    function maintenance()
    {
        throw new \PhalconRest\Exception\HTTPException('Maintenance mode', 503,
            ['more' => 'Server is down for maintenance, and will be back shortly.']);
    }
});

$catchAll->map('/:params', 'maintenance'); //:params is a "catch-the-rest" regex
return [$catchAll];
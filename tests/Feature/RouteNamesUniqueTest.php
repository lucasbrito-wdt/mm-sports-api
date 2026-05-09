<?php

it('does not register duplicate route names', function () {
    $router = app('router');
    $seen = [];

    foreach ($router->getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null) {
            continue;
        }

        expect(isset($seen[$name]))->toBeFalse("Duplicate route name: {$name}");
        $seen[$name] = true;
    }
});

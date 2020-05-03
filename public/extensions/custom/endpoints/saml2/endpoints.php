<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Directus\Application\Http\Request;
use Directus\Application\Http\Response;

return [
	// The endpoint path:
	// '' means it is located at: `/custom/<endpoint-id>`
	// '/` means it is located at: `/custom/<endpoint-id>/`
	// 'test' and `/test` means it is located at: `/custom/<endpoint-id>/test
	// if the handler is a Closure or Anonymous function, it's binded to the app container. Which means $this = to the app container.
	'login_check' => [
		'method' => 'POST',
		'handler' => function (Request $request, Response $response) {
			return (new \Mathielen\Directus\Saml2\LoginCheckHandler())->handleLoginCheck($request, $response);
		}
	],
	'logout' => [
		'method' => 'GET',
		'handler' => function (Request $request, Response $response) {
			return (new \Mathielen\Directus\Saml2\LoginCheckHandler())->handleLogout($request, $response);
		}
	]
];
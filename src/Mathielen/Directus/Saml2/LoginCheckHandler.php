<?php

namespace Mathielen\Directus\Saml2;

use Directus\Application\Http\Request;
use Directus\Application\Http\Response;
use Directus\Database\Schema\SchemaManager;
use Directus\Database\TableGatewayFactory;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Assertion\AttributeStatement;

class LoginCheckHandler
{

	public function handleLoginCheck(Request $request, Response $response)
	{
		$symfonyRequest = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

		$bindingFactory = new BindingFactory();
		$binding = $bindingFactory->getBindingByRequest($symfonyRequest);

		$messageContext = new MessageContext();
		$binding->receive($symfonyRequest, $messageContext);

		$samlResponse = $messageContext->getMessage();

		$nameId = $samlResponse
			->getFirstAssertion()
			->getSubject()
			->getNameID();
		$nameId = $nameId ? $nameId->getValue() : null;

		$attributes = [
			'id' => $nameId,
		];

		foreach ($samlResponse->getAllAssertions() as $assertion) {
			foreach ($assertion->getAllItems() as $item) {
				if (!$item instanceof AttributeStatement) {
					continue;
				}

				foreach ($item->getAllAttributes() as $attribute) {
					$name = $attribute->getFriendlyName();
					$name = empty($name) ? $attribute->getName() : $name;

					$attributes[$name] = $attribute->getAllAttributeValues();
				}
			}
		}

		$uid = $this->getOrCreateUserIfNotExists($attributes);

		$projectName = \Directus\get_api_project_from_request();
		return $response->withRedirect('/'.$projectName.'/auth/sso/saml2/callback?uid='.$uid); //call mandatory auth-callback function
	}

	private function getOrCreateUserIfNotExists(array $attributes)
	{
		$uid = $attributes['uid'][0];

		$tableGateway = TableGatewayFactory::create(SchemaManager::COLLECTION_USERS, ['acl' => false]);
		$user = $tableGateway->findOneBy('email', $uid); //TODO make uid field configurable
		if (!$user) {
			//TODO make role-mapping to directus roles configureable
			if (in_array('ROLE_FRONTEND_ADMINISTRATOR', $attributes['roles'])) {
				$role = 1;
			} elseif (in_array('ROLE_FRONTEND_CMS', $attributes['roles'])) {
				$role = 3;
			} else {
				throw new \RuntimeException("Not allowed");
			}

			$row = $tableGateway->newRow();
			$row->populate([ //TODO make configureable
				'status' => 'active',
				'role' => $role,
				'first_name' => $attributes['givenName'][0],
				'last_name' => $attributes['sn'][0],
				'email' => $uid,
				'timezone' => 'UTC',
				'locale' => $attributes['locale'][0],
			]);
			$row->save();
		}

		return $uid;
	}

	public function handleLogout(Request $request, Response $response)
	{
		return $response->withJson(null);
	}

}
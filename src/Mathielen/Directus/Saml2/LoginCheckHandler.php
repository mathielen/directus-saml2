<?php

namespace Mathielen\Directus\Saml2;

use Directus\Application\Http\Request;
use Directus\Application\Http\Response;
use Directus\Database\Schema\SchemaManager;
use Directus\Database\TableGatewayFactory;
use Directus\Services\UserSessionService;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Helper as LightSamlHelper;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\LogoutResponse;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\SamlConstants;
use function Directus\get_project_config;

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

		$saml2Config = self::getDirectusSaml2Config();
		$userCreatorCls = $saml2Config['user_creator_cls'] ?? DefaultSamlUserCreator::class;

		if (!class_exists($userCreatorCls)) {
			throw new \RuntimeException("User Creator class '$userCreatorCls' does not exist");
		}

		$userCreator = new $userCreatorCls();
		$uid = $userCreator->getOrCreateUserIfNotExists($attributes);

		$projectName = \Directus\get_api_project_from_request();
		return $response->withRedirect('/'.$projectName.'/auth/sso/saml2/callback?uid='.$uid); //call mandatory auth-callback function
	}

	private static function getDirectusSaml2Config(): array
	{
		$projectName = \Directus\get_api_project_from_request();
		$config = get_project_config($projectName);
		$authConfig = $config->get('auth');

		return $authConfig['social_providers']['saml2'];
	}

	public function handleLogout(Request $request, Response $response)
	{
		$symfonyRequest = \Symfony\Component\HttpFoundation\Request::create(
			$request->getUri(),
			$request->getMethod(),
			$request->getParams(),
			$request->getCookieParams(),
			$request->getUploadedFiles(),
			$request->getServerParams(),
			$request->getBody()
		);

		$bindingFactory = new BindingFactory();
		$binding = $bindingFactory->getBindingByRequest($symfonyRequest);

		$messageContext = new MessageContext();
		$binding->receive($symfonyRequest, $messageContext);

		/** @var LogoutRequest $ipRequest */
		$ipRequest = $messageContext->getMessage();

		$saml2Config = self::getDirectusSaml2Config();
		$entityId = $saml2Config['entity_id'];
		$singleLogoutUrl = $saml2Config['single_logout_service'];

		$logoutResponse = new LogoutResponse();
		$logoutResponse
			->setID(LightSamlHelper::generateID())
			->setIssueInstant(new \DateTime())
			->setIssuer(new Issuer($entityId))
			->setInResponseTo($ipRequest->getID())
			->setStatus(new Status(
				new StatusCode(SamlConstants::STATUS_SUCCESS)
			))
			->setDestination($singleLogoutUrl)
			->setRelayState($ipRequest->getRelayState())
		;

		$context = new MessageContext();
		$context->setMessage($logoutResponse);
		$context->setBindingType('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect');

		$bindingFactory = new BindingFactory();
		$symfonyResponse = $bindingFactory
			->create($context->getBindingType())
			->send($context);

		//logout from directus
		global $container;
		$userSessionService = new UserSessionService($container);
		$userSessionService->destroy(['user' => $request->getAttribute('user')]);

		$body = new \Slim\Http\Body(fopen('php://temp', 'r+'));
		$body->write($symfonyResponse->getContent());

		return $response
			->withHeaders($symfonyResponse->headers->all())
			->withBody($body);
	}

}
<?php
namespace Mathielen\Directus\Saml2;

use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Helper;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\SamlConstants;

class SamlProvider
{

	private $options;

	public function __construct(array $options = [])
	{
		$this->options = $options;
	}

	public function getAuthorizationUrl()
	{
		$authnRequest = new AuthnRequest();
		$authnRequest
			->setProtocolBinding(SamlConstants::BINDING_SAML2_HTTP_POST)
			->setID(Helper::generateID())
			->setIssueInstant(new \DateTime())
			->setDestination($this->options['single_sign_on_service'])
			->setIssuer(new Issuer($this->options['entity_id']))
		;

		$bindingFactory = new BindingFactory();
		$redirectBinding = $bindingFactory->create(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);

		$messageContext = new MessageContext();
		$messageContext->setMessage($authnRequest);

		/** @var \Symfony\Component\HttpFoundation\RedirectResponse $httpResponse */
		$httpResponse = $redirectBinding->send($messageContext);

		return $httpResponse->getTargetUrl();
	}

}
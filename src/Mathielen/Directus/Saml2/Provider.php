<?php

namespace Mathielen\Directus\Saml2;

use Directus\Application\Http\Request;
use Directus\Authentication\Sso\AbstractSocialProvider;
use Directus\Authentication\Sso\SocialUser;

class Provider extends AbstractSocialProvider
{

	protected function createProvider()
	{
		$config = [
			'sign_on_service_url' => $this->config['sign_on_service_url'],
			'entity_id' => $this->config['entity_id']
		];

		$this->provider = new SamlProvider($config);

		return $this->provider;
	}

	public function getRequestAuthorizationUrl()
	{
		return $this->provider->getAuthorizationUrl();
	}

	public function request()
	{
		// TODO: what for?
	}

	public function handle()
	{
		/** @var Request $request */
		$request = $this->container->get('request');

		$uid = $request->getQueryParam('uid');

		return $this->getUserFromCode([
			'email' => $uid
		]);
	}

	public function getUserFromCode(array $data)
	{
		return new SocialUser($data);
	}
}

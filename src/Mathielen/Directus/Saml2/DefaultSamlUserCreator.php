<?php

namespace Mathielen\Directus\Saml2;

use Directus\Database\Schema\SchemaManager;
use Directus\Database\TableGatewayFactory;

class DefaultSamlUserCreator
{

	public function getOrCreateUserIfNotExists(array $attributes): string
	{
		//TODO make role-mapping to directus roles configureable
		if (in_array('ROLE_FRONTEND_ADMINISTRATOR', $attributes['roles'])) {
			$directusRole = 1;
		} elseif (in_array('ROLE_FRONTEND_CMS', $attributes['roles'])) {
			$directusRole = 3;
		} else {
			throw new \RuntimeException("Not allowed");
		}

		$uid = $attributes['uid'][0];

		$tableGateway = TableGatewayFactory::create(SchemaManager::COLLECTION_USERS, ['acl' => false]);
		$user = $tableGateway->findOneBy('email', $uid); //TODO make uid field configurable
		if (!$user) {
			$row = $tableGateway->newRow();
			$row->populate([ //TODO make configureable
				'status' => 'active',
				'role' => $directusRole,
				'first_name' => $attributes['givenName'][0],
				'last_name' => $attributes['sn'][0],
				'email' => $uid,
				'timezone' => 'UTC',
				'locale' => $attributes['locale'][0],
				'password' => 'disabled-by-sso'
			]);
			$row->save();
		}

		return $uid;
	}

}
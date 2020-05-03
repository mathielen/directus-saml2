via https://github.com/directus/directus/issues/2675

#Integrate into directus8
1. `git clone` this project somewhere on your server
2. `composer install`
3. symlink the 2 extension points from to respective directories
   ```bash
   ln -s public/extensions/custom/auth/saml2 /path/to/directus/public/extensions/custom/auth/
   ln -s public/extensions/custom/endpoints/saml2 /path/to/directus/public/extensions/custom/endpoints/
   ```
4. setup your directus config file (see config/_example.php)
   ```php
   ...
   'social_providers' => [
     'saml2' => [
         'entity_id' => 'entity-id',
         'sign_on_service_url' => '<url-of-idp>',
     ],
     ...
   ```
   4.a You have to patch the Schema.php file in directus (see directus.patch), because the config seems not to be extendable...
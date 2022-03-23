# API Platform Extensions Bundle

## Description

Adds functionality to api platform like /me endpoint and _global_search

## Install 

composer require webstack/api-platform-extensions-bundle:[version]

## Configure

After installing the plugin you will need to add some config files to you're project:

- config/packages/webstack_api_platform_extensions.yaml<br>
<code>
webstack_api_platform_extensions:<br>
identifier_class: App\Entity\%UserEntity%
</code>
- config/routes/api_platform.yaml<br/>
<code>
  app_extra:<br/>
  resource: '@WebstackApiPlatformExtensionsBundle/Resources/config/routing/routing.xml'
  <br/>prefix: /api
</code>

# API Platform extensions bundle
What is API Platform and how do we extend it?

# Filters
These filters are added by this bundle:

## GlobalSearchFilter (`api_platform.doctrine.orm.global_search_filter`)
Searches (recursively?) through the specified columns (or all of them, scary stuff) of an entity on whose endpoint it's activated.

Configure the filter as a service (`config/services/api_platform/search/[$domain/]$entity.yaml`):
```yaml
services:
  api.resource.region.global.search_filter:
    parent: 'api_platform.doctrine.orm.global_search_filter'
    arguments: [ {
      'name': 'partial',
      'depot.description': 'partial',
    } ]
    tags: [ { name: 'api_platform.filter', id: 'api.region.global_search_filter' } ]
    autowire: false
    autoconfigure: false
```

And apply it to the appropriate resource:
```yaml
App\Entity\Transport\Region:
  attributes:
    route_prefix: /transport
    filters:
      - 'api.region.global_search_filter'
```

Now when a user calls the API with `?_global_search=foo`, the query will become something like this:

```sql
SELECT 
    r.*
FROM 
    region r
LEFT JOIN
    depot d
ON
    d.id = r.depot_id
WHERE
    r.name LIKE '%foo%' 
    OR d.description LIKE '%foo%'
```

## AliasSearchFilter (`api_platform.doctrine.orm.alias_search_filter`)
Works as the regular search filter, but lets you rename or alias (nested) properties in the URL.  Pass the properties and aliases as separate DI arguments:

```yaml
services:
  api.some_entity.deeply_nested_prop_filter:
    parent: 'api_platform.doctrine.orm.alias_search_filter'
    arguments:
      $properties:
        deeply.nested.property: 'exact'
      $aliases:
        myProp: 'deeply.nested.property'
    tags: [ { name: 'api_platform.filter' } ]
```

And apply it to the appropriate resource:
```yaml
App\Entity\SomeEntity:
  attributes:
    route_prefix: /some
    filters:
      - 'api.some_entity.deeply_nested_prop_filter'
```

Now to hit this filter, the URL using the default SearchFilter would be:

    /some?deeply.nested.property=foo

This alias search filter adds a new query string parameter that it'll map to the configured alias:

    /some?myProp=foo

## OrSearchFilter (`api_platform.doctrine.orm.or_search_filter`)
TODO

## UuidFilter (`api_platform.doctrine.orm.uuid_filter`)
For looking up nested entities by their UUID, because API Platform doesn't support that (see https://github.com/api-platform/core/pull/3774, was reverted because it broke date search).

Service configuration:
```yaml
services:
  api.resource.transport_position.vehicle_filter:
    parent: 'api_platform.doctrine.orm.uuid_filter'
    arguments: [ {
      vehicle.id: 'exact'
    } ]
    tags: [ { name: 'api_platform.filter', id: 'api.transport_position.vehicle_filter' } ]
    autowire: false
    autoconfigure: false
    public: false
```

API Platform entity configuration:
```yaml
App\Entity\Transport\Position:
  collectionOperations:
    get:
      filters:
        - 'api.transport_position.vehicle_filter'
```

Now the API caller can filter using `GET .../transport_positions?vehicle.id=$uuid`.

# Routes
The bundle introduces the following route(s):

## `/me`
Get info about the caller. Includes a SwaggerDecorator to generate OpenAPI documentation about this endpoint.

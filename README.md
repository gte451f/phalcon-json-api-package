# phalcon-json-api-package
A composer package designed to help you create a JSON:API in Phalcon

What happens when a PHP developer wants to create an api to drive their client-side SPA?  Well you start with [Phalcon](http://phalconphp.com/en/) (A modern super fast framework) loosely follow the [JSON:API](http://jsonapi.org/) and package it up for [Composer](https://getcomposer.org/).  The result is the phalcon-json-api package (herafter referred to as the API) so enjoy.

# System requirements
- Phalcon 2.x
- SQL persistance layer (ie. MYSQL, MariaDB)  Make sure the database is supported by [Phalcon's Databse Abstation Layer](https://docs.phalconphp.com/en/latest/reference/db.html).
- PHP Version 5.5 or greater?

# How is Phalcon used?
Phalcon is the underlying framework this project depends on.  Any user of the API package will need to have a working installation of Phalcon already installed on their system.  The API makes extensive use of Phalcon sub systems including the ORM, Router and Service Locator.

# How is JSONAPI used?
The Phalcon JSON API package attempts to follow the JSON API as closely as possible.  There are several enhancements this project incorporates beyond the JSON API specification.

# How can I quickly see this project in action?
New folks are encouraged to download and install the [sister project](https://github.com/gte451f/phalcon-json-api) that acts as a simple example application to demonstrate how one could use the API.  This simple application include all the building blocks that make up the api including use of traditional Phalcon objects like Controllers and Models along with objects designed for use in the API such as Entities, Route and SearchHelpers.

# How can I install this project?
Aside from meeting the system requirements, you should include this project in your composer file.  Here is an example composer file that includes a few extra libraries needed for testing and timing api respones.

```
{
    "require": {
        "jsanc623/phpbenchtime": "dev-master",
        "gte451f/phalcon-json-api-package": "dev-master"
    },
    "require-dev": {
        "codeception/codeception": "*",
        "flow/jsonpath": "dev-master"
    }
}
```

# Where is the wiki?
Lots more help is available [here](https://github.com/gte451f/phalcon-json-api-package/wiki).

# Where do babies come from?
![The Stork Silly!](http://img2.wikia.nocookie.net/__cb20120518150112/disney/images/2/2f/Dumbo-disneyscreencaps_com-672.jpg "Dumbo Photo")

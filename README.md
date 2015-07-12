# phalcon-json-api-package
A composer package designed to help you create a JSON:API in Phalcon

What happens when a PHP developer wants to create an api to drive their client-side SPA?  Well you start with [Phalcon](http://phalconphp.com/en/) (A modern super fast framework) loosely follow the [JSON:API](http://jsonapi.org/) and package it up for [Composer](https://getcomposer.org/).  The result is the phalcon-json-api package (herafter referred to as the API) so enjoy.

# System requirements
- Phalcon 1.X (The latest release in the 1.x series)  The 2.x series has not been tested yet.
- SQL persistance layer (ie. MYSQL, MariaDB)  Make sure the database is supported by [Phalcon's Databse Abstation Layer](https://docs.phalconphp.com/en/latest/reference/db.html).
- PHP Version 5.5 or greater?

# How is Phalcon used?
Phalcon is the underlying framework this project depends on.  Any user of the API package will need to have a working installation of Phalcon already installed on their system.  The API makes extensive use of Phalcon sub systems including the ORM, Router and Service Locator.

# How is JSONAPI used?
The Phalcon JSON API package attempts to follow the JSON API as closely as possible.  There are several enhancements this project incorporates beyond the JSON API specification.

# How can I get started?
New folks are encouraged to download and install the [sister project](https://github.com/gte451f/phalcon-json-api) that acts as a simple example application to demonstrate how one could use the API.  This simple application include all the building blocks that make up the api including use of traditional Phalcon objects like Controllers and Models along with objects designed for use in the API such as Entities, Route and SearchHelpers.

# Within the context of the API, please describe

# What is an API End Point ?
And end point in the API represents a resource which client applications can perform actions on.  Each end point can support any number of defined RESTish verbs like POST, GET etc. The minimum set of files an end point requires is a Controller, Model, Entity and Route.

# What is a Model and how is it used?
A model is a normal Phalcon object that you are probably already familiar with.  The API contains an extended version of a [Phalcon Model](https://docs.phalconphp.com/en/latest/reference/models.html) that you should base all models off of from within your application.  A model represents a table from your persistence layer.  Each Phalcon model expects a primary key which is often detected via Phalcon::ORM.  The upstream Entity object depends on a model to perform crud operations.  

# What is an Entity and how is it used?
An entity is a custom contstruct of the API.  It acts as a layer of abstraction that sits on top of and coordinates among models and their relationsionships.  It also takes into account an associated searchHelper object that further describes how the entity should gather and data before turning to the requesting client.

# What is a Controller and how is it used?
A controller is also a normal Phalcon object with some specific enhancements for performaing it's responsabilities.  It catches requests from a calling client (ie a web browser) and gathers any specific data beforing delegating work to an underlying entity.  The controller also formats data returned from an Entity into the correct format configured in the API.  JSON repsonses are the most tested, but an abstraction layer provides an option to use a different response format.

# What is a searchHelper and how is it used?
A SearchHelper works in conjunction with an entity to further guide the API in gathering and formating data.  Specifically, a SearhcHelper drives entity behavior around building relationships, filtering data and showing or hiding columns.
